<?php

namespace App\Services\Billing;

use App\Enums\DiscountRecurrence;
use App\Enums\DiscountStatus;
use App\Enums\DiscountType;
use App\Models\Discount;
use App\Models\DiscountRule;
use App\Models\SubscriptionAccount;
use DateTimeInterface;

/**
 * The discount engine (BRD §6.7). Every quote runs a charge through here to get
 * a deterministic breakdown: list → volume → manual → final (floored at ₹0).
 *
 * Precedence (BR-27): apply the best automatic volume tier, then the best
 * applicable manual discount. Stacking is governed by config
 * 'hostelease.discount_stacking' ('stack' sequential | 'greater' best-of).
 */
class DiscountService
{
    /**
     * @param  string  $chargeKind  'purchase' | 'renewal' | 'add_branch'
     * @return array{subtotal:float, quantity:int, volume_amount:float, volume_rule_id:?int, manual_amount:float, manual_discount_id:?int, discount_total:float, final:float}
     */
    public function preview(SubscriptionAccount $account, float $subtotal, int $quantity, string $chargeKind, ?DateTimeInterface $on = null): array
    {
        $subtotal = round(max(0, $subtotal), 2);
        $on = $on ?: now();
        $stacking = (string) config('hostelease.discount_stacking', 'stack');

        // Best automatic volume tier for this quantity.
        $volumeRule = $this->bestVolumeRule($quantity, $subtotal);
        $volumeAmount = $volumeRule
            ? $this->amountFor($volumeRule->type, (float) $volumeRule->value, $volumeRule->max_amount, $subtotal)
            : 0.0;

        // Best applicable manual discount (on the post-volume base when stacking).
        $manual = $this->bestManualDiscount($account, $chargeKind, $subtotal, $on);
        $manualBase = $stacking === 'stack' ? max(0, $subtotal - $volumeAmount) : $subtotal;
        $manualAmount = $manual
            ? $this->amountFor($manual->type, (float) $manual->value, $manual->max_amount, $manualBase)
            : 0.0;

        if ($stacking === 'greater') {
            // Only the single larger discount wins.
            if ($volumeAmount >= $manualAmount) {
                $manualAmount = 0.0;
                $manual = null;
            } else {
                $volumeAmount = 0.0;
                $volumeRule = null;
            }
        }

        $discountTotal = round(min($subtotal, $volumeAmount + $manualAmount), 2);

        return [
            'subtotal' => $subtotal,
            'quantity' => $quantity,
            'volume_amount' => round($volumeAmount, 2),
            'volume_rule_id' => $volumeRule?->id,
            'manual_amount' => round($manualAmount, 2),
            'manual_discount_id' => $manual?->id,
            'discount_total' => $discountTotal,
            'final' => round(max(0, $subtotal - $discountTotal), 2),
        ];
    }

    /** Mark a one-shot manual discount consumed after a successful charge (BR-28). */
    public function consume(?int $manualDiscountId): void
    {
        if (! $manualDiscountId) {
            return;
        }

        $discount = Discount::find($manualDiscountId);
        if ($discount && $discount->recurrence->isConsumable() && $discount->status === DiscountStatus::Active) {
            $discount->update(['status' => DiscountStatus::Consumed, 'consumed_at' => now()]);
        }
    }

    protected function bestVolumeRule(int $quantity, float $subtotal): ?DiscountRule
    {
        return DiscountRule::active()
            ->where('min_quantity', '<=', $quantity)
            ->get()
            ->sortByDesc(fn (DiscountRule $r) => $this->amountFor($r->type, (float) $r->value, $r->max_amount, $subtotal))
            ->first();
    }

    protected function bestManualDiscount(SubscriptionAccount $account, string $chargeKind, float $base, DateTimeInterface $on): ?Discount
    {
        return $account->discounts()
            ->availableOn($on)
            ->get()
            ->filter(fn (Discount $d) => $this->recurrenceApplies($d->recurrence, $chargeKind))
            ->sortByDesc(fn (Discount $d) => $this->amountFor($d->type, (float) $d->value, $d->max_amount, $base))
            ->first();
    }

    /** one_time applies to any charge; the two renewal kinds only to renewals. */
    protected function recurrenceApplies(DiscountRecurrence $recurrence, string $chargeKind): bool
    {
        if ($chargeKind === 'renewal') {
            return true;
        }

        return $recurrence === DiscountRecurrence::OneTime;
    }

    /** Compute a discount amount, honouring a percentage cap and never exceeding the base. */
    protected function amountFor(DiscountType $type, float $value, $cap, float $base): float
    {
        $amount = $type === DiscountType::Percentage ? $base * $value / 100 : $value;

        if ($cap !== null) {
            $amount = min($amount, (float) $cap);
        }

        return round(min($amount, $base), 2);
    }
}
