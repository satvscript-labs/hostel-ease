<?php

namespace App\Services\Billing;

use App\Enums\AccountStatus;
use App\Enums\BillingPeriod;
use App\Enums\CollectionMethod;
use App\Enums\OrderKind;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Hostel;
use App\Models\SubscriptionAccount;
use App\Models\SubscriptionOrder;
use App\Models\SubscriptionOrderLine;
use App\Models\User;
use App\Services\BranchBillingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Account-level billing brain (BRD §6). One owner = one account with a single
 * anchor date; branches renew together.
 *
 *  - recordBranchRenewal(): the compat drop-in for the existing per-branch flows
 *    (web / webhook / offline / provision). It delegates the branch coverage +
 *    legacy `subscriptions` write to BranchBillingService (behaviour unchanged),
 *    then maintains the account/order spine on top.
 *  - renewAccount() / addBranch() / align() / comp(): the consolidated ops the
 *    Super Admin control terminal (Phase 4) drives.
 *
 * Access control is untouched: this service mirrors the anchor onto each
 * branch's hostels.subscription_end, which is what Hostel::isActive() reads.
 */
class AccountBillingService
{
    public function __construct(
        protected BranchBillingService $branchBilling,
        protected DiscountService $discounts,
        protected CoverageMirror $mirror,
    ) {}

    // -----------------------------------------------------------------
    // Account resolution
    // -----------------------------------------------------------------

    public function accountFor(User $owner): SubscriptionAccount
    {
        return SubscriptionAccount::firstOrCreate(
            ['owner_id' => $owner->id],
            ['period' => BillingPeriod::Trial->value, 'status' => AccountStatus::Trial->value],
        );
    }

    public function accountForBranch(Hostel $branch): ?SubscriptionAccount
    {
        $owner = $this->ownerForBranch($branch);

        return $owner ? $this->accountFor($owner) : null;
    }

    /**
     * The account a VIEWER should be shown.
     *
     * accountFor() is a firstOrCreate keyed on owner_id, so calling it with
     * `$request->user()` mints an account for whoever is looking. A co-admin is
     * also a `hostel_admin` (there is no separate role), so merely opening
     * Settings or the Subscription page silently turned them into a "customer"
     * of their own — a phantom trial account in the Super Admin's Customers
     * list, owning no branches.
     *
     * A co-admin belongs to the OWNER's account. Resolve that first; an account
     * is only ever created for someone who genuinely owns a branch.
     */
    public function accountForViewer(User $viewer): SubscriptionAccount
    {
        return $this->accountFor($this->ownerForViewer($viewer));
    }

    public function ownerForViewer(User $viewer): User
    {
        $accessibleIds = $viewer->accessibleHostelIds();

        // 1. They own one of the branches they can see — they ARE the owner.
        if (Hostel::whereIn('id', $accessibleIds)->where('owner_id', $viewer->id)->exists()) {
            return $viewer;
        }

        // 2. They already hold an account: never strand an existing one (e.g. a
        //    freshly-registered owner whose branch FK isn't linked yet).
        if (SubscriptionAccount::where('owner_id', $viewer->id)->exists()) {
            return $viewer;
        }

        // 3. Co-admin: the account is the one owning the branches they work in.
        //    The explicit FK is the authority (P4 item 14) — deterministic, and
        //    read-only here, so a page view can never re-point ownership.
        $ownerId = Hostel::whereIn('id', $accessibleIds)
            ->whereNotNull('owner_id')
            ->where('owner_id', '!=', $viewer->id)
            ->value('owner_id');

        if ($ownerId && $owner = User::find($ownerId)) {
            return $owner;
        }

        // 4. Nothing resolvable (legacy row with no owner FK, or a brand-new
        //    owner mid-provision) — unchanged from previous behaviour.
        return $viewer;
    }

    public function ownerForBranch(Hostel $branch): ?User
    {
        // The explicit owner FK is the authority (P4 item 14) — deterministic,
        // and immune to a co-admin sorting first in the pivot.
        if ($branch->owner_id && $branch->owner && $branch->owner->isHostelAdmin()) {
            return $branch->owner;
        }

        // Legacy fallbacks (pre-FK rows, factory data): mobile identity first,
        // then any admin holding pivot access. Self-heal by persisting the
        // resolution so the system converges on the FK.
        $owner = User::where('role', 'hostel_admin')->where('mobile', $branch->mobile)->first()
            ?: User::where('role', 'hostel_admin')
                ->whereHas('hostels', fn ($q) => $q->where('hostels.id', $branch->id))
                ->orderBy('id')->first();

        if ($owner) {
            $branch->forceFill(['owner_id' => $owner->id])->save();
        }

        return $owner;
    }

    /**
     * The branches an account is BILLED for — its quantity (BR-4/BR-5).
     *
     * The filter is `cancelled_at IS NULL` and NOTHING ELSE (D11 · rule R1). It is
     * tempting to also require `status = 'active'`; that would be a revenue bug.
     * An **expired** account's branches are not `active`, and a **suspended**
     * account's are `suspended` — so a status filter would quote a lapsed or held
     * customer for ZERO branches and their renewal would cost ₹0. Entitlement is a
     * separate question, answered by Hostel::isActive() at request time.
     *
     * A cancelled branch is excluded from here the moment the operator confirms the
     * removal, so the next renewal quote drops immediately — while the branch keeps
     * working until its own coverage runs out (rule R2), because that comes from its
     * order lines, not from this list.
     */
    public function includedBranches(SubscriptionAccount $account): Collection
    {
        return $this->allBranches($account)->whereNull('cancelled_at')->values();
    }

    /**
     * EVERY branch the owner holds, cancelled ones included. For surfaces that must
     * show the whole estate (Account 360, the owner's branch list) and for coverage
     * maintenance, which continues for a cancelled branch until its end date.
     */
    public function allBranches(SubscriptionAccount $account): Collection
    {
        $owner = $account->owner;
        if (! $owner) {
            return collect();
        }

        return Hostel::whereIn('id', $owner->accessibleHostelIds())->orderBy('name')->get();
    }

    public function unitPrice(SubscriptionAccount $account, BillingPeriod $period): float
    {
        if (! $period->isPaid()) {
            return 0.0;
        }

        // Per-period bespoke rate (BR-6): a custom yearly price no longer leaks
        // into monthly renewals and vice-versa.
        $override = $period === BillingPeriod::Monthly
            ? $account->unit_price_override_monthly
            : $account->unit_price_override_yearly;

        if ($override !== null) {
            return (float) $override;
        }

        return $this->branchBilling->unitPrice($period->value);
    }

    // -----------------------------------------------------------------
    // Single-branch charge (provisioning, trials, per-branch offline records)
    // -----------------------------------------------------------------

