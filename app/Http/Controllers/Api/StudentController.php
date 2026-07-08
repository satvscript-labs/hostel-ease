<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentMode;
use App\Models\Student;
use App\Models\StudentDocument;
use App\Services\ActivityLogger;
use App\Services\PaymentService;
use App\Services\ImageService;
use App\Services\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Student endpoints for the mobile app (full CRUD + documents). Branch-scoped.
 */
class StudentController extends Controller
{
    public function __construct(
        protected ActivityLogger $logger,
        protected PaymentService $payments,
        protected ImageService $imageService,
        protected StorageService $storageService
    ) {
    }

    /**
     * Collect an amount and apply it to the student's unpaid dues, oldest first,
     * so Outstanding drops by exactly the amount collected. Scope keeps AC bills
     * SEPARATE from fees: scope=fees → semester + monthly; scope=ac → AC shares.
     * Leftover (overpayment) is kept as an advance.
     */
    public function collect(Request $request, int $student): JsonResponse
    {
        $model = Student::findOrFail($student);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:9999999'],
            'mode' => ['required', Rule::in(PaymentMode::active()->pluck('code')->all())],
            'credit_used' => ['nullable', 'numeric', 'min:0'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'remarks' => ['nullable', 'string', 'max:500'],
            'scope' => ['nullable', Rule::in(['fees', 'ac'])],
        ]);

        $scope = $data['scope'] ?? 'fees';

        // Unpaid obligations, oldest first — fees and AC are never mixed.
        $obligations = [];
        if ($scope === 'ac') {
            foreach ($model->acBillShares()->where('status', '!=', 'paid')->orderBy('id')->get() as $o) {
                $obligations[] = $o;
            }
        } else {
            foreach ($model->semesterFees()->where('status', '!=', 'paid')->orderByRaw('due_date is null, due_date')->orderBy('id')->get() as $o) {
                $obligations[] = $o;
            }
            foreach ($model->monthlyRents()->where('status', '!=', 'paid')->orderBy('rent_month')->get() as $o) {
                $obligations[] = $o;
            }
        }

        $base = [
            'student_id' => $model->id,
            'mode' => $data['mode'],
            'paid_on' => $data['paid_on'],
            'reference_number' => $data['reference_number'] ?? null,
            'remarks' => $data['remarks'] ?? null,
            'collected_by' => $request->user()->id,
        ];

        $remaining = (float) $data['amount'];
        $receipts = [];
        $firstPayment = null;

        foreach ($obligations as $ob) {
            if ($remaining <= 0.001) {
                break;
            }
            $bal = (float) $ob->balance;
            if ($bal <= 0) {
                continue;
            }
            $pay = min($remaining, $bal);
            $payment = $this->payments->record(array_merge($base, [
                'amount' => round($pay, 2),
                'credit_used' => 0,
            ]));
            $firstPayment ??= $payment;
            $receipts[] = $payment->receipt_number;
            $remaining -= $pay;
        }

        // Leftover beyond all dues → record as an advance (not tied to an obligation).
        if ($remaining > 0.001) {
            $payment = $this->payments->record(array_merge($base, [
                'amount' => round($remaining, 2),
                'credit_used' => 0,
            ]));
            $firstPayment ??= $payment;
            $receipts[] = $payment->receipt_number;
        }

        $this->logger->log('payment.collect', "Collected {$data['amount']} ({$scope}) from {$model->name}");

