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
