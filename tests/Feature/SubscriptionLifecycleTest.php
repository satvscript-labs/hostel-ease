<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Models\Hostel;
use App\Models\SubscriptionAccount;
use App\Models\User;
use App\Services\Billing\AccountBillingService;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::clear();
        config()->set('hostelease.grace_days', 3);
    }

    protected static int $mobileSeq = 0;

    protected function ownerWithBranch(\Carbon\Carbon $end, string $status = 'active'): array
    {
        $mobile = '93333'.str_pad((string) (++static::$mobileSeq), 5, '0', STR_PAD_LEFT);
        $owner = User::factory()->create(['role' => 'hostel_admin', 'mobile' => $mobile]);
        $branch = Hostel::factory()->create(['mobile' => $mobile, 'status' => $status, 'subscription_end' => $end]);
        $owner->hostels()->sync([$branch->id]);
        $account = SubscriptionAccount::create(['owner_id' => $owner->id, 'period' => 'yearly', 'status' => 'active', 'current_period_end' => $end]);

        return [$owner, $branch, $account];
    }

    public function test_branch_stays_accessible_during_the_grace_window(): void
    {
        [, $branch] = $this->ownerWithBranch(now()->subDay());

        $this->assertTrue($branch->fresh()->isActive(), 'Branch should still be active 1 day past end within a 3-day grace window.');
    }

    public function test_branch_is_blocked_once_grace_window_passes(): void
    {
        [, $branch] = $this->ownerWithBranch(now()->subDays(4));

        $this->assertFalse($branch->fresh()->isActive(), 'Branch should be blocked once 4 days have passed a 3-day grace window.');
    }

    public function test_zero_grace_days_blocks_immediately_on_expiry(): void
    {
        config()->set('hostelease.grace_days', 0);
        [, $branch] = $this->ownerWithBranch(now()->subDay());

        $this->assertFalse($branch->fresh()->isActive());
    }

    public function test_compute_status_transitions_active_grace_expired(): void
    {
        $service = app(AccountBillingService::class);

        [, , $future] = $this->ownerWithBranch(now()->addMonth());
        $this->assertSame(AccountStatus::Active, $service->computeStatus($future));

        [, , $grace] = $this->ownerWithBranch(now()->subDay());
        $this->assertSame(AccountStatus::Grace, $service->computeStatus($grace));

        [, , $expired] = $this->ownerWithBranch(now()->subDays(10));
        $this->assertSame(AccountStatus::Expired, $service->computeStatus($expired));
    }

    public function test_refresh_account_anchor_advances_status_over_time_without_any_payment(): void
    {
        [, $branch, $account] = $this->ownerWithBranch(now()->subDay());

        // No renewal happened — just the daily "tick" recomputing status from the anchor.
        app(AccountBillingService::class)->refreshAccountAnchor($account);

        $this->assertSame(AccountStatus::Grace, $account->fresh()->status);
    }

    public function test_suspend_cascades_to_branches_and_survives_a_refresh(): void
    {
        [$owner, $branch, $account] = $this->ownerWithBranch(now()->addMonth());
        $service = app(AccountBillingService::class);

        $service->suspend($account, 'payment dispute');

        $this->assertSame(AccountStatus::Suspended, $account->fresh()->status);
        $this->assertSame('suspended', $branch->fresh()->status);
        $this->assertFalse($branch->fresh()->isActive());

        // A refresh (e.g. the daily job, or another renewal touching the account)
        // must NOT silently un-suspend it.
        $service->refreshAccountAnchor($account->fresh());
        $this->assertSame(AccountStatus::Suspended, $account->fresh()->status);
    }

    public function test_renew_account_does_not_clear_a_manual_suspension(): void
    {
        [, , $account] = $this->ownerWithBranch(now()->addMonth());
        $service = app(AccountBillingService::class);
        $service->suspend($account, 'abuse');

        $service->renewAccount($account->fresh(), 'yearly', ['payment_status' => 'paid', 'payment_method' => 'cash']);

        $this->assertSame(AccountStatus::Suspended, $account->fresh()->status, 'A renewal must not silently clear a manual suspension.');
    }

    public function test_reactivate_lifts_suspension_and_recomputes_real_status(): void
    {
        [, $branch, $account] = $this->ownerWithBranch(now()->addMonth());
        $service = app(AccountBillingService::class);
        $service->suspend($account, 'temporary hold');

        $service->reactivate($account->fresh());

        $this->assertSame(AccountStatus::Active, $account->fresh()->status);
        $this->assertSame('active', $branch->fresh()->status);
        $this->assertTrue($branch->fresh()->isActive());
    }
}