        return response()->json([
            'message' => 'Payment collected.',
            'payment_id' => $firstPayment?->id,
            'receipt_number' => $firstPayment?->receipt_number,
            'receipts' => $receipts,
            'outstanding' => $this->outstanding($model),
            'outstanding_fees' => $this->outstandingFees($model),
            'outstanding_ac' => $this->outstandingAc($model),
        ], 201);
    }

    /**
     * Record a "promise to pay" date + note against the student's unpaid dues of
     * a scope (fees or AC). No money is taken now. Mirrors the website.
     */
    public function promise(Request $request, int $student): JsonResponse
    {
        $model = Student::findOrFail($student);
        $data = $request->validate([
            'scope' => ['required', Rule::in(['fees', 'ac'])],
            'promise_date' => ['required', 'date', 'after_or_equal:today'],
            'promise_note' => ['nullable', 'string', 'max:255'],
        ]);

        $obligations = $this->unpaidObligations($model, $data['scope']);
        if (empty($obligations)) {
            return response()->json(['message' => 'No unpaid dues to set a promise against.'], 422);
        }

        foreach ($obligations as $o) {
            $o->forceFill([
                'promise_date' => $data['promise_date'],
                'promise_note' => $data['promise_note'] ?? null,
            ])->save();
        }

        $this->logger->log('promise.set',
            "Promise to pay ({$data['scope']}) set for {$data['promise_date']} — {$model->name}", $model);

        return response()->json([
            'message' => 'Promise to pay saved.',
            'count' => count($obligations),
            'outstanding_fees' => $this->outstandingFees($model),
            'outstanding_ac' => $this->outstandingAc($model),
        ]);
    }

    /**
     * Unpaid obligations for a scope, oldest first. Fees and AC never mix.
     *
     * @return array<int, \Illuminate\Database\Eloquent\Model>
     */
    protected function unpaidObligations(Student $model, string $scope): array
    {
        $obligations = [];
        if ($scope === 'ac') {
            foreach ($model->acBillShares()->where('status', '!=', 'paid')->orderBy('id')->get() as $o) {
                $obligations[] = $o;
            }
        } else {
            foreach ($model->semesterFees()->where('status', '!=', 'paid')->orderBy('id')->get() as $o) {
                $obligations[] = $o;
            }
            foreach ($model->monthlyRents()->where('status', '!=', 'paid')->orderBy('rent_month')->get() as $o) {
                $obligations[] = $o;
            }
        }

        return $obligations;
    }

    public function index(Request $request): JsonResponse
    {
        $students = Student::query()
            ->with('activeAssignment.bed.room')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('occupation'), fn ($q) => $q->where('occupation_type', $request->occupation))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->search.'%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('mobile', 'like', $term));
            })
            ->orderBy('name')
            ->get();

        $data = $students->map(function (Student $s) {
            $assignment = $s->activeAssignment;

            return [
                'id' => $s->id,
                'name' => $s->name,
                'mobile' => $s->mobile,
                'photo_url' => $s->photo_url,
                'occupation_type' => $s->occupation_type,
                'status' => $s->status,
                'room' => $assignment?->bed?->room?->room_number,
                'bed' => $assignment?->bed?->bed_number,
                'outstanding' => $this->outstanding($s),
            ];
        });

        return response()->json([
            'students' => $data,
            'occupation_types' => config('hostelease.occupation_types'),
        ]);
    }

    public function show(int $student): JsonResponse
    {
        // Resolved explicitly (not route-model binding) so TenantScope — set by
        // the api.tenant middleware — is guaranteed applied for branch isolation.
        $student = Student::with('activeAssignment.bed.room.floor')->findOrFail($student);

        $assignment = $student->activeAssignment;

        return response()->json([
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'mobile' => $student->mobile,
                'father_mobile' => $student->father_mobile,
                'mother_mobile' => $student->mother_mobile,
                'guardian_mobile' => $student->guardian_mobile,
                'aadhaar' => $student->aadhaar,
                'address' => $student->address,
                'photo_url' => $student->photo_url,
                'occupation_type' => $student->occupation_type,
                'status' => $student->status,
                'city' => $student->city,
                'state' => $student->state,
                'join_date' => $student->join_date?->toDateString(),
                'leave_date' => $student->leave_date?->toDateString(),
                'floor' => $assignment?->bed?->room?->floor?->name,
                'room' => $assignment?->bed?->room?->room_number,
                'bed' => $assignment?->bed?->bed_number,
                'total_paid' => (float) $student->payments()->sum('amount'),
                'total_billed' => $this->totalBilled($student),
                'outstanding' => $this->outstanding($student),
                'outstanding_fees' => $this->outstandingFees($student),
                'outstanding_ac' => $this->outstandingAc($student),
            ],
            'fees_dues' => $this->feesDues($student),
            'ac_dues' => $this->acDues($student),
            'pocket_money' => [
                'balance' => \App\Models\PocketMoneyTransaction::balanceFor($student->id),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateStudent($request);

        if ($request->hasFile('photo')) {
            $processed = $this->imageService->compressAndConvertToWebp($request->file('photo'), 800, 800, 80);
            $data['photo'] = $this->storageService->store($processed['content'], 'students/photos', 'public', $processed['extension']);
        }

        $student = Student::create($data);
        $this->logger->log('student.create', "Added student {$student->name}", $student);

        return response()->json(['message' => 'Student added.', 'id' => $student->id], 201);
    }

    public function update(Request $request, int $student): JsonResponse
    {
        $model = Student::findOrFail($student);
        $data = $this->validateStudent($request);

        if ($request->hasFile('photo')) {
            if ($model->photo) {
                $this->storageService->delete($model->photo, 'public');
            }
            $processed = $this->imageService->compressAndConvertToWebp($request->file('photo'), 800, 800, 80);
            $data['photo'] = $this->storageService->store($processed['content'], 'students/photos', 'public', $processed['extension']);
        }

        $model->update($data);
        $this->logger->log('student.update', "Updated student {$model->name}", $model);

        return response()->json(['message' => 'Student updated.']);
    }

    public function destroy(int $student): JsonResponse
    {
        $model = Student::findOrFail($student);
        if ($model->activeAssignment()->exists()) {
            return response()->json(['message' => 'This student occupies a bed. Release them from Bed Assignment first.'], 422);
        }
        $this->logger->log('student.delete', "Deleted student {$model->name}", $model);
        $model->delete();

        return response()->json(['message' => 'Student removed.']);
    }

    /**
     * Documents for a student.
     */
    public function documents(int $student): JsonResponse
    {
        $model = Student::findOrFail($student);
        $docs = $model->documents()->latest()->get()->map(fn ($d) => [
            'id' => $d->id,
            'type' => $d->type,
            'title' => $d->title,
            'url' => Storage::disk('public')->url($d->file_path),
            'expiry_date' => $d->expiry_date?->toDateString(),
            'is_signed' => (bool) $d->is_signed,
        ]);

        return response()->json(['documents' => $docs]);
    }

    public function storeDocument(Request $request, int $student): JsonResponse
    {
        $model = Student::findOrFail($student);
        $data = $request->validate([
            'type' => ['required', Rule::in(['aadhaar', 'photo', 'agreement', 'other'])],
            'title' => ['nullable', 'string', 'max:150'],
            'expiry_date' => ['nullable', 'date'],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'is_signed' => ['nullable', 'boolean'],
        ]);

        $file = $request->file('file');
        $directory = "students/documents/{$model->id}";
        
        if (str_starts_with($file->getMimeType(), 'image/')) {
            $processed = $this->imageService->compressAndConvertToWebp($file, 1600, 1600, 80);
            $path = $this->storageService->store($processed['content'], $directory, 'public', $processed['extension']);
        } else {
            $path = $this->storageService->store($file, $directory, 'public');
        }
        $doc = $model->documents()->create([
            'hostel_id' => $model->hostel_id,
            'type' => $data['type'],
            'title' => $data['title'] ?? ucfirst($data['type']),
            'file_path' => $path,
            'expiry_date' => $data['expiry_date'] ?? null,
            'is_signed' => $request->boolean('is_signed'),
        ]);
        $this->logger->log('document.upload', "Uploaded {$doc->type} for {$model->name}", $doc);

        return response()->json(['message' => 'Document uploaded.', 'id' => $doc->id], 201);
    }

    public function destroyDocument(int $student, int $document): JsonResponse
    {
        $model = Student::findOrFail($student);
        $doc = $model->documents()->findOrFail($document);

        $this->storageService->delete($doc->file_path, 'public');
        $doc->delete();

        $this->logger->log('document.delete', "Deleted document for {$model->name}", $model);

        return response()->json(['message' => 'Document deleted.']);
    }

    protected function validateStudent(Request $request): array
    {
        // Normalise mobiles to +91 format, aadhaar to all 12 digits (mirrors StoreStudentRequest).
        $normalize = fn ($v) => $v === null ? null : '+91' . substr(preg_replace('/\D+/', '', $v), -10);
        $aadhaar = fn ($v) => $v === null ? null : substr(preg_replace('/\D+/', '', $v), -12);
        $request->merge([
            'mobile' => $normalize($request->mobile),
            'father_mobile' => $normalize($request->father_mobile),
            'mother_mobile' => $normalize($request->mother_mobile),
            'guardian_mobile' => $normalize($request->guardian_mobile),
            'aadhaar' => $aadhaar($request->aadhaar),
        ]);

        $mobile = ['nullable', 'regex:/^\+91\d{10}$/'];

        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'mobile' => ['required', 'regex:/^\+91\d{10}$/'],
            'father_mobile' => $mobile,
            'mother_mobile' => $mobile,
            'guardian_mobile' => $mobile,
            'aadhaar' => ['nullable', 'digits:12'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'occupation_type' => ['required', Rule::in(array_keys(config('hostelease.occupation_types')))],
            'join_date' => ['nullable', 'date'],
            'leave_date' => ['nullable', 'date', 'after_or_equal:join_date'],
            'status' => ['required', Rule::in(['active', 'left'])],
        ]);
    }

    /** Fee obligations (semester + monthly), paid + unpaid, oldest first. */
    public function feesDues(Student $student): array
    {
        $out = [];
        foreach ($student->semesterFees()->orderBy('semester')->get() as $f) {
            $yearly = ($f->period_type ?? 'semester') === 'yearly';
            $out[] = [
                'payable_type' => 'semester_fee',
                'payable_id' => $f->id,
                'label' => $yearly ? "Year {$f->semester} Fee" : "Semester {$f->semester} Fee",
                'total' => (float) $f->total_fee,
                'paid' => (float) $f->paid_amount,
                'balance' => (float) $f->balance,
                'status' => $f->status,
                'due_date' => $f->due_date?->toDateString(),
                'promise_date' => $f->promise_date?->toDateString(),
            ];
        }
        foreach ($student->monthlyRents()->orderBy('rent_month')->get() as $r) {
            $out[] = [
                'payable_type' => 'monthly_rent',
                'payable_id' => $r->id,
                'label' => 'Rent — '.$r->rent_month?->format('M Y'),
                'total' => (float) $r->amount,
                'paid' => (float) $r->paid_amount,
                'balance' => (float) $r->balance,
                'status' => $r->status,
                'due_date' => $r->due_date?->toDateString(),
                'promise_date' => $r->promise_date?->toDateString(),
            ];
        }

        return $out;
    }

    /** AC bill shares, paid + unpaid (kept separate from fees). */
    public function acDues(Student $student): array
    {
        $out = [];
        foreach ($student->acBillShares()->with('acBill')->get() as $a) {
            $month = $a->acBill?->bill_month ? ' — '.$a->acBill->bill_month->format('M Y') : '';
            $out[] = [
                'payable_type' => 'ac_bill_student',
                'payable_id' => $a->id,
                'label' => 'AC Bill'.$month,
                'total' => (float) $a->amount,
                'paid' => (float) $a->paid_amount,
                'balance' => max(0, (float) $a->amount - (float) $a->paid_amount),
                'status' => $a->status,
                'due_date' => null,
                'promise_date' => $a->promise_date?->toDateString(),
            ];
        }

        return $out;
    }

    /** Sum of every obligation billed to the student (paid + unpaid). */
    protected function totalBilled(Student $student): float
    {
        return (float) $student->semesterFees()->sum('total_fee')
            + (float) $student->monthlyRents()->sum('amount')
            + (float) $student->acBillShares()->sum('amount');
    }

    protected function outstandingFees(Student $student): float
    {
        return (float) $student->semesterFees()->where('status', '!=', 'paid')->sum('balance')
            + (float) $student->monthlyRents()->where('status', '!=', 'paid')->sum('balance');
    }

    protected function outstandingAc(Student $student): float
    {
        return (float) $student->acBillShares()->where('status', '!=', 'paid')
            ->get()->sum(fn ($a) => max(0, (float) $a->amount - (float) $a->paid_amount));
    }

    protected function outstanding(Student $student): float
    {
        return $this->outstandingFees($student) + $this->outstandingAc($student);
    }
}

