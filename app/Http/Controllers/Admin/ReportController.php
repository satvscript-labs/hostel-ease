<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ReportExport;
use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Reports (rebuilt W8). The registry below is the single source of truth for
 * the catalog — hub cards, the show page, and the 404 guard all read it.
 */
class ReportController extends Controller
{
    /**
     * key => [label, description, icon, category, needsRange].
     * Order here IS hub order within each category.
     */
    public const TYPES = [
        'pnl' => ['Profit & Loss', 'Income vs expenses, month by month — the bottom line.', 'scale-balanced', 'money', true],
        'collection' => ['Collections', 'What actually came in, grouped by day, week, month or year.', 'sack-dollar', 'money', true],
        'income' => ['Income by Mode', 'Where the money arrives — cash, UPI, cheque and the rest.', 'wallet', 'money', true],
        'dues' => ['Dues & Aging', 'Who owes what, and how long it has been owed.', 'hourglass-half', 'money', false],
        'expenses' => ['Expenses', 'Spending by category or by month — one report, a toggle.', 'money-bill-trend-up', 'money', true],
        'ac' => ['AC Bills', 'Metered AC billing — billed, collected and due per bill.', 'bolt', 'money', true],
        'occupancy' => ['Occupancy', 'Bed utilisation floor by floor, live.', 'bed', 'property', false],
        // Presence (P6) — shown only to presence-access roles (see index/show).
        'presence_time_out' => ['Time Out', 'How long students spend out — sessions and hours.', 'person-walking-arrow-right', 'presence', true],
        'presence_late' => ['Late Returns', 'Who came back during curfew, and how often.', 'clock', 'presence', true],
        'presence_nights_out' => ['Nights Out', 'Nights a student was away overnight.', 'moon', 'presence', true],
        'presence_staff_hours' => ['Staff Hours', 'Hours staff were on the premises, per person.', 'business-time', 'presence', true],
    ];

    public function __construct(protected ReportService $reports)
    {
    }

    public function index(): View
    {
        // One cheap aggregate per card — the hub is a mini-dashboard, not a
        // list of links. All hit W6-audit indexes.
        $month = [now()->startOfMonth(), now()->endOfDay()];
        $income = (float) Payment::income()->whereBetween('paid_on', $month)->sum('amount');
        $expense = (float) Expense::whereBetween('expense_date', $month)->sum('amount');

        $stats = [
            'pnl' => ($income - $expense >= 0 ? 'Net ' : 'Net −').hostelease_money(abs($income - $expense)).' this month',
            'collection' => hostelease_money($income).' this month',
            'income' => hostelease_money($income).' this month',
            'dues' => hostelease_money((float) Invoice::where('status', '!=', 'paid')->sum('balance')).' outstanding',
            'expenses' => hostelease_money($expense).' this month',
            'ac' => hostelease_money((float) Invoice::where('type', 'ac')->where('status', '!=', 'paid')->sum('balance')).' AC due',
            'occupancy' => $this->occupancyStat(),
        ];

        // Presence reports are restricted to presence-access roles (owner Q6):
        // a reports-access accountant/viewer must not see gate data. Hide the
        // whole category from them by dropping those types.
        $types = self::TYPES;
        if (auth()->user()->canAccessPresence()) {
            $enrolled = \App\Models\PresenceProfile::query()->enrolled()->count();
            $stats['presence_time_out'] = $enrolled.' enrolled';
            $stats['presence_late'] = $this->presenceStat();
            $stats['presence_nights_out'] = $enrolled.' enrolled';
            $stats['presence_staff_hours'] = \App\Models\PresenceProfile::query()->enrolled()
                ->where('presenceable_type', \App\Models\Staff::class)->count().' staff';
        } else {
            $types = array_filter($types, fn ($t) => $t[3] !== 'presence');
        }

        return view('admin.reports.index', ['types' => $types, 'stats' => $stats]);
    }

