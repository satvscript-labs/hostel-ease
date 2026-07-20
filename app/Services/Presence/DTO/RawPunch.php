<?php

namespace App\Services\Presence\DTO;

use Carbon\CarbonInterface;

/**
 * One punch as an adapter reports it — vendor field names stop here. Direction
 * is intentionally NOT resolved yet: PresenceService decides that per the
 * device's mode (`rawInOutMode` is passed through for the entry/exit case).
 */
final readonly class RawPunch
{
    public function __construct(
        public string $deviceSerial,
        public string $deviceUserId,
        public CarbonInterface $punchedAt,
        public ?string $rawInOutMode = null,
        public ?string $verifyMode = null,
    ) {
    }
}
