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
use Tests\TestCase;

/**
 * Rent is for a BED. The FIRST invoice must not be raised until a student
 * actually holds one — setting a fee plan on the profile before assignment only
 * SAVES the intended plan. The bill, once raised, is dated from the student's
 * registered join_date (the seat is reserved from then), not the move-in date.
 */
class RentInvoiceFlowTest extends TestCase
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

        $this->actingAs($this->admin);
    }

    protected function bedlessStudent(array $attrs = []): Student
    {
        return Student::create(array_merge([
            'hostel_id' => $this->hostel->id, 'name' => 'Enquiry Student',
            'mobile' => (string) random_int(9000000000, 9999999999),
            'occupation_type' => 'student', 'status' => 'active',
            'join_date' => now()->subDays(5)->startOfDay(),
        ], $attrs));
    }

    protected function firstBed(): Bed
    {
        return $this->room->beds()->where('status', 'empty')->firstOrFail();
    }

    public function test_setting_a_plan_on_a_bedless_student_saves_it_but_raises_no_invoice(): void
    {
        $student = $this->bedlessStudent();

        $this->put(route('admin.students.fee-settings.update', $student), [
            'fee_frequency' => 'monthly', 'fee_amount' => 15000,
        ])->assertRedirect();

        $student->refresh();
        $this->assertEquals(15000, (float) $student->fee_amount);
        $this->assertSame('monthly', $student->fee_frequency);
        // The point: no money is asked for until a bed exists.
        $this->assertSame(0, $student->invoices()->count());
    }

    public function test_changing_a_saved_plan_while_bedless_still_raises_no_invoice(): void
    {
        $student = $this->bedlessStudent();

        $this->put(route('admin.students.fee-settings.update', $student), [
            'fee_frequency' => 'monthly', 'fee_amount' => 15000,
        ])->assertRedirect();

        // Changed their mind before they ever moved in — still just a saved plan.
        $this->put(route('admin.students.fee-settings.update', $student), [
            'fee_frequency' => 'yearly', 'fee_amount' => 120000,
        ])->assertRedirect();

        $student->refresh();
        $this->assertEquals(120000, (float) $student->fee_amount);
        $this->assertSame('yearly', $student->fee_frequency);
        $this->assertSame(0, $student->invoices()->count());
    }

    public function test_assigning_a_bed_raises_the_first_invoice_dated_from_the_registered_join_date(): void
    {
        $joinDate = now()->subDays(5)->startOfDay();
        $student = $this->bedlessStudent(['join_date' => $joinDate]);

        // A plan was set earlier while bedless (no invoice yet).
        $this->put(route('admin.students.fee-settings.update', $student), [
            'fee_frequency' => 'monthly', 'fee_amount' => 15000,
        ])->assertRedirect();
        $this->assertSame(0, $student->invoices()->count());

        // Now they actually get a bed — THIS raises the first invoice.
        $this->post(route('admin.property.assign'), [
            'student_id' => $student->id,
            'bed_id' => $this->firstBed()->id,
            'join_date' => now()->toDateString(), // the bed move-in date, capped at today
            'fee_frequency' => 'monthly', 'fee_amount' => 15000,
        ])->assertRedirect();

        $student->refresh();
        $this->assertSame(1, $student->invoices()->count());

        $invoice = $student->invoices()->firstOrFail();
        // Billed from the REGISTERED join date, not the move-in date.
        $this->assertSame($joinDate->toDateString(), $invoice->billing_cycle_start->toDateString());
        $this->assertSame($joinDate->toDateString(), $invoice->due_date->toDateString());
        $this->assertEquals(15000, (float) $invoice->amount);
    }

    public function test_a_plan_change_after_the_first_invoice_still_prorates(): void
    {
        $student = $this->bedlessStudent();

        // Assign → first invoice exists.
        app(BedAssignmentService::class)->assign($student, $this->firstBed(), [
            'join_date' => $student->join_date->toDateString(),
            'fee_amount' => 15000, 'fee_frequency' => 'monthly',
        ]);
        app(\App\Services\ProrationService::class)->generateInitialInvoice($student->refresh());
        $this->assertSame(1, $student->invoices()->count());

        // Changing the plan now that an invoice exists runs proration → new invoice.
        $this->put(route('admin.students.fee-settings.update', $student), [
            'fee_frequency' => 'monthly', 'fee_amount' => 18000,
        ])->assertRedirect();

        $this->assertSame(2, $student->fresh()->invoices()->count());
    }
}
