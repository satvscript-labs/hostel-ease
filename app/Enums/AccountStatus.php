<?php

namespace App\Enums;

/**
 * Lifecycle of a billing account (one per owner).
 *   trial → active → grace → expired, plus suspended (manual override).
 */
enum AccountStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case Grace = 'grace';
    case Expired = 'expired';
    case Suspended = 'suspended';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** Bootstrap-ish colour token for status pills in the Super Admin UI. */
    public function color(): string
    {
        return match ($this) {
            self::Trial => 'info',
            self::Active => 'success',
            self::Grace => 'warning',
            self::Expired => 'danger',
            self::Suspended => 'secondary',
        };
    }

    /** Whether branches under the account are entitled to work. */
    public function isEntitled(): bool
    {
        return in_array($this, [self::Trial, self::Active, self::Grace], true);
    }
}
