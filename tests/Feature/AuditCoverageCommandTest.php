<?php

namespace Tests\Feature;

use App\Models\Hostel;
use App\Models\Subscription;
use App\Models\SubscriptionAccount;
use App\Models\User;
use App\Services\Billing\AccountBillingService;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The read-only audit behind decision D1. It must (a) find coverage a branch holds
 * beyond what its payments bought, (b) count each payment ONCE even though the
 * same charge lives in both ledgers, and (c) never write anything.
 */
class AuditCoverageCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::clear();
    }

    /** @return array{0: User, 1: Hostel} */
    private function ownerWithBranch(string $mobile = '9000000777'): array
    {
        $owner = User::factory()->create(['role' => 'hostel_admin', 'mobile' => $mobile]);
        $branch = Hostel::factory()->create([
            'name' => 'Audited Branch', 'mobile' => $mobile, 'owner_id' => $owner->id,
            'status' => 'active', 'subscription_end' => null,
        ]);
        $owner->hostels()->sync([$branch->id]);

        return [$owner, $branch];
    }

    public function test_a_clean_ledger_reports_nothing_over_granted(): void
    {
        [, $branch] = $this->ownerWithBranch();

        app(AccountBillingService::class)->recordBranchRenewal($branch, 'yearly', [
            'amount' => 10000, 'payment_status' => 'paid', 'payment_method' => 'cash', 'transaction_number' => 'clean_1',
        ]);

        $this->artisan('hostelease:audit-coverage')
            ->expectsOutputToContain('Every branch holds exactly the coverage its payments bought.')
            ->expectsOutputToContain('0 days')
            ->assertSuccessful();
    }

    public function test_it_counts_one_payment_once_even_though_both_ledgers_hold_it(): void
    {
        // PRE-S1 SHAPE. Until S1 a single charge was written twice — once as a legacy
        // `subscriptions` row and once as the order mirrored from it — and those rows
        // still exist in production. Counting records rather than rolling up per
        // branch would report one ₹10,000 payment as two years of coverage and
        // "find" an overage that never happened. Nothing writes this shape any more,
        // so the fixture has to build it by hand.
        [$owner, $branch] = $this->ownerWithBranch();

        $order = app(AccountBillingService::class)->recordBranchRenewal($branch, 'yearly', [
            'amount' => 10000, 'payment_status' => 'paid', 'payment_method' => 'cash', 'transaction_number' => 'dup_1',
        ]);

        $legacy = Subscription::create([
            'hostel_id' => $branch->id, 'plan' => 'yearly',
            'start_date' => now(), 'end_date' => now()->addYear(),
            'amount' => 10000, 'payment_status' => 'paid', 'payment_method' => 'cash',
        ]);
        // The link that marks the order as a mirror of the legacy row.
        $order->forceFill(['legacy_subscription_id' => $legacy->id])->save();

        $this->assertSame(1, Subscription::count());
        $this->assertDatabaseCount('subscription_orders', 1);

        $this->artisan('hostelease:audit-coverage')
            ->expectsOutputToContain('Every branch holds exactly the coverage its payments bought.')
            ->assertSuccessful();
    }

    public function test_it_reports_coverage_held_beyond_what_was_paid_for(): void
    {
        [, $branch] = $this->ownerWithBranch();

        app(AccountBillingService::class)->recordBranchRenewal($branch, 'yearly', [
            'amount' => 10000, 'payment_status' => 'paid', 'payment_method' => 'cash', 'transaction_number' => 'over_1',
        ]);

        // Reproduce exactly what F1 left behind: a branch covered two years on one
        // year's money. (Written straight to the column — the service can no longer
        // produce this state, which is the point of the fix.)
        $branch->forceFill(['subscription_end' => now()->addYears(2)])->save();

        $this->artisan('hostelease:audit-coverage')
            ->expectsOutputToContain('Audited Branch')
            ->expectsOutputToContain('Over-granted coverage still unexpired')
            ->assertSuccessful();
    }

    public function test_it_flags_a_branch_entitled_with_no_paid_record_and_one_with_no_coverage(): void
    {
        [, $backed] = $this->ownerWithBranch();
        app(AccountBillingService::class)->recordBranchRenewal($backed, 'yearly', [
            'amount' => 10000, 'payment_status' => 'paid', 'payment_method' => 'cash', 'transaction_number' => 'mix_1',
        ]);

        // Entitled, but nothing was ever paid or comped for it.
        Hostel::factory()->create(['name' => 'Freeloader', 'status' => 'active', 'subscription_end' => now()->addMonths(6)]);
        // No coverage at all — unlimited access before S0, correctly blocked after.
        Hostel::factory()->create(['name' => 'Uncovered', 'status' => 'active', 'subscription_end' => null]);

        $this->artisan('hostelease:audit-coverage')
            ->expectsOutputToContain('Freeloader')
            ->expectsOutputToContain('Uncovered')
            ->assertSuccessful();
    }

    public function test_it_writes_nothing(): void
    {
        [, $branch] = $this->ownerWithBranch();
        app(AccountBillingService::class)->recordBranchRenewal($branch, 'yearly', [
            'amount' => 10000, 'payment_status' => 'paid', 'payment_method' => 'cash', 'transaction_number' => 'ro_1',
        ]);
        $branch->forceFill(['subscription_end' => now()->addYears(2)])->save();

        // Snapshot as scalars — comparing Eloquent attributes directly would
        // compare Carbon object identities, which differ on every hydration.
        $snapshot = fn () => [
            'hostels' => Hostel::orderBy('id')->get()->map(fn ($h) => [
                $h->id, (string) $h->subscription_start, (string) $h->subscription_end, $h->status,
            ])->toArray(),
            'accounts' => SubscriptionAccount::orderBy('id')->get()->map(fn ($a) => [
                $a->id, (string) $a->current_period_start, (string) $a->current_period_end, $a->status->value,
            ])->toArray(),
            'orders' => \App\Models\SubscriptionOrder::count(),
            'subs' => Subscription::count(),
        ];

        $before = $snapshot();

        $this->artisan('hostelease:audit-coverage')->assertSuccessful();

        $this->assertSame($before, $snapshot(), 'The audit modified data — it must be read-only.');
    }

    // -----------------------------------------------------------------
    // --fix (decision D1)
    // -----------------------------------------------------------------

    public function test_fix_brings_the_branch_back_to_what_was_paid_and_credits_the_excess(): void
    {
        [$owner, $branch] = $this->ownerWithBranch();
        app(AccountBillingService::class)->recordBranchRenewal($branch, 'yearly', [
            'amount' => 10000, 'payment_status' => 'paid', 'payment_method' => 'cash', 'transaction_number' => 'fix_1',
        ]);

        $paidUpTo = $branch->fresh()->subscription_end->toDateString();

        // The F1 state: two years held on one year's money, and the account anchor
        // that was derived from it.
        $branch->forceFill(['subscription_end' => now()->addYears(2)])->save();
        $account = SubscriptionAccount::where('owner_id', $owner->id)->firstOrFail();
        $account->forceFill(['current_period_end' => now()->addYears(2)])->save();

        $this->artisan('hostelease:audit-coverage', ['--fix' => true, '--force' => true])->assertSuccessful();

        $this->assertSame($paidUpTo, $branch->fresh()->subscription_end->toDateString());
        $this->assertSame(
            $paidUpTo,
            $account->fresh()->current_period_end->toDateString(),
            'The account anchor was not re-derived from the corrected branch.',
        );
        // The year past the renewal date is not lost — it is the next renewal, free (doc 22).
        $this->assertSame(1, $branch->fresh()->free_renewals);
    }

    public function test_fix_repairs_a_stale_cycle_start(): void
    {
        [$owner, $branch] = $this->ownerWithBranch();
        app(AccountBillingService::class)->recordBranchRenewal($branch, 'yearly', [
            'amount' => 10000, 'payment_status' => 'paid', 'payment_method' => 'cash', 'transaction_number' => 'fix_2',
        ]);

        // F15's signature: a start that never advanced, leaving a multi-year window.
        $account = SubscriptionAccount::where('owner_id', $owner->id)->firstOrFail();
        $account->forceFill(['current_period_start' => now()->subYears(3)])->save();

        $this->artisan('hostelease:audit-coverage', ['--fix' => true, '--force' => true])->assertSuccessful();

        $account = $account->fresh();
        $this->assertSame(
            $account->current_period_end->copy()->subYear()->toDateString(),
            $account->current_period_start->toDateString(),
        );
    }

    /** A ₹0 gift line, as an old comp or back-fill left behind (no service makes one past the date any more). */
    protected function giftLine(\App\Models\SubscriptionAccount $account, \App\Models\Hostel $branch, $end, string $kind = 'comp'): void
    {
        $order = \App\Models\SubscriptionOrder::create([
            'account_id' => $account->id, 'period' => 'yearly', 'kind' => $kind, 'quantity' => 1,
            'subtotal' => 0, 'discount_total' => 0, 'amount' => 0, 'payment_status' => 'paid',
            'payment_method' => $kind === 'comp' ? 'comp' : null, 'collection' => 'offline', 'remarks' => 'Test gift',
        ]);
        \App\Models\SubscriptionOrderLine::create([
            'order_id' => $order->id, 'branch_id' => $branch->id, 'amount' => 0,
            'start_date' => now(), 'end_date' => $end,
        ]);
        app(\App\Services\Billing\CoverageMirror::class)->sync($account->fresh());
        app(\App\Services\Billing\AccountBillingService::class)->refreshAccountAnchor($account->fresh());
    }

    public function test_a_backfilled_adjustment_is_not_reported_as_an_overage(): void
    {
        // The S1 migration gives coverage that predates the ledger a ₹0 `adjustment`
        // order carrying its existing dates. Measuring that against "one term" would
        // invent an overage: the adjustment's whole span is entitled by definition,
        // exactly like a comp. Same for the operator's own hand-edits.
        [, $branch] = $this->ownerWithBranch();
        app(AccountBillingService::class)->recordBranchRenewal($branch, 'yearly', [
            'amount' => 10000, 'payment_status' => 'paid', 'payment_method' => 'cash', 'transaction_number' => 'adj_1',
        ]);

        $this->giftLine(\App\Models\SubscriptionAccount::sole(), $branch->fresh(), now()->addYears(3), 'adjustment');

        $this->artisan('hostelease:audit-coverage')
            ->expectsOutputToContain('Every branch holds exactly the coverage its payments bought.')
            ->expectsOutputToContain('0 days')
            ->assertSuccessful();
    }

    public function test_fix_leaves_an_unledgered_trial_alone(): void
    {
        // Check 2's rows are NOT over-granted — they are grants that never reached
        // the ledger (self-signup trials). Nulling their coverage would lock out a
        // legitimately-trialling tenant, so --fix must not touch them.
        $trial = Hostel::factory()->create([
            'name' => 'Self Signup', 'status' => 'active', 'subscription_end' => now()->addDays(14),
        ]);

        $this->artisan('hostelease:audit-coverage', ['--fix' => true, '--force' => true])->assertSuccessful();

        $this->assertNotNull($trial->fresh()->subscription_end);
        $this->assertTrue($trial->fresh()->isActive());
    }

    public function test_fix_without_force_can_be_declined(): void
    {
        [, $branch] = $this->ownerWithBranch();
        app(AccountBillingService::class)->recordBranchRenewal($branch, 'yearly', [
            'amount' => 10000, 'payment_status' => 'paid', 'payment_method' => 'cash', 'transaction_number' => 'fix_3',
        ]);
        $branch->forceFill(['subscription_end' => now()->addYears(2)])->save();

        $this->artisan('hostelease:audit-coverage', ['--fix' => true])
            ->expectsConfirmation('Apply these corrections?', 'no')
            ->expectsOutputToContain('Nothing was changed')
            ->assertSuccessful();

        $this->assertSame(now()->addYears(2)->toDateString(), $branch->fresh()->subscription_end->toDateString());
    }

    public function test_fix_writes_an_audit_entry_per_correction(): void
    {
        [, $branch] = $this->ownerWithBranch();
        app(AccountBillingService::class)->recordBranchRenewal($branch, 'yearly', [
            'amount' => 10000, 'payment_status' => 'paid', 'payment_method' => 'cash', 'transaction_number' => 'fix_4',
        ]);
        $branch->forceFill(['subscription_end' => now()->addYears(2)])->save();

        $this->artisan('hostelease:audit-coverage', ['--fix' => true, '--force' => true])->assertSuccessful();

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'subscription.update',
            'hostel_id' => $branch->id,
            'subject_type' => $branch->getMorphClass(),
            'subject_id' => $branch->id,
        ]);
    }

    public function test_fix_on_a_clean_ledger_changes_nothing(): void
    {
        [, $branch] = $this->ownerWithBranch();
        app(AccountBillingService::class)->recordBranchRenewal($branch, 'yearly', [
            'amount' => 10000, 'payment_status' => 'paid', 'payment_method' => 'cash', 'transaction_number' => 'fix_5',
        ]);

        $end = $branch->fresh()->subscription_end->toDateString();

        $this->artisan('hostelease:audit-coverage', ['--fix' => true, '--force' => true])
            ->expectsOutputToContain('Nothing to fix')
            ->assertSuccessful();

        $this->assertSame($end, $branch->fresh()->subscription_end->toDateString());
    }

    public function test_the_csv_option_writes_the_findings(): void
    {
        [, $branch] = $this->ownerWithBranch();
        app(AccountBillingService::class)->recordBranchRenewal($branch, 'yearly', [
            'amount' => 10000, 'payment_status' => 'paid', 'payment_method' => 'cash', 'transaction_number' => 'csv_1',
        ]);
        $branch->forceFill(['subscription_end' => now()->addYears(2)])->save();

        $path = storage_path('app/audit-coverage-test.csv');
        @unlink($path);

        $this->artisan('hostelease:audit-coverage', ['--csv' => $path])->assertSuccessful();

        $this->assertFileExists($path);
        $csv = file_get_contents($path);
        $this->assertStringContainsString('check', $csv);
        $this->assertStringContainsString('over_granted', $csv);
        $this->assertStringContainsString('Audited Branch', $csv);

        @unlink($path);
    }
}
