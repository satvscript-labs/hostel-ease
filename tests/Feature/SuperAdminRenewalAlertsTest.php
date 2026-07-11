<?php

namespace Tests\Feature;

use App\Models\Hostel;
use App\Models\Notification;
use App\Models\SubscriptionAccount;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminRenewalAlertsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::clear();
    }

    public function test_one_alert_per_account_even_with_multiple_co_terminated_branches(): void
    {
        $owner = User::factory()->create(['role' => 'hostel_admin', 'mobile' => '9555500001', 'name' => 'Multi Branch Owner']);
        $anchor = now()->addDays(5);
        $b1 = Hostel::factory()->create(['mobile' => '9555500001', 'status' => 'active', 'subscription_end' => $anchor]);
        $b2 = Hostel::factory()->create(['mobile' => '9555500001', 'status' => 'active', 'subscription_end' => $anchor]);
        $owner->hostels()->sync([$b1->id, $b2->id]);
        SubscriptionAccount::create(['owner_id' => $owner->id, 'period' => 'yearly', 'status' => 'active', 'current_period_end' => $anchor]);

        app(NotificationService::class)->generateForSuperAdmin();

        $this->assertSame(1, Notification::where('type', 'renewal_due')->count(), 'Two co-terminated branches on one account must produce exactly one Super Admin alert, not two.');
        $this->assertStringContainsString('Multi Branch Owner', Notification::where('type', 'renewal_due')->first()->title);
    }

    public function test_stale_pre_account_model_alerts_are_cleared(): void
    {
        Notification::create(['hostel_id' => null, 'type' => 'renewal_due', 'title' => 'Old', 'data' => ['sig' => 'hostel:999']]);

        app(NotificationService::class)->generateForSuperAdmin();

        // Notification uses SoftDeletes — the row is soft-deleted (like NotificationService::clear()
        // does elsewhere), so assert it's gone from a normal (scoped) query rather than the raw table.
        $this->assertSame(0, Notification::where('title', 'Old')->count());
    }
}
