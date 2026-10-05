<?php

namespace App\Console\Commands;

use App\Enums\BillingPeriod;
use App\Models\Hostel;
use App\Models\Subscription;
use App\Models\SubscriptionAccount;
use App\Models\SubscriptionOrder;
use App\Models\SubscriptionOrderLine;
use App\Support\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * READ-ONLY coverage audit (S0 · the input to decision D1).
 *
 * Finding F1: Super Admin provisioning used to stamp coverage onto the branch and
 * then ask the billing service to grant a term on top of it, so a paid yearly
 * provision granted TWO years for one year's money and a trial ran 28 days. The
 * code is fixed; this command measures what the bug already handed out, so the
 * owner can decide what — if anything — to do about live customers (D1).
 *
 * Without `--fix` it writes NOTHING — safe to run anywhere. `--csv=` dumps the
 * row-level findings for a spreadsheet. `--fix` applies the correction decided in
 * D1 (see applyFixes()).
 *
 * Five checks:
 *   1. Coverage held vs coverage paid for, rolled up per branch.
 *   2. Entitled branches with no paid record behind them at all.
 *   3. Branches with no coverage end date — these had unlimited access before S0
 *      and are correctly not entitled after it, so they are the rows most likely
 *      to generate a support call.
 *   4. Account anchor vs branch mirrors — the two should agree (finding F11).
 *   5. Stale current_period_start — a "current period" longer than one term
 *      (finding F15).
 */
class AuditCoverage extends Command
{
    protected $signature = 'hostelease:audit-coverage
        {--csv= : Write row-level findings to this CSV path}
        {--fix : Bring every account onto one renewal date (doc 22): coverage past it becomes free renewals; re-derives anchors; repairs stale cycle starts}
        {--force : Skip the --fix confirmation prompt}';

    protected $description = 'Audit (and optionally correct) subscription coverage against what the ledger supports (S0 / decision D1).';

    /**
     * A window longer than this many terms is a double-stamp, not a rounding
     * artefact. 1.35 leaves room for month-length and leap-day wobble while still
     * catching the 2.0 the bug produced.
     */
    private const DOUBLE_THRESHOLD = 1.35;

    /** @var array<int, array<string, mixed>> */
    private array $rows = [];

    /**
     * Corrections --fix would apply, collected by the checks that run before it.
     *
     * @var array{cycle_start: array<int, array{account: SubscriptionAccount, to: Carbon}>}
     */
    private array $fixes = ['cycle_start' => []];

    public function handle(): int
    {
        $fix = (bool) $this->option('fix');
        $this->rows = [];
        $this->fixes = ['cycle_start' => []];

        $this->line('');
        $this->info($fix
            ? '  Coverage audit — FIX MODE. Coverage past a renewal date becomes free renewals; anchors and cycle starts are repaired.'
            : '  Coverage audit — read-only. Nothing is modified.');
        $this->line('  '.now()->format('d M Y H:i').'  ·  grace window: '.config('hostelease.grace_days').' days');
        $this->line('');

        $over = $this->checkOverGrantedCoverage();
        $this->checkUnbackedCoverage();
        $this->checkMissingCoverage();
        $this->checkAnchorDrift();
        $this->checkStalePeriodStart();
        $this->checkPastRenewalDate();

        $this->summarise($over, $fix);

        if ($path = $this->option('csv')) {
            $this->writeCsv($path);
        }

        if ($fix) {
            return $this->applyFixes();
        }

        return self::SUCCESS;
    }

    // -----------------------------------------------------------------
    // --fix (decision D1)
    // -----------------------------------------------------------------

