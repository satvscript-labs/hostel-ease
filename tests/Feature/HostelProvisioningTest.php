<?php

namespace Tests\Feature;

use App\Models\Hostel;
use App\Models\User;
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
        $this->assertDatabaseHas('users', ['mobile' => '9876543210', 'role' => 'hostel_admin']);
        $this->assertDatabaseHas('subscriptions', ['hostel_id' => $result['hostel']->id, 'amount' => 5000]);

        // The generated password actually authenticates the admin.
        $this->assertTrue(Hash::check($result['password'], $result['admin']->password));
    }

    public function test_super_admin_can_create_hostel_via_http_and_sees_credentials(): void
    {
        $super = User::factory()->superAdmin()->create();

        $response = $this->actingAs($super)->post(route('superadmin.hostels.store'), [
            'name' => 'HTTP Hostel', 'owner_name' => 'Owner', 'mobile' => '9000011111',
            'subscription_start' => now()->toDateString(),
            'subscription_end' => now()->addYear()->toDateString(),
            'status' => 'active',
        ]);

        $hostel = Hostel::where('name', 'HTTP Hostel')->firstOrFail();
        $response->assertRedirect(route('superadmin.hostels.show', $hostel));
        $response->assertSessionHas('credentials');
    }

    public function test_renewal_extends_hostel_coverage_and_reactivates(): void
    {
        $hostel = Hostel::factory()->expired()->create();
        $this->assertSame('expired', $hostel->status);

        app(HostelService::class)->createSubscription($hostel, [
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'amount' => 5000, 'payment_status' => 'paid',
        ]);

        $hostel->refresh();
        $this->assertSame('active', $hostel->status);
        $this->assertTrue($hostel->subscription_end->isFuture());
    }
}
