<?php

namespace App\Services;

use App\Models\AcBill;
use App\Models\Bed;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\PaymentMode;
use App\Models\Invoice;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * Builds the datasets behind every report (rebuilt W8 — the legacy service
 * predated the W6.2 income rules and overstated income on any hostel that had
 * ever used credit).
 *
 * Every method returns one normalised shape the page, the PDF and the Excel
 * export all consume:
 *
 *   [
 *     'headings' => [...],            table headings
 *     'rows'     => [[...]],          table rows (plain scalars)
 *     'money'    => [colIdx, ...],    which columns format as ₹
 *     'total'    => ?float,           table footer (null = no footer)
 *     'summary'  => [[icon,label,value,tone?], ...]   the he-stats tiles
 *     'chart'    => ?['type','labels','series'=>[[label,data,tone]]],
 *   ]
 *
 * MONEY RULE (the W6.2 fix this rebuild exists for): income is
 * Payment::income() — 'credit' re-applies money that was already income the
 * day it arrived, and 'credit_note' is a refund liability. Summing either
 * inflates every figure. Date grouping happens in PHP on two selected columns
 * (SQLite dev / MySQL prod disagree on date functions); sums and counts happen
 * in SQL on the W6-audit indexes.
 */
class ReportService
{
    /** Collections grouped by day / week / month / year. */
    public function collection(string $period, Carbon $from, Carbon $to): array
    {
        $format = match ($period) {
            'daily' => 'Y-m-d',
            'weekly' => 'o-\WW',
            'yearly' => 'Y',
            default => 'Y-m',
        };
        $pretty = match ($period) {
            'daily' => fn ($k) => Carbon::parse($k)->format('d M Y'),
            'monthly' => fn ($k) => Carbon::createFromFormat('Y-m', $k)->format('M Y'),
            default => fn ($k) => $k,
        };

        $payments = Payment::income()
            ->whereBetween('paid_on', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->get(['amount', 'paid_on']);

        $groups = $payments->groupBy(fn ($p) => $p->paid_on->format($format))->sortKeys();
        $rows = $groups->map(fn ($g, $k) => [$pretty($k), $g->count(), (float) $g->sum('amount')])->values();

        $total = (float) $payments->sum('amount');
        $days = max(1, (int) $from->diffInDays($to) + 1);

        return [
            'headings' => ['Period', 'Payments', 'Amount'],
            'rows' => $rows->all(),
            'money' => [2],
            'total' => $total,
            'summary' => [
                ['sack-dollar', 'Collected', hostelease_money($total), 'hero'],
                ['receipt', 'Payments', (string) $payments->count()],
                ['calendar-day', 'Avg / Day', hostelease_money($total / $days)],
            ],
            'chart' => [
                'type' => 'area',
                'labels' => $rows->pluck(0)->all(),
                'series' => [['Collected', $rows->pluck(2)->all(), 'primary']],
            ],
        ];
    }

    /** Income per payment mode — mode names from ONE preloaded map (was N+1). */
    public function incomeByMode(Carbon $from, Carbon $to): array
    {
        // Inactive modes included on purpose: a mode retired last year still
        // names last year's income.
        $names = PaymentMode::pluck('name', 'code');

        $rows = Payment::income()
            ->whereBetween('paid_on', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->selectRaw('mode, COUNT(*) as cnt, SUM(amount) as total')
            ->groupBy('mode')->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                $names[$r->mode] ?? config('hostelease.payment_modes.'.$r->mode, ucfirst((string) $r->mode)),
                (int) $r->cnt,
                (float) $r->total,
            ]);

        $total = (float) $rows->sum(fn ($r) => $r[2]);
        $top = $rows->first();

        return [
            'headings' => ['Mode', 'Payments', 'Amount'],
            'rows' => $rows->all(),
            'money' => [2],
            'total' => $total,
            'summary' => [
                ['sack-dollar', 'Income', hostelease_money($total), 'hero'],
                ['wallet', 'Modes Used', (string) $rows->count()],
                ['crown', 'Top Mode', $top ? $top[0].' · '.hostelease_money($top[2]) : '—'],
            ],
            'chart' => [
                'type' => 'bar',
                'labels' => $rows->pluck(0)->all(),
                'series' => [['Income', $rows->pluck(2)->all(), 'primary']],
            ],
        ];
    }

