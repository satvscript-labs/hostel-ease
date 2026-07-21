<?php

namespace Tests\Feature\Presence;

use App\Enums\Presence\DeviceStatus;
use App\Enums\Presence\PresenceState;
use App\Models\Hostel;
use App\Models\PresenceDevice;
use App\Models\PresenceProfile;
use App\Models\PresencePunch;
use App\Models\Student;
use App\Services\Presence\DTO\DeviceInfo;
use App\Services\Presence\DTO\RawPunch;
use App\Services\Presence\FakePresenceAdapter;
use App\Services\Presence\PresenceDeviceAdapter;
use App\Services\Presence\PresenceService;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Presence P1 — the engine, proven end-to-end through the fake adapter (no
 * hardware). Covers both gate topologies, idempotency, debounce, out-of-order
 * arrival, the dual missed-punch detector, tenancy, and the sync command.
 */
class PresencePipelineTest extends TestCase
{
    use RefreshDatabase;

    protected Hostel $hostel;
    protected PresenceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::clear(); // the pipeline runs outside tenant context, like the job
        $this->hostel = Hostel::factory()->create();
        $this->service = app(PresenceService::class);
    }

    protected function device(string $mode = 'toggle', ?string $serial = null): PresenceDevice
    {
        return PresenceDevice::factory()->create([
            'hostel_id' => $this->hostel->id,
            'direction_mode' => $mode,
            'serial_number' => $serial ?? 'TW-'.fake()->unique()->numerify('######'),
        ]);
    }

    protected function profile(PresenceDevice $device, string $userId = 'S1'): PresenceProfile
    {
        $student = Student::create([
            'hostel_id' => $this->hostel->id, 'name' => 'Resident '.$userId,
            'mobile' => fake()->unique()->numerify('9#########'),
            'occupation_type' => 'student', 'status' => 'active',
        ]);

        return PresenceProfile::factory()->forPerson($student)->create([
            'device_user_id' => $userId,
            'state' => PresenceState::Unknown,
        ]);
    }

    protected function punch(PresenceDevice $d, string $userId, Carbon $at, ?string $inOut = null): RawPunch
    {
        return new RawPunch($d->serial_number, $userId, $at, $inOut, 'Face');
    }

    // ── Idempotency ──────────────────────────────────────────────────────

    public function test_ingesting_the_same_batch_twice_stores_each_punch_once(): void
    {
        $d = $this->device('toggle');
        $this->profile($d, 'S1');
        $at = now()->startOfMinute();

        $batch = collect([$this->punch($d, 'S1', $at)]);

        $this->assertSame(1, $this->service->ingest($batch));
        $this->assertSame(0, $this->service->ingest($batch), 'A re-polled punch must not double-count.');
        $this->assertSame(1, PresencePunch::count());
    }

    // ── Toggle topology ──────────────────────────────────────────────────

    public function test_toggle_device_flips_state_in_out_in(): void
    {
        $d = $this->device('toggle');
        $p = $this->profile($d, 'S1');
        $t = now()->startOfHour();

        $this->service->ingest(collect([$this->punch($d, 'S1', $t)]));
        $this->assertSame(PresenceState::In, $p->fresh()->state);

        $this->service->ingest(collect([$this->punch($d, 'S1', $t->copy()->addHours(2))]));
        $this->assertSame(PresenceState::Out, $p->fresh()->state);

        $this->service->ingest(collect([$this->punch($d, 'S1', $t->copy()->addHours(4))]));
        $this->assertSame(PresenceState::In, $p->fresh()->state);
    }

    // ── Fixed-direction topology ─────────────────────────────────────────

    public function test_entry_and_exit_devices_set_a_fixed_direction(): void
    {
        $entry = $this->device('entry', 'TW-ENTRY');
        $exit = $this->device('exit', 'TW-EXIT');
        $p = $this->profile($entry, 'S1');
        $t = now()->startOfHour();

        $this->service->ingest(collect([$this->punch($entry, 'S1', $t)]));
        $this->assertSame(PresenceState::In, $p->fresh()->state);

        $this->service->ingest(collect([$this->punch($exit, 'S1', $t->copy()->addHour())]));
        $this->assertSame(PresenceState::Out, $p->fresh()->state);
    }

    // ── Quarantine ───────────────────────────────────────────────────────

    public function test_an_unmatched_device_id_is_stored_but_quarantined(): void
    {
        $d = $this->device('toggle');

        $stored = $this->service->ingest(collect([$this->punch($d, 'GHOST9', now())]));

        $this->assertSame(1, $stored);
        $punch = PresencePunch::firstOrFail();
        $this->assertNull($punch->presence_profile_id, 'Unmatched scans quarantine, never drop.');
        $this->assertSame('GHOST9', $punch->device_user_id);
    }

    // ── Debounce ─────────────────────────────────────────────────────────

    public function test_a_double_scan_within_the_debounce_window_does_not_flip_state(): void
    {
        config(['presence.sync.debounce_seconds' => 60]);
        $d = $this->device('toggle');
        $p = $this->profile($d, 'S1');
        $t = now()->startOfHour();

        $this->service->ingest(collect([$this->punch($d, 'S1', $t)]));
        $this->assertSame(PresenceState::In, $p->fresh()->state);

        // A fumbled second scan 5s later — stored, but must not invert to Out.
        $this->service->ingest(collect([$this->punch($d, 'S1', $t->copy()->addSeconds(5))]));

        $this->assertSame(PresenceState::In, $p->fresh()->state);
        $this->assertSame(2, PresencePunch::count(), 'The debounced punch is still recorded for audit.');
        $this->assertSame(PresenceState::Unknown, PresencePunch::latest('id')->first()->direction);
    }

    // ── Out-of-order arrival ─────────────────────────────────────────────

    public function test_a_late_arriving_punch_does_not_overwrite_a_newer_state(): void
    {
        $entry = $this->device('entry', 'TW-IN');
        $exit = $this->device('exit', 'TW-OUT');
        $p = $this->profile($entry, 'S1');
        $t = now()->startOfHour();

        // Newest state first: they are currently OUT (exit at t+2h).
        $this->service->ingest(collect([$this->punch($exit, 'S1', $t->copy()->addHours(2))]));
        $this->assertSame(PresenceState::Out, $p->fresh()->state);

        // A buffered ENTRY from t (earlier) arrives late. It joins history but
        // must NOT flip the state back to In.
        $this->service->ingest(collect([$this->punch($entry, 'S1', $t)]));

        $this->assertSame(PresenceState::Out, $p->fresh()->state, 'Late punch overwrote a newer state.');
        $this->assertSame(2, PresencePunch::count());
    }

    // ── Missed-punch: direction-aware detector ───────────────────────────

    public function test_consecutive_same_direction_flags_a_missed_punch(): void
    {
        $entry = $this->device('entry', 'TW-IN');
        $p = $this->profile($entry, 'S1');
        $t = now()->startOfHour();

        // Two ENTRY punches in a row — they left without scanning out between.
        $this->service->ingest(collect([$this->punch($entry, 'S1', $t)]));
        $this->assertFalse($p->fresh()->has_missed_punch);

        $this->service->ingest(collect([$this->punch($entry, 'S1', $t->copy()->addHours(3))]));

        $p->refresh();
        $this->assertSame(PresenceState::In, $p->state);
        $this->assertTrue($p->has_missed_punch, 'A missed OUT between two INs must be flagged.');
        // …and never fabricated into the register.
        $this->assertSame(2, PresencePunch::count());
    }

    // ── Missed-punch: stale-duration detector ────────────────────────────

    public function test_a_profile_inside_past_the_threshold_is_flagged_stale(): void
    {
        config(['presence.sync.stale_hours' => 24]);
        $d = $this->device('toggle');
        $p = $this->profile($d, 'S1');

        // Entered 2 days ago, never scanned out.
        $this->service->ingest(collect([$this->punch($d, 'S1', now()->subDays(2))]));
        $this->assertSame(PresenceState::In, $p->fresh()->state);
        $this->assertFalse($p->fresh()->has_missed_punch);

        $flagged = $this->service->flagStaleProfiles();

        $this->assertSame(1, $flagged);
        $this->assertTrue($p->fresh()->has_missed_punch);
    }

    // ── Rebuild from the log (single source of truth) ────────────────────

    public function test_state_can_be_fully_rebuilt_from_the_punch_log(): void
    {
        $entry = $this->device('entry', 'TW-IN');
        $exit = $this->device('exit', 'TW-OUT');
        $p = $this->profile($entry, 'S1');
        $t = now()->startOfHour();

        $this->service->ingest(collect([
            $this->punch($entry, 'S1', $t),
            $this->punch($exit, 'S1', $t->copy()->addHour()),
        ]));

        // Corrupt the cached state, then rebuild purely from punches.
        $p->forceFill(['state' => PresenceState::Unknown, 'last_punch_id' => null])->save();
        $this->service->rebuildState($p->fresh());

        $this->assertSame(PresenceState::Out, $p->fresh()->state);
    }

    public function test_rebuild_after_a_correction_clears_a_stale_missed_flag(): void
    {
        $entry = $this->device('entry', 'TW-IN');
        $p = $this->profile($entry, 'S1');

        // Entered 2 days ago, never scanned out → flagged stale by the detector.
        $this->service->ingest(collect([$this->punch($entry, 'S1', now()->subDays(2))]));
        $this->service->flagStaleProfiles();
        $this->assertTrue($p->fresh()->has_missed_punch);

        // A manual OUT correction lands in the log and rebuild runs: the gap is
        // now resolved, so the stale flag must clear (not linger on the board).
        PresencePunch::create([
            'hostel_id' => $this->hostel->id,
            'presence_profile_id' => $p->id,
            'device_user_id' => 'S1',
            'punched_at' => now(),
            'direction' => PresenceState::Out,
            'source' => \App\Enums\Presence\PunchSource::Manual,
        ]);
        $this->service->rebuildState($p->fresh());

        $this->assertSame(PresenceState::Out, $p->fresh()->state);
        $this->assertFalse($p->fresh()->has_missed_punch, 'A correction that resolves the gap must clear the stale flag.');
    }

    // ── Tenancy ──────────────────────────────────────────────────────────

    public function test_a_device_user_id_matches_only_within_its_own_hostel(): void
    {
        $other = Hostel::factory()->create();

        // Same device_user_id string 'S1' in two hostels.
        $dA = $this->device('toggle', 'TW-A');
        $pA = $this->profile($dA, 'S1');

        $dB = PresenceDevice::factory()->create(['hostel_id' => $other->id, 'direction_mode' => 'toggle', 'serial_number' => 'TW-B']);
        $studentB = Student::create(['hostel_id' => $other->id, 'name' => 'B', 'mobile' => '9111111111', 'occupation_type' => 'student', 'status' => 'active']);
        $pB = PresenceProfile::factory()->forPerson($studentB)->create(['device_user_id' => 'S1', 'state' => PresenceState::Unknown]);

        // A punch on hostel B's device must bind to B's profile, never A's.
        $this->service->ingest(collect([$this->punch($dB, 'S1', now())]));

        $this->assertSame(PresenceState::Unknown, $pA->fresh()->state, 'Cross-tenant leak: hostel A profile moved.');
        $this->assertSame(PresenceState::In, $pB->fresh()->state);
        $this->assertSame($pB->id, PresencePunch::firstOrFail()->presence_profile_id);
    }

    // ── The sync command, end-to-end ─────────────────────────────────────

    public function test_the_sync_command_refreshes_health_and_ingests_punches(): void
    {
        $fake = new FakePresenceAdapter;
        $this->app->instance(PresenceDeviceAdapter::class, $fake);

        $d = $this->device('toggle', 'TW-CMD');
        $p = $this->profile($d, 'S1');

        $fake->registerDevice(new DeviceInfo(
            serial: 'TW-CMD', name: 'Main Gate', status: DeviceStatus::Online,
            lastConnectedAt: now(), lastLogAt: now(), userCount: 12, faceCount: 12,
        ));
        $fake->pushPunch($this->punch($d, 'S1', now()->subMinutes(2)));

        $this->artisan('hostelease:presence-sync')->assertSuccessful();

        $d->refresh();
        $this->assertSame(DeviceStatus::Online, $d->device_status);
        $this->assertSame(12, $d->enrolled_count);
        $this->assertNotNull($d->last_synced_at);
        $this->assertSame(PresenceState::In, $p->fresh()->state);
        $this->assertTrue($fake->called('pullLogs'));
        $this->assertTrue($fake->called('getPunches'));
    }

    public function test_the_sync_command_is_a_noop_with_no_active_devices(): void
    {
        $fake = new FakePresenceAdapter;
        $this->app->instance(PresenceDeviceAdapter::class, $fake);

        $this->artisan('hostelease:presence-sync')->assertSuccessful();

        $this->assertFalse($fake->called('getPunches'));
    }
}
