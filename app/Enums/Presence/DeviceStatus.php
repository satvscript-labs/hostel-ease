<?php

namespace App\Enums\Presence;

/**
 * A device's connectivity, cached from GetDeviceList by the sync command.
 * Unknown = we have not reached iDMS yet, or iDMS is unreachable — shown as an
 * honest "last synced X ago" banner, never a false "online" (03 §7).
 */
enum DeviceStatus: string
{
    case Online = 'online';
    case Offline = 'offline';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Online => 'Online',
            self::Offline => 'Offline',
            self::Unknown => 'Unknown',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Online => 'success',
            self::Offline => 'danger',
            self::Unknown => 'secondary',
        };
    }

    /** Map a raw iDMS GetDeviceList status string onto our enum. */
    public static function fromVendor(?string $raw): self
    {
        return match (strtolower(trim((string) $raw))) {
            'online' => self::Online,
            'offline' => self::Offline,
            default => self::Unknown,
        };
    }
}
