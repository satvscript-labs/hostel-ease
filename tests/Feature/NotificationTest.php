<?php

namespace Tests\Feature;

use App\Models\Hostel;
use App\Models\Notification;
use App\Models\SemesterFee;
use App\Models\Student;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_fees_alert_is_created_then_cleared_when_resolved(): void
    {
        $hostel = Hostel::factory()->create(['subscription_end' => now()->addMonths(6)]);
        Tenant::set($hostel->id);
        $student = Student::create(['hostel_id' => $hostel->id, 'name' => 'A', 'mobile' => '9000000001',
            'occupation_type' => 'student', 'status' => 'active']);
        $fee = SemesterFee::create(['hostel_id' => $hostel->id, 'student_id' => $student->id, 'semester' => 1,
            'total_fee' => 10000, 'paid_amount' => 0, 'balance' => 10000, 'status' => 'pending']);
        Tenant::clear();

        $service = app(NotificationService::class);
        $service->generateForHostel($hostel);

        $this->assertDatabaseHas('notifications', ['hostel_id' => $hostel->id, 'type' => 'fee_pending']);

        // Re-running should not duplicate the unread alert.
        $service->generateForHostel($hostel);
        $this->assertSame(1, Notification::where('type', 'fee_pending')->count());

        // Resolve the balance → alert clears.
        $fee->update(['paid_amount' => 10000, 'balance' => 0, 'status' => 'paid']);
        $service->generateForHostel($hostel);
        $this->assertSame(0, Notification::where('type', 'fee_pending')->whereNull('read_at')->count());
    }

    public function test_super_admin_only_sees_system_feed(): void
    {
        $hostel = Hostel::factory()->create();
        $super = User::factory()->superAdmin()->create();
        $admin = User::factory()->create(['hostel_id' => $hostel->id, 'role' => 'hostel_admin']);

        Notification::create(['hostel_id' => null, 'type' => 'renewal_due', 'title' => 'System', 'data' => ['sig' => 'x']]);
        Notification::create(['hostel_id' => $hostel->id, 'type' => 'fee_pending', 'title' => 'Hostel', 'data' => ['sig' => 'y']]);

        $this->assertSame(1, Notification::forUser($super)->count());
        $this->assertSame('System', Notification::forUser($super)->first()->title);
        $this->assertSame('Hostel', Notification::forUser($admin)->first()->title);
    }
}
