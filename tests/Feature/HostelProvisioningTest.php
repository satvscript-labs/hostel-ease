<?php

namespace Tests\Feature;

use App\Models\Hostel;
use App\Models\User;
use App\Services\BranchBillingService;
use App\Services\HostelService;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HostelProvisioningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::clear();   // super admin operates unbound
    }

    public function test_provisioning_creates_hostel_admin_and_subscription(): void
    {
        $result = app(HostelService::class)->provision([
            'name' => 'Test Hostel', 'owner_name' => 'Owner', 'mobile' => '9876543210',
            'subscription_start' => now()->toDateString(),
            'subscription_end' => now()->addYear()->toDateString(),
            'status' => 'active', 'amount' => 5000, 'payment_status' => 'paid',
        ]);

        $this->assertDatabaseHas('hostels', ['name' => 'Test Hostel']);
        // Normalised on the way in, so the login lookup can find it — this
        // asserted the raw '9876543210' and passed while the owner it created
        // was unable to log in at all (fixed W6.3-followup).
        $this->assertDatabaseHas('users', ['mobile' => '+919876543210', 'role' => 'hostel_admin']);
        $this->assertDatabaseHas('subscriptions', ['hostel_id' => $result['hostel']->id, 'amount' => 5000]);

        // The generated password actually authenticates the admin.
        $this->assertTrue(Hash::check($result['password'], $result['admin']->password));
    }

    /**
     * The whole point of provisioning: the owner it hands you can sign in.
     * Nothing asserted this end-to-end, which is how a login-breaking mobile
     * mismatch survived in a fully "passing" suite.
     */
    public function test_provisioned_owner_can_actually_log_in(): void
    {
        $result = app(HostelService::class)->provision([
            'name' => 'Login Hostel', 'owner_name' => 'Owner', 'mobile' => '9876500123',
            'plan' => 'yearly', 'status' => 'active',
        ]);

        $this->post('/login', ['mobile' => '9876500123', 'password' => $result['password']])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($result['admin']);
    }

    public function test_super_admin_can_create_hostel_via_http_and_sees_credentials(): void
    {
        $super = User::factory()->superAdmin()->create();

        $response = $this->actingAs($super)->post(route('superadmin.hostels.store'), [
            'name' => 'HTTP Hostel', 'owner_name' => 'Owner', 'mobile' => '9000011111',
            'plan' => 'yearly', 'status' => 'active',
        ]);

        $hostel = Hostel::where('name', 'HTTP Hostel')->firstOrFail();
        $response->assertRedirect(route('superadmin.hostels.show', $hostel));
        $response->assertSessionHas('credentials');
    }

    public function test_hostels_index_and_profile_render_and_edit_route_redirects(): void
    {
        $super = User::factory()->superAdmin()->create();
        $hostel = Hostel::factory()->create(['name' => 'Render Wing', 'subscription_end' => now()->addYear()]);

        // Redesigned index (stat tiles + directory) renders.
        $this->actingAs($super)->get(route('superadmin.hostels.index'))
            ->assertOk()->assertSee('Render Wing')->assertSee('Expiring', false);

        // Redesigned profile: page-level Alpine scope owns BOTH modals (the old
        // Add Admin button was dead because its teleport sat outside the scope).
        $this->actingAs($super)->get(route('superadmin.hostels.show', $hostel))
            ->assertOk()
            ->assertSee('hostelProfile()', false)
            ->assertSee('adminOpen', false)
            ->assertSee('editOpen', false);

        // Route::resource declares hostels.edit; the missing edit() 500'd. Now it
        // deep-links to the profile with the edit modal auto-opened.
        $this->actingAs($super)->get(route('superadmin.hostels.edit', $hostel))
            ->assertRedirect(route('superadmin.hostels.show', [$hostel, 'edit' => 1]));
    }

    public function test_add_admin_branch_access_tiles_grant_the_selected_branches(): void
    {
        // Regression for the dead "ALSO GRANT ACCESS TO" selector: the old
        // checkbox+CSS-sibling tiles didn't reliably toggle. The redesigned
        // modal is Alpine-driven (adminSelected[] -> looped hidden inputs), so
        // this asserts the branches actually submitted get synced onto the admin.
        $super = User::factory()->superAdmin()->create();
        $primary = Hostel::factory()->create(['name' => 'Primary Branch']);
        $extra = Hostel::factory()->create(['name' => 'Extra Branch']);

        $this->actingAs($super)->post(route('superadmin.admins.store'), [
            'hostel_id' => $primary->id, 'name' => 'New Admin', 'mobile' => '9000022222',
            'branches' => [$primary->id, $extra->id],
        ])->assertRedirect();

        $admin = User::where('mobile', '+919000022222')->firstOrFail(); // stored in +91 login form (item 14)
        $this->assertTrue($admin->hostels->pluck('id')->contains($primary->id));
        $this->assertTrue($admin->hostels->pluck('id')->contains($extra->id));
    }

    public function test_renewal_extends_hostel_coverage_and_reactivates(): void
    {
        $hostel = Hostel::factory()->expired()->create();
        $this->assertSame('expired', $hostel->status);

        // A paid renewal reactivates the branch and extends coverage into the future.
        app(BranchBillingService::class)->renewBranch($hostel, 'yearly', [
            'amount' => 5000, 'payment_status' => 'paid',
        ]);

        $hostel->refresh();
        $this->assertSame('active', $hostel->status);
        $this->assertTrue($hostel->subscription_end->isFuture());
    }
}