    /**
     * Record one branch's charge: an order with a single line, and the coverage
     * that line grants.
     *
     * S1: this no longer writes the legacy `subscriptions` table (decision D8). It
     * used to delegate to BranchBillingService::renewBranch() — which was the other
     * half of the F1 double-stamp and the only writer of the legacy ENUM that F6 was
     * about. Both problems disappear with the dual write.
     *
     * Returns the ORDER (it used to return the legacy Subscription). Only a `paid`
     * status grants coverage; a pending one is a proforma the operator can accept
     * later (S0 · finding F3).
     */
    public function recordBranchRenewal(Hostel $branch, string $period, array $payment = []): SubscriptionOrder
    {
        $account = $this->accountForBranch($branch);

        if (! $account) {
            // No resolvable owner — nothing to hang an account order off. Shouldn't
            // happen post-S0 (every creation path sets owner_id), but refusing
            // loudly beats silently granting coverage nothing can audit.
            throw new RuntimeException("Cannot bill branch #{$branch->id}: no owner account could be resolved.");
        }

        return DB::transaction(function () use ($account, $branch, $period, $payment) {
            $bp = BillingPeriod::tryFrom($period) ?? BillingPeriod::Yearly;

            // ONE FREE TRIAL PER ACCOUNT (owner decision, 2026-10-04) — and a branch
            // added WHILE that trial runs joins it (owner decision, 2026-10-05): it
            // works free until the trial ends, and everything is billed together when
            // the customer subscribes, or expires with the trial if they do not. Every
            // trial in the product is granted through this method, so the rule lives
            // here and nowhere else: self-signup, the owner's Add branch, and the
            // operator's Add hostel / Add to trial all meet it.
            $joinsTrial = false;
            if ($bp === BillingPeriod::Trial && ! $this->trialAvailable($account, $branch)) {
                if (! $this->trialJoinable($account)) {
                    throw new RuntimeException('This customer has already had their free trial, and it has ended — the trial belongs to the account, once, not to each branch. Choose a paid plan for this branch.');
                }
                $joinsTrial = true;
            }

            $quote = $this->branchBilling->quote($branch, $bp->value);

            // A paid single-branch term is priced at THIS ACCOUNT's unit price — its
            // negotiated rate when it has one — never the list price the per-branch
            // quoter knows about. (Reached when an account with no live cycle adds a
            // branch; the list price ignored a customer's custom rate.)
            if ($bp->isPaid()) {
                $quote['amount'] = $this->unitPrice($account, $bp);
            }

            if ($joinsTrial) {
                // Same window as the account's trial: today → the day it ends. ₹0, and
                // no posted amount can change that.
                $quote['start'] = Carbon::now()->startOfDay();
                $quote['end'] = $account->current_period_end->copy();
                $quote['amount'] = 0.0;
                unset($payment['amount']);
                $payment['remarks'] ??= 'Joined the free trial (until '.$account->current_period_end->format('d M Y').')';
            }

            $amount = $payment['amount'] ?? $quote['amount'];

            $order = $this->makeOrder(
                $account,
                $bp,
                1,
                (float) $amount,
                0,
                (float) $amount,
                $payment,
                $payment['kind'] ?? ($bp === BillingPeriod::Trial ? OrderKind::Trial : OrderKind::Renewal),
            );

            $this->addLine($order, $branch, (float) $amount, $quote['end'], $quote['start']);

            // Coverage follows the ledger, so a pending order grants nothing until
            // it is accepted — no branch of logic needed for the anchor.
            $this->mirror->sync($account);

            // The CADENCE is a different matter, and does need the branch (S2).
            // `period`/`status` are not ledger-derived, so handing this a paid period
            // for a pending charge would relabel the account as a paying one before
            // any money arrived — the same defect as addBranch()/align(). A paid or
            // trial grant passes its own period; a pending charge passes nothing and
            // leaves the account as it was until acceptOrder() settles it.
            //
            // A TRIAL grant must also never override an established paid cadence
            // (S3 · design 14 §1 P1). A trial is a property of ONE BRANCH's first two
            // weeks, not of the account. Handing `trial` through relabelled a paying
            // yearly account as period=trial / status=trial the moment its owner — or
            // the operator, via Account 360's "Add hostel → trial", which is live in
            // production — added a branch on a free trial. Only an account that has
            // never had a paid cadence takes the trial cadence from its first branch.
            $cadence = $order->payment_status->grantsCoverage() ? $bp : null;
            if ($cadence === BillingPeriod::Trial && $account->period?->isPaid()) {
                $cadence = null;
            }

            $this->refreshAccountAnchor($account, $cadence);

            return $order;
        });
    }

    /**
     * Can this account still have its free trial?
     *
     * The trial belongs to the ACCOUNT and is granted once — to its first branch,
     * when the customer first joins (owner decision, 2026-10-04). It is gone if:
     *
     *  · the account has ever had a trial — a trial order that was not voided (a
     *    voided one was a mistake, so it does not count as used); or
     *  · the account already holds any branch other than the one being set up — a
     *    second branch is never a trial branch, whatever the account's state.
     *
     * The second rule also catches accounts whose trial predates the ledger (S1
     * back-filled those as adjustments, not trials): they already have a branch.
     *
     * This also retires the trial-vs-renewal-date problem (16_S3_AUDIT.md §4): a
     * trial can no longer be added to an account that has a renewal date of its own.
     */
    public function trialJoinable(SubscriptionAccount $account): bool
    {
        // The account's trial is RUNNING: a trial cadence, trial status, and its end
        // date not yet passed (today counts — the trial covers its last day).
        return $account->period === BillingPeriod::Trial
            && $account->status === AccountStatus::Trial
            && $account->current_period_end
            && $account->current_period_end->copy()->endOfDay()->isFuture();
    }

    public function trialAvailable(SubscriptionAccount $account, ?Hostel $forBranch = null): bool
    {
        $hadTrial = $account->orders()
            ->where('kind', OrderKind::Trial->value)
            ->where('payment_status', '!=', PaymentStatus::Voided->value)
            ->exists();

        if ($hadTrial) {
            return false;
        }

        return $this->allBranches($account)
            ->reject(fn (Hostel $b) => $forBranch && $b->id === $forBranch->id)
            ->isEmpty();
    }

    /**
     * Accept a pending charge: the money arrived. Flips the order to paid, which is
     * what grants its coverage, then re-derives the mirrors.
     *
     * Replaces the legacy Subscriptions page's one-click accept (S1 / D8), and is
     * what makes the receivables worklist actionable.
     */
    public function acceptOrder(SubscriptionOrder $order, array $payment = []): SubscriptionOrder
    {
        return DB::transaction(function () use ($order, $payment) {
            // Lock and re-read (S3 audit): the operator's Accept, an owner checkout
            // and a payment link can all target the same order. Deciding "already
            // paid?" on the instance the caller loaded — possibly seconds stale —
            // let a second writer overwrite the first's transaction number.
            SubscriptionOrder::query()->whereKey($order->getKey())->lockForUpdate()->first();
            $order->refresh();

            if ($order->payment_status === PaymentStatus::Paid) {
                return $order;   // idempotent — the caller compares what it got back
            }

            $order->update([
                'payment_status' => PaymentStatus::Paid->value,
                'payment_method' => $payment['payment_method'] ?? $order->payment_method?->value,
                'transaction_number' => $payment['transaction_number'] ?? $order->transaction_number,
                'remarks' => $payment['remarks'] ?? $order->remarks,
            ]);

            // A free renewal is used when the renewal is PAID, never when it is quoted.
            $this->spendFreeRenewals($order);

            $account = $order->account;
            if ($account) {
                $this->mirror->sync($account);

                // Carry the ORDER's own period, not the account's stale one (S2).
                // Accepting a monthly link on a yearly account has to switch the
                // account to monthly; passing nothing would have kept the old cadence
                // and then priced the next renewal off it.
                //
                // An add-branch or align is NOT a cadence change — it tops up the
                // existing cycle — but it IS the moment a trial account becomes a
                // paying one, which is the half addBranch()/align() now defer to here
                // rather than doing on an unpaid link.
                $period = match (true) {
                    $order->kind === OrderKind::Renewal && (bool) $order->period?->isPaid() => $order->period,
                    in_array($order->kind, [OrderKind::AddBranch, OrderKind::Align, OrderKind::Purchase], true) => $this->paidPeriod($account->period?->value),
                    default => null,
                };

                // A renewal bought in advance carries its real cycle start on its
                // lines (see renewAccount), so the cycle window stays a matched pair
                // when the money arrives days or weeks after the quote. Without this
                // the start date would stick at whatever it was — finding F15 again,
                // reached down the payment-link path instead of the renewal one.
                $cycleStart = $order->kind === OrderKind::Renewal
                    ? $order->termStartDate()
                    : null;

                $this->refreshAccountAnchor(
                    $account,
                    $period,
                    $cycleStart ? Carbon::parse($cycleStart) : null,
                );
            }

            // The negotiated discount is consumed HERE, when the money is actually
            // in, rather than when the charge was quoted (design §5.2). For an
            // offline order — created already paid — creation consumed it and this
            // is a harmless no-op, because consume() only acts on an Active one.
            $this->discounts->consume($order->manual_discount_id);

            return $order->fresh();
        });
    }

