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
use App\Services\ImageService;
use App\Services\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StudentController extends Controller
{
    public function __construct(
        protected ActivityLogger $logger,
        protected PaymentService $payments,
        protected ImageService $imageService,
        protected StorageService $storageService,
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
            'credit_used' => ['nullable', 'numeric', 'min:0', 'max:'.(float) $student->credit_balance],
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
            $processed = $this->imageService->compressAndConvertToWebp($request->file('photo'), 800, 800, 80);
            $data['photo'] = $this->storageService->store($processed['content'], 'students/photos', 'public', $processed['extension']);
        }

        if ($request->hasFile('aadhaar_file')) {
            $processed = $this->imageService->compressAndConvertToWebp($request->file('aadhaar_file'), 1600, 1600, 80);
            $data['aadhaar_file'] = $this->storageService->store($processed['content'], 'students/documents', 'public', $processed['extension']);
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
        //
        // Every event carries `precise`: whether a clock time is actually known
        // for it. join_date, leave_date and paid_on are DATE columns — they hold
        // no time, so rendering them as "12:00 AM" was the view inventing a
        // midnight that never happened. Only entries with a real timestamp
        // behind them get a time; the rest show the date alone.
        $timeline = collect();

        // 1. Bed Assignments — date columns, no clock time exists.
        foreach ($student->assignments as $a) {
            $timeline->push((object)[
                'date' => $a->join_date,
                'precise' => false,
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
                    'precise' => false,
                    'type' => 'unassignment',
                    'title' => "Vacated Room {$a->bed->room->room_number} / Bed {$a->bed->bed_number}",
                    'icon' => 'door-open',
                    'color' => 'secondary'
                ]);
            }
        }

        // 2. Invoices — created_at is a real timestamp.
        foreach ($invoices as $inv) {
            $timeline->push((object)[
                'date' => $inv->created_at,
                'precise' => true,
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

            // paid_on is the business date the owner entered; created_at is the
            // moment the receipt was actually recorded. When they fall on the
            // same day, created_at IS that day's real time — use it, so three
            // collections two minutes apart read 05:36 / 05:38 / 05:40 instead
            // of three identical midnights. A backdated payment keeps its
            // entered date and shows no time, because none is known.
            $recordedSameDay = $pay->created_at && $pay->paid_on
                && $pay->created_at->isSameDay($pay->paid_on);

            $timeline->push((object)[
                'date' => $recordedSameDay ? $pay->created_at : $pay->paid_on,
                'precise' => $recordedSameDay,
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
                $this->storageService->delete($student->photo, 'public');
            }
            $processed = $this->imageService->compressAndConvertToWebp($request->file('photo'), 800, 800, 80);
            $data['photo'] = $this->storageService->store($processed['content'], 'students/photos', 'public', $processed['extension']);
        }

        if ($request->hasFile('aadhaar_file')) {
            if ($student->aadhaar_file) {
                $this->storageService->delete($student->aadhaar_file, 'public');
            }
            $processed = $this->imageService->compressAndConvertToWebp($request->file('aadhaar_file'), 1600, 1600, 80);
            $data['aadhaar_file'] = $this->storageService->store($processed['content'], 'students/documents', 'public', $processed['extension']);
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

    public function updateFeeSettings(Request $request, Student $student, \App\Services\ProrationService $prorationService): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'room_preference' => ['nullable', 'string', 'in:AC,Non-AC'],
            'sharing_preference' => ['nullable', 'string', Rule::in(hostelease_sharing_labels())],
            'fee_frequency' => ['required', 'string', 'in:monthly,semester,yearly'],
            'fee_amount' => ['required', 'numeric', 'min:0'],
        ]);

        // If the student already has an invoice, we use ProrationService to handle the change
        if ($student->invoices()->count() > 0) {
            $prorationService->apply($student, $data);

            if ($request->expectsJson()) {
                return response()->json(['success' => true]);
            }

            return redirect()->route('admin.students.show', $student)
                ->with('success', 'Plan changed and prorated invoice generated successfully.');
        }

        // Otherwise just update and generate the initial invoice (shared with
        // the move flow's assign step since W6.4 — one implementation).
        $student->update($data);
        $prorationService->generateInitialInvoice($student->refresh());

        $this->logger->log('student.fee_settings', "Updated fee settings for {$student->name}", $student);

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

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
