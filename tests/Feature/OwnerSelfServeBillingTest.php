<?php

namespace Tests\Feature;

use App\Models\Discount;
use App\Models\DiscountRule;
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
 * S3 — owner self-serve billing, rebuilt on the shared core.
 *
 * Design and the probes these pin down: _artifact/saas_billing_autopay/14_S3_DESIGN.md.
 * The Phase 6 tests this file used to hold asserted the retired pay-first behaviour
 * (an order built from a fresh quote when the money arrived); they were replaced
 * along with it, not loosened.
 */
class OwnerSelfServeBillingTest extends TestCase
{
    use RefreshDatabase;

    private const KEY_SECRET = 'rzp_test_secret';

    private const HOOK_SECRET = 'whsec_s3';

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::clear();

        config([
            'services.razorpay.enabled' => true,
            'services.razorpay.key' => 'rzp_test_key',
            'services.razorpay.secret' => self::KEY_SECRET,
            'services.razorpay.webhook_secret' => self::HOOK_SECRET,
            'hostelease.owner_self_serve' => true,
        ]);
    }

    /** @return array{0:User,1:SubscriptionAccount,2:array<int,int>} */
    protected function owner(int $branches = 2, array $account = [], string $mobile = '9700000001'): array
    {
        $owner = User::factory()->create(['role' => 'hostel_admin', 'mobile' => $mobile, 'name' => 'Owner '.$mobile, 'email' => 'o'.$mobile.'@example.test']);
        $ids = [];
        for ($i = 0; $i < $branches; $i++) {
            $ids[] = Hostel::factory()->create([
                'mobile' => $mobile, 'owner_id' => $owner->id, 'status' => 'active',
                'subscription_end' => now()->addMonths(2),
            ])->id;
        }
        $owner->hostels()->sync($ids);
        $owner->forceFill(['hostel_id' => $ids[0] ?? null])->save();

        $acct = SubscriptionAccount::create(array_merge([
            'owner_id' => $owner->id, 'period' => 'yearly', 'status' => 'active',
            'current_period_end' => now()->addMonths(2),
        ], $account));

        return [$owner, $acct, $ids];
    }

    /**
     * Back the fixture's coverage with the ledger, the way S1's back-fill backs every
     * real branch. Only tests that VOID something need it: a void re-derives coverage
     * from paid lines, so coverage written straight onto a branch would be wiped — a
     * state production cannot reach.
     */
    protected function backCoverage(SubscriptionAccount $account, array $ids): void
    {
        $backing = SubscriptionOrder::create([
            'account_id' => $account->id, 'period' => 'yearly', 'kind' => 'adjustment', 'quantity' => count($ids),
            'subtotal' => 0, 'discount_total' => 0, 'amount' => 0, 'payment_status' => 'paid', 'collection' => 'offline',
            'remarks' => 'Test fixture: coverage back-fill',
        ]);
        foreach ($ids as $id) {
            \App\Models\SubscriptionOrderLine::create([
                'order_id' => $backing->id, 'branch_id' => $id, 'amount' => 0,
                'start_date' => now()->subMonths(10), 'end_date' => Hostel::find($id)->subscription_end,
            ]);
        }
    }

    /** Razorpay order creation, with a sequence of ids. */
    protected function fakeOrders(string ...$ids): void
    {
        $seq = Http::sequence();
        foreach ($ids ?: ['order_rzp1'] as $id) {
            $seq->push(['id' => $id, 'amount' => 1, 'currency' => 'INR', 'receipt' => 'r'], 200);
        }

        Http::fake(['api.razorpay.com/v1/orders' => $seq]);
    }

    protected function sign(string $orderId, string $paymentId): string
    {
        return hash_hmac('sha256', $orderId.'|'.$paymentId, self::KEY_SECRET);
    }

    /** A captured payment, as Razorpay's GET /payments/{id} reports it. */
    protected function fakePayment(string $paymentId, int $amount, string $orderId, string $status = 'captured'): void
    {
        Http::fake(['api.razorpay.com/v1/payments/'.$paymentId => Http::response([
            'id' => $paymentId, 'amount' => $amount, 'currency' => 'INR', 'status' => $status, 'order_id' => $orderId,
        ], 200)]);
    }

    protected function startRenewal(User $owner, string $period = 'yearly')
    {
        return $this->actingAs($owner)->postJson(route('admin.subscription.checkout'), ['charge' => 'renewal', 'period' => $period]);
    }

    // =================================================================
    // The pending-order-first shape
    // =================================================================

    public function test_renewal_checkout_writes_a_pending_order_and_charges_its_amount(): void
    {
        [$owner, $account] = $this->owner(2);
        $this->fakeOrders('order_A');
        $anchorBefore = $account->current_period_end->toDateString();

        $res = $this->startRenewal($owner)->assertOk()->assertJsonPath('mode', 'checkout');

        $order = SubscriptionOrder::sole();
        $this->assertSame('pending', $order->payment_status->value);
        $this->assertSame('checkout', $order->collection->value);
        $this->assertSame('order_A', $order->razorpay_order_id);
        $this->assertSame(2, $order->quantity);

        // The checkout amount is the ORDER's, which is the server's quote: 2 × ₹10,000.
        $this->assertSame(2000000, $res->json('razorpay.amount'));
        $this->assertSame(2000000, $order->amountPaise());
        Http::assertSent(fn ($r) => str_contains($r->url(), '/v1/orders') && $r['amount'] === 2000000
            && $r['notes']['type'] === 'checkout' && $r['notes']['he_order_id'] === (string) $order->id);

        // Nothing is paid, so nothing moved (S2 · design 10 §5.1).
        $this->assertSame($anchorBefore, $account->fresh()->current_period_end->toDateString());
    }

    /**
     * 🔴 P2, the decisive one. The Phase 6 flow re-quoted when the money arrived: a
     * branch cancelled while checkout was open left a customer who paid for TWO
     * branches with ONE renewed. The order is now locked when the price is shown.
     */
    public function test_a_branch_cancelled_mid_checkout_does_not_change_what_the_payment_bought(): void
    {
        [$owner, $account, $ids] = $this->owner(2);
        $this->fakeOrders('order_P2');

        $this->startRenewal($owner)->assertOk();
        $order = SubscriptionOrder::sole();

        // The operator confirms a removal while checkout is open.
        app(AccountBillingService::class)->cancelBranch(Hostel::find($ids[1]), 'asked last week');

        // The customer pays what they were shown.
        $this->fakePayment('pay_P2', 2000000, 'order_P2');
        $this->actingAs($owner)->postJson(route('admin.subscription.confirm'), [
            'razorpay_order_id' => 'order_P2', 'razorpay_payment_id' => 'pay_P2', 'razorpay_signature' => $this->sign('order_P2', 'pay_P2'),
        ])->assertOk()->assertJsonPath('state', 'applied');

        $order->refresh();
        $this->assertSame('paid', $order->payment_status->value);
        $this->assertSame(2, $order->quantity, 'Paid for two — the order still says two.');
        $this->assertSame(round((float) $order->subtotal - (float) $order->discount_total, 2), round((float) $order->amount, 2));

        // Both branches got the year they were paid for (a cancelled branch keeps paid coverage — D11 R2).
        $anchor = $account->fresh()->current_period_end;
        foreach ($ids as $id) {
            $this->assertTrue(Hostel::find($id)->subscription_end->greaterThan(now()->addMonths(13)));
        }
        $this->assertNotNull($anchor);
    }

    public function test_reopening_checkout_at_the_same_price_reuses_the_same_order(): void
    {
        [$owner] = $this->owner(2);
        $this->fakeOrders('order_R1', 'order_R2');

        $first = $this->startRenewal($owner)->assertOk()->json('razorpay.order_id');
        $second = $this->startRenewal($owner)->assertOk()->json('razorpay.order_id');

        $this->assertSame($first, $second, 'A retry, not a new charge.');
        $this->assertSame(1, SubscriptionOrder::count());
        Http::assertSentCount(1);
    }

    public function test_reopening_after_the_price_changed_supersedes_the_old_attempt(): void
    {
        [$owner, $account, $ids] = $this->owner(2);
        $this->backCoverage($account, $ids);   // the old attempt is voided below
        $this->fakeOrders('order_S1', 'order_S2');
        Http::fake(['api.razorpay.com/v1/orders/order_S1/payments' => Http::response(['items' => []], 200)]);

        $this->startRenewal($owner)->assertOk();
        $old = SubscriptionOrder::where('kind', 'renewal')->sole();

        $account->update(['unit_price_override_yearly' => 8500]);   // the operator changes the price

        $this->startRenewal($owner)->assertOk()->assertJsonPath('razorpay.amount', 1700000);

        $this->assertSame('voided', $old->fresh()->payment_status->value);
        $this->assertSame(1, SubscriptionOrder::outstanding()->count(), 'Only the current attempt is owed.');
    }

    /** A "superseded" attempt that had in fact been paid is applied, never voided. */
    public function test_a_superseded_attempt_that_was_actually_paid_is_applied_not_voided(): void
    {
        [$owner, $account] = $this->owner(2);
        $this->fakeOrders('order_T1');
        Http::fake(['api.razorpay.com/v1/orders/order_T1/payments' => Http::response(['items' => [
            ['id' => 'pay_T1', 'amount' => 2000000, 'status' => 'captured'],
        ]], 200)]);

        $this->startRenewal($owner)->assertOk();
        $old = SubscriptionOrder::sole();
        $account->update(['unit_price_override_yearly' => 8500]);

        $this->startRenewal($owner)->assertOk()->assertJsonPath('mode', 'paid');

        $old->refresh();
        $this->assertSame('paid', $old->payment_status->value);
        $this->assertSame('pay_T1', $old->transaction_number);
        $this->assertSame(1, SubscriptionOrder::count(), 'No new charge was raised.');
    }

    // =================================================================
    // One open demand per charge, across channels (design §3, 12 §4)
    // =================================================================

    public function test_an_operator_payment_link_is_what_the_owner_pays(): void
    {
        [$owner, $account] = $this->owner(2);
        $order = app(AccountBillingService::class)->renewAccount($account, 'yearly', ['payment_status' => 'pending', 'collection' => 'link']);
        $order->update(['payment_link_id' => 'plink_OP', 'payment_link_url' => 'https://rzp.io/i/op', 'payment_link_status' => 'created', 'payment_link_attempts' => 1]);
        Http::fake();

        $this->startRenewal($owner)->assertOk()
            ->assertJsonPath('mode', 'link')
            ->assertJsonPath('url', 'https://rzp.io/i/op');

        $this->assertSame(1, SubscriptionOrder::count(), 'Never a second demand beside the link.');
        Http::assertNothingSent();
    }

    public function test_an_operator_proforma_is_paid_by_checkout_on_that_same_order(): void
    {
        [$owner, $account] = $this->owner(2);
        $proforma = app(AccountBillingService::class)->renewAccount($account, 'yearly', ['payment_status' => 'pending', 'amount' => 18000]);
        $this->fakeOrders('order_PF');

        $this->startRenewal($owner)->assertOk()
            ->assertJsonPath('mode', 'checkout')
            ->assertJsonPath('razorpay.amount', 1800000);   // the operator's negotiated figure survives

        $this->assertSame(1, SubscriptionOrder::count());
        $this->assertSame('order_PF', $proforma->fresh()->razorpay_order_id);
    }

    public function test_an_operator_renewal_for_a_different_term_is_not_worked_around(): void
    {
        [$owner, $account] = $this->owner(2);
        app(AccountBillingService::class)->renewAccount($account, 'yearly', ['payment_status' => 'pending']);
        Http::fake();

        $message = $this->startRenewal($owner, 'monthly')->assertStatus(422)->json('message');

        // It names what IS due, so the owner knows what to pay or ask about.
        $this->assertStringContainsString('yearly', $message);
        $this->assertStringContainsString('20,000', $message);
        $this->assertSame(1, SubscriptionOrder::count());
        Http::assertNothingSent();
    }

    /** The mirror image: no link on an order the owner has open in checkout. */
    public function test_the_operator_cannot_send_a_link_for_a_charge_open_in_checkout(): void
    {
        [$owner, $account] = $this->owner(2);
        $this->fakeOrders('order_OC');
        $this->startRenewal($owner)->assertOk();
        $order = SubscriptionOrder::sole();

        $this->assertFalse($order->canIssueLink());

        $this->actingAs(User::factory()->superAdmin()->create())
            ->post(route('superadmin.accounts.orders.link.issue', $account), ['order_id' => $order->id])
            ->assertSessionHas('error');

        $this->assertNull($order->fresh()->payment_link_id);
    }

    // =================================================================
    // Confirm — only Razorpay's three ids (design §1 P5/P7)
    // =================================================================

    public function test_confirm_settles_the_order_and_extends_coverage(): void
    {
        [$owner, $account, $ids] = $this->owner(2);
        $this->fakeOrders('order_C1');
        $this->startRenewal($owner);
        $this->fakePayment('pay_C1', 2000000, 'order_C1');

        $this->actingAs($owner)->postJson(route('admin.subscription.confirm'), [
            'razorpay_order_id' => 'order_C1', 'razorpay_payment_id' => 'pay_C1', 'razorpay_signature' => $this->sign('order_C1', 'pay_C1'),
        ])->assertOk()->assertJsonPath('state', 'applied');

        $order = SubscriptionOrder::sole();
        $this->assertSame('paid', $order->payment_status->value);
        $this->assertSame('pay_C1', $order->transaction_number);
        $this->assertSame('online', $order->payment_method->value);
        $this->assertSame('checkout', $order->collection->value);
        $this->assertSame(now()->addMonths(2)->addYear()->toDateString(), $account->fresh()->current_period_end->toDateString());
    }

    public function test_confirm_refuses_a_forged_signature(): void
    {
        [$owner] = $this->owner(2);
        $this->fakeOrders('order_F1');
        $this->startRenewal($owner);

        $this->actingAs($owner)->postJson(route('admin.subscription.confirm'), [
            'razorpay_order_id' => 'order_F1', 'razorpay_payment_id' => 'pay_F1', 'razorpay_signature' => 'forged',
        ])->assertStatus(422);

        $this->assertSame('pending', SubscriptionOrder::sole()->payment_status->value);
    }

    public function test_confirm_cannot_settle_another_accounts_order(): void
    {
        [$ownerA] = $this->owner(2, [], '9700000011');
        [$ownerB] = $this->owner(1, [], '9700000022');
        $this->fakeOrders('order_A_only');
        $this->startRenewal($ownerA);
        $this->fakePayment('pay_X', 2000000, 'order_A_only');

        // A perfectly valid signature — for somebody else's order.
        $this->actingAs($ownerB)->postJson(route('admin.subscription.confirm'), [
            'razorpay_order_id' => 'order_A_only', 'razorpay_payment_id' => 'pay_X', 'razorpay_signature' => $this->sign('order_A_only', 'pay_X'),
        ])->assertStatus(422);

        $this->assertSame('pending', SubscriptionOrder::sole()->payment_status->value);
    }

    public function test_confirm_refuses_a_payment_that_belongs_to_a_different_order(): void
    {
        [$owner] = $this->owner(2);
        $this->fakeOrders('order_D1');
        $this->startRenewal($owner);
        $this->fakePayment('pay_D1', 2000000, 'order_SOMETHING_ELSE');

        $this->actingAs($owner)->postJson(route('admin.subscription.confirm'), [
            'razorpay_order_id' => 'order_D1', 'razorpay_payment_id' => 'pay_D1', 'razorpay_signature' => $this->sign('order_D1', 'pay_D1'),
        ])->assertStatus(422);

        $this->assertSame('pending', SubscriptionOrder::sole()->payment_status->value);
    }

    public function test_an_authorised_but_uncaptured_payment_is_left_to_the_webhook(): void
    {
        [$owner] = $this->owner(2);
        $this->fakeOrders('order_AU');
        $this->startRenewal($owner);
        $this->fakePayment('pay_AU', 2000000, 'order_AU', 'authorized');

        $this->actingAs($owner)->postJson(route('admin.subscription.confirm'), [
            'razorpay_order_id' => 'order_AU', 'razorpay_payment_id' => 'pay_AU', 'razorpay_signature' => $this->sign('order_AU', 'pay_AU'),
        ])->assertOk()->assertJsonPath('state', 'pending');

        $this->assertSame('pending', SubscriptionOrder::sole()->payment_status->value);
    }

    /** The old code fell back to the quote and APPLIED. Now it waits for the webhook. */
    public function test_an_unreadable_payment_is_never_applied_on_assumption(): void
    {
        [$owner] = $this->owner(2);
        $this->fakeOrders('order_UR');
        $this->startRenewal($owner);
        Http::fake(['api.razorpay.com/v1/payments/*' => Http::response(['error' => ['description' => 'down']], 500)]);

        $this->actingAs($owner)->postJson(route('admin.subscription.confirm'), [
            'razorpay_order_id' => 'order_UR', 'razorpay_payment_id' => 'pay_UR', 'razorpay_signature' => $this->sign('order_UR', 'pay_UR'),
        ])->assertOk()->assertJsonPath('state', 'pending');

        $this->assertSame('pending', SubscriptionOrder::sole()->payment_status->value);
    }

    public function test_a_short_capture_is_refused_and_raised(): void
    {
        [$owner] = $this->owner(2);
        $this->fakeOrders('order_SH');
        $this->startRenewal($owner);
        $this->fakePayment('pay_SH', 500000, 'order_SH');

        $this->actingAs($owner)->postJson(route('admin.subscription.confirm'), [
            'razorpay_order_id' => 'order_SH', 'razorpay_payment_id' => 'pay_SH', 'razorpay_signature' => $this->sign('order_SH', 'pay_SH'),
        ])->assertOk()->assertJsonPath('state', 'refused');

        $this->assertSame('pending', SubscriptionOrder::sole()->payment_status->value);
        $this->assertDatabaseHas('notifications', ['type' => 'payment_unapplied', 'hostel_id' => null]);
    }

    /** 🟠 P5. Money already taken is confirmed even with the kill switch off. */
    public function test_confirm_still_works_with_the_kill_switch_off(): void
    {
        [$owner] = $this->owner(2);
        $this->fakeOrders('order_KS');
        $this->startRenewal($owner);

        config(['hostelease.owner_self_serve' => false]);
        $this->fakePayment('pay_KS', 2000000, 'order_KS');

        $this->actingAs($owner)->postJson(route('admin.subscription.confirm'), [
            'razorpay_order_id' => 'order_KS', 'razorpay_payment_id' => 'pay_KS', 'razorpay_signature' => $this->sign('order_KS', 'pay_KS'),
        ])->assertOk()->assertJsonPath('state', 'applied');
    }

    // =================================================================
    // Webhook — the same settle path
    // =================================================================

    protected function deliverOrderPaid(string $rzpOrderId, string $paymentId, int $amount, array $notes): void
    {
        $payload = json_encode(['event' => 'order.paid', 'payload' => [
            'order' => ['entity' => ['id' => $rzpOrderId, 'notes' => $notes]],
            'payment' => ['entity' => ['id' => $paymentId, 'amount' => $amount]],
        ]]);

        $this->call('POST', '/api/v1/webhooks/razorpay', [], [], [], [
            'HTTP_X-Razorpay-Signature' => hash_hmac('sha256', $payload, self::HOOK_SECRET),
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertOk();
    }

    public function test_the_webhook_settles_a_checkout_order_and_a_racing_callback_is_a_no_op(): void
    {
        [$owner] = $this->owner(2);
        $this->fakeOrders('order_W1');
        $this->startRenewal($owner);
        $order = SubscriptionOrder::sole();

        $this->deliverOrderPaid('order_W1', 'pay_W1', 2000000, ['type' => 'checkout', 'he_order_id' => (string) $order->id]);
        $this->assertSame('paid', $order->fresh()->payment_status->value);
        $coverage = Hostel::find($order->lines()->value('branch_id'))->subscription_end->copy();

        // The browser callback arrives second, for the same payment.
        $this->fakePayment('pay_W1', 2000000, 'order_W1');
        $this->actingAs($owner)->postJson(route('admin.subscription.confirm'), [
            'razorpay_order_id' => 'order_W1', 'razorpay_payment_id' => 'pay_W1', 'razorpay_signature' => $this->sign('order_W1', 'pay_W1'),
        ])->assertOk()->assertJsonPath('state', 'already');

        // And the webhook again, for good measure.
        $this->deliverOrderPaid('order_W1', 'pay_W1', 2000000, ['type' => 'checkout', 'he_order_id' => (string) $order->id]);

        $this->assertSame(1, SubscriptionOrder::where('transaction_number', 'pay_W1')->count());
        $this->assertTrue($coverage->equalTo(Hostel::find($order->lines()->value('branch_id'))->subscription_end));
    }

    /** A note pointing at the wrong order cannot redirect a payment. */
    public function test_a_note_pointing_at_the_wrong_order_cannot_redirect_a_payment(): void
    {
        [$owner] = $this->owner(2);
        $this->fakeOrders('order_RIGHT');
        $this->startRenewal($owner);
        $order = SubscriptionOrder::sole();

        $this->deliverOrderPaid('order_WRONG', 'pay_WR', 2000000, ['type' => 'checkout', 'he_order_id' => (string) $order->id]);

        $this->assertSame('pending', $order->fresh()->payment_status->value);
        $this->assertDatabaseHas('notifications', ['type' => 'payment_unapplied']);
    }

    // =================================================================
    // Who may do what (design §4)
    // =================================================================

    public function test_a_co_admin_cannot_start_a_charge_or_add_a_branch(): void
    {
        [$owner, , $ids] = $this->owner(1);
        $co = User::factory()->create(['role' => 'hostel_admin', 'mobile' => '9700000099', 'hostel_id' => $ids[0]]);
        $co->hostels()->sync($ids);
        Http::fake();

        $this->actingAs($co)->postJson(route('admin.subscription.checkout'), ['charge' => 'renewal', 'period' => 'yearly'])->assertStatus(403);
        $this->actingAs($co)->postJson(route('admin.subscription.add-branch'), ['name' => 'Co Wing'])->assertStatus(403);

        $this->assertSame(0, SubscriptionOrder::count());
        $this->assertNull(Hostel::where('name', 'Co Wing')->first(), 'And no branch owned by the co-admin (design §1 P4).');
        $this->assertDatabaseMissing('subscription_accounts', ['owner_id' => $co->id]);

        $this->actingAs($co)->get(route('admin.subscription.index'))->assertOk()->assertSee('Managed by the account owner');
    }

    public function test_the_kill_switch_makes_the_page_read_only_but_a_sent_link_stays_payable(): void
    {
        [$owner, $account] = $this->owner(2);
        $order = app(AccountBillingService::class)->renewAccount($account, 'yearly', ['payment_status' => 'pending', 'collection' => 'link']);
        $order->update(['payment_link_id' => 'plink_KS', 'payment_link_url' => 'https://rzp.io/i/killswitch', 'payment_link_status' => 'created', 'payment_link_attempts' => 1]);

        config(['hostelease.owner_self_serve' => false]);

        $this->actingAs($owner)->postJson(route('admin.subscription.checkout'), ['charge' => 'renewal', 'period' => 'yearly'])->assertStatus(503);

        $this->actingAs($owner)->get(route('admin.subscription.index'))
            ->assertOk()
            ->assertSee('Billing is managed by HostelEase support')
            ->assertSee('https://rzp.io/i/killswitch', false)       // a link WE sent is payable
            ->assertDontSee('checkout.razorpay.com', false);         // but no self-serve checkout
    }

    // =================================================================
    // What the owner sees (design §7)
    // =================================================================

    /**
     * THE two-surface test: the owner's page and the operator's Account 360 price the
     * same account identically — custom price, volume tier and negotiated discount all
     * in play. It is the one test that stops the two front doors drifting apart.
     */
    public function test_the_owner_and_the_operator_see_identical_renewal_totals(): void
    {
        [$owner, $account] = $this->owner(3, ['unit_price_override_yearly' => 9000, 'unit_price_override_monthly' => 950]);
        DiscountRule::create(['min_quantity' => 3, 'type' => 'percentage', 'value' => 5, 'active' => true]);
        Discount::create(['account_id' => $account->id, 'type' => 'fixed', 'value' => 700, 'recurrence' => 'every_renewal', 'status' => 'active', 'reason' => 'SECRET-NEGOTIATION-NOTE']);

        $ownerView = $this->actingAs($owner)->get(route('admin.subscription.index'))->assertOk();
        $operatorQuotes = $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('superadmin.accounts.show', $account))->assertOk()->viewData('renewQuotes');

        foreach (['yearly', 'monthly'] as $term) {
            $this->assertSame($operatorQuotes[$term]['auto'], $ownerView->viewData('quotes')[$term]['final'], "{$term} totals must match");
            $this->assertSame($operatorQuotes[$term]['unit'], $ownerView->viewData('quotes')[$term]['unit']);
        }

        // Their price, and the reason behind a negotiated discount never reaches them.
        $ownerView->assertDontSee('SECRET-NEGOTIATION-NOTE');
    }

    public function test_the_yearly_saving_is_computed_from_their_own_prices(): void
    {
        [$owner] = $this->owner(1, ['unit_price_override_yearly' => 11000, 'unit_price_override_monthly' => 1000]);

        // 11,000 vs 12 × 1,000 = 12,000 → 8%, not a hard-coded 16%.
        $this->assertSame(8, $this->actingAs($owner)->get(route('admin.subscription.index'))->viewData('yearlySaving'));
    }

    public function test_an_open_renewal_replaces_the_renew_button_with_pay(): void
    {
        [$owner, $account] = $this->owner(2);
        app(AccountBillingService::class)->renewAccount($account, 'yearly', ['payment_status' => 'pending', 'amount' => 18000]);

        $this->actingAs($owner)->get(route('admin.subscription.index'))
            ->assertOk()
            ->assertSee('Payment due')
            ->assertSee('18,000')
            ->assertDontSee('Renew all now');
    }

    // =================================================================
    // Add a branch
    // =================================================================

    /**
     * One free trial per ACCOUNT (owner decision, 2026-10-04). A branch the owner
     * adds later gets no trial — it is inactive until paid for — and the account is
     * left exactly as it was (P1: it used to be relabelled a trial).
     */
    public function test_a_branch_added_later_gets_no_trial_and_leaves_the_account_alone(): void
    {
        [$owner, $account] = $this->owner(2);

        $this->actingAs($owner)->postJson(route('admin.subscription.add-branch'), ['name' => 'New Wing'])
            ->assertOk()->assertJsonPath('mode', 'created');

        $branch = Hostel::where('name', 'New Wing')->sole();
        $this->assertNull($branch->subscription_end, 'No trial coverage for a later branch.');
        $this->assertFalse($branch->isActive());
        $this->assertSame(0, SubscriptionOrder::where('kind', 'trial')->count());

        $account->refresh();
        $this->assertSame('yearly', $account->period->value);
        $this->assertSame('active', $account->status->value);

        // It is offered to be paid for, from the same quote the operator sees.
        $this->assertArrayHasKey($branch->id, $this->actingAs($owner)->get(route('admin.subscription.index'))->viewData('addable'));
    }

    /** The operator cannot hand an existing account a second trial either. */
    public function test_the_operator_cannot_give_an_existing_account_a_trial_branch(): void
    {
        [, $account] = $this->owner(2);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->post(route('superadmin.accounts.add-hostel', $account), ['name' => 'Ops Wing', 'plan' => 'trial'])
            ->assertSessionHas('error');

        $this->assertNull(Hostel::where('name', 'Ops Wing')->first());
        $account->refresh();
        $this->assertSame('yearly', $account->period->value);
        $this->assertSame('active', $account->status->value);
    }

    /** Provisioning onto an EXISTING owner (same mobile) cannot carry a trial. */
    public function test_provisioning_a_trial_hostel_for_an_existing_owner_is_refused(): void
    {
        [$owner] = $this->owner(1, [], '9700000041');

        $this->actingAs(User::factory()->superAdmin()->create())
            ->post(route('superadmin.hostels.store'), [
                'name' => 'Second Site', 'owner_name' => $owner->name, 'mobile' => '+919700000041',
                'status' => 'active', 'plan' => 'trial', 'payment_status' => 'paid',
            ])
            ->assertSessionHasErrors('plan');

        $this->assertNull(Hostel::where('name', 'Second Site')->first());
    }

    /** The rule itself, at the one place every trial is granted. */
    public function test_the_trial_is_granted_once_per_account_and_never_to_a_second_branch(): void
    {
        $billing = app(AccountBillingService::class);

        // A brand-new owner's first branch: the trial is available, and granted.
        $owner = User::factory()->create(['role' => 'hostel_admin', 'mobile' => '9700000051']);
        $first = Hostel::factory()->create(['mobile' => '9700000051', 'owner_id' => $owner->id]);
        $owner->hostels()->sync([$first->id]);
        $account = $billing->accountFor($owner);

        $this->assertTrue($billing->trialAvailable($account, $first));
        $trial = $billing->recordBranchRenewal($first, 'trial', ['payment_status' => 'paid']);

        // Used: never a SECOND 14-day trial. A branch added while the trial runs
        // joins it instead — same end date, ₹0 (owner decision, 2026-10-05).
        $second = Hostel::factory()->create(['mobile' => '9700000051', 'owner_id' => $owner->id, 'subscription_start' => null, 'subscription_end' => null]);
        $owner->hostels()->syncWithoutDetaching([$second->id]);
        $this->assertFalse($billing->trialAvailable($account->fresh(), $second));
        $this->assertTrue($billing->trialJoinable($account->fresh()));

        $joined = $billing->recordBranchRenewal($second, 'trial', ['payment_status' => 'paid']);
        $this->assertSame(0.0, (float) $joined->amount);
        $this->assertSame($account->fresh()->current_period_end->toDateString(), $second->fresh()->subscription_end->toDateString(), 'Joins the SAME window — no fresh 14 days.');

        // Once the trial is over, there is nothing to join and no second trial.
        $this->travelTo($account->fresh()->current_period_end->copy()->addDays(5));
        $billing->refreshAccountAnchor($account->fresh());
        $third = Hostel::factory()->create(['mobile' => '9700000051', 'owner_id' => $owner->id, 'subscription_start' => null, 'subscription_end' => null]);
        $owner->hostels()->syncWithoutDetaching([$third->id]);
        try {
            $billing->recordBranchRenewal($third, 'trial', ['payment_status' => 'paid']);
            $this->fail('A trial after the trial ended must be refused.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('already had their free trial', $e->getMessage());
        }
        $this->travelBack();

        // A trial voided as a MISTAKE does not count as used — but the account still
        // has another branch, so it stays unavailable.
        $billing->voidOrder($joined, 'recorded in error');
        $billing->voidOrder($trial, 'recorded in error');
        $this->assertFalse($billing->trialAvailable($account->fresh(), $second));
    }

    /** A voided trial on a single-branch account can be granted again. */
    public function test_a_trial_voided_as_a_mistake_can_be_granted_again(): void
    {
        $billing = app(AccountBillingService::class);
        $owner = User::factory()->create(['role' => 'hostel_admin', 'mobile' => '9700000052']);
        $only = Hostel::factory()->create(['mobile' => '9700000052', 'owner_id' => $owner->id]);
        $owner->hostels()->sync([$only->id]);

        $trial = $billing->recordBranchRenewal($only, 'trial', ['payment_status' => 'paid']);
        $billing->voidOrder($trial, 'recorded in error');

        $this->assertTrue($billing->trialAvailable($billing->accountFor($owner), $only));
    }

    /** A brand-new account still takes its trial cadence from its first branch. */
    public function test_a_first_trial_still_makes_a_new_account_a_trial(): void
    {
        $owner = User::factory()->create(['role' => 'hostel_admin', 'mobile' => '9700000077']);
        $branch = Hostel::factory()->create(['mobile' => '9700000077', 'owner_id' => $owner->id]);
        $owner->hostels()->sync([$branch->id]);

        app(AccountBillingService::class)->recordBranchRenewal($branch, 'trial', ['payment_status' => 'paid']);

        $account = SubscriptionAccount::where('owner_id', $owner->id)->sole();
        $this->assertSame('trial', $account->period->value);
        $this->assertSame('trial', $account->status->value);
    }

    public function test_add_and_pay_charges_the_operators_prorated_quote_and_an_abandoned_payment_keeps_the_branch(): void
    {
        [$owner, $account] = $this->owner(1, ['current_period_end' => now()->addMonths(6)]);
        Hostel::where('owner_id', $owner->id)->update(['subscription_end' => now()->addMonths(6)]);
        $this->fakeOrders('order_ADD');

        $res = $this->actingAs($owner)->postJson(route('admin.subscription.add-branch'), ['name' => 'Paid Wing', 'pay_now' => true])
            ->assertOk()->assertJsonPath('mode', 'checkout');

        $branch = Hostel::where('name', 'Paid Wing')->sole();
        $expected = (int) round(app(AccountBillingService::class)->quoteAddBranch($account->fresh(), $branch)['breakdown']['final'] * 100);
        $this->assertSame($expected, $res->json('razorpay.amount'));

        // The owner closes the checkout without paying: the branch is kept — owned by
        // the owner, inactive (no trial for a later branch), and payable from its
        // "Add to plan" button.
        $this->assertFalse($branch->fresh()->isActive());
        $this->assertSame($owner->id, $branch->owner_id);
        $this->assertSame('pending', SubscriptionOrder::where('kind', 'add_branch')->sole()->payment_status->value);
    }

    public function test_add_and_pay_on_a_trial_account_just_creates_the_branch(): void
    {
        [$owner] = $this->owner(1, ['period' => 'trial', 'status' => 'trial', 'current_period_end' => now()->addDays(10)]);
        Http::fake();

        $this->actingAs($owner)->postJson(route('admin.subscription.add-branch'), ['name' => 'Trial Wing', 'pay_now' => true])
            ->assertOk()->assertJsonPath('mode', 'created');

        $this->assertNotNull(Hostel::where('name', 'Trial Wing')->first());
        $this->assertSame(0, SubscriptionOrder::where('kind', 'add_branch')->count());
        Http::assertNothingSent();
    }

    public function test_a_branch_already_covered_to_the_anchor_has_nothing_to_add(): void
    {
        [$owner, , $ids] = $this->owner(2);
        Http::fake();

        $this->actingAs($owner)->postJson(route('admin.subscription.checkout'), ['charge' => 'add_branch', 'branch_id' => $ids[0]])
            ->assertStatus(422);

        $this->assertSame(0, SubscriptionOrder::count());
    }

    public function test_a_posted_id_cannot_reach_another_accounts_branch_or_order(): void
    {
        [$ownerA] = $this->owner(1, [], '9700000031');
        [, $accountB, $idsB] = $this->owner(1, [], '9700000032');
        $orderB = app(AccountBillingService::class)->renewAccount($accountB, 'yearly', ['payment_status' => 'pending']);
        Http::fake();

        $this->actingAs($ownerA)->postJson(route('admin.subscription.checkout'), ['charge' => 'add_branch', 'branch_id' => $idsB[0]])->assertNotFound();
        $this->actingAs($ownerA)->postJson(route('admin.subscription.checkout'), ['charge' => 'order', 'order_id' => $orderB->id])->assertNotFound();
    }

    public function test_an_unreachable_razorpay_is_a_message_and_leaves_nothing_behind(): void
    {
        [$owner] = $this->owner(2);
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('timed out'));

        $this->startRenewal($owner)->assertStatus(422);

        $this->assertSame(0, SubscriptionOrder::count(), 'The pending order rolled back with the failed call.');
    }

    // =================================================================
    // Found in the S3 check pass
    // =================================================================

    /**
     * Suspended is an operator hold, and computeStatus() keeps it Suspended whatever
     * is paid — so a suspended owner who paid would be charged and stay blocked. The
     * page hid the button; the server did not refuse a crafted request.
     */
    public function test_a_suspended_account_cannot_start_a_charge(): void
    {
        [$owner] = $this->owner(2, ['status' => 'suspended']);
        Http::fake();

        // 423 Locked: refused at the controller's door since the audit, before any
        // service runs. CheckoutService refuses too, as the second line of defence.
        $this->startRenewal($owner)->assertStatus(423);

        $this->assertSame(0, SubscriptionOrder::count());
        Http::assertNothingSent();
    }

    /** A link URL comes from Razorpay and lands in an href — https only, ever. */
    public function test_a_non_https_link_url_is_never_rendered_or_followed(): void
    {
        [$owner, $account] = $this->owner(2);
        $order = app(AccountBillingService::class)->renewAccount($account, 'yearly', ['payment_status' => 'pending', 'collection' => 'link']);
        $order->update(['payment_link_id' => 'plink_BAD', 'payment_link_url' => 'javascript:alert(1)', 'payment_link_status' => 'created', 'payment_link_attempts' => 1]);
        Http::fake();

        $this->actingAs($owner)->get(route('admin.subscription.index'))
            ->assertOk()
            ->assertDontSee('javascript:alert(1)', false);

        $this->startRenewal($owner)->assertStatus(422);
    }

    /** The operator can see when the owner has a charge open in checkout. */
    public function test_account_360_shows_an_open_owner_checkout(): void
    {
        [$owner, $account] = $this->owner(2);
        $this->fakeOrders('order_VIS');
        $this->startRenewal($owner)->assertOk();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('superadmin.accounts.show', $account))
            ->assertOk()
            ->assertSee('Owner opened checkout');
    }

    /** No N+1 on the rebuilt page — flat as branches and open charges grow. */
    public function test_the_owner_page_query_count_does_not_grow_with_branches(): void
    {
        $count = function (int $branches, string $mobile) {
            [$owner] = $this->owner($branches, [], $mobile);
            \Illuminate\Support\Facades\DB::flushQueryLog();
            \Illuminate\Support\Facades\DB::enableQueryLog();
            $this->actingAs($owner)->get(route('admin.subscription.index'))->assertOk();
            $n = count(\Illuminate\Support\Facades\DB::getQueryLog());
            \Illuminate\Support\Facades\DB::disableQueryLog();

            return $n;
        };

        $small = $count(2, '9700000061');
        $large = $count(10, '9700000062');

        $this->assertLessThanOrEqual($small + 2, $large, "Owner page went from {$small} to {$large} queries as branches grew 2 → 10.");
    }

    // =================================================================
    // The S3 audit (16_S3_AUDIT.md)
    // =================================================================

    /**
     * 🔴 Paying twice for one year. Two pending renewals could coexist (S2 blocks two
     * LIVE links, not two pending orders), both quoted from the same anchor, both
     * listed as Payment due. Pay one, and the other now buys nothing — so it must
     * stop being offered, and refuse to open.
     */
    public function test_once_one_renewal_is_paid_a_second_one_is_never_offered(): void
    {
        [$owner, $account] = $this->owner(2);
        $billing = app(AccountBillingService::class);
        $first = $billing->renewAccount($account, 'yearly', ['payment_status' => 'pending', 'collection' => 'link']);
        $second = $billing->renewAccount($account->fresh(), 'yearly', ['payment_status' => 'pending', 'collection' => 'link']);

        $billing->acceptOrder($first, ['payment_method' => 'rtgs']);

        $due = collect($this->actingAs($owner)->get(route('admin.subscription.index'))->assertOk()->viewData('due'));
        $this->assertFalse($due->firstWhere('id', $second->id)['payable'], 'An overtaken renewal must not be offered.');

        Http::fake();
        $this->actingAs($owner)->postJson(route('admin.subscription.checkout'), ['charge' => 'order', 'order_id' => $second->id])
            ->assertStatus(422);
        Http::assertNothingSent();
    }

    /** 🔴 The owner's open attempt, overtaken by an offline renewal, is never paid. */
    public function test_an_attempt_overtaken_by_an_offline_renewal_is_refused_and_cleared(): void
    {
        [$owner, $account] = $this->owner(2);
        $this->fakeOrders('order_OV1', 'order_OV2');
        Http::fake(['api.razorpay.com/v1/orders/order_OV1/payments' => Http::response(['items' => []], 200)]);

        $this->startRenewal($owner)->assertOk();
        $own = SubscriptionOrder::sole();

        // The operator records a renewal paid by bank transfer meanwhile.
        app(AccountBillingService::class)->renewAccount($account->fresh(), 'yearly', ['payment_status' => 'paid', 'payment_method' => 'rtgs']);

        $this->actingAs($owner)->postJson(route('admin.subscription.checkout'), ['charge' => 'order', 'order_id' => $own->id])
            ->assertStatus(422);

        // And the next Renew clears it out rather than reusing it.
        $this->startRenewal($owner)->assertOk();
        $this->assertSame('voided', $own->fresh()->payment_status->value);
    }

    /** 🟠 Reuse compared branch COUNT; a swapped branch left the new one unpaid. */
    public function test_a_swapped_branch_is_never_paid_for_with_the_old_order(): void
    {
        [$owner, $account, $ids] = $this->owner(2);
        $this->fakeOrders('order_SW1', 'order_SW2');
        Http::fake(['api.razorpay.com/v1/orders/order_SW1/payments' => Http::response(['items' => []], 200)]);

        $this->startRenewal($owner)->assertOk();
        $first = SubscriptionOrder::sole();

        app(AccountBillingService::class)->cancelBranch(Hostel::find($ids[1]), 'swap');
        $new = Hostel::factory()->create(['mobile' => '9700000001', 'owner_id' => $owner->id, 'status' => 'active', 'subscription_end' => now()->addMonths(2)]);
        $owner->hostels()->syncWithoutDetaching([$new->id]);

        $this->startRenewal($owner)->assertOk()->assertJsonPath('razorpay.order_id', 'order_SW2');

        $this->assertSame('voided', $first->fresh()->payment_status->value);
        $current = SubscriptionOrder::where('payment_status', 'pending')->sole();
        $this->assertContains($new->id, $current->lines()->pluck('branch_id')->map(fn ($id) => (int) $id)->all());
    }

    /** 🟠 A payment still in flight blocks a supersede — voiding it made a refund case. */
    public function test_an_in_flight_payment_blocks_superseding_the_attempt(): void
    {
        [$owner, $account] = $this->owner(2);
        $this->fakeOrders('order_FL1', 'order_FL2');
        Http::fake(['api.razorpay.com/v1/orders/order_FL1/payments' => Http::response(['items' => [
            ['id' => 'pay_FL', 'amount' => 2000000, 'status' => 'authorized'],
        ]], 200)]);

        $this->startRenewal($owner)->assertOk();
        $old = SubscriptionOrder::sole();
        $account->update(['unit_price_override_yearly' => 8500]);

        $this->startRenewal($owner)->assertStatus(422);

        $this->assertSame('pending', $old->fresh()->payment_status->value);
        $this->assertSame(1, SubscriptionOrder::count(), 'No second charge while the first is still being paid.');
    }

    /** 🟡 A found-but-refused payment is reported as held, never as "paid". */
    public function test_a_short_payment_found_on_retry_is_held_not_reported_paid(): void
    {
        [$owner, $account] = $this->owner(2);
        $this->fakeOrders('order_SR1');
        Http::fake(['api.razorpay.com/v1/orders/order_SR1/payments' => Http::response(['items' => [
            ['id' => 'pay_SR', 'amount' => 500000, 'status' => 'captured'],
        ]], 200)]);

        $this->startRenewal($owner)->assertOk();
        $account->update(['unit_price_override_yearly' => 8500]);

        $this->startRenewal($owner)->assertOk()->assertJsonPath('mode', 'held');
        $this->assertDatabaseHas('notifications', ['type' => 'payment_unapplied']);
    }

    /** 🟡 Suspended means nothing can be started — adding a branch included. */
    public function test_a_suspended_owner_cannot_add_a_branch(): void
    {
        [$owner] = $this->owner(1, ['status' => 'suspended']);

        $this->actingAs($owner)->postJson(route('admin.subscription.add-branch'), ['name' => 'Held Wing'])->assertStatus(423);

        $this->assertNull(Hostel::where('name', 'Held Wing')->first());
    }

    /** 🔴 A payment that lands on an overtaken charge is recorded AND raised for refund. */
    public function test_a_payment_that_buys_nothing_raises_a_refund_alert(): void
    {
        [$owner, $account] = $this->owner(2);
        $billing = app(AccountBillingService::class);
        $first = $billing->renewAccount($account, 'yearly', ['payment_status' => 'pending']);
        $second = $billing->renewAccount($account->fresh(), 'yearly', ['payment_status' => 'pending']);
        $billing->acceptOrder($first, ['payment_method' => 'rtgs']);

        // The customer pays the overtaken one anyway (an old link, a stale tab).
        app(\App\Services\Billing\PaymentSettlement::class)->settle($second, 'pay_dup', 2000000, 'payment link', 'webhook');

        $this->assertSame('paid', $second->fresh()->payment_status->value, 'Recorded — so it is not offered again.');
        $this->assertDatabaseHas('notifications', ['type' => 'payment_no_coverage', 'hostel_id' => null]);
    }

    /**
     * 🔴 A renewal paid by checkout overtakes the operator's live add-branch link for
     * a branch it just covered. That link must die before the customer taps it from
     * an old SMS and pays for nothing.
     */
    public function test_paying_a_renewal_cancels_a_live_link_it_just_made_pointless(): void
    {
        [$owner, $account, $ids] = $this->owner(2);
        Hostel::find($ids[1])->update(['subscription_end' => now()->addMonth()]);   // behind the anchor

        $addBranch = app(AccountBillingService::class)->addBranch($account->fresh(), Hostel::find($ids[1]), ['payment_status' => 'pending', 'collection' => 'link']);
        $addBranch->update(['payment_link_id' => 'plink_AB', 'payment_link_url' => 'https://rzp.io/i/ab', 'payment_link_status' => 'created', 'payment_link_attempts' => 1]);

        $this->fakeOrders('order_RN');
        Http::fake(['api.razorpay.com/v1/payment_links/plink_AB/cancel' => Http::response(['id' => 'plink_AB', 'status' => 'cancelled'], 200)]);
        $this->startRenewal($owner)->assertOk();

        // The renewal brings the behind branch up to the anchor too (2026-10-05), so it
        // costs more than 2 × ₹10,000 — pay what the order says.
        $renewal = SubscriptionOrder::where('kind', 'renewal')->sole();
        $this->assertGreaterThan(2000000, $renewal->amountPaise());
        $this->fakePayment('pay_RN', $renewal->amountPaise(), 'order_RN');
        $this->actingAs($owner)->postJson(route('admin.subscription.confirm'), [
            'razorpay_order_id' => 'order_RN', 'razorpay_payment_id' => 'pay_RN', 'razorpay_signature' => $this->sign('order_RN', 'pay_RN'),
        ])->assertOk()->assertJsonPath('state', 'applied');

        Http::assertSent(fn ($r) => str_contains($r->url(), 'plink_AB/cancel'));
        $this->assertSame('cancelled', $addBranch->fresh()->payment_link_status->value);
    }

    /** 🟠 The operator is told the truth when the owner paid online moments before. */
    public function test_accepting_an_order_just_paid_online_tells_the_operator_not_to_take_payment(): void
    {
        [, $account] = $this->owner(2);
        $order = app(AccountBillingService::class)->renewAccount($account, 'yearly', ['payment_status' => 'pending']);

        app(\App\Services\Billing\PaymentSettlement::class)->settle($order->fresh(), 'pay_race', 2000000, 'online checkout', 'webhook');

        $this->actingAs(User::factory()->superAdmin()->create())
            ->patch(route('superadmin.accounts.orders.accept', [$account, $order]), ['payment_method' => 'cash'])
            ->assertSessionHas('info', fn ($m) => str_contains($m, 'pay_race') && str_contains($m, 'Do not take payment'));

        $this->assertSame('pay_race', $order->fresh()->transaction_number, 'The online payment is never overwritten.');
    }

    /** The operator cannot send a link for a charge that would buy nothing. */
    public function test_the_operator_cannot_send_a_link_for_an_overtaken_charge(): void
    {
        [, $account] = $this->owner(2);
        $billing = app(AccountBillingService::class);
        $first = $billing->renewAccount($account, 'yearly', ['payment_status' => 'pending']);
        $second = $billing->renewAccount($account->fresh(), 'yearly', ['payment_status' => 'pending']);
        $billing->acceptOrder($first, ['payment_method' => 'rtgs']);
        Http::fake();

        $super = User::factory()->superAdmin()->create();
        $this->actingAs($super)->post(route('superadmin.accounts.orders.link.issue', $account), ['order_id' => $second->id])
            ->assertSessionHas('error');
        $this->assertNull($second->fresh()->payment_link_id);

        $this->actingAs($super)->get(route('superadmin.accounts.show', $account))->assertOk()->assertSee('collecting this buys nothing');
    }

    /** A note alone can never choose which charge a payment settles. */
    public function test_a_note_cannot_settle_an_order_with_no_razorpay_order_on_record(): void
    {
        [, $account] = $this->owner(2);
        $order = app(AccountBillingService::class)->renewAccount($account, 'yearly', ['payment_status' => 'pending']);
        $this->assertNull($order->razorpay_order_id);

        $this->deliverOrderPaid('order_FORGED_NOTE', 'pay_FN', 2000000, ['type' => 'checkout', 'he_order_id' => (string) $order->id]);

        $this->assertSame('pending', $order->fresh()->payment_status->value);
        $this->assertDatabaseHas('notifications', ['type' => 'payment_unapplied']);
    }

    public function test_the_subscription_page_renders_for_the_owner(): void
    {
        [$owner] = $this->owner(2);

        $this->actingAs($owner)->get(route('admin.subscription.index'))
            ->assertOk()
            ->assertSee('Your branches')
            ->assertSee('Renew all now')
            ->assertSee('checkout.razorpay.com', false);
    }
}