    /**
     * Write an order off as a mistake. Never deletes: the ledger keeps the row and
     * the invoice series keeps its number, which is what an auditor expects.
     * Voiding a PAID order withdraws the coverage it granted (the mirror re-derives
     * without it) — deliberate, and the reason a reason is required.
     */
    public function voidOrder(SubscriptionOrder $order, string $reason): SubscriptionOrder
    {
        return DB::transaction(function () use ($order, $reason) {
            // Voiding a PAID renewal hands back the free renewals it used.
            if ($order->payment_status === PaymentStatus::Paid) {
                $this->spendFreeRenewals($order, refund: true);
            }

            $order->update([
                'payment_status' => PaymentStatus::Voided->value,
                'remarks' => trim(($order->remarks ? $order->remarks.' · ' : '')."Voided: {$reason}"),
            ]);

            $account = $order->account;
            if ($account) {
                // allowShorten: voiding is the deliberate way to WITHDRAW coverage, so
                // the mirror must be allowed to follow the ledger down. Every other
                // sync is monotonic on purpose (see CoverageMirror::sync).
                $this->mirror->sync($account, allowShorten: true);
                $this->refreshAccountAnchor($account);
            }

            return $order->fresh();
        });
    }

    /**
     * Recompute an account's anchor/status from what the LEDGER supports across its
     * BILLABLE branches.
     *
     * S1 changes two things here:
     *  · the anchor comes from CoverageMirror::supportedAnchor (paid order lines),
     *    not from whatever the branch mirrors happen to say — that circularity is
     *    finding F11, and it is how F1's doubled year became an account clock;
     *  · drift between the mirrors and the ledger is LOGGED rather than absorbed.
     *
     * Cancelled branches are excluded from the anchor (D11 case 8): a leaving branch
     * must not hold the renewal date open for everyone else. Its own coverage is
     * untouched — that projects from its own lines.
     */
    public function refreshAccountAnchor(SubscriptionAccount $account, ?BillingPeriod $period = null, ?Carbon $cycleStart = null): void
    {
        // Fetch the estate ONCE and share it: this method ran allBranches() three
        // times (here, and twice inside drift) plus supportedEnds() twice, for every
        // account on every daily tick.
        $all = $this->allBranches($account);
        $branches = $all->whereNull('cancelled_at')->values();

        foreach ($this->mirror->drift($account, $all) as $row) {
            Log::warning('Account anchor refresh: branch coverage differs from the ledger', $row + [
                'account_id' => $account->id,
            ]);
        }

        $anchor = $this->mirror->supportedAnchor($account, $branches);
        $resolvedPeriod = $period ?? $account->period ?? BillingPeriod::Yearly;

        $account->update([
            // `?? ` means the stored start is normally left alone — a long-standing
            // customer's cycle start must not drift on every daily tick. $cycleStart
            // is the deliberate exception: acceptOrder() passes the cycle a renewal
            // actually bought, which is the only event that legitimately moves it.
            'current_period_start' => $cycleStart
                ?? $account->current_period_start
                ?? $branches->min('subscription_start')
                ?? now(),
            'current_period_end' => $anchor,
            'status' => $this->computeStatus($account, $anchor, $resolvedPeriod)->value,
            'period' => $resolvedPeriod->value,
        ]);
    }

    /**
     * Effective account status given an anchor + the grace window (BR-18):
     *  - Suspended is a manual override that only Reactivate clears — never
     *    silently overwritten by a renewal/sync/daily refresh.
     *  - No anchor yet → leave whatever the account already is (e.g. a fresh Trial).
     *  - Anchor today or in the future → Trial (if the period is trial) or Active.
     *  - Anchor passed, still within the grace window → Grace.
     *  - Anchor passed beyond the grace window → Expired.
     *
     * Accepts optional overrides so callers computing a *new* anchor/period as
     * part of the same write (renewAccount, addBranch, align) can resolve "what
     * would status become under this anchor" without persisting first.
     */
    public function computeStatus(SubscriptionAccount $account, ?Carbon $anchor = null, ?BillingPeriod $period = null): AccountStatus
    {
        if ($account->status === AccountStatus::Suspended) {
            return AccountStatus::Suspended;
        }

        // `$anchor ?? $account->current_period_end` was wrong once an anchor could
        // legitimately BE null: passing null meant "this account has no coverage",
        // but the ?? read it as "argument omitted" and fell back to the stale stored
        // date — so voiding the last order wrote `current_period_end = null` and
        // `status = active` in the same statement. func_num_args() distinguishes the
        // two, which is the only thing that can.
        if (func_num_args() < 2) {
            $anchor = $account->current_period_end;
        }

        if (! $anchor) {
            // NO ANCHOR means one of two very different things, and conflating them
            // left an account with zero coverage showing a green "Active" badge
            // (found verifying S1: void the only paid order, or cancel the only
            // branch, and the anchor goes null):
            //   · it never had coverage — a fresh trial. Leave it alone.
            //   · it HAD coverage and no longer does — that is Expired. The access
            //     gate already blocks it (Hostel::isActive() needs an end date), so
            //     this is about the operator and the owner being told the truth.
            return in_array($account->status, [AccountStatus::Active, AccountStatus::Grace], true)
                ? AccountStatus::Expired
                : ($account->status ?? AccountStatus::Trial);
        }

        $period = $period ?? $account->period ?? BillingPeriod::Yearly;
        $today = Carbon::now()->startOfDay();
        $anchorDay = $anchor->copy()->startOfDay();

        if ($anchorDay->greaterThanOrEqualTo($today)) {
            return $period === BillingPeriod::Trial ? AccountStatus::Trial : AccountStatus::Active;
        }

        $graceDays = (int) config('hostelease.grace_days', 0);
        if ($today->lte($anchorDay->copy()->addDays($graceDays))) {
            return AccountStatus::Grace;
        }

        return AccountStatus::Expired;
    }

    /** Manually suspend an account and cascade to every included branch (BR-18). */
    public function suspend(SubscriptionAccount $account, string $reason): void
    {
        DB::transaction(function () use ($account, $reason) {
            $account->update([
                'status' => AccountStatus::Suspended->value,
                'notes' => trim(($account->notes ? $account->notes."\n" : '')."Suspended: {$reason}"),
            ]);

            $this->includedBranches($account)->each(fn (Hostel $b) => $b->update(['status' => 'suspended']));
        });
    }

    /** Lift a manual suspension and recompute the account's real lifecycle status from its anchor. */
    public function reactivate(SubscriptionAccount $account): void
    {
        DB::transaction(function () use ($account) {
            $this->includedBranches($account)->each(fn (Hostel $b) => $b->update(['status' => 'active']));

            // Clear the in-memory Suspended flag so computeStatus() (called inside
            // refreshAccountAnchor) derives the real status from the anchor instead
            // of re-preserving Suspended.
            $account->status = AccountStatus::Active;
            $this->refreshAccountAnchor($account);
        });
    }

    // -----------------------------------------------------------------
    // Consolidated ops (driven by the Super Admin terminal, Phase 4)
    // -----------------------------------------------------------------

    /**
     * Quote a full-account renewal (all included branches to one new anchor).
     *
     * @return array{period:BillingPeriod, quantity:int, unit:float, subtotal:float, breakdown:array, new_anchor:Carbon, branch_ids:array}
     */
    public function quoteRenewal(SubscriptionAccount $account, string $period): array
    {
        $bp = BillingPeriod::tryFrom($period) ?? BillingPeriod::Yearly;
        $branches = $this->includedBranches($account);
        $quantity = $branches->count();
        $unit = $this->unitPrice($account, $bp);
        $subtotal = round($quantity * $unit, 2);

        $base = $account->isEntitled() && $account->current_period_end && $account->current_period_end->isFuture()
            ? $account->current_period_end->copy()
            : Carbon::now();
        $newAnchor = $bp->extend($base);

        // FREE RENEWALS (doc 22): a branch holding one renews for ₹0. Its share of
        // the term is taken off before the engine prices the rest; the volume tier
        // still counts every branch on the plan.
        $complimentary = $branches->filter(fn (Hostel $b) => (int) $b->free_renewals > 0)
            ->map(fn (Hostel $b) => ['branch' => $b, 'amount' => $unit])->values()->all();
        $complimentaryTotal = round(count($complimentary) * $unit, 2);

        $breakdown = $this->discounts->preview($account, max(0, $subtotal - $complimentaryTotal), $quantity, 'renewal');
        $topups = $this->renewalTopUps($account, $branches);
        $topupTotal = round(array_sum(array_column($topups, 'amount')), 2);

        return [
            'period' => $bp,
            'quantity' => $quantity,
            'unit' => $unit,
            // The TERM: N branches × unit; free renewals off it; then its discounts
            // (breakdown is the engine's view of what is left to pay).
            'subtotal' => $subtotal,
            'complimentary' => $complimentary,
            'complimentary_total' => $complimentaryTotal,
            'breakdown' => $breakdown,
            // Branches BEHIND the current renewal date, brought up to it first.
            'topups' => $topups,
            'topup_total' => $topupTotal,
            // What the customer pays. Use THIS as the price — not breakdown.final,
            // which is the term alone.
            'total' => round($breakdown['final'] + $topupTotal, 2),
            'current_anchor' => $topups ? $account->current_period_end->copy() : null,
            'new_anchor' => $newAnchor,
            'branch_ids' => $branches->pluck('id')->all(),
        ];
    }

