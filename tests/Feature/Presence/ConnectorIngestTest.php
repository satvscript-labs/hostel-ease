<?php

namespace Tests\Feature\Presence;

use App\Enums\Presence\EnrollmentStatus;
use App\Enums\Presence\PresenceState;
use App\Models\Hostel;
use App\Models\PresenceDevice;
use App\Models\PresenceProfile;
use App\Models\PresencePunch;
use App\Models\Student;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Presence S1 — the Connector ingest contract.
 *
 * Proves the whole inbound path with NO hardware: a signed SDK-shaped payload
 * arrives, is authenticated, mapped (direction fold + UTC conversion), and lands
 * as a punch that flips a board's state — plus every way we refuse bad input.
 */
class ConnectorIngestTest extends TestCase
{
    use RefreshDatabase;

    protected const SECRET = 'test-connector-secret';
    protected const SERIAL = 'TW60000324000187';

    protected Hostel $hostel;
    protected PresenceDevice $device;

    protected function setUp(): void
    {
        parent::setUp();
        config(['presence.connector.secret' => self::SECRET]);

        $this->hostel = Hostel::factory()->create();
        Tenant::set($this->hostel->id);
        $this->device = PresenceDevice::factory()->create([
            'hostel_id' => $this->hostel->id,
            'serial_number' => self::SERIAL,
            'direction_mode' => 'toggle',
        ]);
        // The endpoint runs tenant-less, like the Connector calling in.
        Tenant::clear();
    }

    /** A student enrolled on the device, keyed by the id the device reports. */
    protected function profile(string $deviceUserId = 'S412'): PresenceProfile
    {
        Tenant::set($this->hostel->id);
        $student = Student::create([
            'hostel_id' => $this->hostel->id, 'name' => 'Karan Mehta',
            'mobile' => fake()->unique()->numerify('9#########'),
            'occupation_type' => 'student', 'status' => 'active',
        ]);
        $profile = PresenceProfile::factory()->forPerson($student)->create([
            'device_user_id' => $deviceUserId,
            'enrollment_status' => EnrollmentStatus::Active,
            'state' => PresenceState::Unknown,
        ]);
        Tenant::clear();

        return $profile;
    }

