<?php

namespace Tests\Feature;

use App\Models\Hostel;
use App\Models\SubscriptionAccount;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminAccountsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::clear();
    }

    protected function seedAccount(): array
    {
        $owner = User::factory()->create(['role' => 'hostel_admin', 'mobile' => '9000000009', 'name' => 'Ravi Owner']);
        $b1 = Hostel::factory()->create(['mobile' => '9000000009', 'status' => 'active', 'subscription_end' => now()->addMonths(3)]);
        $b2 = Hostel::factory()->create(['mobile' => '9000000009', 'status' => 'active', 'subscription_end' => now()->addMonths(3)]);
        $owner->hostels()->sync([$b1->id, $b2->id]);

        $account = SubscriptionAccount::create([
            'owner_id' => $owner->id, 'period' => 'yearly', 'status' => 'active', 'current_period_end' => now()->addMonths(3),
        ]);

        return [$owner, $account];
    }

    public function test_customers_index_renders_and_lists_the_account(): void
    {
        [$owner] = $this->seedAccount();
        $super = User::factory()->superAdmin()->create();

        $this->actingAs($super)->get(route('superadmin.accounts.index'))
            ->assertOk()
            ->assertSee('Ravi Owner');
    }

    public function test_account_360_renders(): void
    {
        [, $account] = $this->seedAccount();
        $super = User::factory()->superAdmin()->create();

        $this->actingAs($super)->get(route('superadmin.accounts.show', $account))
            ->assertOk()
            ->assertSee('Renew all')
            ->assertSee('Branches')
            // Redesigned billing modals + the shared live summary are present.
            ->assertSee('Renew all branches')
            ->assertSee('Add branch to cycle')
            ->assertSee('Align branches to renewal date')
            ->assertSee('he-summary', false)
            ->assertSee('renewSummary', false);
    }

    public function test_renew_all_advances_the_anchor_and_records_an_order(): void
    {
        [, $account] = $this->seedAccount();
        $super = User::factory()->superAdmin()->create();

        $this->actingAs($super)
            ->post(route('superadmin.accounts.renew', $account), ['period' => 'yearly', 'payment_method' => 'cash'])
            ->assertRedirect();

        $expected = now()->addMonths(3)->addYear()->toDateString();
        $this->assertSame($expected, $account->fresh()->current_period_end->toDateString());
        $this->assertDatabaseHas('subscription_orders', ['account_id' => $account->id, 'quantity' => 2, 'payment_status' => 'paid']);
    }

    public function test_add_branch_prorates_a_behind_branch_and_co_terminates(): void
    {
        $owner = User::factory()->create(['role' => 'hostel_admin', 'mobile' => '9000000010']);
        $covered = Hostel::factory()->create(['mobile' => '9000000010', 'status' => 'active', 'subscription_end' => now()->addMonths(6)]);
        $behind = Hostel::factory()->create(['mobile' => '9000000010', 'status' => 'expired', 'subscription_end' => now()->subDay()]);
        $owner->hostels()->sync([$covered->id, $behind->id]);
        $account = SubscriptionAccount::create(['owner_id' => $owner->id, 'period' => 'yearly', 'status' => 'active', 'current_period_end' => now()->addMonths(6)]);

        $super = User::factory()->superAdmin()->create();

        $this->actingAs($super)
            ->post(route('superadmin.accounts.add-branch', $account), ['branch_id' => $behind->id, 'payment_method' => 'cash'])
            ->assertRedirect();

        $this->assertSame($account->current_period_end->toDateString(), $behind->fresh()->subscription_end->toDateString());
        $this->assertDatabaseHas('subscription_orders', ['account_id' => $account->id, 'quantity' => 1]);
    }

    public function test_suspend_blocks_access_and_reactivate_restores_it(): void
    {
        [$owner, $account] = $this->seedAccount();
        $super = User::factory()->superAdmin()->create();
        $branch = Hostel::whereIn('id', $owner->accessibleHostelIds())->first();

        $this->actingAs($super)->post(route('superadmin.accounts.suspend', $account), ['reason' => 'payment dispute'])->assertRedirect();
        $this->assertSame('suspended', $branch->fresh()->status);
        $this->assertFalse($branch->fresh()->isActive());

        $this->actingAs($super)->post(route('superadmin.accounts.reactivate', $account))->assertRedirect();
        $this->assertSame('active', $branch->fresh()->status);
        $this->assertTrue($branch->fresh()->isActive());
    }

    public function test_add_discount_and_comp_and_override(): void
    {
        [, $account] = $this->seedAccount();
        $super = User::factory()->superAdmin()->create();

        $this->actingAs($super)->post(route('superadmin.accounts.discounts.store', $account), [
            'recurrence' => 'every_renewal', 'type' => 'percentage', 'value' => 10, 'reason' => 'loyal customer',
        ])->assertRedirect();
        $this->assertDatabaseHas('discounts', ['account_id' => $account->id, 'recurrence' => 'every_renewal', 'status' => 'active']);

        $this->actingAs($super)->post(route('superadmin.accounts.comp', $account), ['period' => 'yearly', 'reason' => 'goodwill'])->assertRedirect();
        $this->assertDatabaseHas('subscription_orders', ['account_id' => $account->id, 'amount' => 0, 'payment_method' => 'comp']);

        $this->actingAs($super)->post(route('superadmin.accounts.override', $account), ['unit_price_override' => 8000])->assertRedirect();
        $this->assertSame('8000.00', (string) $account->fresh()->unit_price_override);
    }
}
