<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcBillStudent;
use App\Models\Bed;
use App\Models\Expense;
use App\Models\MonthlyRent;
use App\Models\Payment;
use App\Models\PocketMoneyTransaction;
use App\Models\Room;
use App\Models\SemesterFee;
use App\Models\Staff;
use App\Models\Student;
use App\Models\StudentRegistration;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

/**
 * Dashboard summary for the mobile app. Mirrors the web dashboard stats,
 * scoped to the active branch by the api.tenant middleware.
 */
class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $totalBeds = Bed::count();
        $occupiedBeds = Bed::where('status', 'occupied')->count();
        $emptyBeds = Bed::where('status', 'empty')->count();

        $stats = [
            'total_rooms' => Room::count(),
            'total_beds' => $totalBeds,
            'occupied_beds' => $occupiedBeds,
            'empty_beds' => $emptyBeds,
            'students' => Student::active()->count(),
            'monthly_income' => (float) Payment::whereBetween('paid_on', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount'),
            'pending_fees' => (float) SemesterFee::where('status', '!=', 'paid')->sum('balance')
                + (float) MonthlyRent::where('status', '!=', 'paid')->sum('balance'),
            'ac_pending' => (float) AcBillStudent::where('status', '!=', 'paid')
                ->get()->sum(fn ($s) => (float) $s->amount - (float) $s->paid_amount),
            'occupancy_pct' => $totalBeds > 0 ? round(($occupiedBeds / $totalBeds) * 100, 1) : 0,
            'pending_registrations' => StudentRegistration::pending()->count(),
            'pocket_money_held' => round((float) PocketMoneyTransaction::query()
                ->selectRaw("SUM(CASE WHEN type='deposit' THEN amount ELSE -amount END) as bal")
                ->value('bal') ?? 0, 2),
            'monthly_expense' => (float) Expense::whereBetween('expense_date', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount'),
            'staff_active' => Staff::active()->count(),
        ];

        // Last 6 months collection, grouped in PHP (DB-agnostic).
        $months = collect(range(0, 5))->map(fn ($i) => now()->subMonths(5 - $i)->format('Y-m'));
        $collection = Payment::where('paid_on', '>=', now()->subMonths(5)->startOfMonth())
            ->get(['amount', 'paid_on'])
            ->groupBy(fn ($p) => $p->paid_on->format('Y-m'))
            ->map(fn ($g) => (float) $g->sum('amount'));

        $chart = $months->map(fn ($m) => [
            'label' => Carbon::createFromFormat('Y-m', $m)->format('M y'),
            'value' => (float) ($collection[$m] ?? 0),
        ])->values();

        return response()->json([
            'stats' => $stats,
            'occupancy' => [
                'occupied' => $occupiedBeds,
                'empty' => $emptyBeds,
                'reserved' => Bed::where('status', 'reserved')->count(),
                'maintenance' => Bed::where('status', 'maintenance')->count(),
            ],
            'collection_chart' => $chart,
        ]);
    }
}
