<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\PaymentMode;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\StaffSalaryPayment;
use App\Services\ActivityLogger;
use App\Services\ImageService;
use App\Services\StorageService;
use App\Support\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StaffController extends Controller
{
    /** field => [storage directory, max dimension] */
    private const IMAGE_FIELDS = [
        'photo' => ['staff/photos', 800],
        'aadhaar_file' => ['staff/documents', 1600],
    ];

    public function __construct(
        protected ActivityLogger $logger,
        protected ImageService $imageService,
        protected StorageService $storageService
    ) {
    }

    public function index(Request $request): View
    {
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        $search = $request->input('search');
        $status = $request->input('status');

        // W7.1: server-side search + status filter + pagination. The old page
        // loaded every staff row and filtered client-side by interpolating each
        // name into an Alpine expression — so a name with an apostrophe
        // ("O'Brien") produced a JS syntax error and the card vanished.
        //
        // The per-row attendance/salary counts were two queries EACH, inside
        // the loop (same N+1 W6.4 replaced with withCount/withSum).
        $staff = Staff::query()
            ->when($status === 'removed', fn ($q) => $q->onlyTrashed())
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($search, function ($q) use ($search) {
                // Mobiles are stored +91XXXXXXXXXX, so a typed "98765 43210"
                // only matches once stripped to digits. Guard the empty case:
                // a text search strips to '', and LIKE '%%' matches EVERY row —
                // searching a name would silently return the whole directory.
                $digits = preg_replace('/\D+/', '', $search);

                $q->where(function ($qq) use ($search, $digits) {
                    $qq->where('name', 'like', "%{$search}%")
                        ->orWhere('designation', 'like', "%{$search}%");

                    if ($digits !== '') {
                        $qq->orWhere('mobile', 'like', "%{$digits}%");
                    }
                });
            })
            ->withCount(['attendances as present_this_month' => fn ($q) => $q
                ->whereBetween('date', [$monthStart, $monthEnd])
                ->whereIn('status', ['present', 'half_day'])])
            ->withSum(['salaryPayments as paid_this_month' => fn ($q) => $q
                ->whereBetween('salary_month', [$monthStart, $monthEnd])], 'amount')
            ->orderByDesc('is_active')->orderBy('name')
            ->paginate(12)->withQueryString();

        // Whole-book truth — never filter-scoped, a search must not shrink it.
        $summary = [
            'active' => Staff::where('is_active', true)->count(),
            'total' => Staff::count(),
            'payroll' => (float) Staff::where('is_active', true)->sum('monthly_salary'),
            'paid_this_month' => (float) StaffSalaryPayment::whereBetween('salary_month', [$monthStart, $monthEnd])->sum('amount'),
        ];

        // --- Attendance tab (rebuilt in W7.3; reads unchanged here) ---
        $date = $request->filled('date') ? Carbon::parse($request->date('date'))->toDateString() : now()->toDateString();
        $roster = Staff::where('is_active', true)->orderBy('name')->get();
        $marks = StaffAttendance::whereDate('date', $date)->get()->keyBy('staff_id');

        // Feeds the Pay Salary sheet. The Directory tab's copy hardcoded
        // cash/upi/bank while paySalary() validates against the tenant's real
        // payment_modes — and there IS no 'bank' code (the default vocabulary
        // is cash/upi/cheque/rtgs), so paying by "Bank Transfer" from this tab
        // failed validation every time. Only show() loaded modes; W6.2 fixed
        // that copy and missed this one.
        $paymentModes = PaymentMode::active()->ordered()->get();

        return view('admin.staff.index', compact(
            'staff', 'summary', 'date', 'roster', 'marks', 'paymentModes', 'search', 'status'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateStaff($request);

        foreach (self::IMAGE_FIELDS as $field => [$dir, $size]) {
            $data[$field] = $this->storeImage($request, $field, $dir, $size);
        }

        // hostel_id is auto-filled by BelongsToHostel on create.
        $staff = Staff::create($data + ['is_active' => $request->boolean('is_active')]);

        $this->logger->log('staff.create', "Staff added — {$staff->name}"
            .($staff->designation ? " ({$staff->designation})" : '')
            .' at '.hostelease_money($staff->monthly_salary).'/month', $staff);

        return back()->with('success', 'Staff added.');
    }

    public function update(Request $request, Staff $staff): RedirectResponse
    {
        $data = $this->validateStaff($request, $staff);

        // Only replace an image when a new one was actually uploaded — an
        // absent file field must never blank the stored path.
        foreach (self::IMAGE_FIELDS as $field => [$dir, $size]) {
            unset($data[$field]);

            if ($path = $this->storeImage($request, $field, $dir, $size)) {
                if ($staff->{$field}) {
                    $this->storageService->delete($staff->{$field}, 'public');
                }
                $data[$field] = $path;
            }
        }

        $staff->update($data + ['is_active' => $request->boolean('is_active')]);

        $this->logger->log('staff.update', "Staff updated — {$staff->name}", $staff);

        return back()->with('success', 'Staff updated.');
    }

    /**
     * Owner decision (W7.1): salary history and its expense mirrors SURVIVE —
     * money that left is money that left, exactly as W6.2 rules it for
     * Expenses. So this is a soft delete: the member leaves the directory,
     * their record stays reachable under the "Removed" filter, and every rupee
     * already paid stays in the P&L where it belongs.
     *
     * Why the profile must stay reachable: Expenses refuses to delete a salary
     * mirror and points at the staff member's page. If that page 404'd for a
     * removed member, the mirror would be un-deletable from BOTH sides — a
     * permanent phantom expense. `show` and `salary.destroy` bind withTrashed
     * for exactly this reason.
     */
    public function destroy(Staff $staff): RedirectResponse
    {
        $staff->delete();

        $this->logger->log('staff.delete', "Staff removed — {$staff->name}. Salary history kept.", $staff);

        return back()->with('success', "{$staff->name} removed — salary history kept.");
    }

    public function restore(int $staff): RedirectResponse
    {
        $record = Staff::onlyTrashed()->findOrFail($staff);
        $record->restore();

        $this->logger->log('staff.restore', "Staff restored — {$record->name}", $record);

        return back()->with('success', "{$record->name} restored.");
    }

    public function show(Staff $staff): View
    {
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        $attendance = $staff->attendances()
            ->whereBetween('date', [$monthStart, $monthEnd])
            ->orderBy('date')->get();

        $counts = ['present' => 0, 'absent' => 0, 'half_day' => 0, 'leave' => 0];
        foreach ($attendance as $a) {
            $counts[$a->status]++;
        }

        $payments = $staff->salaryPayments()->latest('salary_month')->latest('paid_on')->get();

        // Salary rows store the mode CODE; resolve names once rather than per
        // row, and keep showing a mode that has since been deactivated.
        $modeNames = PaymentMode::pluck('name', 'code');

        $paymentModes = PaymentMode::active()->ordered()->get();

        return view('admin.staff.show', compact(
            'staff', 'counts', 'attendance', 'payments', 'paymentModes', 'modeNames'
        ));
    }

    public function saveAttendance(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'status' => ['required', 'array'],
            // The keys are staff ids straight off the form — unvalidated, they
            // let a crafted POST write attendance rows against ANOTHER
            // hostel's staff (W7.1). Same class as the W6.4 tenant fixes.
            'status.*' => ['required', Rule::in(['present', 'absent', 'half_day', 'leave'])],
        ]);

        $date = Carbon::parse($data['date'])->startOfDay();

        $ownIds = Staff::where('is_active', true)->pluck('id')->flip();

        foreach ($data['status'] as $staffId => $status) {
            if (! $ownIds->has((int) $staffId)) {
                continue;
            }

            StaffAttendance::updateOrCreate(
                ['staff_id' => (int) $staffId, 'date' => $date],
                ['hostel_id' => Tenant::id(), 'status' => $status],
            );
        }

        return redirect()->route('admin.staff.index', ['tab' => 'attendance', 'date' => $date->toDateString()])
            ->with('success', 'Attendance saved.');
    }

    public function paySalary(Request $request, Staff $staff): RedirectResponse
    {
        $data = $request->validate([
            'salary_month' => ['required', 'date_format:Y-m'],
            'amount' => ['required', 'numeric', 'min:1', 'max:9999999'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            // Was a free string — salaries now spend through the same tenant
            // payment_modes vocabulary as every other outflow (W6.2).
            'mode' => ['required', Rule::in(PaymentMode::active()->pluck('code')->all())],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $month = Carbon::parse($data['salary_month'].'-01')->startOfMonth();

        // Salary + its expense mirror are one fact recorded twice, so they're
        // created in one transaction (owner decision, W6.2). Before this,
        // salaries paid through the staff module never reached Expenses or
        // Net Profit at all — and an owner who ALSO logged them by hand
        // double-counted. The mirror is what makes payroll visible to the
        // P&L exactly once.
        DB::transaction(function () use ($data, $staff, $month) {
            $payment = StaffSalaryPayment::create([
                'hostel_id' => Tenant::id(),
                'staff_id' => $staff->id,
                'salary_month' => $month,
                'amount' => $data['amount'],
                'paid_on' => $data['paid_on'],
                'mode' => $data['mode'],
                'reference_number' => $data['reference_number'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            Expense::create([
                'hostel_id' => Tenant::id(),
                'category' => 'staff_salary',
                'title' => "Salary — {$staff->name} · {$month->format('M Y')}",
                'amount' => $data['amount'],
                'expense_date' => $data['paid_on'],
                'paid_to' => $staff->name,
                'mode' => $data['mode'],
                'reference_number' => $data['reference_number'] ?? null,
                'notes' => $data['notes'] ?? null,
                'recorded_by' => auth()->id(),
                'staff_salary_payment_id' => $payment->id,
            ]);

            $this->logger->log('staff.salary', "Salary paid — {$staff->name} · {$month->format('M Y')} · "
                .hostelease_money($data['amount']), $payment);
        });

        return back()->with('success', "Salary recorded for {$staff->name} — logged to expenses.");
    }

    public function deleteSalary(Staff $staff, StaffSalaryPayment $payment): RedirectResponse
    {
        abort_unless($payment->staff_id === $staff->id, 404);

        // The mirror goes with the salary — leaving it behind would keep a
        // phantom expense inflating the P&L (the FK's nullOnDelete is only a
        // backstop; this is the real cascade).
        DB::transaction(function () use ($payment, $staff) {
            Expense::where('staff_salary_payment_id', $payment->id)->get()
                ->each->delete();
            $payment->delete();

            $this->logger->log('staff.salary.delete', "Salary entry removed — {$staff->name} · "
                .$payment->salary_month->format('M Y').' · '.hostelease_money($payment->amount), $staff);
        });

        return back()->with('success', 'Salary entry removed — its expense entry went with it.');
    }

    /**
     * Compress + store an uploaded image, or null when none was sent.
     */
    protected function storeImage(Request $request, string $field, string $directory, int $maxDimension): ?string
    {
        if (! $request->hasFile($field)) {
            return null;
        }

        $processed = $this->imageService->compressAndConvertToWebp($request->file($field), $maxDimension, $maxDimension, 80);

        return $this->storageService->store($processed['content'], $directory, 'public', $processed['extension']);
    }

    protected function validateStaff(Request $request, ?Staff $staff = null): array
    {
        // The model normalises mobile on write (W7.1) — this only strips the
        // separators a human types so the regex sees digits.
        if (filled($request->mobile)) {
            $request->merge(['mobile' => substr(preg_replace('/\D+/', '', $request->mobile), -10)]);
        }

        if (filled($request->aadhaar_number)) {
            $request->merge(['aadhaar_number' => preg_replace('/\D+/', '', $request->aadhaar_number)]);
        }

        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'designation' => ['nullable', 'string', 'max:100'],
            'mobile' => ['required', 'digits:10'],
            'aadhaar_number' => ['required', 'digits:12'],
            'aadhaar_file' => [$staff ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'monthly_salary' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'join_date' => ['nullable', 'date', 'before_or_equal:today'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
