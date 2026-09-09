<?php

namespace App\Services\Presence;

use App\Enums\Presence\DeviceDirectionMode;
use App\Enums\Presence\PresenceState;
use App\Enums\Presence\PunchSource;
use App\Models\PresenceDevice;
use App\Models\PresenceProfile;
use App\Models\PresencePunch;
use App\Services\Presence\DTO\RawPunch;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * The presence engine (01 §4/§5). Pure domain logic over the adapter seam:
 *
 *   ingest → dedupe → match profile → resolve direction → update state → flag
 *
 * Transport-agnostic on purpose: the sync command feeds it polled punches
 * today; a future webhook would feed the same ingest() with no change here.
 * Direction resolution supports BOTH topologies (owner Q2) via the device's
 * direction_mode — nothing here assumes a single-toggle gate.
 */
class PresenceService
{
    /**
     * Ingest a batch of raw punches (already fetched from an adapter). Returns
     * the number of NEW punches stored. Idempotent: re-delivered punches hit the
     * unique index and are skipped, so overlap windows and re-runs are free.
     *
     * @param  Collection<int, RawPunch>  $rawPunches
     */
    public function ingest(Collection $rawPunches): int
    {
        // Resolve each raw punch's device once, grouped by serial.
        $devices = PresenceDevice::query()
            ->whereIn('serial_number', $rawPunches->pluck('deviceSerial')->unique()->all())
            ->get()
            ->keyBy('serial_number');

        $stored = 0;

        // Process per profile in chronological order so toggle/consecutive logic
        // sees punches in the order they physically happened, not fetch order.
        $ordered = $rawPunches
            ->filter(fn (RawPunch $p) => $devices->has($p->deviceSerial))
            ->sortBy(fn (RawPunch $p) => $p->punchedAt->getTimestamp())
            ->values();

        foreach ($ordered as $raw) {
            $device = $devices->get($raw->deviceSerial);
            if ($this->storePunch($raw, $device)) {
                $stored++;
            }
        }

        return $stored;
    }

    /**
     * Store one punch idempotently and, if new and matched, advance the
     * profile's state. Returns true if a new row was written.
     */
    protected function storePunch(RawPunch $raw, PresenceDevice $device): bool
    {
        $profile = PresenceProfile::query()
            ->where('hostel_id', $device->hostel_id)
            ->where('device_user_id', $raw->deviceUserId)
            ->first();

        // Resolve direction BEFORE insert. For toggle this reads the profile's
        // current state, so it must run against the state as it stands now.
        $direction = $this->resolveDirection($raw, $device, $profile);

        $existing = PresencePunch::query()
            ->where('presence_device_id', $device->id)
            ->where('device_user_id', $raw->deviceUserId)
            ->where('punched_at', $raw->punchedAt)
            ->exists();

        if ($existing) {
            return false; // idempotent no-op (re-poll / overlap / re-delivery)
        }

        $debounced = $this->isDebounced($raw, $device, $profile);

        $punch = PresencePunch::create([
            'hostel_id' => $device->hostel_id,
            'presence_device_id' => $device->id,
            'presence_profile_id' => $profile?->id,
            'device_user_id' => $raw->deviceUserId,
            'punched_at' => $raw->punchedAt,
            // A debounced punch is stored for audit but marked Unknown and does
            // not flip state (a fumbled double-scan must not invert reality).
            'direction' => $debounced ? PresenceState::Unknown : $direction,
            'verify_mode' => $raw->verifyMode,
            'source' => PunchSource::Device,
        ]);

        if ($profile && ! $debounced) {
            $this->applyToState($profile, $punch);
        }

        return true;
    }

    /**
     * Resolve a punch's direction, in the documented order (01 §4):
     *
     *  1. The DEVICE'S OWN VERDICT (`rawInOutMode`) when it reported one. The
     *     unit runs its own attendance engine and stamps every real-time event
     *     with an in/out state; that is first-hand truth about which way the
     *     person walked, so it outranks our inference. (S1 — mapped from
     *     `emAttendanceState` by SdkEventMapper.)
     *  2. Otherwise the device's configured mode:
     *     - entry/exit → fixed direction,
     *     - toggle     → opposite of the person's current state.
     *
     * Step 2 is NOT redundant: historical records replayed after an outage carry
     * no attendance state (10 §7), and a device left in the wrong attendance
     * mode reports nothing usable. So both paths stay live.
     */
    protected function resolveDirection(RawPunch $raw, PresenceDevice $device, ?PresenceProfile $profile): PresenceState
    {
        $mode = $device->direction_mode;

        // 1. A fixed-direction unit: the ADMIN'S topology declaration wins.
        //
        //    ⚠️ Proven on real hardware 2026-09-09: this device stamps
        //    emEventType = ENTRY on EVERY event, regardless of its role. In the
        //    back-to-back topology (01 §5.2) the EXIT unit would therefore also
        //    claim ENTRY — so trusting the device over the admin's config would
        //    silently record every exit as an entry. The device does not know
        //    which side of the door it is on; the admin does.
        if ($mode instanceof DeviceDirectionMode && $mode->fixedDirection() !== null) {
            return $mode->fixedDirection();
        }

        // 2. Toggle means "one unit serves both ways — I can't tell from the
        //    hardware". A device that explicitly reports OUT is nonetheless
        //    giving us real information (it is distinguishing directions), so
        //    honour that; an ENTRY claim here is ignored as the device's default.
        if ($this->reportedDirection($raw) === PresenceState::Out) {
            return PresenceState::Out;
        }

        // 3. Toggle alternation needs somebody to alternate FROM. An unmatched
        //    punch has no profile, so there is no state to flip — and guessing
        //    "in" would be inventing a fact about a person we cannot even name.
        //
        //    Found in the field 2026-09-09: a quarantined device user produced
        //    12 consecutive "in" punches and 1 "out", which is physically
        //    impossible. `unknown` is the honest answer; rebuildState() ignores
        //    unknown-direction punches, so matching the person later derives
        //    their state from real evidence instead of our guesses (01 §4).
        if (! $profile) {
            return PresenceState::Unknown;
        }

        // 4. Otherwise flip from current state. A known person's first-ever
        //    punch is treated as "in" — they are arriving.
        return match ($profile->state) {
            PresenceState::In => PresenceState::Out,
            PresenceState::Out => PresenceState::In,
            PresenceState::Unknown => PresenceState::In,
        };
    }

