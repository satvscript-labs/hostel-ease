<?php

namespace App\Services\Billing;

use App\Enums\BillingPeriod;
use App\Enums\OrderKind;
use App\Enums\PaymentStatus;
use App\Models\SubscriptionAccount;
use App\Models\SubscriptionOrder;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Every revenue figure on the Super Admin Revenue page (S5 · 28_S5_DESIGN.md).
 *
 * The one idea: every number comes from PAID ORDER LINES — one branch × a date range ×
 * an amount, written by every money path since S1 and already net of discounts and
 * overrides. A line's daily rate is amount ÷ days, so prorations and top-ups need no
 * special case. Cash is the only figure read from orders, by paid_at.
 *
 * Date ranges are start-inclusive, end-exclusive; "today" is the app clock's date.
 * Loaded once per instance — one query for the lines, small enough to work in PHP.
 */
class RevenueMetrics
{
    /** Kinds that make an account a CUSTOMER (not a trial) — any amount, ₹0 gifts included. */
    private const NOT_CUSTOMER_KINDS = [OrderKind::Trial->value];

    /** Kinds that mean a trial converted: the customer bought a plan. */
    private const CONVERSION_KINDS = [OrderKind::Purchase->value, OrderKind::Renewal->value, OrderKind::AddBranch->value, OrderKind::Align->value];

    /** @var Collection<int, object>|null */
    protected ?Collection $lines = null;

    // ── the raw material ────────────────────────────────────────────────────

    /**
     * Every line of every paid order: {account, kind, period, amount, start, end, days}.
     *
     * @return Collection<int, object>
     */
    public function lines(): Collection
    {
        return $this->lines ??= DB::table('subscription_order_lines as l')
            ->join('subscription_orders as o', 'o.id', '=', 'l.order_id')
            ->where('o.payment_status', PaymentStatus::Paid->value)
            ->whereNull('o.deleted_at')
            ->get(['l.start_date', 'l.end_date', 'l.amount', 'o.account_id', 'o.kind', 'o.period', 'o.created_at'])
            ->map(function ($r) {
                // E14: a line without a start began when its order was raised.
                $start = Carbon::parse($r->start_date ?? $r->created_at)->startOfDay();
                $end = $r->end_date ? Carbon::parse($r->end_date)->startOfDay() : null;

                return (object) [
                    'account' => (int) $r->account_id,
                    'kind' => (string) $r->kind,
                    'period' => BillingPeriod::tryFrom((string) $r->period) ?? BillingPeriod::Yearly,
                    'amount' => (float) $r->amount,
                    'start' => $start,
                    'end' => $end,
                    'days' => $end && $end->greaterThan($start) ? (int) $start->diffInDays($end) : 0,
                ];
            })
            ->filter(fn ($l) => $l->days > 0)
            ->values();
    }

    /** What a line is worth per month: its full-term equivalent, ÷ 12 for a yearly term. */
    public function monthlyRate(object $line): float
    {
        if ($line->amount <= 0) {
            return 0.0;
        }

        // The line's own term length (E8): a full February month is ₹1,000, not ₹1,086,
        // and a yearly line spanning a leap day is still one year.
        $monthly = $line->period === BillingPeriod::Monthly;
        $termDays = ($monthly ? BillingPeriod::Monthly : BillingPeriod::Yearly)->cycleDays($line->end);
        $fullTerm = $line->amount * $termDays / $line->days;

        return $monthly ? $fullTerm : $fullTerm / 12;
    }

    protected function live(object $line, Carbon $date): bool
    {
        return $line->start->lessThanOrEqualTo($date) && $date->lessThan($line->end);
    }

    protected function day(?CarbonInterface $date = null): Carbon
    {
        return Carbon::instance($date ?? Carbon::now())->startOfDay();
    }

    // ── MRR family ──────────────────────────────────────────────────────────

