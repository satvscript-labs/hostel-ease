<?php

namespace App\Enums;

/**
 * How long a manual discount lives (BRD BR-24).
 *   one_time      → the next single charge, then consumed
 *   one_renewal   → the next renewal only, then consumed
 *   every_renewal → re-applied on every renewal until removed/expired (permanent)
 */
enum DiscountRecurrence: string
{
    case OneTime = 'one_time';
    case OneRenewal = 'one_renewal';
    case EveryRenewal = 'every_renewal';

    public function label(): string
    {
        return match ($this) {
            self::OneTime => __('One-time'),
            self::OneRenewal => __('Next renewal'),
            self::EveryRenewal => __('Permanent (every renewal)'),
        };
    }

    /** One-shot discounts are consumed after they apply; permanent ones persist. */
    public function isConsumable(): bool
    {
        return $this !== self::EveryRenewal;
    }
}
