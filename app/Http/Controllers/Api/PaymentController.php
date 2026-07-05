<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcBillStudent;
use App\Models\MonthlyRent;
use App\Models\Payment;
use App\Models\PaymentMode;
use App\Models\SemesterFee;
use App\Services\PaymentService;
use App\Support\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Fee collection endpoints for the mobile app.
 */
class PaymentController extends Controller
{
    /** payable_type → model class for settling a specific obligation. */
    protected array $payables = [
        'semester_fee' => SemesterFee::class,
        'monthly_rent' => MonthlyRent::class,
        'ac_bill_student' => AcBillStudent::class,
    ];

    public function __construct(protected PaymentService $payments)
    {
    }

    /**
     * Active payment modes for the collect form (tenant-scoped).
     */
    public function modes(): JsonResponse
    {
        $modes = PaymentMode::active()->ordered()->get()->map(fn ($m) => [
            'code' => $m->code,
            'name' => $m->name,
            'requires_reference' => (bool) $m->requires_reference,
        ]);

        return response()->json([
            'modes' => $modes,
            'payment_types' => config('hsms.payment_types'),
        ]);
    }

    /**
     * Recent payments (optionally filtered by student).
     */
    public function index(Request $request): JsonResponse
    {
        $payments = Payment::with('student')
            ->when($request->filled('student_id'), fn ($q) => $q->where('student_id', $request->integer('student_id')))
            ->orderByDesc('paid_on')->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'receipt_number' => $p->receipt_number,
                'student' => $p->student?->name,
                'amount' => (float) $p->amount,
                'mode' => $p->mode,
                'paid_on' => $p->paid_on?->toDateString(),
            ]);

        return response()->json(['payments' => $payments]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_id' => ['required', Rule::exists('students', 'id')->where('hostel_id', Tenant::id())->whereNull('deleted_at')],
            'amount' => ['required', 'numeric', 'min:1', 'max:9999999'],
            'payment_type' => ['required', Rule::in(array_keys(config('hsms.payment_types')))],
            'mode' => ['required', Rule::in(PaymentMode::active()->pluck('code')->all())],
            'reference_number' => [
                Rule::requiredIf(fn () => (bool) optional(
                    PaymentMode::active()->where('code', $request->mode)->first()
                )->requires_reference),
                'nullable', 'string', 'max:100',
            ],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'remarks' => ['nullable', 'string', 'max:500'],
            // Optional: settle a specific obligation.
            'payable_type' => ['nullable', Rule::in(array_keys($this->payables))],
            'payable_id' => ['nullable', 'integer', 'required_with:payable_type'],
        ]);

        $payable = $this->resolvePayable($data);

        $data['collected_by'] = $request->user()->id;
        $payment = $this->payments->record($data, $payable);

        return response()->json([
            'message' => 'Payment recorded.',
            'payment' => [
                'id' => $payment->id,
                'receipt_number' => $payment->receipt_number,
                'amount' => (float) $payment->amount,
                'mode' => $payment->mode,
                'paid_on' => $payment->paid_on?->toDateString(),
            ],
        ], 201);
    }

    /**
     * A single payment's details (for the receipt screen).
     */
    public function show(int $payment): JsonResponse
    {
        $model = Payment::with('student', 'collector', 'hostel')->findOrFail($payment);

        return response()->json([
            'payment' => [
                'id' => $model->id,
                'receipt_number' => $model->receipt_number,
                'student' => $model->student?->name,
                'student_mobile' => $model->student?->mobile,
                'amount' => (float) $model->amount,
                'payment_type' => $model->payment_type,
                'mode' => $model->mode,
                'reference_number' => $model->reference_number,
                'paid_on' => $model->paid_on?->toDateString(),
                'remarks' => $model->remarks,
                'collected_by' => $model->collector?->name,
                'hostel' => $model->hostel?->name,
            ],
        ]);
    }

    /**
     * Download the receipt PDF (app fetches with the bearer header, then shares).
     */
    public function receipt(int $payment): SymfonyResponse
    {
        $model = Payment::with('student', 'collector', 'hostel')->findOrFail($payment);

        return Pdf::loadView('admin.payments.receipt_pdf', ['payment' => $model])
            ->download($model->receipt_number.'.pdf');
    }

    /**
     * Resolve and tenant-verify the payable obligation, if one was supplied.
     */
    protected function resolvePayable(array $data): ?Model
    {
        if (empty($data['payable_type'])) {
            return null;
        }

        /** @var class-string<Model> $class */
        $class = $this->payables[$data['payable_type']];

        // TenantScope on these models guarantees branch isolation.
        return $class::where('student_id', $data['student_id'])->findOrFail($data['payable_id']);
    }
}
