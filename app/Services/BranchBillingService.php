<?php

namespace App\Services;

use App\Models\Hostel;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Branch-level subscription billing.
 *
 * Each branch (Hostel) is billed and managed individually.
 */
class BranchBillingService
{
    /** Methods the legacy `subscriptions.payment_method` ENUM column accepts. */
    private const LEGACY_METHODS = ['cash', 'upi', 'cheque', 'rtgs', 'online'];

    /** Per-branch price for the given period ('yearly' | 'monthly'). */
    public function unitPrice(string $period): float
    {
        if ($period === 'trial') return 0.0;
        
        return (float) config(
            'hostelease.subscription_pricing.'.($period === 'monthly' ? 'monthly' : 'yearly'),
            $period === 'monthly' ? 1000 : 10000
        );
    }

    /**
     * Build a price quote for renewing a specific branch.
     *
     * @return array{
     *   period:string, branch_id:int, amount:float, amount_paise:int, start:Carbon, end:Carbon
     * }
     */
    public function quote(Hostel $branch, string $period): array
    {
        $period = in_array($period, ['monthly', 'yearly', 'trial']) ? $period : 'yearly';
        $amount = $this->unitPrice($period);

        // Stack a renewal on top of remaining coverage if still active.
        $base = $branch->isActive() && $branch->subscription_end ? $branch->subscription_end->copy() : Carbon::now();
        
        if ($period === 'trial') {
            $end = $base->copy()->addDays(14);
        } else {
            $end = $period === 'monthly' ? $base->copy()->addMonth() : $base->copy()->addYear();
        }

        return [
            'period' => $period,
            'branch_id' => $branch->id,
            'amount' => $amount,
            'amount_paise' => (int) round($amount * 100),
            'start' => Carbon::now(),
            'end' => $end,
        ];
    }

    /**
     * Record a subscription and extend the branch's coverage.
     */
    public function renewBranch(Hostel $branch, string $period, array $payment = []): Subscription
    {
        $quote = $this->quote($branch, $period);

        // The legacy column is a MySQL ENUM without 'comp' (S0 · finding F6), and
        // the connection is strict — so a comped charge arriving here used to throw
        // and 500 the request. Guarded at the single write point rather than in each
        // caller's validator, because four controllers can reach this. The real
        // method is still recorded on the subscription_order, which is a plain
        // string column; only the legacy mirror is narrowed. Invisible to the test
        // suite on SQLite, which does not enforce ENUM at all (finding F14).
        $legacyMethod = in_array($payment['payment_method'] ?? null, self::LEGACY_METHODS, true)
            ? $payment['payment_method']
            : null;

        return DB::transaction(function () use ($branch, $period, $payment, $quote, $legacyMethod) {
            $subscription = Subscription::create([
                'hostel_id' => $branch->id,
                'plan' => $period,
                'start_date' => $quote['start'],
                'end_date' => $quote['end'],
                'amount' => $payment['amount'] ?? $quote['amount'],
                'payment_status' => $payment['payment_status'] ?? 'pending',
                'payment_method' => $legacyMethod,
                'transaction_number' => $payment['transaction_number'] ?? null,
                'razorpay_order_id' => $payment['razorpay_order_id'] ?? null,
                'remarks' => $payment['remarks'] ?? "Branch renewal · {$branch->name}",
            ]);

            // Only extend coverage once the payment is actually settled.
            if (($payment['payment_status'] ?? 'pending') === 'paid') {
                $branch->update([
                    'subscription_start' => $branch->subscription_start ?? $quote['start'],
                    'subscription_end' => $quote['end'],
                    'status' => 'active',
                ]);
            }

            return $subscription;
        });
    }

    /**
     * Extend a specific branch to the subscription's end-date.
     * Used when super admin updates payment status offline.
     */
    public function syncBranchToSubscription(Subscription $subscription): void
    {
        $branch = Hostel::find($subscription->hostel_id);
        if (! $branch) {
            return;
        }

        $end = $subscription->end_date;
        if ($branch->subscription_end && $branch->subscription_end->greaterThan($end)) {
            $end = $branch->subscription_end; // don't shorten coverage
        }

        $branch->update([
            'subscription_start' => $branch->subscription_start ?? $subscription->start_date,
            'subscription_end' => $end,
            'status' => 'active',
        ]);
    }
}
