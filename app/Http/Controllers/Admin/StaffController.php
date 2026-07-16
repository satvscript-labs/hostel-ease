<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\StaffSalaryPayment;
use App\Services\ActivityLogger;
use App\Support\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function __construct(
        protected ActivityLogger $logger,
        protected \App\Services\ImageService $imageService,
        protected \App\Services\StorageService $storageService
    ) {
    }

    public function index(Request $request): View
    {
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        $staff = Staff::orderByDesc('is_active')->orderBy('name')->get()->map(function ($s) use ($monthStart, $monthEnd) {
            $s->present_this_month = StaffAttendance::where('staff_id', $s->id)
                ->whereBetween('date', [$monthStart, $monthEnd])->whereIn('status', ['present', 'half_day'])->count();
            $s->paid_this_month = (float) StaffSalaryPayment::where('staff_id', $s->id)
                ->whereBetween('salary_month', [$monthStart, $monthEnd])->sum('amount');

            return $s;
        });

        $summary = [
            'active' => $staff->where('is_active', true)->count(),
            'total' => $staff->count(),
            'payroll' => (float) Staff::active()->sum('monthly_salary'),
        ];

        // Attendance Data for the tab
        $date = $request->filled('date') ? Carbon::parse($request->date('date'))->toDateString() : now()->toDateString();
        $marks = StaffAttendance::whereDate('date', $date)->get()->keyBy('staff_id');

        return view('admin.staff.index', compact('staff', 'summary', 'date', 'marks'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateStaff($request);

        if ($request->hasFile('photo')) {
            $processed = $this->imageService->compressAndConvertToWebp($request->file('photo'), 800, 800, 80);
            $data['photo'] = $this->storageService->store($processed['content'], 'staff/photos', 'public', $processed['extension']);
        }

        if ($request->hasFile('aadhaar_file')) {
            $processed = $this->imageService->compressAndConvertToWebp($request->file('aadhaar_file'), 1600, 1600, 80);
            $data['aadhaar_file'] = $this->storageService->store($processed['content'], 'staff/documents', 'public', $processed['extension']);
        }

        Staff::create($data);

        return back()->with('success', 'Staff added.');
    }

    public function update(Request $request, Staff $staff): RedirectResponse
    {
        $data = $this->validateStaff($request, $staff);

        if ($request->hasFile('photo')) {
            if ($staff->photo) {
                $this->storageService->delete($staff->photo, 'public');
            }
            $processed = $this->imageService->compressAndConvertToWebp($request->file('photo'), 800, 800, 80);
            $data['photo'] = $this->storageService->store($processed['content'], 'staff/photos', 'public', $processed['extension']);
        }

        if ($request->hasFile('aadhaar_file')) {
            if ($staff->aadhaar_file) {
                $this->storageService->delete($staff->aadhaar_file, 'public');
            }
            $processed = $this->imageService->compressAndConvertToWebp($request->file('aadhaar_file'), 1600, 1600, 80);
            $data['aadhaar_file'] = $this->storageService->store($processed['content'], 'staff/documents', 'public', $processed['extension']);
        }

        $staff->update($data);

        return back()->with('success', 'Staff updated.');
    }

    public function destroy(Staff $staff): RedirectResponse
    {
        $staff->delete();

        return back()->with('success', 'Staff removed.');
    }

    public function show(Staff $staff): View
    {
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();
        $attendance = StaffAttendance::where('staff_id', $staff->id)->whereBetween('date', [$monthStart, $monthEnd])->orderBy('date')->get();
        $counts = ['present' => 0, 'absent' => 0, 'half_day' => 0, 'leave' => 0];
        foreach ($attendance as $a) {
            $counts[$a->status]++;
        }
        $payments = StaffSalaryPayment::where('staff_id', $staff->id)->orderByDesc('paid_on')->get();

        // Feeds the Pay Salary modal's mode picker — same tenant vocabulary
        // as every other payment in the app (W6.2).
        $paymentModes = \App\Models\PaymentMode::active()->orderBy('sort_order')->orderBy('name')->get();

        return view('admin.staff.show', compact('staff', 'counts', 'attendance', 'payments', 'paymentModes'));
    }



    public function saveAttendance(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'status' => ['required', 'array'],
            'status.*' => ['required', Rule::in(['present', 'absent', 'half_day', 'leave'])],
        ]);
        $date = Carbon::parse($data['date'])->startOfDay();
        foreach ($data['status'] as $staffId => $status) {
            StaffAttendance::updateOrCreate(
                ['staff_id' => $staffId, 'date' => $date],
                ['hostel_id' => Tenant::id(), 'status' => $status],
            );
        }

        return redirect()->route('admin.staff.index', ['tab' => 'attendance', 'date' => $date->toDateString()])->with('success', 'Attendance saved.');
    }

    public function paySalary(Request $request, Staff $staff): RedirectResponse
    {
        $data = $request->validate([
            'salary_month' => ['required', 'date_format:Y-m'],
            'amount' => ['required', 'numeric', 'min:1', 'max:9999999'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            // Was a free string — salaries now spend through the same tenant
            // payment_modes vocabulary as every other outflow (W6.2).
            'mode' => ['required', Rule::in(\App\Models\PaymentMode::active()->pluck('code')->all())],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $month = Carbon::parse($data['salary_month'].'-01')->startOfMonth();

        // Salary + its expense mirror are one fact recorded twice, so they're
        // created in one transaction (owner decision, W6.2). Before this,
        // salaries paid through the staff module never reached Expenses or
        // Net Profit at all — and an owner who ALSO logged them by hand
        // double-counted. The mirror is what makes payroll visible to the
        // P&L exactly once.
        \Illuminate\Support\Facades\DB::transaction(function () use ($data, $staff, $month) {
            $payment = StaffSalaryPayment::create([
                'hostel_id' => Tenant::id(),
                'staff_id' => $staff->id,
                'salary_month' => $month,
                'amount' => $data['amount'],
                'paid_on' => $data['paid_on'],
                'mode' => $data['mode'],
                'notes' => $data['notes'] ?? null,
            ]);

            \App\Models\Expense::create([
                'hostel_id' => Tenant::id(),
                'category' => 'staff_salary',
                'title' => "Salary — {$staff->name} · {$month->format('M Y')}",
                'amount' => $data['amount'],
                'expense_date' => $data['paid_on'],
                'paid_to' => $staff->name,
                'mode' => $data['mode'],
                'notes' => $data['notes'] ?? null,
                'recorded_by' => auth()->id(),
                'staff_salary_payment_id' => $payment->id,
            ]);
        });

        return back()->with('success', "Salary recorded for {$staff->name} — logged to expenses.");
    }

    public function deleteSalary(Staff $staff, StaffSalaryPayment $payment): RedirectResponse
    {
        abort_unless($payment->staff_id === $staff->id, 404);

        // The mirror goes with the salary — leaving it behind would keep a
        // phantom expense inflating the P&L (the FK's nullOnDelete is only a
        // backstop; this is the real cascade).
        \Illuminate\Support\Facades\DB::transaction(function () use ($payment) {
            \App\Models\Expense::where('staff_salary_payment_id', $payment->id)->get()
                ->each->delete();
            $payment->delete();
        });

        return back()->with('success', 'Salary entry removed — its expense entry went with it.');
    }

    protected function validateStaff(Request $request, ?Staff $staff = null): array
    {
        // Normalize mobile to +91 format
        if ($request->has('mobile') && !blank($request->mobile)) {
            $digits = substr(preg_replace('/\D+/', '', $request->mobile), -10);
            $request->merge(['mobile' => '+91' . $digits]);
        }

        if ($request->has('aadhaar_number') && !blank($request->aadhaar_number)) {
            $request->merge(['aadhaar_number' => preg_replace('/\D+/', '', $request->aadhaar_number)]);
        }

        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'designation' => ['nullable', 'string', 'max:100'],
            'mobile' => ['required', 'regex:/^\+91\d{10}$/'],
            'aadhaar_number' => ['required', 'digits:12'],
            'aadhaar_file' => [$staff ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'monthly_salary' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'join_date' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
