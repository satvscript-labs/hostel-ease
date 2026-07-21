<?php

namespace App\Services\Presence;

use App\Services\Presence\DTO\AdapterResult;
use App\Services\Presence\DTO\DeviceUser;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * The vendor-agnostic seam (01 §5, 04 §4). Everything above this interface —
 * PresenceService, the command, the boards — never sees a TimeWatch field name.
 * The fingerprint variant and future vendors are new implementations; nothing
 * above changes. If TimeWatch ever adds a push/webhook, it becomes a new route
 * calling PresenceService::ingest(), not a new adapter method.
 */
interface PresenceDeviceAdapter
{
    /** @return Collection<int, \App\Services\Presence\DTO\DeviceInfo> */
    public function getDevices(): Collection;

    public function addUser(DeviceUser $user, string $deviceSerial): AdapterResult;

    public function deleteUser(string $deviceUserId, string $deviceSerial): AdapterResult;

    /** @return Collection<int, \App\Services\Presence\DTO\RawPunch> */
    public function getPunches(CarbonInterface $from, CarbonInterface $to, ?string $deviceSerial = null): Collection;

    /** @param array<int, string> $deviceSerials */
    public function pullLogs(array $deviceSerials, CarbonInterface $from, CarbonInterface $to): AdapterResult;

    /** @param array<int, string> $deviceSerials */
    public function syncTime(array $deviceSerials): AdapterResult;

    public function verifyUserOnDevice(string $deviceUserId, string $deviceSerial): bool;
}
