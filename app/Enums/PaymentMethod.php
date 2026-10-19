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
            self::Cash => __('Cash'),
            self::Upi => __('UPI'),
            self::Cheque => __('Cheque'),
            self::Rtgs => __('RTGS / NEFT'),
            self::Online => __('Online (Razorpay)'),
            self::Comp => __('Complimentary'),
        };
    }
}
