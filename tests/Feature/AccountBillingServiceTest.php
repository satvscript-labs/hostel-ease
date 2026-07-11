<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\DiscountStatus;
use App\Models\Discount;
use App\Models\Hostel;
use App\Models\SubscriptionAccount;
use App\Models\User;
use App\Services\Billing\AccountBillingService;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountBillingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::clear();
    }

    protected function service(): AccountBillingService
    {
        return app(AccountBillingService::class);
    }

    protected function ownerWithBranches(array $ends, string $mobile = '9000000001'): array
    {
        $owner = User::factory()->create(['role' => 'hostel_admin', 'mobile' => $mobile]);
        $branches = collect($ends)->map(fn ($end) => Hostel::factory()->create([
            'mobile' => $mobile,
            'status' => $end && $end->isFuture() ? 'active' : 'expired',
            'subscription_end' => $end,
        ]));
        $owner->hostels()->sync($branches->pluck('id')->all());

        return [$owner, $branches];
    }

    public function test_record_branch_renewal_builds_the_account_order_and_line(): void
    {
        [$owner, $branches] = $this->ownerWithBranches([now()->subDay()]);
        $branch = $branches->first();

        $this->service()->recordBranchRenewal($branch, 'yearly', [
            'amount' => 10000, 'payment_status' => 'paid', 'payment_method' => 'cash', 'transaction_number' => 'off_1',
        ]);

        $account = SubscriptionAccount::where('owner_id', $owner->id)->firstOrFail();
        $this->assertSame(AccountStatus::Active, $account->status);
        $this->assertNotNull($account->current_period_end);
        $this->assertDatabaseHas('subscription_orders', ['transaction_number' => 'off_1', 'account_id' => $account->id, 'quantity' => 1]);
        $this->assertDatabaseHas('subscription_order_lines', ['branch_id' => $branch->id]);

        $this->assertTrue($branch->fresh()->isActive());
    }

    public function test_renew_account_co_terminates_every_branch_on_one_anchor(): void
    {
        [$owner, $branches] = $this->ownerWithBranches([now()->addMonths(2), now()->addMonths(2)]);
        $account = SubscriptionAccount::create([
            'owner_id' => $owner->id, 'period' => 'yearly', 'status' => 'active', 'current_period_end' => now()->addMonths(2),
        ]);

        $order = $this->service()->renewAccount($account, 'yearly', ['payment_status' => 'paid', 'payment_method' => 'cash']);

        $expected = now()->addMonths(2)->addYear()->toDateString();
        $account->refresh();
        $this->assertSame($expected, $account->current_period_end->toDateString());
        $this->assertSame(2, $order->quantity);
        $this->assertSame('20000.00', (string) $order->subtotal); // 2 branches × ₹10,000

        foreach ($branches as $branch) {
            $this->assertSame($expected, $branch->fresh()->subscription_end->toDateString());
        }
    }

    public function test_add_branch_charges_a_prorated_amount_and_co_terminates(): void
    {
        [$owner, $branches] = $this->ownerWithBranches([now()->addMonths(6)]);
        $account = SubscriptionAccount::create([
            'owner_id' => $owner->id, 'period' => 'yearly', 'status' => 'active', 'current_period_end' => now()->addMonths(6),
        ]);

        $newBranch = Hostel::factory()->create(['mobile' => '9000000001', 'status' => 'expired', 'subscription_end' => null]);
        $owner->hostels()->syncWithoutDetaching([$newBranch->id]);

        $order = $this->service()->addBranch($account, $newBranch, ['payment_status' => 'paid', 'payment_method' => 'cash']);

        // A prorated slice of the ₹10,000 yearly unit — strictly between ₹0 and full price.
        $this->assertGreaterThan(0, (float) $order->subtotal);
        $this->assertLessThan(10000, (float) $order->subtotal);

        // The new branch co-terminates on the existing anchor.
        $this->assertSame($account->current_period_end->toDateString(), $newBranch->fresh()->subscription_end->toDateString());
    }

    public function test_comp_grants_zero_rupee_coverage(): void
    {
        [$owner] = $this->ownerWithBranches([now()->addMonth()]);
        $account = SubscriptionAccount::create([
            'owner_id' => $owner->id, 'period' => 'yearly', 'status' => 'active', 'current_period_end' => now()->addMonth(),
        ]);

        $order = $this->service()->comp($account, 'yearly', 'goodwill for a friend');

        $this->assertSame('0.00', (string) $order->amount);
        $this->assertDatabaseHas('subscription_orders', ['id' => $order->id, 'payment_method' => 'comp', 'payment_status' => 'paid']);
    }

    public function test_one_time_manual_discount_is_consumed_after_a_renewal(): void
    {
        [$owner] = $this->ownerWithBranches([now()->addMonth()]);
        $account = SubscriptionAccount::create([
            'owner_id' => $owner->id, 'period' => 'yearly', 'status' => 'active', 'current_period_end' => now()->addMonth(),
        ]);
        $discount = Discount::create(['account_id' => $account->id, 'recurrence' => 'one_time', 'type' => 'percentage', 'value' => 20, 'reason' => 'launch', 'status' => 'active']);

        $this->service()->renewAccount($account, 'yearly', ['payment_status' => 'paid', 'payment_method' => 'cash']);

        $this->assertSame(DiscountStatus::Consumed, $discount->fresh()->status);
    }
}
