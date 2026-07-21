<?php

namespace Tests\Feature\Presence;

use App\Enums\Presence\EnrollmentStatus;
use App\Enums\Presence\PresenceState;
use App\Models\Hostel;
use App\Models\Notification;
use App\Models\PresenceDevice;
use App\Models\PresenceProfile;
use App\Models\PresencePunch;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Presence P5 — curfew flags + warden alerts, the on-leave marker, the
 * dashboard tile, and the staff-attendance review bridge.
 */
class CurfewBridgeTest extends TestCase
{
    use RefreshDatabase;

    protected Hostel $hostel;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hostel = Hostel::factory()->create();
        $this->admin = User::factory()->create(['hostel_id' => $this->hostel->id, 'role' => 'hostel_admin']);
        Tenant::set($this->hostel->id);
        PresenceDevice::factory()->create(['hostel_id' => $this->hostel->id]);
    }

    protected function outStudent(string $name, $leaveUntil = null): PresenceProfile
    {
        $s = Student::create(['hostel_id' => $this->hostel->id, 'name' => $name,
            'mobile' => fake()->unique()->numerify('9#########'), 'occupation_type' => 'student', 'status' => 'active']);

        return PresenceProfile::factory()->forPerson($s)->create([
            'device_user_id' => 'S'.$s->id, 'enrollment_status' => EnrollmentStatus::Active,
            'state' => PresenceState::Out, 'state_changed_at' => now()->subHours(3),
            'on_leave_until' => $leaveUntil,
        ]);
    }

    /** A curfew window that currently contains "now" (handles midnight wrap). */
    protected function curfewCoveringNow(bool $notify = false): void
    {
        $this->hostel->forceFill([
            'curfew_from' => now()->subHours(2)->format('H:i'),
            'curfew_to' => now()->addHours(2)->format('H:i'),
            'curfew_notify' => $notify,
        ])->save();
    }

    // ── Curfew setting + late flags ──────────────────────────────────────

    public function test_saving_the_curfew_window_stores_it_and_the_toggle_clears_it(): void
    {
        $this->actingAs($this->admin)->post(route('admin.presence.curfew'), [
            'curfew_enabled' => '1', 'curfew_from' => '22:00', 'curfew_to' => '06:00', 'curfew_notify' => '1',
        ])->assertRedirect();

        $this->hostel->refresh();
        $this->assertSame('22:00', $this->hostel->curfew_from);
        $this->assertSame('06:00', $this->hostel->curfew_to);
        $this->assertTrue($this->hostel->curfew_notify);

        // Toggle OFF (unchecked) clears the whole curfew.
        $this->actingAs($this->admin)->post(route('admin.presence.curfew'), [
            'curfew_from' => '22:00', 'curfew_to' => '06:00',
        ])->assertRedirect();

        $this->hostel->refresh();
        $this->assertNull($this->hostel->curfew_from);
        $this->assertNull($this->hostel->curfew_to);
        $this->assertFalse($this->hostel->curfew_notify);
    }

    public function test_the_board_flags_students_out_within_the_curfew_window(): void
    {
        $this->curfewCoveringNow();
        $this->outStudent('Late One');
        $this->outStudent('Late Two');

        $stats = $this->actingAs($this->admin)->get(route('admin.presence.students'))
            ->assertOk()->viewData('stats');

        $this->assertSame(2, $stats['late']);
    }

    public function test_an_on_leave_student_is_not_counted_late(): void
    {
        $this->curfewCoveringNow();
        $this->outStudent('At Home', now()->addDays(2));  // on leave
        $this->outStudent('Actually Late');               // not

        $stats = $this->actingAs($this->admin)->get(route('admin.presence.students'))
            ->assertOk()->viewData('stats');

        $this->assertSame(1, $stats['late']);
    }

    public function test_no_late_flag_outside_the_curfew_window(): void
    {
        // A window that does NOT contain now (starts 2h from now).
        $this->hostel->forceFill([
            'curfew_from' => now()->addHours(2)->format('H:i'),
            'curfew_to' => now()->addHours(3)->format('H:i'),
        ])->save();
        $this->outStudent('Out But Not In Curfew');

        $stats = $this->actingAs($this->admin)->get(route('admin.presence.students'))->assertOk()->viewData('stats');
        $this->assertSame(0, $stats['late']);
    }

    public function test_curfew_never_flags_the_staff_board(): void
    {
        $this->curfewCoveringNow();
        $staff = Staff::create(['hostel_id' => $this->hostel->id, 'name' => 'Night Guard',
            'designation' => 'Guard', 'mobile' => '9800000077', 'monthly_salary' => 9000, 'is_active' => true]);
        PresenceProfile::factory()->forPerson($staff)->create([
            'device_user_id' => 'T'.$staff->id, 'enrollment_status' => EnrollmentStatus::Active,
            'state' => PresenceState::Out, 'state_changed_at' => now()->subHours(3),
        ]);

        $res = $this->actingAs($this->admin)->get(route('admin.presence.staff'))->assertOk();
        $this->assertSame(0, $res->viewData('stats')['late']);   // staff are never "late"
        $res->assertDontSee('Set curfew');                        // no curfew control on staff board
    }

    // ── On-leave marker ──────────────────────────────────────────────────

    public function test_marking_and_clearing_leave(): void
    {
        $p = $this->outStudent('Traveler');

        $this->actingAs($this->admin)->post(route('admin.presence.history.leave', $p), [
            'until' => now()->addDays(3)->toDateString(),
        ])->assertRedirect();
        $this->assertNotNull($p->fresh()->on_leave_until);
        $this->assertTrue($p->fresh()->isOnLeave());

        $this->actingAs($this->admin)->delete(route('admin.presence.history.leave.clear', $p))->assertRedirect();
        $this->assertNull($p->fresh()->on_leave_until);
    }

    // ── Curfew alert command ─────────────────────────────────────────────

    public function test_the_curfew_command_notifies_the_warden_once(): void
    {
        $this->curfewCoveringNow(notify: true);
        $this->outStudent('Still Out');

        $this->artisan('hostelease:presence-curfew-check')->assertSuccessful();

        $note = Notification::where('hostel_id', $this->hostel->id)->where('type', 'presence.curfew')->first();
        $this->assertNotNull($note);
        $this->assertStringContainsString('Still Out', $note->message);

        // Runs again → deduped (no second notification, already alerted today).
        $this->artisan('hostelease:presence-curfew-check')->assertSuccessful();
        $this->assertSame(1, Notification::where('type', 'presence.curfew')->count());
    }

    public function test_a_student_leaving_later_in_the_window_is_still_alerted(): void
    {
        $this->curfewCoveringNow(notify: true);

        // First check with nobody out yet: no alert — and the window must NOT be
        // marked notified, or a later leaver would be silenced for the whole window.
        $this->artisan('hostelease:presence-curfew-check')->assertSuccessful();
        $this->assertSame(0, Notification::where('type', 'presence.curfew')->count());
        $this->assertNull($this->hostel->fresh()->curfew_notified_at);

        // Someone leaves during the window; the next check must still alert them.
        $this->outStudent('Late Leaver');
        $this->artisan('hostelease:presence-curfew-check')->assertSuccessful();

        $note = Notification::where('type', 'presence.curfew')->first();
        $this->assertNotNull($note);
        $this->assertStringContainsString('Late Leaver', $note->message);
    }

    public function test_the_curfew_command_ignores_on_leave_students(): void
    {
        $this->curfewCoveringNow(notify: true);
        $this->outStudent('On Holiday', now()->addDays(2));

        $this->artisan('hostelease:presence-curfew-check')->assertSuccessful();

        $this->assertSame(0, Notification::where('type', 'presence.curfew')->count());
    }

    // ── Dashboard tile ───────────────────────────────────────────────────

    public function test_the_dashboard_exposes_the_currently_out_count(): void
    {
        $this->outStudent('Out A');
        $this->outStudent('Out B');

        $stats = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->viewData('stats');

        $this->assertTrue($stats['presence_configured']);
        $this->assertSame(2, $stats['presence_out']);
    }

    // ── Staff-attendance bridge ──────────────────────────────────────────

    public function test_staff_attendance_suggests_present_for_staff_seen_at_the_gate(): void
    {
        $device = PresenceDevice::where('hostel_id', $this->hostel->id)->first();
        $staff = Staff::create(['hostel_id' => $this->hostel->id, 'name' => 'Gate Guard',
            'designation' => 'Guard', 'mobile' => '9800000009', 'monthly_salary' => 9000, 'is_active' => true]);
        $profile = PresenceProfile::factory()->forPerson($staff)->create([
            'device_user_id' => 'T'.$staff->id, 'enrollment_status' => EnrollmentStatus::Active, 'state' => PresenceState::In,
        ]);
        PresencePunch::create(['hostel_id' => $this->hostel->id, 'presence_device_id' => $device->id,
            'presence_profile_id' => $profile->id, 'device_user_id' => $profile->device_user_id,
            'punched_at' => now()->setTime(9, 0), 'direction' => 'in', 'source' => 'device']);

        $attendance = $this->actingAs($this->admin)->get(route('admin.staff.index', ['tab' => 'attendance']))
            ->assertOk()->viewData('attendance');

        $today = now()->toDateString();
        $this->assertArrayHasKey($today, $attendance['suggested']);
        $this->assertArrayHasKey((string) $staff->id, $attendance['suggested'][$today]);
    }
}
