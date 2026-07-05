<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\CollectsPayments;
use App\Http\Controllers\Controller;
use App\Models\SemesterFee;
use App\Models\Student;
use App\Services\ActivityLogger;
use App\Services\PaymentService;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SemesterFeeController extends Controller
{
    use CollectsPayments;

    public function __construct(
        protected PaymentService $payments,
        protected ActivityLogger $logger,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $fees = SemesterFee::with('student')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('semester'), fn ($q) => $q->where('semester', $request->integer('semester')))
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'fees' => $fees->map(fn ($f) => [
                'id' => $f->id,
                'student' => $f->student?->name,
                'student_id' => $f->student_id,
                'semester' => $f->semester,
                'period_type' => $f->period_type ?? 'semester',
                'total_fee' => (float) $f->total_fee,
                'paid_amount' => (float) $f->paid_amount,
                'balance' => (float) $f->balance,
                'status' => $f->status,
                'due_date' => $f->due_date?->toDateString(),
                'promise_date' => $f->promise_date?->toDateString(),
                'promise_note' => $f->promise_note,
            ]),
            'summary' => [
                'total' => (float) $fees->sum('total_fee'),
                'paid' => (float) $fees->sum('paid_amount'),
                'due' => (float) $fees->sum('balance'),
            ],
            'students' => Student::active()->where('occupation_type', 'student')->orderBy('name')->get(['id', 'name', 'mobile']),
            'semesters' => config('hsms.semesters'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_id' => ['required', Rule::exists('students', 'id')->where('hostel_id', Tenant::id())],
            'semester' => ['required', 'integer', Rule::in(config('hsms.semesters'))],
            'total_fee' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'due_date' => ['nullable', 'date'],
        ]);

        if (SemesterFee::where('student_id', $data['student_id'])->where('period_type', 'semester')->where('semester', $data['semester'])->exists()) {
            return response()->json(['message' => 'A fee record for this student & semester already exists.'], 422);
        }

        $fee = new SemesterFee($data);
        $fee->paid_amount = 0;
        $fee->recalculate();
        $fee->save();
        $this->logger->log('semester_fee.create', "Semester {$fee->semester} fee for student #{$fee->student_id}", $fee);

        return response()->json(['message' => 'Semester fee added.', 'id' => $fee->id], 201);
    }

    public function update(Request $request, int $semesterFee): JsonResponse
    {
        $fee = SemesterFee::findOrFail($semesterFee);
        $data = $request->validate([
            'total_fee' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'due_date' => ['nullable', 'date'],
        ]);
        $fee->fill($data);
        $fee->recalculate();
        $fee->save();

        return response()->json(['message' => 'Semester fee updated.']);
    }

    public function collect(Request $request, int $semesterFee): JsonResponse
    {
        $fee = SemesterFee::findOrFail($semesterFee);
        $data = $this->validateCollection($request);
        $payment = $this->payments->record(array_merge($data, ['student_id' => $fee->student_id]), $fee);

        return response()->json([
            'message' => "Payment recorded against semester {$fee->semester}.",
            'receipt_number' => $payment->receipt_number,
            'payment_id' => $payment->id,
        ], 201);
    }

    public function destroy(int $semesterFee): JsonResponse
    {
        SemesterFee::findOrFail($semesterFee)->delete();

        return response()->json(['message' => 'Semester fee removed.']);
    }
}
