<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcBill;
use App\Models\Invoice;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AcBillController extends Controller
{
    public function index()
    {
        $filterMonth = request('month') ? \Carbon\Carbon::parse(request('month'))->startOfMonth() : now()->startOfMonth();
        $filterFloor = request('floor');
        $from = $filterMonth->copy()->startOfMonth();
        $to = $filterMonth->copy()->endOfMonth();

        $billsQuery = AcBill::with('room.floor')
            ->withCount('invoices as shares_count')
            ->withSum('invoices as collected', 'paid_amount')
            ->whereBetween('bill_month', [$from->format('Y-m-d'), $to->format('Y-m-d')]);
            
        if ($filterFloor) {
            $billsQuery->whereHas('room', function($q) use ($filterFloor) {
                $q->where('floor_id', $filterFloor);
            });
        }
        
        $bills = $billsQuery->latest('bill_month')->get();
            
        $rooms = Room::where('room_type', 'ac')
            ->whereHas('beds.activeAssignment.student')
            ->with('beds.activeAssignment.student')
            ->get();
            
        $floors = \App\Models\Floor::ordered()->get();
        
        $lastBill = AcBill::latest('id')->first();
        $defaultUnitPrice = $lastBill ? $lastBill->unit_price : 12.0;
        // Fetch latest reading for each room
        $latestReadings = AcBill::select('room_id', 'current_reading')
            ->whereIn('id', function ($query) {
                $query->selectRaw('MAX(id)')->from('ac_bills')->groupBy('room_id');
            })
            ->get()
            ->pluck('current_reading', 'room_id');
            
        // withSum() leaves collected null when a bill has zero invoices.
        $bills->each(fn ($bill) => $bill->collected = (float) ($bill->collected ?? 0));

        $summary = [
            'billed' => $bills->sum('total_amount'),
            'collected' => $bills->sum('collected'),
            'due' => $bills->sum('total_amount') - $bills->sum('collected'),
        ];
        
        return view('admin.ac_bills.index', compact('bills', 'rooms', 'summary', 'filterMonth', 'filterFloor', 'floors', 'latestReadings', 'defaultUnitPrice'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'room_id' => 'required|exists:rooms,id',
            'bill_month' => 'required|date',
            'previous_reading' => 'required|numeric|min:0',
            'current_reading' => 'required|numeric|gte:previous_reading',
            'unit_price' => 'required|numeric|min:0.01',
        ]);

        $room = Room::with('beds.activeAssignment.student')->findOrFail($data['room_id']);
        
        $students = $room->beds->map->activeAssignment->filter()->map->student->filter();
        
        if ($students->isEmpty()) {
            return back()->with('error', 'Cannot generate an AC bill for an empty room.');
        }

        $totalUnits = $data['current_reading'] - $data['previous_reading'];
        $totalAmount = $totalUnits * $data['unit_price'];
        $splitAmount = round($totalAmount / $students->count(), 2);



        DB::transaction(function () use ($data, $room, $students, $totalUnits, $totalAmount, $splitAmount) {
            $acBill = AcBill::create([
                'room_id' => $room->id,
                'bill_month' => \Carbon\Carbon::parse($data['bill_month'])->startOfMonth()->format('Y-m-d'),
                'previous_reading' => $data['previous_reading'],
                'current_reading' => $data['current_reading'],
                'total_units' => $totalUnits,
                'unit_price' => $data['unit_price'],
                'total_amount' => $totalAmount,
            ]);

            $monthName = \Carbon\Carbon::parse($data['bill_month'])->format('M Y');
            $title = "AC Bill #{$acBill->id} - {$monthName} (Room {$room->room_number})";

            foreach ($students as $student) {
                Invoice::create([
                    'hostel_id' => $room->hostel_id,
                    'student_id' => $student->id,
                    'type' => 'ac',
                    'ac_bill_id' => $acBill->id,
                    'title' => $title,
                    'amount' => $splitAmount,
                    'status' => 'pending',
                    'is_generated_by_system' => true,
                ]);
            }
        });

        return back()->with('success', 'AC Bill generated successfully and split among students.');
    }

    public function destroy(AcBill $acBill)
    {
        $invoices = $acBill->invoices;

        if ($invoices->where('paid_amount', '>', 0)->isNotEmpty()) {
            return back()->with('error', 'Cannot delete this AC Bill because some students have already made payments.');
        }

        DB::transaction(function () use ($acBill, $invoices) {
            foreach ($invoices as $invoice) {
                $invoice->delete();
            }
            $acBill->delete();
        });

        return back()->with('success', 'AC Bill and associated pending invoices deleted successfully.');
    }
}
