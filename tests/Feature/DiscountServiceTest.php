<?php

namespace Tests\Feature;

use App\Models\Discount;
use App\Models\DiscountRule;
use App\Models\SubscriptionAccount;
use App\Models\User;
use App\Services\Billing\DiscountService;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscountServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::clear();
        config()->set('hostelease.discount_stacking', 'stack');
    }

    protected function account(): SubscriptionAccount
    {
        $owner = User::factory()->create(['role' => 'hostel_admin']);

        return SubscriptionAccount::create(['owner_id' => $owner->id, 'period' => 'yearly', 'status' => 'active']);
    }

    public function test_no_discounts_leaves_subtotal_untouched(): void
    {
        $b = app(DiscountService::class)->preview($this->account(), 50000, 5, 'renewal');

        $this->assertSame(0.0, $b['discount_total']);
        $this->assertSame(50000.0, $b['final']);
    }

    public function test_permanent_percentage_discount_applies_to_renewal(): void
    {
        $account = $this->account();
        Discount::create(['account_id' => $account->id, 'recurrence' => 'every_renewal', 'type' => 'percentage', 'value' => 10, 'reason' => 'loyal', 'status' => 'active']);

        $b = app(DiscountService::class)->preview($account, 50000, 5, 'renewal');

        $this->assertSame(5000.0, $b['manual_amount']);
        $this->assertSame(45000.0, $b['final']);
    }

    public function test_next_renewal_discount_does_not_apply_to_add_branch(): void
    {
        $account = $this->account();
        Discount::create(['account_id' => $account->id, 'recurrence' => 'one_renewal', 'type' => 'fixed', 'value' => 2000, 'reason' => 'promo', 'status' => 'active']);

        $service = app(DiscountService::class);
        $this->assertSame(0.0, $service->preview($account, 10000, 1, 'add_branch')['manual_amount']);
        $this->assertSame(2000.0, $service->preview($account, 10000, 1, 'renewal')['manual_amount']);
    }

    public function test_volume_tier_and_manual_stack_sequentially(): void
    {
        $account = $this->account();
        DiscountRule::create(['min_quantity' => 3, 'type' => 'percentage', 'value' => 10, 'active' => true]);
        Discount::create(['account_id' => $account->id, 'recurrence' => 'every_renewal', 'type' => 'percentage', 'value' => 10, 'reason' => 'vip', 'status' => 'active']);

        $b = app(DiscountService::class)->preview($account, 50000, 5, 'renewal');

        // Volume 10% of 50000 = 5000; manual 10% of the remaining 45000 = 4500.
        $this->assertSame(5000.0, $b['volume_amount']);
        $this->assertSame(4500.0, $b['manual_amount']);
        $this->assertSame(9500.0, $b['discount_total']);
        $this->assertSame(40500.0, $b['final']);
    }
}