    /** @return array<int, float> account id → MRR on $date */
    public function mrrByAccount(?CarbonInterface $date = null): array
    {
        $date = $this->day($date);
        $by = [];

        foreach ($this->lines() as $line) {
            if ($this->live($line, $date) && ($rate = $this->monthlyRate($line)) > 0) {
                $by[$line->account] = ($by[$line->account] ?? 0) + $rate;
            }
        }

        return array_map(fn ($v) => round($v, 2), $by);
    }

    public function mrr(?CarbonInterface $date = null): float
    {
        return round(array_sum($this->mrrByAccount($date)), 2);
    }

    public function payingCustomers(?CarbonInterface $date = null): int
    {
        return count($this->mrrByAccount($date));
    }

    public function arpa(?CarbonInterface $date = null): float
    {
        $n = $this->payingCustomers($date);

        return $n ? round($this->mrr($date) / $n, 2) : 0.0;
    }

    // ── accrual ─────────────────────────────────────────────────────────────

    /** Revenue EARNED in [$from, $to): each line's amount × its days inside the range ÷ its days. */
    public function earned(CarbonInterface $from, CarbonInterface $to): float
    {
        $from = $this->day($from);
        $to = $this->day($to);
        $sum = 0.0;

        foreach ($this->lines() as $line) {
            $a = $line->start->greaterThan($from) ? $line->start : $from;
            $b = $line->end->lessThan($to) ? $line->end : $to;
            if ($b->greaterThan($a) && $line->amount > 0) {
                $sum += $line->amount * $a->diffInDays($b) / $line->days;
            }
        }

        return round($sum, 2);
    }

    /** Cash received for service not yet delivered, as of $date (E1: advance renewals in full). */
    public function deferred(?CarbonInterface $date = null): float
    {
        $date = $this->day($date);
        $sum = 0.0;

        foreach ($this->lines() as $line) {
            if ($line->amount <= 0 || $line->end->lessThanOrEqualTo($date)) {
                continue;
            }
            $from = $line->start->greaterThan($date) ? $line->start : $date;
            $sum += $line->amount * $from->diffInDays($line->end) / $line->days;
        }

        return round($sum, 2);
    }

    // ── cash ────────────────────────────────────────────────────────────────

    /** Cash collected in [$from, $to), by paid_at, still paid today (E5: net of voids). */
    public function cash(CarbonInterface $from, CarbonInterface $to): float
    {
        return round((float) SubscriptionOrder::query()
            ->where('payment_status', PaymentStatus::Paid->value)
            ->where('paid_at', '>=', Carbon::instance($from))
            ->where('paid_at', '<', Carbon::instance($to))
            ->sum('amount'), 2);
    }

    // ── receivables ─────────────────────────────────────────────────────────

    /**
     * Charges raised and not paid, aged by when raised — only those that would still BUY
     * something. A charge a later payment already covered ("Already covered — nothing to
     * pay" on the owner's page) is money nobody can collect; it is counted apart as
     * `stale` so the operator voids it, not chased as debt (S5 audit B2). The Customers
     * page reads this same method, so the two never disagree.
     *
     * @return array{total: float, count: int, fresh: float, mid: float, old: float, stale: float, stale_count: int}
     */
    public function receivables(?CarbonInterface $date = null): array
    {
        $date = $this->day($date);
        $out = ['total' => 0.0, 'count' => 0, 'fresh' => 0.0, 'mid' => 0.0, 'old' => 0.0, 'stale' => 0.0, 'stale_count' => 0];

        foreach (SubscriptionOrder::outstanding()->with('lines.branch')->get() as $o) {
            if ($o->coverageState() === 'covered') {
                $out['stale'] += (float) $o->amount;
                $out['stale_count']++;

                continue;
            }

            $age = (int) $o->created_at->copy()->startOfDay()->diffInDays($date);
            $bucket = $age <= 7 ? 'fresh' : ($age <= 30 ? 'mid' : 'old');
            $out[$bucket] += (float) $o->amount;
            $out['total'] += (float) $o->amount;
            $out['count']++;
        }

        return array_map(fn ($v) => is_float($v) ? round($v, 2) : $v, $out);
    }

