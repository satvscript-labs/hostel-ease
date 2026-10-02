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
        // recordBranchRenewal writes a legacy subscriptions row AND mirrors it into
        // subscription_orders. Counting records rather than rolling up per branch
        // would report this single ₹10,000 payment twice.
        [, $branch] = $this->ownerWithBranch();

        app(AccountBillingService::class)->recordBranchRenewal($branch, 'yearly', [
            'amount' => 10000, 'payment_status' => 'paid', 'payment_method' => 'cash', 'transaction_number' => 'dup_1',
        ]);

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