    /**
     * What a renewal must also charge for branches BEHIND the current renewal date.
     *
     * A renewal extends every branch from the current anchor. Coverage is the latest
     * paid end date — not a continuous span — so a branch whose coverage stops before
     * that anchor (typically one added mid-term and not yet paid for) would be
     * switched on today and ride FREE until the anchor. Found 2026-10-05: a branch
     * added 182 days before the anchor got ₹4,986 of coverage for nothing.
     *
     * So, on a live PAID cycle, each such branch also pays its stretch up to the
     * anchor — priced exactly as Align prices it (prorate(), at the account's current
     * cadence, no discounts: the renewal's discounts apply to the term and are never
     * applied twice). A TRIAL cycle charges none: branches added during a running
     * trial share it (owner decision, 2026-10-05). An expired or suspended account has
     * no live anchor to catch up to — its renewal starts today for every branch.
     *
     * @return list<array{branch: Hostel, from: Carbon, days: int, amount: float}>
     */
    protected function renewalTopUps(SubscriptionAccount $account, Collection $branches): array
    {
        $anchor = $account->current_period_end;

        if (! $account->period?->isPaid() || ! $account->isEntitled() || ! $anchor || ! $anchor->isFuture()) {
            return [];
        }

        $cadence = $this->paidPeriod($account->period->value);
        $unit = $this->unitPrice($account, $cadence);
        $topups = [];

        foreach ($branches as $branch) {
            if ($branch->subscription_end && ! $branch->subscription_end->lt($anchor)) {
                continue;   // already covered to the anchor
            }

            $from = $this->prorationStart($branch);
            $line = $this->prorate($anchor, $from, $unit, $cadence);

            if ($line['amount'] > 0) {
                $topups[] = ['branch' => $branch, 'from' => $from->copy()->startOfDay(), 'days' => $line['days'], 'amount' => $line['amount']];
            }
        }

        return $topups;
    }

    /**
     * Consolidated renewal: every included branch is extended to one new anchor,
     * recorded as a single order with N lines.
     */
    public function renewAccount(SubscriptionAccount $account, string $period, array $payment = []): SubscriptionOrder
    {
        $branches = $this->includedBranches($account);

        // NOTHING TO RENEW (D11 case 9). Every branch has been cancelled, so this
        // account is closing. Without this guard the quote would be ₹0 × 0 branches
        // and we would write an empty order and push the anchor a year forward —
        // making a closed account look renewed, forever.
        if ($branches->isEmpty()) {
            throw new RuntimeException('This account has no billable branches — every branch has been cancelled or removed. Restore a branch, or add one, before renewing.');
        }

        return DB::transaction(function () use ($account, $period, $payment, $branches) {
            $quote = $this->quoteRenewal($account, $period);
            $anchor = $quote['new_anchor'];

            // The charge = the term (with its discounts) + any top-ups for branches
            // behind the current renewal date. Subtotal and the "auto" total both carry
            // the top-ups, so subtotal − discount = amount still holds and an operator
            // override is still measured against the whole bill.
            $subtotal = round($quote['subtotal'] + $quote['topup_total'], 2);
            [$amount, $discountTotal] = $this->resolveCharge(
                $subtotal,
                // Free renewals are recorded as discount: the invoice reads list − gifts − discounts = paid.
                ['final' => $quote['total'], 'discount_total' => round($quote['complimentary_total'] + $quote['breakdown']['discount_total'], 2)],
                $payment,
            );

            // The cycle START advances with the cycle END (S0 · finding F15). It
            // used to be written `?? now()`, i.e. once and never again, so after
            // three yearly renewals an account read start 2026 / end 2029 — a
            // "current period" three years long, useless for reporting. The new
            // start is the same base quoteRenewal() extended to get the new anchor,
            // so start and end stay a matched pair describing one real term.
            //
            // Computed BEFORE the order is written (S2): the lines now carry it, so
            // a renewal that is paid later can read its own cycle start back out of
            // the ledger instead of needing a column to remember it.
            $previousAnchor = $account->current_period_end;
            $cycleStart = ($account->isEntitled() && $previousAnchor && $previousAnchor->isFuture())
                ? $previousAnchor->copy()
                : Carbon::now();

            $order = $this->makeOrder(
                $account,
                $quote['period'],
                $quote['quantity'],
                $subtotal,
                $discountTotal,
                $amount,
                $payment + ['manual_discount_id' => $quote['breakdown']['manual_discount_id'] ?? null],
                OrderKind::Renewal,
            );

            // Lines sum EXACTLY to the amount (finding F12). Term shares are an equal
            // split of the term; each top-up keeps its own prorated figure. If the
            // operator lowered the total, every line is scaled down proportionally so
            // the lines still add up to what was charged.
            // A branch on a free renewal gets a ₹0 line marked complimentary; the paying
            // branches share the term.
            $freeIds = array_map(fn (array $c) => $c['branch']->id, $quote['complimentary']);
            $paying = $branches->reject(fn (Hostel $b) => in_array($b->id, $freeIds, true))->values();
            $paidShares = array_combine($paying->pluck('id')->all(), $this->allocate($quote['breakdown']['final'], $paying->count())) ?: [];
            $termShares = $branches->values()->map(fn (Hostel $b) => $paidShares[$b->id] ?? 0.0)->all();

            $weights = array_merge($termShares, array_column($quote['topups'], 'amount'));
            $shares = abs(array_sum($weights) - $amount) < 0.005 ? $weights : $this->allocateProportionally($amount, $weights);

            foreach ($branches->values() as $i => $branch) {
                // start_date = the real cycle start, not "now". An advance renewal's
                // line should read 01 Oct 2027 → 01 Oct 2028 on the invoice, not
                // 03 Oct 2026 → 01 Oct 2028, and acceptOrder() reads it back to
                // advance current_period_start.
                $this->addLine($order, $branch, $shares[$i], $anchor, $cycleStart, in_array($branch->id, $freeIds, true));
            }

            // A TOP-UP line per behind branch: from where its coverage stops up to the
            // CURRENT anchor — so the invoice shows what the extra money bought, and a
            // top-up paid some other way first (Add to plan) makes this order stale
            // rather than payable twice (SubscriptionOrder::coverageState()).
            foreach ($quote['topups'] as $j => $topup) {
                $this->addLine($order, $topup['branch'], $shares[$branches->count() + $j], $quote['current_anchor'], $topup['from']);
            }
            $this->mirror->sync($account);

            // ── THE ACCOUNT CLOCK MOVES ONLY FOR MONEY (S2) ──
            // This block used to run unconditionally, which was safe only because
            // every operator path created the order already PAID. S2 creates PENDING
            // renewals (a payment link is a pending order with a URL attached), and
            // on one of those this would have pushed the renewal date a year forward
            // and painted the badge green with nothing received — so the renewals
            // worklist, the dunning windows and the owner's "Renews on" line would
            // all have stopped chasing money we are still owed.
            //
            // A pending renewal therefore leaves the account exactly as it was.
            // acceptOrder() re-derives the anchor from the ledger when the money
            // lands, which is the same number by a safer route.
            if ($order->payment_status->grantsCoverage()) {
                $account->update([
                    'period' => $quote['period']->value,
                    'current_period_start' => $cycleStart,
                    'current_period_end' => $anchor,
                    'status' => $this->computeStatus($account, $anchor, $quote['period'])->value,
                ]);

                $this->discounts->consume($quote['breakdown']['manual_discount_id']);
                $this->spendFreeRenewals($order);
            }

            return $order;
        });
    }

