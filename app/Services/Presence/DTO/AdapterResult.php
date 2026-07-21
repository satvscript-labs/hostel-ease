<?php

namespace App\Services\Presence\DTO;

/**
 * A typed pass/fail for a command-style adapter call (AddUser, DeleteUser,
 * SyncTime, PullLogs), so nothing above the adapter reads a vendor JSON shape
 * or a raw HTTP status.
 */
final readonly class AdapterResult
{
    public function __construct(
        public bool $success,
        public ?string $message = null,
        public mixed $data = null,
    ) {
    }

    public static function ok(?string $message = null, mixed $data = null): self
    {
        return new self(true, $message, $data);
    }

    public static function fail(?string $message = null, mixed $data = null): self
    {
        return new self(false, $message, $data);
    }
}