    /**
     * Correct the ledger. Owner decision D1, settled 2026-10-02 once it was clear
     * there are no live customers yet: rather than carrying the over-granted
     * coverage forward as goodwill, bring the data in line with what was actually
     * paid for, so the invariants S1 is about to enforce start from a clean base.
     *
     * Three corrections, in this order because each depends on the last:
     *   1. Shorten over-granted branch coverage to what its payments bought.
     *   2. Re-derive every account anchor/status from the corrected mirrors.
     *   3. Repair stale cycle starts from the corrected anchor (finding F15).
     *
     * Deliberately NOT corrected: check 2 (entitled branches with no paid record).
     * Those are not over-granted — they are grants that never reached the ledger,
     * mostly self-signup trials, and nulling their coverage would lock out
     * legitimately-trialling tenants. The real fix is routing that path through the
     * biller, which is S1.
     *
     * Every change is written to the activity log, tenant-bound to the branch.
     */
    private function applyFixes(): int
    {
        // ONE RENEWAL DATE PER ACCOUNT (doc 22, owner decision 2026-10-06): coverage a
        // branch holds past its account's renewal date — gifted time, a hand edit, an
        // old double-stamp — is not deleted, it becomes free renewals. Planned first
        // (nothing written), shown, confirmed, then applied.
        $billing = app(\App\Services\Billing\AccountBillingService::class);
        $coverage = [];
        foreach (SubscriptionAccount::cursor() as $account) {
            foreach ($billing->foldGiftsIntoFreeRenewals($account, dryRun: true) as $row) {
                $coverage[] = $row + ['account' => $account];
            }
        }
        $cycleStarts = $this->fixes['cycle_start'];

        if (! $coverage && ! $cycleStarts) {
            $this->info('  Nothing to fix — the ledger already agrees with itself.');
            $this->line('');

            return self::SUCCESS;
        }

        $this->warn('  --fix will change data:');
        foreach ($coverage as $row) {
            $this->line("    · {$row['branch']->name}: ".$row['from']->format('d M Y').' → '.$row['to']->format('d M Y')
                ." ({$row['days']} days past the renewal date) = {$row['free_renewals']} free renewal(s)");
        }
        $this->line('    · every account anchor + status re-derived from the corrected branches');
        $this->line('    · '.count($cycleStarts).' account cycle start(s) repaired');
        $this->line('');

        if (! $this->option('force') && ! $this->confirm('Apply these corrections?', false)) {
            $this->line('  Aborted. Nothing was changed.');

            return self::SUCCESS;
        }

        $logger = app(\App\Services\ActivityLogger::class);

        // 1. One renewal date per account; the excess becomes free renewals.
        $done = 0;
        foreach (collect($coverage)->pluck('account')->unique('id') as $account) {
            foreach ($billing->foldGiftsIntoFreeRenewals($account->fresh()) as $row) {
                $branch = $row['branch'];
                $done++;

                Tenant::set($branch->id);
                try {
                    $logger->log(
                        'subscription.update',
                        "One renewal date: {$branch->name} brought back from ".$row['from']->format('d M Y').' to '.$row['to']->format('d M Y')
                            ." — {$row['days']} days past the renewal date became {$row['free_renewals']} free renewal(s).",
                        $branch,
                        ['from' => $row['from']->toDateString(), 'to' => $row['to']->toDateString(), 'days' => $row['days'], 'free_renewals' => $row['free_renewals']],
                    );
                } finally {
                    Tenant::clear();
                }

                $this->line("    ✓ {$branch->name}: ".$row['from']->format('d M Y').' → '.$row['to']->format('d M Y')." · +{$row['free_renewals']} free renewal(s)");
            }
        }

        // 2. Account anchors + status, from the corrected mirrors.
        $anchorsMoved = 0;
        foreach (SubscriptionAccount::cursor() as $account) {
            $before = $account->current_period_end?->toDateString();
            $billing->refreshAccountAnchor($account);
            $account->refresh();

            if ($account->current_period_end?->toDateString() !== $before) {
                $anchorsMoved++;
                $this->line('    ✓ anchor for '.($account->owner?->name ?? 'account #'.$account->id)
                    .': '.($before ?? 'none').' → '.($account->current_period_end?->toDateString() ?? 'none'));
            }
        }

        // 3. Cycle starts, now that the anchors are right. Re-read each account so
        //    the start is derived from the corrected anchor, not the stale one.
        $startsFixed = 0;
        foreach ($cycleStarts as $fix) {
            $account = $fix['account']->fresh();
            if (! $account || ! $account->current_period_end) {
                continue;
            }

            $period = $account->period ?? BillingPeriod::Yearly;
            $start = $period->cycleStart($account->current_period_end);
            $was = $account->current_period_start?->toDateString();

            if ($was === $start->toDateString()) {
                continue;   // already right once the dates above were corrected
            }

            $account->forceFill(['current_period_start' => $start])->save();
            $startsFixed++;

            app(\App\Services\ActivityLogger::class)->log(
                'subscription.update',
                'Cycle start corrected (D1, F15) for '.($account->owner?->name ?? 'account #'.$account->id)
                    .': '.($was ?? 'none').' → '.$start->toDateString(),
                $account,
            );

            $this->line('    ✓ cycle start for '.($account->owner?->name ?? 'account #'.$account->id)
                .': '.($was ?? 'none').' → '.$start->toDateString());
        }

        $this->line('');
        $this->info("  Corrected: {$done} branch(es) onto their renewal date · {$anchorsMoved} anchor(s) · {$startsFixed} cycle start(s).");
        $this->line('  Re-run without --fix to confirm the ledger is clean.');
        $this->line('');

        return self::SUCCESS;
    }

