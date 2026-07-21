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

    /**
     * Rewritten in W6.4. This test asserted the fee plan landed on the
     * ASSIGNMENT — a design the 2026_07_06 recreate_student_fees_flow
     * migration deliberately retired, moving the plan onto `students` and
     * dropping those columns. The test outlived the design and has been
     * failing ever since.
     *
     * The current contract: a move re-prices the STUDENT (one current plan,
     * which billing reads) and records the room's rent of the day on the
     * assignment as history.
     */
    public function test_assigning_marks_bed_occupied_and_re_prices_the_student(): void
    {
        $service = app(BedAssignmentService::class);
        $bed = $this->room->beds()->first();
        $student = $this->student('Amit');

        $assignment = $service->assign($student, $bed, [
            'join_date' => now()->toDateString(),
            'fee_amount' => 30000,
            'fee_frequency' => 'semester',
        ]);

        $this->assertSame('occupied', $bed->fresh()->status);
        $this->assertTrue($assignment->is_active);

        // The plan lives on the student…
        $student->refresh();
        $this->assertEquals(30000, (float) $student->fee_amount);
        $this->assertSame('semester', $student->fee_frequency);

        // …and the stay remembers what the room cost at the time.
        $this->assertEquals(5000, (float) $assignment->monthly_rent);
    }

    /** A move with no plan data must not wipe the student's existing one. */
    public function test_assigning_without_plan_data_leaves_the_students_plan_alone(): void
    {
        $student = $this->student('Amit');
        $student->update(['fee_amount' => 12000, 'fee_frequency' => 'monthly']);

        app(BedAssignmentService::class)->assign($student, $this->room->beds()->first(), []);

        $student->refresh();
        $this->assertEquals(12000, (float) $student->fee_amount);
        $this->assertSame('monthly', $student->fee_frequency);
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

    // ── W6.4: every move is a re-pricing ──────────────────────────────────

    protected function acRoom(float $rent = 8000): Room
    {
        $room = Room::create([
            'hostel_id' => $this->hostel->id, 'floor_id' => $this->room->floor_id,
            'room_number' => '301', 'room_type' => 'ac', 'sharing' => 2, 'rent' => $rent,
        ]);
        app(BedGenerator::class)->sync($room);

        return $room;
    }

    /**
     * The bug the owner caught: a student moved from a ₹5,000 Non-AC room to
     * an ₹8,000 AC room kept being billed ₹5,000 forever, because a transfer
     * never asked about the plan.
     */
    public function test_transfer_re_prices_the_student_for_the_new_room(): void
    {
        $this->actingAs($this->admin);
        $student = $this->student('Amit');
        $student->update(['fee_amount' => 5000, 'fee_frequency' => 'monthly']);
        $assignment = app(BedAssignmentService::class)->assign($student, $this->room->beds()->first(), []);

        $target = $this->acRoom();

        $this->patch(route('admin.property.transfer', $assignment), [
            'bed_id' => $target->beds()->first()->id,
            'join_date' => now()->toDateString(),
            'old_meter_reading' => null,      // leaving a Non-AC room
            'meter_reading' => 250,           // entering an AC room — required
            'fee_amount' => 8000,
            'fee_frequency' => 'monthly',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $student->refresh();
        $this->assertEquals(8000, (float) $student->fee_amount);
        $this->assertEquals(8000, (float) $student->activeAssignment->monthly_rent);
        $this->assertEquals(250, (float) $student->activeAssignment->join_meter_reading);
    }

    /** Owner decision: plan-forward-only — a transfer creates no money. */
    public function test_transfer_does_not_touch_existing_invoices_or_credit(): void
    {
        $this->actingAs($this->admin);
        $student = $this->student('Amit');
        $student->update(['fee_amount' => 5000, 'fee_frequency' => 'monthly']);
        $assignment = app(BedAssignmentService::class)->assign($student, $this->room->beds()->first(), []);

        \App\Models\Invoice::create([
            'hostel_id' => $this->hostel->id, 'student_id' => $student->id, 'type' => 'fee',
            'title' => 'Rent for this month', 'amount' => 5000, 'paid_amount' => 0, 'status' => 'pending',
            'billing_cycle_start' => now()->startOfMonth(), 'billing_cycle_end' => now()->endOfMonth(),
        ]);

        $this->patch(route('admin.property.transfer', $assignment), [
            'bed_id' => $this->acRoom()->beds()->first()->id,
            'join_date' => now()->toDateString(),
            'meter_reading' => 100,
            'fee_amount' => 8000,
            'fee_frequency' => 'monthly',
        ])->assertRedirect();

        // The plan moved on; the money did not.
        $this->assertEquals(8000, (float) $student->fresh()->fee_amount);
        $this->assertSame(1, $student->invoices()->count());
        $this->assertEquals(5000, (float) $student->invoices()->first()->amount);
        $this->assertEquals(0, (float) $student->fresh()->credit_balance);
    }

    /** The plan is confirmed on the way in — never assumed. */
    public function test_assign_and_transfer_require_the_plan(): void
    {
        $this->actingAs($this->admin);
        $student = $this->student('Amit');

        $this->post(route('admin.property.assign'), [
            'student_id' => $student->id,
            'bed_id' => $this->room->beds()->first()->id,
        ])->assertSessionHasErrors(['fee_amount', 'fee_frequency']);

        $this->assertSame(0, $student->assignments()->count());
    }

    /** Assigning raises their first bill on the confirmed plan. */
    public function test_assign_raises_the_first_invoice_on_the_confirmed_plan(): void
    {
        $this->actingAs($this->admin);
        $student = $this->student('Amit');

        $this->post(route('admin.property.assign'), [
            'student_id' => $student->id,
            'bed_id' => $this->room->beds()->first()->id,
            'join_date' => now()->toDateString(),
            'fee_amount' => 5000,
            'fee_frequency' => 'monthly',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $invoice = $student->invoices()->firstOrFail();
        $this->assertEquals(5000, (float) $invoice->amount);
        $this->assertStringContainsString('Initial', $invoice->title);
    }

    // ── W10: assign/transfer driven from the student profile ──────────────

    /**
     * The Accommodation buttons now assign inline and pass redirect_to=profile,
     * so the operator lands back on the student — not the Property Board — with
     * every rule still enforced.
     */
    public function test_assign_from_the_profile_returns_to_the_profile(): void
    {
        $this->actingAs($this->admin);
        $student = $this->student('Amit');

        $this->post(route('admin.property.assign'), [
            'student_id' => $student->id,
            'bed_id' => $this->room->beds()->first()->id,
            'join_date' => now()->toDateString(),
            'fee_amount' => 5000,
            'fee_frequency' => 'monthly',
            'redirect_to' => 'profile',
        ])->assertRedirect(route('admin.students.show', $student))->assertSessionHasNoErrors();

        $this->assertSame('occupied', $this->room->beds()->first()->fresh()->status);
    }

    public function test_transfer_from_the_profile_returns_to_the_profile(): void
    {
        $this->actingAs($this->admin);
        $student = $this->student('Amit');
        $assignment = app(BedAssignmentService::class)->assign($student, $this->room->beds()->first(), [
            'join_date' => now()->subMonth()->toDateString(), 'fee_amount' => 5000, 'fee_frequency' => 'monthly',
        ]);
        $target = $this->room->beds()->skip(1)->first();

        $this->patch(route('admin.property.transfer', $assignment), [
            'bed_id' => $target->id,
            'join_date' => now()->toDateString(),
            'fee_amount' => 6000,
            'fee_frequency' => 'monthly',
            'redirect_to' => 'profile',
        ])->assertRedirect(route('admin.students.show', $student))->assertSessionHasNoErrors();

        $this->assertSame('occupied', $target->fresh()->status);
    }

    /** The profile is the same endpoint, so the AC-meter rule still bites. */
    public function test_profile_assign_still_requires_the_ac_meter(): void
    {
        $this->actingAs($this->admin);
        $student = $this->student('Amit');
        $acBed = $this->acRoom()->beds()->first();

        $this->post(route('admin.property.assign'), [
            'student_id' => $student->id,
            'bed_id' => $acBed->id,
            'join_date' => now()->toDateString(),
            'fee_amount' => 8000,
            'fee_frequency' => 'monthly',
            'redirect_to' => 'profile',
            // no meter_reading
        ])->assertSessionHasErrors('meter_reading');

        $this->assertSame(0, $student->assignments()->count());
    }

    /** The profile passes vacant beds to the sheets. */
    public function test_the_profile_exposes_vacant_beds_to_the_sheets(): void
    {
        $this->actingAs($this->admin);
        $student = $this->student('Amit');

        $beds = $this->get(route('admin.students.show', $student))->assertOk()->viewData('vacantBeds');

        $this->assertNotEmpty($beds);
        $this->assertArrayHasKey('is_ac', $beds->first());
        $this->assertArrayHasKey('room', $beds->first());
    }

    // ── Public-ID hardening (U3): opaque ULID route key ───────────────────

    /**
     * Bed history lists everyone who has ever occupied that bed, so a guessable
     * id walked other rooms' occupancy. The URL is opaque now.
     */
    public function test_the_bed_history_url_uses_the_public_id_and_the_integer_is_rejected(): void
    {
        $this->actingAs($this->admin);
        $bed = $this->room->beds()->firstOrFail();

        $this->assertSame(26, strlen($bed->public_id));

        $url = route('admin.beds.history', $bed);
        $this->assertStringEndsWith('/'.$bed->public_id.'/history', $url);

        $this->get($url)->assertOk();
        $this->get('/admin/beds/'.$bed->id.'/history')->assertNotFound();
    }

    /**
     * Release/transfer are driven by URLs the property board builds in JS from
     * the assignment id — so the assignment needed an opaque key of its own.
     */
    public function test_releasing_an_assignment_works_through_its_opaque_id(): void
    {
        $this->actingAs($this->admin);
        $student = $this->student('Release Me');
        $bed = $this->room->beds()->firstOrFail();

        app(BedAssignmentService::class)->assign($student, $bed, [
            'join_date' => now()->toDateString(), 'fee_amount' => 5000, 'fee_frequency' => 'monthly',
        ]);
        $assignment = $student->activeAssignment()->firstOrFail();

        $url = route('admin.property.release', $assignment);
        $this->assertStringContainsString($assignment->public_id, $url);

        $this->patch($url, ['leave_date' => now()->toDateString()])->assertRedirect();
        $this->assertSame(0, $student->assignments()->where('is_active', true)->count());
    }
}
