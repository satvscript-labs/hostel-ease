<?php

namespace App\Enums;

/**
 * HOW the money for an order arrived — the collection channel, as distinct from
 * what the charge was (OrderKind) and from the instrument (PaymentMethod).
 *
 * Exists so S2/S3/S6 can tell their own charges apart: an operator payment link,
 * an owner's self-serve checkout and an auto-debit all produce ordinary orders and
 * all need to be findable as a group (and reported on separately — a link that is
 * never paid is a very different signal from a failed auto-debit).
 */
enum CollectionMethod: string
{
    case Offline = 'offline';    // recorded by the operator: cash, UPI, cheque, RTGS, or a ₹0 grant
    case Link = 'link';          // operator-issued Razorpay Payment Link (S2)
    case Checkout = 'checkout';  // owner self-serve Razorpay Checkout (S3)
    case Autopay = 'autopay';    // mandate debit we initiated (S6)

    public function label(): string
    {
        return match ($this) {
            self::Offline => 'Recorded offline',
            self::Link => 'Payment link',
            self::Checkout => 'Online checkout',
            self::Autopay => 'Auto-debit',
        };
    }

    /** Whether the customer paid this themselves through Razorpay. */
    public function isOnline(): bool
    {
        return $this !== self::Offline;
    }
}
