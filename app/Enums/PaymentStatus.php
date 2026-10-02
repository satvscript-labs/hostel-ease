<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Paid = 'paid';
    case Pending = 'pending';
    case Failed = 'failed';
    case Refunded = 'refunded';
    /** An operator wrote this order off as a mistake. Never grants, never owed. */
    case Voided = 'voided';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** Only a settled payment grants/extends coverage. */
    public function grantsCoverage(): bool
    {
        return $this === self::Paid;
    }

    /**
     * Whether this order is still money we expect to receive. Drives the
     * receivables worklist, so it must exclude both the settled and the
     * written-off.
     */
    public function isOutstanding(): bool
    {
        return $this === self::Pending;
    }

    /** Bootstrap-ish colour token for status pills. */
    public function color(): string
    {
        return match ($this) {
            self::Paid => 'success',
            self::Pending => 'warning',
            self::Failed => 'danger',
            self::Refunded => 'info',
            self::Voided => 'secondary',
        };
    }
}