    public function show(Request $request, string $type)
    {
        abort_unless(isset(self::TYPES[$type]), 404);
        [$label, $description, $icon, $category, $needsRange] = self::TYPES[$type];

        // Presence reports carry gate data — restricted to presence-access roles.
        if ($category === 'presence') {
            abort_unless(auth()->user()->canAccessPresence(), 403);
        }

        [$from, $to, $preset] = $this->range($request);
        $period = in_array($request->input('period'), ['daily', 'weekly', 'monthly', 'yearly'], true)
            ? $request->input('period') : 'monthly';
        $groupBy = $request->input('group') === 'month' ? 'month' : 'category';
        $exporting = in_array($request->input('export'), ['excel', 'pdf'], true);

        $data = match ($type) {
            'pnl' => $this->reports->profitLoss($from, $to),
            'collection' => $this->reports->collection($period, $from, $to),
            'income' => $this->reports->incomeByMode($from, $to),
            // Exports always carry EVERY row; the page paginates.
            'dues' => $this->reports->duesAging(max(1, (int) $request->input('page', 1)), 15, all: $exporting),
            'expenses' => $this->reports->expenses($groupBy, $from, $to),
            'ac' => $this->reports->acReport($from, $to),
            'occupancy' => $this->reports->occupancy(),
            'presence_time_out' => $this->reports->presenceTimeOut($from, $to),
            'presence_late' => $this->reports->presenceLateReturns($from, $to),
            'presence_nights_out' => $this->reports->presenceNightsOut($from, $to),
            'presence_staff_hours' => $this->reports->presenceStaffHours($from, $to),
        };

        $title = $label.' Report';

        if ($request->input('export') === 'excel') {
            return Excel::download(
                new ReportExport($data['headings'], $data['rows'], $title),
                str($type)->slug().'-report.xlsx'
            );
        }

        if ($request->input('export') === 'pdf') {
            return Pdf::loadView('admin.reports.pdf', compact('title', 'data', 'from', 'to', 'needsRange'))
                ->download(str($type)->slug().'-report.pdf');
        }

        return view('admin.reports.show', compact(
            'type', 'label', 'description', 'icon', 'title', 'data', 'needsRange',
            'period', 'groupBy', 'from', 'to', 'preset'
        ));
    }

    /**
     * Resolve the date range: a named preset chip, explicit from/to, or the
     * default (this month). FY is the Indian financial year (Apr–Mar).
     */
    protected function range(Request $request): array
    {
        $preset = $request->input('range');

        [$from, $to] = match ($preset) {
            'month' => [now()->startOfMonth(), now()],
            'last_month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
            'quarter' => [now()->subMonthsNoOverflow(2)->startOfMonth(), now()],
            'fy' => [
                now()->month >= 4 ? now()->startOfYear()->addMonths(3) : now()->subYear()->startOfYear()->addMonths(3),
                now(),
            ],
            default => [null, null],
        };

        if ($from === null) {
            // rescue(): hand-edited ?from=garbage must not 500 the page.
            $from = $request->filled('from')
                ? rescue(fn () => Carbon::parse($request->input('from')), now()->startOfMonth(), false)
                : now()->startOfMonth();
            $to = $request->filled('to')
                ? rescue(fn () => Carbon::parse($request->input('to')), now(), false)
                : now();
            $preset = $request->filled('from') || $request->filled('to') ? 'custom' : 'month';
        }

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from]; // swapped bounds are a fix, not an error
        }

        return [$from->startOfDay(), $to->endOfDay(), $preset];
    }

    protected function occupancyStat(): string
    {
        $total = \App\Models\Bed::count();
        $occ = \App\Models\Bed::where('status', 'occupied')->count();

        return $total ? round($occ / $total * 100).'% occupied' : 'No beds yet';
    }

    protected function presenceStat(): string
    {
        $branch = \App\Models\Hostel::find(\App\Support\Tenant::id());

        return $branch?->hasCurfew() ? 'Curfew '.$branch->curfewLabel() : 'No curfew set';
    }
}
