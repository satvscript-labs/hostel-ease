<?php

namespace App\Services\Billing;

use App\Enums\AccountStatus;
use App\Enums\BillingPeriod;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Hostel;
use App\Models\Subscription;
use App\Models\SubscriptionAccount;
use App\Models\SubscriptionOrder;
use App\Models\SubscriptionOrderLine;
use App\Models\User;
use App\Services\BranchBillingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
    ) {
    }

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

    public function ownerForBranch(Hostel $branch): ?User
    {
        $owner = User::where('role', 'hostel_admin')
            ->whereHas('hostels', fn ($q) => $q->where('hostels.id', $branch->id))
            ->first();

        return $owner ?: User::where('role', 'hostel_admin')->where('mobile', $branch->mobile)->first();
    }

    /** The branches billed under an account (all the owner can access). */
    public function includedBranches(SubscriptionAccount $account): Collection
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

        if ($account->unit_price_override !== null) {
            return (float) $account->unit_price_override;
        }

        return $this->branchBilling->unitPrice($period->value);
    }

    // -----------------------------------------------------------------
    // Compat: per-branch renewal (wired into the existing consumers)
    // -----------------------------------------------------------------

    /**
     * Drop-in replacement for BranchBillingService::renewBranch that also keeps
     * the account/order spine in sync. Returns the legacy Subscription so
     * callers (logger/response) are unchanged.
     */
    public function recordBranchRenewal(Hostel $branch, string $period, array $payment = []): Subscription
    {
        return DB::transaction(function () use ($branch, $period, $payment) {
            // Legacy per-branch coverage — unchanged behaviour (legacy sub + hostel mirror).
            $subscription = $this->branchBilling->renewBranch($branch, $period, $payment);

            $account = $this->accountForBranch($branch);
            if ($account) {
                // A settled payment becomes an order; pending/failed just refreshes state.
                if (($payment['payment_status'] ?? 'pending') === PaymentStatus::Paid->value) {
                    $this->orderFromLegacy($account, $branch, $subscription, $period);
                }
                $this->refreshAccountAnchor($account, BillingPeriod::tryFrom($period));
            }

            return $subscription;
        });
    }

    /**
     * Reflect an out-of-band change to a legacy Subscription (e.g. the Super
     * Admin marking a pending offline payment paid) into the account spine.
     */
    public function syncLegacySubscription(Subscription $subscription): void
    {
        $branch = Hostel::find($subscription->hostel_id);
        if (! $branch) {
            return;
        }

        $account = $this->accountForBranch($branch);
        if (! $account) {
            return;
        }

        DB::transaction(function () use ($account, $branch, $subscription) {
            if ($subscription->payment_status === PaymentStatus::Paid->value) {
                $this->orderFromLegacy($account, $branch, $subscription, (string) $subscription->plan);
            }
            $this->refreshAccountAnchor($account, BillingPeriod::tryFrom((string) $subscription->plan));
        });
    }

    protected function orderFromLegacy(SubscriptionAccount $account, Hostel $branch, Subscription $subscription, string $period): SubscriptionOrder
    {
        $bp = BillingPeriod::tryFrom($period) ?? BillingPeriod::Yearly;

        $order = SubscriptionOrder::updateOrCreate(
            ['legacy_subscription_id' => $subscription->id],
            [
                'account_id' => $account->id,
                'period' => $bp->value,
                'quantity' => 1,
                'subtotal' => $subscription->amount,
                'discount_total' => 0,
                'amount' => $subscription->amount,
                'payment_status' => $subscription->payment_status,
                'payment_method' => $subscription->payment_method,
                'transaction_number' => $subscription->transaction_number,
                'razorpay_order_id' => $subscription->razorpay_order_id,
                'remarks' => $subscription->remarks,
            ],
        );

        SubscriptionOrderLine::updateOrCreate(
            ['order_id' => $order->id, 'branch_id' => $branch->id],
            [
                'amount' => $subscription->amount,
                'start_date' => $subscription->start_date,
                'end_date' => $subscription->end_date,
            ],
        );

        return $order;
    }

    /** Recompute an account's anchor/status from its branches' actual coverage. */
    public function refreshAccountAnchor(SubscriptionAccount $account, ?BillingPeriod $period = null): void
    {
        $branches = $this->includedBranches($account);
        $anchor = $branches->max('subscription_end');
        $resolvedPeriod = $period ?? $account->period ?? BillingPeriod::Yearly;

        $account->update([
            'current_period_start' => $account->current_period_start ?? $branches->min('subscription_start') ?? now(),
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

        $anchor = $anchor ?? $account->current_period_end;
        if (! $anchor) {
            return $account->status ?? AccountStatus::Trial;
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

        return [
            'period' => $bp,
            'quantity' => $quantity,
            'unit' => $unit,
            'subtotal' => $subtotal,
            'breakdown' => $this->discounts->preview($account, $subtotal, $quantity, 'renewal'),
            'new_anchor' => $newAnchor,
            'branch_ids' => $branches->pluck('id')->all(),
        ];
    }

    /**
     * Consolidated renewal: every included branch is extended to one new anchor,
     * recorded as a single order with N lines.
     */
    public function renewAccount(SubscriptionAccount $account, string $period, array $payment = []): SubscriptionOrder
    {
        return DB::transaction(function () use ($account, $period, $payment) {
            $quote = $this->quoteRenewal($account, $period);
            $anchor = $quote['new_anchor'];
            $amount = $payment['amount'] ?? $quote['breakdown']['final'];

            $order = $this->makeOrder($account, $quote['period'], $quote['quantity'], $quote['subtotal'], $quote['breakdown']['discount_total'], $amount, $payment);

            $branches = $this->includedBranches($account);
            $share = $branches->count() > 0 ? round($amount / $branches->count(), 2) : 0;
            foreach ($branches as $branch) {
                $this->addLineAndMirror($order, $branch, $share, $anchor);
            }

            $account->update([
                'period' => $quote['period']->value,
                'current_period_start' => $account->current_period_start ?? now(),
                'current_period_end' => $anchor,
                'status' => $this->computeStatus($account, $anchor, $quote['period'])->value,
            ]);

            $this->discounts->consume($quote['breakdown']['manual_discount_id']);

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

        $from = $branch && $branch->subscription_end && $branch->subscription_end->isFuture()
            ? $branch->subscription_end->copy()
            : Carbon::now();
        $days = ($anchor && $anchor->greaterThan($from)) ? $from->diffInDays($anchor) : 0;
        $prorated = round($unit * $days / max(1, $bp->days()), 2);

        return [
            'prorated' => $prorated,
            'days_remaining' => (int) $days,
            'anchor' => $anchor,
            'unit' => $unit,
            'breakdown' => $this->discounts->preview($account, $prorated, 1, 'add_branch'),
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
                $sub = $this->recordBranchRenewal($branch, $this->paidPeriod($account->period?->value)->value, $payment);

                return SubscriptionOrder::firstWhere('legacy_subscription_id', $sub->id)
                    ?? $this->makeOrder($account, $this->paidPeriod($account->period?->value), 1, $sub->amount, 0, $sub->amount, $payment);
            }

            $quote = $this->quoteAddBranch($account, $branch);
            $amount = $payment['amount'] ?? $quote['breakdown']['final'];

            $order = $this->makeOrder($account, $account->period ?? BillingPeriod::Yearly, 1, $quote['prorated'], $quote['breakdown']['discount_total'], $amount, $payment);
            $this->addLineAndMirror($order, $branch, $amount, $anchor);

            $this->refreshAccountAnchor($account);
            $this->discounts->consume($quote['breakdown']['manual_discount_id']);

            return $order;
        });
    }

    /**
     * Align staggered branches: extend every branch that ends before the anchor
     * up to the anchor, charging a prorated top-up per branch (BRD D4).
     */
    public function align(SubscriptionAccount $account, array $payment = []): ?SubscriptionOrder
    {
        $anchor = $account->current_period_end;
        if (! $anchor) {
            return null;
        }

        return DB::transaction(function () use ($account, $anchor, $payment) {
            $bp = $this->paidPeriod($account->period?->value);
            $unit = $this->unitPrice($account, $bp);
            $behind = $this->includedBranches($account)
                ->filter(fn (Hostel $b) => ! $b->subscription_end || $b->subscription_end->lt($anchor));

            if ($behind->isEmpty()) {
                return null;
            }

            $lines = [];
            $subtotal = 0.0;
            foreach ($behind as $branch) {
                $from = $branch->subscription_end && $branch->subscription_end->isFuture() ? $branch->subscription_end : Carbon::now();
                $days = $from->diffInDays($anchor);
                $amount = round($unit * $days / max(1, $bp->days()), 2);
                $subtotal += $amount;
                $lines[] = [$branch, $amount];
            }

            $amount = $payment['amount'] ?? round($subtotal, 2);
            $order = $this->makeOrder($account, $bp, $behind->count(), round($subtotal, 2), 0, $amount, $payment);

            foreach ($lines as [$branch, $lineAmount]) {
                $this->addLineAndMirror($order, $branch, $lineAmount, $anchor);
            }

            $this->refreshAccountAnchor($account);

            return $order;
        });
    }

    /** Complimentary (₹0) grant of coverage across the account (BR-17). */
    public function comp(SubscriptionAccount $account, string $period, string $reason): SubscriptionOrder
    {
        return $this->renewAccount($account, $period, [
            'amount' => 0,
            'payment_status' => PaymentStatus::Paid->value,
            'payment_method' => PaymentMethod::Comp->value,
            'remarks' => 'Complimentary — '.$reason,
        ]);
    }

    public function setUnitPriceOverride(SubscriptionAccount $account, ?float $price): void
    {
        $account->update(['unit_price_override' => $price]);
    }

    // -----------------------------------------------------------------
    // Internals
    // -----------------------------------------------------------------

    protected function makeOrder(SubscriptionAccount $account, BillingPeriod $period, int $quantity, float $subtotal, float $discountTotal, float $amount, array $payment): SubscriptionOrder
    {
        return SubscriptionOrder::create([
            'account_id' => $account->id,
            'period' => $period->value,
            'quantity' => $quantity,
            'subtotal' => round($subtotal, 2),
            'discount_total' => round($discountTotal, 2),
            'amount' => round($amount, 2),
            'payment_status' => $payment['payment_status'] ?? PaymentStatus::Paid->value,
            'payment_method' => $payment['payment_method'] ?? null,
            'transaction_number' => $payment['transaction_number'] ?? null,
            'razorpay_order_id' => $payment['razorpay_order_id'] ?? null,
            'remarks' => $payment['remarks'] ?? null,
        ]);
    }

    /** Add an order line for a branch and mirror the anchor onto the branch (never shortens). */
    protected function addLineAndMirror(SubscriptionOrder $order, Hostel $branch, float $amount, Carbon $anchor): void
    {
        $end = ($branch->subscription_end && $branch->subscription_end->greaterThan($anchor))
            ? $branch->subscription_end
            : $anchor;

        SubscriptionOrderLine::create([
            'order_id' => $order->id,
            'branch_id' => $branch->id,
            'amount' => round($amount, 2),
            'start_date' => Carbon::now(),
            'end_date' => $end,
        ]);

        $branch->update([
            'subscription_start' => $branch->subscription_start ?? Carbon::now(),
            'subscription_end' => $end,
            'status' => 'active',
        ]);
    }
}
