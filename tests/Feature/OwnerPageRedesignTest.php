<?php

namespace Tests\Feature;

use App\Models\Hostel;
use App\Models\SubscriptionAccount;
use App\Models\SubscriptionOrder;
use App\Models\SubscriptionOrderLine;
use App\Models\User;
use App\Services\Billing\AccountBillingService;
use App\Services\HostelService;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The owner's Subscription page, redesigned (doc 23): every charge reviewed before
 * paying, "Bring all up to date" in one payment, receipts, and nothing internal
 * shown as a payment. The money-safety half is the point: the new align charge goes
 * through the same rules as every other one.
 */
class OwnerPageRedesignTest extends TestCase
{
    use RefreshDatabase;

    private AccountBillingService $billing;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::clear();
        $this->billing = app(AccountBillingService::class);
        config(['hostelease.owner_self_serve' => true, 'services.razorpay.enabled' => true, 'services.razorpay.key' => 'rzp_test_key', 'services.razorpay.secret' => 'rzp_test_secret']);
    }

    /** A paid yearly account (bought six months ago) with $behind extra branches not yet paid for. */
    private function account(int $behind = 2, string $mobile = '9866600001'): array
    {
        $owner = User::factory()->create(['role' => 'hostel_admin', 'mobile' => $mobile]);
        $first = Hostel::factory()->create(['owner_id' => $owner->id, 'mobile' => $mobile, 'subscription_start' => null, 'subscription_end' => null]);
        $owner->hostels()->sync([$first->id]);
        $owner->forceFill(['hostel_id' => $first->id])->save();
        $account = $this->billing->accountFor($owner);
        $this->travel(-6)->months();
        $this->billing->renewAccount($account->fresh(), 'yearly', ['payment_status' => 'paid', 'payment_method' => 'cash']);
        $this->travelBack();

        $extra = [];
        for ($i = 1; $i <= $behind; $i++) {
            $extra[] = app(HostelService::class)->createBranchForOwner($owner, ['name' => "Wing {$i}"]);
        }

        return [$owner, $account->fresh(), $first, $extra];
    }

    private function fakeOrders(string ...$ids): void
    {
        $seq = Http::sequence();
        foreach ($ids as $id) {
            $seq->push(['id' => $id, 'amount' => 1, 'currency' => 'INR', 'receipt' => 'r'], 200);
        }
        Http::fake(['api.razorpay.com/v1/orders' => $seq, 'api.razorpay.com/v1/orders/*/payments' => Http::response(['items' => []], 200)]);
    }

    private function align(User $owner)
    {
        return $this->actingAs($owner)->postJson(route('admin.subscription.checkout'), ['charge' => 'align']);
    }

    // ── Bring all up to date ─────────────────────────────────────────────

    public function test_bring_all_up_to_date_charges_exactly_what_align_quotes_in_one_order(): void
    {
        [$owner, $account] = $this->account(2);
        $quote = $this->billing->quoteAlign($account);
        $this->fakeOrders('order_AL');

        $res = $this->align($owner)->assertOk()->assertJsonPath('mode', 'checkout');

        $order = SubscriptionOrder::where('kind', 'align')->sole();
        $this->assertSame('pending', $order->payment_status->value);
        $this->assertSame('checkout', $order->collection->value);
        $this->assertSame(2, $order->lines()->count());
        $this->assertSame((int) round($quote['subtotal'] * 100), $order->amountPaise());
        $this->assertSame($order->amountPaise(), $res->json('razorpay.amount'), 'Razorpay is asked for the ORDER amount.');
    }

    public function test_reopening_reuses_the_same_order_and_razorpay_order(): void
    {
        [$owner] = $this->account(2);
        $this->fakeOrders('order_AL1', 'order_AL2');

        $this->align($owner)->assertOk();
        $this->align($owner)->assertOk()->assertJsonPath('razorpay.order_id', 'order_AL1');

        $this->assertSame(1, SubscriptionOrder::where('kind', 'align')->count());
    }

    public function test_a_changed_branch_set_supersedes_the_earlier_attempt(): void
    {
        [$owner, , , $extra] = $this->account(2);
        $this->fakeOrders('order_A1', 'order_A2');
        $this->align($owner)->assertOk();
        $first = SubscriptionOrder::where('kind', 'align')->sole();

        app(HostelService::class)->createBranchForOwner($owner, ['name' => 'Wing 3']);
        $this->align($owner)->assertOk()->assertJsonPath('razorpay.order_id', 'order_A2');

        $this->assertSame('voided', $first->fresh()->payment_status->value);
        $this->assertSame(3, SubscriptionOrder::where('kind', 'align')->where('payment_status', 'pending')->sole()->lines()->count());
    }

    /** Same total, different branches (one removed, one added the same day) — never the old order. */
    public function test_a_swapped_branch_is_never_paid_for_with_the_old_align(): void
    {
        [$owner, $account, , $extra] = $this->account(2);
        $this->fakeOrders('order_S1', 'order_S2');
        $this->align($owner)->assertOk();
        $first = SubscriptionOrder::where('kind', 'align')->sole();

        $this->billing->cancelBranch($extra[1]->fresh(), 'not opening after all');
        app(HostelService::class)->createBranchForOwner($owner, ['name' => 'Wing 3']);
        $this->assertSame($first->amountPaise(), (int) round($this->billing->quoteAlign($account->fresh())['subtotal'] * 100), 'Same price.');

        $this->align($owner)->assertOk()->assertJsonPath('razorpay.order_id', 'order_S2');
        $this->assertSame('voided', $first->fresh()->payment_status->value);
    }

    public function test_a_branch_paid_separately_makes_the_open_align_stale_never_payable_twice(): void
    {
        [$owner, $account, , $extra] = $this->account(2);
        $this->fakeOrders('order_A1', 'order_A2');
        $this->align($owner)->assertOk();
        $open = SubscriptionOrder::where('kind', 'align')->sole();

        // One of its branches gets paid another way first.
        $this->billing->addBranch($account->fresh(), $extra[0]->fresh(), ['payment_status' => 'paid', 'payment_method' => 'cash']);

        $this->assertSame('stale', $open->coverageState(true));
        $this->actingAs($owner)->postJson(route('admin.subscription.checkout'), ['charge' => 'order', 'order_id' => $open->id])->assertStatus(422);

        // Bringing all up to date again re-quotes: only the branch still behind,
        // and the stale attempt is cleared — the paid branch is never charged twice.
        $remaining = $this->billing->quoteAlign($account->fresh());
        $this->assertSame(1, $remaining['count']);
        $this->align($owner)->assertOk()->assertJsonPath('razorpay.amount', (int) round($remaining['subtotal'] * 100));
        $this->assertSame('voided', $open->fresh()->payment_status->value);
    }

    public function test_bring_all_up_to_date_needs_a_live_paid_plan(): void
    {
        $owner = User::factory()->create(['role' => 'hostel_admin', 'mobile' => '9866600090']);
        $first = Hostel::factory()->create(['owner_id' => $owner->id, 'subscription_start' => null, 'subscription_end' => null]);
        $owner->hostels()->sync([$first->id]);
        $owner->forceFill(['hostel_id' => $first->id])->save();
        $this->billing->recordBranchRenewal($first, 'trial', ['payment_status' => 'paid']);
        // Two branches behind the trial's end, not joined to it — nothing may charge
        // trial time at a paid rate.
        app(HostelService::class)->createBranchForOwner($owner, ['name' => 'Old Wing A']);
        app(HostelService::class)->createBranchForOwner($owner, ['name' => 'Old Wing B']);
        $this->assertSame(2, $this->billing->quoteAlign(SubscriptionAccount::where('owner_id', $owner->id)->sole())['count']);
        Http::fake();

        $this->align($owner)->assertStatus(422);
        Http::assertNothingSent();
    }

    public function test_a_managed_owner_cannot_start_an_align(): void
    {
        [$owner, $account] = $this->account(2);
        $account->forceFill(['billing_mode' => 'managed'])->save();
        Http::fake();

        $this->align($owner)->assertStatus(403);
        $this->assertSame(0, SubscriptionOrder::where('kind', 'align')->count());
    }

    // ── What the page shows ──────────────────────────────────────────────

    public function test_two_behind_branches_offer_bring_all_up_to_date_with_its_total(): void
    {
        [$owner, $account] = $this->account(2);
        $total = $this->billing->quoteAlign($account)['subtotal'];

        $this->actingAs($owner)->get(route('admin.subscription.index'))
            ->assertOk()
            ->assertSee('Next step')
            ->assertSee('@click="openAlign()"', false)
            ->assertSee(hostelease_money($total));
    }

    public function test_one_behind_branch_is_offered_on_its_own(): void
    {
        [$owner] = $this->account(1);

        $this->actingAs($owner)->get(route('admin.subscription.index'))
            ->assertOk()
            ->assertSee('Wing 1 is not covered to')
            ->assertDontSee('@click="openAlign()"', false);
    }

    public function test_nothing_to_do_means_no_next_step_card(): void
    {
        [$owner] = $this->account(0);

        $this->actingAs($owner)->get(route('admin.subscription.index'))
            ->assertOk()
            ->assertDontSee('os-steps os-rise', false);   // the card itself is not rendered
    }

    public function test_payments_show_money_and_free_grants_but_never_internal_adjustments(): void
    {
        [$owner, $account, $first] = $this->account(0);
        $adj = SubscriptionOrder::create(['account_id' => $account->id, 'period' => 'yearly', 'kind' => 'adjustment', 'quantity' => 1,
            'subtotal' => 0, 'discount_total' => 0, 'amount' => 0, 'payment_status' => 'paid', 'collection' => 'offline', 'remarks' => 'internal']);
        SubscriptionOrderLine::create(['order_id' => $adj->id, 'branch_id' => $first->id, 'amount' => 0, 'start_date' => now(), 'end_date' => now()]);
        $this->billing->extendRenewalDate($account->fresh(), 1, 'months', 'goodwill');   // a free grant

        $paid = SubscriptionOrder::where('kind', 'renewal')->sole();
        $html = $this->actingAs($owner)->get(route('admin.subscription.index'))->assertOk();

        $html->assertSee('Payments &amp; receipts', false)
            ->assertSee('Free')
            ->assertSee(route('admin.subscription.receipt', $paid), false)
            ->assertDontSee('Adjustment');
    }

    public function test_the_add_branch_review_shows_the_price_before_anything_is_added(): void
    {
        [$owner, $account] = $this->account(0);
        $q = $this->billing->quoteAddBranch($account, null);

        $this->actingAs($owner)->get(route('admin.subscription.index'))
            ->assertOk()
            ->assertSee('"mode":"prorate"', false)
            ->assertSee('"final":'.json_encode(round((float) $q['breakdown']['final'], 2)), false);
    }

    // ── Receipts ─────────────────────────────────────────────────────────

    public function test_the_owner_downloads_a_receipt_for_their_payment(): void
    {
        [$owner] = $this->account(0);
        $paid = SubscriptionOrder::where('kind', 'renewal')->sole();

        $this->actingAs($owner)->get(route('admin.subscription.receipt', $paid))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_receipts_of_another_account_a_pending_charge_or_a_free_grant_are_not_found(): void
    {
        [$owner, $account] = $this->account(0);
        $this->account(0, '9866600077');
        $theirs = SubscriptionOrder::where('kind', 'renewal')->where('account_id', '!=', $account->id)->sole();
        $pending = $this->billing->renewAccount($account->fresh(), 'yearly', ['payment_status' => 'pending']);
        $free = $this->billing->extendRenewalDate($account->fresh(), 1, 'months', 'gift');

        foreach ([$theirs, $pending, $free] as $order) {
            $this->actingAs($owner)->get(route('admin.subscription.receipt', $order))->assertNotFound();
        }
    }
}