    /**
     * Quote adding a branch mid-cycle: prorate to the current anchor (BR-10).
     *
     * The charge covers only the stretch the branch does not already hold —
     * from its own coverage end (it keeps whatever time it has already paid
     * for, or is on) up to the anchor — never from *today*, which would re-bill
     * time the branch is already covered for. A brand-new branch (none passed,
     * or one with no future coverage) prorates from now. This mirrors align()'s
     * per-branch top-up so the two ops price an identical branch identically.
     *
     * @return array{prorated:float, days_remaining:int, anchor:?Carbon, unit:float, breakdown:array}
     */
    public function quoteAddBranch(SubscriptionAccount $account, ?Hostel $branch = null, ?string $period = null): array
    {
        $bp = $this->paidPeriod($period ?? $account->period?->value);
        $anchor = $account->current_period_end;
        $unit = $this->unitPrice($account, $bp);

        $from = $this->prorationStart($branch);
        $line = $this->prorate($anchor, $from, $unit, $bp);

        return [
            'prorated' => $line['amount'],
            'days_remaining' => $line['days'],
            'cycle_days' => $line['cycle_days'],
            'anchor' => $anchor,
            'unit' => $unit,
            'breakdown' => $this->discounts->preview($account, $line['amount'], 1, 'add_branch'),
        ];
    }

    /**
     * Where a branch's prorated top-up starts: its own coverage end if it still
     * holds future coverage (never re-bill time it already has), else today.
     */
    protected function prorationStart(?Hostel $branch): Carbon
    {
        return $branch && $branch->subscription_end && $branch->subscription_end->isFuture()
            ? $branch->subscription_end->copy()
            : Carbon::now();
    }

    /**
     * THE proration helper — the single place a part-cycle charge is computed
     * (S0 · finding F2, decision D5). Cost of covering ONE branch from $from up
     * to $anchor, priced at the daily rate of the cycle that ENDS on that anchor.
     *
     * Three things this gets right that the old inline math did not:
     *
     *  1. The denominator is the REAL length of the cycle being prorated into
     *     (BillingPeriod::cycleDays), not a hard-coded 365 / 30. Numerator and
     *     denominator are therefore days of the SAME calendar stretch, which is
     *     what makes "half a cycle costs half a unit" true in February, in a leap
     *     year, everywhere.
     *  2. It is clamped at one unit price. A top-up TO the anchor can never cost
     *     more than a full term — if it would, the account needs a renewal, not a
     *     top-up. (Before the clamp, a branch added against a two-year-out anchor
     *     was quoted ₹20,014 for one branch.)
     *  3. Both ends are pinned to midnight, so the same action costs the same
     *     whether it is done at 9am or 11pm, and the branch is covered for the
     *     day it is added.
     *
     * @return array{days:int, cycle_days:int, amount:float}
     */
    protected function prorate(?Carbon $anchor, Carbon $from, float $unit, BillingPeriod $period): array
    {
        if (! $anchor) {
            return ['days' => 0, 'cycle_days' => $period->days(), 'amount' => 0.0];
        }

        $anchorDay = $anchor->copy()->startOfDay();
        $fromDay = $from->copy()->startOfDay();

        $cycleDays = $period->cycleDays($anchorDay);
        $days = $anchorDay->greaterThan($fromDay) ? (int) $fromDay->diffInDays($anchorDay) : 0;

        return [
            'days' => $days,
            'cycle_days' => $cycleDays,
            'amount' => $days > 0 ? min($unit, round($unit * $days / $cycleDays, 2)) : 0.0,
        ];
    }

    /**
     * Resolve a *paid* billing period for a proration. A co-termination top-up
     * is always priced at a paid rate — never 'trial' (which prices at ₹0). An
     * account's period can transiently read 'trial' (e.g. right after a trial
     * branch is provisioned); pricing an add/align off that would hand out free
     * coverage. Falls back to Yearly when there's no usable paid cadence.
     */
    protected function paidPeriod(?string $period): BillingPeriod
    {
        $bp = BillingPeriod::tryFrom((string) $period);

        return $bp && $bp->isPaid() ? $bp : BillingPeriod::Yearly;
    }

    /**
     * Add a branch to a live account: charge a prorated amount and co-terminate
     * the branch on the existing anchor. If the account has no live anchor, this
     * falls back to a plain single-branch renewal.
     */
    public function addBranch(SubscriptionAccount $account, Hostel $branch, array $payment = []): SubscriptionOrder
    {
        return DB::transaction(function () use ($account, $branch, $payment) {
            $anchor = $account->current_period_end;
            if (! $anchor || ! $anchor->isFuture()) {
                // No live cycle to co-terminate with — treat as a normal renewal
                // at a paid rate (never trial, which would add the branch free).
                // recordBranchRenewal returns the order directly since S1.
                $period = $this->paidPeriod($account->period?->value)->value;

                // This branch of addBranch() does NOT go through resolveCharge(), so
                // the posted-override guard has to be applied here or this is the one
                // operator path where a client could still charge above the quote.
                // recordBranchRenewal() itself must stay unguarded: the webhook and
                // the checkout callback record what Razorpay actually captured, which
                // may legitimately differ from any quote and must never be clamped.
                if (($payment['amount'] ?? null) !== null && ! ($payment['amount_authoritative'] ?? false)) {
                    $this->assertOverrideReducesOnly(
                        (float) $this->branchBilling->quote($branch, $period)['amount'],
                        round((float) $payment['amount'], 2),
                    );
                }

                return $this->recordBranchRenewal(
                    $branch,
                    $period,
                    $payment + ['kind' => OrderKind::AddBranch],
                );
            }

            $quote = $this->quoteAddBranch($account, $branch);
            [$amount, $discountTotal] = $this->resolveCharge($quote['prorated'], $quote['breakdown'], $payment);

            $order = $this->makeOrder(
                $account,
                $account->period ?? BillingPeriod::Yearly,
                1,
                $quote['prorated'],
                $discountTotal,
                $amount,
                $payment + ['manual_discount_id' => $quote['breakdown']['manual_discount_id'] ?? null],
                OrderKind::AddBranch,
            );
            $this->addLine($order, $branch, $amount, $anchor);
            $this->mirror->sync($account);

            // A real (paid) top-up just happened — resolve the account off a paid
            // cadence, not whatever it was left on (e.g. 'trial' from the branch's
            // own provisioning). Otherwise the account keeps reading Trial even
            // though the branch is now fully paid through the anchor (BR-18-adjacent).
            //
            // ONLY WHEN IT IS PAID. refreshAccountAnchor() derives the anchor from
            // PAID lines, so an unpaid top-up cannot move that — but it writes
            // `period` and `status` from whatever period it is handed, and those are
            // NOT ledger-derived. Handing it a paid cadence for a pending link
            // rewrote a TRIAL account to period=yearly / status=active with no money
            // received: it then read as a paying customer in the Customers list, in
            // the renewals worklist and in S5's trial-conversion figures, and its
            // trial-expiry messaging stopped. Passing null leaves the account's own
            // period alone; acceptOrder() resolves the paid cadence when the money
            // actually lands. (Same family as the renewAccount fix — design §5.1.)
            $this->refreshAccountAnchor(
                $account,
                $order->payment_status->grantsCoverage() ? $this->paidPeriod($account->period?->value) : null,
            );

            // Consume the one-shot discount only once the money is in (S2 · design
            // §5.2). On a pending payment link this used to mark a single-use
            // negotiated discount Consumed before anyone had paid, so a link that
            // expired took the discount with it and the re-issue was full price.
            if ($order->payment_status->grantsCoverage()) {
                $this->discounts->consume($quote['breakdown']['manual_discount_id']);
            }

            return $order;
        });
    }

