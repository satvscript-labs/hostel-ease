<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\Hostel;
use App\Models\User;
use App\Models\Visitor;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisitorComplaintTest extends TestCase
{
    use RefreshDatabase;

    protected Hostel $hostel;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hostel = Hostel::factory()->create();
        $this->admin = User::factory()->create(['hostel_id' => $this->hostel->id, 'role' => 'hostel_admin']);
        Tenant::set($this->hostel->id);
    }

    public function test_visitor_checkin_and_checkout(): void
    {
        $this->actingAs($this->admin)->post(route('admin.visitors.store'), [
            'name' => 'Guest', 'purpose' => 'Meeting',
        ])->assertRedirect();

        $visitor = Visitor::firstOrFail();
        $this->assertTrue($visitor->isInside());

        $this->actingAs($this->admin)->patch(route('admin.visitors.checkout', $visitor))->assertRedirect();
        $this->assertNotNull($visitor->fresh()->check_out);
    }

    public function test_complaint_resolution_stamps_resolved_at(): void
    {
        $complaint = Complaint::create(['hostel_id' => $this->hostel->id, 'title' => 'No water',
            'category' => 'plumbing', 'priority' => 'high', 'status' => 'open']);

        $this->actingAs($this->admin)->patch(route('admin.complaints.update', $complaint), [
            'status' => 'resolved', 'resolution' => 'Fixed the pipe.',
        ])->assertRedirect();

        $complaint->refresh();
        $this->assertSame('resolved', $complaint->status);
        $this->assertNotNull($complaint->resolved_at);
    }
}