    // ── customers, churn ────────────────────────────────────────────────────

    /** Accounts with CUSTOMER coverage (any non-trial paid line) live on $date. */
    public function customersCovered(?CarbonInterface $date = null): int
    {
        $date = $this->day($date);

        return $this->lines()
            ->filter(fn ($l) => ! in_array($l->kind, self::NOT_CUSTOMER_KINDS, true) && $this->live($l, $date))
            ->pluck('account')->unique()->count();
    }

    /**
     * Customers who LEFT: their last customer coverage ended, the grace window has
     * passed, and nothing came after. Trial-only accounts are never here (E4).
     *
     * @return Collection<int, array{account:int, ended:Carbon, mrr:float}>
     */
    public function lostCustomers(?CarbonInterface $today = null): Collection
    {
        $today = $this->day($today);
        $grace = (int) config('hostelease.grace_days', 0);

        return $this->lines()
            ->reject(fn ($l) => in_array($l->kind, self::NOT_CUSTOMER_KINDS, true))
            ->groupBy('account')
            ->map(function (Collection $lines, $account) use ($today, $grace) {
                $ended = $lines->max('end');
                if (! $ended->copy()->addDays($grace)->lessThan($today)) {
                    return null;   // still covered, or still inside grace
                }
                $before = $ended->copy()->subDay();

                return [
                    'account' => (int) $account,
                    'ended' => $ended->copy(),
                    'mrr' => round($lines->filter(fn ($l) => $this->live($l, $before))->sum(fn ($l) => $this->monthlyRate($l)), 2),
                ];
            })
            ->filter()
            ->sortByDesc(fn ($r) => $r['ended']->timestamp)
            ->values();
    }

    /** Lost in [$from, $to) ÷ customers covered on $from. */
    public function churnRate(CarbonInterface $from, CarbonInterface $to): ?float
    {
        $base = $this->customersCovered($from);
        if ($base === 0) {
            return null;
        }

        // Dated by the LAST COVERED DAY (the end date is exclusive): a plan that ends on
        // 1 Sep was last used on 31 Aug, so it is August's loss — and August's base has
        // it. Dated by the end date it fell into September, whose base no longer did,
        // and vanished from every rate (S5 audit B1).
        $from = $this->day($from);
        $to = $this->day($to);
        $lost = $this->lostCustomers()->filter(function ($r) use ($from, $to) {
            $lastDay = $r['ended']->copy()->subDay();

            return $lastDay->greaterThanOrEqualTo($from) && $lastDay->lessThan($to);
        })->count();

        return round($lost / $base * 100, 1);
    }

    // ── MRR movement ────────────────────────────────────────────────────────

    /**
     * How MRR moved between two dates, per account: new, expansion, reactivation,
     * contraction, churned. start + new + expansion + reactivation − contraction −
     * churned = end, exactly (the movements are differences of the same two maps).
     *
     * @return array{start: float, new: float, expansion: float, reactivation: float, contraction: float, churned: float, end: float}
     */
    public function movement(CarbonInterface $from, CarbonInterface $to): array
    {
        $a = $this->mrrByAccount($from);
        $b = $this->mrrByAccount($to);
        $from = $this->day($from);
        $out = ['start' => round(array_sum($a), 2), 'new' => 0.0, 'expansion' => 0.0, 'reactivation' => 0.0, 'contraction' => 0.0, 'churned' => 0.0];

        foreach (array_unique(array_merge(array_keys($a), array_keys($b))) as $acc) {
            $before = $a[$acc] ?? 0.0;
            $after = $b[$acc] ?? 0.0;
            $delta = $after - $before;

            if ($before == 0.0 && $after > 0) {
                $paidBefore = $this->lines()->contains(fn ($l) => $l->account === $acc && $l->amount > 0 && $l->start->lessThan($from));
                $out[$paidBefore ? 'reactivation' : 'new'] += $after;
            } elseif ($before > 0 && $after == 0.0) {
                $out['churned'] += $before;
            } elseif ($delta > 0) {
                $out['expansion'] += $delta;
            } elseif ($delta < 0) {
                $out['contraction'] += -$delta;
            }
        }

        $out = array_map(fn ($v) => round($v, 2), $out);
        $out['end'] = round(array_sum($b), 2);

        return $out;
    }

