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
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Presence P6 — the reports (Time Out, Late Returns, Nights Out, Staff Hours),
 * plugged into the Reports area and gated to presence-access roles.
 */
class ReportsTest extends TestCase
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
        $this->device = PresenceDevice::factory()->create(['hostel_id' => $this->hostel->id]);
    }

    protected function student(string $name): PresenceProfile
    {
        $s = Student::create(['hostel_id' => $this->hostel->id, 'name' => $name,
            'mobile' => fake()->unique()->numerify('9#########'), 'occupation_type' => 'student', 'status' => 'active']);

        return PresenceProfile::factory()->forPerson($s)->create([
            'device_user_id' => 'S'.$s->id, 'enrollment_status' => EnrollmentStatus::Active, 'state' => PresenceState::In,
        ]);
    }

    protected function punch(PresenceProfile $p, string $dir, $at): void
    {
        PresencePunch::create(['hostel_id' => $this->hostel->id, 'presence_device_id' => $this->device->id,
            'presence_profile_id' => $p->id, 'device_user_id' => $p->device_user_id,
            'punched_at' => $at, 'direction' => $dir, 'source' => 'device']);
    }

    // ── Time Out ─────────────────────────────────────────────────────────

    public function test_time_out_report_pairs_out_sessions_into_hours(): void
    {
        $p = $this->student('Wanderer');
        $day = now()->startOfDay();
        // Out 10:00 → In 13:00 = a 3-hour out session.
        $this->punch($p, 'out', $day->copy()->addHours(10));
        $this->punch($p, 'in', $day->copy()->addHours(13));

        $res = $this->actingAs($this->admin)->get(route('admin.reports.show', 'presence_time_out'))->assertOk();
        $data = $res->viewData('data');

        $this->assertCount(1, $data['rows']);
        [$name, $room, $sessions, $hours] = $data['rows'][0];
        $this->assertSame('Wanderer', $name);
        $this->assertSame(1, $sessions);
        $this->assertSame(3.0, $hours);
    }

    // ── Late Returns ─────────────────────────────────────────────────────

    public function test_late_returns_counts_ins_inside_the_curfew_window(): void
    {
        // Curfew window that currently includes an 'in' at (now).
        $this->hostel->forceFill([
            'curfew_from' => now()->subHour()->format('H:i'),
            'curfew_to' => now()->addHour()->format('H:i'),
        ])->save();

        $p = $this->student('Late Larry');
        $this->punch($p, 'in', now());                       // inside window → late return
        $this->punch($p, 'in', now()->subHours(6));          // outside window → not

        $data = $this->actingAs($this->admin)->get(route('admin.reports.show', 'presence_late'))
            ->assertOk()->viewData('data');

        $this->assertCount(1, $data['rows']);
        $this->assertSame(1, $data['rows'][0][2]); // one late return
    }

    public function test_late_returns_reports_when_no_curfew_is_set(): void
    {
        $this->student('Anyone');
        $data = $this->actingAs($this->admin)->get(route('admin.reports.show', 'presence_late'))
            ->assertOk()->viewData('data');

        $this->assertSame([], $data['rows']);
        $this->assertSame('Not set', $data['summary'][0][2]);
    }

    // ── Staff Hours ──────────────────────────────────────────────────────

    public function test_staff_hours_report_sums_time_on_premises(): void
    {
        $staff = Staff::create(['hostel_id' => $this->hostel->id, 'name' => 'On Duty',
            'designation' => 'Guard', 'mobile' => '9800000010', 'monthly_salary' => 9000, 'is_active' => true]);
        $sp = PresenceProfile::factory()->forPerson($staff)->create([
            'device_user_id' => 'T'.$staff->id, 'enrollment_status' => EnrollmentStatus::Active, 'state' => PresenceState::In,
        ]);
        $day = now()->startOfDay();
        $this->punch($sp, 'in', $day->copy()->addHours(9));
        $this->punch($sp, 'out', $day->copy()->addHours(17)); // 8 hours on premises

        $data = $this->actingAs($this->admin)->get(route('admin.reports.show', 'presence_staff_hours'))
            ->assertOk()->viewData('data');

        $this->assertCount(1, $data['rows']);
        $this->assertSame(8.0, $data['rows'][0][3]);
    }

    // ── Nights Out ───────────────────────────────────────────────────────

    public function test_nights_out_counts_overnight_absences(): void
    {
        $p = $this->student('Night Owl');
        // Out at 22:00, back at 09:00 next day (> 08:00) → one night out.
        $this->punch($p, 'out', now()->startOfDay()->addHours(22));
        $this->punch($p, 'in', now()->startOfDay()->addDay()->addHours(9));

        $data = $this->actingAs($this->admin)->get(route('admin.reports.show', 'presence_nights_out'))
            ->assertOk()->viewData('data');

        $this->assertCount(1, $data['rows']);
        $this->assertSame(1, $data['rows'][0][2]);
    }

    // ── Hub + RBAC ───────────────────────────────────────────────────────

    public function test_the_reports_hub_shows_presence_cards_to_allowed_roles(): void
    {
        $this->actingAs($this->admin)->get(route('admin.reports.index'))
            ->assertOk()->assertSee('Time Out')->assertSee('Presence');
    }

    public function test_presence_reports_are_hidden_and_blocked_for_non_presence_roles(): void
    {
        // accountant has reports access but NOT presence access.
        $acc = User::factory()->create(['hostel_id' => $this->hostel->id, 'role' => 'accountant']);

        $this->actingAs($acc)->get(route('admin.reports.index'))
            ->assertOk()->assertDontSee('Time Out');

        $this->actingAs($acc)->get(route('admin.reports.show', 'presence_time_out'))
            ->assertForbidden();
    }

    public function test_presence_report_exports_csv(): void
    {
        $p = $this->student('Export Me');
        $this->punch($p, 'out', now()->startOfDay()->addHours(10));
        $this->punch($p, 'in', now()->startOfDay()->addHours(12));

        $this->actingAs($this->admin)
            ->get(route('admin.reports.show', ['type' => 'presence_time_out', 'export' => 'excel']))
            ->assertOk();
    }
}
