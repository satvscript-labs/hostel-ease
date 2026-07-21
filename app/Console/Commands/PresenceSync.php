<?php

namespace App\Console\Commands;

use App\Enums\Presence\DeviceStatus;
use App\Models\PresenceDevice;
use App\Services\Presence\DTO\DeviceInfo;
use App\Services\Presence\PresenceDeviceAdapter;
use App\Services\Presence\PresenceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The presence pipeline tick (04 §5). Scheduled every minute:
 *   1. GetDeviceList → refresh device health cache (also our iDMS liveness probe)
 *   2. PullLogs      → nudge devices to upload buffered punches
 *   3. GetPunchData  → sliding overlap window → idempotent ingest
 *   4. flag stale profiles (the always-works missed-punch detector)
 *
 * Runs OUTSIDE any tenant context: it iterates every active device across all
 * hostels and writes with explicit hostel_id (the punch pipeline resolves each
 * device's tenant from the device row). Idempotent and restartable — a killed
 * run loses nothing; the overlap window + punch unique index dedupe re-reads,
 * and the look-back floor (oldest device's last sync) back-fills outages.
 */
class PresenceSync extends Command
{
    protected $signature = 'hostelease:presence-sync';

    protected $description = 'Poll iDMS for gate punches, refresh device health, update presence state.';

    public function handle(PresenceDeviceAdapter $adapter, PresenceService $service): int
    {
        $devices = PresenceDevice::query()->where('is_active', true)->get();

        if ($devices->isEmpty()) {
            $this->info('Presence sync: no active devices.');

            return self::SUCCESS;
        }

        $this->refreshHealth($adapter, $devices);

        $serials = $devices->pluck('serial_number')->all();

        // Back-fill floor: the oldest device's last successful sync, so an outage
        // catches up automatically. Minus the overlap so nothing at the seam is
        // missed (the unique index makes the re-read free).
        $window = (int) config('presence.sync.window_minutes', 15);
        $overlap = (int) config('presence.sync.overlap_minutes', 10);

        $floor = $devices->pluck('last_synced_at')->filter()->min();
        $from = ($floor ?? now()->subMinutes($window))->copy()->subMinutes($overlap);
        $to = now();

        $stored = 0;
        try {
            $adapter->pullLogs($serials, $from, $to);
            $raw = $adapter->getPunches($from, $to);
            $stored = $service->ingest($raw);
        } catch (Throwable $e) {
            Log::error('Presence sync: punch ingest failed', ['error' => $e->getMessage()]);
            $this->error('Punch ingest failed: '.$e->getMessage());
        }

        // Advance each device's marker only after a successful pass.
        PresenceDevice::query()->whereIn('id', $devices->pluck('id'))
            ->update(['last_synced_at' => $to]);

        $flagged = $service->flagStaleProfiles();

        $this->info("Presence sync: {$stored} new punch(es), {$flagged} newly-stale profile(s), {$devices->count()} device(s).");

        return self::SUCCESS;
    }

    /**
     * Refresh the cached health of each device from GetDeviceList. iDMS
     * unreachable → the call returns empty and devices are marked Unknown, so
     * the boards show an honest "last synced X ago" instead of a false Online.
     */
    protected function refreshHealth(PresenceDeviceAdapter $adapter, $devices): void
    {
        try {
            $infos = $adapter->getDevices()->keyBy(fn (DeviceInfo $d) => $d->serial);
        } catch (Throwable $e) {
            Log::warning('Presence sync: GetDeviceList failed', ['error' => $e->getMessage()]);
            $infos = collect();
        }

        foreach ($devices as $device) {
            $info = $infos->get($device->serial_number);

            if (! $info) {
                $device->forceFill(['device_status' => DeviceStatus::Unknown])->save();

                continue;
            }

            $device->forceFill([
                'device_status' => $info->status,
                'last_connected_at' => $info->lastConnectedAt,
                'last_log_at' => $info->lastLogAt,
                'enrolled_count' => $info->userCount,
                'face_count' => $info->faceCount,
            ])->save();
        }
    }
}
