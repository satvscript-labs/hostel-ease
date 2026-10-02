<?php

namespace App\Services;

use App\Models\Hostel;
use Illuminate\Support\Carbon;

/**
 * Single-branch PRICING. Pure calculation — it writes nothing.
 *
 * S1 (decision D8) removed its two write methods, `renewBranch()` and
 * `syncBranchToSubscription()`. They were the last writers of the legacy
 * per-branch `subscriptions` table and of `hostels.subscription_*`:
 *
 *   · `renewBranch()` was the other half of the F1 double-stamp (it re-quoted
 *     against coverage the caller had already written, then stacked another term),
 *     and the only code that fed the legacy payment_method ENUM, which is what
 *     finding F6 was about;
 *   · coverage is now a projection over paid order lines, maintained solely by
 *     CoverageMirror, and every charge is a `subscription_order`.
 *
 * What remains is the per-branch unit price and a single-branch quote, both still
 * needed: the account engine prices a branch through here, and the owner's
 * per-branch checkout quotes through here.
 */
class BranchBillingService
{
    /** Per-branch price for the given period ('yearly' | 'monthly' | 'trial'). */
    public function unitPrice(string $period): float
    {
        if ($period === 'trial') {
            return 0.0;
        }

        return (float) config(
            'hostelease.subscription_pricing.'.($period === 'monthly' ? 'monthly' : 'yearly'),
            $period === 'monthly' ? 1000 : 10000
        );
    }

    /**
     * Price one branch's next term, stacking on remaining coverage so no paid day
     * is ever lost (BR-9).
     *
     * @return array{period:string, branch_id:int, amount:float, amount_paise:int, start:Carbon, end:Carbon}
     */
    public function quote(Hostel $branch, string $period): array
    {
        $period = in_array($period, ['monthly', 'yearly', 'trial'], true) ? $period : 'yearly';
        $amount = $this->unitPrice($period);

        // Stack on remaining coverage when the branch is still entitled; otherwise
        // the term starts today. A branch with no coverage end date is not entitled
        // (S0 · F1), so it bases on now — which is what a first charge should do.
        $base = $branch->isActive() && $branch->subscription_end ? $branch->subscription_end->copy() : Carbon::now();

        $end = match ($period) {
            'trial' => $base->copy()->addDays((int) config('hostelease.trial_days', 14)),
            'monthly' => $base->copy()->addMonth(),
            default => $base->copy()->addYear(),
        };

        return [
            'period' => $period,
            'branch_id' => $branch->id,
            'amount' => $amount,
            'amount_paise' => (int) round($amount * 100),
            'start' => Carbon::now(),
            'end' => $end,
        ];
    }
}