    // ── trials ──────────────────────────────────────────────────────────────

    /**
     * Trial cohorts by the month each account's FIRST trial started.
     *
     * @return array<string, array{started:int, converted:int, running:int, lapsed:int, rate:?float}> keyed Y-m
     */
    public function trialCohorts(?CarbonInterface $today = null): array
    {
        $today = $this->day($today);

        $trials = DB::table('subscription_orders as o')
            ->join('subscription_order_lines as l', 'l.order_id', '=', 'o.id')
            ->where('o.kind', OrderKind::Trial->value)
            ->where('o.payment_status', PaymentStatus::Paid->value)
            ->whereNull('o.deleted_at')
            ->groupBy('o.account_id')
            ->selectRaw('o.account_id, MIN(o.created_at) as started, MAX(l.end_date) as ends')
            ->get();

        $conversions = DB::table('subscription_orders')
            ->whereIn('kind', self::CONVERSION_KINDS)
            ->where('payment_status', PaymentStatus::Paid->value)
            ->whereNull('deleted_at')
            ->groupBy('account_id')
            ->selectRaw('account_id, MAX(created_at) as last_paid')
            ->pluck('last_paid', 'account_id');

        $cohorts = [];
        foreach ($trials as $t) {
            $month = Carbon::parse($t->started)->format('Y-m');
            $cohorts[$month] ??= ['started' => 0, 'converted' => 0, 'running' => 0, 'lapsed' => 0, 'rate' => null];
            $cohorts[$month]['started']++;

            $paidAfter = isset($conversions[$t->account_id]) && Carbon::parse($conversions[$t->account_id])->greaterThanOrEqualTo(Carbon::parse($t->started));

            if ($paidAfter) {
                $cohorts[$month]['converted']++;
            } elseif ($t->ends && Carbon::parse($t->ends)->startOfDay()->greaterThan($today)) {
                $cohorts[$month]['running']++;
            } else {
                $cohorts[$month]['lapsed']++;
            }
        }

        foreach ($cohorts as &$c) {
            $decided = $c['converted'] + $c['lapsed'];
            $c['rate'] = $decided ? round($c['converted'] / $decided * 100, 1) : null;
        }
        krsort($cohorts);

        return $cohorts;
    }

    // ── the page ────────────────────────────────────────────────────────────

    /** Twelve months back from this one, oldest first: [label, from, to]. */
    public function months(int $count = 12, ?CarbonInterface $today = null): array
    {
        $today = $this->day($today);

        return collect(range($count - 1, 0))->map(function ($back) use ($today) {
            $from = $today->copy()->startOfMonth()->subMonthsNoOverflow($back);

            return ['key' => $from->format('Y-m'), 'label' => $from->format('M y'), 'from' => $from, 'to' => $from->copy()->addMonthNoOverflow()];
        })->all();
    }

    /** Who the accounts are, in one query. @return array<int, array{name: string, public_id: string}> */
    public function accounts(array $accountIds): array
    {
        return SubscriptionAccount::withTrashed()->with('owner:id,name')->whereIn('id', $accountIds)->get()
            ->mapWithKeys(fn (SubscriptionAccount $a) => [$a->id => ['name' => $a->owner?->name ?? 'Account #'.$a->id, 'public_id' => $a->public_id]])->all();
    }
}
