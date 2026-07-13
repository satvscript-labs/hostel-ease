<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Paid = 'paid';
    case Pending = 'pending';
    case Failed = 'failed';
    case Refunded = 'refunded';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** Only a settled payment grants/extends coverage. */
    public function grantsCoverage(): bool
    {
        return $this === self::Paid;
    }
}
