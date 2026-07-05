<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Hostel;
use App\Models\Student;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $stats = [
            'total_hostels' => Hostel::count(),
            'active_hostels' => Hostel::where('status', 'active')->count(),
            'expired_hostels' => Hostel::where('status', 'expired')
                ->orWhere('subscription_end', '<', now())->count(),
            'due_renewals' => Hostel::expiringWithin(30)->count(),
            'total_students' => Student::acrossHostels()->count(),
            'total_income' => (float) Subscription::paid()->sum('amount'),
            'monthly_revenue' => (float) Subscription::paid()
                ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('amount'),
        ];

        $upcomingRenewals = Hostel::expiringWithin(30)
            ->orderBy('subscription_end')
            ->limit(10)
            ->get();

        // Revenue + registrations for the last 12 months (grouped in PHP — DB-agnostic).
        $since = now()->subMonths(11)->startOfMonth();

        $revenue = Subscription::paid()->where('created_at', '>=', $since)
            ->get(['amount', 'created_at'])
            ->groupBy(fn ($s) => $s->created_at->format('Y-m'))
            ->map(fn ($g) => (float) $g->sum('amount'));

        $registrations = Hostel::where('created_at', '>=', $since)
            ->get(['created_at'])
            ->groupBy(fn ($h) => $h->created_at->format('Y-m'))
            ->map->count();

        $months = collect(range(0, 11))
            ->map(fn ($i) => now()->subMonths(11 - $i)->format('Y-m'));

        $charts = [
            'labels' => $months->map(fn ($m) => \Carbon\Carbon::createFromFormat('Y-m', $m)->format('M y'))->values(),
            'revenue' => $months->map(fn ($m) => (float) ($revenue[$m] ?? 0))->values(),
            'registrations' => $months->map(fn ($m) => (int) ($registrations[$m] ?? 0))->values(),
        ];

        return view('superadmin.dashboard', compact('stats', 'upcomingRenewals', 'charts'));
    }
}
