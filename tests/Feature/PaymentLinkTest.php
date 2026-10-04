<?php

namespace Tests\Feature;

use App\Enums\DiscountStatus;
use App\Models\Discount;
use App\Models\Hostel;
use App\Models\SubscriptionAccount;
use App\Models\SubscriptionOrder;
use App\Models\User;
use App\Services\Billing\AccountBillingService;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * S2 — operator-initiated collection via Razorpay Payment Links.
 *
 * Design + the breaking-point register these pin down:
 * _artifact/saas_billing_autopay/10_S2_DESIGN.md.
 *
 * Razorpay is always faked here. The one thing no test can prove is a real
 * test-mode link paid on a real phone, which is why 11_S2_MANUAL_TESTING.md
 * exists alongside this file.
 */
class PaymentLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::clear();

        config([
            'services.razorpay.enabled' => true,
            'services.razorpay.key' => 'rzp_test_key',
            'services.razorpay.secret' => 'rzp_test_secret',
            'hostelease.payment_links.expiry_days' => 7,
        ]);
    }

    /** @return array{0:User,1:SubscriptionAccount,2:Hostel,3:Hostel} */
    protected function seedAccount(array $ownerAttributes = []): array
    {
        $owner = User::factory()->create(array_merge([
            'role' => 'hostel_admin', 'mobile' => '9000000011', 'name' => 'Link Owner', 'email' => 'owner@example.test',
        ], $ownerAttributes));

        $b1 = Hostel::factory()->create(['mobile' => '9000000011', 'owner_id' => $owner->id, 'status' => 'active', 'subscription_end' => now()->addMonths(3)]);
        $b2 = Hostel::factory()->create(['mobile' => '9000000011', 'owner_id' => $owner->id, 'status' => 'active', 'subscription_end' => now()->addMonths(3)]);
        $owner->hostels()->sync([$b1->id, $b2->id]);

        $account = SubscriptionAccount::create([
            'owner_id' => $owner->id, 'period' => 'yearly', 'status' => 'active', 'current_period_end' => now()->addMonths(3),
        ]);

        return [$owner, $account, $b1, $b2];
    }

    protected function superAdmin(): User
    {
        return User::factory()->superAdmin()->create();
    }

    /** A successful Razorpay create-link response. */
    protected function fakeLinkCreated(string $id = 'plink_TEST1', string $url = 'https://rzp.io/i/testlink'): void
    {
        Http::fake([
            'api.razorpay.com/v1/payment_links' => Http::response([
                'id' => $id,
                'short_url' => $url,
                'status' => 'created',
                'amount' => 2000000,
                'reference_id' => 'ref',
                'expire_by' => now()->addDays(7)->getTimestamp(),
            ], 200),
        ]);
    }

    // =================================================================
    // Issuing
    // =================================================================

    public function test_renewing_with_collect_link_creates_a_pending_order_and_a_link(): void
    {
        [, $account] = $this->seedAccount();
        $this->fakeLinkCreated();

        $this->actingAs($this->superAdmin())
            ->post(route('superadmin.accounts.renew', $account), ['period' => 'yearly', 'collect' => 'link'])
            ->assertRedirect()
            ->assertSessionHas('payment_link');

        $order = SubscriptionOrder::latest('id')->first();

        $this->assertSame('pending', $order->payment_status->value);
        $this->assertSame('link', $order->collection->value);
        $this->assertSame('plink_TEST1', $order->payment_link_id);
        $this->assertSame('https://rzp.io/i/testlink', $order->payment_link_url);
        $this->assertSame('created', $order->payment_link_status->value);
        $this->assertSame(1, $order->payment_link_attempts);
        $this->assertSame($order->public_id, $order->payment_link_ref);

        // A pending charge has no instrument yet — stamping it "Cash" would be a lie
        // on the order right up until Razorpay fills the real one in.
        $this->assertNull($order->payment_method);
    }

    public function test_the_link_amount_is_the_server_computed_quote_to_the_paise(): void
    {
        [, $account] = $this->seedAccount();
        $this->fakeLinkCreated();

        // Two branches × ₹10,000 = ₹20,000 = 2,000,000 paise.
        $this->actingAs($this->superAdmin())
            ->post(route('superadmin.accounts.renew', $account), ['period' => 'yearly', 'collect' => 'link']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/payment_links')
                && $request['amount'] === 2000000
                && $request['currency'] === 'INR';
        });
    }

    public function test_an_amount_override_is_what_the_link_charges(): void
    {
        [, $account] = $this->seedAccount();
        $this->fakeLinkCreated();

        $this->actingAs($this->superAdmin())
            ->post(route('superadmin.accounts.renew', $account), ['period' => 'yearly', 'collect' => 'link', 'amount' => 17500]);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/payment_links') && $request['amount'] === 1750000);
    }

    /**
     * Design §6 BP8. Razorpay errors outright if notify.email is true with no
     * email, so a customer with only a mobile would never get a link at all.
     */
    public function test_email_notification_is_off_when_the_owner_has_no_email(): void
    {
        [, $account] = $this->seedAccount(['email' => null]);
        $this->fakeLinkCreated();

        $this->actingAs($this->superAdmin())
            ->post(route('superadmin.accounts.renew', $account), ['period' => 'yearly', 'collect' => 'link']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/payment_links')
                && $request['notify']['email'] === false
                && $request['notify']['sms'] === true;
        });
    }

    /**
     * Design §6 BP1 — the expensive one. Link notes must never use the key names
     * the legacy `order.paid` arm reads, or one payment could be applied twice.
     */
    public function test_link_notes_never_use_the_legacy_branch_note_keys(): void
    {
        [, $account] = $this->seedAccount();
        $this->fakeLinkCreated();

        $this->actingAs($this->superAdmin())
            ->post(route('superadmin.accounts.renew', $account), ['period' => 'yearly', 'collect' => 'link']);

        Http::assertSent(function ($request) {
            $notes = $request['notes'];

            return str_contains($request->url(), '/payment_links')
                && ($notes['type'] ?? null) === 'payment_link'
                && ! array_key_exists('branch_id', $notes)
                && ! array_key_exists('period', $notes)
                && filled($notes['he_order_id']);
        });
    }

    /**
     * Design §6 BP4. The Razorpay call runs inside the order's own transaction, so
     * a gateway failure must leave NO pending order behind to sit in receivables
     * as money nobody was ever asked for.
     */
    public function test_a_razorpay_failure_leaves_no_orphan_order(): void
    {
        [, $account] = $this->seedAccount();

        Http::fake([
            'api.razorpay.com/v1/payment_links' => Http::response(['error' => ['description' => 'Gateway said no']], 400),
        ]);

        $before = SubscriptionOrder::count();

        $this->actingAs($this->superAdmin())
            ->post(route('superadmin.accounts.renew', $account), ['period' => 'yearly', 'collect' => 'link'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame($before, SubscriptionOrder::count());

        // And the account clock did not move either.
        $this->assertTrue($account->fresh()->current_period_end->lessThan(now()->addMonths(4)));
    }

    public function test_collect_link_is_refused_when_razorpay_is_not_configured(): void
    {
        config(['services.razorpay.enabled' => false]);
        [, $account] = $this->seedAccount();

        $this->actingAs($this->superAdmin())
            ->post(route('superadmin.accounts.renew', $account), ['period' => 'yearly', 'collect' => 'link'])
            ->assertSessionHas('error');

        $this->assertSame(0, SubscriptionOrder::count());
    }

    /** Design §6 BP3 — two live renewal links for one account are always duplicates. */
    public function test_a_second_live_renewal_link_is_refused(): void
    {
        [, $account] = $this->seedAccount();
        $this->fakeLinkCreated();

        $this->actingAs($this->superAdmin())
            ->post(route('superadmin.accounts.renew', $account), ['period' => 'yearly', 'collect' => 'link']);

        $this->assertSame(1, SubscriptionOrder::count());

        $this->actingAs($this->superAdmin())
            ->post(route('superadmin.accounts.renew', $account), ['period' => 'yearly', 'collect' => 'link'])
            ->assertSessionHas('error');

        // The refusal rolled the second order back with its transaction.
        $this->assertSame(1, SubscriptionOrder::count());
    }

    public function test_a_charge_that_is_already_paid_cannot_be_given_a_link(): void
    {
        [, $account] = $this->seedAccount();
        $this->fakeLinkCreated();

        // Record the renewal offline — the order is paid.
        $this->actingAs($this->superAdmin())
            ->post(route('superadmin.accounts.renew', $account), ['period' => 'yearly']);

        $order = SubscriptionOrder::latest('id')->first();
        $this->assertSame('paid', $order->payment_status->value);

        $this->actingAs($this->superAdmin())
            ->post(route('superadmin.accounts.orders.link.issue', $account), ['order_id' => $order->id])
            ->assertSessionHas('error');

        $this->assertNull($order->fresh()->payment_link_id);
    }

    public function test_an_offline_proforma_can_be_given_a_link_later(): void
    {
        [, $account, $b1] = $this->seedAccount();
        $this->fakeLinkCreated('plink_LATER');

        // A pending charge with no link, as recordBranchRenewal leaves one.
        $order = app(AccountBillingService::class)
            ->recordBranchRenewal($b1, 'yearly', ['payment_status' => 'pending']);

        $this->actingAs($this->superAdmin())
            ->post(route('superadmin.accounts.orders.link.issue', $account), ['order_id' => $order->id])
            ->assertSessionHas('payment_link');

        $this->assertSame('plink_LATER', $order->fresh()->payment_link_id);
    }

    // =================================================================
    // Lifecycle
    // =================================================================

    /** Design §6 BP2 — the double-payment path, and the most expensive bug S2 could ship. */
    public function test_accepting_a_charge_offline_cancels_its_live_link(): void
    {
        [, $account] = $this->seedAccount();
        $this->fakeLinkCreated('plink_KILL');

        $this->actingAs($this->superAdmin())
            ->post(route('superadmin.accounts.renew', $account), ['period' => 'yearly', 'collect' => 'link']);

        $order = SubscriptionOrder::latest('id')->first();

        Http::fake([
            'api.razorpay.com/v1/payment_links/plink_KILL/cancel' => Http::response(['id' => 'plink_KILL', 'status' => 'cancelled'], 200),
        ]);

        $this->actingAs($this->superAdmin())
            ->patch(route('superadmin.accounts.orders.accept', [$account, $order]), ['payment_method' => 'rtgs'])
            ->assertSessionHas('success');

        Http::assertSent(fn ($r) => str_contains($r->url(), 'plink_KILL/cancel'));

        $order->refresh();
        $this->assertSame('paid', $order->payment_status->value);
        $this->assertSame('cancelled', $order->payment_link_status->value);
    }

    public function test_voiding_a_charge_cancels_its_live_link(): void
    {
        [, $account] = $this->seedAccount();
        $this->fakeLinkCreated('plink_VOID');

        $this->actingAs($this->superAdmin())
            ->post(route('superadmin.accounts.renew', $account), ['period' => 'yearly', 'collect' => 'link']);

        $order = SubscriptionOrder::latest('id')->first();

        Http::fake([
            'api.razorpay.com/v1/payment_links/plink_VOID/cancel' => Http::response(['id' => 'plink_VOID', 'status' => 'cancelled'], 200),
        ]);

        $this->actingAs($this->superAdmin())
            ->patch(route('superadmin.accounts.orders.void', $account), ['order_id' => $order->id, 'reason' => 'quoted the wrong customer'])
            ->assertSessionHas('success');

        Http::assertSent(fn ($r) => str_contains($r->url(), 'plink_VOID/cancel'));
        $this->assertSame('cancelled', $order->fresh()->payment_link_status->value);
    }

    /**
     * Razorpay refuses to cancel a link it considers finished. The operator's
     * action must still succeed — they cannot control upstream state — and the
     * real status gets recorded instead of being assumed away.
     */
    public function test_a_refused_cancellation_does_not_break_the_operators_action(): void
    {
        [, $account] = $this->seedAccount();
        $this->fakeLinkCreated('plink_STUCK');

        $this->actingAs($this->superAdmin())
            ->post(route('superadmin.accounts.renew', $account), ['period' => 'yearly', 'collect' => 'link']);

        $order = SubscriptionOrder::latest('id')->first();

        Http::fake([
            'api.razorpay.com/v1/payment_links/plink_STUCK/cancel' => Http::response(['error' => ['description' => 'Already paid']], 400),
            'api.razorpay.com/v1/payment_links/plink_STUCK' => Http::response(['id' => 'plink_STUCK', 'status' => 'paid', 'amount' => 2000000, 'amount_paid' => 2000000, 'payments' => []], 200),
        ]);

        $this->actingAs($this->superAdmin())
            ->patch(route('superadmin.accounts.orders.accept', [$account, $order]), ['payment_method' => 'cash'])
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertSame('paid', $order->payment_status->value);
        // Razorpay's real answer was read back rather than guessed.
        $this->assertSame('paid', $order->payment_link_status->value);
    }

    public function test_a_lapsed_link_can_be_reissued_with_a_fresh_reference(): void
    {
        [, $account] = $this->seedAccount();

        // A SEQUENCE, not two Http::fake() calls: a second fake() appends its stub
        // rather than replacing, so the first match would keep winning and the
        // re-issue would appear to return the original link.
        Http::fake([
            'api.razorpay.com/v1/payment_links' => Http::sequence()
                ->push(['id' => 'plink_ONE', 'short_url' => 'https://rzp.io/i/first', 'status' => 'created', 'amount' => 2000000])
                ->push(['id' => 'plink_TWO', 'short_url' => 'https://rzp.io/i/second', 'status' => 'created', 'amount' => 2000000]),
        ]);

        $this->actingAs($this->superAdmin())
            ->post(route('superadmin.accounts.renew', $account), ['period' => 'yearly', 'collect' => 'link']);

        $order = SubscriptionOrder::latest('id')->first();
        $this->assertSame('plink_ONE', $order->payment_link_id);

        $order->update(['payment_link_status' => 'expired']);

        $this->actingAs($this->superAdmin())
            ->post(route('superadmin.accounts.orders.link.issue', $account), ['order_id' => $order->id])
            ->assertSessionHas('payment_link');

        $order->refresh();
        $this->assertSame('plink_TWO', $order->payment_link_id);
        $this->assertSame(2, $order->payment_link_attempts);
        // reference_id is UNIQUE at Razorpay, so a re-issue MUST vary it.
        $this->assertSame($order->public_id.'-2', $order->payment_link_ref);
    }

    /**
     * Design §6 BP6 — the likeliest production failure. A paid link whose webhook
     * never arrived must be recoverable with one button.
     */
    public function test_check_with_razorpay_applies_a_payment_the_webhook_missed(): void
    {
        [, $account, $b1] = $this->seedAccount();
        $this->fakeLinkCreated('plink_MISSED');

        $this->actingAs($this->superAdmin())
            ->post(route('superadmin.accounts.renew', $account), ['period' => 'yearly', 'collect' => 'link']);

        $order = SubscriptionOrder::latest('id')->first();
        $coverageBefore = $b1->fresh()->subscription_end;

        Http::fake([
            'api.razorpay.com/v1/payment_links/plink_MISSED' => Http::response([
                'id' => 'plink_MISSED',
                'status' => 'paid',
                'amount' => 2000000,
                'amount_paid' => 2000000,
                'payments' => [[
                    'payment_id' => 'pay_recovered', 'amount' => 2000000, 'status' => 'captured', 'method' => 'upi',
                ]],
            ], 200),
        ]);

        $this->actingAs($this->superAdmin())
            ->post(route('superadmin.accounts.orders.link.check', $account), ['order_id' => $order->id])
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertSame('paid', $order->payment_status->value);
        $this->assertSame('pay_recovered', $order->transaction_number);
        $this->assertSame('paid', $order->payment_link_status->value);

        // And coverage actually moved — the whole point.
        $this->assertTrue($b1->fresh()->subscription_end->greaterThan($coverageBefore));
    }

    public function test_check_with_razorpay_reports_an_unpaid_link_without_changing_anything(): void
    {
        [, $account] = $this->seedAccount();
        $this->fakeLinkCreated('plink_OPEN');

        $this->actingAs($this->superAdmin())
            ->post(route('superadmin.accounts.renew', $account), ['period' => 'yearly', 'collect' => 'link']);

        $order = SubscriptionOrder::latest('id')->first();

        Http::fake([
            'api.razorpay.com/v1/payment_links/plink_OPEN' => Http::response([
                'id' => 'plink_OPEN', 'status' => 'created', 'amount' => 2000000, 'amount_paid' => 0, 'payments' => [],
            ], 200),
        ]);

        $this->actingAs($this->superAdmin())
            ->post(route('superadmin.accounts.orders.link.check', $account), ['order_id' => $order->id])
            ->assertSessionHas('info');

        $this->assertSame('pending', $order->fresh()->payment_status->value);
    }

    public function test_resend_by_email_is_refused_when_the_owner_has_no_email(): void
    {
        [, $account] = $this->seedAccount(['email' => null]);
        $this->fakeLinkCreated('plink_NOMAIL');

        $this->actingAs($this->superAdmin())
            ->post(route('superadmin.accounts.renew', $account), ['period' => 'yearly', 'collect' => 'link']);

        $order = SubscriptionOrder::latest('id')->first();

        $this->actingAs($this->superAdmin())
            ->post(route('superadmin.accounts.orders.link.resend', $account), ['order_id' => $order->id, 'medium' => 'email'])
            ->assertSessionHas('error');
    }

    // =================================================================
    // The §5 regressions — latent bugs that only bite once orders can be pending
    // =================================================================

    /**
     * Design §5.1, and the single worst thing S2 could have shipped: an unpaid
     * renewal pushing the account's renewal date a year forward and painting the
     * badge green, so the customer stops being chased for money they owe.
     */
    public function test_a_pending_renewal_does_not_move_the_account_clock(): void
    {
        [, $account] = $this->seedAccount();
        $this->fakeLinkCreated();

        $anchorBefore = $account->current_period_end->toDateString();
        $statusBefore = $account->status->value;

        $this->actingAs($this->superAdmin())
            ->post(route('superadmin.accounts.renew', $account), ['period' => 'yearly', 'collect' => 'link']);

        $account->refresh();
        $this->assertSame($anchorBefore, $account->current_period_end->toDateString());
        $this->assertSame($statusBefore, $account->status->value);
    }

    public function test_accepting_a_pending_renewal_advances_the_whole_cycle(): void
    {
        [, $account] = $this->seedAccount();
        $this->fakeLinkCreated('plink_CYCLE');

        $oldAnchor = $account->current_period_end->copy();

        $this->actingAs($this->superAdmin())
            ->post(route('superadmin.accounts.renew', $account), ['period' => 'yearly', 'collect' => 'link']);

        $order = SubscriptionOrder::latest('id')->first();

        Http::fake([
            'api.razorpay.com/v1/payment_links/plink_CYCLE/cancel' => Http::response(['id' => 'plink_CYCLE', 'status' => 'cancelled'], 200),
        ]);

        $this->actingAs($this->superAdmin())
            ->patch(route('superadmin.accounts.orders.accept', [$account, $order]), ['payment_method' => 'rtgs']);

        $account->refresh();

        // The anchor moved a year from the OLD anchor (never lose paid time, BR-9).
        $this->assertSame($oldAnchor->copy()->addYear()->toDateString(), $account->current_period_end->toDateString());
        // And the cycle START came with it — finding F15, reached down the link path.
        $this->assertSame($oldAnchor->toDateString(), $account->current_period_start->toDateString());
    }

    /** Design §5.1 — a monthly link paid on a yearly account must switch the cadence. */
    public function test_accepting_a_monthly_link_switches_the_account_to_monthly(): void
    {
        [, $account] = $this->seedAccount();
        $this->fakeLinkCreated('plink_MONTH');

        $this->assertSame('yearly', $account->period->value);

        $this->actingAs($this->superAdmin())
            ->post(route('superadmin.accounts.renew', $account), ['period' => 'monthly', 'collect' => 'link']);

        $order = SubscriptionOrder::latest('id')->first();

        Http::fake([
            'api.razorpay.com/v1/payment_links/plink_MONTH/cancel' => Http::response(['id' => 'plink_MONTH', 'status' => 'cancelled'], 200),
        ]);

        $this->actingAs($this->superAdmin())
            ->patch(route('superadmin.accounts.orders.accept', [$account, $order]), ['payment_method' => 'upi']);

        $this->assertSame('monthly', $account->fresh()->period->value);
    }

    /**
     * Design §5.2. A one-shot negotiated discount used to be marked Consumed at
     * quote time, so a link nobody paid took the discount with it and the re-issue
     * was full price.
     */
    public function test_a_one_shot_discount_survives_an_unpaid_link_and_is_consumed_on_payment(): void
    {
        [, $account] = $this->seedAccount();
        $this->fakeLinkCreated('plink_DISC');

        $discount = Discount::create([
            'account_id' => $account->id,
            'type' => 'percentage',
            'value' => 10,
            'recurrence' => 'one_time',
            'status' => 'active',
            'reason' => 'negotiated',
        ]);

        $this->actingAs($this->superAdmin())
            ->post(route('superadmin.accounts.renew', $account), ['period' => 'yearly', 'collect' => 'link']);

        $order = SubscriptionOrder::latest('id')->first();

        // Still available: nobody has paid anything yet.
        $this->assertSame(DiscountStatus::Active, $discount->fresh()->status);
        // And the order remembers which discount it priced against.
        $this->assertSame($discount->id, $order->manual_discount_id);

        Http::fake([
            'api.razorpay.com/v1/payment_links/plink_DISC/cancel' => Http::response(['id' => 'plink_DISC', 'status' => 'cancelled'], 200),
        ]);

        $this->actingAs($this->superAdmin())
            ->patch(route('superadmin.accounts.orders.accept', [$account, $order]), ['payment_method' => 'cash']);

        $this->assertSame(DiscountStatus::Consumed, $discount->fresh()->status);
    }

    /** An offline charge still consumes its discount immediately — unchanged by S2. */
    public function test_an_offline_renewal_still_consumes_its_discount_at_once(): void
    {
        [, $account] = $this->seedAccount();

        $discount = Discount::create([
            'account_id' => $account->id,
            'type' => 'percentage', 'value' => 10, 'recurrence' => 'one_time', 'status' => 'active', 'reason' => 'negotiated',
        ]);

        $this->actingAs($this->superAdmin())
            ->post(route('superadmin.accounts.renew', $account), ['period' => 'yearly']);

        $this->assertSame(DiscountStatus::Consumed, $discount->fresh()->status);
    }

    /**
     * V8, found in the S2 check pass. `refreshAccountAnchor()` derives the ANCHOR
     * from paid lines — so an unpaid charge cannot move it — but it writes `period`
     * and `status` from whatever period it is handed, and those are NOT
     * ledger-derived. addBranch()/align()/recordBranchRenewal() each handed it a
     * PAID cadence unconditionally, so an unpaid payment link rewrote a TRIAL
     * account to period=yearly / status=active with no money received: it then read
     * as a paying customer in the Customers list, in the renewals worklist and in
     * S5's trial-conversion figures, and its trial-expiry messaging stopped.
     */
    public function test_an_unpaid_link_does_not_promote_a_trial_account_to_paying(): void
    {
        $owner = User::factory()->create(['role' => 'hostel_admin', 'mobile' => '9000000033', 'email' => 'trial@example.test']);
        $live = Hostel::factory()->create(['mobile' => '9000000033', 'owner_id' => $owner->id, 'status' => 'active', 'subscription_end' => now()->addDays(10)]);
        $fresh = Hostel::factory()->create(['mobile' => '9000000033', 'owner_id' => $owner->id, 'status' => 'active', 'subscription_end' => null]);
        $owner->hostels()->sync([$live->id, $fresh->id]);

        $account = SubscriptionAccount::create([
            'owner_id' => $owner->id, 'period' => 'trial', 'status' => 'trial',
            'current_period_end' => now()->addDays(10),
        ]);

        $this->fakeLinkCreated('plink_TRIAL');

        $this->actingAs($this->superAdmin())
            ->post(route('superadmin.accounts.add-branch', $account), [
                'branch_id' => $fresh->id, 'collect' => 'link',
            ])
            ->assertSessionHas('payment_link');

        $account->refresh();
        $this->assertSame('trial', $account->period->value, 'An unpaid link must not change the cadence.');
        $this->assertSame('trial', $account->status->value, 'An unpaid link must not make a trial look like a paying account.');
        $this->assertNull($fresh->fresh()->subscription_end, 'And it must grant no coverage.');
    }

    /** The other half: paying it IS the moment a trial becomes a paying account. */
    public function test_paying_the_link_is_what_promotes_the_trial(): void
    {
        $owner = User::factory()->create(['role' => 'hostel_admin', 'mobile' => '9000000034']);
        $live = Hostel::factory()->create(['mobile' => '9000000034', 'owner_id' => $owner->id, 'status' => 'active', 'subscription_end' => now()->addDays(10)]);
        $fresh = Hostel::factory()->create(['mobile' => '9000000034', 'owner_id' => $owner->id, 'status' => 'active', 'subscription_end' => null]);
        $owner->hostels()->sync([$live->id, $fresh->id]);

        $account = SubscriptionAccount::create([
            'owner_id' => $owner->id, 'period' => 'trial', 'status' => 'trial',
            'current_period_end' => now()->addDays(10),
        ]);

        $this->fakeLinkCreated('plink_PROMOTE');

        $this->actingAs($this->superAdmin())
            ->post(route('superadmin.accounts.add-branch', $account), ['branch_id' => $fresh->id, 'collect' => 'link']);

        $order = SubscriptionOrder::latest('id')->first();

        Http::fake([
            'api.razorpay.com/v1/payment_links/plink_PROMOTE/cancel' => Http::response(['id' => 'plink_PROMOTE', 'status' => 'cancelled'], 200),
        ]);

        $this->actingAs($this->superAdmin())
            ->patch(route('superadmin.accounts.orders.accept', [$account, $order]), ['payment_method' => 'upi']);

        $account->refresh();
        $this->assertSame('yearly', $account->period->value);
        $this->assertSame('active', $account->status->value);
        $this->assertNotNull($fresh->fresh()->subscription_end, 'And the branch is now co-terminated.');
    }

    // =================================================================
    // Form reality + authorization (design §6 BP10)
    // =================================================================

    /**
     * The S1 lesson, pinned. `<x-he-modal ::action="…">` renders a <div> instead of
     * a <form>, so the button silently does nothing and a render test still passes.
     * This asserts the WRAPPER is a real form posting to the real route.
     */
    public function test_the_link_modals_are_real_forms_posting_to_the_right_routes(): void
    {
        [, $account] = $this->seedAccount();
        $this->fakeLinkCreated('plink_FORM');

        $this->actingAs($this->superAdmin())
            ->post(route('superadmin.accounts.renew', $account), ['period' => 'yearly', 'collect' => 'link']);

        $html = $this->actingAs($this->superAdmin())
            ->get(route('superadmin.accounts.show', $account))
            ->assertOk()
            ->getContent();

        $cancelRoute = route('superadmin.accounts.orders.link.cancel', $account);
        $this->assertMatchesRegularExpression(
            '/<form[^>]+action="'.preg_quote($cancelRoute, '/').'"/',
            $html,
            'The cancel-link modal must render as a <form>, not a <div>.',
        );
        $this->assertStringContainsString('name="order_id"', $html);

        // The per-row actions are server-rendered forms too.
        foreach (['resend', 'check'] as $action) {
            $route = route('superadmin.accounts.orders.link.'.$action, $account);
            $this->assertMatchesRegularExpression(
                '/<form[^>]+action="'.preg_quote($route, '/').'"/',
                $html,
                "The {$action} action must post from a real form.",
            );
        }
    }

    public function test_a_posted_order_id_cannot_reach_another_accounts_charge(): void
    {
        [, $accountA] = $this->seedAccount();
        $this->fakeLinkCreated('plink_A');

        $this->actingAs($this->superAdmin())
            ->post(route('superadmin.accounts.renew', $accountA), ['period' => 'yearly', 'collect' => 'link']);

        $ordersOfA = SubscriptionOrder::latest('id')->first();

        // A second, unrelated account.
        $otherOwner = User::factory()->create(['role' => 'hostel_admin', 'mobile' => '9000000022']);
        $otherBranch = Hostel::factory()->create(['mobile' => '9000000022', 'owner_id' => $otherOwner->id]);
        $otherOwner->hostels()->sync([$otherBranch->id]);
        $accountB = SubscriptionAccount::create(['owner_id' => $otherOwner->id, 'period' => 'yearly', 'status' => 'active']);

        foreach (['issue', 'cancel', 'resend', 'check'] as $action) {
            $this->actingAs($this->superAdmin())
                ->post(route('superadmin.accounts.orders.link.'.$action, $accountB), ['order_id' => $ordersOfA->id])
                ->assertNotFound();
        }
    }

    public function test_the_link_options_are_hidden_when_razorpay_is_not_configured(): void
    {
        config(['services.razorpay.enabled' => false]);
        [, $account] = $this->seedAccount();

        $html = $this->actingAs($this->superAdmin())
            ->get(route('superadmin.accounts.show', $account))
            ->assertOk()
            ->getContent();

        // A button that always errors is worse than no button; offline recording is
        // a complete path on its own.
        $this->assertStringNotContainsString('Send a payment link', $html);
        $this->assertStringContainsString('name="collect" value="offline"', $html);
    }

    public function test_the_collection_choice_is_offered_when_razorpay_is_configured(): void
    {
        [, $account] = $this->seedAccount();

        $this->actingAs($this->superAdmin())
            ->get(route('superadmin.accounts.show', $account))
            ->assertOk()
            ->assertSee('Send a payment link')
            ->assertSee('Record as received');
    }
}
