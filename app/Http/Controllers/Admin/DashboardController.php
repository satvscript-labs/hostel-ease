<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bed;
use App\Models\Payment;
use App\Models\Room;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\Complaint;
use App\Models\Visitor;
use App\Models\Expense;
use App\Models\StudentRegistration;
use Carbon\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $totalBeds = Bed::count();
        $occupiedBeds = Bed::where('status', 'occupied')->count();
        $emptyBeds = Bed::where('status', 'empty')->count();

        // Greeting Logic
        $hour = now()->hour;
        if ($hour < 12) $greeting = "Good Morning";
        elseif ($hour < 17) $greeting = "Good Afternoon";
        else $greeting = "Good Evening";

        $unresolvedComplaints = Complaint::where('status', '!=', 'resolved')->count();
        $pendingDues = (float) Invoice::where('status', '!=', 'paid')->sum('balance');
        $studentsLeaving = Student::leavingWithin(7)->count();

        // Building the summary text
        $summaryParts = [];
        if ($unresolvedComplaints > 0) $summaryParts[] = "<span class=\"text-danger fw-bold\">{$unresolvedComplaints} unresolved complaints</span>";
        if ($studentsLeaving > 0) $summaryParts[] = "{$studentsLeaving} students leaving soon";
        if ($pendingDues > 0) $summaryParts[] = "₹" . number_format($pendingDues) . " in pending dues";
        
        $summaryText = empty($summaryParts) 
            ? "All systems normal. You have no pending urgent items today."
            : "You have " . implode(', ', $summaryParts) . " requiring attention.";

        $stats = [
            'greeting' => $greeting,
            'summary' => $summaryText,
            'total_rooms' => Room::count(),
            'occupied_beds' => $occupiedBeds,
            'empty_beds' => $emptyBeds,
            'total_beds' => $totalBeds,
            'students' => Student::active()->count(),
            'monthly_income' => (float) Payment::whereBetween('paid_on', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount'),
            'pending_fees' => $pendingDues,
            'unresolved_complaints' => $unresolvedComplaints,
            'visitors_today' => Visitor::whereDate('visit_date', now()->toDateString())->count(),
            'expenses_month' => (float) Expense::whereBetween('expense_date', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount'),
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

        // Build Unified Live Feed
        $feed = collect();

        // Latest 5 Payments
        Payment::with('student')->latest('id')->take(5)->get()->each(function ($p) use ($feed) {
            $feed->push((object)[
                'type' => 'payment',
                'title' => 'Payment Received',
                'desc' => "₹{$p->amount} from " . ($p->student->name ?? 'Student'),
                'time' => $p->created_at,
                'icon' => 'indian-rupee-sign',
                'color' => 'success'
            ]);
        });

        // Latest 5 Complaints
        Complaint::with('student')->latest('id')->take(5)->get()->each(function ($c) use ($feed) {
            $feed->push((object)[
                'type' => 'complaint',
                'title' => 'Complaint Logged',
                'desc' => $c->category . ' by ' . ($c->student->name ?? 'Student'),
                'time' => $c->created_at,
                'icon' => 'triangle-exclamation',
                'color' => 'warning'
            ]);
        });

        // Latest 3 Registrations
        StudentRegistration::latest('id')->take(3)->get()->each(function ($r) use ($feed) {
            $feed->push((object)[
                'type' => 'registration',
                'title' => 'New Registration',
                'desc' => $r->name . ' registered',
                'time' => $r->created_at,
                'icon' => 'user-plus',
                'color' => 'info'
            ]);
        });

        $feed = $feed->sortByDesc('time')->take(10)->values();

        return view('admin.dashboard', compact('stats', 'charts', 'feed'));
    }
}
