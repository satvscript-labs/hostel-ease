<?php

namespace Tests\Feature;

use App\Models\Hostel;
use App\Models\SubscriptionAccount;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminLegacyCleanupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::clear();
    }

    public function test_dashboard_renders_with_per_account_upcoming_renewals(): void
    {
        $owner = User::factory()->create(['role' => 'hostel_admin', 'mobile' => '9666600001', 'name' => 'Dash Owner']);
        $branch = Hostel::factory()->create(['mobile' => '9666600001', 'status' => 'active', 'subscription_end' => now()->addDays(10)]);
        $owner->hostels()->sync([$branch->id]);
        $account = SubscriptionAccount::create(['owner_id' => $owner->id, 'period' => 'yearly', 'status' => 'active', 'current_period_end' => now()->addDays(10)]);

        $super = User::factory()->superAdmin()->create();

        $this->actingAs($super)->get(route('superadmin.dashboard'))
            ->assertOk()
            ->assertSee('Dash Owner')
            ->assertSee(route('superadmin.accounts.show', $account));
    }

    public function test_hostel_show_renew_button_points_at_account_360_when_an_account_exists(): void
    {
        $owner = User::factory()->create(['role' => 'hostel_admin', 'mobile' => '9666600002']);
        $branch = Hostel::factory()->create(['mobile' => '9666600002', 'status' => 'active', 'subscription_end' => now()->addYear()]);
        $owner->hostels()->sync([$branch->id]);
        $account = SubscriptionAccount::create(['owner_id' => $owner->id, 'period' => 'yearly', 'status' => 'active', 'current_period_end' => now()->addYear()]);

        $super = User::factory()->superAdmin()->create();

        $this->actingAs($super)->get(route('superadmin.hostels.show', $branch))
            ->assertOk()
            ->assertSee(route('superadmin.accounts.show', $account), false);
    }

    public function test_legacy_subscriptions_route_still_works_though_removed_from_sidebar(): void
    {
        $super = User::factory()->superAdmin()->create();

        $this->actingAs($super)->get(route('superadmin.subscriptions.index'))->assertOk();
        $this->actingAs($super)->get(route('superadmin.dashboard'))->assertDontSee('href="'.route('superadmin.subscriptions.index').'"', false);
    }
}