    // -----------------------------------------------------------------
    // 1. Coverage held vs coverage paid for (the F1 damage report)
    // -----------------------------------------------------------------

    /**
     * Per BRANCH, not per record.
     *
     * Rolling up per branch matters because the same payment can appear twice —
     * once as a legacy `subscriptions` row and once as the `subscription_order`
     * mirrored from it — and because a renewal stacked onto an already-inflated
     * anchor produces a THIRD overlapping window. Counting records would multiply
     * the same rupees. The canonical grant set is therefore every paid legacy row,
     * plus every paid order line whose order was NOT mirrored from one.
     *
     * entitled_days = Σ over paid grants of the days that grant should have bought
     * (one term for a renewal, its own span for a proration or a multi-term comp).
     * paid_up_to = first grant's start + entitled_days. Anything the branch holds
     * past that is coverage nobody paid for.
     *
     * @return array{future_days: int, future_value: float, past_days: int}
     */
    private function checkOverGrantedCoverage(): array
    {
        $this->components->twoColumnDetail('<options=bold>1. Coverage held vs coverage paid for</>', 'F1');

        $findings = [];
        $futureDays = 0;
        $pastDays = 0;
        $futureValue = 0.0;
        $today = Carbon::now()->startOfDay();

        /** @var array<int, array{period:string, grants:int, entitled:int, first:?Carbon}> $perBranch */
        $perBranch = [];

        $accrue = function (int $branchId, string $plan, ?Carbon $start, ?Carbon $end, bool $fullSpanEntitled) use (&$perBranch): void {
            if (! $start || ! $end || $end->lessThanOrEqualTo($start)) {
                return;
            }

            $period = BillingPeriod::tryFrom($plan) ?? BillingPeriod::Yearly;
            $startDay = $start->copy()->startOfDay();
            $span = (int) $startDay->diffInDays($end->copy()->startOfDay());
            $oneTerm = (int) $startDay->diffInDays($period->extend($startDay->copy()));

            // A COMP is a deliberate multi-term gift and an ADJUSTMENT is a deliberate
            // operator correction (including the S1 migration's back-fill of coverage
            // that predates the ledger) — for both, the whole span is entitled by
            // definition, so measuring them against "one term" would invent an overage
            // that never existed. Everything else buys at most one term; a proration
            // buys less.
            $entitled = $fullSpanEntitled ? $span : min($span, $oneTerm);

            $perBranch[$branchId] ??= ['period' => $period->value, 'grants' => 0, 'entitled' => 0, 'first' => null];
            $perBranch[$branchId]['grants']++;
            $perBranch[$branchId]['entitled'] += $entitled;
            $perBranch[$branchId]['first'] = $perBranch[$branchId]['first'] && $perBranch[$branchId]['first']->lessThan($startDay)
                ? $perBranch[$branchId]['first']
                : $startDay;

            // Value the overage at a paid rate when the branch has ever had one.
            if ($period->isPaid()) {
                $perBranch[$branchId]['period'] = $period->value;
            }
        };

        foreach (Subscription::acrossHostels()->where('payment_status', 'paid')->cursor() as $sub) {
            $accrue($sub->hostel_id, (string) $sub->plan, $sub->start_date, $sub->end_date, $sub->payment_method === 'comp');
        }

        // Order ids that merely mirror a legacy row — their lines are duplicates.
        $mirroredOrderIds = SubscriptionOrder::whereNotNull('legacy_subscription_id')->pluck('id')->all();

        foreach (SubscriptionOrderLine::with('order')->whereNotIn('order_id', $mirroredOrderIds ?: [0])->cursor() as $line) {
            if ($line->order?->payment_status?->value !== 'paid') {
                continue;
            }

            $accrue(
                $line->branch_id,
                $line->order->period?->value ?? 'yearly',
                $line->start_date,
                $line->end_date,
                in_array($line->order->kind, [\App\Enums\OrderKind::Comp, \App\Enums\OrderKind::Adjustment], true)
                    || $line->order->payment_method?->value === 'comp',
            );
        }

        foreach (Hostel::with('owner')->get() as $branch) {
            $held = $branch->subscription_end;
            $roll = $perBranch[$branch->id] ?? null;

            if (! $held || ! $roll || ! $roll['first']) {
                continue;   // no coverage, or no grants at all — checks 2 and 3 cover those
            }

            $paidUpTo = $roll['first']->copy()->addDays($roll['entitled']);
            $heldDay = $held->copy()->startOfDay();
            $over = (int) $paidUpTo->diffInDays($heldDay, false);

            if ($over <= 1) {
                continue;   // a day of rounding slack
            }

            // Split what they still hold from what they have already used up: only
            // the first is recoverable, and it is what D1 is actually about.
            $countFrom = $paidUpTo->greaterThan($today) ? $paidUpTo : $today;
            $future = $heldDay->greaterThan($today) ? min($over, (int) $countFrom->diffInDays($heldDay)) : 0;
            $value = $this->valueOfDays($branch, $roll['period'], $future, $heldDay);

            $futureDays += $future;
            $pastDays += $over - $future;
            $futureValue += $value;


            $findings[] = [
                $branch->name,
                $branch->owner?->name ?? '—',
                $roll['grants'],
                $roll['first']->format('d M Y'),
                $paidUpTo->format('d M Y'),
                $heldDay->format('d M Y'),
                $over,
                $future,
                hostelease_money($value),
            ];
        }

        $this->render(
            $findings,
            ['Branch', 'Owner', 'Grants', 'First covered', 'Paid up to', 'Covered to', 'Over (days)', 'Still future', 'Value (future)'],
            'Every branch holds exactly the coverage its payments bought.',
        );

        foreach ($findings as $f) {
            $this->rows[] = array_merge(['check' => 'over_granted'], array_combine(
                ['branch', 'owner', 'grants', 'first_covered', 'paid_up_to', 'covered_to', 'over_days', 'future_days', 'future_value'],
                $f,
            ));
        }

        return ['future_days' => $futureDays, 'future_value' => $futureValue, 'past_days' => $pastDays];
    }

