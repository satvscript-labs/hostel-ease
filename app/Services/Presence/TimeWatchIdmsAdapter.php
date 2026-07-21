<?php

namespace App\Services\Presence;

use App\Enums\Presence\DeviceStatus;
use App\Services\Presence\DTO\AdapterResult;
use App\Services\Presence\DTO\DeviceInfo;
use App\Services\Presence\DTO\DeviceUser;
use App\Services\Presence\DTO\RawPunch;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * TimeWatch iDMS HTTP adapter (04 §2). Maps our vendor-agnostic calls onto the
 * documented iDMS endpoints. Isolated behind PresenceDeviceAdapter so the
 * fingerprint variant (same API) and future vendors never ripple upward.
 *
 * Quirks handled here so nothing else sees them (04 §2):
 *  - GetPunchData dates are `yyyy-mm-dd`; PullLogs takes `yyyy-mm-dd HH:mm`.
 *  - A punch row's DeviceID is the SERIAL; GetDeviceList's DeviceID is a numeric
 *    row id — the serial is authoritative everywhere in our schema.
 */
class TimeWatchIdmsAdapter implements PresenceDeviceAdapter
{
    public function __construct(
        protected ?string $baseUrl,
        protected ?string $apiKey,
        protected int $timeout = 15,
        protected int $retries = 2,
    ) {
    }

    protected function client(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) $this->baseUrl, '/'))
            ->withHeaders(['X-Api-Key' => (string) $this->apiKey])
            ->timeout($this->timeout)
            ->retry($this->retries, 200);
    }

    /**
     * POST a body and return the decoded array, or null on transport/vendor
     * failure. A failure is logged and swallowed to null — callers degrade
     * (mark devices unknown, back-fill next run), never throw into the pipeline.
     */
    protected function post(string $endpoint, array $body): ?array
    {
        try {
            $res = $this->client()->post($endpoint, $body);
            if ($res->failed()) {
                Log::warning("iDMS {$endpoint} HTTP {$res->status()}", ['body' => $res->body()]);

                return null;
            }
            $json = $res->json();

            return is_array($json) ? $json : null;
        } catch (Throwable $e) {
            Log::warning("iDMS {$endpoint} threw", ['error' => $e->getMessage()]);

            return null;
        }
    }

    protected function toResult(?array $json): AdapterResult
    {
        if ($json === null) {
            return AdapterResult::fail('iDMS unreachable.');
        }

        return new AdapterResult(
            (bool) ($json['Success'] ?? false),
            $json['Message'] ?? null,
            $json['Data'] ?? null,
        );
    }

    public function getDevices(): Collection
    {
        $json = $this->post('/GetDeviceList', ['SerialNumber' => '']);
        $rows = $json['Data'] ?? [];

        return collect(is_array($rows) ? $rows : [])->map(fn ($d) => new DeviceInfo(
            serial: (string) ($d['SerialNumber'] ?? ''),
            name: $d['DeviceName'] ?? null,
            status: DeviceStatus::fromVendor($d['Status'] ?? null),
            lastConnectedAt: $this->parseDate($d['LastConnected'] ?? null),
            lastLogAt: $this->parseDate($d['LastLog'] ?? null),
            userCount: (int) ($d['UserCount'] ?? 0),
            faceCount: (int) ($d['FaceCount'] ?? 0),
            fingerCount: (int) ($d['FingerCount'] ?? 0),
        ))->filter(fn (DeviceInfo $d) => $d->serial !== '')->values();
    }

    public function addUser(DeviceUser $user, string $deviceSerial): AdapterResult
    {
        return $this->toResult($this->post('/AddUser', array_filter([
            'UserID' => $user->deviceUserId,
            'Name' => $user->name,
            'DeviceID' => $deviceSerial,
            'Card' => $user->card,
            'AccesstimeFrom' => $user->accessFrom,
            'AccesstimeTo' => $user->accessTo,
        ], fn ($v) => $v !== null)));
    }

    public function deleteUser(string $deviceUserId, string $deviceSerial): AdapterResult
    {
        return $this->toResult($this->post('/DeleteUser', [
            'UserID' => $deviceUserId,
            'DeviceID' => $deviceSerial,
        ]));
    }

    public function getPunches(CarbonInterface $from, CarbonInterface $to, ?string $deviceSerial = null): Collection
    {
        $json = $this->post('/GetPunchData', array_filter([
            'FromDate' => $from->format('Y-m-d'),
            'ToDate' => $to->format('Y-m-d'),
            'DeviceID' => $deviceSerial,
        ], fn ($v) => $v !== null));

        $rows = $json['Data'] ?? [];

        return collect(is_array($rows) ? $rows : [])->map(function ($p) {
            $at = $this->parseDate($p['PunchTime'] ?? null);
            if ($at === null) {
                return null;
            }

            return new RawPunch(
                deviceSerial: (string) ($p['DeviceID'] ?? ''),
                deviceUserId: (string) ($p['UserID'] ?? ''),
                punchedAt: $at,
                rawInOutMode: isset($p['InOutMode']) ? (string) $p['InOutMode'] : null,
                verifyMode: $p['VerifyMode'] ?? null,
            );
        })->filter(fn (?RawPunch $p) => $p !== null && $p->deviceSerial !== '' && $p->deviceUserId !== '')->values();
    }

    public function pullLogs(array $deviceSerials, CarbonInterface $from, CarbonInterface $to): AdapterResult
    {
        return $this->toResult($this->post('/PullLogs', [
            'DeviceList' => array_values($deviceSerials),
            'FromDate' => $from->format('Y-m-d H:i'),
            'ToDate' => $to->format('Y-m-d H:i'),
        ]));
    }

    public function syncTime(array $deviceSerials): AdapterResult
    {
        return $this->toResult($this->post('/SyncTime', [
            'DeviceList' => array_values($deviceSerials),
        ]));
    }

    public function verifyUserOnDevice(string $deviceUserId, string $deviceSerial): bool
    {
        $json = $this->post('/GetUserDetails', [
            'UserID' => $deviceUserId,
            'DeviceID' => $deviceSerial,
        ]);

        $rows = $json['Data'] ?? [];

        return is_array($rows) && collect($rows)->contains(
            fn ($r) => (string) ($r['UserID'] ?? '') === $deviceUserId
        );
    }

    protected function parseDate(?string $raw): ?CarbonInterface
    {
        if (empty($raw)) {
            return null;
        }

        try {
            return Carbon::parse($raw);
        } catch (Throwable) {
            return null;
        }
    }
}
