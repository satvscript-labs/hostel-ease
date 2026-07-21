<?php

namespace App\Services\Presence;

use App\Enums\Presence\DeviceStatus;
use App\Services\Presence\DTO\AdapterResult;
use App\Services\Presence\DTO\DeviceInfo;
use App\Services\Presence\DTO\DeviceUser;
use App\Services\Presence\DTO\RawPunch;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * In-memory, scriptable adapter — the whole point of the seam. Tests (and local
 * dev without a physical box) drive the entire pipeline by pushing punches and
 * inspecting the calls the service made. No hardware, no HTTP.
 */
class FakePresenceAdapter implements PresenceDeviceAdapter
{
    /** @var array<int, RawPunch> */
    public array $punches = [];

    /** @var array<int, DeviceInfo> */
    public array $devices = [];

    /** device users registered per serial: [serial => [deviceUserId => name]] */
    public array $registered = [];

    /** call log for assertions: each entry is [method, ...args] */
    public array $calls = [];

    // ── Scripting helpers (test-facing) ─────────────────────────────────

    public function pushPunch(RawPunch $punch): self
    {
        $this->punches[] = $punch;

        return $this;
    }

    public function registerDevice(DeviceInfo $device): self
    {
        $this->devices[] = $device;

        return $this;
    }

    // ── Adapter contract ────────────────────────────────────────────────

    public function getDevices(): Collection
    {
        $this->calls[] = ['getDevices'];

        return collect($this->devices);
    }

    public function addUser(DeviceUser $user, string $deviceSerial): AdapterResult
    {
        $this->calls[] = ['addUser', $user->deviceUserId, $deviceSerial];
        $this->registered[$deviceSerial][$user->deviceUserId] = $user->name;

        return AdapterResult::ok('User added.');
    }

    public function deleteUser(string $deviceUserId, string $deviceSerial): AdapterResult
    {
        $this->calls[] = ['deleteUser', $deviceUserId, $deviceSerial];
        unset($this->registered[$deviceSerial][$deviceUserId]);

        return AdapterResult::ok('User deleted.');
    }

    public function getPunches(CarbonInterface $from, CarbonInterface $to, ?string $deviceSerial = null): Collection
    {
        $this->calls[] = ['getPunches', $from, $to, $deviceSerial];

        return collect($this->punches)->filter(function (RawPunch $p) use ($from, $to, $deviceSerial) {
            if ($deviceSerial !== null && $p->deviceSerial !== $deviceSerial) {
                return false;
            }

            return $p->punchedAt->betweenIncluded($from, $to);
        })->values();
    }

    public function pullLogs(array $deviceSerials, CarbonInterface $from, CarbonInterface $to): AdapterResult
    {
        $this->calls[] = ['pullLogs', $deviceSerials, $from, $to];

        return AdapterResult::ok('Pull command queued.');
    }

    public function syncTime(array $deviceSerials): AdapterResult
    {
        $this->calls[] = ['syncTime', $deviceSerials];

        return AdapterResult::ok('Time synced.');
    }

    public function verifyUserOnDevice(string $deviceUserId, string $deviceSerial): bool
    {
        $this->calls[] = ['verifyUserOnDevice', $deviceUserId, $deviceSerial];

        return isset($this->registered[$deviceSerial][$deviceUserId]);
    }

    /** True if any scripted call matched the given method name. */
    public function called(string $method): bool
    {
        return collect($this->calls)->contains(fn ($c) => $c[0] === $method);
    }
}
