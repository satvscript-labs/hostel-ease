<?php

namespace Tests\Feature\Presence;

use App\Enums\Presence\EnrollmentStatus;
use App\Enums\Presence\PresenceState;
use App\Enums\Presence\PunchSource;
use App\Models\Hostel;
use App\Models\PresenceDevice;
use App\Models\PresenceProfile;
use App\Models\PresencePunch;
use App\Models\Student;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Presence P4 — Gate Log, per-person history drawer, manual corrections, and
 * the last-seen profile chip.
 */
class GateLogAndHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected Hostel $hostel;
    protected User $admin;
    protected PresenceDevice $device;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hostel = Hostel::factory()->create();
        $this->admin = User::factory()->create(['hostel_id' => $this->hostel->id, 'role' => 'hostel_admin']);
        Tenant::set($this->hostel->id);
        $this->device = PresenceDevice::factory()->create(['hostel_id' => $this->hostel->id, 'name' => 'Main Gate']);
    }

    protected function enrolled(string $name = 'Ravi'): PresenceProfile
    {
        $s = Student::create(['hostel_id' => $this->hostel->id, 'name' => $name,
            'mobile' => fake()->unique()->numerify('9#########'), 'occupation_type' => 'student', 'status' => 'active']);

        return PresenceProfile::factory()->forPerson($s)->create([
            'device_user_id' => 'S'.$s->id, 'enrollment_status' => EnrollmentStatus::Active,
            'state' => PresenceState::Out, 'state_changed_at' => now()->subHours(2),
        ]);
    }

    protected function punch(PresenceProfile $p, string $dir, $at, string $source = 'device'): PresencePunch
    {
        return PresencePunch::create([
            'hostel_id' => $this->hostel->id,
            'presence_device_id' => $source === 'manual' ? null : $this->device->id,
            'presence_profile_id' => $p->id, 'device_user_id' => $p->device_user_id,
            'punched_at' => $at, 'direction' => $dir, 'verify_mode' => 'Face', 'source' => $source,
        ]);
    }

    // ── Gate Log ─────────────────────────────────────────────────────────

    public function test_the_gate_log_renders_todays_punches(): void
    {
        $p = $this->enrolled('Log Person');
        $this->punch($p, 'in', now()->setTime(7, 42));
        $this->punch($p, 'out', now()->setTime(18, 10));

        $this->actingAs($this->admin)->get(route('admin.presence.log'))
            ->assertOk()
            ->assertSee('Gate Log')
            ->assertSee('Log Person')
            ->assertSee('07:42')
            ->assertSee('18:10');
    }

    public function test_the_gate_log_direction_filter_narrows_the_feed(): void
    {
        $p = $this->enrolled('Directional');
        $this->punch($p, 'in', now()->setTime(8, 0));
        $this->punch($p, 'out', now()->setTime(9, 0));

        $res = $this->actingAs($this->admin)->get(route('admin.presence.log', ['direction' => 'in']))->assertOk();
        $res->assertSee('08:00');
        $res->assertDontSee('09:00');
    }

    public function test_unmatched_filter_shows_only_quarantined_punches(): void
    {
        $p = $this->enrolled('Matched');
        $this->punch($p, 'in', now()->setTime(8, 0));
        // An unmatched punch (no profile).
        PresencePunch::create(['hostel_id' => $this->hostel->id, 'presence_device_id' => $this->device->id,
            'presence_profile_id' => null, 'device_user_id' => 'GHOST7', 'punched_at' => now()->setTime(9, 0),
            'direction' => 'in', 'source' => 'device']);

        // Assert on the feed data itself: "Matched" also appears in the Match
        // picker's option list (all active students), so a page-wide assertDontSee
        // would trip on the picker, not the feed.
        $feed = $this->actingAs($this->admin)->get(route('admin.presence.log', ['unmatched' => 1]))
            ->assertOk()->assertSee('GHOST7')->viewData('punches');

        $this->assertCount(1, $feed->items());
        $this->assertSame('GHOST7', $feed->first()->device_user_id);
        $this->assertNull($feed->first()->presence_profile_id);
    }

    public function test_the_gate_log_exports_csv(): void
    {
        $p = $this->enrolled('Csv Person');
        $this->punch($p, 'in', now()->setTime(7, 42));

        $res = $this->actingAs($this->admin)->get(route('admin.presence.log.export'));
        $res->assertOk();
        $res->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Csv Person', $res->streamedContent());
        $this->assertStringContainsString('Date,Time,Person', $res->streamedContent());
    }

    // ── History drawer ───────────────────────────────────────────────────

    public function test_the_history_drawer_body_renders_the_timeline(): void
    {
        $p = $this->enrolled('History Person');
        $this->punch($p, 'in', now()->setTime(7, 42));
        $this->punch($p, 'out', now()->setTime(19, 10));

        $this->actingAs($this->admin)->get(route('admin.presence.history', $p))
            ->assertOk()
            ->assertSee('History Person')
            ->assertSee('07:42')
            ->assertSee('19:10')
            ->assertSee('Entered')
            ->assertSee('Left');
    }

    // ── Manual correction ────────────────────────────────────────────────

    public function test_a_manual_correction_writes_a_device_less_punch_and_rebuilds_state(): void
    {
        $p = $this->enrolled('Correct Me');
        // Currently Out; correct them to Inside.
        $this->actingAs($this->admin)->post(route('admin.presence.history.correct', $p), [
            'direction' => 'in', 'reason' => 'Forgot to scan in',
        ])->assertRedirect();

        $punch = PresencePunch::where('presence_profile_id', $p->id)->where('source', PunchSource::Manual->value)->first();
        $this->assertNotNull($punch);
        $this->assertNull($punch->presence_device_id, 'A manual correction has no gate device.');
        $this->assertSame('Forgot to scan in', $punch->note);
        // State re-derived from the log → now Inside.
        $this->assertSame(PresenceState::In, $p->fresh()->state);
    }

    public function test_a_correction_requires_a_reason(): void
    {
        $p = $this->enrolled('No Reason');
        $this->actingAs($this->admin)->post(route('admin.presence.history.correct', $p), [
            'direction' => 'in',
        ])->assertSessionHasErrors('reason');
    }

    public function test_reset_puts_the_state_back_to_unknown(): void
    {
        $p = $this->enrolled('Reset Me');
        $this->actingAs($this->admin)->post(route('admin.presence.history.reset', $p))->assertRedirect();

        $this->assertSame(PresenceState::Unknown, $p->fresh()->state);
        $this->assertNull($p->fresh()->state_changed_at);
    }

    // ── Last-seen chip ───────────────────────────────────────────────────

    public function test_the_last_seen_chip_shows_on_an_enrolled_students_profile(): void
    {
        $p = $this->enrolled('Chip Kid');
        $student = $p->presenceable;

        $this->actingAs($this->admin)->get(route('admin.students.show', $student))
            ->assertOk()
            ->assertSee('ls-chip'); // the chip markup is present for an enrolled person
    }

    // ── Access ───────────────────────────────────────────────────────────

    public function test_gate_log_and_history_respect_the_allow_list(): void
    {
        $p = $this->enrolled();
        $viewer = User::factory()->create(['hostel_id' => $this->hostel->id, 'role' => 'viewer']);

        $this->actingAs($viewer)->get(route('admin.presence.log'))->assertRedirect(route('admin.dashboard'));
        $this->actingAs($viewer)->get(route('admin.presence.history', $p))->assertRedirect(route('admin.dashboard'));
    }
}
