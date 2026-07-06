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

        return view('admin.students.show', compact('student', 'paymentSummary', 'qrSvg', 'invoices', 'pocketBalance', 'paymentModes'));
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

    public function updateFeeSettings(Request $request, Student $student): RedirectResponse
    {
        $data = $request->validate([
            'room_preference' => ['nullable', 'string', 'in:AC,Non-AC'],
            'sharing_preference' => ['nullable', 'string', 'in:Single,Double,Triple,Quad'],
            'fee_frequency' => ['required', 'string', 'in:monthly,semester,yearly'],
            'fee_amount' => ['required', 'numeric', 'min:0'],
        ]);

        $student->update($data);
        
        // Generate initial invoice if no invoices exist and we have a joining date
        if ($student->join_date && $student->invoices()->count() === 0) {
            $monthYear = $student->join_date->format('M Y');
            $period = $data['fee_frequency'] === 'monthly' ? "Rent for $monthYear" : ucfirst($data['fee_frequency']) . " Fee";
            
            Invoice::create([
                'student_id' => $student->id,
                'type' => 'fee',
                'title' => 'Initial ' . $period,
                'amount' => $data['fee_amount'],
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
