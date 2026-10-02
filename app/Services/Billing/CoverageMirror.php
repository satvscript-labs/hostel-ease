<?php

namespace App\Services\Billing;

use App\Enums\PaymentStatus;
use App\Models\Hostel;
use App\Models\SubscriptionAccount;
use App\Models\SubscriptionOrderLine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * THE single writer of branch coverage (`hostels.subscription_start/end`).
 *
 * S0 established that only the billing service may write coverage. S1 goes one
 * step further and makes coverage DERIVED:
 *
 *     branch.subscription_end  ==  MAX(end_date) over that branch's PAID order lines
 *
 * so `hostels.subscription_*` is a write-through cache of the ledger rather than
 * state anybody edits. Three things follow, and they are the whole point:
 *
 *  1. It is CHECKABLE. "Is the coverage right?" is one query, not a judgement
 *     call — which is what makes `hostelease:audit-coverage` meaningful.
 *  2. It is IDEMPOTENT. sync() converges from any starting state, so it is safe to
 *     run after every op, on a schedule, or as a repair.
 *  3. It breaks the circular truth (finding F11): the anchor used to be derived
 *     from the branch mirrors AND the mirrors from the anchor. Now both derive
 *     from the lines, and neither from the other.
 *
 * The access gate is untouched — `Hostel::isActive()` still reads the mirror, so
 * entitlement stays one cheap column read per request.
 *
 * NOTE ON CANCELLATION (D11): a cancelled branch keeps projecting from the lines it
 * already holds, so it runs out exactly the coverage it paid for with no special
 * case anywhere in this class. Cancellation only removes it from the *billable*
 * set, which is `AccountBillingService::includedBranches()`.
 */
class CoverageMirror
{
    /**
     * Re-derive every branch on an account from the ledger, and return what moved.
     *
     * MONOTONIC BY DEFAULT — it only ever EXTENDS coverage. That is a deliberate
     * safety choice, not a compromise:
     *
     *  · a routine sync runs on every billing op and in the daily tick. If it were
     *    free to shorten, then ONE branch whose coverage predates the ledger (a
     *    pre-S1 grant the migration could not attribute, or any future path that
     *    slips past the invariant) would be silently cut off — a paying tenant
     *    locked out by a maintenance job. Unacceptable risk for the upside.
     *  · shortening coverage is therefore always DELIBERATE: voiding an order, or
     *    `hostelease:audit-coverage --fix`. Both pass $allowShorten and both are
     *    audited.
     *
     * Either way, a mismatch between mirror and ledger is logged as drift, so it is
     * visible rather than absorbed (finding F11) — and `drift()` reports it without
     * writing anything.
     *
     * @return array<int, array{branch: string, from: ?string, to: ?string}>
     */
    public function sync(SubscriptionAccount $account, bool $allowShorten = false): array
    {
        $branches = $this->allBranches($account);
        if ($branches->isEmpty()) {
            return [];
        }

        $supported = $this->supportedEnds($branches->pluck('id')->all());
        $changes = [];

        DB::transaction(function () use ($branches, $supported, $allowShorten, &$changes) {
            foreach ($branches as $branch) {
                $change = $this->apply($branch, $supported[$branch->id] ?? null, $allowShorten);
                if ($change) {
                    $changes[] = $change;
                }
            }
        });

        return $changes;
    }

    /** Re-derive one branch. Returns its coverage end after the sync. */
    public function syncBranch(Hostel $branch, bool $allowShorten = false): ?Carbon
    {
        $supported = $this->supportedEnds([$branch->id])[$branch->id] ?? null;
        $this->apply($branch, $supported, $allowShorten);

        return $branch->subscription_end;
    }

    /**
     * What the ledger says, versus what the mirror says — write nothing.
     *
     * Used by the daily tick and the audit so drift is REPORTED rather than
     * silently absorbed, which is how F1's doubled year became an account anchor.
     *
     * @return array<int, array{branch: string, mirror: ?string, ledger: ?string, days: int}>
     */
    public function drift(SubscriptionAccount $account): array
    {
        $branches = $this->allBranches($account);
        $supported = $this->supportedEnds($branches->pluck('id')->all());
        $out = [];

        foreach ($branches as $branch) {
            $ledger = $supported[$branch->id] ?? null;
            $mirror = $branch->subscription_end;

            if ($this->sameDay($mirror, $ledger)) {
                continue;
            }

            $out[] = [
                'branch' => $branch->name,
                'mirror' => $mirror?->toDateString(),
                'ledger' => $ledger?->toDateString(),
                'days' => ($mirror && $ledger)
                    ? (int) $ledger->copy()->startOfDay()->diffInDays($mirror->copy()->startOfDay(), false)
                    : 0,
            ];
        }

        return $out;
    }

