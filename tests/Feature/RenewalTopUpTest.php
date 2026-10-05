<?php

namespace Tests\Feature;

use App\Models\DiscountRule;
use App\Models\Hostel;
use App\Models\Notification;
use App\Models\SubscriptionAccount;
use App\Models\SubscriptionOrder;
use App\Models\User;
use App\Services\Billing\AccountBillingService;
use App\Services\Billing\PaymentSettlement;
use App\Services\HostelService;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Renew all no longer gives a branch behind the renewal date free time (found
 * 2026-10-05: a branch added 182 days before the anchor rode free, ₹4,986), and a
 * branch added during a running trial joins that trial.
 *
 * Every fixture here is built through the billing service, so coverage is backed by
 * the ledger exactly as in production. Design: 21_RENEWAL_TOPUPS.md.
 */
class RenewalTopUpTest extends TestCase
{
    use RefreshDatabase;

    private AccountBillingService $billing;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::clear();
        $this->billing = app(AccountBillingService::class);
        config([
            'services.razorpay.enabled' => true, 'services.razorpay.key' => 'rzp_test_key',
            'services.razorpay.secret' => 'rzp_test_secret', 'services.razorpay.webhook_secret' => 'whsec_t',
            'hostelease.owner_self_serve' => true,
        ]);
    }

    /** An owner with one branch on a PAID yearly term bought six months ago (anchor ~6 months out). */
    private function paidAccount(string $mobile = '9811100001'): array
    {
        $owner = User::factory()->create(['role' => 'hostel_admin', 'mobile' => $mobile]);
        $first = Hostel::factory()->create(['owner_id' => $owner->id, 'mobile' => $mobile, 'subscription_start' => null, 'subscription_end' => null]);
        $owner->hostels()->sync([$first->id]);
        $owner->forceFill(['hostel_id' => $first->id])->save();
        $account = $this->billing->accountFor($owner);

        $this->travel(-6)->months();
        $this->billing->renewAccount($account->fresh(), 'yearly', ['payment_status' => 'paid', 'payment_method' => 'cash']);
        $this->travelBack();

        return [$owner, $account->fresh(), $first];
    }

    /** An owner whose first branch is on the account's free trial. */
    private function trialAccount(string $mobile = '9811100002'): array
    {
        $owner = User::factory()->create(['role' => 'hostel_admin', 'mobile' => $mobile]);
        $first = Hostel::factory()->create(['owner_id' => $owner->id, 'mobile' => $mobile, 'subscription_start' => null, 'subscription_end' => null]);
        $owner->hostels()->sync([$first->id]);
        $owner->forceFill(['hostel_id' => $first->id])->save();
        $this->billing->recordBranchRenewal($first, 'trial', ['payment_status' => 'paid']);

        return [$owner, SubscriptionAccount::where('owner_id', $owner->id)->sole(), $first];
    }

    private function addBranch(User $owner, string $name = 'New Wing'): Hostel
    {
        return app(HostelService::class)->createBranchForOwner($owner, ['name' => $name]);
    }

    // ── paid accounts: the leak ──────────────────────────────────────────

    public function test_renew_all_charges_a_behind_branch_its_stretch_up_to_the_renewal_date(): void
    {
        [$owner, $account] = $this->paidAccount();
        $new = $this->addBranch($owner);
        $fair = $this->billing->quoteAddBranch($account, $new);   // what "Add to plan" charges

        $q = $this->billing->quoteRenewal($account, 'yearly');

        $this->assertCount(1, $q['topups']);
        $this->assertSame($new->id, $q['topups'][0]['branch']->id);
        $this->assertEqualsWithDelta($fair['prorated'], $q['topup_total'], 0.001, 'Same price as Add to plan (before any one-time discount).');
        $this->assertEqualsWithDelta(2 * 10000 + $fair['prorated'], $q['total'], 0.001);
    }

    public function test_the_renewal_order_adds_up_and_shows_what_the_top_up_bought(): void
    {
        [$owner, $account] = $this->paidAccount();
        $new = $this->addBranch($owner);
        $anchor = $account->current_period_end->copy();
        $q = $this->billing->quoteRenewal($account, 'yearly');

        $order = $this->billing->renewAccount($account, 'yearly', ['payment_status' => 'paid', 'payment_method' => 'cash']);

        $this->assertEqualsWithDelta($q['total'], (float) $order->amount, 0.001);
        $this->assertEqualsWithDelta((float) $order->subtotal - (float) $order->discount_total, (float) $order->amount, 0.001, 'subtotal − discount = amount');
        $this->assertEqualsWithDelta((float) $order->amount, (float) $order->lines()->sum('amount'), 0.001, 'Σ lines = amount');
        $this->assertSame(2, $order->quantity);

        $topUp = $order->topUpLines()->sole();
        $this->assertSame($new->id, $topUp->branch_id);
        $this->assertSame(now()->toDateString(), $topUp->start_date->toDateString());
        $this->assertSame($anchor->toDateString(), $topUp->end_date->toDateString(), 'The top-up runs up to the CURRENT renewal date.');

        $this->assertTrue($new->fresh()->isActive());
        $this->assertSame($anchor->copy()->addYear()->toDateString(), $new->fresh()->subscription_end->toDateString());
    }

    public function test_a_pending_renewal_with_a_top_up_grants_nothing_until_paid_and_keeps_the_cycle_start_right(): void
    {
        [$owner, $account] = $this->paidAccount();
        $new = $this->addBranch($owner);
        $anchor = $account->current_period_end->copy();

        $order = $this->billing->renewAccount($account, 'yearly', ['payment_status' => 'pending']);
        $this->assertFalse($new->fresh()->isActive(), 'Unpaid: still inactive.');

        $this->billing->acceptOrder($order->fresh(), ['payment_method' => 'upi']);

        $account->refresh();
        $this->assertSame($anchor->toDateString(), $account->current_period_start->toDateString(),
            'The new cycle starts at the old anchor — not where the top-up started.');
        $this->assertSame($anchor->copy()->addYear()->toDateString(), $account->current_period_end->toDateString());
        $this->assertTrue($new->fresh()->isActive());
    }

    public function test_discounts_apply_to_the_term_and_the_top_up_is_charged_as_align_would(): void
    {
        DiscountRule::create(['min_quantity' => 2, 'type' => 'percentage', 'value' => 10, 'active' => true]);
        [$owner, $account] = $this->paidAccount();
        $new = $this->addBranch($owner);

        $q = $this->billing->quoteRenewal($account, 'yearly');
        $align = $this->billing->quoteAlign($account);

        $this->assertEqualsWithDelta(2000, $q['breakdown']['volume_amount'], 0.001, '10% of the term (2 × ₹10,000) only');
        $this->assertEqualsWithDelta($align['subtotal'], $q['topup_total'], 0.001, 'Top-up = Align\'s price for the same branch');
        $this->assertEqualsWithDelta(18000 + $align['subtotal'], $q['total'], 0.001);
    }

    public function test_an_operator_override_scales_every_line_and_still_adds_up(): void
    {
        [$owner, $account] = $this->paidAccount();
        $this->addBranch($owner);
        $q = $this->billing->quoteRenewal($account, 'yearly');

        $order = $this->billing->renewAccount($account, 'yearly', ['payment_status' => 'paid', 'payment_method' => 'cash', 'amount' => 20000]);

        $this->assertEqualsWithDelta(20000, (float) $order->amount, 0.001);
        $this->assertEqualsWithDelta(20000, (float) $order->lines()->sum('amount'), 0.001);
        $this->assertEqualsWithDelta($q['total'] - 20000, (float) $order->discount_total, 0.01, 'The reduction is recorded as discount.');

        $this->expectException(\RuntimeException::class);
        $this->billing->renewAccount($account->fresh(), 'yearly', ['payment_status' => 'paid', 'amount' => $q['total'] * 3]);
    }

    public function test_an_aligned_account_pays_exactly_what_it_did_before(): void
    {
        [, $account] = $this->paidAccount();

        $q = $this->billing->quoteRenewal($account, 'yearly');

        $this->assertSame([], $q['topups']);
        $this->assertEqualsWithDelta(10000, $q['total'], 0.001);
    }

    public function test_an_expired_account_renews_every_branch_from_today_with_no_top_up(): void
    {
        [$owner, $account] = $this->paidAccount();
        $this->addBranch($owner);
        $this->travel(8)->months();
        $this->billing->refreshAccountAnchor($account->fresh());

        $q = $this->billing->quoteRenewal($account->fresh(), 'yearly');

        $this->assertSame([], $q['topups'], 'No live anchor to catch up to.');
        $this->assertSame(now()->addYear()->toDateString(), $q['new_anchor']->toDateString());
    }

    // ── a top-up paid another way first ──────────────────────────────────

    public function test_a_renewal_whose_top_up_was_paid_separately_is_stale_and_not_offered(): void
    {
        [$owner, $account] = $this->paidAccount();
        $new = $this->addBranch($owner);
        $renewal = $this->billing->renewAccount($account, 'yearly', ['payment_status' => 'pending', 'collection' => 'checkout']);
        $this->assertSame('extends', $renewal->coverageState(true));

        // The owner pays "Add to plan" for that branch first.
        $this->billing->addBranch($account->fresh(), $new->fresh(), ['payment_status' => 'paid', 'payment_method' => 'cash']);

        $this->assertSame('stale', $renewal->coverageState(true));
        $this->assertFalse($renewal->wouldExtendCoverage(true));

        // The owner's page offers no Pay for it, and says why.
        $this->actingAs($owner)->get(route('admin.subscription.index'))
            ->assertSee('Amount changed — renew again for the new total');

        // Renewing again re-quotes WITHOUT the top-up — no double charge.
        Http::fake([
            'api.razorpay.com/v1/orders' => Http::response(['id' => 'order_NEW', 'amount' => 1, 'currency' => 'INR', 'receipt' => 'r'], 200),
            'api.razorpay.com/v1/orders/*/payments' => Http::response(['items' => []], 200),
        ]);
        $this->actingAs($owner)->postJson(route('admin.subscription.checkout'), ['charge' => 'renewal', 'period' => 'yearly'])
            ->assertOk()->assertJsonPath('razorpay.amount', 2000000);
        $this->assertSame('voided', $renewal->fresh()->payment_status->value, 'The stale attempt is cleared.');
    }

    public function test_a_stale_renewal_paid_anyway_lands_and_raises_a_refund_alert_for_the_double_top_up(): void
    {
        [$owner, $account] = $this->paidAccount();
        $new = $this->addBranch($owner);
        $renewal = $this->billing->renewAccount($account, 'yearly', ['payment_status' => 'pending', 'collection' => 'link']);
        $topUp = (float) $renewal->topUpLines()->sole()->amount;
        $this->billing->addBranch($account->fresh(), $new->fresh(), ['payment_status' => 'paid', 'payment_method' => 'cash']);

        app(PaymentSettlement::class)->settle($renewal->fresh(), 'pay_old_link', $renewal->amountPaise(), 'payment link', 'webhook');

        $this->assertSame('paid', $renewal->fresh()->payment_status->value, 'Money is never refused — it is recorded.');
        $alert = Notification::where('type', 'payment_no_coverage')->sole();
        $this->assertStringContainsString('refund '.hostelease_money($topUp), $alert->title);
    }

    // ── trial accounts ───────────────────────────────────────────────────

    public function test_a_branch_added_during_the_trial_joins_it_free_until_it_ends(): void
    {
        [$owner, $account] = $this->trialAccount();

        $this->actingAs($owner)->postJson(route('admin.subscription.add-branch'), ['name' => 'Day Four Wing'])
            ->assertOk()
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'added to your free trial'));

        $wing = Hostel::where('name', 'Day Four Wing')->sole();
        $this->assertTrue($wing->isActive(), 'It works straight away.');
        $this->assertSame($account->current_period_end->toDateString(), $wing->subscription_end->toDateString(), 'Until the SAME trial end — not a new 14 days.');
        $this->assertSame(0.0, (float) SubscriptionOrder::where('kind', 'trial')->latest('id')->first()->amount);
        $this->assertSame('trial', $account->fresh()->period->value);
    }

    public function test_subscribing_bills_every_branch_from_the_trial_end_with_no_top_up(): void
    {
        [$owner, $account] = $this->trialAccount();
        $this->billing->recordBranchRenewal($this->addBranch($owner), 'trial', ['payment_status' => 'paid']);

        $q = $this->billing->quoteRenewal($account->fresh(), 'yearly');

        $this->assertSame([], $q['topups'], 'Trial time is shared, never charged.');
        $this->assertSame(2, $q['quantity']);
        $this->assertSame($account->current_period_end->copy()->addYear()->toDateString(), $q['new_anchor']->toDateString());
    }

    public function test_after_the_trial_ends_a_new_branch_waits_to_be_paid_for(): void
    {
        [$owner, $account] = $this->trialAccount();
        $this->travel(20)->days();
        $this->billing->refreshAccountAnchor($account->fresh());

        $this->actingAs($owner)->postJson(route('admin.subscription.add-branch'), ['name' => 'Late Wing'])
            ->assertOk()
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'becomes active when you subscribe'));

        $this->assertFalse(Hostel::where('name', 'Late Wing')->sole()->isActive());
    }

    public function test_the_operator_can_add_a_hostel_into_a_running_trial(): void
    {
        [, $account] = $this->trialAccount();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->post(route('superadmin.accounts.add-hostel', $account), ['name' => 'Operator Wing', 'plan' => 'trial'])
            ->assertSessionHas('success');

        $this->assertSame($account->current_period_end->toDateString(), Hostel::where('name', 'Operator Wing')->sole()->subscription_end->toDateString());
    }

    // ── per-branch terms use the account's own price ─────────────────────

    public function test_a_single_branch_term_uses_the_accounts_negotiated_price_not_the_list_price(): void
    {
        [$owner, $account] = $this->paidAccount();
        $account->update(['unit_price_override_yearly' => 7000]);
        $this->travel(8)->months();   // no live cycle: addBranch falls back to a full term
        $this->billing->refreshAccountAnchor($account->fresh());

        $order = $this->billing->addBranch($account->fresh(), $this->addBranch($owner), ['payment_status' => 'paid', 'payment_method' => 'cash']);

        $this->assertEqualsWithDelta(7000, (float) $order->amount, 0.001);
    }
}
