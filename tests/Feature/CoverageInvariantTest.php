<?php

namespace Tests\Feature;

use App\Enums\OrderKind;
use App\Models\Hostel;
use App\Models\Subscription;
use App\Models\SubscriptionAccount;
use App\Models\SubscriptionOrder;
use App\Models\SubscriptionOrderLine;
use App\Models\User;
use App\Services\Billing\AccountBillingService;
use App\Services\HostelService;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The invariant that stops finding F1 coming back: COVERAGE IS WRITTEN ONLY BY A
 * CHARGE, and a charge grants exactly one term.
 *
 * The bug these tests lock out: HostelService stamped subscription_start/end at
 * Hostel::create, then recordBranchRenewal() re-quoted against a branch that was
 * already "active until next year" and — correctly honouring BR-9, never lose paid
 * time — stacked a SECOND term on top. A paid yearly provision granted two years
 * for ₹10,000; a trial ran 28 days. Every assertion here is an exact date, because
 * the old behaviour passed every "is it active?" style assertion in the suite.
 */
class CoverageInvariantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::clear();   // the Super Admin provisions unbound
    }

    private function provision(array $overrides = []): array
    {
        return app(HostelService::class)->provision(array_merge([
            'name' => 'Audit Hostel',
            'owner_name' => 'Owner',
            'mobile' => '+919000000111',
            'plan' => 'yearly',
            'status' => 'active',
            'amount' => 10000,
            'payment_status' => 'paid',
        ], $overrides));
    }

    // -----------------------------------------------------------------
    // F1 — the three reproduced cases, by exact date
    // -----------------------------------------------------------------

    public function test_paid_yearly_provision_grants_exactly_one_year(): void
    {
        $hostel = $this->provision()['hostel']->fresh();

        $this->assertSame(
            now()->addYear()->toDateString(),
            $hostel->subscription_end->toDateString(),
            'A paid yearly provision granted two years — the F1 double-stamp is back.',
        );

        // And the LEDGER agrees, because since S1 the mirror is derived from it.
        $line = SubscriptionOrderLine::where('branch_id', $hostel->id)->firstOrFail();
        $this->assertSame(now()->addYear()->toDateString(), $line->end_date->toDateString());
        $this->assertSame(OrderKind::Renewal, $line->order->kind);

        // Nothing is written to the retired legacy table any more (D8).
        $this->assertSame(0, Subscription::count(), 'The legacy subscriptions table was written again.');
    }

    public function test_paid_monthly_provision_grants_exactly_one_month(): void
    {
        $hostel = $this->provision(['plan' => 'monthly', 'amount' => 1000])['hostel']->fresh();

        $this->assertSame(now()->addMonth()->toDateString(), $hostel->subscription_end->toDateString());
    }

    public function test_trial_provision_grants_exactly_the_configured_trial_length(): void
    {
        config(['hostelease.trial_days' => 14]);

        $hostel = $this->provision(['plan' => 'trial', 'amount' => 0])['hostel']->fresh();

        $this->assertSame(
            now()->addDays(14)->toDateString(),
            $hostel->subscription_end->toDateString(),
            'The trial ran 28 days — branch creation is stamping coverage again.',
        );
    }

    public function test_accepting_a_pending_provision_grants_one_year_not_two(): void
    {
        $hostel = $this->provision(['payment_status' => 'pending'])['hostel']->fresh();

        // Pending grants nothing at all now.
        $this->assertNull($hostel->subscription_end);
        $this->assertFalse($hostel->isActive());

        // ...but it IS on the ledger as a proforma (F3), which is how the operator
        // sees money they are owed.
        $order = SubscriptionOrder::firstOrFail();
        $this->assertSame('pending', $order->payment_status->value);

        // The Super Admin accepts it — the S1 replacement for the legacy page's
        // one-click accept, and what makes the receivables worklist actionable.
        app(AccountBillingService::class)->acceptOrder($order, ['payment_method' => 'cash']);

        $this->assertSame(
            now()->addYear()->toDateString(),
            $hostel->fresh()->subscription_end->toDateString(),
            'Accepting the payment granted the wrong amount of coverage.',
        );

        // The same order flipped to paid — not a second one created beside it.
        $this->assertSame(1, SubscriptionOrder::count());
        $this->assertSame('paid', $order->fresh()->payment_status->value);
    }

    public function test_accepting_is_idempotent_and_voiding_withdraws_the_coverage(): void
    {
        $hostel = $this->provision(['payment_status' => 'pending'])['hostel'];
        $order = SubscriptionOrder::firstOrFail();
        $billing = app(AccountBillingService::class);

        $billing->acceptOrder($order, ['payment_method' => 'cash']);
        $billing->acceptOrder($order->fresh(), ['payment_method' => 'upi']);

        $this->assertSame(1, SubscriptionOrder::count());
        $this->assertSame('cash', $order->fresh()->payment_method->value, 'A second accept overwrote the recorded method.');

        // Voiding is the audited way to withdraw coverage — and the only routine
        // path allowed to shorten a mirror.
        $billing->voidOrder($order->fresh(), 'recorded against the wrong customer');

        $this->assertNull($hostel->fresh()->subscription_end);
        $this->assertFalse($hostel->fresh()->isActive());
        $this->assertSame('voided', $order->fresh()->payment_status->value);
    }

    public function test_a_routine_sync_never_shortens_coverage_it_cannot_explain(): void
    {
        // The safety rule in CoverageMirror: a branch holding coverage the ledger
        // cannot account for (a pre-S1 grant, or any future path that slips past the
        // invariant) must NOT be cut off by a maintenance pass. Drift is logged; only
        // an explicit void or --fix may shorten.
        $hostel = $this->provision()['hostel'];
        $hostel->forceFill(['subscription_end' => now()->addYears(5)])->save();

        $billing = app(AccountBillingService::class);
        $account = SubscriptionAccount::firstOrFail();
        $billing->refreshAccountAnchor($account);
        app(\App\Services\Billing\CoverageMirror::class)->sync($account);

        $this->assertSame(
            now()->addYears(5)->toDateString(),
            $hostel->fresh()->subscription_end->toDateString(),
            'A routine sync shortened coverage — a paying tenant could be locked out by a cron job.',
        );
    }

    // -----------------------------------------------------------------
    // The invariant itself
    // -----------------------------------------------------------------

    public function test_a_newly_created_branch_has_no_coverage_until_it_is_billed(): void
    {
        $owner = User::factory()->create(['role' => 'hostel_admin', 'mobile' => '+919000000222']);

        foreach (['yearly', 'monthly', 'trial'] as $plan) {
            $branch = app(HostelService::class)->createBranchForOwner($owner, [
                'name' => "Branch {$plan}", 'plan' => $plan,
            ]);

            $this->assertNull(
                $branch->subscription_start,
                "createBranchForOwner stamped a start date for a {$plan} plan — only the billing service may write coverage.",
            );
            $this->assertNull($branch->subscription_end, "createBranchForOwner stamped coverage for a {$plan} plan.");
        }
    }

    public function test_a_branch_with_no_coverage_end_is_not_entitled(): void
    {
        // Before S0 this returned true — "no end date" read as unlimited access, so
        // a branch created and never billed worked forever for free.
        $branch = Hostel::factory()->create(['status' => 'active', 'subscription_end' => null]);

        $this->assertFalse($branch->isActive());
    }

    public function test_no_branch_holds_coverage_beyond_its_account_anchor(): void
    {
        $mobile = '9000000333';
        $owner = User::factory()->create(['role' => 'hostel_admin', 'mobile' => $mobile]);
        $branches = collect(['a', 'b', 'c'])->map(fn ($n) => Hostel::factory()->create([
            'name' => 'Branch '.$n, 'mobile' => $mobile, 'status' => 'active', 'subscription_end' => now()->addDays(30),
        ]));
        $owner->hostels()->sync($branches->pluck('id')->all());

        $billing = app(AccountBillingService::class);
        $account = $billing->accountFor($owner);
        $billing->refreshAccountAnchor($account);
        $billing->renewAccount($account->fresh(), 'yearly', ['payment_status' => 'paid', 'payment_method' => 'cash']);

        $account = $account->fresh();
        foreach ($branches as $branch) {
            $this->assertTrue(
                $branch->fresh()->subscription_end->lessThanOrEqualTo($account->current_period_end),
                "{$branch->name} is covered past the account anchor — the mirror and the clock have diverged (F11).",
            );
        }
    }

    public function test_renewal_advances_the_cycle_start_with_the_cycle_end(): void
    {
        // F15: current_period_start was written `?? now()`, i.e. once and never
        // again, so after three renewals an account read a three-year "current
        // period" — unusable for reporting and as a proration denominator.
        $mobile = '9000000444';
        $owner = User::factory()->create(['role' => 'hostel_admin', 'mobile' => $mobile]);
        $branch = Hostel::factory()->create(['mobile' => $mobile, 'owner_id' => $owner->id, 'status' => 'active', 'subscription_end' => null]);
        $owner->hostels()->sync([$branch->id]);

        $billing = app(AccountBillingService::class);
        // Coverage has to come from the ledger now, so the fixture grants it the way
        // the product does rather than writing the column.
        $billing->recordBranchRenewal($branch, 'trial', ['payment_status' => 'paid']);
        $account = $billing->accountFor($owner);
        $billing->refreshAccountAnchor($account);

        $trialAnchor = $account->fresh()->current_period_end;
        $billing->renewAccount($account->fresh(), 'yearly', ['payment_status' => 'paid', 'payment_method' => 'cash']);

        // Capture by VALUE before renewing again — renewAccount() mutates the model
        // instance it is handed, so holding a reference would read the new anchor.
        $firstCycleEnd = $account->fresh()->current_period_end->toDateString();
        $this->assertSame($trialAnchor->copy()->addYear()->toDateString(), $firstCycleEnd);

        // Renewing again: the new cycle must START where the previous one ended.
        $billing->renewAccount($account->fresh(), 'yearly', ['payment_status' => 'paid', 'payment_method' => 'cash']);
        $afterSecond = $account->fresh();

        $this->assertSame(
            $firstCycleEnd,
            $afterSecond->current_period_start->toDateString(),
            'The cycle start did not advance with the anchor (F15).',
        );
        $this->assertSame(
            $trialAnchor->copy()->addYears(2)->toDateString(),
            $afterSecond->current_period_end->toDateString(),
        );

        // And the window is one term long, not two.
        $span = $afterSecond->current_period_start->diffInDays($afterSecond->current_period_end);
        $this->assertLessThanOrEqual(366, $span);
    }

    public function test_a_comped_offline_method_no_longer_touches_the_legacy_enum(): void
    {
        // F6 is structurally gone in S1: nothing writes the legacy ENUM column, so
        // 'comp' (which the ENUM never allowed) can no longer 500 a request on MySQL.
        // It is recorded on the order, a plain string column.
        $mobile = '9000000555';
        $owner = User::factory()->create(['role' => 'hostel_admin', 'mobile' => $mobile]);
        $branch = Hostel::factory()->create(['mobile' => $mobile, 'owner_id' => $owner->id, 'status' => 'active', 'subscription_end' => null]);
        $owner->hostels()->sync([$branch->id]);

        app(AccountBillingService::class)->recordBranchRenewal($branch, 'yearly', [
            'amount' => 0, 'payment_status' => 'paid', 'payment_method' => 'comp', 'remarks' => 'favour',
        ]);

        $this->assertSame(0, Subscription::count(), 'The legacy table was written — the ENUM landmine is back.');
        $this->assertSame('comp', SubscriptionOrder::firstOrFail()->payment_method->value);
        $this->assertSame(1, SubscriptionAccount::count());
    }
}