    /** What N days of coverage are worth at this branch's account rate. */
    private function valueOfDays(?Hostel $branch, string $plan, int $days, Carbon $at): float
    {
        if ($days <= 0) {
            return 0.0;
        }

        $period = BillingPeriod::tryFrom($plan) ?? BillingPeriod::Yearly;
        if (! $period->isPaid()) {
            return 0.0;   // extra trial days cost us nothing but time
        }

        $account = $branch?->owner_id ? SubscriptionAccount::where('owner_id', $branch->owner_id)->first() : null;
        $unit = $account
            ? app(\App\Services\Billing\AccountBillingService::class)->unitPrice($account, $period)
            : (float) config('hostelease.subscription_pricing.'.$period->value, 0);

        return round($unit * $days / $period->cycleDays($at), 2);
    }

    // -----------------------------------------------------------------
    // 2. Coverage nothing paid for
    // -----------------------------------------------------------------

    private function checkUnbackedCoverage(): void
    {
        $this->components->twoColumnDetail('<options=bold>2. Entitled branches with no paid record</>', 'F3');

        $paidLineBranchIds = SubscriptionOrderLine::whereHas(
            'order',
            fn ($q) => $q->where('payment_status', 'paid'),
        )->distinct()->pluck('branch_id')->all();

        $paidLegacyBranchIds = Subscription::acrossHostels()
            ->where('payment_status', 'paid')->distinct()->pluck('hostel_id')->all();

        $backed = array_unique(array_merge($paidLineBranchIds, $paidLegacyBranchIds));

        $findings = Hostel::whereNotIn('id', $backed ?: [0])
            ->whereNotNull('subscription_end')
            ->with('owner')
            ->get()
            ->filter(fn (Hostel $h) => $h->isActive())
            ->map(fn (Hostel $h) => [
                $h->name,
                $h->owner?->name ?? '—',
                optional($h->subscription_end)->format('d M Y'),
                $h->daysUntilExpiry().' days left',
                $h->status,
            ])->values()->all();

        $this->render($findings, ['Branch', 'Owner', 'Covered to', 'Remaining', 'Status'],
            'Every entitled branch has at least one paid or comped record behind it.');

        foreach ($findings as $f) {
            $this->rows[] = array_merge(['check' => 'unbacked_coverage'], array_combine(
                ['branch', 'owner', 'covered_to', 'remaining', 'status'], $f,
            ));
        }
    }

