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

    // ── Public-ID hardening (U3): opaque ULID route key ───────────────────

    public function test_visitor_and_complaint_actions_use_opaque_ids(): void
    {
        $visitor = Visitor::create(['hostel_id' => $this->hostel->id, 'name' => 'Guest',
            'purpose' => 'Meeting', 'check_in' => now()]);
        $complaint = Complaint::create(['hostel_id' => $this->hostel->id, 'title' => 'No water',
            'category' => 'plumbing', 'priority' => 'high', 'status' => 'open']);

        $this->assertSame(26, strlen($visitor->public_id));
        $this->assertSame(26, strlen($complaint->public_id));

        $this->assertStringContainsString($visitor->public_id, route('admin.visitors.checkout', $visitor));
        $this->assertStringContainsString($complaint->public_id, route('admin.complaints.update', $complaint));

        // Guessing the sequential integer no longer reaches either record.
        $this->actingAs($this->admin)
            ->patch('/admin/visitors/'.$visitor->id.'/checkout')->assertNotFound();
        $this->actingAs($this->admin)
            ->patch('/admin/complaints/'.$complaint->id, ['status' => 'resolved'])->assertNotFound();

        $this->assertNull($visitor->fresh()->check_out);
        $this->assertSame('open', $complaint->fresh()->status);
    }
}
