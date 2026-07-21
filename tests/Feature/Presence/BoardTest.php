<?php

namespace Tests\Feature\Presence;

use App\Enums\Presence\EnrollmentStatus;
use App\Enums\Presence\PresenceState;
use App\Models\Hostel;
use App\Models\PresenceProfile;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Presence P3 — the live boards. Render, filters, stats, the muster, and
 * tenancy. State is set directly on profiles (the pipeline that produces it is
 * proven in PresencePipelineTest); here we test the board that reads it.
 */
class BoardTest extends TestCase
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
    }

    protected function enrolledStudent(string $name, PresenceState $state, $since = null): Student
    {
        $s = Student::create([
            'hostel_id' => $this->hostel->id, 'name' => $name,
            'mobile' => fake()->unique()->numerify('9#########'),
            'occupation_type' => 'student', 'status' => 'active',
        ]);
        PresenceProfile::factory()->forPerson($s)->create([
            'device_user_id' => 'S'.$s->id,
            'enrollment_status' => EnrollmentStatus::Active,
            'state' => $state,
            'state_changed_at' => $since ?? now()->subHours(2),
        ]);

        return $s;
    }

    // ── Render ───────────────────────────────────────────────────────────

    public function test_the_student_board_renders_with_enrolled_people(): void
    {
        $this->enrolledStudent('Ravi Out', PresenceState::Out);
        $this->enrolledStudent('Meena Inside', PresenceState::In);

        $this->actingAs($this->admin)->get(route('admin.presence.students'))
            ->assertOk()
            ->assertSee('Student Presence')
            ->assertSee('Currently Out')
            ->assertSee('Ravi Out')
            ->assertSee('Meena Inside');
    }

    public function test_the_staff_board_renders_separately(): void
    {
        $staff = Staff::create(['hostel_id' => $this->hostel->id, 'name' => 'Warden Singh',
            'designation' => 'Warden', 'mobile' => '9800000001', 'monthly_salary' => 15000, 'is_active' => true]);
        PresenceProfile::factory()->forPerson($staff)->create([
            'device_user_id' => 'T'.$staff->id, 'enrollment_status' => EnrollmentStatus::Active,
            'state' => PresenceState::In, 'state_changed_at' => now()->subHour(),
        ]);

        $this->actingAs($this->admin)->get(route('admin.presence.staff'))
            ->assertOk()
            ->assertSee('Staff Presence')
            ->assertSee('Warden Singh')
            ->assertSee('On Premises');
    }

    // ── Stats ────────────────────────────────────────────────────────────

    public function test_stats_count_inside_out_unknown_and_not_enrolled(): void
    {
        $this->enrolledStudent('A', PresenceState::Out);
        $this->enrolledStudent('B', PresenceState::Out);
        $this->enrolledStudent('C', PresenceState::In);
        $this->enrolledStudent('D', PresenceState::Unknown);
        // One active student left un-enrolled.
        Student::create(['hostel_id' => $this->hostel->id, 'name' => 'Not Enrolled',
            'mobile' => '9700000000', 'occupation_type' => 'student', 'status' => 'active']);

        $stats = $this->actingAs($this->admin)->get(route('admin.presence.students'))
            ->assertOk()->viewData('stats');

        $this->assertSame(2, $stats['out']);
        $this->assertSame(1, $stats['inside']);
        $this->assertSame(1, $stats['unknown']);
        $this->assertSame(1, $stats['not_enrolled']);
    }

    // ── Filters ──────────────────────────────────────────────────────────

    public function test_status_filter_narrows_to_out_only(): void
    {
        $this->enrolledStudent('Outside Person', PresenceState::Out);
        $this->enrolledStudent('Inside Person', PresenceState::In);

        $res = $this->actingAs($this->admin)->get(route('admin.presence.students', ['status' => 'out']))->assertOk();
        $res->assertSee('Outside Person');
        $res->assertDontSee('Inside Person');
    }

    public function test_search_matches_by_name(): void
    {
        $this->enrolledStudent('Findable Kumar', PresenceState::Out);
        $this->enrolledStudent('Other Person', PresenceState::Out);

        $res = $this->actingAs($this->admin)->get(route('admin.presence.students', ['search' => 'Findable']))->assertOk();
        $res->assertSee('Findable Kumar');
        $res->assertDontSee('Other Person');
    }

    public function test_default_sort_puts_longest_out_first(): void
    {
        $this->enrolledStudent('Recently Out', PresenceState::Out, now()->subMinutes(10));
        $this->enrolledStudent('Long Gone', PresenceState::Out, now()->subDays(1));

        $profiles = $this->actingAs($this->admin)->get(route('admin.presence.students'))
            ->assertOk()->viewData('profiles');

        // Longest-out (oldest state_changed_at) is row one.
        $this->assertSame('Long Gone', $profiles->first()->presenceable->name);
    }

    // ── Muster ───────────────────────────────────────────────────────────

    public function test_the_muster_lists_only_people_currently_inside(): void
    {
        $this->enrolledStudent('Inside Now', PresenceState::In);
        $this->enrolledStudent('Gone Out', PresenceState::Out);
        $this->enrolledStudent('Uncertain One', PresenceState::Unknown);

        $res = $this->actingAs($this->admin)->get(route('admin.presence.muster', ['type' => 'students']))->assertOk();
        $res->assertSee('Evacuation Muster');
        $res->assertSee('Inside Now');
        $res->assertSee('Uncertain One'); // surfaced separately as uncertain
        $res->assertDontSee('Gone Out');  // out people are not on the muster
    }

    // ── Tenancy ──────────────────────────────────────────────────────────

    public function test_a_board_only_shows_its_own_hostels_people(): void
    {
        $this->enrolledStudent('Mine', PresenceState::Out);

        // Another hostel with its own enrolled student.
        $other = Hostel::factory()->create();
        Tenant::set($other->id);
        $theirs = Student::create(['hostel_id' => $other->id, 'name' => 'Theirs',
            'mobile' => '9666666666', 'occupation_type' => 'student', 'status' => 'active']);
        PresenceProfile::factory()->forPerson($theirs)->create([
            'device_user_id' => 'S'.$theirs->id, 'enrollment_status' => EnrollmentStatus::Active,
            'state' => PresenceState::Out, 'state_changed_at' => now(),
        ]);
        Tenant::set($this->hostel->id);

        $res = $this->actingAs($this->admin)->get(route('admin.presence.students'))->assertOk();
        $res->assertSee('Mine');
        $res->assertDontSee('Theirs');
    }

    // ── Access ───────────────────────────────────────────────────────────

    public function test_boards_respect_the_presence_allow_list(): void
    {
        $viewer = User::factory()->create(['hostel_id' => $this->hostel->id, 'role' => 'viewer']);
        $this->actingAs($viewer)->get(route('admin.presence.students'))->assertRedirect(route('admin.dashboard'));
        $this->actingAs($viewer)->get(route('admin.presence.staff'))->assertRedirect(route('admin.dashboard'));
    }
}
