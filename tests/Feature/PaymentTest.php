<?php

namespace Tests\Feature;

use App\Models\Hostel;
use App\Models\Payment;
use App\Models\SemesterFee;
use App\Models\Student;
use App\Models\User;
use App\Services\PaymentService;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    protected Hostel $hostel;
    protected User $admin;
    protected Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hostel = Hostel::factory()->create();
        $this->admin = User::factory()->create(['hostel_id' => $this->hostel->id, 'role' => 'hostel_admin']);
        Tenant::set($this->hostel->id);
        $this->student = Student::create([
            'hostel_id' => $this->hostel->id, 'name' => 'Amit', 'mobile' => '9876543210',
            'occupation_type' => 'student', 'status' => 'active',
        ]);
        $this->actingAs($this->admin);
    }

    public function test_recording_a_payment_generates_a_unique_receipt(): void
    {
        $this->post(route('admin.payments.store'), [
            'student_id' => $this->student->id,
            'amount' => 5000,
            'payment_type' => 'full',
            'mode' => 'cash',
            'paid_on' => now()->toDateString(),
        ])->assertRedirect();

        $payment = Payment::firstOrFail();
        $this->assertStringStartsWith('RCPT-'.$this->hostel->id.'-', $payment->receipt_number);
        $this->assertSame($this->admin->id, $payment->collected_by);
    }

    public function test_cheque_requires_reference_number(): void
    {
        $this->post(route('admin.payments.store'), [
            'student_id' => $this->student->id,
            'amount' => 5000,
            'payment_type' => 'full',
            'mode' => 'cheque',
            'paid_on' => now()->toDateString(),
        ])->assertSessionHasErrors('reference_number');
    }

    public function test_payment_settles_a_linked_obligation_balance(): void
    {
        $fee = SemesterFee::create([
            'hostel_id' => $this->hostel->id, 'student_id' => $this->student->id,
            'semester' => 1, 'total_fee' => 10000, 'paid_amount' => 0, 'balance' => 10000, 'status' => 'pending',
        ]);

        app(PaymentService::class)->record([
            'student_id' => $this->student->id,
            'amount' => 4000,
            'mode' => 'upi',
        ], $fee);

        $fee->refresh();
        $this->assertEquals(4000, (float) $fee->paid_amount);
        $this->assertEquals(6000, (float) $fee->balance);
        $this->assertSame('partial', $fee->status);
    }
}
