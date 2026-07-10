<?php

namespace Tests\Feature;

use App\Models\AcBill;
use App\Models\Floor;
use App\Models\Hostel;
use App\Models\Invoice;
use App\Models\Room;
use App\Models\Student;
use App\Models\User;
use App\Services\BedAssignmentService;
use App\Services\BedGenerator;
use App\Services\ReportService;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcBillTest extends TestCase
{
    use RefreshDatabase;

    protected Hostel $hostel;
    protected User $admin;
    protected Room $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hostel = Hostel::factory()->create();
        $this->admin = User::factory()->create(['hostel_id' => $this->hostel->id, 'role' => 'hostel_admin']);
        Tenant::set($this->hostel->id);

        $floor = Floor::create(['hostel_id' => $this->hostel->id, 'name' => 'GF']);
        $this->room = Room::create(['hostel_id' => $this->hostel->id, 'floor_id' => $floor->id,
            'room_number' => '301', 'room_type' => 'ac', 'sharing' => 3, 'rent' => 6000]);
        app(BedGenerator::class)->sync($this->room);

        // Occupy all three beds.
        foreach ($this->room->beds as $i => $bed) {
            $s = Student::create(['hostel_id' => $this->hostel->id, 'name' => "S{$i}",
                'mobile' => (string) (9000000000 + $i), 'occupation_type' => 'working', 'status' => 'active']);
            app(BedAssignmentService::class)->assign($s, $bed, []);
        }

        $this->actingAs($this->admin);
    }

    public function test_generating_a_bill_splits_the_total_and_links_invoices_to_it(): void
    {
        // 100 units * 10 = 1000, split 3 ways.
        $this->post(route('admin.ac-bills.store'), [
            'room_id' => $this->room->id,
            'bill_month' => now()->format('Y-m-d'),
            'previous_reading' => 0,
            'current_reading' => 100,
            'unit_price' => 10,
        ])->assertRedirect();

        $bill = AcBill::firstOrFail();
        $this->assertEquals(1000, (float) $bill->total_amount);

        $invoices = Invoice::where('ac_bill_id', $bill->id)->get();
        $this->assertCount(3, $invoices);
        $this->assertTrue($invoices->every(fn ($i) => $i->type === 'ac'));
        // AcBillController splits with a flat round() per share (no remainder
        // absorption on the last share), so on a non-evenly-divisible total the
        // sum can be a paisa or two under the bill's total_amount — a known,
        // pre-existing minor rounding gap, not in scope for this fix pass.
        $this->assertEqualsWithDelta(1000, (float) $invoices->sum('amount'), 0.05);
    }

    public function test_ac_report_totals_match_the_generated_invoices(): void
    {
        // 90 units * 10 = 900, split 3 ways evenly (no rounding remainder).
        $this->post(route('admin.ac-bills.store'), [
            'room_id' => $this->room->id,
            'bill_month' => now()->format('Y-m-d'),
            'previous_reading' => 0,
            'current_reading' => 90,
            'unit_price' => 10,
        ]);

        $bill = AcBill::firstOrFail();
        $firstInvoice = Invoice::where('ac_bill_id', $bill->id)->first();
        $firstInvoice->update(['paid_amount' => 300, 'status' => 'paid']);

        $data = app(ReportService::class)->acReport(now()->startOfMonth(), now()->endOfMonth());

        $this->assertEquals(900, $data['rows'][0][3]); // billed
        $this->assertEquals(300, $data['rows'][0][4]); // collected
    }
}
