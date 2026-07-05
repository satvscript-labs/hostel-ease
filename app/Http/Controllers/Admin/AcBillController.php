<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CollectPaymentRequest;
use App\Http\Requests\StoreAcBillRequest;
use App\Models\AcBill;
use App\Models\AcBillStudent;
use App\Models\Room;
use App\Services\AcBillService;
use App\Services\ActivityLogger;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AcBillController extends Controller
{
    public function __construct(
        protected AcBillService $acBills,
        protected PaymentService $payments,
        protected ActivityLogger $logger,
    ) {
    }

    public function index(Request $request): View
    {
        $bills = AcBill::with('room.floor')
            ->withCount('shares')
            ->withSum('shares as billed', 'amount')
            ->withSum('shares as collected', 'paid_amount')
            ->when($request->filled('room'), fn ($q) => $q->where('room_id', $request->integer('room')))
            ->orderByDesc('bill_month')
            ->get();

        $summary = [
            'billed' => (float) $bills->sum('billed'),
            'collected' => (float) $bills->sum('collected'),
            'due' => (float) $bills->sum(fn ($b) => (float) $b->billed - (float) $b->collected),
        ];

        $acRooms = Room::where('room_type', 'ac')->orderBy('room_number')->get(['id', 'room_number']);

        return view('admin.ac_bills.index', compact('bills', 'summary', 'acRooms'));
    }

    public function create(Request $request): View
    {
        $acRooms = Room::where('room_type', 'ac')->with('floor')->orderBy('room_number')->get();
        $selectedRoom = $request->filled('room') ? $acRooms->firstWhere('id', $request->integer('room')) : null;

        $occupants = collect();
        $lastReading = 0.0;
        if ($selectedRoom) {
            $occupants = $this->acBills->occupants($selectedRoom);
            $lastReading = $this->acBills->lastReading($selectedRoom);
        }

        return view('admin.ac_bills.create', compact('acRooms', 'selectedRoom', 'occupants', 'lastReading'));
    }

    public function store(StoreAcBillRequest $request): RedirectResponse
    {
        $room = Room::where('room_type', 'ac')->findOrFail($request->integer('room_id'));

        $bill = $this->acBills->create($room, $request->validated(), $request->input('students', []));

        $this->logger->log('ac_bill.create',
            "AC bill {$room->room_number} {$bill->bill_month->format('M Y')} — ".hostelease_money($bill->total_amount), $bill);

        return redirect()->route('admin.ac-bills.show', $bill)
            ->with('success', 'AC bill generated and split among occupants.');
    }

    public function show(AcBill $ac_bill): View
    {
        $ac_bill->load('room.floor', 'shares.student');

        return view('admin.ac_bills.show', ['bill' => $ac_bill]);
    }

    public function collect(CollectPaymentRequest $request, AcBillStudent $share): RedirectResponse
    {
        $payment = $this->payments->record(
            array_merge($request->validated(), ['student_id' => $share->student_id]),
            $share,
        );

        return redirect()->route('admin.payments.show', $payment)
            ->with('success', 'AC bill payment recorded.');
    }

    public function destroy(AcBill $ac_bill): RedirectResponse
    {
        if ($ac_bill->shares()->where('paid_amount', '>', 0)->exists()) {
            return back()->with('error', 'This AC bill has collected payments — reverse those receipts first.');
        }

        $this->logger->log('ac_bill.delete', "Deleted AC bill #{$ac_bill->id}", $ac_bill);
        $ac_bill->delete();   // shares cascade

        return redirect()->route('admin.ac-bills.index')->with('success', 'AC bill deleted.');
    }
}