    // -----------------------------------------------------------------
    // 3. Branches with no coverage end date
    // -----------------------------------------------------------------

    private function checkMissingCoverage(): void
    {
        $this->components->twoColumnDetail('<options=bold>3. Branches with NO coverage end date</>', 'S0 behaviour change');

        $findings = Hostel::whereNull('subscription_end')->with('owner')->get()
            ->map(fn (Hostel $h) => [
                $h->name,
                $h->owner?->name ?? '—',
                $h->status,
                $h->created_at?->format('d M Y') ?? '—',
            ])->all();

        if ($findings) {
            $this->warn('     These had UNLIMITED access before S0 (a null end date read as active) and are');
            $this->warn('     correctly NOT entitled now. Grant a trial, a comp, or record their payment.');
        }

        $this->render($findings, ['Branch', 'Owner', 'Status', 'Created'],
            'No branch is missing a coverage end date.');

        foreach ($findings as $f) {
            $this->rows[] = array_merge(['check' => 'missing_coverage'], array_combine(
                ['branch', 'owner', 'status', 'created'], $f,
            ));
        }
    }

    // -----------------------------------------------------------------
    // 4. Account anchor vs the branch mirrors it is supposed to match
    // -----------------------------------------------------------------

    private function checkAnchorDrift(): void
    {
        $this->components->twoColumnDetail('<options=bold>4. Account anchor vs branch coverage</>', 'F11');

        $findings = [];

        foreach (SubscriptionAccount::with('owner')->cursor() as $account) {
            $branches = $account->owner
                ? Hostel::whereIn('id', $account->owner->accessibleHostelIds())->get()
                : collect();

            if ($branches->isEmpty()) {
                continue;
            }

            $maxEnd = $branches->max('subscription_end');
            $anchor = $account->current_period_end;

            if (! $anchor && ! $maxEnd) {
                continue;
            }

            $driftDays = ($anchor && $maxEnd)
                ? (int) $anchor->copy()->startOfDay()->diffInDays($maxEnd->copy()->startOfDay(), false)
                : null;

            if ($driftDays === 0) {
                continue;
            }

            $findings[] = [
                $account->owner?->name ?? 'account #'.$account->id,
                $branches->count(),
                $anchor?->format('d M Y') ?? 'none',
                $maxEnd?->format('d M Y') ?? 'none',
                $driftDays === null ? 'one side missing' : ($driftDays > 0 ? "branch +{$driftDays}d" : 'anchor +'.abs($driftDays).'d'),
                $account->status->value,
            ];
        }

        $this->render($findings, ['Customer', 'Branches', 'Account anchor', 'Furthest branch', 'Drift', 'Status'],
            'Every account anchor matches its furthest branch.');

        foreach ($findings as $f) {
            $this->rows[] = array_merge(['check' => 'anchor_drift'], array_combine(
                ['customer', 'branches', 'anchor', 'furthest_branch', 'drift', 'status'], $f,
            ));
        }
    }

    // -----------------------------------------------------------------
    // 5. Stale current_period_start
    // -----------------------------------------------------------------

    private function checkStalePeriodStart(): void
    {
        $this->components->twoColumnDetail('<options=bold>5. Accounts with a stale cycle start</>', 'F15');

        $findings = [];

        foreach (SubscriptionAccount::with('owner')->cursor() as $account) {
            $start = $account->current_period_start;
            $end = $account->current_period_end;
            if (! $start || ! $end || $end->lessThanOrEqualTo($start)) {
                continue;
            }

            $period = $account->period ?? BillingPeriod::Yearly;
            $span = (int) $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay());
            $expected = $period->cycleDays($end);

            if ($span <= (int) round($expected * self::DOUBLE_THRESHOLD)) {
                continue;
            }

            $this->fixes['cycle_start'][] = ['account' => $account, 'to' => $period->cycleStart($end)];

            $findings[] = [
                $account->owner?->name ?? 'account #'.$account->id,
                $period->value,
                $start->format('d M Y'),
                $end->format('d M Y'),
                $span.' / '.$expected,
            ];
        }

        if ($findings) {
            $this->line('     Harmless to access, but the window is wrong for reporting. S0 fixes it going');
            $this->line('     forward (the next renewal writes a correct start); --fix repairs it now.');
        }

