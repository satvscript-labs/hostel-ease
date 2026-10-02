<?php

namespace Tests\Feature;

use App\Models\Hostel;
use App\Models\SubscriptionOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RazorpayWebhookTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A branch with its owner. Required since S1: a charge is recorded against the
     * owner's ACCOUNT, so an ownerless branch has nothing to bill — which is also
     * why a real one can never exist (every creation path sets owner_id).
     */
    private function ownedBranch(string $mobile, array $attributes = []): Hostel
    {
        $owner = User::factory()->create(['role' => 'hostel_admin', 'mobile' => $mobile]);
        $branch = Hostel::factory()->create(array_merge(['mobile' => $mobile, 'owner_id' => $owner->id], $attributes));
        $owner->hostels()->sync([$branch->id]);

        return $branch;
    }

    public function test_order_paid_webhook_renews_the_branch_and_is_idempotent(): void
    {
        config(['services.razorpay.webhook_secret' => 'whsec_test']);

        $hostel = $this->ownedBranch('+919770000001', ['subscription_end' => now()->addDays(5)]);

        $payload = json_encode([
            'event' => 'order.paid',
            'payload' => [
                'order' => ['entity' => [
                    'id' => 'order_test123',
                    'notes' => ['branch_id' => (string) $hostel->id, 'period' => 'monthly'],
                ]],
                'payment' => ['entity' => ['id' => 'pay_test123']],
            ],
        ]);

        $signature = hash_hmac('sha256', $payload, 'whsec_test');
        $headers = ['HTTP_X-Razorpay-Signature' => $signature, 'CONTENT_TYPE' => 'application/json'];

        $this->call('POST', '/api/v1/webhooks/razorpay', [], [], [], $headers, $payload)->assertOk();

        // The ORDER ledger is where a charge lands since S1 (D8) — the legacy
        // `subscriptions` table is never written.
        $this->assertDatabaseHas('subscription_orders', [
            'transaction_number' => 'pay_test123',
            'payment_status' => 'paid',
        ]);
        $this->assertSame(1, SubscriptionOrder::where('transaction_number', 'pay_test123')->count());
        $this->assertDatabaseCount('subscriptions', 0);

        $hostel->refresh();
        $this->assertTrue($hostel->subscription_end->isAfter(now()->addDays(25))); // stacked a month on top

        // Replay the identical payload — must not create a second order.
        $this->call('POST', '/api/v1/webhooks/razorpay', [], [], [], $headers, $payload)->assertOk();
        $this->assertSame(1, SubscriptionOrder::where('transaction_number', 'pay_test123')->count());
    }

    /**
     * Money was captured but cannot be attributed. It must NOT 500 — Razorpay would
     * retry forever and never succeed — and it must not vanish either.
     */
    public function test_an_unattributable_payment_is_acknowledged_and_raised_for_review(): void
    {
        config(['services.razorpay.webhook_secret' => 'whsec_test']);

        $orphan = Hostel::factory()->create(['owner_id' => null, 'mobile' => '+919779999999', 'subscription_end' => now()->addDays(5)]);

        $payload = json_encode([
            'event' => 'order.paid',
            'payload' => [
                'order' => ['entity' => ['id' => 'order_orphan', 'notes' => ['branch_id' => (string) $orphan->id, 'period' => 'yearly']]],
                'payment' => ['entity' => ['id' => 'pay_orphan', 'amount' => 1000000]],
            ],
        ]);

        $this->call('POST', '/api/v1/webhooks/razorpay', [], [], [], [
            'HTTP_X-Razorpay-Signature' => hash_hmac('sha256', $payload, 'whsec_test'),
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertOk();

        $this->assertDatabaseMissing('subscription_orders', ['transaction_number' => 'pay_orphan']);
        $this->assertDatabaseHas('notifications', ['type' => 'payment_unapplied', 'hostel_id' => null]);
    }

    public function test_order_paid_records_the_captured_amount_and_order_id(): void
    {
        config(['services.razorpay.webhook_secret' => 'whsec_test']);

        $hostel = $this->ownedBranch('+919770000002', ['subscription_end' => now()->addDays(5)]);

        $payload = json_encode([
            'event' => 'order.paid',
            'payload' => [
                'order' => ['entity' => [
                    'id' => 'order_amt1',
                    'notes' => ['branch_id' => (string) $hostel->id, 'period' => 'yearly'],
                ]],
                'payment' => ['entity' => ['id' => 'pay_amt1', 'amount' => 1000000]], // ₹10,000 in paise
            ],
        ]);

        $signature = hash_hmac('sha256', $payload, 'whsec_test');
        $headers = ['HTTP_X-Razorpay-Signature' => $signature, 'CONTENT_TYPE' => 'application/json'];

        $this->call('POST', '/api/v1/webhooks/razorpay', [], [], [], $headers, $payload)->assertOk();

        $order = SubscriptionOrder::where('transaction_number', 'pay_amt1')->firstOrFail();
        $this->assertSame('order_amt1', $order->razorpay_order_id);
        $this->assertSame(10000.0, (float) $order->amount); // recorded what was captured, not just the quote
        // Razorpay handled it, so the collection channel is the online one.
        $this->assertSame('checkout', $order->collection->value);
    }

    public function test_payment_failed_is_acknowledged_without_granting_coverage(): void
    {
        config(['services.razorpay.webhook_secret' => 'whsec_test']);

        $payload = json_encode([
            'event' => 'payment.failed',
            'payload' => ['payment' => ['entity' => [
                'id' => 'pay_fail1', 'order_id' => 'order_fail1', 'error_description' => 'card declined',
            ]]],
        ]);

        $signature = hash_hmac('sha256', $payload, 'whsec_test');

        $this->call('POST', '/api/v1/webhooks/razorpay', [], [], [], [
            'HTTP_X-Razorpay-Signature' => $signature, 'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertOk();

        $this->assertDatabaseMissing('subscription_orders', ['transaction_number' => 'pay_fail1']);
    }

    public function test_refund_raises_a_super_admin_review_alert(): void
    {
        config(['services.razorpay.webhook_secret' => 'whsec_test']);

        $payload = json_encode([
            'event' => 'refund.created',
            'payload' => ['refund' => ['entity' => [
                'id' => 'rfnd_1', 'payment_id' => 'pay_x', 'amount' => 500000,
            ]]],
        ]);

        $signature = hash_hmac('sha256', $payload, 'whsec_test');

        $this->call('POST', '/api/v1/webhooks/razorpay', [], [], [], [
            'HTTP_X-Razorpay-Signature' => $signature, 'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertOk();

        $this->assertDatabaseHas('notifications', ['type' => 'refund_review', 'hostel_id' => null]);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        config(['services.razorpay.webhook_secret' => 'whsec_test']);

        $response = $this->call('POST', '/api/v1/webhooks/razorpay', [], [], [], [
            'HTTP_X-Razorpay-Signature' => 'not-a-real-signature',
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['event' => 'order.paid']));

        $response->assertStatus(400);
    }
}
