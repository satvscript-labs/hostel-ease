<?php

namespace Tests\Feature;

use App\Models\Hostel;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMode;
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
        PaymentMode::create(['hostel_id' => $this->hostel->id, 'code' => 'cash', 'name' => 'Cash',
            'is_active' => true, 'requires_reference' => false, 'sort_order' => 0]);
        $this->actingAs($this->admin);
    }

    /**
     * Public-ID hardening (U2). Receipts are the enumerable one: payments/{payment}/pdf
     * is a GET that returns a real document, so a sequential id there let anyone
     * walk other people's receipts. The URL now carries an opaque ULID.
     */
    public function test_the_receipt_pdf_url_uses_the_public_id_and_the_integer_is_rejected(): void
    {
        $this->post(route('admin.students.collect', $this->student), [
            'amount' => 5000, 'mode' => 'cash', 'paid_on' => now()->toDateString(),
        ])->assertRedirect();

        $payment = Payment::firstOrFail();
        $this->assertSame(26, strlen($payment->public_id));

        $url = route('admin.payments.pdf', $payment);
        $this->assertStringEndsWith('/'.$payment->public_id.'/pdf', $url);

        $this->get($url)->assertOk();
        $this->get('/admin/payments/'.$payment->id.'/pdf')->assertNotFound();
    }

    public function test_recording_a_payment_generates_a_unique_receipt(): void
    {
        $this->post(route('admin.students.collect', $this->student), [
            'amount' => 5000,
            'mode' => 'cash',
            'paid_on' => now()->toDateString(),
        ])->assertRedirect();

        $payment = Payment::firstOrFail();
        $this->assertStringStartsWith('RCPT-'.$this->hostel->id.'-', $payment->receipt_number);
        $this->assertSame($this->admin->id, $payment->collected_by);
    }

    public function test_payment_settles_a_linked_invoice_balance(): void
    {
        $invoice = Invoice::create([
            'hostel_id' => $this->hostel->id, 'student_id' => $this->student->id, 'type' => 'fee',
            'title' => 'Semester 1 Fee', 'amount' => 10000, 'paid_amount' => 0, 'status' => 'pending',
        ]);

        app(PaymentService::class)->record([
            'student_id' => $this->student->id,
            'amount' => 4000,
            'mode' => 'upi',
        ]);

        $invoice->refresh();
        $this->assertEquals(4000, (float) $invoice->paid_amount);
        $this->assertEquals(6000, (float) $invoice->balance);
        $this->assertSame('partial', $invoice->status);
    }

    public function test_credit_used_cannot_exceed_students_credit_balance(): void
    {
        $this->student->update(['credit_balance' => 100]);

        $this->post(route('admin.students.collect', $this->student), [
            'amount' => 500,
            'mode' => 'cash',
            'credit_used' => 500, // more than the 100 available
            'paid_on' => now()->toDateString(),
        ])->assertSessionHasErrors('credit_used');

        $this->assertDatabaseMissing('payments', ['student_id' => $this->student->id]);
    }
}
