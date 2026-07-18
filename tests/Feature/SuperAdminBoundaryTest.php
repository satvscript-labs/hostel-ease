<?php

namespace Tests\Feature;

use App\Models\Hostel;
use App\Models\SubscriptionAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * W12: the Super Admin side had ZERO controller tests. The one property that
 * must never regress is the boundary itself — a hostel owner (or any staff
 * role) must not reach ANY superadmin.* route, and a logged-out visitor must
 * not either.
 *
 * The GET sweep is DYNAMIC over the route table, so a future superadmin route
 * is covered the day it's added — forgetting to extend this test is not a
 * hole.
 */
class SuperAdminBoundaryTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $owner;
    protected Hostel $hostel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->superAdmin = User::factory()->create(['role' => 'super_admin', 'hostel_id' => null]);
        $this->hostel = Hostel::factory()->create();
        $this->owner = User::factory()->create(['role' => 'hostel_admin', 'hostel_id' => $this->hostel->id]);
        $this->hostel->update(['owner_id' => $this->owner->id]);
    }

    /** Every GET superadmin.* route, with real ids bound where needed. */
    protected function superAdminGetUrls(): array
    {
        $account = SubscriptionAccount::firstOrCreate(
            ['owner_id' => $this->owner->id],
            ['period' => 'yearly', 'status' => 'active', 'auto_debit' => false]
        );

        // Bind every model param to a REAL row so route-model binding succeeds
        // and the `role:super_admin` middleware is what refuses (redirect +
        // error) — otherwise a missing bound model 404s first, which is still a
        // refusal but takes a different path than the assertions expect.
        $order = \App\Models\SubscriptionOrder::firstOrCreate(
            ['account_id' => $account->id, 'transaction_number' => 'boundary-test'],
            ['period' => 'yearly', 'quantity' => 1, 'subtotal' => 0, 'discount_total' => 0,
             'amount' => 0, 'payment_status' => 'paid', 'payment_method' => 'comp']
        );

        $urls = [];
        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();
            if (! $name || ! str_starts_with($name, 'superadmin.') || ! in_array('GET', $route->methods(), true)) {
                continue;
            }

            $params = [];
            foreach ($route->parameterNames() as $p) {
                $params[$p] = match ($p) {
                    'hostel' => $this->hostel->id,
                    'account' => $account->id,
                    'order' => $order->id,
                    // File-bound routes (backups download) can't 200 without a
                    // real file — the boundary still applies, so bind a name.
                    'filename' => 'nonexistent.sql',
                    default => 1,
                };
            }

            $urls[$name] = route($name, $params);
        }

        // Sanity: the sweep found the known surface, not an empty list.
        $this->assertGreaterThanOrEqual(8, count($urls), 'Route sweep looks broken — too few superadmin GET routes found.');

        return $urls;
    }

    public function test_a_hostel_owner_is_refused_from_every_superadmin_page(): void
    {
        foreach ($this->superAdminGetUrls() as $name => $url) {
            $res = $this->actingAs($this->owner)->get($url);

            // role:super_admin redirects-back with an error flash — never 200.
            $this->assertNotSame(200, $res->status(), "hostel_admin got 200 from {$name}");
            $res->assertSessionHas('error');
        }
    }

    public function test_a_staff_role_is_refused_from_every_superadmin_page(): void
    {
        $warden = User::factory()->create(['role' => 'warden', 'hostel_id' => $this->hostel->id]);

        foreach ($this->superAdminGetUrls() as $name => $url) {
            $this->assertNotSame(200, $this->actingAs($warden)->get($url)->status(),
                "warden got 200 from {$name}");
            auth()->logout();
        }
    }

    public function test_a_logged_out_visitor_is_refused_from_every_superadmin_page(): void
    {
        foreach ($this->superAdminGetUrls() as $name => $url) {
            $this->assertNotSame(200, $this->get($url)->status(), "guest got 200 from {$name}");
        }
    }

    /** Writes are the higher-stakes boundary: billing actions must refuse too. */
    public function test_a_hostel_owner_cannot_fire_superadmin_billing_actions(): void
    {
        $account = SubscriptionAccount::firstOrCreate(
            ['owner_id' => $this->owner->id],
            ['period' => 'yearly', 'status' => 'active', 'auto_debit' => false]
        );

        $before = $account->only(['status', 'current_period_end']);

        $this->actingAs($this->owner)
            ->post(route('superadmin.accounts.suspend', $account), ['reason' => 'x'])
            ->assertRedirect();

        $this->assertSame($before, $account->fresh()->only(['status', 'current_period_end']),
            'A hostel owner mutated a billing account through a superadmin route.');
    }

    /** And the super admin actually CAN use the surface (not just "others can't"). */
    public function test_the_super_admin_can_open_the_w12_surfaces(): void
    {
        foreach (['superadmin.dashboard', 'superadmin.hostels.index', 'superadmin.activity', 'superadmin.backups.index'] as $name) {
            $this->actingAs($this->superAdmin)->get(route($name))->assertOk();
        }

        $this->actingAs($this->superAdmin)
            ->get(route('superadmin.hostels.show', $this->hostel))
            ->assertOk()
            ->assertSee($this->hostel->name);
    }
}
