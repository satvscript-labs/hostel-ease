<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\CollectsPayments;
use App\Http\Controllers\Controller;
use App\Models\MonthlyRent;
use App\Services\ActivityLogger;
use App\Services\MonthlyRentService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class MonthlyRentController extends Controller
{
    use CollectsPayments;

    public function __construct(
        protected PaymentService $payments,
        protected MonthlyRentService $rents,
        protected ActivityLogger $logger,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $month = $request->filled('month')
            ? Carbon::parse($request->input('month').'-01')
            : now()->startOfMonth();

        $rows = MonthlyRent::with('student')
            ->whereDate('rent_month', $month->toDateString())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderBy('id')
            ->get();

        return response()->json([
            'month' => $month->format('Y-m'),
            'rows' => $rows->map(fn ($r) => [
                'id' => $r->id,
                'student' => $r->student?->name,
                'student_id' => $r->student_id,
                'rent_month' => $r->rent_month?->format('Y-m'),
                'amount' => (float) $r->amount,
                'paid_amount' => (float) $r->paid_amount,
                'balance' => (float) $r->balance,
                'status' => $r->status,
                'due_date' => $r->due_date?->toDateString(),
                'promise_date' => $r->promise_date?->toDateString(),
                'promise_note' => $r->promise_note,
            ]),
            'summary' => [
                'amount' => (float) $rows->sum('amount'),
                'paid' => (float) $rows->sum('paid_amount'),
                'due' => (float) $rows->sum('balance'),
            ],
        ]);
    }

    public function generate(Request $request): JsonResponse
    {
        $month = Carbon::parse($request->input('month', now()->format('Y-m')).'-01');
        $created = $this->rents->generateForMonth($month);
        $this->logger->log('rent.generate', "Generated {$created} rent rows for {$month->format('M Y')}");

        return response()->json([
            'message' => "{$created} rent row(s) generated for {$month->format('M Y')}.",
            'created' => $created,
            'month' => $month->format('Y-m'),
        ]);
    }

    public function collect(Request $request, int $monthlyRent): JsonResponse
    {
        $rent = MonthlyRent::findOrFail($monthlyRent);
        $data = $this->validateCollection($request);
        $payment = $this->payments->record(array_merge($data, ['student_id' => $rent->student_id]), $rent);

        return response()->json([
            'message' => "Rent payment recorded for {$rent->rent_month->format('M Y')}.",
            'receipt_number' => $payment->receipt_number,
            'payment_id' => $payment->id,
        ], 201);
    }

    public function destroy(int $monthlyRent): JsonResponse
    {
        MonthlyRent::findOrFail($monthlyRent)->delete();

        return response()->json(['message' => 'Rent row removed.']);
    }
}
