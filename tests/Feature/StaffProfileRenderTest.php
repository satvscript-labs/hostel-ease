<?php

namespace Tests\Feature;

use App\Models\Hostel;
use App\Models\PaymentMode;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\StaffSalaryPayment;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The profile renders a lot of derived figures. A view that throws only shows
 * up as a 500 in a browser nobody has opened yet, so assert the real numbers
 * actually reach the page.
 */
class StaffProfileRenderTest extends TestCase
{
    use RefreshDatabase;

    protected Hostel $hostel;
    protected Staff $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hostel = Hostel::factory()->create();
        $admin = User::factory()->create(['hostel_id' => $this->hostel->id, 'role' => 'hostel_admin']);
        Tenant::set($this->hostel->id);

        PaymentMode::create(['hostel_id' => $this->hostel->id, 'code' => 'cash', 'name' => 'Cash',
            'is_active' => true, 'requires_reference' => false, 'sort_order' => 0]);

        $this->staff = Staff::create(['hostel_id' => $this->hostel->id, 'name' => 'Govind Watchman',
            'designation' => 'Security Guard', 'mobile' => '9800000001',
            'monthly_salary' => 12000, 'join_date' => now()->subMonths(5), 'is_active' => true]);

        $this->actingAs($admin);
    }

    public function test_hero_metrics_are_the_real_figures(): void
    {
        // This month + a previous one: "Paid · Jul" must be this month only,
        // "Paid Lifetime" must be everything.
        StaffSalaryPayment::create(['hostel_id' => $this->hostel->id, 'staff_id' => $this->staff->id,
            'salary_month' => now()->startOfMonth(), 'amount' => 12000, 'paid_on' => now(), 'mode' => 'cash']);
        StaffSalaryPayment::create(['hostel_id' => $this->hostel->id, 'staff_id' => $this->staff->id,
            'salary_month' => now()->subMonth()->startOfMonth(), 'amount' => 9000,
            'paid_on' => now()->subMonth(), 'mode' => 'cash']);

        foreach (['present', 'present', 'half_day', 'absent'] as $i => $status) {
            StaffAttendance::create(['hostel_id' => $this->hostel->id, 'staff_id' => $this->staff->id,
                'date' => now()->startOfMonth()->addDays($i), 'status' => $status]);
        }

        $res = $this->get(route('admin.staff.show', $this->staff))->assertOk();

        $this->assertEquals(12000.0, $res->viewData('paidThisMonth'));
        $this->assertEquals(21000.0, $res->viewData('paidLifetime'));

        // present + half_day is what the hero calls "days present".
        $counts = $res->viewData('counts');
        $this->assertSame(2, $counts['present']);
        $this->assertSame(1, $counts['half_day']);
        $this->assertSame(1, $counts['absent']);

        $res->assertSee('Govind Watchman')->assertSee('Security Guard');
    }

    /** The back link is navigation, not the page-head's one action — it must
     *  never be hidden on phones (it was, until the profile redesign). */
    public function test_the_back_link_is_always_present(): void
    {
        $html = $this->get(route('admin.staff.show', $this->staff))->assertOk()->getContent();

        $this->assertStringContainsString(route('admin.staff.index'), $html);
        $this->assertMatchesRegularExpression(
            '/<a[^>]+href="[^"]*'.preg_quote(parse_url(route('admin.staff.index'), PHP_URL_PATH), '/').'"(?![^>]*d-none)/',
            $html,
            'The back link must not carry a d-none class — phones would have no way out of the page.'
        );
    }

    public function test_a_removed_staff_profile_renders_without_write_actions(): void
    {
        $this->staff->delete();

        // Assert on what the page OFFERS, not on URL fragments: the update route
        // is /admin/staff/1, which is a substring of the restore URL the page
        // legitimately renders — so a URL check here passes or fails by accident.
        $this->get(route('admin.staff.show', $this->staff))
            ->assertOk()
            ->assertSee('Govind Watchman')
            ->assertSee('Restore')
            ->assertDontSee('Edit Staff')       // the edit sheet
            ->assertDontSee('Record Payment')   // the pay sheet
            ->assertDontSee('Remove from directory');
    }
}
