<?php

namespace App\Services\Presence\DTO;

/** A user to push onto a device via AddUser. Face is captured at the device. */
final readonly class DeviceUser
{
    public function __construct(
        public string $deviceUserId,
        public string $name,
        public ?string $card = null,
        public ?string $accessFrom = null,
        public ?string $accessTo = null,
    ) {
    }
}
