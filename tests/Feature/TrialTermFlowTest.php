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
 * What an owner on a free trial sees and can do (found hand-testing, 2026-10-06):
 * an abandoned monthly checkout must not trap them in monthly, "Next total" must not
 * present one term's price as THE price, and a renewal quoted before another branch
 * joined the trial must not renew only some of the account.
 */
class TrialTermFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private SubscriptionAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::clear();
        config(['hostelease.owner_self_serve' => true, 'services.razorpay.enabled' => true, 'services.razorpay.key' => 'k', 'services.razorpay.secret' => 's']);
        $billing = app(AccountBillingService::class);

        $this->owner = User::factory()->create(['role' => 'hostel_admin', 'mobile' => '9855500001']);
        $first = Hostel::factory()->create(['owner_id' => $this->owner->id, 'subscription_start' => null, 'subscription_end' => null]);
        $this->owner->hostels()->sync([$first->id]);
        $this->owner->forceFill(['hostel_id' => $first->id])->save();
        $billing->recordBranchRenewal($first, 'trial', ['payment_status' => 'paid']);
        $billing->recordBranchRenewal(app(HostelService::class)->createBranchForOwner($this->owner, ['name' => 'Second']), 'trial', ['payment_status' => 'paid']);
        $this->account = SubscriptionAccount::where('owner_id', $this->owner->id)->sole();
    }

    private function fakeRazorpay(string $orderId = 'o1'): void
    {
        Http::fake([
            'api.razorpay.com/v1/orders' => Http::response(['id' => $orderId, 'amount' => 1, 'currency' => 'INR', 'receipt' => 'r'], 200),
            'api.razorpay.com/v1/orders/*/payments' => Http::response(['items' => []], 200),
        ]);
    }

    private function startMonthly(): void
    {
        $this->actingAs($this->owner)->postJson(route('admin.subscription.checkout'), ['charge' => 'renewal', 'period' => 'monthly'])->assertOk();
    }

    // 1 ── an abandoned attempt does not trap the owner in that term

    public function test_the_owner_can_change_the_term_of_their_own_abandoned_attempt(): void
    {
        $this->fakeRazorpay();
        $this->startMonthly();
        $monthly = SubscriptionOrder::where('kind', 'renewal')->sole();

        $this->actingAs($this->owner)->get(route('admin.subscription.index'))
            ->assertOk()->assertSee('Change term')->assertSee('Pay');

        // Choosing yearly now replaces the abandoned monthly attempt.
        $this->fakeRazorpay('o2');
        $this->actingAs($this->owner)->postJson(route('admin.subscription.checkout'), ['charge' => 'renewal', 'period' => 'yearly'])
            ->assertOk()->assertJsonPath('razorpay.amount', 2000000);

        $this->assertSame('voided', $monthly->fresh()->payment_status->value);
        $this->assertSame(1, SubscriptionOrder::where('kind', 'renewal')->where('payment_status', 'pending')->count());
    }

    public function test_an_operator_raised_renewal_does_not_offer_change_term(): void
    {
        $order = app(AccountBillingService::class)->renewAccount($this->account, 'monthly', ['payment_status' => 'pending', 'collection' => 'link']);
        $order->update(['payment_link_id' => 'plink_X', 'payment_link_url' => 'https://rzp.io/i/x', 'payment_link_status' => 'created', 'payment_link_attempts' => 1]);

        $this->actingAs($this->owner)->get(route('admin.subscription.index'))
            ->assertOk()->assertDontSee('Change term');
    }

    // 2 ── the number says what it is

    public function test_on_a_trial_the_page_shows_both_prices_and_never_one_as_the_total(): void
    {
        $this->actingAs($this->owner)->get(route('admin.subscription.index'))
            ->assertOk()
            ->assertSee('After the trial')
            ->assertSee('₹20,000.00')      // 2 × yearly
            ->assertSee('₹2,000.00')       // 2 × monthly
            ->assertDontSee('Next total');
    }

    public function test_once_a_term_is_billed_the_page_shows_what_is_due_in_that_term(): void
    {
        $this->fakeRazorpay();
        $this->startMonthly();

        $this->actingAs($this->owner)->get(route('admin.subscription.index'))
            ->assertOk()
            ->assertSee('Due now')
            ->assertSee('Monthly')
            ->assertDontSee('After the trial');
    }

    // 3 ── a renewal quoted before another branch joined the trial

    public function test_a_branch_added_to_the_trial_after_the_quote_makes_that_renewal_out_of_date(): void
    {
        $this->fakeRazorpay();
        $this->startMonthly();
        $pending = SubscriptionOrder::where('kind', 'renewal')->sole();
        $this->assertSame('extends', $pending->coverageState(true));

        $this->actingAs($this->owner)->postJson(route('admin.subscription.add-branch'), ['name' => 'Third'])->assertOk();

        $this->assertTrue(Hostel::where('name', 'Third')->sole()->isActive(), 'It joined the trial.');
        $this->assertSame('stale', $pending->coverageState(true), 'The quote leaves Third out.');

        $this->actingAs($this->owner)->get(route('admin.subscription.index'))
            ->assertSee('Amount changed — renew again for the new total');

        // Paying the stale charge by id is refused; re-quoting includes all three.
        $this->actingAs($this->owner)->postJson(route('admin.subscription.checkout'), ['charge' => 'order', 'order_id' => $pending->id])
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'out of date'));

        $this->fakeRazorpay('o3');
        $this->actingAs($this->owner)->postJson(route('admin.subscription.checkout'), ['charge' => 'renewal', 'period' => 'monthly'])
            ->assertOk()->assertJsonPath('razorpay.amount', 300000);
        $fresh = SubscriptionOrder::where('kind', 'renewal')->where('payment_status', 'pending')->sole();
        $this->assertSame(3, $fresh->quantity);
        $this->assertSame('voided', $pending->fresh()->payment_status->value);
    }

    public function test_a_renewal_that_already_includes_every_branch_stays_payable(): void
    {
        $this->fakeRazorpay();
        $this->startMonthly();
        $pending = SubscriptionOrder::where('kind', 'renewal')->sole();

        $this->assertSame('extends', $pending->coverageState(true));
    }
}
