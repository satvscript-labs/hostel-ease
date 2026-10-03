<?php

namespace Tests\Feature;

use App\Models\Hostel;
use App\Models\Notification;
use App\Models\SubscriptionAccount;
use App\Models\SubscriptionOrder;
use App\Models\User;
use App\Services\Billing\AccountBillingService;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * S2 — the `payment_link.*` webhook arms.
 *
 * The webhook is where money actually becomes coverage, so these tests care
 * about exactly three things: it applies a real payment once, it NEVER applies
 * one it should not, and it never 500s — a 500 makes Razorpay retry forever and
 * still never succeed, so unattributable money has to reach a human instead.
 */
class PaymentLinkWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'whsec_s2_test';

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::clear();
        config(['services.razorpay.webhook_secret' => self::SECRET]);
    }

    /** A pending link order, built through the real engine so the ledger is real. */
    protected function pendingLinkOrder(float $amount = 10000, string $linkId = 'plink_WH1'): SubscriptionOrder
    {
        $owner = User::factory()->create(['role' => 'hostel_admin', 'mobile' => '9880000001', 'name' => 'Webhook Owner']);
        $branch = Hostel::factory()->create([
            'mobile' => '9880000001', 'owner_id' => $owner->id, 'status' => 'active',
            'subscription_end' => now()->addMonths(2),
        ]);
        $owner->hostels()->sync([$branch->id]);

        SubscriptionAccount::create([
            'owner_id' => $owner->id, 'period' => 'yearly', 'status' => 'active',
            'current_period_end' => now()->addMonths(2),
        ]);

        $order = app(AccountBillingService::class)->recordBranchRenewal($branch, 'yearly', [
            'payment_status' => 'pending',
            'amount' => $amount,
            'collection' => 'link',
        ]);

        $order->update([
            'payment_link_id' => $linkId,
            'payment_link_url' => 'https://rzp.io/i/'.$linkId,
            'payment_link_ref' => $order->public_id,
            'payment_link_status' => 'created',
            'payment_link_attempts' => 1,
        ]);

        return $order->fresh();
    }

    protected function deliver(array $payload, ?string $secret = null): TestResponse
    {
        $body = json_encode($payload);
        $signature = hash_hmac('sha256', $body, $secret ?? self::SECRET);

        return $this->call(
            'POST',
            '/api/v1/webhooks/razorpay',
            [], [], [],
            ['HTTP_X-Razorpay-Signature' => $signature, 'CONTENT_TYPE' => 'application/json'],
            $body,
        );
    }

    protected function paidPayload(SubscriptionOrder $order, int $paidPaise, string $paymentId = 'pay_link_1'): array
    {
        return [
            'event' => 'payment_link.paid',
            'payload' => [
                'payment_link' => ['entity' => [
                    'id' => $order->payment_link_id,
                    'status' => 'paid',
                    'amount' => $order->amountPaise(),
                    'amount_paid' => $paidPaise,
                    'reference_id' => $order->payment_link_ref,
                    'notes' => ['type' => 'payment_link', 'he_order_id' => (string) $order->id],
                ]],
                'order' => ['entity' => ['id' => 'order_behind_link', 'notes' => ['type' => 'payment_link']]],
                'payment' => ['entity' => ['id' => $paymentId, 'amount' => $paidPaise, 'status' => 'captured']],
            ],
        ];
    }

    // =================================================================
    // The happy path, and only once
    // =================================================================

    public function test_a_paid_link_applies_the_charge_and_extends_coverage(): void
    {
        $order = $this->pendingLinkOrder();
        $branch = $order->lines()->first()->branch;
        $coverageBefore = $branch->subscription_end->copy();

        $this->deliver($this->paidPayload($order, $order->amountPaise()))->assertOk();

        $order->refresh();
        $this->assertSame('paid', $order->payment_status->value);
        $this->assertSame('pay_link_1', $order->transaction_number);
        $this->assertSame('online', $order->payment_method->value);
        $this->assertSame('paid', $order->payment_link_status->value);
        // The collection channel survives — it is still a link payment, not a checkout.
        $this->assertSame('link', $order->collection->value);

        $this->assertTrue($branch->fresh()->subscription_end->greaterThan($coverageBefore));
    }

    public function test_a_retried_delivery_is_a_clean_no_op(): void
    {
        $order = $this->pendingLinkOrder();
        $payload = $this->paidPayload($order, $order->amountPaise());

        $this->deliver($payload)->assertOk();
        $coverageAfterFirst = $order->lines()->first()->branch->fresh()->subscription_end->copy();

        $this->deliver($payload)->assertOk();
        $this->deliver($payload)->assertOk();

        $this->assertSame(1, SubscriptionOrder::where('transaction_number', 'pay_link_1')->count());
        $this->assertTrue($coverageAfterFirst->equalTo($order->lines()->first()->branch->fresh()->subscription_end));
    }

    public function test_an_invalid_signature_is_rejected_and_nothing_is_applied(): void
    {
        $order = $this->pendingLinkOrder();

        $this->deliver($this->paidPayload($order, $order->amountPaise()), 'wrong_secret')
            ->assertStatus(400);

        $this->assertSame('pending', $order->fresh()->payment_status->value);
    }

    // =================================================================
    // What it must refuse to apply
    // =================================================================

    /**
     * Design §6 BP5. Granting a full term for part of the money is the one mistake
     * here that costs real revenue.
     */
    public function test_a_short_payment_grants_nothing_and_raises_an_alert(): void
    {
        $order = $this->pendingLinkOrder(10000);

        // ₹4,000 against a ₹10,000 charge.
        $this->deliver($this->paidPayload($order, 400000, 'pay_short'))->assertOk();

        $this->assertSame('pending', $order->fresh()->payment_status->value);
        $this->assertNull($order->fresh()->transaction_number);

        $this->assertDatabaseHas('notifications', ['type' => 'payment_unapplied', 'hostel_id' => null]);
    }

    public function test_a_partially_paid_link_grants_nothing(): void
    {
        $order = $this->pendingLinkOrder(10000);

        $this->deliver([
            'event' => 'payment_link.partially_paid',
            'payload' => [
                'payment_link' => ['entity' => [
                    'id' => $order->payment_link_id, 'status' => 'partially_paid',
                    'amount' => 1000000, 'amount_paid' => 300000,
                    'reference_id' => $order->payment_link_ref,
                ]],
            ],
        ])->assertOk();

        $order->refresh();
        $this->assertSame('pending', $order->payment_status->value);
        $this->assertSame('partially_paid', $order->payment_link_status->value);
        $this->assertDatabaseHas('notifications', ['type' => 'payment_unapplied']);
    }

    public function test_a_second_different_payment_on_a_settled_charge_alerts_instead_of_granting(): void
    {
        $order = $this->pendingLinkOrder();

        $this->deliver($this->paidPayload($order, $order->amountPaise(), 'pay_first'))->assertOk();
        $coverage = $order->lines()->first()->branch->fresh()->subscription_end->copy();

        // A DIFFERENT payment id against the same, now-paid, charge.
        $this->deliver($this->paidPayload($order->fresh(), $order->amountPaise(), 'pay_second'))->assertOk();

        $this->assertSame('pay_first', $order->fresh()->transaction_number);
        $this->assertTrue($coverage->equalTo($order->lines()->first()->branch->fresh()->subscription_end));
        $this->assertDatabaseHas('notifications', ['type' => 'payment_unapplied']);
    }

    public function test_a_payment_on_a_voided_charge_alerts_instead_of_granting(): void
    {
        $order = $this->pendingLinkOrder();
        app(AccountBillingService::class)->voidOrder($order, 'wrong customer');

        $this->deliver($this->paidPayload($order->fresh(), $order->amountPaise(), 'pay_on_void'))->assertOk();

        $this->assertSame('voided', $order->fresh()->payment_status->value);
        $this->assertDatabaseHas('notifications', ['type' => 'payment_unapplied']);
    }

    /**
     * Design §6 BP4's residual risk: a live link whose order never committed. The
     * money is real, so this must return 200 (a 500 makes Razorpay retry forever)
     * and put it in front of a human.
     */
    public function test_a_paid_link_with_no_matching_order_returns_200_and_alerts(): void
    {
        $this->deliver([
            'event' => 'payment_link.paid',
            'payload' => [
                'payment_link' => ['entity' => [
                    'id' => 'plink_GHOST', 'status' => 'paid', 'amount' => 1000000,
                    'amount_paid' => 1000000, 'reference_id' => 'nothing-here', 'notes' => [],
                ]],
                'payment' => ['entity' => ['id' => 'pay_ghost', 'amount' => 1000000]],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('notifications', ['type' => 'payment_unapplied', 'hostel_id' => null]);
        $this->assertSame(0, SubscriptionOrder::where('transaction_number', 'pay_ghost')->count());
    }

    // =================================================================
    // Design §6 BP1 — the double-handling trap
    // =================================================================

    /**
     * Every payment link has a Razorpay order behind it, and Razorpay may
     * propagate the link's notes onto it. If `order.paid` acted on that, one
     * payment would be applied TWICE — once per arm.
     */
    public function test_order_paid_carrying_propagated_link_notes_grants_nothing(): void
    {
        $order = $this->pendingLinkOrder();
        $ordersBefore = SubscriptionOrder::count();
        $coverageBefore = $order->lines()->first()->branch->fresh()->subscription_end->copy();

        $this->deliver([
            'event' => 'order.paid',
            'payload' => [
                'order' => ['entity' => [
                    'id' => 'order_behind_link',
                    'notes' => [
                        'type' => 'payment_link',
                        'he_order_id' => (string) $order->id,
                        'he_account_id' => (string) $order->account_id,
                    ],
                ]],
                'payment' => ['entity' => ['id' => 'pay_via_order_paid', 'amount' => $order->amountPaise()]],
            ],
        ])->assertOk();

        // No new order, no coverage movement, and the charge is still owed —
        // payment_link.paid owns this payment.
        $this->assertSame($ordersBefore, SubscriptionOrder::count());
        $this->assertSame('pending', $order->fresh()->payment_status->value);
        $this->assertTrue($coverageBefore->equalTo($order->lines()->first()->branch->fresh()->subscription_end));
    }

    /**
     * The belt behind the braces: even with NO type marker, link notes do not
     * carry `branch_id`/`period`, so the legacy arm cannot act on them.
     */
    public function test_order_paid_with_link_notes_but_no_type_still_grants_nothing(): void
    {
        $order = $this->pendingLinkOrder();
        $ordersBefore = SubscriptionOrder::count();

        $this->deliver([
            'event' => 'order.paid',
            'payload' => [
                'order' => ['entity' => ['id' => 'order_x', 'notes' => ['he_order_id' => (string) $order->id]]],
                'payment' => ['entity' => ['id' => 'pay_untyped', 'amount' => $order->amountPaise()]],
            ],
        ])->assertOk();

        $this->assertSame($ordersBefore, SubscriptionOrder::count());
        $this->assertSame('pending', $order->fresh()->payment_status->value);
    }

    // =================================================================
    // Expiry and cancellation — the link dies, the charge does not
    // =================================================================

    public function test_an_expired_link_leaves_the_charge_owed_and_tells_the_operator(): void
    {
        $order = $this->pendingLinkOrder();

        $this->deliver([
            'event' => 'payment_link.expired',
            'payload' => ['payment_link' => ['entity' => [
                'id' => $order->payment_link_id, 'status' => 'expired', 'reference_id' => $order->payment_link_ref,
            ]]],
        ])->assertOk();

        $order->refresh();
        $this->assertSame('expired', $order->payment_link_status->value);
        // STILL OWED — only the link died.
        $this->assertSame('pending', $order->payment_status->value);
        $this->assertSame(1, SubscriptionOrder::outstanding()->where('id', $order->id)->count());

        $this->assertDatabaseHas('notifications', ['type' => 'payment_link_expired']);
    }

    public function test_a_cancelled_link_leaves_the_charge_owed(): void
    {
        $order = $this->pendingLinkOrder();

        $this->deliver([
            'event' => 'payment_link.cancelled',
            'payload' => ['payment_link' => ['entity' => [
                'id' => $order->payment_link_id, 'status' => 'cancelled', 'reference_id' => $order->payment_link_ref,
            ]]],
        ])->assertOk();

        $order->refresh();
        $this->assertSame('cancelled', $order->payment_link_status->value);
        $this->assertSame('pending', $order->payment_status->value);
    }

    /** Razorpay can deliver out of order — a late expiry must not un-pay a paid link. */
    public function test_a_late_expiry_notice_cannot_reopen_a_paid_link(): void
    {
        $order = $this->pendingLinkOrder();

        $this->deliver($this->paidPayload($order, $order->amountPaise()))->assertOk();
        $this->assertSame('paid', $order->fresh()->payment_link_status->value);

        $this->deliver([
            'event' => 'payment_link.expired',
            'payload' => ['payment_link' => ['entity' => [
                'id' => $order->payment_link_id, 'status' => 'expired', 'reference_id' => $order->payment_link_ref,
            ]]],
        ])->assertOk();

        $this->assertSame('paid', $order->fresh()->payment_link_status->value);
        $this->assertSame('paid', $order->fresh()->payment_status->value);
    }

    // =================================================================
    // Resolution
    // =================================================================

    public function test_an_order_is_resolved_by_notes_when_the_link_id_does_not_match(): void
    {
        $order = $this->pendingLinkOrder(10000, 'plink_REAL');

        $payload = $this->paidPayload($order, $order->amountPaise(), 'pay_by_notes');
        // Simulate a stamp that never committed: Razorpay knows a link id we do not.
        $payload['payload']['payment_link']['entity']['id'] = 'plink_UNKNOWN';
        $payload['payload']['payment_link']['entity']['reference_id'] = 'also-unknown';

        $this->deliver($payload)->assertOk();

        $this->assertSame('paid', $order->fresh()->payment_status->value);
        $this->assertSame('pay_by_notes', $order->fresh()->transaction_number);
    }

    public function test_an_order_is_resolved_by_reference_id_as_the_last_resort(): void
    {
        $order = $this->pendingLinkOrder(10000, 'plink_REF');

        $payload = $this->paidPayload($order, $order->amountPaise(), 'pay_by_ref');
        $payload['payload']['payment_link']['entity']['id'] = 'plink_OTHER';
        $payload['payload']['payment_link']['entity']['notes'] = [];

        $this->deliver($payload)->assertOk();

        $this->assertSame('paid', $order->fresh()->payment_status->value);
        $this->assertSame('pay_by_ref', $order->fresh()->transaction_number);
    }

    public function test_an_unknown_event_is_acknowledged_and_ignored(): void
    {
        $this->deliver(['event' => 'payment_link.something_new', 'payload' => []])->assertOk();

        $this->assertSame(0, Notification::count());
    }
}
