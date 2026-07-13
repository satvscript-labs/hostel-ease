<?php

namespace Tests\Feature;

use App\Models\Hostel;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RazorpayWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_paid_webhook_renews_the_branch_and_is_idempotent(): void
    {
        config(['services.razorpay.webhook_secret' => 'whsec_test']);

        $hostel = Hostel::factory()->create(['subscription_end' => now()->addDays(5)]);

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

        $this->assertDatabaseHas('subscriptions', [
            'hostel_id' => $hostel->id,
            'transaction_number' => 'pay_test123',
            'payment_status' => 'paid',
        ]);
        $this->assertSame(1, Subscription::where('transaction_number', 'pay_test123')->count());

        $hostel->refresh();
        $this->assertTrue($hostel->subscription_end->isAfter(now()->addDays(25))); // stacked a month on top

        // Replay the identical payload — must not create a second Subscription row.
        $this->call('POST', '/api/v1/webhooks/razorpay', [], [], [], $headers, $payload)->assertOk();
        $this->assertSame(1, Subscription::where('transaction_number', 'pay_test123')->count());
    }

    public function test_order_paid_records_the_captured_amount_and_order_id(): void
    {
        config(['services.razorpay.webhook_secret' => 'whsec_test']);

        $hostel = Hostel::factory()->create(['subscription_end' => now()->addDays(5)]);

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

        $sub = Subscription::where('transaction_number', 'pay_amt1')->firstOrFail();
        $this->assertSame('order_amt1', $sub->razorpay_order_id);
        $this->assertSame(10000.0, (float) $sub->amount); // recorded what was captured, not just the quote
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

        $this->assertDatabaseMissing('subscriptions', ['transaction_number' => 'pay_fail1']);
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