    /**
     * Quote an Align: the prorated top-up for every branch that ends before the
     * anchor, one line each. Align is deliberately priced at the plain prorated
     * rate — it applies no volume/negotiated discount (only an operator amount
     * override can reduce it, recorded as a manual adjustment). Mirrors the
     * per-branch shape the Account 360 modal renders before charging.
     *
     * @return array{anchor:?Carbon, count:int, subtotal:float, lines:array<int, array{branch:Hostel, days:int, amount:float}>}
     */
    public function quoteAlign(SubscriptionAccount $account): array
    {
        $anchor = $account->current_period_end;
        $lines = [];
        $subtotal = 0.0;

        if ($anchor) {
            $bp = $this->paidPeriod($account->period?->value);
            $unit = $this->unitPrice($account, $bp);

            foreach ($this->branchesBehind($account, $anchor) as $branch) {
                // Same helper as quoteAddBranch, so Align and Add-to-cycle price an
                // identical branch identically (S0 · F2).
                $line = $this->prorate($anchor, $this->prorationStart($branch), $unit, $bp);
                $subtotal += $line['amount'];
                $lines[] = ['branch' => $branch, 'days' => $line['days'], 'amount' => $line['amount']];
            }
        }

        return [
            'anchor' => $anchor,
            'count' => count($lines),
            'subtotal' => round($subtotal, 2),
            'lines' => $lines,
        ];
    }

    /**
     * Align staggered branches: extend every branch that ends before the anchor
     * up to the anchor, charging a prorated top-up per branch (BRD D4). An
     * optional amount override is recorded as a manual discount and spread
     * proportionally across the branch lines.
     */
    public function align(SubscriptionAccount $account, array $payment = []): ?SubscriptionOrder
    {
        $anchor = $account->current_period_end;
        if (! $anchor) {
            return null;
        }

        return DB::transaction(function () use ($account, $anchor, $payment) {
            $bp = $this->paidPeriod($account->period?->value);
            $quote = $this->quoteAlign($account);
            if ($quote['count'] === 0) {
                return null;
            }

            $subtotal = $quote['subtotal'];
            $override = $payment['amount'] ?? null;
            $amount = $override !== null ? round((float) $override, 2) : $subtotal;

            // Align prices itself rather than going through resolveCharge(), so it
            // needs the same guard explicitly: an override may only reduce. Without
            // it, an over-quote figure would also make $scale > 1 and inflate every
            // per-branch line past the total they are supposed to add up to.
            if ($override !== null && ! ($payment['amount_authoritative'] ?? false)) {
                $this->assertOverrideReducesOnly($subtotal, $amount);
            }

            $discountTotal = $override !== null ? max(0, round($subtotal - $amount, 2)) : 0.0;
            $scale = ($override !== null && $subtotal > 0) ? $amount / $subtotal : 1.0;

            $order = $this->makeOrder($account, $bp, $quote['count'], $subtotal, $discountTotal, $amount, $payment, OrderKind::Align);

            foreach ($quote['lines'] as $line) {
                $this->addLine($order, $line['branch'], round($line['amount'] * $scale, 2), $anchor);
            }
            $this->mirror->sync($account);

            // Same reasoning as addBranch(), including the pending guard: resolve off
            // the paid cadence ($bp) only once the money is in, because `period` and
            // `status` are NOT ledger-derived and an unpaid link must not promote a
            // trial to a paying account. acceptOrder() resolves it on payment.
            $this->refreshAccountAnchor(
                $account,
                $order->payment_status->grantsCoverage() ? $bp : null,
            );

            return $order;
        });
    }

    /** Branches whose coverage ends before (or is missing relative to) the anchor. */
    protected function branchesBehind(SubscriptionAccount $account, Carbon $anchor): Collection
    {
        return $this->includedBranches($account)
            ->filter(fn (Hostel $b) => ! $b->subscription_end || $b->subscription_end->lt($anchor))
            ->values();
    }

    /**
     * Resolve the final charged amount + recorded discount for a charge.
     * With no operator override the engine's discounted total stands. With an
     * override, the difference from the pre-discount subtotal is recorded as the
     * total discount (engine + operator), so subtotal − discount_total == amount
     * always holds and the order row reads "list − discount = paid" (BR: 5.1).
     *
     * @param  array{final:float, discount_total:float}  $breakdown
     * @return array{0:float, 1:float} [amount, discountTotal]
     */
    protected function resolveCharge(float $subtotal, array $breakdown, array $payment): array
    {
        $override = $payment['amount'] ?? null;

        if ($override === null) {
            return [$breakdown['final'], $breakdown['discount_total']];
        }

        $amount = round((float) $override, 2);

        // AN OPERATOR OVERRIDE MAY ONLY REDUCE A CHARGE. It is posted by the browser,
        // so it is the one money value in the system that comes from outside the
        // engine — and it was unbounded above. Posting 25,000 against a 20,000 quote
        // wrote `subtotal 20000 · discount 0 · amount 25000`: the row's own invariant
        // (subtotal − discount == amount) broken, the invoice PDF nonsense, and in
        // S2 the payment LINK asked the customer for the posted figure rather than
        // the quoted one. Refusing is the only safe reading: an override's whole
        // meaning here is "charge less, and log the difference as a discount".
        if (! ($payment['amount_authoritative'] ?? false)) {
            $this->assertOverrideReducesOnly($subtotal, $amount);
        }

        return [$amount, max(0, round($subtotal - $amount, 2))];
    }

    /**
     * Guard the one money value that arrives from the client.
     *
     * Deliberately in the SERVICE, not just in request validation: every operator
     * charge path funnels through here, so no future controller, console command or
     * job can reintroduce an over-quote charge.
     *
     * ⚠️ GATEWAY AMOUNTS ARE EXEMPT, via `amount_authoritative` in the payment array.
     * The webhook and the checkout callback record what Razorpay *actually captured*,
     * fetched server-side from Razorpay itself — and that legitimately exceeds the
     * current quote whenever the quote has moved since checkout opened (proration
     * shrinks by the day). Clamping it would be wrong twice over: it would understate
     * money we have genuinely received, and because it throws, it would 500 the
     * WEBHOOK — which makes Razorpay retry the delivery forever and still never
     * succeed. The suite caught exactly that when this guard first went in.
     *
     * @throws RuntimeException
     */
    protected function assertOverrideReducesOnly(float $subtotal, float $amount): void
    {
        if (round($amount, 2) <= round($subtotal, 2)) {
            return;
        }

        throw new RuntimeException(sprintf(
            'The amount override (%s) is more than this charge comes to (%s). An override can only '
                .'bring a charge DOWN — the difference is recorded as a discount. To bill more, change '
                .'the branch count or set a custom unit price for this customer.',
            hostelease_money($amount),
            hostelease_money($subtotal),
        ));
    }

    /**
     * Complimentary (₹0) grant of coverage (BR-17). Extends each selected branch
     * by `multiplier × term` from its own current coverage end (or today if it
     * has lapsed), never shortening. Recorded as one ₹0 order (method=comp) with
     * a line per branch. Unselected branches are untouched, so a comp can be
     * scoped to just some branches and staggered branches stay staggered.
     *
     * @param  int[]  $branchIds
     */
    // -----------------------------------------------------------------
    // Gifts — one renewal date per account (doc 22)
    // -----------------------------------------------------------------
    //
    // A gift never adds dates to one branch: the account's renewal date is the
    // furthest coverage of any branch, so that moved the date for everyone and left
    // the others "behind". Instead a gift is one of:
    //
    //   · free renewals      — these branches' next N renewals cost ₹0 (the default);
    //   · extend renewal date — EVERY branch moves together (on a trial: extend trial);
    //   · bring up to date free — a behind branch up to the date, ₹0 (Add to cycle).

    /**
     * Set how many upcoming renewals of these branches are free. SETS, not adds — the
     * same form corrects a mistake (0 removes them). Only branches still on the plan.
     *
     * @param  list<int>  $branchIds
     * @return int how many branches changed
     */
    public function setFreeRenewals(SubscriptionAccount $account, array $branchIds, int $count): int
    {
        $count = max(0, min(10, $count));
        $ids = $this->includedBranches($account)->pluck('id')->intersect($branchIds)->values()->all();

        return Hostel::whereIn('id', $ids)->where('free_renewals', '!=', $count)->update(['free_renewals' => $count]);
    }

