<?php

namespace Tests\Feature;

use App\Models\DiscountRule;
use App\Models\Hostel;
use App\Models\SubscriptionAccount;
use App\Models\SubscriptionOrder;
use App\Models\SubscriptionOrderLine;
use App\Models\User;
use App\Services\Billing\AccountBillingService;
use App\Services\HostelService;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * One renewal date per account (doc 22, owner decision 2026-10-06). A gift never adds
 * dates to one branch — that moved the whole account's renewal date. It is:
 *   · free renewals for chosen branches (the default),
 *   · extending the renewal date for EVERY branch (on a trial: extending the trial),
 *   · bringing a behind branch up to the date free (Add to cycle → Complimentary).
 */
class GiftTest extends TestCase
{
    use RefreshDatabase;

    private AccountBillingService $billing;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::clear();
        $this->billing = app(AccountBillingService::class);
    }

    /** An owner with $n branches on a paid yearly term bought six months ago. */
    private function account(int $n = 2, string $period = 'yearly', string $mobile = '9833300001'): array
    {
        $owner = User::factory()->create(['role' => 'hostel_admin', 'mobile' => $mobile]);
        $ids = [];
        for ($i = 0; $i < $n; $i++) {
            $ids[] = Hostel::factory()->create(['owner_id' => $owner->id, 'mobile' => $mobile, 'subscription_start' => null, 'subscription_end' => null])->id;
        }
        $owner->hostels()->sync($ids);
        $owner->forceFill(['hostel_id' => $ids[0]])->save();
        $account = $this->billing->accountFor($owner);

        $this->travel($period === 'monthly' ? -10 : -6)->{$period === 'monthly' ? 'days' : 'months'}();
        $this->billing->renewAccount($account->fresh(), $period, ['payment_status' => 'paid', 'payment_method' => 'cash']);
        $this->travelBack();

        return [$owner, $account->fresh(), Hostel::whereIn('id', $ids)->orderBy('id')->get()];
    }

    private function superAdmin(): User
    {
        return User::factory()->superAdmin()->create();
    }

    // ── free renewals ────────────────────────────────────────────────────

    public function test_granting_free_renewals_never_moves_the_renewal_date(): void
    {
        [, $account, $branches] = $this->account(2);
        $date = $account->current_period_end->toDateString();

        $this->actingAs($this->superAdmin())->post(route('superadmin.accounts.free-renewals', $account), [
            'branches' => [$branches[0]->id], 'count' => 2, 'reason' => 'Referred two customers',
        ])->assertSessionHas('success');

        $this->assertSame(2, $branches[0]->fresh()->free_renewals);
        $this->assertSame(0, $branches[1]->fresh()->free_renewals);
        $this->assertSame($date, $account->fresh()->current_period_end->toDateString());
        $this->assertSame($date, $branches[0]->fresh()->subscription_end->toDateString(), 'No dates added to the branch.');
    }

    public function test_the_count_is_set_not_added_and_zero_removes_it(): void
    {
        [, $account, $branches] = $this->account(1);

        $this->billing->setFreeRenewals($account, [$branches[0]->id], 3);
        $this->billing->setFreeRenewals($account, [$branches[0]->id], 1);
        $this->assertSame(1, $branches[0]->fresh()->free_renewals);

        $this->billing->setFreeRenewals($account, [$branches[0]->id], 0);
        $this->assertSame(0, $branches[0]->fresh()->free_renewals);
    }

    public function test_only_this_accounts_branches_on_the_plan_can_be_given_one(): void
    {
        [, $account, $branches] = $this->account(2);
        [, , $others] = $this->account(1, 'yearly', '9833300099');
        $this->billing->cancelBranch($branches[1]->fresh(), 'leaving');

        $changed = $this->billing->setFreeRenewals($account->fresh(), [$branches[0]->id, $branches[1]->id, $others[0]->id], 1);

        $this->assertSame(1, $changed);
        $this->assertSame(0, $branches[1]->fresh()->free_renewals, 'A leaving branch is never renewed.');
        $this->assertSame(0, $others[0]->fresh()->free_renewals, 'Another customer\'s branch.');
    }

    public function test_the_next_renewal_is_free_for_that_branch_and_adds_up(): void
    {
        [, $account, $branches] = $this->account(3);
        $this->billing->setFreeRenewals($account, [$branches[1]->id], 1);

        $q = $this->billing->quoteRenewal($account->fresh(), 'yearly');
        $this->assertCount(1, $q['complimentary']);
        $this->assertEqualsWithDelta(20000, $q['total'], 0.001, '3 branches, one free.');

        $order = $this->billing->renewAccount($account->fresh(), 'yearly', ['payment_status' => 'paid', 'payment_method' => 'cash']);

        $this->assertEqualsWithDelta(20000, (float) $order->amount, 0.001);
        $this->assertEqualsWithDelta((float) $order->subtotal - (float) $order->discount_total, (float) $order->amount, 0.001);
        $this->assertEqualsWithDelta(20000, (float) $order->lines()->sum('amount'), 0.001);
        $free = $order->lines()->where('branch_id', $branches[1]->id)->sole();
        $this->assertTrue($free->complimentary);
        $this->assertSame(0.0, (float) $free->amount);
        $this->assertSame(0, $branches[1]->fresh()->free_renewals, 'Used.');
        $this->assertTrue($branches[1]->fresh()->isActive());
    }

    public function test_the_volume_tier_still_counts_a_free_branch(): void
    {
        DiscountRule::create(['min_quantity' => 3, 'type' => 'percentage', 'value' => 10, 'active' => true]);
        [, $account, $branches] = $this->account(3);
        $this->billing->setFreeRenewals($account, [$branches[0]->id], 1);

        $q = $this->billing->quoteRenewal($account->fresh(), 'yearly');

        $this->assertEqualsWithDelta(2000, $q['breakdown']['volume_amount'], 0.001, '10% of what is left to pay (₹20,000).');
        $this->assertEqualsWithDelta(18000, $q['total'], 0.001);
    }

    public function test_a_free_renewal_is_used_only_when_the_renewal_is_paid_and_given_back_on_void(): void
    {
        [, $account, $branches] = $this->account(2);
        $this->billing->setFreeRenewals($account, [$branches[0]->id], 1);

        $pending = $this->billing->renewAccount($account->fresh(), 'yearly', ['payment_status' => 'pending']);
        $this->assertSame(1, $branches[0]->fresh()->free_renewals, 'Quoting uses nothing.');

        $this->billing->acceptOrder($pending->fresh(), ['payment_method' => 'upi']);
        $this->assertSame(0, $branches[0]->fresh()->free_renewals, 'Paying uses it.');

        $this->billing->voidOrder($pending->fresh(), 'recorded in error');
        $this->assertSame(1, $branches[0]->fresh()->free_renewals, 'Voiding hands it back.');
    }

    public function test_a_renewal_priced_with_a_gift_that_was_then_removed_is_not_payable(): void
    {
        [$owner, $account, $branches] = $this->account(2);
        config(['hostelease.owner_self_serve' => true, 'services.razorpay.enabled' => true, 'services.razorpay.key' => 'k', 'services.razorpay.secret' => 's']);
        $this->billing->setFreeRenewals($account, [$branches[0]->id], 1);
        $pending = $this->billing->renewAccount($account->fresh(), 'yearly', ['payment_status' => 'pending', 'collection' => 'checkout']);

        $this->billing->setFreeRenewals($account->fresh(), [$branches[0]->id], 0);

        $this->assertSame('stale', $pending->coverageState(true));
        $this->actingAs($owner)->get(route('admin.subscription.index'))->assertSee('Amount changed — renew again for the new total');

        // Renewing again prices that branch normally.
        Http::fake([
            'api.razorpay.com/v1/orders' => Http::response(['id' => 'order_G', 'amount' => 1, 'currency' => 'INR', 'receipt' => 'r'], 200),
            'api.razorpay.com/v1/orders/*/payments' => Http::response(['items' => []], 200),
        ]);
        $this->actingAs($owner)->postJson(route('admin.subscription.checkout'), ['charge' => 'renewal', 'period' => 'yearly'])
            ->assertOk()->assertJsonPath('razorpay.amount', 2000000);
    }

    // ── extend the renewal date (the whole account) ─────────────────────

    public function test_extending_moves_every_branch_and_the_renewal_date_together(): void
    {
        [, $account, $branches] = $this->account(2);
        $new = $account->current_period_end->copy()->addMonth()->toDateString();

        $this->actingAs($this->superAdmin())->post(route('superadmin.accounts.extend', $account), [
            'amount' => 1, 'unit' => 'months', 'reason' => 'Downtime in March',
        ])->assertSessionHas('success');

        $this->assertSame($new, $account->fresh()->current_period_end->toDateString());
        foreach ($branches as $b) {
            $this->assertSame($new, $b->fresh()->subscription_end->toDateString());
        }
        $this->assertSame(0.0, (float) SubscriptionOrder::where('kind', 'comp')->sole()->amount);
    }

    public function test_a_behind_branch_is_not_carried_along_free(): void
    {
        [$owner, $account] = $this->account(1);
        $behind = app(HostelService::class)->createBranchForOwner($owner, ['name' => 'Behind Wing']);

        $this->billing->extendRenewalDate($account->fresh(), 1, 'months', 'goodwill');

        $this->assertNull($behind->fresh()->subscription_end, 'Its gap is still owed.');
    }

    public function test_extending_needs_a_live_renewal_date(): void
    {
        [, $account] = $this->account(1);
        $this->travel(8)->months();
        $this->billing->refreshAccountAnchor($account->fresh());

        $this->actingAs($this->superAdmin())->post(route('superadmin.accounts.extend', $account), [
            'amount' => 10, 'unit' => 'days', 'reason' => 'x',
        ])->assertSessionHas('error');
    }

    public function test_on_a_running_trial_it_extends_the_trial(): void
    {
        $owner = User::factory()->create(['role' => 'hostel_admin', 'mobile' => '9833300050']);
        $first = Hostel::factory()->create(['owner_id' => $owner->id, 'subscription_start' => null, 'subscription_end' => null]);
        $owner->hostels()->sync([$first->id]);
        $this->billing->recordBranchRenewal($first, 'trial', ['payment_status' => 'paid']);
        $account = SubscriptionAccount::where('owner_id', $owner->id)->sole();
        $end = $account->current_period_end->copy();

        $this->billing->extendRenewalDate($account, 7, 'days', 'needs more time');

        $this->assertSame($end->addDays(7)->toDateString(), $account->fresh()->current_period_end->toDateString());
        $this->assertSame('trial', $account->fresh()->status->value);
    }

    // ── bring a behind branch up to date free ───────────────────────────

    public function test_add_to_cycle_can_bring_a_branch_up_to_date_free_without_moving_the_date(): void
    {
        [$owner, $account] = $this->account(1);
        $behind = app(HostelService::class)->createBranchForOwner($owner, ['name' => 'Free Wing']);
        $date = $account->current_period_end->toDateString();

        $this->actingAs($this->superAdmin())->post(route('superadmin.accounts.add-branch', $account), [
            'branch_id' => $behind->id, 'complimentary' => 1, 'collect' => 'link',
        ])->assertSessionHas('success');

        $order = SubscriptionOrder::latest('id')->first();
        $this->assertSame('comp', $order->kind->value);
        $this->assertSame(0.0, (float) $order->amount);
        $this->assertSame('paid', $order->payment_status->value, 'A gift is never "owed", and never a link.');
        $this->assertSame($date, $behind->fresh()->subscription_end->toDateString());
        $this->assertSame($date, $account->fresh()->current_period_end->toDateString());
    }

    // ── existing gifted time becomes free renewals (audit --fix) ────────

    public function test_gifted_time_past_the_renewal_date_folds_into_free_renewals(): void
    {
        [, $account, $branches] = $this->account(2);
        $date = $account->current_period_end->copy();

        // An old comp: one branch a year past everyone else — the account's date moved with it.
        $comp = SubscriptionOrder::create(['account_id' => $account->id, 'period' => 'yearly', 'kind' => 'comp', 'quantity' => 1,
            'subtotal' => 0, 'discount_total' => 0, 'amount' => 0, 'payment_status' => 'paid', 'payment_method' => 'comp', 'collection' => 'offline']);
        SubscriptionOrderLine::create(['order_id' => $comp->id, 'branch_id' => $branches[0]->id, 'amount' => 0, 'start_date' => $date, 'end_date' => $date->copy()->addYear()]);
        app(\App\Services\Billing\CoverageMirror::class)->sync($account->fresh());
        $this->billing->refreshAccountAnchor($account->fresh());
        $this->assertSame($date->copy()->addYear()->toDateString(), $account->fresh()->current_period_end->toDateString(), 'The old problem.');

        $this->artisan('hostelease:audit-coverage', ['--fix' => true, '--force' => true])->assertSuccessful();

        $this->assertSame($date->toDateString(), $account->fresh()->current_period_end->toDateString(), 'One date again.');
        $this->assertSame($date->toDateString(), $branches[0]->fresh()->subscription_end->toDateString());
        $this->assertSame(1, $branches[0]->fresh()->free_renewals, 'The year is kept — as the next renewal, free.');

        // Running it again changes nothing.
        $this->artisan('hostelease:audit-coverage', ['--fix' => true, '--force' => true])->expectsOutputToContain('Nothing to fix');
        $this->assertSame(1, $branches[0]->fresh()->free_renewals);
    }

    public function test_an_account_with_nothing_paid_is_left_alone(): void
    {
        $owner = User::factory()->create(['role' => 'hostel_admin']);
        $branch = Hostel::factory()->create(['owner_id' => $owner->id, 'subscription_end' => now()->addYears(2)]);
        $owner->hostels()->sync([$branch->id]);
        $account = $this->billing->accountFor($owner);

        $this->assertSame([], $this->billing->foldGiftsIntoFreeRenewals($account));
        $this->assertSame(now()->addYears(2)->toDateString(), $branch->fresh()->subscription_end->toDateString());
    }

    // ── what people see ──────────────────────────────────────────────────

    public function test_the_owner_sees_next_renewal_free(): void
    {
        [$owner, $account, $branches] = $this->account(2);
        config(['hostelease.owner_self_serve' => true, 'services.razorpay.enabled' => true, 'services.razorpay.key' => 'k', 'services.razorpay.secret' => 's']);
        $this->billing->setFreeRenewals($account, [$branches[0]->id], 1);

        $this->actingAs($owner)->get(route('admin.subscription.index'))
            ->assertOk()
            ->assertSee('Next renewal free');
    }

    public function test_account_360_shows_the_gift_and_the_give_free_time_modal(): void
    {
        [, $account, $branches] = $this->account(2);
        $this->billing->setFreeRenewals($account, [$branches[0]->id], 2);

        $this->actingAs($this->superAdmin())->get(route('superadmin.accounts.show', $account))
            ->assertOk()
            ->assertSee('Give free time')
            ->assertSee('2 free renewals')
            ->assertSee('Extend renewal date')
            ->assertDontSee('Complimentary coverage');
    }

    public function test_the_hostel_edit_form_cannot_move_coverage_any_more(): void
    {
        [, , $branches] = $this->account(1);
        $branch = $branches[0]->fresh();
        $end = $branch->subscription_end->toDateString();

        $this->actingAs($this->superAdmin())->put(route('superadmin.hostels.update', $branch), [
            'name' => $branch->name, 'owner_name' => $branch->owner_name ?? 'Owner', 'mobile' => substr($branch->mobile, -10),
            'status' => 'active', 'subscription_start' => '2020-01-01', 'subscription_end' => '2035-01-01',
        ])->assertRedirect();

        $this->assertSame($end, $branch->fresh()->subscription_end->toDateString());
    }
}