    /** POST a payload with a valid signature (or an explicitly bad one). */
    protected function send(array $payload, ?string $signature = null)
    {
        $raw = json_encode($payload);
        $signature ??= hash_hmac('sha256', $raw, self::SECRET);

        return $this->call(
            'POST', route('api.presence.events'), [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PRESENCE_SIGNATURE' => $signature],
            $raw
        );
    }

    protected function event(array $overrides = []): array
    {
        return array_merge([
            'device_serial' => self::SERIAL,
            'device_user_id' => 'S412',
            'punched_at_utc' => now()->utc()->toIso8601String(),
            'attendance_state' => 1,   // SIGNIN
            'open_method' => 16,       // FACE_RECOGNITION
            'granted' => true,
        ], $overrides);
    }

    // ── Happy path ───────────────────────────────────────────────────────

    public function test_a_signed_event_becomes_a_punch_and_flips_state(): void
    {
        $profile = $this->profile();

        $this->send($this->event())
            ->assertOk()
            ->assertJson(['received' => 1, 'accepted' => 1, 'stored' => 1]);

        $this->assertDatabaseHas('presence_punches', [
            'presence_profile_id' => $profile->id,
            'device_user_id' => 'S412',
            'direction' => 'in',
            'verify_mode' => 'Face',
        ]);
        $this->assertSame(PresenceState::In, $profile->fresh()->state);
    }

    public function test_a_batch_is_accepted_in_one_post(): void
    {
        $this->profile();
        $t = now()->utc();

        $this->send(['events' => [
            $this->event(['punched_at_utc' => $t->copy()->subMinutes(10)->toIso8601String(), 'attendance_state' => 1]),
            $this->event(['punched_at_utc' => $t->copy()->subMinutes(5)->toIso8601String(), 'attendance_state' => 4]),
        ]])->assertOk()->assertJson(['received' => 2, 'stored' => 2]);

        $this->assertSame(2, PresencePunch::withoutGlobalScopes()->count());
    }

    // ── The two risky mappings ───────────────────────────────────────────

    /**
     * The device's six-value attendance vocabulary must fold to our binary
     * in/out — getting this wrong marks residents as inside when they left.
     */
    /**
     * The six-value attendance vocabulary must fold correctly to in/out.
     *
     * Asserted on the MAPPER rather than through the pipeline: since the real
     * hardware finding (see the exit-unit test below), `direction_mode` is
     * authoritative for state, so a pipeline assertion here would be measuring
     * the device-mode strategy instead of the fold.
     */
    public function test_every_attendance_state_folds_to_the_right_direction(): void
    {
        $mapper = app(\App\Services\Presence\SdkEventMapper::class);
        $map = [1 => 'in', 2 => 'out', 3 => 'in', 4 => 'out', 5 => 'in', 6 => 'out'];

        foreach ($map as $state => $expected) {
            $punch = $mapper->map($this->event(['attendance_state' => $state, 'event_type' => 0]));

            $this->assertNotNull($punch, "attendance_state {$state} must still map");
            $this->assertSame($expected, $punch->rawInOutMode,
                "attendance_state {$state} must fold to {$expected}");
        }

        // 0 = UNKNOWN must NOT guess — it falls through to the device strategy.
        $this->assertNull($mapper->map($this->event(['attendance_state' => 0, 'event_type' => 0]))->rawInOutMode);
    }

    /** event_type (ENTRY/EXIT) is read, and outranks attendance_state. */
    public function test_event_type_is_parsed_and_outranks_attendance_state(): void
    {
        $mapper = app(\App\Services\Presence\SdkEventMapper::class);

        $this->assertSame('in', $mapper->map($this->event(['event_type' => 1, 'attendance_state' => 0]))->rawInOutMode);
        $this->assertSame('out', $mapper->map($this->event(['event_type' => 2, 'attendance_state' => 0]))->rawInOutMode);
        // Disagreement: the access-control verdict wins over the shift status.
        $this->assertSame('out', $mapper->map($this->event(['event_type' => 2, 'attendance_state' => 1]))->rawInOutMode);
    }

    /**
     * Device stamps are UTC. If we ever read them as local time, every punch
     * shifts by the app offset and every duration/curfew/report is wrong.
     */
    public function test_the_utc_timestamp_is_converted_to_app_timezone(): void
    {
        $this->profile();

        // Relative to today, NOT a fixed date: a hardcoded one eventually ages
        // past the plausibility window and the test fails for the wrong reason
        // (this happened — the guard correctly rejected a 46-day-old stamp).
        // 16:17:03Z is 21:47:03 in IST (+05:30).
        $utc = Carbon::now('UTC')->subDays(2)->setTime(16, 17, 3);
        $this->send($this->event(['punched_at_utc' => $utc->toIso8601String()]))->assertOk();

        $punch = PresencePunch::withoutGlobalScopes()->firstOrFail();
        $expected = $utc->copy()->setTimezone(config('app.timezone'));

        $this->assertSame($expected->format('Y-m-d H:i:s'), $punch->punched_at->format('Y-m-d H:i:s'));
    }

    /**
     * REAL-HARDWARE FINDING (2026-09-09): on a unit configured as a plain access
     * controller, `emAttendanceState` is ALWAYS 0 while `emEventType` correctly
     * reports ENTRY/EXIT. So event_type must win — reading only the attendance
     * state would discard the only field the device actually fills in.
     */
    /**
     * THE most important direction test, and it exists because of a real
     * hardware finding: the device stamps `emEventType = ENTRY` on EVERY event
     * regardless of which unit it is. In the back-to-back topology the EXIT
     * unit would therefore also claim ENTRY — so a device that says "ENTRY"
     * must NOT override an admin who configured that unit as the exit.
     */
    public function test_an_exit_unit_records_out_even_when_the_device_claims_entry(): void
    {
        $this->profile();
        Tenant::set($this->hostel->id);
        $this->device->forceFill(['direction_mode' => 'exit'])->save();
        Tenant::clear();

        // Exactly what the real device sends: ENTRY + attendance_state 0.
        $this->send($this->event(['event_type' => 1, 'attendance_state' => 0]))->assertOk();

        $this->assertSame('out', PresencePunch::withoutGlobalScopes()->value('direction')?->value,
            'The admin\'s topology must beat the device\'s always-ENTRY claim.');
    }

    /** On a toggle unit, an always-ENTRY claim must not defeat alternation. */
    public function test_a_toggle_unit_still_alternates_despite_an_entry_claim(): void
    {
        $p = $this->profile();
        $t = now()->utc()->subHours(2);

        // Two scans, both reporting ENTRY (what the hardware actually does).
        $this->send($this->event(['event_type' => 1, 'punched_at_utc' => $t->toIso8601String()]))->assertOk();
        $this->assertSame(PresenceState::In, $p->fresh()->state);

        $this->send($this->event([
            'event_type' => 1,
            'punched_at_utc' => $t->copy()->addMinutes(10)->toIso8601String(),
        ]))->assertOk();

        $this->assertSame(PresenceState::Out, $p->fresh()->state,
            'A toggle unit must alternate; the device always says ENTRY.');
    }

    /** An explicit EXIT on a toggle unit IS real information — honour it. */
    public function test_a_toggle_unit_honours_an_explicit_exit(): void
    {
        $p = $this->profile();
        $p->forceFill(['state' => PresenceState::Unknown])->save();

        $this->send($this->event(['event_type' => 2, 'attendance_state' => 0]))->assertOk();

        $this->assertSame('out', PresencePunch::withoutGlobalScopes()->value('direction')?->value);
    }

    /** An unknown attendance state must not guess — direction_mode decides. */
    public function test_an_unknown_attendance_state_falls_back_to_device_mode(): void
    {
        $this->profile();

        // direction_mode = toggle, first punch → treated as 'in' by the engine.
        $this->send($this->event(['attendance_state' => 0]))->assertOk();

        $this->assertSame('in', PresencePunch::withoutGlobalScopes()->value('direction')?->value);
    }

    // ── Security ─────────────────────────────────────────────────────────

    public function test_a_bad_signature_is_rejected(): void
    {
        $this->profile();

        $this->send($this->event(), 'sha256=deadbeef')->assertStatus(401);
        $this->assertSame(0, PresencePunch::withoutGlobalScopes()->count());
    }

    public function test_a_missing_signature_is_rejected(): void
    {
        $this->profile();

        $this->postJson(route('api.presence.events'), $this->event())->assertStatus(401);
        $this->assertSame(0, PresencePunch::withoutGlobalScopes()->count());
    }

    /** Fail closed: an unconfigured secret must never accept unsigned punches. */
    public function test_an_unconfigured_secret_rejects_everything(): void
    {
        $this->profile();
        config(['presence.connector.secret' => null]);

        $this->send($this->event())->assertStatus(401);
        $this->assertSame(0, PresencePunch::withoutGlobalScopes()->count());
    }

    /** A valid signature still cannot invent hardware we don't know. */
    public function test_an_unknown_device_serial_is_refused(): void
    {
        $this->profile();

        $this->send($this->event(['device_serial' => 'NOT-OUR-DEVICE']))
            ->assertOk()
            ->assertJson(['accepted' => 0, 'stored' => 0]);

        $this->assertSame(0, PresencePunch::withoutGlobalScopes()->count());
    }

    // ── Robustness ───────────────────────────────────────────────────────

    /** The Connector retries on failure — a replay must not double-count. */
    public function test_a_replayed_event_is_idempotent(): void
    {
        $this->profile();
        $event = $this->event();

        $this->send($event)->assertOk()->assertJson(['stored' => 1]);
        $this->send($event)->assertOk()->assertJson(['accepted' => 1, 'stored' => 0]);

        $this->assertSame(1, PresencePunch::withoutGlobalScopes()->count());
    }

    /** A device with a wildly wrong clock must not poison durations. */
    public function test_an_implausible_timestamp_is_refused(): void
    {
        $this->profile();

        $this->send(['events' => [
            $this->event(['punched_at_utc' => now()->utc()->addYear()->toIso8601String()]),
            $this->event(['punched_at_utc' => now()->utc()->subYears(2)->toIso8601String()]),
        ]])->assertOk()->assertJson(['accepted' => 0, 'stored' => 0]);

        $this->assertSame(0, PresencePunch::withoutGlobalScopes()->count());
    }

    public function test_unmappable_and_oversized_payloads_are_refused(): void
    {
        $this->profile();

        // Missing the user id → nothing to anchor a punch to.
        $this->send($this->event(['device_user_id' => '']))
            ->assertOk()->assertJson(['accepted' => 0]);

        // Over the configured batch cap.
        config(['presence.connector.max_batch' => 2]);
        $this->send(['events' => [$this->event(), $this->event(), $this->event()]])
            ->assertStatus(422);

        $this->assertSame(0, PresencePunch::withoutGlobalScopes()->count());
    }

    /**
     * FIELD FINDING 2026-09-09: an unmatched user on a toggle device produced
     * 12 consecutive "in" punches and 1 "out" — impossible. Toggle alternation
     * needs a profile to flip from; with none, "in" was being invented.
     * `unknown` is the honest answer.
     */
    public function test_an_unmatched_punch_on_a_toggle_device_is_direction_unknown(): void
    {
        // No profile for this device id at all.
        $t = now()->utc()->subHour();
        foreach ([0, 5, 10] as $i) {
            $this->send($this->event([
                'device_user_id' => 'STRANGER',
                'event_type' => 1,          // device always claims ENTRY
                'punched_at_utc' => $t->copy()->addMinutes($i)->toIso8601String(),
            ]))->assertOk();
        }

        $dirs = PresencePunch::withoutGlobalScopes()
            ->where('device_user_id', 'STRANGER')->pluck('direction')
            ->map(fn ($d) => $d->value)->all();

        $this->assertSame(['unknown', 'unknown', 'unknown'], $dirs,
            'We must not invent a direction for someone we cannot identify.');

        // Still stored for quarantine — never dropped.
        $this->assertCount(3, $dirs);
    }

    /** A known person on a toggle device still alternates normally. */
    public function test_a_known_person_on_a_toggle_device_still_alternates(): void
    {
        $p = $this->profile();
        $t = now()->utc()->subHour();

        $this->send($this->event(['punched_at_utc' => $t->toIso8601String()]))->assertOk();
        $this->assertSame(PresenceState::In, $p->fresh()->state);

        $this->send($this->event(['punched_at_utc' => $t->copy()->addMinutes(5)->toIso8601String()]))->assertOk();
        $this->assertSame(PresenceState::Out, $p->fresh()->state);
    }

    /** An id we've never enrolled is still recorded — quarantined, not dropped. */
    public function test_an_unmatched_user_id_is_still_stored_for_quarantine(): void
    {
        $this->send($this->event(['device_user_id' => 'F9999']))
            ->assertOk()->assertJson(['stored' => 1]);

        $this->assertDatabaseHas('presence_punches', [
            'device_user_id' => 'F9999',
            'presence_profile_id' => null,
        ]);
    }
}
