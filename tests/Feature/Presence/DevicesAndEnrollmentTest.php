<?php

namespace Tests\Feature\Presence;

use App\Enums\Presence\EnrollmentStatus;
use App\Enums\Presence\PresenceState;
use App\Models\Hostel;
use App\Models\PresenceDevice;
use App\Models\PresenceProfile;
use App\Models\PresencePunch;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use App\Services\Presence\FakePresenceAdapter;
use App\Services\Presence\PresenceDeviceAdapter;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Presence P2 — Devices & Enrollment. The page renders (catches Blade errors),
 * the RBAC allow-list holds, and every enrollment / device / quarantine action
 * works through the fake adapter.
 */
class DevicesAndEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    protected Hostel $hostel;
    protected User $admin;
    protected FakePresenceAdapter $fake;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hostel = Hostel::factory()->create();
        $this->admin = User::factory()->create(['hostel_id' => $this->hostel->id, 'role' => 'hostel_admin']);
        Tenant::set($this->hostel->id);

        $this->fake = new FakePresenceAdapter;
        $this->app->instance(PresenceDeviceAdapter::class, $this->fake);
    }

    protected function student(string $name = 'Amit'): Student
    {
        return Student::create([
            'hostel_id' => $this->hostel->id, 'name' => $name,
            'mobile' => fake()->unique()->numerify('9#########'),
            'occupation_type' => 'student', 'status' => 'active',
        ]);
    }

    protected function device(): PresenceDevice
    {
        return PresenceDevice::factory()->create(['hostel_id' => $this->hostel->id]);
    }

    // ── Render + RBAC ────────────────────────────────────────────────────

    public function test_the_devices_page_renders(): void
    {
        $this->device();
        $this->student();

        $this->actingAs($this->admin)->get(route('admin.presence.devices'))
            ->assertOk()
            ->assertSee('Devices & Enrollment')
            ->assertSee('Gate Terminals');
    }

    public function test_warden_and_manager_may_enter_but_accountant_and_viewer_may_not(): void
    {
        foreach (['manager', 'warden'] as $role) {
            $u = User::factory()->create(['hostel_id' => $this->hostel->id, 'role' => $role]);
            $this->actingAs($u)->get(route('admin.presence.devices'))->assertOk();
        }

        foreach (['accountant', 'viewer'] as $role) {
            $u = User::factory()->create(['hostel_id' => $this->hostel->id, 'role' => $role]);
            $this->actingAs($u)->get(route('admin.presence.devices'))
                ->assertRedirect(route('admin.dashboard'));
        }
    }

    public function test_the_sidebar_shows_presence_only_to_allowed_roles(): void
    {
        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertSee(route('admin.presence.devices'));

        $viewer = User::factory()->create(['hostel_id' => $this->hostel->id, 'role' => 'viewer']);
        $this->actingAs($viewer)->get(route('admin.dashboard'))
            ->assertDontSee(route('admin.presence.devices'));
    }

    // ── Device registry ──────────────────────────────────────────────────

    public function test_a_device_can_be_added_edited_and_removed(): void
    {
        $this->actingAs($this->admin)->post(route('admin.presence.devices.store'), [
            'serial_number' => 'TW-NEW-1', 'name' => 'Main Gate', 'direction_mode' => 'toggle',
        ])->assertRedirect();

        $device = PresenceDevice::firstOrFail();
        $this->assertSame('Main Gate', $device->name);
        $this->assertSame(26, strlen($device->public_id));

        $this->actingAs($this->admin)->put(route('admin.presence.devices.update', $device), [
            'name' => 'Front Gate', 'direction_mode' => 'entry', 'is_active' => '1',
        ])->assertRedirect();
        $this->assertSame('Front Gate', $device->fresh()->name);

        $this->actingAs($this->admin)->delete(route('admin.presence.devices.destroy', $device))->assertRedirect();
        $this->assertSoftDeleted($device);
    }

    public function test_sync_time_and_pull_logs_reach_the_adapter(): void
    {
        $device = $this->device();

        $this->actingAs($this->admin)->post(route('admin.presence.devices.sync-time', $device))->assertRedirect();
        $this->assertTrue($this->fake->called('syncTime'));

        $this->actingAs($this->admin)->post(route('admin.presence.devices.pull-logs', $device))->assertRedirect();
        $this->assertTrue($this->fake->called('pullLogs'));
    }

    // ── Enrollment ───────────────────────────────────────────────────────

    public function test_enrolling_a_student_pushes_to_the_device_and_creates_a_pending_profile(): void
    {
        $device = $this->device();
        $student = $this->student();

        $this->actingAs($this->admin)->post(route('admin.presence.enroll'), [
            'person_type' => 'student', 'person_id' => $student->public_id,
        ])->assertRedirect();

        $profile = $student->fresh()->presenceProfile;
        $this->assertNotNull($profile);
        $this->assertSame('S'.$student->id, $profile->device_user_id);
        $this->assertSame(EnrollmentStatus::Pending, $profile->enrollment_status);
        // AddUser was pushed to the branch's device.
        $this->assertTrue($this->fake->called('addUser'));
        $this->assertArrayHasKey('S'.$student->id, $this->fake->registered[$device->serial_number] ?? []);
    }

    public function test_reconcile_flips_pending_to_active_once_the_device_confirms(): void
    {
        $device = $this->device();
        $student = $this->student();

        $this->actingAs($this->admin)->post(route('admin.presence.enroll'), [
            'person_type' => 'student', 'person_id' => $student->public_id,
        ]);
        $this->assertSame(EnrollmentStatus::Pending, $student->fresh()->presenceProfile->enrollment_status);

        // Person registers their face → the device now reports them.
        $this->actingAs($this->admin)->post(route('admin.presence.reconcile'))->assertRedirect();

        $this->assertSame(EnrollmentStatus::Active, $student->fresh()->presenceProfile->enrollment_status);
    }

    public function test_revoking_removes_from_the_device_and_keeps_punch_history(): void
    {
        $device = $this->device();
        $student = $this->student();
        $this->actingAs($this->admin)->post(route('admin.presence.enroll'), [
            'person_type' => 'student', 'person_id' => $student->public_id,
        ]);
        $profile = $student->fresh()->presenceProfile;

        // A punch on the register before revoke.
        PresencePunch::create([
            'hostel_id' => $this->hostel->id, 'presence_device_id' => $device->id,
            'presence_profile_id' => $profile->id, 'device_user_id' => $profile->device_user_id,
            'punched_at' => now(), 'direction' => 'in', 'source' => 'device',
        ]);

        $this->actingAs($this->admin)->delete(route('admin.presence.profiles.revoke', $profile))->assertRedirect();

        $this->assertTrue($this->fake->called('deleteUser'));
        $this->assertSoftDeleted('presence_profiles', ['id' => $profile->id]);
        // History retained.
        $this->assertDatabaseHas('presence_punches', ['presence_profile_id' => $profile->id]);
    }

    public function test_bulk_floor_enroll_covers_students_on_that_floor(): void
    {
        $device = $this->device();
        $floor = \App\Models\Floor::create(['hostel_id' => $this->hostel->id, 'name' => 'Ground']);
        $room = \App\Models\Room::create(['hostel_id' => $this->hostel->id, 'floor_id' => $floor->id,
            'room_number' => '101', 'room_type' => 'non_ac', 'sharing' => 2, 'rent' => 5000]);
        app(\App\Services\BedGenerator::class)->sync($room);
        $student = $this->student('Floor Kid');
        app(\App\Services\BedAssignmentService::class)->assign($student, $room->beds()->first(), [
            'join_date' => now()->toDateString(), 'fee_amount' => 5000, 'fee_frequency' => 'monthly',
        ]);

        $this->actingAs($this->admin)->post(route('admin.presence.enroll.floor'), ['floor_id' => $floor->id])
            ->assertRedirect();

        $this->assertNotNull($student->fresh()->presenceProfile);
    }

    public function test_bulk_floor_enroll_rejects_a_floor_from_another_branch(): void
    {
        // A real floor id, but belonging to a DIFFERENT hostel. The validation
        // must reject it (not pass an unscoped exists, then fatal on a null find).
        $otherHostel = Hostel::factory()->create();
        $foreignFloor = \App\Models\Floor::create(['hostel_id' => $otherHostel->id, 'name' => 'Foreign']);

        $this->actingAs($this->admin)->post(route('admin.presence.enroll.floor'), ['floor_id' => $foreignFloor->id])
            ->assertSessionHasErrors('floor_id');
    }

    // ── Quarantine ───────────────────────────────────────────────────────

    public function test_matching_an_unmatched_id_binds_it_and_attaches_its_history(): void
    {
        $device = $this->device();
        $student = $this->student('Ghost Owner');

        // Two unmatched punches under a hand-enrolled id.
        foreach ([now()->subHours(3), now()->subHour()] as $at) {
            PresencePunch::create([
                'hostel_id' => $this->hostel->id, 'presence_device_id' => $device->id,
                'presence_profile_id' => null, 'device_user_id' => 'F9999',
                'punched_at' => $at, 'direction' => 'in', 'source' => 'device',
            ]);
        }

        $this->actingAs($this->admin)->post(route('admin.presence.quarantine.match'), [
            'device_user_id' => 'F9999', 'person_type' => 'student', 'person_id' => $student->public_id,
        ])->assertRedirect();

        $profile = $student->fresh()->presenceProfile;
        $this->assertNotNull($profile);
        // Both punches now belong to the person…
        $this->assertSame(2, PresencePunch::where('presence_profile_id', $profile->id)->count());
        $this->assertSame(0, PresencePunch::unmatched()->count());
        // …and state was re-derived from them.
        $this->assertSame(PresenceState::In, $profile->state);
    }
}
