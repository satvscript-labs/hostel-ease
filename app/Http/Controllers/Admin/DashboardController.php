<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcBillStudent;
use App\Models\Bed;
use App\Models\MonthlyRent;
use App\Models\Payment;
use App\Models\Room;
use App\Models\SemesterFee;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $totalBeds = Bed::count();
        $occupiedBeds = Bed::where('status', 'occupied')->count();
        $emptyBeds = Bed::where('status', 'empty')->count();

        $stats = [
            'total_rooms' => Room::count(),
            'occupied_beds' => $occupiedBeds,
            'empty_beds' => $emptyBeds,
            'total_beds' => $totalBeds,
            'students' => Student::active()->count(),
            'monthly_income' => (float) Payment::whereBetween('paid_on', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount'),
            'pending_fees' => (float) SemesterFee::where('status', '!=', 'paid')->sum('balance')
                + (float) MonthlyRent::where('status', '!=', 'paid')->sum('balance'),
            'ac_pending' => (float) AcBillStudent::where('status', '!=', 'paid')
                ->get()
                ->sum(fn ($s) => (float) $s->amount - (float) $s->paid_amount),
            'occupancy_pct' => $totalBeds > 0 ? round(($occupiedBeds / $totalBeds) * 100, 1) : 0,
        ];

        // Monthly collection for the last 6 months (grouped in PHP — DB-agnostic).
        $months = collect(range(0, 5))->map(fn ($i) => now()->subMonths(5 - $i)->format('Y-m'));
        $collection = Payment::where('paid_on', '>=', now()->subMonths(5)->startOfMonth())
            ->get(['amount', 'paid_on'])
            ->groupBy(fn ($p) => $p->paid_on->format('Y-m'))
            ->map(fn ($g) => (float) $g->sum('amount'));

        $charts = [
            'occupancy' => [
                'occupied' => $occupiedBeds,
                'empty' => $emptyBeds,
                'reserved' => Bed::where('status', 'reserved')->count(),
                'maintenance' => Bed::where('status', 'maintenance')->count(),
            ],
            'collection_labels' => $months->map(fn ($m) => Carbon::createFromFormat('Y-m', $m)->format('M y'))->values(),
            'collection_values' => $months->map(fn ($m) => (float) ($collection[$m] ?? 0))->values(),
        ];

        $alerts = [
            'leaving_soon' => Student::leavingWithin(7)->with('activeAssignment.bed.room')->get(),
            'empty_beds' => $emptyBeds,
        ];

        return view('admin.dashboard', compact('stats', 'charts', 'alerts'));
    }
}
