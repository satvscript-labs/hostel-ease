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
            ->assertSee('Complimentary coverage')
            ->assertSee('he-summary', false)
            ->assertSee('renewSummary', false)
            ->assertSee('compBranches', false);
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

        $branchIds = $account->owner->hostels->pluck('id')->all();
        $this->actingAs($super)->post(route('superadmin.accounts.comp', $account), [
            'period' => 'yearly', 'multiplier' => 1, 'branches' => $branchIds, 'reason' => 'goodwill',
        ])->assertRedirect();
        $this->assertDatabaseHas('subscription_orders', ['account_id' => $account->id, 'amount' => 0, 'payment_method' => 'comp']);

        $this->actingAs($super)->post(route('superadmin.accounts.override', $account), ['unit_price_override_yearly' => 8000])->assertRedirect();
        $this->assertSame('8000.00', (string) $account->fresh()->unit_price_override_yearly);
        $this->assertNull($account->fresh()->unit_price_override_monthly);  // monthly stays on list price
    }

    public function test_customers_due_filter_is_a_renewals_worklist(): void
    {
        $super = User::factory()->superAdmin()->create();

        // One renewing in 3 days, one far out.
        $soon = User::factory()->create(['role' => 'hostel_admin', 'mobile' => '9000000021', 'name' => 'Soon Owner']);
        Hostel::factory()->create(['mobile' => '9000000021', 'owner_id' => $soon->id, 'status' => 'active', 'subscription_end' => now()->addDays(3)]);
        $soon->hostels()->sync(Hostel::where('owner_id', $soon->id)->pluck('id')->all());
        SubscriptionAccount::create(['owner_id' => $soon->id, 'period' => 'yearly', 'status' => 'active', 'current_period_end' => now()->addDays(3)]);

        $far = User::factory()->create(['role' => 'hostel_admin', 'mobile' => '9000000022', 'name' => 'Faraway Owner']);
        Hostel::factory()->create(['mobile' => '9000000022', 'owner_id' => $far->id, 'status' => 'active', 'subscription_end' => now()->addMonths(9)]);
        $far->hostels()->sync(Hostel::where('owner_id', $far->id)->pluck('id')->all());
        SubscriptionAccount::create(['owner_id' => $far->id, 'period' => 'yearly', 'status' => 'active', 'current_period_end' => now()->addMonths(9)]);

        $this->actingAs($super)->get(route('superadmin.accounts.index', ['due' => 7]))
            ->assertOk()
            ->assertSee('Soon Owner')          // within the window
            ->assertDontSee('Faraway Owner')   // 9 months out — excluded
            ->assertSee('Renewals due');       // the worklist tile
    }

    public function test_add_hostel_creates_a_branch_under_the_owner_and_co_terminates(): void
    {
        [$owner, $account] = $this->seedAccount();
        $super = User::factory()->superAdmin()->create();
        $before = $owner->hostels()->count();

        $this->actingAs($super)->post(route('superadmin.accounts.add-hostel', $account), [
            'name' => 'Brand New Wing', 'plan' => 'yearly', 'payment_method' => 'cash',
        ])->assertRedirect();

        // Linked to the SAME owner (no new login, same mobile) — one more branch.
        $this->assertSame($before + 1, $owner->fresh()->hostels()->count());
        $hostel = Hostel::where('name', 'Brand New Wing')->firstOrFail();
        $this->assertSame($owner->mobile, $hostel->mobile);
        // Charged through the account path and co-terminated on the anchor.
        $this->assertSame($account->current_period_end->toDateString(), $hostel->subscription_end->toDateString());
        $this->assertDatabaseHas('subscription_order_lines', ['branch_id' => $hostel->id]);
    }

    public function test_add_hostel_on_trial_starts_a_free_14_day_clock(): void
    {
        [$owner, $account] = $this->seedAccount();
        $super = User::factory()->superAdmin()->create();

        $this->actingAs($super)->post(route('superadmin.accounts.add-hostel', $account), [
            'name' => 'Trial Wing', 'plan' => 'trial',
        ])->assertRedirect();

        $hostel = Hostel::where('name', 'Trial Wing')->firstOrFail();
        $this->assertTrue($owner->fresh()->hostels->contains($hostel->id));
        // Its own ~14-day window, not co-terminated onto the 3-month anchor.
        $this->assertTrue($hostel->subscription_end->lessThan($account->current_period_end));
    }
}
