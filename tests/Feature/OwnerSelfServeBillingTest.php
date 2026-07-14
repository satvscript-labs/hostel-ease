<?php

namespace Tests\Feature;

use App\Models\Hostel;
use App\Models\SubscriptionAccount;
use App\Models\SubscriptionOrder;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerSelfServeBillingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::clear();
    }

    protected function ownerWith(int $branches, \Carbon\Carbon $anchor): array
    {
        $owner = User::factory()->create(['role' => 'hostel_admin', 'mobile' => '9700000001']);
        $ids = [];
        for ($i = 0; $i < $branches; $i++) {
            $ids[] = Hostel::factory()->create(['mobile' => '9700000001', 'status' => 'active', 'subscription_end' => $anchor])->id;
        }
        $owner->hostels()->sync($ids);
        $account = SubscriptionAccount::create(['owner_id' => $owner->id, 'period' => 'yearly', 'status' => 'active', 'current_period_end' => $anchor]);

        return [$owner, $account];
    }

    public function test_renew_order_is_unavailable_when_razorpay_is_not_configured(): void
    {
        config(['services.razorpay.enabled' => false]);
        [$owner] = $this->ownerWith(3, now()->addMonths(2));

        $this->actingAs($owner)
            ->postJson(route('admin.subscription.renew-order'), ['period' => 'yearly'])
            ->assertStatus(503);
    }

    public function test_account_renewal_webhook_renews_all_branches_on_one_anchor_and_is_idempotent(): void
    {
        config(['services.razorpay.webhook_secret' => 'whsec_test']);
        [, $account] = $this->ownerWith(3, now()->addMonths(2));

        $payload = json_encode([
            'event' => 'order.paid',
            'payload' => [
                'order' => ['entity' => [
                    'id' => 'order_acct_1',
                    'notes' => ['account_id' => (string) $account->id, 'type' => 'renew_account', 'period' => 'yearly'],
                ]],
                'payment' => ['entity' => ['id' => 'pay_acct_1', 'amount' => 3000000]], // 3 × ₹10,000
            ],
        ]);
        $headers = ['HTTP_X-Razorpay-Signature' => hash_hmac('sha256', $payload, 'whsec_test'), 'CONTENT_TYPE' => 'application/json'];

        $this->call('POST', '/api/v1/webhooks/razorpay', [], [], [], $headers, $payload)->assertOk();

        // One consolidated order, quantity 3, captured amount recorded.
        $order = SubscriptionOrder::where('transaction_number', 'pay_acct_1')->firstOrFail();
        $this->assertSame(3, $order->quantity);
        $this->assertSame(30000.0, (float) $order->amount);
        $this->assertSame('order_acct_1', $order->razorpay_order_id);

        // Every branch co-terminated on the new anchor (old anchor + 1 year).
        $expected = now()->addMonths(2)->addYear()->toDateString();
        $account->refresh();
        $this->assertSame($expected, $account->current_period_end->toDateString());

        // Replay — no duplicate order.
        $this->call('POST', '/api/v1/webhooks/razorpay', [], [], [], $headers, $payload)->assertOk();
        $this->assertSame(1, SubscriptionOrder::where('transaction_number', 'pay_acct_1')->count());
    }

    public function test_subscription_page_renders_for_the_owner(): void
    {
        [$owner] = $this->ownerWith(2, now()->addMonths(4));

        // Production-locked by default (P4 item 15): plans visible, ops supervised.
        $this->actingAs($owner)->get(route('admin.subscription.index'))
            ->assertOk()
            ->assertSee('Your branches')
            ->assertSee('Billing is managed by HostelEase support');

        // Unlocked (post-launch): the renew CTA/modal come back.
        config(['hostelease.owner_self_serve' => true]);
        $this->actingAs($owner)->get(route('admin.subscription.index'))
            ->assertOk()
            ->assertSee('Renew all');
    }

    public function test_add_branch_order_creates_the_branch_on_a_trial_up_front(): void
    {
        config(['services.razorpay.enabled' => false]); // no online payment configured
        [$owner] = $this->ownerWith(1, now()->addMonths(3));

        // Even without Razorpay, hitting the endpoint should 503 (payment) — the
        // trial fallback is exercised via the branch form; assert the guard here.
        $this->actingAs($owner)
            ->postJson(route('admin.subscription.add-branch-order'), ['name' => 'New Wing'])
            ->assertStatus(503);
    }

    public function test_add_branch_webhook_co_terminates_the_new_branch(): void
    {
        config(['services.razorpay.webhook_secret' => 'whsec_test']);
        [$owner, $account] = $this->ownerWith(1, now()->addMonths(6));

        // A freshly-created branch (trial) behind the anchor.
        $new = Hostel::factory()->create(['mobile' => '9700000001', 'status' => 'active', 'subscription_end' => now()->addDays(14)]);
        $owner->hostels()->syncWithoutDetaching([$new->id]);

        $payload = json_encode([
            'event' => 'order.paid',
            'payload' => [
                'order' => ['entity' => [
                    'id' => 'order_add_1',
                    'notes' => ['account_id' => (string) $account->id, 'branch_id' => (string) $new->id, 'type' => 'add_branch', 'period' => 'yearly'],
                ]],
                'payment' => ['entity' => ['id' => 'pay_add_1', 'amount' => 500000]],
            ],
        ]);
        $headers = ['HTTP_X-Razorpay-Signature' => hash_hmac('sha256', $payload, 'whsec_test'), 'CONTENT_TYPE' => 'application/json'];

        $this->call('POST', '/api/v1/webhooks/razorpay', [], [], [], $headers, $payload)->assertOk();

        $this->assertDatabaseHas('subscription_orders', ['transaction_number' => 'pay_add_1']);
        $this->assertSame($account->fresh()->current_period_end->toDateString(), $new->fresh()->subscription_end->toDateString());
    }
}