    /**
     * Move the renewal date for the WHOLE account — every branch covered to it moves
     * with it, so nothing is left out of line. On a running trial this extends the
     * trial. A branch already behind stays behind: its gap is still owed.
     */
    public function extendRenewalDate(SubscriptionAccount $account, int $amount, string $unit, string $reason): SubscriptionOrder
    {
        $anchor = $account->current_period_end;
        if (! $anchor || ! $anchor->copy()->endOfDay()->isFuture()) {
            throw new RuntimeException('This account has no live renewal date to extend — renew it first.');
        }
        if ($amount < 1 || ($unit === 'months' ? $amount > 12 : $amount > 366)) {
            throw new RuntimeException('Extend by 1–366 days or 1–12 months.');
        }

        return DB::transaction(function () use ($account, $anchor, $amount, $unit, $reason) {
            $newAnchor = $unit === 'months' ? $anchor->copy()->addMonths($amount) : $anchor->copy()->addDays($amount);
            $branches = $this->includedBranches($account)
                ->filter(fn (Hostel $b) => $b->subscription_end && ! $b->subscription_end->lt($anchor))
                ->values();

            if ($branches->isEmpty()) {
                throw new RuntimeException('No branch is covered up to the renewal date, so there is nothing to extend.');
            }

            $label = $amount.' '.($unit === 'months' ? \Illuminate\Support\Str::plural('month', $amount) : \Illuminate\Support\Str::plural('day', $amount));
            $order = $this->makeOrder($account, $account->period ?? BillingPeriod::Yearly, $branches->count(), 0, 0, 0, [
                'payment_status' => PaymentStatus::Paid->value,
                'payment_method' => PaymentMethod::Comp->value,
                'remarks' => "Renewal date extended by {$label} — {$reason}",
            ], OrderKind::Comp);

            foreach ($branches as $branch) {
                $this->addLine($order, $branch, 0, $newAnchor, $anchor->copy());
            }

            $this->mirror->sync($account);
            $this->refreshAccountAnchor($account);

            return $order;
        });
    }

    /**
     * Use (or, on void, hand back) the free renewals a paid renewal consumed.
     * Idempotent per state change: called once when the order becomes paid, once when
     * a paid order is voided.
     */
    protected function spendFreeRenewals(SubscriptionOrder $order, bool $refund = false): void
    {
        if ($order->kind !== OrderKind::Renewal) {
            return;
        }

        $ids = $order->lines()->where('complimentary', true)->pluck('branch_id')->all();
        if ($ids === []) {
            return;
        }

        $refund
            ? Hostel::whereIn('id', $ids)->where('free_renewals', '<', 255)->increment('free_renewals')
            : Hostel::whereIn('id', $ids)->where('free_renewals', '>', 0)->decrement('free_renewals');
    }

    /**
     * Bring an account onto ONE renewal date: coverage a branch holds past it from a
     * gift (a comp, a hand edit, an old back-fill) becomes free renewals instead.
     *
     * The renewal date here is the furthest end of the lines the customer PAID for (or
     * their trial) — gifts are what is being moved, so they cannot define it. Each
     * branch past it is brought back to it and credited round(excess ÷ term) free
     * renewals; the ₹0 gift lines that carried the excess are shortened to the date.
     * An account with no paid or trial coverage at all is left exactly as it is.
     *
     * @return list<array{branch: Hostel, from: Carbon, to: Carbon, days: int, free_renewals: int}>
     */
    public function foldGiftsIntoFreeRenewals(SubscriptionAccount $account, bool $dryRun = false): array
    {
        return DB::transaction(function () use ($account, $dryRun) {
            $branches = $this->includedBranches($account);
            if ($branches->isEmpty()) {
                return [];
            }

            $paidKinds = [OrderKind::Purchase->value, OrderKind::Renewal->value, OrderKind::AddBranch->value, OrderKind::Align->value, OrderKind::Trial->value];
            $date = SubscriptionOrderLine::query()
                ->join('subscription_orders', 'subscription_orders.id', '=', 'subscription_order_lines.order_id')
                ->where('subscription_orders.account_id', $account->id)
                ->where('subscription_orders.payment_status', PaymentStatus::Paid->value)
                ->whereNull('subscription_orders.deleted_at')
                ->whereIn('subscription_orders.kind', $paidKinds)
                ->whereIn('subscription_order_lines.branch_id', $branches->pluck('id'))
                ->max('subscription_order_lines.end_date');

            if (! $date) {
                return [];
            }

            $date = Carbon::parse($date)->startOfDay();
            $termDays = $account->period === BillingPeriod::Monthly ? 30 : 365;
            $folded = [];

            foreach ($branches as $branch) {
                if (! $branch->subscription_end || ! $branch->subscription_end->copy()->startOfDay()->greaterThan($date)) {
                    continue;
                }

                $days = (int) $date->diffInDays($branch->subscription_end->copy()->startOfDay());
                $credits = (int) round($days / $termDays);
                $row = ['branch' => $branch, 'from' => $branch->subscription_end->copy(), 'to' => $date->copy(), 'days' => $days, 'free_renewals' => $credits];

                if ($dryRun) {
                    $folded[] = $row;

                    continue;
                }

                // Shorten the ₹0 gift lines that reach past the date. A paid line never
                // does — the date is the furthest paid end.
                $giftLines = SubscriptionOrderLine::query()
                    ->where('branch_id', $branch->id)
                    ->whereDate('end_date', '>', $date)
                    ->whereHas('order', fn ($q) => $q->where('account_id', $account->id)->whereNotIn('kind', $paidKinds))
                    ->get();
                foreach ($giftLines as $line) {
                    $line->update([
                        'end_date' => $date,
                        'start_date' => $line->start_date && $line->start_date->greaterThan($date) ? $date : $line->start_date,
                    ]);
                }

                if ($credits > 0) {
                    $branch->forceFill(['free_renewals' => min(255, (int) $branch->free_renewals + $credits)])->save();
                }

                $folded[] = $row;
            }

            if ($folded !== [] && ! $dryRun) {
                // Deliberately shortening: the gift is now held as free renewals.
                $this->mirror->sync($account, allowShorten: true);
                $this->refreshAccountAnchor($account);
            }

            return $folded;
        });
    }

    public function setUnitPriceOverride(SubscriptionAccount $account, ?float $yearly, ?float $monthly): void
    {
        $account->update([
            'unit_price_override_yearly' => $yearly,
            'unit_price_override_monthly' => $monthly,
        ]);
    }

    // -----------------------------------------------------------------
    // Branch removal (D11) — the owner asks, the operator decides
    // -----------------------------------------------------------------

    /**
     * The owner asks for a branch to be removed. Nothing billing-related changes:
     * the branch is still counted, still charged at the next renewal, still working.
     * It only becomes visible to the operator as a request to act on.
     *
     * Idempotent — asking twice does not reopen or duplicate anything (D11 case 18).
     */
    public function requestRemoval(Hostel $branch, string $reason): bool
    {
        if ($branch->cancelled_at || $branch->cancellation_requested_at) {
            return false;   // already cancelled, or already asked
        }

        $branch->forceFill([
            'cancellation_requested_at' => Carbon::now(),
            'cancellation_requested_reason' => $reason,
        ])->save();

        return true;
    }

    /** The owner changes their mind, or the operator declines. Leaves no billing trace. */
    public function clearRemovalRequest(Hostel $branch): bool
    {
        if (! $branch->cancellation_requested_at) {
            return false;
        }

        $branch->forceFill([
            'cancellation_requested_at' => null,
            'cancellation_requested_reason' => null,
        ])->save();

        return true;
    }

    /**
     * The operator confirms removal (BR-12).
     *
     * Coverage is deliberately NOT touched: the branch keeps the service it has paid
     * for and lapses on its own date (rule R2). All this does is take it out of the
     * billable set, so the next renewal quote drops and no new line is ever created
     * for it. No refund, no credit — BRD D6.
     *
     * The anchor is re-derived afterwards because a leaving branch must not hold the
     * renewal date open for the rest of the account (D11 case 8).
     */
    public function cancelBranch(Hostel $branch, string $reason): bool
    {
        if ($branch->cancelled_at) {
            return false;   // idempotent (D11 case 15)
        }

        DB::transaction(function () use ($branch, $reason) {
            $branch->forceFill([
                'cancelled_at' => Carbon::now(),
                'cancellation_reason' => $reason,
                'cancellation_requested_at' => null,
                'cancellation_requested_reason' => null,
            ])->save();

            if ($account = $this->accountForBranch($branch)) {
                $this->refreshAccountAnchor($account);
            }
        });

        return true;
    }

