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

    /**
     * Nominal length in days. Kept for display and for defensive fallbacks only —
     * NOT a proration denominator. Use cycleDays() for that: 365 is wrong in a
     * leap year and 30 is wrong in every month except four (S0 · finding F2).
     */
    public function days(): int
    {
        return match ($this) {
            self::Yearly => 365,
            self::Monthly => 30,
            self::Trial => (int) config('hostelease.trial_days', 14),
        };
    }

    /**
     * The start of the cycle that ENDS on $anchor — i.e. one term back from it.
     * Derived from the anchor so it needs no stored state and is automatically
     * calendar-correct (Feb is 28 or 29 days, a leap year is 366).
     */
    public function cycleStart(Carbon $anchor): Carbon
    {
        return match ($this) {
            self::Yearly => $anchor->copy()->subYear(),
            self::Monthly => $anchor->copy()->subMonth(),
            self::Trial => $anchor->copy()->subDays($this->days()),
        };
    }

    /**
     * The REAL number of days in the cycle ending on $anchor — the only correct
     * denominator for prorating a part-cycle charge. Pinned to midnight at both
     * ends so the answer never depends on the time of day.
     */
    public function cycleDays(Carbon $anchor): int
    {
        $end = $anchor->copy()->startOfDay();

        return max(1, (int) $this->cycleStart($end)->startOfDay()->diffInDays($end));
    }

    /** Paid periods only (trial is free). */
    public function isPaid(): bool
    {
        return $this !== self::Trial;
    }
}
