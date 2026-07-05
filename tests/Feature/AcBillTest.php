<?php

namespace Tests\Feature;

use App\Models\AcBill;
use App\Models\Floor;
use App\Models\Hostel;
use App\Models\Room;
use App\Models\Student;
use App\Models\User;
use App\Services\AcBillService;
use App\Services\BedAssignmentService;
use App\Services\BedGenerator;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcBillTest extends TestCase
{
    use RefreshDatabase;

    protected Hostel $hostel;
    protected Room $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hostel = Hostel::factory()->create();
        User::factory()->create(['hostel_id' => $this->hostel->id, 'role' => 'hostel_admin']);
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
    }

    public function test_equal_distribution_splits_total_with_no_rounding_loss(): void
    {
        // 100 units * 10 = 1000, split 3 ways → 333.33 + 333.33 + 333.34
        $bill = app(AcBillService::class)->create($this->room, [
            'bill_month' => now()->format('Y-m'),
            'previous_unit' => 0, 'current_unit' => 100, 'unit_price' => 10,
            'distribution' => 'equal',
        ]);

        $this->assertEquals(1000, (float) $bill->total_amount);
        $this->assertSame(3, $bill->shares()->count());
        $this->assertEquals(1000, round((float) $bill->shares()->sum('amount'), 2));
    }

    public function test_selected_distribution_only_bills_chosen_students(): void
    {
        $ids = \App\Models\BedAssignment::where('hostel_id', $this->hostel->id)
            ->where('is_active', true)->limit(2)->pluck('student_id')->all();

        $bill = app(AcBillService::class)->create($this->room, [
            'bill_month' => now()->format('Y-m'),
            'previous_unit' => 0, 'current_unit' => 50, 'unit_price' => 10,
            'distribution' => 'selected',
        ], $ids);

        $this->assertSame(2, $bill->shares()->count());
        $this->assertEquals(500, round((float) $bill->shares()->sum('amount'), 2));
    }
}
