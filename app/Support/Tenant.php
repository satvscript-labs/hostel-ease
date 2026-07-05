<?php

namespace App\Support;

/**
 * Lightweight request-scoped holder for the active hostel (tenant) id.
 *
 * Set by the SetTenant middleware from the authenticated user. Super Admin
 * leaves this null so the TenantScope does not constrain their queries.
 */
class Tenant
{
    protected static ?int $hostelId = null;

    public static function set(?int $hostelId): void
    {
        static::$hostelId = $hostelId;
    }

    public static function id(): ?int
    {
        return static::$hostelId;
    }

    public static function check(): bool
    {
        return static::$hostelId !== null;
    }

    public static function clear(): void
    {
        static::$hostelId = null;
    }
}
