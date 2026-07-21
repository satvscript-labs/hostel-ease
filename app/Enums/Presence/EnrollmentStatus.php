<?php

namespace App\Enums\Presence;

/**
 * A presence profile's enrollment lifecycle (01 §6):
 *  Pending  — queued/pushed to the device; person must register their face.
 *  Active   — confirmed present on the device.
 *  Failed   — the device rejected the push (surface + retry, never silent).
 *  Removed  — revoked from all devices; punch history is retained.
 */
enum EnrollmentStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Failed = 'failed';
    case Removed = 'removed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending — register face at device',
            self::Active => 'Active',
            self::Failed => 'Failed',
            self::Removed => 'Removed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Active => 'success',
            self::Failed => 'danger',
            self::Removed => 'secondary',
        };
    }
}
