<?php

namespace App\Services;

use App\Models\AcBill;
use App\Models\Bed;
use App\Models\Expense;
use App\Models\Floor;
use App\Models\Payment;
use App\Models\Student;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds the datasets behind every report. Each method returns a normalised
 * shape: ['headings' => [...], 'rows' => [[...]], 'total' => ?float].
 */
class ReportService
{
    public function __construct(protected LedgerService $ledger)
    {
    }

    /**
     * Collection grouped by day / week / month / year for a date range.
     */
    public function collection(string $period, Carbon $from, Carbon $to): array
    {
        // Group in PHP so the report works on any database driver.
        $format = match ($period) {
            'daily' => 'Y-m-d',
            'weekly' => 'o-\WW',   // ISO year-week, e.g. 2026-W23
            'yearly' => 'Y',
            default => 'Y-m',
        };

        $rows = Payment::query()
            ->whereBetween('paid_on', [$from->startOfDay(), $to->endOfDay()])
            ->get(['amount', 'paid_on'])
            ->groupBy(fn ($p) => $p->paid_on->format($format))
            ->sortKeys()
            ->map(fn ($g, $period) => [$period, $g->count(), (float) $g->sum('amount')])
            ->values();

        return [
            'headings' => ['Period', 'Payments', 'Amount'],
            'rows' => $rows->all(),
            'money' => [2],
            'total' => (float) $rows->sum(fn ($r) => $r[2]),
        ];
    }

    /**
     * Income broken down by payment mode for a date range.
     */
    public function incomeByMode(Carbon $from, Carbon $to): array
    {
        $rows = Payment::query()
            ->whereBetween('paid_on', [$from->startOfDay(), $to->endOfDay()])
            ->selectRaw('mode, COUNT(*) as cnt, SUM(amount) as total')
            ->groupBy('mode')->orderByDesc('total')
            ->get()
            ->map(function ($r) {
                $name = optional(\App\Models\PaymentMode::where('code', $r->mode)->first())->name
                    ?? config('hsms.payment_modes.'.$r->mode, ucfirst((string) $r->mode));

                return [$name, (int) $r->cnt, (float) $r->total];
            });

        return [
            'headings' => ['Mode', 'Payments', 'Amount'],
            'rows' => $rows->all(),
            'money' => [2],
            'total' => (float) $rows->sum(fn ($r) => $r[2]),
        ];
    }

    /**
     * Floor-wise bed occupancy.
     */
    public function occupancy(): array
    {
        $rows = Floor::ordered()->get()->map(function ($floor) {
            $beds = Bed::whereHas('room', fn ($q) => $q->where('floor_id', $floor->id));
            $total = (clone $beds)->count();
            $occ = (clone $beds)->where('status', 'occupied')->count();
            $empty = (clone $beds)->where('status', 'empty')->count();

            return [$floor->name, $total, $occ, $empty, $total ? round($occ / $total * 100, 1).'%' : '0%'];
        });

        return [
            'headings' => ['Floor', 'Total Beds', 'Occupied', 'Empty', 'Occupancy'],
            'rows' => $rows->all(),
            'money' => [],
            'total' => null,
        ];
    }

    /**
     * Students with an outstanding balance across all obligations.
     */
    public function pendingFees(): array
    {
        $rows = Student::with(['semesterFees', 'monthlyRents', 'acBillShares', 'payments'])
            ->orderBy('name')->get()
            ->map(function ($s) {
                $t = $this->ledger->totalsFor($s);

                return [$s, $t];
            })
            ->filter(fn ($pair) => $pair[1]['outstanding'] > 0)
            ->map(fn ($pair) => [
                $pair[0]->name,
                hsms_phone($pair[0]->mobile),
                $pair[1]['billed'],
                $pair[1]['paid'],
                $pair[1]['outstanding'],
            ])->values();

        return [
            'headings' => ['Student', 'Mobile', 'Billed', 'Paid', 'Outstanding'],
            'rows' => $rows->all(),
            'money' => [2, 3, 4],
            'total' => (float) $rows->sum(fn ($r) => $r[4]),
        ];
    }

    /**
     * Expenses grouped by category for a date range.
     */
    public function expensesByCategory(Carbon $from, Carbon $to): array
    {
        $rows = Expense::query()
            ->whereBetween('expense_date', [$from->startOfDay(), $to->endOfDay()])
            ->get(['amount', 'category'])
            ->groupBy('category')
            ->map(fn ($g, $cat) => [config('hsms.expense_categories.'.$cat, ucfirst((string) $cat)), $g->count(), (float) $g->sum('amount')])
            ->sortByDesc(fn ($r) => $r[2])
            ->values();

        return [
            'headings' => ['Category', 'Count', 'Amount'],
            'rows' => $rows->all(),
            'money' => [2],
            'total' => (float) $rows->sum(fn ($r) => $r[2]),
        ];
    }

    /**
     * Expenses grouped by month for a date range (DB-agnostic — grouped in PHP).
     */
    public function expensesByMonth(Carbon $from, Carbon $to): array
    {
        $rows = Expense::query()
            ->whereBetween('expense_date', [$from->startOfDay(), $to->endOfDay()])
            ->get(['amount', 'expense_date'])
            ->groupBy(fn ($e) => $e->expense_date->format('Y-m'))
            ->sortKeys()
            ->map(fn ($g, $m) => [Carbon::createFromFormat('Y-m', $m)->format('M Y'), $g->count(), (float) $g->sum('amount')])
            ->values();

        return [
            'headings' => ['Month', 'Count', 'Amount'],
            'rows' => $rows->all(),
            'money' => [2],
            'total' => (float) $rows->sum(fn ($r) => $r[2]),
        ];
    }

    /**
     * AC bills with income (collected) and due per bill.
     */
    public function acReport(Carbon $from, Carbon $to): array
    {
        $bills = AcBill::with('room')
            ->whereBetween('bill_month', [$from->startOfMonth(), $to->endOfMonth()])
            ->withSum('shares as billed', 'amount')
            ->withSum('shares as collected', 'paid_amount')
            ->orderByDesc('bill_month')->get();

        $rows = $bills->map(fn ($b) => [
            $b->bill_month->format('M Y'),
            $b->room->room_number,
            (float) $b->total_units,
            (float) $b->billed,
            (float) $b->collected,
            (float) $b->billed - (float) $b->collected,
        ]);

        return [
            'headings' => ['Month', 'Room', 'Units', 'Billed', 'Collected', 'Due'],
            'rows' => $rows->all(),
            'money' => [3, 4, 5],
            'total' => (float) $rows->sum(fn ($r) => $r[3]),
        ];
    }
}
