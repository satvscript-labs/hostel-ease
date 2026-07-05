<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStudentRequest;
use App\Models\PaymentMode;
use App\Models\PocketMoneyTransaction;
use App\Models\Student;
use App\Services\ActivityLogger;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StudentController extends Controller
{
    public function __construct(
        protected ActivityLogger $logger,
        protected PaymentService $payments,
    ) {
    }

    /**
     * Collect a lump amount and apply it to the student's unpaid dues (oldest
     * first) so Outstanding drops by exactly that amount. AC is kept separate
     * from fees via scope. Mirrors the mobile app's collect.
     */
    public function collect(Request $request, Student $student): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:9999999'],
            'mode' => ['required', Rule::in(PaymentMode::active()->pluck('code')->all())],
            'payment_type' => ['required', Rule::in(array_keys(config('hostelease.payment_types')))],
            'scope' => ['required', Rule::in(['fees', 'ac'])],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $obligations = [];
        if ($data['scope'] === 'ac') {
            foreach ($student->acBillShares()->where('status', '!=', 'paid')->orderBy('id')->get() as $o) {
                $obligations[] = $o;
            }
        } else {
            foreach ($student->semesterFees()->where('status', '!=', 'paid')->orderBy('id')->get() as $o) {
                $obligations[] = $o;
            }
            foreach ($student->monthlyRents()->where('status', '!=', 'paid')->orderBy('rent_month')->get() as $o) {
                $obligations[] = $o;
            }
        }

        $base = [
            'student_id' => $student->id,
            'mode' => $data['mode'],
            'paid_on' => $data['paid_on'],
            'reference_number' => $data['reference_number'] ?? null,
            'remarks' => $data['remarks'] ?? null,
        ];
        $remaining = (float) $data['amount'];

        foreach ($obligations as $ob) {
            if ($remaining <= 0.001) {
                break;
            }
            $bal = (float) $ob->balance;
            if ($bal <= 0) {
                continue;
            }
            $pay = min($remaining, $bal);
            $this->payments->record(array_merge($base, ['amount' => round($pay, 2), 'payment_type' => $pay >= $bal ? 'full' : 'partial']), $ob);
            $remaining -= $pay;
        }
        if ($remaining > 0.001) {
            $this->payments->record(array_merge($base, ['amount' => round($remaining, 2), 'payment_type' => 'advance']));
        }

        return back()->with('success', 'Payment collected — outstanding updated.');
    }

    /**
     * Record a "promise to pay" date + note against the student's unpaid dues of
     * a scope (fees or AC). Used from the collect dialog when no money is taken
     * now but the student commits to a date. Mirrors the mobile app.
     */
    public function promise(Request $request, Student $student): RedirectResponse
    {
        $data = $request->validate([
            'scope' => ['required', Rule::in(['fees', 'ac'])],
            'promise_date' => ['required', 'date', 'after_or_equal:today'],
            'promise_note' => ['nullable', 'string', 'max:255'],
        ]);

        $obligations = $this->unpaidObligations($student, $data['scope']);
        if (empty($obligations)) {
            return back()->with('error', 'No unpaid dues to set a promise against.');
        }

        foreach ($obligations as $o) {
            $o->forceFill([
                'promise_date' => $data['promise_date'],
                'promise_note' => $data['promise_note'] ?? null,
            ])->save();
        }

        $this->logger->log('promise.set',
            "Promise to pay ({$data['scope']}) set for {$data['promise_date']} — {$student->name}", $student);

        return back()->with('success', 'Promise to pay saved.');
    }

    /**
     * Unpaid obligations for a scope, oldest first. Fees and AC never mix.
     *
     * @return array<int, \Illuminate\Database\Eloquent\Model>
     */
    protected function unpaidObligations(Student $student, string $scope): array
    {
        $obligations = [];
        if ($scope === 'ac') {
            foreach ($student->acBillShares()->where('status', '!=', 'paid')->orderBy('id')->get() as $o) {
                $obligations[] = $o;
            }
        } else {
            foreach ($student->semesterFees()->where('status', '!=', 'paid')->orderBy('id')->get() as $o) {
                $obligations[] = $o;
            }
            foreach ($student->monthlyRents()->where('status', '!=', 'paid')->orderBy('rent_month')->get() as $o) {
                $obligations[] = $o;
            }
        }

        return $obligations;
    }

    public function index(Request $request): View
    {
        $students = Student::query()
            ->with('activeAssignment.bed.room')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('occupation'), fn ($q) => $q->where('occupation_type', $request->occupation))
            ->orderByDesc('created_at')
            ->get();

        return view('admin.students.index', compact('students'));
    }

    public function create(): View
    {
        return view('admin.students.create');
    }

    public function store(StoreStudentRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('students/photos', 'public');
        }

        $student = Student::create($data);
        $this->logger->log('student.create', "Added student {$student->name}", $student);

        return redirect()->route('admin.students.show', $student)
            ->with('success', 'Student added successfully.');
    }

    public function show(Student $student): View
    {
        $student->load([
            'activeAssignment.bed.room.floor',
            'assignments' => fn ($q) => $q->with('bed.room')->orderByDesc('join_date'),
            'documents' => fn ($q) => $q->latest(),
            'semesterFees' => fn ($q) => $q->orderByDesc('semester'),
            'monthlyRents' => fn ($q) => $q->orderByDesc('rent_month'),
            'acBillShares' => fn ($q) => $q->with('acBill.room')->orderByDesc('id'),
        ]);

        // Every fee type the student owes, linked across the software, with the
        // unpaid ones flagged so the profile shows real outstanding balances.
        $dues = $this->collectDues($student);
        $feesDues = $dues->whereIn('pay_type', ['semester_fee', 'monthly_rent'])->values();
        $acDues = $dues->where('pay_type', 'ac_bill_student')->values();

        $paymentSummary = [
            'total_paid' => (float) $student->payments()->sum('amount'),
            'last_payment' => $student->payments()->latest('paid_on')->first(),
            'total_billed' => (float) $dues->sum('total'),
            'outstanding' => (float) $dues->sum('balance'),
            'outstanding_fees' => (float) $feesDues->sum('balance'),
            'outstanding_ac' => (float) $acDues->sum('balance'),
        ];

        // These query tables added in later migrations (pocket_money,
        // payment_modes). Degrade gracefully if the server hasn't migrated yet
        // so the profile never hard-500s on a half-updated deploy.
        try {
            $pocketBalance = PocketMoneyTransaction::balanceFor($student->id);
        } catch (\Throwable $e) {
            $pocketBalance = 0.0;
        }
        try {
            $paymentModes = PaymentMode::active()->ordered()->get();
        } catch (\Throwable $e) {
            $paymentModes = collect();
        }

        // QR linking to this profile — degrades gracefully if the package/GD is unavailable.
        $qrSvg = null;
        try {
            $qrSvg = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')
                ->size(150)->margin(0)
                ->generate(route('admin.students.show', $student));
        } catch (\Throwable $e) {
            $qrSvg = null;
        }

        return view('admin.students.show', compact('student', 'paymentSummary', 'qrSvg', 'dues', 'feesDues', 'acDues', 'pocketBalance', 'paymentModes'));
    }

    /**
     * Build a unified list of the student's fee obligations across all modules
     * (semester fees, monthly rents, AC bill shares) for the profile view.
     */
    protected function collectDues(Student $student): \Illuminate\Support\Collection
    {
        $dues = collect();

        foreach ($student->semesterFees as $f) {
            $yearly = ($f->period_type ?? 'semester') === 'yearly';
            $dues->push([
                'kind' => $yearly ? 'Yearly Fee' : 'Semester Fee',
                'label' => $yearly ? "Year {$f->semester}" : "Semester {$f->semester}",
                'total' => (float) $f->total_fee,
                'paid' => (float) $f->paid_amount,
                'balance' => (float) $f->balance,
                'status' => $f->status,
                'due_date' => $f->due_date,
                'pay_type' => 'semester_fee',
                'pay_id' => $f->id,
            ]);
        }

        foreach ($student->monthlyRents as $r) {
            $dues->push([
                'kind' => 'Monthly Rent',
                'label' => optional($r->rent_month)->format('M Y') ?? '—',
                'total' => (float) $r->amount,
                'paid' => (float) $r->paid_amount,
                'balance' => (float) $r->balance,
                'status' => $r->status,
                'due_date' => $r->due_date,
                'pay_type' => 'monthly_rent',
                'pay_id' => $r->id,
            ]);
        }

        foreach ($student->acBillShares as $a) {
            $dues->push([
                'kind' => 'AC Bill',
                'label' => optional(optional($a->acBill)->bill_month)->format('M Y')
                    ? 'AC '.$a->acBill->bill_month->format('M Y').(optional(optional($a->acBill)->room)->room_number ? ' · Room '.$a->acBill->room->room_number : '')
                    : 'AC Bill',
                'total' => (float) $a->amount,
                'paid' => (float) $a->paid_amount,
                'balance' => max(0, (float) $a->amount - (float) $a->paid_amount),
                'status' => $a->status,
                'due_date' => $a->promise_date,
                'pay_type' => 'ac_bill_student',
                'pay_id' => $a->id,
            ]);
        }

        return $dues;
    }

    public function edit(Student $student): View
    {
        return view('admin.students.edit', compact('student'));
    }

    public function update(StoreStudentRequest $request, Student $student): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('photo')) {
            if ($student->photo) {
                Storage::disk('public')->delete($student->photo);
            }
            $data['photo'] = $request->file('photo')->store('students/photos', 'public');
        }

        $student->update($data);
        $this->logger->log('student.update', "Updated student {$student->name}", $student);

        return redirect()->route('admin.students.show', $student)
            ->with('success', 'Student updated successfully.');
    }

    public function destroy(Student $student): RedirectResponse
    {
        if ($student->activeAssignment()->exists()) {
            return back()->with('error', 'This student occupies a bed. Release them from Bed Assignment first.');
        }

        $this->logger->log('student.delete', "Deleted student {$student->name}", $student);
        $student->delete();

        return redirect()->route('admin.students.index')->with('success', 'Student removed.');
    }
}

