<?php

namespace App\Services\Presence;

use App\Enums\Presence\PresenceState;
use App\Services\Presence\DTO\RawPunch;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Presence S1 — translates one gate event from the Connector into a `RawPunch`.
 *
 * The Connector marshals the device SDK's `NET_ALARM_ACCESS_CTL_EVENT_INFO`
 * struct to JSON verbatim; this class is where those vendor field names stop
 * (04 §2.5, 10 §5). Nothing above it knows what "emAttendanceState" is.
 *
 * Two details carry real risk and are handled explicitly:
 *
 *  1. TIME IS UTC. The device stamps events in UTC (the vendor's own demo
 *     converts with GetZoneTimeByUTCTime). These units also ship set to
 *     UTC+08:00, so a naive read is wrong twice over. We parse as UTC and let
 *     Carbon convert — every duration, curfew check and report depends on it.
 *  2. DIRECTION IS SIX-VALUED, not two. `emAttendanceState` folds to our binary
 *     in/out below; anything unknown stays Unknown rather than guessing, and
 *     PresenceService then falls back to the device's own direction_mode.
 */
class SdkEventMapper
{
    /**
     * `EM_ATTENDANCESTATE` → our in/out (10 §6). The device's six-status
     * vocabulary maps cleanly: every "…In" means inside, every "…Out" means
     * outside.
     *
     *   0 UNKNOWN               → Unknown (let direction_mode decide)
     *   1 SIGNIN                → In      (Check In)
     *   2 GOOUT                 → Out     (Break Out)
     *   3 GOOUT_AND_RETRUN[sic] → In      (Break In — vendor's spelling)
     *   4 SIGNOUT               → Out     (Check Out)
     *   5 WORK_OVERTIME_SIGNIN  → In      (Overtime Check In)
     *   6 WORK_OVERTIME_SIGNOUT → Out     (Overtime Check Out)
     */
    public const ATTENDANCE_STATE = [
        1 => 'in',
        2 => 'out',
        3 => 'in',
        4 => 'out',
        5 => 'in',
        6 => 'out',
    ];

    /**
     * `EM_ACCESS_CTL_EVENT_TYPE` — the access controller's own verdict on which
     * way the person went. This is the PRIMARY direction source.
     *
     *   0 UNKNOWN · 1 ENTRY ("Get In") · 2 EXIT ("Get Out")
     *
     * Why this outranks `emAttendanceState` (proven on real hardware
     * 2026-09-09): attendance state is only populated when the unit is running
     * its ATTENDANCE engine (manual §3.6.5). On a gate configured purely as an
     * access controller — which is our deployment — every event arrives with
     * `emAttendanceState = 0` while `emEventType` correctly reports ENTRY/EXIT.
     * Reading only the attendance state would have thrown away the one field
     * the device actually fills in.
     */
    public const EVENT_TYPE = [
        1 => 'in',
        2 => 'out',
    ];

    /**
     * `EM_ACCESS_DOOROPEN_METHOD` → a human verify mode. Only the values a
     * hostel gate realistically produces are named; anything else is kept as a
     * raw code rather than being dropped, so an unexpected method is visible in
     * the log instead of silently blank.
     */
    public const OPEN_METHOD = [
        1 => 'Password',
        2 => 'Card',
        3 => 'Card + Password',
        4 => 'Password + Card',
        5 => 'Remote',
        6 => 'Fingerprint',
        10 => 'Password + Fingerprint',
        11 => 'Card + Fingerprint',
        15 => 'QR Code',
        16 => 'Face',
        18 => 'Face + ID Card',
        19 => 'ID Card + Face',
    ];

    /**
     * Map one event payload. Returns null when the event carries nothing we can
     * anchor a punch to (no serial, no user id, or an unparseable time) — the
     * caller reports it rather than storing a meaningless row.
     *
     * @param  array<string, mixed>  $event
     */
    public function map(array $event): ?RawPunch
    {
        $serial = trim((string) ($event['device_serial'] ?? ''));
        $userId = trim((string) ($event['device_user_id'] ?? ''));

        if ($serial === '' || $userId === '') {
            return null;
        }

        $punchedAt = $this->parseUtc($event['punched_at_utc'] ?? null);
        if (! $punchedAt) {
            return null;
        }

        return new RawPunch(
            deviceSerial: $serial,
            deviceUserId: $userId,
            punchedAt: $punchedAt,
            rawInOutMode: $this->direction($event),
            verifyMode: $this->verifyMode($event['open_method'] ?? null),
        );
    }

    /**
     * Parse the device's UTC stamp into an app-timezone Carbon instance.
     *
     * Accepts an ISO-8601 string (what the Connector sends). A string with no
     * explicit zone is READ AS UTC — never as local time — because that is what
     * the device means; assuming local here is exactly the bug that would shift
     * every punch by the app's offset.
     */
    protected function parseUtc(mixed $value): ?CarbonInterface
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value, 'UTC')->setTimezone(config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * The device's own direction verdict, or null when it didn't give one.
     *
     * Two fields can carry it, and which is populated depends on how the unit
     * is configured — so we read both, most-reliable first:
     *
     *   1. `event_type`      (EM_ACCESS_CTL_EVENT_TYPE)  ENTRY/EXIT
     *   2. `attendance_state`(EM_ATTENDANCESTATE)        the six shift statuses
     *
     * Null here is not a failure: PresenceService then falls back to the
     * device's configured `direction_mode`, which is why that column stays.
     *
     * @param  array<string, mixed>  $event
     */
    protected function direction(array $event): ?string
    {
        $eventType = $event['event_type'] ?? null;
        if (is_numeric($eventType) && isset(self::EVENT_TYPE[(int) $eventType])) {
            return self::EVENT_TYPE[(int) $eventType];
        }

        $state = $event['attendance_state'] ?? null;
        if (is_numeric($state) && isset(self::ATTENDANCE_STATE[(int) $state])) {
            return self::ATTENDANCE_STATE[(int) $state];
        }

        return null;
    }

    /** Human label for how they verified, or the raw code when unrecognised. */
    protected function verifyMode(mixed $method): ?string
    {
        if ($method === null || $method === '' || ! is_numeric($method)) {
            return null;
        }

        $code = (int) $method;

        return self::OPEN_METHOD[$code] ?? "Method {$code}";
    }

    /**
     * Is this event one we should store at all? A device that was denied entry
     * still produced a real scan, so `granted=false` is NOT filtered here — the
     * register is a record of what happened. This exists as the single place to
     * add such a rule if the owner ever wants one.
     */
    public function shouldStore(array $event): bool
    {
        return true;
    }

    /** Does this timestamp sit inside the sane window? (config-driven, 04 §6) */
    public function isPlausible(CarbonInterface $at): bool
    {
        $future = (int) config('presence.connector.max_future_minutes', 10);
        $age = (int) config('presence.connector.max_age_days', 30);

        return $at->lte(now()->addMinutes($future))
            && $at->gte(now()->subDays($age));
    }
}