    /**
     * Put a cancelled branch back into the billable set.
     *
     * Grants nothing by itself: if its coverage has already lapsed the branch stays
     * unentitled and needs a prorated top-up (Add to cycle) — restoring is not a
     * back door to free coverage (D11 case 7). The caller tells the operator which
     * of the two situations they are in; `coverageLapsed` in the return says so.
     *
     * @return array{restored: bool, coverageLapsed: bool}
     */
    public function restoreBranch(Hostel $branch): array
    {
        $lapsed = ! $branch->subscription_end || $branch->subscription_end->copy()->endOfDay()->isPast();

        if (! $branch->cancelled_at) {
            return ['restored' => false, 'coverageLapsed' => $lapsed];
        }

        DB::transaction(function () use ($branch) {
            $branch->forceFill(['cancelled_at' => null, 'cancellation_reason' => null])->save();

            if ($account = $this->accountForBranch($branch)) {
                $this->refreshAccountAnchor($account);
            }
        });

        return ['restored' => true, 'coverageLapsed' => $lapsed];
    }

    /**
     * What removing this branch would do to the bill — shown BEFORE confirming.
     *
     * The part worth surfacing is the volume tier (D11 case 11): dropping 5 branches
     * to 4 can lose a quantity tier and so RAISE the per-branch price for the
     * branches that remain. A customer who discovers that on the next invoice churns
     * the rest of the account.
     *
     * @return array{
     *   quantity_now:int, quantity_after:int, total_now:float, total_after:float,
     *   per_branch_now:float, per_branch_after:float, tier_lost:bool, closes_account:bool
     * }
     */
    public function removalImpact(SubscriptionAccount $account, Hostel $branch): array
    {
        $bp = $this->paidPeriod($account->period?->value);
        $unit = $this->unitPrice($account, $bp);

        $now = $this->includedBranches($account)->count();
        $after = max(0, $now - ($branch->cancelled_at ? 0 : 1));

        $quote = fn (int $qty) => $qty > 0
            ? (float) $this->discounts->preview($account, round($qty * $unit, 2), $qty, 'renewal')['final']
            : 0.0;

        $totalNow = $quote($now);
        $totalAfter = $quote($after);
        $perNow = $now > 0 ? round($totalNow / $now, 2) : 0.0;
        $perAfter = $after > 0 ? round($totalAfter / $after, 2) : 0.0;

        return [
            'quantity_now' => $now,
            'quantity_after' => $after,
            'total_now' => $totalNow,
            'total_after' => $totalAfter,
            'per_branch_now' => $perNow,
            'per_branch_after' => $perAfter,
            // A tier was lost when each remaining branch costs MORE than it did.
            'tier_lost' => $after > 0 && $perAfter > $perNow,
            'closes_account' => $after === 0,
        ];
    }

    /** Pending (unpaid) chargeable orders that touch this branch — D11 case 19. */
    public function pendingOrdersForBranch(Hostel $branch): Collection
    {
        return SubscriptionOrder::query()
            ->outstanding()
            ->whereHas('lines', fn ($q) => $q->where('branch_id', $branch->id))
            ->get();
    }

    /**
     * The same question for a whole page, in ONE grouped query.
     *
     * Account 360 asked it per branch, which cost a query each — the kind of per-row
     * lookup NFR-4 exists to prevent.
     *
     * @param  iterable<Hostel>  $branches
     * @return array<int, int> branch id => count of outstanding orders touching it
     */
    public function pendingOrderCountsByBranch(iterable $branches): array
    {
        $ids = collect($branches)->pluck('id')->all();
        if (! $ids) {
            return [];
        }

        return SubscriptionOrderLine::query()
            ->whereIn('branch_id', $ids)
            ->whereIn('order_id', SubscriptionOrder::outstanding()->select('id'))
            ->selectRaw('branch_id, COUNT(DISTINCT order_id) as total')
            ->groupBy('branch_id')
            ->pluck('total', 'branch_id')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    // -----------------------------------------------------------------
    // Internals
    // -----------------------------------------------------------------

    protected function makeOrder(SubscriptionAccount $account, BillingPeriod $period, int $quantity, float $subtotal, float $discountTotal, float $amount, array $payment, ?OrderKind $kind = null): SubscriptionOrder
    {
        $kind = ($payment['kind'] ?? null) instanceof OrderKind ? $payment['kind'] : ($kind ?? OrderKind::Renewal);

        return SubscriptionOrder::create([
            'account_id' => $account->id,
            'period' => $period->value,
            'kind' => $kind->value,
            'quantity' => $quantity,
            'subtotal' => round($subtotal, 2),
            'discount_total' => round($discountTotal, 2),
            // Which negotiated discount this charge consumed (or will consume, if it
            // is still pending). Needed so acceptOrder() can consume it when the
            // money lands rather than at quote time — design §5.2.
            'manual_discount_id' => $payment['manual_discount_id'] ?? null,
            'amount' => round($amount, 2),
            'payment_status' => $payment['payment_status'] ?? PaymentStatus::Paid->value,
            'payment_method' => $payment['payment_method'] ?? null,
            // How the money arrived. Razorpay stamps an order id, so its presence is
            // the honest signal; S2/S3/S6 pass `collection` explicitly.
            'collection' => $payment['collection']
                ?? (($payment['razorpay_order_id'] ?? null) ? CollectionMethod::Checkout->value : CollectionMethod::Offline->value),
            'transaction_number' => $payment['transaction_number'] ?? null,
            'razorpay_order_id' => $payment['razorpay_order_id'] ?? null,
            'remarks' => $payment['remarks'] ?? null,
        ]);
    }

    /**
     * Add one branch's coverage line to an order.
     *
     * S1: this only writes the LINE. It used to also write the branch mirror, which
     * made it one of several places coverage could change; the mirror is now derived
     * by CoverageMirror::sync() from the lines, so the caller syncs once after
     * adding all of them.
     *
     * The never-shorten guard stays here rather than in the mirror: if the branch
     * already holds coverage past this anchor (a comp, a longer paid term), the LINE
     * must record the longer date, or the projection would shorten it on the next
     * sync. The ledger has to be the whole truth, not most of it.
     */
    protected function addLine(SubscriptionOrder $order, Hostel $branch, float $amount, Carbon $end, ?Carbon $start = null, bool $complimentary = false): SubscriptionOrderLine
    {
        $end = ($branch->subscription_end && $branch->subscription_end->greaterThan($end))
            ? $branch->subscription_end->copy()
            : $end->copy();

        return SubscriptionOrderLine::create([
            'order_id' => $order->id,
            'branch_id' => $branch->id,
            'amount' => round($amount, 2),
            'complimentary' => $complimentary,
            'start_date' => $start ?? Carbon::now(),
            'end_date' => $end,
        ]);
    }

    /**
     * Split an amount into N shares that sum EXACTLY to it (largest remainder):
     * every share gets the floor, and the leftover paise are handed out one each
     * from the first line. ₹20,000 over 3 → 6666.67 · 6666.67 · 6666.66.
     *
     * @return array<int, float>
     */
    protected function allocateProportionally(float $amount, array $weights): array
    {
        $total = array_sum($weights);
        if ($total <= 0) {
            return $this->allocate($amount, count($weights));
        }

        // Largest remainder on paise, so the shares sum EXACTLY to $amount.
        $totalPaise = (int) round($amount * 100);
        $raw = array_map(fn ($w) => $totalPaise * $w / $total, $weights);
        $floors = array_map(fn ($r) => (int) floor($r), $raw);
        $left = $totalPaise - array_sum($floors);

        $order = array_keys($raw);
        usort($order, fn ($a, $b) => ($raw[$b] - $floors[$b]) <=> ($raw[$a] - $floors[$a]));
        foreach (array_slice($order, 0, $left) as $i) {
            $floors[$i]++;
        }

        return array_map(fn ($p) => $p / 100, $floors);
    }

    protected function allocate(float $amount, int $parts): array
    {
        if ($parts < 1) {
            return [];
        }

        $totalPaise = (int) round($amount * 100);
        $base = intdiv($totalPaise, $parts);
        $remainder = $totalPaise - ($base * $parts);

        return array_map(
            fn (int $i) => ($base + ($i < $remainder ? 1 : 0)) / 100,
            range(0, $parts - 1),
        );
    }
}