        $this->render($findings, ['Customer', 'Term', 'Cycle start', 'Cycle end', 'Days (actual/expected)'],
            'Every account cycle is one term long.');

        foreach ($findings as $f) {
            $this->rows[] = array_merge(['check' => 'stale_period_start'], array_combine(
                ['customer', 'term', 'cycle_start', 'cycle_end', 'days'], $f,
            ));
        }
    }

    // -----------------------------------------------------------------
    // Output
    // -----------------------------------------------------------------

    /** @param  array<int, array<int, mixed>>  $findings */
    // -----------------------------------------------------------------
    // 6. Past the renewal date (doc 22)
    // -----------------------------------------------------------------

    /**
     * Branches holding coverage past their account's renewal date — old gifts, hand
     * edits, double-stamps. Each one splits the account's single date. Read-only here
     * (a dry run of the fold); --fix turns the excess into free renewals.
     */
    private function checkPastRenewalDate(): void
    {
        $this->components->twoColumnDetail('<options=bold>6. Branches past their renewal date</>', 'one date');

        $billing = app(\App\Services\Billing\AccountBillingService::class);
        $findings = [];

        foreach (SubscriptionAccount::with('owner')->cursor() as $account) {
            foreach ($billing->foldGiftsIntoFreeRenewals($account, dryRun: true) as $row) {
                $findings[] = [
                    $row['branch']->name,
                    $account->owner?->name ?? '—',
                    $row['to']->format('d M Y'),
                    $row['from']->format('d M Y'),
                    $row['days'],
                    $row['free_renewals'],
                ];
            }
        }

        if ($findings) {
            $this->line('     Each one moves its account\'s renewal date. --fix brings it back to the date and');
            $this->line('     keeps the time as free renewals.');
        }

        $this->render($findings, ['Branch', 'Owner', 'Renewal date', 'Covered to', 'Days past', 'Free renewals'],
            'Every branch ends on its account\'s renewal date.');
    }

    private function render(array $findings, array $headers, string $cleanMessage): void
    {
        if (! $findings) {
            $this->line('     <fg=green>✓</> '.$cleanMessage);
            $this->line('');

            return;
        }

        $this->line('');
        $this->table($headers, $findings);
    }

    /** @param  array{future_days:int, future_value:float, past_days:int}  $over */
    private function summarise(array $over, bool $fix = false): void
    {
        $this->line('');
        $this->info('  ── Summary ────────────────────────────────────────────────');
        $this->components->twoColumnDetail('Customers (accounts)', (string) SubscriptionAccount::count());
        $this->components->twoColumnDetail('Branches', (string) Hostel::count());
        $this->components->twoColumnDetail('Paid orders', (string) SubscriptionOrder::paid()->count());
        $this->components->twoColumnDetail('Pending orders (receivables)', hostelease_money(
            (float) SubscriptionOrder::where('payment_status', 'pending')->sum('amount'),
        ));
        $this->components->twoColumnDetail('Findings logged', (string) count($this->rows));
        $this->line('');
        $this->components->twoColumnDetail(
            '<options=bold>Over-granted coverage still unexpired</>',
            '<options=bold>'.$over['future_days'].' days  ·  '.hostelease_money($over['future_value']).'</>',
        );
        $this->components->twoColumnDetail('Over-granted coverage already consumed', $over['past_days'].' days (unrecoverable)');
        $this->line('');

        if (! $fix) {
            $this->line('  That bolded figure is coverage customers hold and did not pay for (decision');
            $this->line('  <options=bold>D1</>). Re-run with <options=bold>--fix</> to shorten it to what the ledger supports.');
            $this->line('  <fg=cyan>_artifact/saas_billing_autopay/03_FINDINGS.md</>');
            $this->line('');
        }
    }

    private function writeCsv(string $path): void
    {
        if (! $this->rows) {
            $this->line('  Nothing to write — no findings.');

            return;
        }

        // Union of every check's keys so one flat file holds all five sections.
        $headers = [];
        foreach ($this->rows as $row) {
            $headers = array_merge($headers, array_keys($row));
        }
        $headers = array_values(array_unique($headers));

        $handle = fopen($path, 'w');
        fputcsv($handle, $headers);
        foreach ($this->rows as $row) {
            fputcsv($handle, array_map(fn ($h) => $row[$h] ?? '', $headers));
        }
        fclose($handle);

        $this->info('  Findings written to '.$path);
        $this->line('');
    }
}
