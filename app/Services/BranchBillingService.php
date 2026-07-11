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

        return DB::transaction(function () use ($branch, $period, $payment, $quote) {
            $subscription = Subscription::create([
                'hostel_id' => $branch->id,
                'plan' => $period,
                'start_date' => $quote['start'],
                'end_date' => $quote['end'],
                'amount' => $payment['amount'] ?? $quote['amount'],
                'payment_status' => $payment['payment_status'] ?? 'pending',
                'payment_method' => $payment['payment_method'] ?? null,
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
