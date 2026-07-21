<?php

namespace App\Enums\Presence;

/**
 * Where a punch came from. A `Manual` punch is a real correction posted by a
 * real admin (reason required, ActivityLogger-logged) and so legitimately
 * joins the immutable register; a `Device` punch is what the gate reported
 * (01 §4).
 */
enum PunchSource: string
{
    case Device = 'device';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Device => 'Device',
            self::Manual => 'Manual',
        };
    }
}
