<?php

namespace App\Enums;

use Illuminate\Support\Carbon;

/**
 * A subscription term. Drives coverage length + proration.
 */
enum BillingPeriod: string
{
    case Yearly = 'yearly';
    case Monthly = 'monthly';
    case Trial = 'trial';

    public function label(): string
    {
        return match ($this) {
            self::Yearly => 'Yearly',
            self::Monthly => 'Monthly',
            self::Trial => 'Trial',
        };
    }

    /** Add one full term of this period onto a date. */
    public function extend(Carbon $from): Carbon
    {
        return match ($this) {
            self::Yearly => $from->copy()->addYear(),
            self::Monthly => $from->copy()->addMonth(),
            self::Trial => $from->copy()->addDays((int) config('hostelease.trial_days', 14)),
        };
    }

    /** Nominal length in days — used as the denominator for proration. */
    public function days(): int
    {
        return match ($this) {
            self::Yearly => 365,
            self::Monthly => 30,
            self::Trial => (int) config('hostelease.trial_days', 14),
        };
    }

    /** Paid periods only (trial is free). */
    public function isPaid(): bool
    {
        return $this !== self::Trial;
    }
}
