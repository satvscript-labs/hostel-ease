<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\Floor;
use App\Models\Hostel;
use App\Models\Room;
use App\Models\Student;
use App\Models\User;
use App\Services\BedAssignmentService;
use App\Services\BedGenerator;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BedAssignmentTest extends TestCase
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

        $floor = Floor::create(['hostel_id' => $this->hostel->id, 'name' => 'Ground Floor']);
        $this->room = Room::create([
            'hostel_id' => $this->hostel->id, 'floor_id' => $floor->id,
            'room_number' => '101', 'room_type' => 'non_ac', 'sharing' => 2, 'rent' => 5000,
        ]);
        app(BedGenerator::class)->sync($this->room);
    }

    protected function student(string $name): Student
    {
        return Student::create([
            'hostel_id' => $this->hostel->id, 'name' => $name,
            'mobile' => (string) random_int(9000000000, 9999999999),
            'occupation_type' => 'student', 'status' => 'active',
        ]);
    }

    public function test_assigning_marks_bed_occupied_and_stores_fee(): void
    {
        $service = app(BedAssignmentService::class);
        $bed = $this->room->beds()->first();

        $assignment = $service->assign($this->student('Amit'), $bed, [
            'join_date' => now()->toDateString(),
            'fee_amount' => 30000,
            'fee_frequency' => 'semester',
        ]);

        $this->assertSame('occupied', $bed->fresh()->status);
        $this->assertTrue($assignment->is_active);
        $this->assertEquals(30000, (float) $assignment->fee_amount);
        $this->assertSame('semester', $assignment->fee_frequency);
    }

    public function test_double_allocation_is_prevented(): void
    {
        $service = app(BedAssignmentService::class);
        $bed = $this->room->beds()->first();
        $service->assign($this->student('Amit'), $bed, []);

        $this->expectException(ValidationException::class);
        $service->assign($this->student('Vivek'), $bed, []);
    }

    public function test_release_frees_bed_but_keeps_history(): void
    {
        $service = app(BedAssignmentService::class);
        $bed = $this->room->beds()->first();
        $student = $this->student('Amit');
        $assignment = $service->assign($student, $bed, []);

        $service->release($assignment, now()->toDateString(), markStudentLeft: true);

        $this->assertSame('empty', $bed->fresh()->status);
        $this->assertFalse($assignment->fresh()->is_active);
        $this->assertNotNull($assignment->fresh()->leave_date);
        $this->assertSame('left', $student->fresh()->status);
        // History row remains.
        $this->assertSame(1, $bed->assignments()->count());
    }

    public function test_student_cannot_hold_two_beds(): void
    {
        $service = app(BedAssignmentService::class);
        $beds = $this->room->beds()->get();
        $student = $this->student('Amit');
        $service->assign($student, $beds[0], []);

        $this->expectException(ValidationException::class);
        $service->assign($student, $beds[1], []);
    }
}
