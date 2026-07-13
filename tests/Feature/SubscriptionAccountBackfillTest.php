<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\BillingPeriod;
use App\Models\Hostel;
use App\Models\Subscription;
use App\Models\SubscriptionAccount;
use App\Models\SubscriptionOrder;
use App\Models\User;
use App\Services\Billing\AccountBackfillService;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionAccountBackfillTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::clear();
    }

    public function test_backfill_builds_one_account_anchored_to_the_furthest_branch_end(): void
    {
        $owner = User::factory()->create(['role' => 'hostel_admin', 'mobile' => '9111100000']);

        $near = Hostel::factory()->create(['mobile' => '9111100000', 'status' => 'active', 'subscription_end' => now()->addMonths(2)]);
        $far = Hostel::factory()->create(['mobile' => '9111100000', 'status' => 'active', 'subscription_end' => now()->addMonths(8)]);
        $owner->hostels()->sync([$near->id, $far->id]);

        Subscription::create(['hostel_id' => $near->id, 'plan' => 'yearly', 'start_date' => now()->subMonths(10), 'end_date' => now()->addMonths(2), 'amount' => 10000, 'payment_status' => 'paid', 'transaction_number' => 'pay_near']);
        Subscription::create(['hostel_id' => $far->id, 'plan' => 'yearly', 'start_date' => now()->subMonths(4), 'end_date' => now()->addMonths(8), 'amount' => 10000, 'payment_status' => 'paid', 'transaction_number' => 'pay_far']);

        $result = app(AccountBackfillService::class)->run();

        $this->assertSame(1, $result['accounts']);
        $this->assertSame(2, $result['orders']);
        $this->assertSame(2, $result['lines']);
        $this->assertSame(0, $result['skipped']);

        $account = SubscriptionAccount::where('owner_id', $owner->id)->firstOrFail();
        $this->assertSame(AccountStatus::Active, $account->status);
        $this->assertSame(BillingPeriod::Yearly, $account->period);
        // Anchor = the furthest branch end-date (no lost time).
        $this->assertSame($far->subscription_end->toDateString(), $account->current_period_end->toDateString());

        $this->assertDatabaseHas('subscription_orders', ['transaction_number' => 'pay_near', 'account_id' => $account->id]);
        $this->assertDatabaseHas('subscription_orders', ['transaction_number' => 'pay_far', 'account_id' => $account->id]);
        $this->assertDatabaseHas('subscription_order_lines', ['branch_id' => $far->id]);
    }

    public function test_backfill_is_idempotent(): void
    {
        $owner = User::factory()->create(['role' => 'hostel_admin', 'mobile' => '9222200000']);
        $branch = Hostel::factory()->create(['mobile' => '9222200000', 'status' => 'active', 'subscription_end' => now()->addYear()]);
        $owner->hostels()->sync([$branch->id]);
        Subscription::create(['hostel_id' => $branch->id, 'plan' => 'yearly', 'start_date' => now(), 'end_date' => now()->addYear(), 'amount' => 10000, 'payment_status' => 'paid', 'transaction_number' => 'pay_idem']);

        $service = app(AccountBackfillService::class);
        $service->run();
        $service->run();

        $this->assertSame(1, SubscriptionAccount::where('owner_id', $owner->id)->count());
        $this->assertSame(1, SubscriptionOrder::where('transaction_number', 'pay_idem')->count());
    }
}
