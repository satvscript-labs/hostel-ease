<?php

namespace Tests\Feature;

use App\Models\AcBill;
use App\Models\BedAssignment;
use App\Models\Floor;
use App\Models\Hostel;
use App\Models\Room;
use App\Models\Student;
use App\Models\User;
use App\Services\AcMeterService;
use App\Services\BedAssignmentService;
use App\Services\BedGenerator;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Meter-floor (2026-07-18): an electricity meter only counts up, so a reading
 * typed anywhere — assign/release/transfer, bill generation, bill edit — can't
 * be below the room's last recorded reading. The floor is DERIVED (bills +
 * move readings, soft-deleted bills included); a genuine meter reset/
 * replacement passes with the explicit `meter_reset` override and is logged.
 */
class AcMeterValidationTest extends TestCase
{
    use RefreshDatabase;

    protected Hostel $hostel;
    protected User $admin;
    protected Floor $floor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hostel = Hostel::factory()->create();
        $this->admin = User::factory()->create(['hostel_id' => $this->hostel->id, 'role' => 'hostel_admin']);
        Tenant::set($this->hostel->id);
        $this->floor = Floor::create(['hostel_id' => $this->hostel->id, 'name' => 'GF']);
        $this->actingAs($this->admin);
    }

    protected function acRoom(string $number = '401'): Room
    {
        $room = Room::create(['hostel_id' => $this->hostel->id, 'floor_id' => $this->floor->id,
            'room_number' => $number, 'room_type' => 'ac', 'sharing' => 2, 'rent' => 6000]);
        app(BedGenerator::class)->sync($room);

        return $room;
    }

    protected function student(string $name): Student
    {
        return Student::create(['hostel_id' => $this->hostel->id, 'name' => $name,
            'mobile' => (string) random_int(9000000000, 9999999999),
            'occupation_type' => 'working', 'status' => 'active']);
    }

    protected function bill(Room $room, string $month, float $prev, float $curr): AcBill
    {
        return AcBill::create([
            'hostel_id' => $this->hostel->id, 'room_id' => $room->id,
            'bill_month' => $month, 'previous_reading' => $prev, 'current_reading' => $curr,
            'total_units' => $curr - $prev, 'unit_price' => 10, 'total_amount' => ($curr - $prev) * 10,
        ]);
    }

    // ── The derived floor ────────────────────────────────────────────────

    public function test_last_reading_is_the_max_across_bills_and_move_readings(): void
    {
        $svc = app(AcMeterService::class);
        $room = $this->acRoom();

        $this->assertNull($svc->lastReading($room), 'a room with no readings has no floor');

        $this->bill($room, now()->subMonths(2)->startOfMonth()->toDateString(), 100, 300);
        $this->assertSame(300.0, $svc->lastReading($room), 'bill end reading floors');

        // A later move reading overtakes the bill.
        app(BedAssignmentService::class)->assign($this->student('A'), $room->beds[0], [
            'meter_reading' => 450,
        ]);
        $this->assertSame(450.0, $svc->lastReading($room), 'move reading overtakes the bill');
    }

    public function test_a_soft_deleted_bills_reading_still_floors(): void
    {
        $room = $this->acRoom();
        $bill = $this->bill($room, now()->subMonths(2)->startOfMonth()->toDateString(), 0, 800);
        $bill->delete(); // reversed — but the meter physically reached 800

        $this->assertSame(800.0, app(AcMeterService::class)->lastReading($room));
    }

    // ── Moves: assign / release floors ───────────────────────────────────

    public function test_assigning_with_a_reading_below_the_floor_is_refused(): void
    {
        $room = $this->acRoom();
        $this->bill($room, now()->subMonths(2)->startOfMonth()->toDateString(), 100, 1200);
        $student = $this->student('Typo');

        $this->post(route('admin.property.assign'), [
            'student_id' => $student->id, 'bed_id' => $room->beds[0]->id,
            'join_date' => now()->toDateString(), 'meter_reading' => 150,
            'fee_frequency' => 'monthly', 'fee_amount' => 5000,
        ])->assertSessionHasErrors('meter_reading');

        $this->assertSame(0, $student->assignments()->count());
    }

    public function test_the_meter_reset_override_accepts_a_lower_reading_and_logs_it(): void
    {
        $room = $this->acRoom();
        $this->bill($room, now()->subMonths(2)->startOfMonth()->toDateString(), 100, 1200);
        $student = $this->student('Reset');

        $this->post(route('admin.property.assign'), [
            'student_id' => $student->id, 'bed_id' => $room->beds[0]->id,
            'join_date' => now()->toDateString(), 'meter_reading' => 150,
            'meter_reset' => 1,
            'fee_frequency' => 'monthly', 'fee_amount' => 5000,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(1, $student->assignments()->count());
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'ac_meter.reset', 'user_id' => $this->admin->id,
        ]);
    }

    public function test_releasing_below_the_students_own_join_reading_is_refused(): void
    {
        $room = $this->acRoom();
        $student = $this->student('Leaver');
        $assignment = app(BedAssignmentService::class)->assign($student, $room->beds[0], [
            'meter_reading' => 500,
        ]);

        // 400 < their own join reading 500 — negative consumption is impossible.
        $this->patch(route('admin.property.release', $assignment), [
            'meter_reading' => 400,
        ])->assertSessionHasErrors('meter_reading');

        $this->assertTrue($assignment->fresh()->is_active);

        // A sane reading releases fine.
        $this->patch(route('admin.property.release', $assignment), [
            'meter_reading' => 560,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertFalse($assignment->fresh()->is_active);
    }

    public function test_a_non_ac_room_never_demands_or_floors_a_reading(): void
    {
        $room = Room::create(['hostel_id' => $this->hostel->id, 'floor_id' => $this->floor->id,
            'room_number' => '402', 'room_type' => 'non_ac', 'sharing' => 2, 'rent' => 4000]);
        app(BedGenerator::class)->sync($room);
        $student = $this->student('NonAc');

        $this->post(route('admin.property.assign'), [
            'student_id' => $student->id, 'bed_id' => $room->beds[0]->id,
            'join_date' => now()->toDateString(),
            'fee_frequency' => 'monthly', 'fee_amount' => 4000,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(1, $student->assignments()->count());
    }

    // ── Bill generation: the start reading floors ────────────────────────

    public function test_a_bill_start_below_the_floor_is_refused_and_the_override_generates(): void
    {
        $room = $this->acRoom();
        // Occupied history so the split has someone to bill.
        $s = $this->student('Occ');
        BedAssignment::create([
            'hostel_id' => $this->hostel->id, 'bed_id' => $room->beds[0]->id, 'student_id' => $s->id,
            'join_date' => now()->subMonths(6)->toDateString(), 'is_active' => true,
        ]);
        $prevMonth = now()->subMonths(2)->startOfMonth();
        $this->bill($room, $prevMonth->toDateString(), 100, 1000);

        $target = $prevMonth->copy()->addMonthNoOverflow()->format('Y-m');

        // Start 900 < the previous bill's end 1000 → friendly refusal, no bill.
        $this->post(route('admin.ac-bills.store'), [
            'room_id' => $room->id, 'unit_price' => 10, 'prev_reading' => 900,
            'months' => [$target], 'readings' => [1100],
        ])->assertRedirect()->assertSessionHas('error');
        $this->assertSame(1, AcBill::where('room_id', $room->id)->count());

        // Same numbers with the reset confirmed → generates and logs.
        $this->post(route('admin.ac-bills.store'), [
            'room_id' => $room->id, 'unit_price' => 10, 'prev_reading' => 900,
            'months' => [$target], 'readings' => [1100], 'meter_reset' => 1,
        ])->assertRedirect()->assertSessionHas('success');
        $this->assertSame(2, AcBill::where('room_id', $room->id)->count());
        $this->assertDatabaseHas('activity_logs', ['action' => 'ac_meter.reset']);
    }

    /**
     * THE math-critical case: a move reading INSIDE the month being billed
     * must never floor that month's start. Room billed till March (end 1000);
     * a student joined mid-April at 1080; billing April starting from 1000 is
     * correct and must pass — the 1080 lives inside the window the bill splits.
     */
    public function test_a_mid_window_move_reading_does_not_floor_the_bill_start(): void
    {
        $room = $this->acRoom();
        $march = now()->subMonths(2)->startOfMonth();
        $april = $march->copy()->addMonthNoOverflow();
        $this->bill($room, $march->toDateString(), 0, 1000);

        // Joined mid-April at meter 1080.
        $s = $this->student('MidApril');
        BedAssignment::create([
            'hostel_id' => $this->hostel->id, 'bed_id' => $room->beds[0]->id, 'student_id' => $s->id,
            'join_date' => $april->copy()->addDays(9)->toDateString(),
            'join_meter_reading' => 1080, 'is_active' => true,
        ]);

        $this->post(route('admin.ac-bills.store'), [
            'room_id' => $room->id, 'unit_price' => 10, 'prev_reading' => 1000,
            'months' => [$april->format('Y-m')], 'readings' => [1150],
        ])->assertRedirect()->assertSessionHas('success');

        $bill = AcBill::where('room_id', $room->id)->orderByDesc('id')->first();
        $this->assertEquals(1000.0, (float) $bill->previous_reading);
        $this->assertEquals(1150.0, (float) $bill->current_reading);
        // The split math is untouched: units = 150 at ₹10 = ₹1,500.
        $this->assertEquals(1500.0, (float) $bill->total_amount);
    }

    public function test_the_preview_reports_the_floor_without_blocking(): void
    {
        $room = $this->acRoom();
        $s = $this->student('Prev');
        BedAssignment::create([
            'hostel_id' => $this->hostel->id, 'bed_id' => $room->beds[0]->id, 'student_id' => $s->id,
            'join_date' => now()->subMonths(6)->toDateString(), 'is_active' => true,
        ]);
        $prevMonth = now()->subMonths(2)->startOfMonth();
        $this->bill($room, $prevMonth->toDateString(), 100, 1000);
        $target = $prevMonth->copy()->addMonthNoOverflow()->format('Y-m');

        $res = $this->postJson(route('admin.ac-bills.preview'), [
            'room_id' => $room->id, 'unit_price' => 10, 'prev_reading' => 900,
            'months' => [$target], 'readings' => [1100],
        ])->assertOk()->json();

        $this->assertEquals(1000, $res['start_floor']);
        $this->assertTrue($res['below_floor']);
        $this->assertNotEmpty($res['months'], 'the preview still renders so the modal can warn inline');
    }

    // ── Bill edit: previous_reading floors against what came before ──────

    public function test_editing_a_bill_below_the_prior_bills_reading_is_refused_and_override_works(): void
    {
        $room = $this->acRoom();
        $s = $this->student('Edit');
        BedAssignment::create([
            'hostel_id' => $this->hostel->id, 'bed_id' => $room->beds[0]->id, 'student_id' => $s->id,
            'join_date' => now()->subMonths(6)->toDateString(), 'is_active' => true,
        ]);
        $m1 = now()->subMonths(3)->startOfMonth();
        $m2 = $m1->copy()->addMonthNoOverflow();
        $this->bill($room, $m1->toDateString(), 0, 500);
        $b2 = $this->bill($room, $m2->toDateString(), 500, 700);

        // 400 < the earlier bill's 500 → refused, bill untouched.
        $this->patch(route('admin.ac-bills.update', $b2), [
            'previous_reading' => 400, 'current_reading' => 700, 'unit_price' => 10,
        ])->assertRedirect()->assertSessionHas('error');
        $this->assertEquals(500.0, (float) $b2->fresh()->previous_reading);

        // With the reset confirmed → applied.
        $this->patch(route('admin.ac-bills.update', $b2), [
            'previous_reading' => 400, 'current_reading' => 700, 'unit_price' => 10, 'meter_reset' => 1,
        ])->assertRedirect()->assertSessionHas('success');
        $this->assertEquals(400.0, (float) $b2->fresh()->previous_reading);
    }
}