    /**
     * Profit & Loss — income vs expenses vs net, month by month. The report an
     * owner actually runs a hostel by. Salary expenses are already mirrored
     * exactly once (W6.2), so summing expenses double-counts nothing.
     */
    public function profitLoss(Carbon $from, Carbon $to): array
    {
        $income = Payment::income()
            ->whereBetween('paid_on', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->get(['amount', 'paid_on'])
            ->groupBy(fn ($p) => $p->paid_on->format('Y-m'))
            ->map(fn ($g) => (float) $g->sum('amount'));

        $expense = Expense::query()
            ->whereBetween('expense_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->get(['amount', 'expense_date'])
            ->groupBy(fn ($e) => $e->expense_date->format('Y-m'))
            ->map(fn ($g) => (float) $g->sum('amount'));

        // Every month in range appears, even at ₹0 — a silent month IS data.
        $rows = collect();
        for ($m = $from->copy()->startOfMonth(); $m->lte($to); $m->addMonth()) {
            $k = $m->format('Y-m');
            $in = $income[$k] ?? 0.0;
            $out = $expense[$k] ?? 0.0;
            $rows->push([$m->format('M Y'), $in, $out, $in - $out]);
        }

        $ti = (float) $rows->sum(fn ($r) => $r[1]);
        $te = (float) $rows->sum(fn ($r) => $r[2]);

        return [
            'headings' => ['Month', 'Income', 'Expenses', 'Net'],
            'rows' => $rows->all(),
            'money' => [1, 2, 3],
            'total' => $ti - $te,
            'summary' => [
                ['scale-balanced', 'Net '.($ti >= $te ? 'Profit' : 'Loss'), hostelease_money(abs($ti - $te)), $ti >= $te ? 'hero' : 'danger'],
                ['arrow-trend-up', 'Income', hostelease_money($ti), 'success'],
                ['arrow-trend-down', 'Expenses', hostelease_money($te), 'danger'],
            ],
            'chart' => [
                'type' => 'bar',
                'labels' => $rows->pluck(0)->all(),
                'series' => [
                    ['Income', $rows->pluck(1)->all(), 'success'],
                    ['Expenses', $rows->pluck(2)->all(), 'danger'],
                ],
            ],
        ];
    }

    /** Expenses grouped by category OR month — one report, a toggle (was two). */
    public function expenses(string $groupBy, Carbon $from, Carbon $to): array
    {
        $expenses = Expense::query()
            ->whereBetween('expense_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->get(['amount', 'category', 'expense_date']);

        $rows = $groupBy === 'month'
            ? $expenses->groupBy(fn ($e) => $e->expense_date->format('Y-m'))->sortKeys()
                ->map(fn ($g, $m) => [Carbon::createFromFormat('Y-m', $m)->format('M Y'), $g->count(), (float) $g->sum('amount')])->values()
            : $expenses->groupBy('category')
                ->map(fn ($g, $c) => [config('hostelease.expense_categories.'.$c, ucfirst((string) $c)), $g->count(), (float) $g->sum('amount')])
                ->sortByDesc(fn ($r) => $r[2])->values();

        $total = (float) $expenses->sum('amount');
        $top = $groupBy === 'month' ? $rows->sortByDesc(fn ($r) => $r[2])->first() : $rows->first();

        return [
            'headings' => [$groupBy === 'month' ? 'Month' : 'Category', 'Entries', 'Amount'],
            'rows' => $rows->all(),
            'money' => [2],
            'total' => $total,
            'summary' => [
                ['money-bill-trend-up', 'Spent', hostelease_money($total), 'hero'],
                ['list', 'Entries', (string) $expenses->count()],
                ['crown', 'Largest', $top ? $top[0].' · '.hostelease_money($top[2]) : '—'],
            ],
            'chart' => [
                'type' => $groupBy === 'month' ? 'area' : 'bar',
                'labels' => $rows->pluck(0)->all(),
                'series' => [['Expenses', $rows->pluck(2)->all(), 'danger']],
            ],
        ];
    }

    /**
     * Dues & Aging — replaces the legacy pendingFees, which loaded EVERY
     * student with ALL invoices and payments to compute totals in PHP. One
     * query on unpaid invoices (indexed hostel_id+status, W6 audit) now feeds
     * both the aging buckets and the per-student table, paginated.
     */
    public function duesAging(int $page = 1, int $perPage = 15, bool $all = false): array
    {
        $today = now()->startOfDay();

        $unpaid = Invoice::query()
            ->where('status', '!=', 'paid')
            ->where('balance', '>', 0)
            ->with('student:id,name,mobile')
            ->get(['id', 'student_id', 'balance', 'due_date']);

        // Bucket by how overdue: no due date / not yet due counts as Current.
        $buckets = ['current' => 0.0, 'b30' => 0.0, 'b60' => 0.0, 'b90' => 0.0];
        foreach ($unpaid as $inv) {
            $days = $inv->due_date ? (int) $inv->due_date->startOfDay()->diffInDays($today, false) : 0;
            $key = $days <= 30 ? ($days <= 0 ? 'current' : 'b30') : ($days <= 60 ? 'b60' : 'b90');
            $buckets[$key] += (float) $inv->balance;
        }

        $byStudent = $unpaid->groupBy('student_id')->map(function ($g) use ($today) {
            $oldest = $g->whereNotNull('due_date')->min('due_date');

            return [
                $g->first()->student?->name ?? '—',
                hostelease_phone($g->first()->student?->mobile),
                $g->count(),
                $oldest ? max(0, (int) Carbon::parse($oldest)->startOfDay()->diffInDays($today, false)) : 0,
                (float) $g->sum('balance'),
            ];
        })->sortByDesc(fn ($r) => $r[4])->values();

        $total = (float) $unpaid->sum('balance');

        $pageRows = $all ? $byStudent : $byStudent->forPage($page, $perPage)->values();
        $paginator = $all ? null : new LengthAwarePaginator(
            $pageRows, $byStudent->count(), $perPage, $page,
            ['path' => request()->url(), 'query' => request()->query()],
        );

        return [
            'headings' => ['Student', 'Mobile', 'Invoices', 'Days Overdue', 'Outstanding'],
            'rows' => $pageRows->all(),
            'money' => [4],
            'total' => $total,
            'paginator' => $paginator,
            'summary' => [
                ['hourglass-half', 'Outstanding', hostelease_money($total), 'hero'],
                ['circle-check', 'Current / ≤30d', hostelease_money($buckets['current'] + $buckets['b30']), 'success'],
                ['clock', '31–60 days', hostelease_money($buckets['b60']), 'warning'],
                ['triangle-exclamation', '60+ days', hostelease_money($buckets['b90']), 'danger'],
            ],
            'chart' => [
                'type' => 'bar',
                'labels' => ['Current', '1–30d', '31–60d', '60+d'],
                'series' => [['Outstanding', array_values($buckets), 'warning']],
            ],
        ];
    }

    /** Floor-wise occupancy — ONE grouped query (was 3 per floor). */
    public function occupancy(): array
    {
        $counts = Bed::query()
            ->join('rooms', 'rooms.id', '=', 'beds.room_id')
            ->join('floors', 'floors.id', '=', 'rooms.floor_id')
            ->selectRaw('floors.name as floor, floors.sort_order, beds.status, COUNT(*) as n')
            ->groupBy('floors.id', 'floors.name', 'floors.sort_order', 'beds.status')
            ->orderBy('floors.sort_order')
            ->get()
            ->groupBy('floor');

        $rows = $counts->map(function ($g, $floor) {
            $total = (int) $g->sum('n');
            $occ = (int) $g->where('status', 'occupied')->sum('n');
            $empty = (int) $g->where('status', 'empty')->sum('n');

            return [$floor, $total, $occ, $empty, $total ? round($occ / $total * 100, 1) : 0.0];
        })->values();

        $beds = (int) $rows->sum(fn ($r) => $r[1]);
        $occupied = (int) $rows->sum(fn ($r) => $r[2]);

        return [
            'headings' => ['Floor', 'Total Beds', 'Occupied', 'Empty', 'Occupancy %'],
            'rows' => $rows->map(fn ($r) => [$r[0], $r[1], $r[2], $r[3], $r[4].'%'])->all(),
            'money' => [],
            'total' => null,
            'summary' => [
                ['bed', 'Occupancy', ($beds ? round($occupied / $beds * 100, 1) : 0).'%', 'hero'],
                ['users', 'Occupied', "{$occupied} / {$beds}"],
                ['door-open', 'Empty', (string) $rows->sum(fn ($r) => $r[3])],
            ],
            'chart' => [
                'type' => 'bar',
                'labels' => $rows->pluck(0)->all(),
                'series' => [
                    ['Occupied', $rows->pluck(2)->all(), 'primary'],
                    ['Empty', $rows->pluck(3)->all(), 'muted'],
                ],
            ],
        ];
    }

    /** AC bills — billed / collected / due per bill. */
    public function acReport(Carbon $from, Carbon $to): array
    {
        $bills = AcBill::with('room:id,room_number')
            ->whereBetween('bill_month', [$from->copy()->startOfMonth(), $to->copy()->endOfMonth()])
            ->withSum('invoices as billed', 'amount')
            ->withSum('invoices as collected', 'paid_amount')
            ->orderByDesc('bill_month')->get();

        $rows = $bills->map(fn ($b) => [
            $b->bill_month->format('M Y'),
            $b->room->room_number,
            (float) $b->total_units,
            (float) $b->billed,
            (float) $b->collected,
            round((float) $b->billed - (float) $b->collected, 2),
        ]);

        $billed = (float) $rows->sum(fn ($r) => $r[3]);
        $collected = (float) $rows->sum(fn ($r) => $r[4]);

        return [
            'headings' => ['Month', 'Room', 'Units', 'Billed', 'Collected', 'Due'],
            'rows' => $rows->all(),
            'money' => [3, 4, 5],
            'total' => $billed,
            'summary' => [
                ['bolt', 'Billed', hostelease_money($billed), 'hero'],
                ['circle-check', 'Collected', hostelease_money($collected), 'success'],
                ['hourglass-half', 'Due', hostelease_money($billed - $collected), $billed - $collected > 0 ? 'danger' : 'success'],
            ],
            'chart' => null, // per-bill rows don't chart meaningfully
        ];
    }
}
