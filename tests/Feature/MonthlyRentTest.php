<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\Floor;
use App\Models\Hostel;
use App\Models\MonthlyRent;
use App\Models\Room;
use App\Models\Student;
use App\Models\User;
use App\Services\BedAssignmentService;
use App\Services\BedGenerator;
use App\Services\MonthlyRentService;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonthlyRentTest extends TestCase
{
    use RefreshDatabase;

    protected Hostel $hostel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hostel = Hostel::factory()->create();
        User::factory()->create(['hostel_id' => $this->hostel->id, 'role' => 'hostel_admin']);
        Tenant::set($this->hostel->id);
    }

    public function test_generation_creates_rows_only_for_monthly_fee_students(): void
    {
        $floor = Floor::create(['hostel_id' => $this->hostel->id, 'name' => 'GF']);
        $room = Room::create(['hostel_id' => $this->hostel->id, 'floor_id' => $floor->id,
            'room_number' => '101', 'room_type' => 'non_ac', 'sharing' => 2]);
        app(BedGenerator::class)->sync($room);

        $monthly = Student::create(['hostel_id' => $this->hostel->id, 'name' => 'Monthly', 'mobile' => '9000000001',
            'occupation_type' => 'working', 'status' => 'active']);
        $semester = Student::create(['hostel_id' => $this->hostel->id, 'name' => 'Semester', 'mobile' => '9000000002',
            'occupation_type' => 'student', 'status' => 'active']);

        app(BedAssignmentService::class)->assign($monthly, $room->beds()->first(), ['fee_amount' => 6000, 'fee_frequency' => 'monthly']);
        app(BedAssignmentService::class)->assign($semester, $room->beds()->skip(1)->first(), ['fee_amount' => 30000, 'fee_frequency' => 'semester']);

        $created = app(MonthlyRentService::class)->generateForMonth(now());

        $this->assertSame(1, $created);
        $rent = MonthlyRent::firstOrFail();
        $this->assertSame($monthly->id, $rent->student_id);
        $this->assertEquals(6000, (float) $rent->amount);
        $this->assertSame('due', $rent->status);
    }

    public function test_generation_is_idempotent(): void
    {
        $floor = Floor::create(['hostel_id' => $this->hostel->id, 'name' => 'GF']);
        $room = Room::create(['hostel_id' => $this->hostel->id, 'floor_id' => $floor->id,
            'room_number' => '101', 'room_type' => 'non_ac', 'sharing' => 1]);
        app(BedGenerator::class)->sync($room);
        $s = Student::create(['hostel_id' => $this->hostel->id, 'name' => 'Pro', 'mobile' => '9000000001',
            'occupation_type' => 'working', 'status' => 'active']);
        app(BedAssignmentService::class)->assign($s, $room->beds()->first(), ['fee_amount' => 6000, 'fee_frequency' => 'monthly']);

        $service = app(MonthlyRentService::class);
        $this->assertSame(1, $service->generateForMonth(now()));
        $this->assertSame(0, $service->generateForMonth(now()));
        $this->assertSame(1, MonthlyRent::count());
    }
}
