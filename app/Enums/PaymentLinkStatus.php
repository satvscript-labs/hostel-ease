<?php

namespace App\Enums;

/**
 * The state of a Razorpay Payment Link attached to an order (S2).
 *
 * These are Razorpay's OWN status values, mirrored verbatim so the column always
 * reads what the gateway reads and `fetchPaymentLink()` can be assigned straight
 * into it with no translation table to drift.
 *
 * It is NOT the order's payment status. The order stays `pending` until the money
 * actually lands — a `paid` link is what *causes* that, via acceptOrder(). Keeping
 * the two separate is what makes "paid at Razorpay but not yet applied here"
 * (a missed webhook) a visible state instead of an invisible one.
 */
enum PaymentLinkStatus: string
{
    case Created = 'created';                  // live, awaiting payment
    case PartiallyPaid = 'partially_paid';     // we disable partial, so: anomaly
    case Paid = 'paid';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Awaiting payment',
            self::PartiallyPaid => 'Partially paid',
            self::Paid => 'Paid',
            self::Expired => 'Expired',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Whether this link can still take money — the one question that matters.
     *
     * Drives both halves of BP2/BP3: a live link is what gets cancelled when the
     * charge is settled another way, and what blocks a second link being issued
     * for the same charge. `partially_paid` counts as live: money has moved and
     * more can still arrive, so it must never be treated as finished.
     */
    public function isLive(): bool
    {
        return in_array($this, [self::Created, self::PartiallyPaid], true);
    }

    /** Whether a replacement link may be issued for this charge. */
    public function isReissuable(): bool
    {
        return in_array($this, [self::Expired, self::Cancelled], true);
    }

    public function color(): string
    {
        return match ($this) {
            self::Created => 'info',
            self::PartiallyPaid => 'warning',
            self::Paid => 'success',
            self::Expired => 'secondary',
            self::Cancelled => 'secondary',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Created => 'paper-plane',
            self::PartiallyPaid => 'circle-half-stroke',
            self::Paid => 'circle-check',
            self::Expired => 'clock',
            self::Cancelled => 'ban',
        };
    }
}
