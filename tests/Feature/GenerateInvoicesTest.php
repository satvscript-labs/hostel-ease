<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\Floor;
use App\Models\Hostel;
use App\Models\Invoice;
use App\Models\Room;
use App\Models\Student;
use App\Models\User;
use App\Services\BedAssignmentService;
use App\Services\BedGenerator;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the `hostel:generate-invoices` cron. It bills MONTHLY plans only
 * (W6.1: semester/yearly have no knowable end date, so they're owner-driven),
 * and only students who actually OCCUPY A BED (W6.4: rent is for a bed).
 */
class GenerateInvoicesTest extends TestCase
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
            'room_number' => '101', 'room_type' => 'non_ac', 'sharing' => 4, 'rent' => 6000]);
        app(BedGenerator::class)->sync($this->room);
    }

    /** Rent is for a bed (W6.4) — the generator only bills housed students. */
    protected function house(Student $student): void
    {
        $bed = $this->room->beds()->where('status', 'empty')->firstOrFail();
        app(BedAssignmentService::class)->assign($student, $bed, []);
    }

    public function test_generation_creates_an_initial_invoice_and_is_idempotent_same_day(): void
    {
        $student = Student::create(['hostel_id' => $this->hostel->id, 'name' => 'Pro', 'mobile' => '9000000001',
            'occupation_type' => 'working', 'status' => 'active', 'join_date' => now()->toDateString(),
            'fee_amount' => 6000, 'fee_frequency' => 'monthly']);
        $this->house($student);

        $this->artisan('hostel:generate-invoices')->assertExitCode(0);

        $this->assertSame(1, Invoice::where('student_id', $student->id)->count());
        $invoice = Invoice::where('student_id', $student->id)->firstOrFail();
        $this->assertEquals(6000, (float) $invoice->amount);
        $this->assertSame('fee', $invoice->type);

        // Re-running the same day must not create a second invoice.
        $this->artisan('hostel:generate-invoices')->assertExitCode(0);
        $this->assertSame(1, Invoice::where('student_id', $student->id)->count());
    }

    public function test_generation_skips_students_without_fee_settings(): void
    {
        $student = Student::create(['hostel_id' => $this->hostel->id, 'name' => 'NoFee', 'mobile' => '9000000002',
            'occupation_type' => 'working', 'status' => 'active', 'join_date' => now()->toDateString()]);
        $this->house($student);

        $this->artisan('hostel:generate-invoices')->assertExitCode(0);

        $this->assertSame(0, Invoice::count());
    }

    /**
     * W6.4 (owner-approved): rent is for a BED. A student released from their
     * bed but still "active" used to keep accruing rent for a room they no
     * longer occupied.
     */
    public function test_generation_skips_students_without_a_bed(): void
    {
        Student::create(['hostel_id' => $this->hostel->id, 'name' => 'Bedless', 'mobile' => '9000000003',
            'occupation_type' => 'working', 'status' => 'active', 'join_date' => now()->toDateString(),
            'fee_amount' => 6000, 'fee_frequency' => 'monthly']);

        $this->artisan('hostel:generate-invoices')->assertExitCode(0);

        $this->assertSame(0, Invoice::count());
    }
}