    /**
     * The account anchor the ledger supports: the furthest coverage across the
     * BILLABLE branches.
     *
     * Billable, not all — a cancelled branch must not hold the anchor open for the
     * rest of the account (D11 case 8). Its own coverage is unaffected; it simply
     * stops being what everyone else renews against.
     */
    public function supportedAnchor(SubscriptionAccount $account, iterable $billableBranches): ?Carbon
    {
        $branches = collect($billableBranches);
        if ($branches->isEmpty()) {
            return null;
        }

        $supported = $this->supportedEnds($branches->pluck('id')->all());

        // Monotonic, for the same reason sync() is: per branch, take the later of
        // what the ledger supports and what the branch currently holds. With a
        // consistent ledger these are equal; where coverage predates the ledger the
        // mirror wins, so a maintenance pass can never quietly pull an account's
        // renewal date backwards (or null it, which would empty the renewals
        // worklist and the owner's "renews on" line).
        return $branches
            ->map(function (Hostel $branch) use ($supported) {
                $ledger = $supported[$branch->id] ?? null;
                $mirror = $branch->subscription_end;

                if (! $ledger) {
                    return $mirror;
                }
                if (! $mirror) {
                    return $ledger;
                }

                return $ledger->greaterThan($mirror) ? $ledger : $mirror;
            })
            ->filter()
            ->max();
    }

    /**
     * The anchor the ledger supports on its own, ignoring the mirrors — what the
     * account's renewal date WOULD be if coverage were strictly earned. Used by the
     * audit and by the deliberate-shortening path.
     */
    public function strictAnchor(iterable $billableBranches): ?Carbon
    {
        $ids = collect($billableBranches)->pluck('id')->all();

        return $ids ? collect($this->supportedEnds($ids))->filter()->max() : null;
    }

    // -----------------------------------------------------------------
    // Internals
    // -----------------------------------------------------------------

    /**
     * EVERY branch the owner holds, cancelled ones included — the mirror has to be
     * maintained for a cancelled branch too, right up to its end date.
     */
    private function allBranches(SubscriptionAccount $account): \Illuminate\Support\Collection
    {
        $owner = $account->owner;
        if (! $owner) {
            return collect();
        }

        return Hostel::whereIn('id', $owner->accessibleHostelIds())->orderBy('name')->get();
    }

    /**
     * MAX(end_date) per branch over PAID order lines — the ledger's answer.
     *
     * @param  int[]  $branchIds
     * @return array<int, Carbon>
     */
    private function supportedEnds(array $branchIds): array
    {
        if (! $branchIds) {
            return [];
        }

        return SubscriptionOrderLine::query()
            ->whereIn('branch_id', $branchIds)
            ->whereHas('order', fn ($q) => $q->where('payment_status', PaymentStatus::Paid->value))
            ->selectRaw('branch_id, MAX(end_date) as supported_end')
            ->groupBy('branch_id')
            ->pluck('supported_end', 'branch_id')
            ->map(fn ($date) => $date ? Carbon::parse($date) : null)
            ->filter()
            ->all();
    }

    /**
     * Write the projection onto one branch.
     *
     * `status` is deliberately NOT managed here. It carries operator intent
     * (suspended) and is set by suspend()/reactivate(); overwriting it from a
     * coverage sync would silently lift a manual hold — the exact bug Phase 5
     * fixed. The one thing this does touch is lifting a branch out of `expired`
     * when the ledger now covers it, because that is coverage, not intent.
     *
     * @return array{branch: string, from: ?string, to: ?string}|null
     */
    private function apply(Hostel $branch, ?Carbon $supportedEnd, bool $allowShorten = false): ?array
    {
        $from = $branch->subscription_end;

        if ($this->sameDay($from, $supportedEnd)) {
            return null;
        }

        // A sync that would REDUCE coverage is drift, not an instruction — unless the
        // caller explicitly asked to shorten (voiding an order, or the audit's --fix).
        $wouldShorten = $from && (! $supportedEnd || $supportedEnd->copy()->startOfDay()->lessThan($from->copy()->startOfDay()));

        if ($wouldShorten && ! $allowShorten) {
            Log::warning('CoverageMirror: branch holds more coverage than the ledger supports — left alone', [
                'branch_id' => $branch->id,
                'mirror' => $from->toDateString(),
                'ledger' => $supportedEnd?->toDateString(),
                'hint' => 'run hostelease:audit-coverage to see why, and --fix to correct it',
            ]);

            return null;
        }

        $attributes = ['subscription_end' => $supportedEnd];

        if ($supportedEnd) {
            // The earliest line start is the honest coverage start; keep an existing
            // one so a long-standing customer's start date does not jump around.
            $attributes['subscription_start'] = $branch->subscription_start ?? $this->earliestStart($branch);

            if ($branch->status === 'expired' && ! $supportedEnd->copy()->endOfDay()->isPast()) {
                $attributes['status'] = 'active';
            }
        }

        $branch->forceFill($attributes)->save();

        Log::info('CoverageMirror: branch coverage re-derived from the ledger', [
            'branch_id' => $branch->id,
            'from' => $from?->toDateString(),
            'to' => $supportedEnd?->toDateString(),
        ]);

        return [
            'branch' => $branch->name,
            'from' => $from?->toDateString(),
            'to' => $supportedEnd?->toDateString(),
        ];
    }

    private function earliestStart(Hostel $branch): ?Carbon
    {
        $start = SubscriptionOrderLine::where('branch_id', $branch->id)
            ->whereHas('order', fn ($q) => $q->where('payment_status', PaymentStatus::Paid->value))
            ->min('start_date');

        return $start ? Carbon::parse($start) : null;
    }

    private function sameDay(?Carbon $a, ?Carbon $b): bool
    {
        if (! $a && ! $b) {
            return true;
        }

        return $a && $b && $a->copy()->startOfDay()->equalTo($b->copy()->startOfDay());
    }
}
