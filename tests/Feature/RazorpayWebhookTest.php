<?php

namespace Tests\Feature;

use App\Models\Hostel;
use App\Models\Notification;
use App\Models\SubscriptionAccount;
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

    /**
     * The retired Phase 6 shape — notes carrying `branch_id` + `period`, which used to
     * renew that ONE branch at list price from whatever the account looked like when
     * the money arrived (14_S3_DESIGN.md §1 P2/P3). It is no longer applied, and it
     * must not vanish either: money was captured, so it reaches a human, and the
     * webhook still returns 200 so Razorpay stops retrying.
     */
    public function test_a_legacy_per_branch_order_is_never_applied_and_is_raised_for_review(): void
    {
        config(['services.razorpay.webhook_secret' => 'whsec_test']);

        $hostel = $this->ownedBranch('+919770000001', ['subscription_end' => now()->addDays(5)]);
        $coverageBefore = $hostel->subscription_end->copy();

        $payload = json_encode([
            'event' => 'order.paid',
            'payload' => [
                'order' => ['entity' => [
                    'id' => 'order_legacy',
                    'notes' => ['branch_id' => (string) $hostel->id, 'period' => 'monthly'],
                ]],
                'payment' => ['entity' => ['id' => 'pay_legacy', 'amount' => 100000]],
            ],
        ]);
        $headers = ['HTTP_X-Razorpay-Signature' => hash_hmac('sha256', $payload, 'whsec_test'), 'CONTENT_TYPE' => 'application/json'];

        $this->call('POST', '/api/v1/webhooks/razorpay', [], [], [], $headers, $payload)->assertOk();

        $this->assertDatabaseMissing('subscription_orders', ['transaction_number' => 'pay_legacy']);
        $this->assertTrue($coverageBefore->equalTo($hostel->fresh()->subscription_end), 'No coverage may be granted.');
        $this->assertDatabaseHas('notifications', ['type' => 'payment_unapplied', 'hostel_id' => null]);
        $this->assertDatabaseCount('subscriptions', 0);
    }

    /** Same for the retired account-level shapes. */
    public function test_legacy_account_renewal_and_add_branch_orders_are_never_applied(): void
    {
        config(['services.razorpay.webhook_secret' => 'whsec_test']);

        $hostel = $this->ownedBranch('+919770000003', ['subscription_end' => now()->addMonths(2)]);
        $account = SubscriptionAccount::create([
            'owner_id' => $hostel->owner_id, 'period' => 'yearly', 'status' => 'active', 'current_period_end' => now()->addMonths(2),
        ]);

        foreach (['renew_account' => 'pay_l1', 'add_branch' => 'pay_l2'] as $type => $paymentId) {
            $payload = json_encode([
                'event' => 'order.paid',
                'payload' => [
                    'order' => ['entity' => ['id' => 'order_'.$paymentId, 'notes' => [
                        'account_id' => (string) $account->id, 'branch_id' => (string) $hostel->id, 'type' => $type, 'period' => 'yearly',
                    ]]],
                    'payment' => ['entity' => ['id' => $paymentId, 'amount' => 1000000]],
                ],
            ]);

            $this->call('POST', '/api/v1/webhooks/razorpay', [], [], [], [
                'HTTP_X-Razorpay-Signature' => hash_hmac('sha256', $payload, 'whsec_test'),
                'CONTENT_TYPE' => 'application/json',
            ], $payload)->assertOk();

            $this->assertDatabaseMissing('subscription_orders', ['transaction_number' => $paymentId]);
        }

        $this->assertSame(2, Notification::where('type', 'payment_unapplied')->count());
    }

    /**
     * Money was captured on an order with no notes at all — created in the Razorpay
     * dashboard, say. It must NOT 500 — Razorpay would retry forever and never
     * succeed — and it must not vanish either.
     */
    public function test_an_unattributable_payment_is_acknowledged_and_raised_for_review(): void
    {
        config(['services.razorpay.webhook_secret' => 'whsec_test']);

        $payload = json_encode([
            'event' => 'order.paid',
            'payload' => [
                'order' => ['entity' => ['id' => 'order_orphan', 'notes' => []]],
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
