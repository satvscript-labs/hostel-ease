<?php

namespace App\Enums;

enum DiscountStatus: string
{
    case Active = 'active';
    case Consumed = 'consumed';
    case Expired = 'expired';
    case Revoked = 'revoked';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
