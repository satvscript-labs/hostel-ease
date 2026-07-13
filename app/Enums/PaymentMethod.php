<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Upi = 'upi';
    case Cheque = 'cheque';
    case Rtgs = 'rtgs';
    case Online = 'online';
    case Comp = 'comp';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Upi => 'UPI',
            self::Cheque => 'Cheque',
            self::Rtgs => 'RTGS / NEFT',
            self::Online => 'Online (Razorpay)',
            self::Comp => 'Complimentary',
        };
    }
}