    /**
     * The direction the device itself reported, or null when it said nothing
     * usable. Deliberately strict: only an explicit in/out counts, so an unknown
     * or garbled value falls through to the device-mode strategy rather than
     * being guessed at.
     */
    protected function reportedDirection(RawPunch $raw): ?PresenceState
    {
        $reported = is_string($raw->rawInOutMode) ? strtolower(trim($raw->rawInOutMode)) : null;

        return match ($reported) {
            'in' => PresenceState::In,
            'out' => PresenceState::Out,
            default => null,
        };
    }

    /**
     * Two punches by the same person within the debounce window collapse. Only
     * meaningful for a matched profile (we debounce a person, not a raw id).
     */
    protected function isDebounced(RawPunch $raw, PresenceDevice $device, ?PresenceProfile $profile): bool
    {
        if (! $profile) {
            return false;
        }

        $seconds = (int) config('presence.sync.debounce_seconds', 60);
        if ($seconds <= 0) {
            return false;
        }

        return PresencePunch::query()
            ->where('presence_profile_id', $profile->id)
            ->whereBetween('punched_at', [
                $raw->punchedAt->copy()->subSeconds($seconds),
                $raw->punchedAt->copy()->addSeconds($seconds),
            ])
            ->exists();
    }

    /**
     * Advance a profile's derived state from a newly stored punch — but only if
     * this punch is the profile's LATEST by punched_at. A late/out-of-order
     * buffered punch inserts into history yet must never overwrite a newer
     * state (01 §5). Also runs the consecutive-same-direction missed-punch
     * detector (the direction-aware half; the stale-duration half runs nightly).
     */
    protected function applyToState(PresenceProfile $profile, PresencePunch $punch): void
    {
        $latest = PresencePunch::query()
            ->where('presence_profile_id', $profile->id)
            ->orderByDesc('punched_at')
            ->orderByDesc('id')
            ->first();

        // Someone else (a newer punch) already owns the state — leave it.
        if (! $latest || $latest->id !== $punch->id) {
            return;
        }

        // Missed-punch (direction-aware): the punch before this one had the SAME
        // direction — an opposite scan went unrecorded between them. Flag it;
        // never fabricate the missing punch (register stays truthful, 01 §4).
        $previous = PresencePunch::query()
            ->where('presence_profile_id', $profile->id)
            ->where('id', '!=', $punch->id)
            ->orderByDesc('punched_at')
            ->orderByDesc('id')
            ->first();

        $missed = $previous
            && $previous->direction->isKnown()
            && $previous->direction === $punch->direction;

        $profile->forceFill([
            'state' => $punch->direction,
            'state_changed_at' => $punch->punched_at,
            'last_punch_id' => $punch->id,
            'has_missed_punch' => $missed,
        ])->save();
    }

    /**
     * The always-works half of the missed-punch detector (01 §4): flag every
     * enrolled profile that has been "inside" longer than the stale threshold.
     * Runs on the nightly tick; covers the pure-toggle gate where a missed scan
     * can't appear as two consecutive same-direction punches.
     *
     * Returns the number of profiles freshly flagged.
     */
    public function flagStaleProfiles(?int $hostelId = null): int
    {
        $hours = (int) config('presence.sync.stale_hours', 24);
        $threshold = now()->subHours($hours);

        return PresenceProfile::query()
            ->when($hostelId, fn ($q) => $q->where('hostel_id', $hostelId))
            ->where('state', PresenceState::In->value)
            ->where('has_missed_punch', false)
            ->whereNotNull('state_changed_at')
            ->where('state_changed_at', '<', $threshold)
            ->update(['has_missed_punch' => true]);
    }

    /**
     * Fully re-derive a profile's state from its punch log — the escape hatch
     * that proves the punches are the single source of truth (01 §3.2). Ignores
     * debounced/unknown punches; the latest known-direction punch wins.
     */
    public function rebuildState(PresenceProfile $profile): void
    {
        // The two most recent known-direction punches: the latest owns the state,
        // and whether the pair shares a direction re-derives the missed-punch
        // flag — so a manual correction that resolves a gap clears a stale flag
        // instead of leaving it stuck on the board (must mirror applyToState()).
        $known = PresencePunch::query()
            ->where('presence_profile_id', $profile->id)
            ->whereIn('direction', [PresenceState::In->value, PresenceState::Out->value])
            ->orderByDesc('punched_at')
            ->orderByDesc('id')
            ->limit(2)->get();

        $latest = $known->first();

        if (! $latest) {
            $profile->forceFill([
                'state' => PresenceState::Unknown,
                'state_changed_at' => null,
                'last_punch_id' => null,
                'has_missed_punch' => false,
            ])->save();

            return;
        }

        $previous = $known->get(1);
        $missed = $previous !== null && $previous->direction === $latest->direction;

        $profile->forceFill([
            'state' => $latest->direction,
            'state_changed_at' => $latest->punched_at,
            'last_punch_id' => $latest->id,
            'has_missed_punch' => $missed,
        ])->save();
    }
}
