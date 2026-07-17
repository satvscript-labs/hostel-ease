<?php

namespace Tests\Feature;

use App\Models\Hostel;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use App\Services\ReportService;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
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

    public function test_collection_report_totals_payments_in_range(): void
    {
        $student = Student::create(['hostel_id' => $this->hostel->id, 'name' => 'A', 'mobile' => '9000000001',
            'occupation_type' => 'student', 'status' => 'active']);

        Payment::create(['hostel_id' => $this->hostel->id, 'student_id' => $student->id, 'receipt_number' => 'R1',
            'amount' => 3000, 'mode' => 'cash', 'paid_on' => now()]);
        Payment::create(['hostel_id' => $this->hostel->id, 'student_id' => $student->id, 'receipt_number' => 'R2',
            'amount' => 2000, 'mode' => 'upi', 'paid_on' => now()]);
        // Outside the range — must be excluded.
        Payment::create(['hostel_id' => $this->hostel->id, 'student_id' => $student->id, 'receipt_number' => 'R3',
            'amount' => 9999, 'mode' => 'cash', 'paid_on' => now()->subYear()]);

        $data = app(ReportService::class)->collection('monthly', now()->startOfMonth(), now()->endOfDay());

        $this->assertEquals(5000, $data['total']);
    }

    /** W8: pendingFees() became duesAging() — same core promise, one query. */
    public function test_dues_report_lists_only_students_with_balance(): void
    {
        $owing = Student::create(['hostel_id' => $this->hostel->id, 'name' => 'Owing', 'mobile' => '9000000002',
            'occupation_type' => 'student', 'status' => 'active']);
        $clear = Student::create(['hostel_id' => $this->hostel->id, 'name' => 'Clear', 'mobile' => '9000000003',
            'occupation_type' => 'student', 'status' => 'active']);

        Invoice::create(['hostel_id' => $this->hostel->id, 'student_id' => $owing->id, 'type' => 'fee',
            'title' => 'Semester 1 Fee', 'amount' => 10000, 'paid_amount' => 2000, 'status' => 'partial']);
        Invoice::create(['hostel_id' => $this->hostel->id, 'student_id' => $clear->id, 'type' => 'fee',
            'title' => 'Semester 1 Fee', 'amount' => 5000, 'paid_amount' => 5000, 'status' => 'paid']);

        $data = app(ReportService::class)->duesAging();

        $this->assertCount(1, $data['rows']);
        $this->assertSame('Owing', $data['rows'][0][0]);
        $this->assertEquals(8000, $data['total']);
    }
}
