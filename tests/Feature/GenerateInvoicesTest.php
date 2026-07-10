<?php

namespace Tests\Feature;

use App\Models\Hostel;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the `hostel:generate-invoices` cron, which supersedes the old
 * per-frequency SemesterFee/MonthlyRent generators — it now drives invoice
 * generation for every fee frequency (monthly/semester/yearly) directly
 * from the student's own fee_amount/fee_frequency/join_date.
 */
class GenerateInvoicesTest extends TestCase
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

    public function test_generation_creates_an_initial_invoice_and_is_idempotent_same_day(): void
    {
        $student = Student::create(['hostel_id' => $this->hostel->id, 'name' => 'Pro', 'mobile' => '9000000001',
            'occupation_type' => 'working', 'status' => 'active', 'join_date' => now()->toDateString(),
            'fee_amount' => 6000, 'fee_frequency' => 'monthly']);

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
        Student::create(['hostel_id' => $this->hostel->id, 'name' => 'NoFee', 'mobile' => '9000000002',
            'occupation_type' => 'working', 'status' => 'active', 'join_date' => now()->toDateString()]);

        $this->artisan('hostel:generate-invoices')->assertExitCode(0);

        $this->assertSame(0, Invoice::count());
    }
}
