<?php

namespace Tests\Feature;

use App\Models\Hostel;
use App\Models\SubscriptionAccount;
use App\Models\SubscriptionOrder;
use App\Models\User;
use App\Services\Billing\AccountBillingService;
use App\Services\HostelService;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Super Admin → Hostels → New customer, rebuilt on the shared billing core
 * (2026-10-05). It used to take a typed amount, a browser "Auto-calc" of the list
 * price, a paid / pending / failed status, and — for an existing owner's number —
 * gave the new branch its own full year at list price, pushing their renewal date.
 */
class ProvisionNewCustomerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::clear();
        $this->admin = User::factory()->superAdmin()->create();
    }

    private function provision(array $over = [])
    {
        return $this->actingAs($this->admin)->post(route('superadmin.hostels.store'), array_merge([
            'name' => 'Lotus PG', 'owner_name' => 'Asha Mehta', 'mobile' => '9822200001', 'status' => 'active',
            'plan' => 'yearly', 'collect' => 'offline', 'payment_method' => 'upi',
        ], $over));
    }

    public function test_a_new_customer_paid_offline_is_live_for_a_year_and_lands_on_their_account(): void
    {
        $res = $this->provision();

        $hostel = Hostel::where('name', 'Lotus PG')->sole();
        $account = SubscriptionAccount::where('owner_id', $hostel->owner_id)->sole();
        $res->assertRedirect(route('superadmin.accounts.show', $account))->assertSessionHas('credentials');

        $order = SubscriptionOrder::sole();
        $this->assertSame('paid', $order->payment_status->value);
        $this->assertSame('upi', $order->payment_method->value);
        $this->assertEqualsWithDelta(10000, (float) $order->amount, 0.001);
        $this->assertTrue($hostel->fresh()->isActive());
        $this->assertSame(now()->addYear()->toDateString(), $account->fresh()->current_period_end->toDateString());
        $this->assertSame('yearly', $account->fresh()->period->value);
    }

    public function test_the_first_term_goes_through_the_discount_engine(): void
    {
        \App\Models\DiscountRule::create(['min_quantity' => 1, 'type' => 'percentage', 'value' => 5, 'active' => true]);

        $this->provision();

        $this->assertEqualsWithDelta(9500, (float) SubscriptionOrder::sole()->amount, 0.001, 'List price minus the automatic tier.');
    }

    public function test_an_override_may_only_lower_the_first_charge(): void
    {
        $this->provision(['amount' => 8000]);
        $this->assertEqualsWithDelta(8000, (float) SubscriptionOrder::sole()->amount, 0.001);

        $this->provision(['name' => 'Greedy PG', 'mobile' => '9822200002', 'amount' => 15000])->assertSessionHasErrors('plan');
        $this->assertNull(Hostel::where('name', 'Greedy PG')->first(), 'A refused charge leaves nothing behind — no hostel, no login.');
        $this->assertNull(User::where('mobile', '+919822200002')->first());
    }

    public function test_a_free_trial_costs_nothing_and_ignores_a_posted_amount(): void
    {
        $this->provision(['plan' => 'trial', 'amount' => 5000]);

        $order = SubscriptionOrder::sole();
        $this->assertSame('trial', $order->kind->value);
        $this->assertSame(0.0, (float) $order->amount);
        $this->assertTrue(Hostel::where('name', 'Lotus PG')->sole()->isActive());
    }

    public function test_a_payment_link_creates_everything_unpaid_and_opens_the_share_panel(): void
    {
        config(['services.razorpay.enabled' => true, 'services.razorpay.key' => 'rzp_test_key', 'services.razorpay.secret' => 's']);
        Http::fake(['api.razorpay.com/v1/payment_links' => Http::response([
            'id' => 'plink_NEW', 'short_url' => 'https://rzp.io/i/new', 'status' => 'created', 'expire_by' => now()->addDays(7)->timestamp,
        ], 200)]);

        $res = $this->provision(['collect' => 'link']);

        $order = SubscriptionOrder::sole();
        $this->assertSame('pending', $order->payment_status->value);
        $this->assertSame('plink_NEW', $order->payment_link_id);
        $this->assertNull($order->payment_method, 'No instrument until it is paid.');
        $this->assertFalse(Hostel::where('name', 'Lotus PG')->sole()->isActive(), 'Live only once paid.');
        $res->assertSessionHas('payment_link', fn ($l) => $l['url'] === 'https://rzp.io/i/new');
    }

    public function test_a_razorpay_failure_while_creating_the_link_leaves_nothing_behind(): void
    {
        config(['services.razorpay.enabled' => true, 'services.razorpay.key' => 'rzp_test_key', 'services.razorpay.secret' => 's']);
        Http::fake(['api.razorpay.com/*' => Http::response(['error' => ['description' => 'down']], 500)]);

        $this->provision(['collect' => 'link'])->assertSessionHasErrors('plan');

        $this->assertSame(0, Hostel::count());
        $this->assertSame(0, SubscriptionOrder::count());
        $this->assertNull(User::where('mobile', '+919822200001')->first());
    }

    public function test_an_existing_customers_number_is_refused_and_creates_nothing(): void
    {
        $this->provision();
        $before = [Hostel::count(), SubscriptionOrder::count()];

        $this->provision(['name' => 'Second Site'])->assertSessionHasErrors('mobile');

        $this->assertSame($before, [Hostel::count(), SubscriptionOrder::count()]);
    }

    public function test_the_lookup_points_an_existing_customer_at_their_account(): void
    {
        $this->provision();
        $account = SubscriptionAccount::sole();

        $this->actingAs($this->admin)->getJson(route('superadmin.hostels.owner-lookup', ['mobile' => '98222 00001']))
            ->assertOk()
            ->assertJson(['exists' => true, 'name' => 'Asha Mehta', 'branches' => 1, 'account_url' => route('superadmin.accounts.show', [$account, 'add_hostel' => 1])]);

        $this->actingAs($this->admin)->getJson(route('superadmin.hostels.owner-lookup', ['mobile' => '9999999999']))
            ->assertJson(['exists' => false]);
    }

    public function test_the_lookup_is_for_super_admins_only(): void
    {
        $owner = User::factory()->create(['role' => 'hostel_admin']);

        $this->actingAs($owner)->getJson(route('superadmin.hostels.owner-lookup', ['mobile' => '9822200001']))
            ->assertStatus(302);
    }

    public function test_the_old_payment_status_field_is_gone(): void
    {
        $this->provision(['payment_status' => 'failed', 'collect' => 'offline']);

        // Ignored: the collection choice decides. Recorded offline = paid.
        $this->assertSame('paid', SubscriptionOrder::sole()->payment_status->value);
    }

    /** The service still links an existing owner — but bills the branch onto THEIR plan. */
    public function test_a_branch_provisioned_for_an_existing_owner_co_terminates_at_their_price(): void
    {
        $first = app(HostelService::class)->provision([
            'name' => 'First', 'owner_name' => 'Ravi', 'mobile' => '+919822200009', 'status' => 'active',
            'plan' => 'yearly', 'payment_status' => 'paid', 'payment_method' => 'cash',
        ]);
        $account = SubscriptionAccount::where('owner_id', $first['admin']->id)->sole();
        $account->update(['unit_price_override_yearly' => 8000]);
        $anchor = $account->fresh()->current_period_end->toDateString();
        $this->travel(3)->months();

        $second = app(HostelService::class)->provision([
            'name' => 'Second', 'owner_name' => 'Ravi', 'mobile' => '+919822200009', 'status' => 'active',
            'plan' => 'yearly', 'payment_status' => 'paid', 'payment_method' => 'cash',
        ]);

        $this->assertSame($anchor, $second['hostel']->fresh()->subscription_end->toDateString(), 'Co-terminated — not its own year.');
        $this->assertSame($anchor, $account->fresh()->current_period_end->toDateString(), 'The account\'s renewal date did not move.');
        $this->assertSame('add_branch', $second['order']->kind->value);
        // Nine months left at ₹8,000/yr — never the ₹10,000 list price for a full year.
        $this->assertEqualsWithDelta(8000 * 0.75, (float) $second['order']->amount, 150, 'Prorated, at their negotiated price.');
    }

    public function test_the_page_renders_with_server_prices_and_no_auto_calc(): void
    {
        $this->actingAs($this->admin)->get(route('superadmin.hostels.index'))
            ->assertOk()
            ->assertSee('New customer')
            ->assertDontSee('Auto-calc')
            ->assertDontSee('PAYMENT STATUS');
    }
}
