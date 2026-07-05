<?php

namespace Tests\Feature;

use App\Models\Hostel;
use App\Models\MonthlyRent;
use App\Models\Payment;
use App\Models\SemesterFee;
use App\Models\Student;
use App\Models\User;
use App\Services\LedgerService;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_totals_aggregate_across_obligations_and_payments(): void
    {
        $hostel = Hostel::factory()->create();
        User::factory()->create(['hostel_id' => $hostel->id, 'role' => 'hostel_admin']);
        Tenant::set($hostel->id);

        $student = Student::create(['hostel_id' => $hostel->id, 'name' => 'Amit', 'mobile' => '9876543210',
            'occupation_type' => 'student', 'status' => 'active']);

        SemesterFee::create(['hostel_id' => $hostel->id, 'student_id' => $student->id, 'semester' => 1,
            'total_fee' => 10000, 'paid_amount' => 4000, 'balance' => 6000, 'status' => 'partial']);
        MonthlyRent::create(['hostel_id' => $hostel->id, 'student_id' => $student->id, 'rent_month' => now()->startOfMonth(),
            'amount' => 5000, 'paid_amount' => 5000, 'balance' => 0, 'status' => 'paid']);

        Payment::create(['hostel_id' => $hostel->id, 'student_id' => $student->id, 'receipt_number' => 'R1',
            'amount' => 4000, 'payment_type' => 'partial', 'mode' => 'cash', 'paid_on' => now()]);
        Payment::create(['hostel_id' => $hostel->id, 'student_id' => $student->id, 'receipt_number' => 'R2',
            'amount' => 5000, 'payment_type' => 'full', 'mode' => 'upi', 'paid_on' => now()]);

        $totals = app(LedgerService::class)->totalsFor($student);

        $this->assertEquals(15000, $totals['billed']);     // 10000 + 5000
        $this->assertEquals(9000, $totals['paid']);        // 4000 + 5000
        $this->assertEquals(6000, $totals['outstanding']); // 6000 + 0
    }
}
