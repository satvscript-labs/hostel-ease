<?php

namespace Tests\Feature;

use App\Models\Hostel;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use App\Services\LedgerService;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_totals_aggregate_across_invoices_and_payments(): void
    {
        $hostel = Hostel::factory()->create();
        User::factory()->create(['hostel_id' => $hostel->id, 'role' => 'hostel_admin']);
        Tenant::set($hostel->id);

        $student = Student::create(['hostel_id' => $hostel->id, 'name' => 'Amit', 'mobile' => '9876543210',
            'occupation_type' => 'student', 'status' => 'active']);

        Invoice::create(['hostel_id' => $hostel->id, 'student_id' => $student->id, 'type' => 'fee',
            'title' => 'Semester 1 Fee', 'amount' => 10000, 'paid_amount' => 4000, 'status' => 'partial']);
        Invoice::create(['hostel_id' => $hostel->id, 'student_id' => $student->id, 'type' => 'rent',
            'title' => 'Rent · '.now()->format('M Y'), 'amount' => 5000, 'paid_amount' => 5000, 'status' => 'paid']);

        Payment::create(['hostel_id' => $hostel->id, 'student_id' => $student->id, 'receipt_number' => 'R1',
            'amount' => 4000, 'mode' => 'cash', 'paid_on' => now()]);
        Payment::create(['hostel_id' => $hostel->id, 'student_id' => $student->id, 'receipt_number' => 'R2',
            'amount' => 5000, 'mode' => 'upi', 'paid_on' => now()]);

        $totals = app(LedgerService::class)->totalsFor($student);

        $this->assertEquals(15000, $totals['billed']);     // 10000 + 5000
        $this->assertEquals(9000, $totals['paid']);        // 4000 + 5000
        $this->assertEquals(6000, $totals['outstanding']); // 6000 + 0
    }
}
