<?php

namespace App\Enums;

enum DiscountType: string
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';

    public function label(): string
    {
        return $this === self::Percentage ? 'Percentage (%)' : 'Fixed amount (₹)';
    }
}
