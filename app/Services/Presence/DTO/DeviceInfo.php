<?php

namespace App\Services\Presence\DTO;

use App\Enums\Presence\DeviceStatus;
use Carbon\CarbonInterface;

/** A device's health as GetDeviceList reports it. */
final readonly class DeviceInfo
{
    public function __construct(
        public string $serial,
        public ?string $name = null,
        public DeviceStatus $status = DeviceStatus::Unknown,
        public ?CarbonInterface $lastConnectedAt = null,
        public ?CarbonInterface $lastLogAt = null,
        public int $userCount = 0,
        public int $faceCount = 0,
        public int $fingerCount = 0,
    ) {
    }
}
