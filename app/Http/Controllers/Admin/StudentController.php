<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStudentRequest;
use App\Models\PaymentMode;
use App\Models\PocketMoneyTransaction;
use App\Models\Student;
use App\Models\Invoice;
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
     * Collect a lump amount and auto-allocate to the oldest unpaid invoices globally.
     */
    public function collect(Request $request, Student $student): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:9999999'],
            'mode' => ['required', Rule::in(PaymentMode::active()->pluck('code')->all())],
            'payment_type' => ['required', Rule::in(array_keys(config('hostelease.payment_types')))],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $this->payments->record(array_merge($data, ['student_id' => $student->id]));

        return back()->with('success', 'Payment collected — balances updated.');
    }

    /**
     * Record a "promise to pay" date against all current unpaid invoices globally.
     */
    public function promise(Request $request, Student $student): RedirectResponse
    {
        $data = $request->validate([
            'promise_date' => ['required', 'date', 'after_or_equal:today'],
            'promise_note' => ['nullable', 'string', 'max:255'],
        ]);

        $unpaidInvoices = $student->invoices()->where('status', '!=', 'paid')->get();
        if ($unpaidInvoices->isEmpty()) {
            return back()->with('error', 'No unpaid dues to set a promise against.');
        }

        foreach ($unpaidInvoices as $invoice) {
            $invoice->forceFill([
                'promise_date' => $data['promise_date'],
                'promise_note' => $data['promise_note'] ?? null,
            ])->save();
        }

        $this->logger->log('promise.set',
            "Global promise to pay set for {$data['promise_date']} — {$student->name}", $student);

        return back()->with('success', 'Promise to pay saved for all dues.');
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
            'invoices' => fn ($q) => $q->orderByDesc('created_at'),
        ]);

        $invoices = $student->invoices;
        
        $paymentSummary = [
            'total_paid' => (float) $student->payments()->sum('amount'),
            'last_payment' => $student->payments()->latest('paid_on')->first(),
            'total_billed' => (float) $invoices->sum('amount'),
            'outstanding' => (float) $invoices->sum('balance'),
        ];

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

        $qrSvg = null;
        try {
            $qrSvg = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')
                ->size(150)->margin(0)
                ->generate(route('admin.students.show', $student));
        } catch (\Throwable $e) {
            $qrSvg = null;
        }

        // Build Unified Timeline
        $timeline = collect();

        // 1. Bed Assignments
        foreach ($student->assignments as $a) {
            $timeline->push((object)[
                'date' => $a->join_date,
                'type' => 'assignment',
                'title' => "Assigned to Room {$a->bed->room->room_number} / Bed {$a->bed->bed_number}",
                'amount' => $a->monthly_rent,
                'status' => $a->is_active ? 'active' : 'past',
                'icon' => 'bed',
                'color' => 'primary'
            ]);
            if ($a->leave_date) {
                $timeline->push((object)[
                    'date' => $a->leave_date,
                    'type' => 'unassignment',
                    'title' => "Vacated Room {$a->bed->room->room_number} / Bed {$a->bed->bed_number}",
                    'icon' => 'door-open',
                    'color' => 'secondary'
                ]);
            }
        }

        // 2. Invoices
        foreach ($invoices as $inv) {
            $timeline->push((object)[
                'date' => $inv->created_at,
                'type' => 'invoice',
                'title' => "Invoice Generated: {$inv->title}",
                'amount' => $inv->amount,
                'status' => $inv->status,
                'icon' => 'file-invoice-dollar',
                'color' => 'danger'
            ]);
        }

        // 3. Payments
        foreach ($student->payments as $pay) {
            $title = "Payment Received";
            $icon = 'money-bill-wave';
            $color = 'success';
            if ($pay->mode === 'credit_note') {
                $title = "Credit Note Issued";
                $icon = 'arrow-rotate-left';
                $color = 'warning';
            } elseif ($pay->mode === 'credit') {
                $title = "Credit Auto-Applied";
                $icon = 'wand-magic-sparkles';
                $color = 'info';
            }

            $timeline->push((object)[
                'date' => $pay->paid_on,
                'type' => 'payment',
                'title' => $title,
                'amount' => $pay->amount,
                'status' => 'paid',
                'desc' => $pay->remarks ?? $pay->receipt_number,
                'icon' => $icon,
                'color' => $color
            ]);
        }

        $timeline = $timeline->sortByDesc('date')->values();

        return view('admin.students.show', compact('student', 'paymentSummary', 'qrSvg', 'invoices', 'pocketBalance', 'paymentModes', 'timeline'));
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

    public function previewProration(Request $request, Student $student, \App\Services\ProrationService $prorationService)
    {
        $data = $request->validate([
            'fee_frequency' => ['required', 'string', 'in:monthly,semester,yearly'],
            'fee_amount' => ['required', 'numeric', 'min:0'],
        ]);

        $preview = $prorationService->preview($student, $data['fee_amount'], $data['fee_frequency']);
        
        return response()->json($preview);
    }

    public function updateFeeSettings(Request $request, Student $student, \App\Services\ProrationService $prorationService): RedirectResponse
    {
        $data = $request->validate([
            'room_preference' => ['nullable', 'string', 'in:AC,Non-AC'],
            'sharing_preference' => ['nullable', 'string', 'in:Single,Double,Triple,Quad'],
            'fee_frequency' => ['required', 'string', 'in:monthly,semester,yearly'],
            'fee_amount' => ['required', 'numeric', 'min:0'],
        ]);

        // If the student already has an invoice, we use ProrationService to handle the change
        if ($student->invoices()->count() > 0) {
            $prorationService->apply($student, $data);
            return redirect()->route('admin.students.show', $student)
                ->with('success', 'Plan changed and prorated invoice generated successfully.');
        }

        // Otherwise just update and generate initial
        $student->update($data);
        
        if ($student->join_date && $student->invoices()->count() === 0) {
            $monthYear = $student->join_date->format('M Y');
            $period = $data['fee_frequency'] === 'monthly' ? "Rent for $monthYear" : ucfirst($data['fee_frequency']) . " Fee";
            
            $monthsToAdd = 1;
            if ($data['fee_frequency'] === 'semester') {
                $monthsToAdd = 6;
            } elseif ($data['fee_frequency'] === 'yearly') {
                $monthsToAdd = 12;
            }
            $cycleEnd = $student->join_date->copy()->addMonthsNoOverflow($monthsToAdd)->subDay();

            Invoice::create([
                'student_id' => $student->id,
                'type' => 'fee',
                'title' => 'Initial ' . $period,
                'amount' => $data['fee_amount'],
                'billing_cycle_start' => $student->join_date,
                'billing_cycle_end' => $cycleEnd,
                'due_date' => $student->join_date,
                'status' => 'pending',
            ]);
        }

        $this->logger->log('student.fee_settings', "Updated fee settings for {$student->name}", $student);

        return redirect()->route('admin.students.show', $student)
            ->with('success', 'Fee settings updated successfully.');
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
