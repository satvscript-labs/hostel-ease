<?php

namespace App\Services;

use App\Models\Hostel;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Account-level (per-owner) subscription billing.
 *
 * Billing is charged once for the whole account: one payment covers every
 * branch (hostel) the owner holds. Pricing applies a "3-for-2" style discount
 * — for every `free_per` branches, one branch is free.
 */
class SubscriptionBillingService
{
    /** Number of branches (hostels) this owner is billed across. */
    public function branchCount(User $owner): int
    {
        return count($owner->accessibleHostelIds());
    }

    /** Free branches = floor(total / free_per). */
    public function freeBranches(int $total): int
    {
        $per = max(1, (int) config('hostelease.subscription_pricing.free_per', 3));

        return intdiv($total, $per);
    }

    /** Payable branches = total - free. */
    public function payableBranches(int $total): int
    {
        return max(0, $total - $this->freeBranches($total));
    }

    /** Per-branch price for the given period ('yearly' | 'monthly'). */
    public function unitPrice(string $period): float
    {
        return (float) config(
            'hsms.subscription_pricing.'.($period === 'monthly' ? 'monthly' : 'yearly')
        );
    }

    /**
     * Build a price quote for renewing an owner's whole account.
     *
     * @return array{
     *   period:string, branches:int, free:int, payable:int, unit:float,
     *   amount:float, amount_paise:int, start:Carbon, end:Carbon
     * }
     */
    public function quote(User $owner, string $period): array
    {
        $period = $period === 'monthly' ? 'monthly' : 'yearly';
        $total = $this->branchCount($owner);
        $free = $this->freeBranches($total);
        $payable = max(0, $total - $free);
        $unit = $this->unitPrice($period);
        $amount = $payable * $unit;

        // Stack a renewal on top of remaining coverage if still active.
        $currentEnd = $this->currentEnd($owner);
        $base = $currentEnd && $currentEnd->isFuture() ? $currentEnd->copy() : Carbon::now();
        $end = $period === 'monthly' ? $base->copy()->addMonth() : $base->copy()->addYear();

        return [
            'period' => $period,
            'branches' => $total,
            'free' => $free,
            'payable' => $payable,
            'unit' => $unit,
            'amount' => $amount,
            'amount_paise' => (int) round($amount * 100),
            'start' => Carbon::now(),
            'end' => $end,
        ];
    }

    /** Latest coverage end-date across all the owner's branches. */
    public function currentEnd(User $owner): ?Carbon
    {
        $max = Hostel::whereIn('id', $owner->accessibleHostelIds())
            ->max('subscription_end');

        return $max ? Carbon::parse($max) : null;
    }

    /**
     * Record one account subscription and extend EVERY branch together.
     *
     * @param  array{amount?:float,payment_status?:string,payment_method?:string,transaction_number?:string,remarks?:string}  $payment
     */
    public function renewOwner(User $owner, string $period, array $payment = []): Subscription
    {
        $quote = $this->quote($owner, $period);
        $hostelIds = $owner->accessibleHostelIds();
        $primaryId = $owner->hostel_id ?: ($hostelIds[0] ?? null);

        abort_if($primaryId === null, 422, 'This owner has no branch to bill.');

        return DB::transaction(function () use ($owner, $period, $payment, $quote, $hostelIds, $primaryId) {
            $subscription = Subscription::create([
                'hostel_id' => $primaryId,
                'plan' => $period,
                'start_date' => $quote['start'],
                'end_date' => $quote['end'],
                'amount' => $payment['amount'] ?? $quote['amount'],
                'payment_status' => $payment['payment_status'] ?? 'pending',
                'payment_method' => $payment['payment_method'] ?? null,
                'transaction_number' => $payment['transaction_number'] ?? null,
                'remarks' => $payment['remarks'] ?? "Account renewal · {$quote['payable']}/{$quote['branches']} branch(es) billable · owner {$owner->name}",
            ]);

            // Only extend coverage once the payment is actually settled.
            if (($payment['payment_status'] ?? 'pending') === 'paid') {
                Hostel::whereIn('id', $hostelIds)->get()->each(function (Hostel $h) use ($quote) {
                    $h->update([
                        'subscription_start' => $h->subscription_start ?? $quote['start'],
                        'subscription_end' => $quote['end'],
                        'status' => 'active',
                    ]);
                });
            }

            return $subscription;
        });
    }

    /**
     * Extend every branch of the subscription's owner to that record's end-date
     * (never shortening existing coverage). Used when a super admin accepts or
     * edits an offline payment to "paid".
     */
    public function syncBranchesToSubscription(Subscription $subscription): void
    {
        $owner = User::where('role', 'hostel_admin')
            ->where('hostel_id', $subscription->hostel_id)
            ->first()
            ?? User::where('role', 'hostel_admin')
                ->whereHas('hostels', fn ($q) => $q->where('hostels.id', $subscription->hostel_id))
                ->first();

        if (! $owner) {
            return;
        }

        Hostel::whereIn('id', $owner->accessibleHostelIds())->get()->each(function (Hostel $h) use ($subscription) {
            $end = $subscription->end_date;
            if ($h->subscription_end && $h->subscription_end->greaterThan($end)) {
                $end = $h->subscription_end; // don't shorten coverage
            }
            $h->update([
                'subscription_start' => $h->subscription_start ?? $subscription->start_date,
                'subscription_end' => $end,
                'status' => 'active',
            ]);
        });
    }

    /** Resolve the owner (account) a subscription record belongs to. */
    public function ownerFor(Subscription $subscription): ?User
    {
        return User::where('role', 'hostel_admin')
            ->where('hostel_id', $subscription->hostel_id)
            ->first()
            ?? User::where('role', 'hostel_admin')
                ->whereHas('hostels', fn ($q) => $q->where('hostels.id', $subscription->hostel_id))
                ->first();
    }
}

