<?php

namespace Tests\Feature;

use App\Models\Hostel;
use App\Models\SubscriptionAccount;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A co-admin is not a customer.
 *
 * There is no separate "co-owner" role — a co-admin is another `hostel_admin`.
 * Both Settings and the Subscription page used to resolve the account with
 * `accountFor($request->user())`, a firstOrCreate keyed on owner_id, so a
 * co-admin merely OPENING either page minted a phantom trial account for
 * themselves. It then appeared in the Super Admin's Customers list as a real
 * customer owning no branches — which is exactly how it was spotted.
 *
 * The fix resolves the branches' real owner first (accountForViewer), so an
 * account is only ever created for someone who genuinely owns a branch.
 */
class CoAdminBillingTest extends TestCase
{
    use RefreshDatabase;

    protected Hostel $branch;
    protected User $owner;
    protected User $coAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::clear();

        $this->branch = Hostel::factory()->create(['mobile' => '+919000000001']);

        $this->owner = User::factory()->create([
            'role' => 'hostel_admin', 'mobile' => '+919000000001', 'hostel_id' => $this->branch->id,
        ]);
        $this->owner->hostels()->sync([$this->branch->id]);
        $this->branch->forceFill(['owner_id' => $this->owner->id])->save();

        // Same role, same branch access — but owns nothing.
        $this->coAdmin = User::factory()->create([
            'role' => 'hostel_admin', 'mobile' => '+919000000009', 'hostel_id' => $this->branch->id,
        ]);
        $this->coAdmin->hostels()->sync([$this->branch->id]);

        Tenant::set($this->branch->id);
    }

    public function test_a_co_admin_opening_settings_does_not_become_a_customer(): void
    {
        $this->actingAs($this->coAdmin)->get(route('admin.settings.index'))->assertOk();

        $this->assertDatabaseMissing('subscription_accounts', ['owner_id' => $this->coAdmin->id]);
    }

    public function test_a_co_admin_opening_the_subscription_page_does_not_become_a_customer(): void
    {
        $this->actingAs($this->coAdmin)->get(route('admin.subscription.index'))->assertOk();

        $this->assertDatabaseMissing('subscription_accounts', ['owner_id' => $this->coAdmin->id]);
    }

    /** They see the OWNER's account — the branches they work in are billed on it. */
    public function test_a_co_admin_is_shown_the_owners_account(): void
    {
        $account = $this->actingAs($this->coAdmin)
            ->get(route('admin.subscription.index'))->assertOk()->viewData('account');

        $this->assertSame($this->owner->id, $account->owner_id);
        $this->assertSame(1, SubscriptionAccount::count(), 'Exactly one account should exist — the owner\'s.');
    }

    /** The genuine owner is unaffected: their account is still created on demand. */
    public function test_the_real_owner_still_gets_their_own_account(): void
    {
        $this->actingAs($this->owner)->get(route('admin.subscription.index'))->assertOk();

        $this->assertDatabaseHas('subscription_accounts', ['owner_id' => $this->owner->id]);
        $this->assertSame(1, SubscriptionAccount::count());
    }

    /**
     * Two co-admins, many page views — still exactly one account. Guards the
     * firstOrCreate from ever drifting back to keying on the viewer.
     */
    public function test_repeated_views_by_several_co_admins_never_multiply_accounts(): void
    {
        $second = User::factory()->create([
            'role' => 'hostel_admin', 'mobile' => '+919000000008', 'hostel_id' => $this->branch->id,
        ]);
        $second->hostels()->sync([$this->branch->id]);

        foreach ([$this->owner, $this->coAdmin, $second, $this->coAdmin] as $user) {
            $this->actingAs($user)->get(route('admin.settings.index'))->assertOk();
            $this->actingAs($user)->get(route('admin.subscription.index'))->assertOk();
        }

        $this->assertSame(1, SubscriptionAccount::count());
        $this->assertSame($this->owner->id, SubscriptionAccount::first()->owner_id);
    }
}
