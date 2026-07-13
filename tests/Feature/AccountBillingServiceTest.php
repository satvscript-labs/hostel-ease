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

    public function test_add_branch_prorates_the_gap_from_the_branch_coverage_not_from_today(): void
    {
        // Anchor is 12 months out; the branch being added is already covered for
        // 6 of those months. Add-to-cycle must bill only the ~6-month gap, not a
        // near-full year from today (the bug: charging now→anchor).
        [$owner, $branches] = $this->ownerWithBranches([now()->addMonths(12), now()->addMonths(6)]);
        $account = SubscriptionAccount::create([
            'owner_id' => $owner->id, 'period' => 'yearly', 'status' => 'active', 'current_period_end' => now()->addMonths(12),
        ]);
        $behind = $branches->last(); // ends in ~6 months

        $quote = $this->service()->quoteAddBranch($account, $behind);

        // ~183 days remaining (6mo → 12mo), not ~365 (today → 12mo).
        $this->assertGreaterThan(150, $quote['days_remaining']);
        $this->assertLessThan(200, $quote['days_remaining']);
        // ≈ ₹10,000 × 183/365 ≈ ₹5,000 — a half-year slice, not the full ₹10,000.
        $this->assertGreaterThan(4000, (float) $quote['prorated']);
        $this->assertLessThan(6000, (float) $quote['prorated']);
    }

    public function test_add_branch_on_a_trial_period_account_still_charges_a_paid_rate(): void
    {
        // Account period reads 'trial' (as it does right after a trial branch is
        // provisioned), but it has a real future anchor. The top-up must price at
        // the paid yearly rate — never ₹0 (the bug: pricing off the trial period).
        [$owner, $branches] = $this->ownerWithBranches([now()->addMonths(6), now()->addMonths(3)]);
        $account = SubscriptionAccount::create([
            'owner_id' => $owner->id, 'period' => 'trial', 'status' => 'active', 'current_period_end' => now()->addMonths(6),
        ]);
        $behind = $branches->last();

        $quote = $this->service()->quoteAddBranch($account, $behind);

        $this->assertGreaterThan(0, (float) $quote['prorated']);
        $this->assertSame(10000.0, (float) $quote['unit']); // yearly list price, not the trial's ₹0
    }

    public function test_add_branch_clears_a_stale_trial_period_after_a_paid_top_up(): void
    {
        // Account period is still 'trial' (left over from the added branch's own
        // trial provisioning), but the top-up we're about to charge is real money.
        // The account must read Active afterwards, not Trial — a paid customer
        // should never see a "Trial" pill (the bug: refreshAccountAnchor() kept
        // inheriting the stale 'trial' period after addBranch()'s own paid charge).
        [$owner, $branches] = $this->ownerWithBranches([now()->addMonths(6), now()->addMonths(3)]);
        $account = SubscriptionAccount::create([
            'owner_id' => $owner->id, 'period' => 'trial', 'status' => 'trial', 'current_period_end' => now()->addMonths(6),
        ]);
        $behind = $branches->last();

        $this->service()->addBranch($account, $behind, ['payment_status' => 'paid', 'payment_method' => 'cash']);

        $account->refresh();
        $this->assertSame(AccountStatus::Active, $account->status);
        $this->assertNotSame('trial', $account->period->value);
    }

    public function test_align_clears_a_stale_trial_period_after_a_paid_top_up(): void
    {
        [$owner, $branches] = $this->ownerWithBranches([now()->addMonths(6), now()->addMonths(2)]);
        $account = SubscriptionAccount::create([
            'owner_id' => $owner->id, 'period' => 'trial', 'status' => 'trial', 'current_period_end' => now()->addMonths(6),
        ]);

        $order = $this->service()->align($account, ['payment_status' => 'paid', 'payment_method' => 'cash']);

        $this->assertNotNull($order);
        $account->refresh();
        $this->assertSame(AccountStatus::Active, $account->status);
        $this->assertNotSame('trial', $account->period->value);
    }

    public function test_renew_override_is_recorded_as_a_discount(): void
    {
        // 2 branches × ₹10,000 = ₹20,000 auto. Operator overrides to ₹18,000 →
        // the ₹2,000 gap must land in discount_total, so subtotal−discount==amount.
        [$owner] = $this->ownerWithBranches([now()->addMonths(2), now()->addMonths(2)]);
        $account = SubscriptionAccount::create([
            'owner_id' => $owner->id, 'period' => 'yearly', 'status' => 'active', 'current_period_end' => now()->addMonths(2),
        ]);

        $order = $this->service()->renewAccount($account, 'yearly', ['amount' => 18000, 'payment_status' => 'paid', 'payment_method' => 'cash']);

        $this->assertSame('20000.00', (string) $order->subtotal);
        $this->assertSame('2000.00', (string) $order->discount_total);
        $this->assertSame('18000.00', (string) $order->amount);
        $this->assertSame((float) $order->subtotal - (float) $order->discount_total, (float) $order->amount);
    }

    public function test_add_branch_override_is_recorded_as_a_discount(): void
    {
        [$owner, $branches] = $this->ownerWithBranches([now()->addMonths(12), now()->addMonths(6)]);
        $account = SubscriptionAccount::create([
            'owner_id' => $owner->id, 'period' => 'yearly', 'status' => 'active', 'current_period_end' => now()->addMonths(12),
        ]);
        $behind = $branches->last();
        $auto = (float) $this->service()->quoteAddBranch($account, $behind)['breakdown']['final'];

        $order = $this->service()->addBranch($account, $behind, ['amount' => $auto - 200, 'payment_status' => 'paid', 'payment_method' => 'cash']);

        $this->assertSame('200.00', (string) $order->discount_total);
        $this->assertEqualsWithDelta($auto - 200, (float) $order->amount, 0.01);
        $this->assertEqualsWithDelta((float) $order->subtotal - (float) $order->discount_total, (float) $order->amount, 0.01);
    }

    public function test_align_quote_and_override_discount(): void
    {
        // Two branches behind the anchor by different gaps.
        [$owner] = $this->ownerWithBranches([now()->addMonths(12), now()->addMonths(6), now()->addMonths(3)]);
        $account = SubscriptionAccount::create([
            'owner_id' => $owner->id, 'period' => 'yearly', 'status' => 'active', 'current_period_end' => now()->addMonths(12),
        ]);

        $quote = $this->service()->quoteAlign($account);
        $this->assertSame(2, $quote['count']);                 // two branches are behind
        $this->assertGreaterThan(0, $quote['subtotal']);

        // No override → no discount recorded (Align applies no engine discount).
        $plain = $this->service()->align($account, ['payment_status' => 'paid', 'payment_method' => 'cash']);
        $this->assertSame('0.00', (string) $plain->discount_total);

        // Reset a branch behind again and override the align total downward.
        $account->owner->hostels->first()->update(['subscription_end' => now()->addMonths(4)]);
        $account->refresh();
        $subtotal = $this->service()->quoteAlign($account)['subtotal'];
        $order = $this->service()->align($account, ['amount' => $subtotal - 300, 'payment_status' => 'paid', 'payment_method' => 'cash']);
        $this->assertSame('300.00', (string) $order->discount_total);
        $this->assertEqualsWithDelta((float) $order->subtotal - (float) $order->discount_total, (float) $order->amount, 0.01);
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
