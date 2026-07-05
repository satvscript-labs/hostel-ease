<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\CollectsPayments;
use App\Http\Controllers\Controller;
use App\Models\AcBill;
use App\Models\AcBillStudent;
use App\Models\Room;
use App\Services\AcBillService;
use App\Services\ActivityLogger;
use App\Services\PaymentService;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AcBillController extends Controller
{
    use CollectsPayments;

    public function __construct(
        protected AcBillService $acBills,
        protected PaymentService $payments,
        protected ActivityLogger $logger,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $bills = AcBill::with('room.floor')
            ->withCount('shares')
            ->withSum('shares as billed', 'amount')
            ->withSum('shares as collected', 'paid_amount')
            ->when($request->filled('room'), fn ($q) => $q->where('room_id', $request->integer('room')))
            ->orderByDesc('bill_month')
            ->get();

        return response()->json([
            'bills' => $bills->map(fn ($b) => [
                'id' => $b->id,
                'room' => $b->room?->room_number,
                'bill_month' => $b->bill_month?->format('M Y'),
                'total_units' => (float) $b->total_units,
                'total_amount' => (float) $b->total_amount,
                'billed' => (float) $b->billed,
                'collected' => (float) $b->collected,
                'shares_count' => $b->shares_count,
            ]),
            'summary' => [
                'billed' => (float) $bills->sum('billed'),
                'collected' => (float) $bills->sum('collected'),
                'due' => (float) $bills->sum(fn ($b) => (float) $b->billed - (float) $b->collected),
            ],
            'ac_rooms' => Room::where('room_type', 'ac')->orderBy('room_number')->get(['id', 'room_number']),
        ]);
    }

    /**
     * Form data for creating a bill: occupants + last reading for a chosen room.
     */
    public function createOptions(Request $request): JsonResponse
    {
        $acRooms = Room::where('room_type', 'ac')->with('floor')->orderBy('room_number')->get(['id', 'room_number', 'floor_id']);
        $room = $request->filled('room') ? Room::where('room_type', 'ac')->find($request->integer('room')) : null;

        $occupants = $room ? $this->acBills->occupants($room)->map(fn ($s) => ['id' => $s->id, 'name' => $s->name]) : [];
        $lastReading = $room ? $this->acBills->lastReading($room) : 0.0;

        return response()->json([
            'ac_rooms' => $acRooms,
            'occupants' => $occupants,
            'last_reading' => $lastReading,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'room_id' => ['required', Rule::exists('rooms', 'id')->where('hostel_id', Tenant::id())->where('room_type', 'ac')],
            'bill_month' => ['required', 'date_format:Y-m'],
            'previous_unit' => ['required', 'numeric', 'min:0'],
            'current_unit' => ['required', 'numeric', 'gte:previous_unit'],
            'unit_price' => ['required', 'numeric', 'min:0', 'max:1000'],
            'distribution' => ['required', Rule::in(['equal', 'selected'])],
            'students' => ['array'],
            'students.*' => ['integer'],
        ]);

        if ($data['distribution'] === 'selected' && empty($data['students'])) {
            return response()->json(['message' => 'Choose at least one student for selected distribution.'], 422);
        }

        $room = Room::where('room_type', 'ac')->findOrFail($data['room_id']);
        $bill = $this->acBills->create($room, $data, $data['students'] ?? []);
        $this->logger->log('ac_bill.create', "AC bill {$room->room_number} {$bill->bill_month->format('M Y')}", $bill);

        return response()->json(['message' => 'AC bill generated and split among occupants.', 'id' => $bill->id], 201);
    }

    public function show(int $acBill): JsonResponse
    {
        $bill = AcBill::with('room.floor', 'shares.student')->findOrFail($acBill);

        return response()->json([
            'bill' => [
                'id' => $bill->id,
                'room' => $bill->room?->room_number,
                'bill_month' => $bill->bill_month?->format('M Y'),
                'previous_unit' => (float) $bill->previous_unit,
                'current_unit' => (float) $bill->current_unit,
                'unit_price' => (float) $bill->unit_price,
                'total_units' => (float) $bill->total_units,
                'total_amount' => (float) $bill->total_amount,
                'distribution' => $bill->distribution,
            ],
            'shares' => $bill->shares->map(fn ($s) => [
                'share_id' => $s->id,
                'student' => $s->student?->name,
                'student_id' => $s->student_id,
                'amount' => (float) $s->amount,
                'paid_amount' => (float) $s->paid_amount,
                'balance' => (float) $s->balance,
                'status' => $s->status,
                'promise_date' => $s->promise_date?->toDateString(),
            ]),
        ]);
    }

    public function collect(Request $request, int $share): JsonResponse
    {
        $model = AcBillStudent::findOrFail($share);
        $data = $this->validateCollection($request);
        $payment = $this->payments->record(array_merge($data, ['student_id' => $model->student_id]), $model);

        return response()->json([
            'message' => 'AC bill payment recorded.',
            'receipt_number' => $payment->receipt_number,
            'payment_id' => $payment->id,
        ], 201);
    }

    public function destroy(int $acBill): JsonResponse
    {
        $bill = AcBill::findOrFail($acBill);
        $this->logger->log('ac_bill.delete', "Deleted AC bill #{$bill->id}", $bill);
        $bill->delete();

        return response()->json(['message' => 'AC bill deleted.']);
    }
}
