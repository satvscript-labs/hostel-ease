<?php

namespace Tests\Feature;

use App\Models\Hostel;
use App\Models\Invoice;
use App\Models\PaymentMode;
use App\Models\SecurityDeposit;
use App\Models\Student;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * First tests this module has ever had (W6.4). The two that matter most:
 * cross-tenant isolation (the model had NO tenant scope — any admin could
 * refund another hostel's deposit by id) and full settlement (refunded +
 * deducted must equal the deposit — the old guard let money vanish).
 */
class SecurityDepositTest extends TestCase
{
    use RefreshDatabase;

    protected Hostel $hostel;
    protected User $admin;
    protected PaymentMode $cash;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hostel = Hostel::factory()->create();
        $this->admin = User::factory()->create(['hostel_id' => $this->hostel->id, 'role' => 'hostel_admin']);
        Tenant::set($this->hostel->id);

        $this->cash = PaymentMode::create(['hostel_id' => $this->hostel->id, 'code' => 'cash', 'name' => 'Cash',
            'is_active' => true, 'requires_reference' => false, 'sort_order' => 0]);

        $this->actingAs($this->admin);
    }

    protected function student(string $name = 'Amit'): Student
    {
        return Student::create(['hostel_id' => $this->hostel->id, 'name' => $name,
            'mobile' => '9'.rand(100000000, 999999999), 'occupation_type' => 'student', 'status' => 'active']);
    }

    protected function deposit(Student $student, float $amount = 5000): SecurityDeposit
    {
        return SecurityDeposit::create([
            'hostel_id' => $this->hostel->id, 'student_id' => $student->id,
            'amount' => $amount, 'status' => 'collected', 'payment_mode_id' => $this->cash->id,
            'receipt_number' => 'SD-'.$this->hostel->id.'-'.str_pad((string) rand(1, 99999), 5, '0', STR_PAD_LEFT),
            'collected_on' => now()->subMonth(), 'created_by' => $this->admin->id,
        ]);
    }

    public function test_deposit_can_be_recorded_with_a_sequential_receipt(): void
    {
        $student = $this->student();

        $this->post(route('admin.security-deposits.store'), [
            'student_id' => $student->id, 'amount' => 5000,
            'payment_mode_id' => $this->cash->id, 'collected_on' => now()->toDateString(),
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('security_deposits', [
            'student_id' => $student->id, 'status' => 'collected',
            'receipt_number' => 'SD-'.$this->hostel->id.'-00001',
        ]);
    }

    public function test_future_collection_dates_are_rejected(): void
    {
        $this->post(route('admin.security-deposits.store'), [
            'student_id' => $this->student()->id, 'amount' => 5000,
            'payment_mode_id' => $this->cash->id, 'collected_on' => now()->addDay()->toDateString(),
        ])->assertSessionHasErrors('collected_on');
    }

    /**
     * THE security fix: another hostel's admin must not even SEE this
     * deposit, let alone refund it. Before W6.4 this request would have
     * refunded the money — the model had no tenant scope and the route bound
     * the deposit by bare id.
     */
    public function test_cross_tenant_deposits_are_invisible_and_unrefundable(): void
    {
        $deposit = $this->deposit($this->student());

        $otherHostel = Hostel::factory()->create();
        $otherAdmin = User::factory()->create(['hostel_id' => $otherHostel->id, 'role' => 'hostel_admin']);

        $this->actingAs($otherAdmin)->post(route('admin.security-deposits.refund', $deposit), [
            'refunded_amount' => 5000, 'deducted_amount' => 0,
        ])->assertNotFound();

        $this->assertSame('collected', $deposit->fresh()->status); // untouched
    }

    /** Owner decision: every rupee accounted for — partial settlement refused. */
    public function test_refund_must_settle_the_full_deposit(): void
    {
        $deposit = $this->deposit($this->student(), 5000);

        // 2000 + 1000 leaves ₹2,000 unaccounted — the old code accepted this
        // and marked the deposit refunded anyway.
        $this->post(route('admin.security-deposits.refund', $deposit), [
            'refunded_amount' => 2000, 'deducted_amount' => 1000,
        ])->assertSessionHas('error');

        $this->assertSame('collected', $deposit->fresh()->status);
    }

    public function test_refund_with_deduction_settles_dues_and_reverts_cleanly(): void
    {
        $student = $this->student();
        $deposit = $this->deposit($student, 5000);

        $invoice = Invoice::create(['hostel_id' => $this->hostel->id, 'student_id' => $student->id,
            'type' => 'other', 'title' => 'Broken chair', 'amount' => 1200, 'status' => 'pending']);

        $this->post(route('admin.security-deposits.refund', $deposit), [
            'refunded_amount' => 3800, 'deducted_amount' => 1200,
            'deduct_invoice_ids' => [$invoice->id],
        ])->assertRedirect()->assertSessionHas('success');

        $deposit->refresh();
        $invoice->refresh();
        $this->assertSame('refunded', $deposit->status);
        $this->assertEquals(1200.0, (float) $invoice->paid_amount);
        $this->assertSame('paid', $invoice->status);

        // Revert restores both sides.
        $this->post(route('admin.security-deposits.revert-refund', $deposit))->assertSessionHas('success');
        $this->assertSame('collected', $deposit->fresh()->status);
        $this->assertEquals(0.0, (float) $invoice->fresh()->paid_amount);
    }

    /**
     * A deduction needn't settle a due at all — keeping money for room damage
     * is normal. It just has to say WHY (owner rule): money kept with no
     * invoice behind it and no note is money the record can't explain.
     */
    public function test_deduction_without_dues_is_allowed_but_needs_a_reason(): void
    {
        $deposit = $this->deposit($this->student(), 5000);

        $this->post(route('admin.security-deposits.refund', $deposit), [
            'refunded_amount' => 4000, 'deducted_amount' => 1000,
        ])->assertSessionHas('error');
        $this->assertSame('collected', $deposit->fresh()->status);

        $this->post(route('admin.security-deposits.refund', $deposit), [
            'refunded_amount' => 4000, 'deducted_amount' => 1000,
            'refund_note' => 'Broken window, no invoice raised',
        ])->assertSessionHas('success');

        $deposit->refresh();
        $this->assertSame('refunded', $deposit->status);
        $this->assertEquals(1000.0, (float) $deposit->deducted_amount);
        $this->assertEquals(4000.0, (float) $deposit->refunded_amount);
    }

    /**
     * Deduction beyond the selected dues is the two parts stacking: the due
     * gets settled, the rest is retained (with a reason). Previously this was
     * rejected outright.
     */
    public function test_deduction_beyond_selected_dues_settles_the_due_and_retains_the_rest(): void
    {
        $student = $this->student();
        $deposit = $this->deposit($student, 5000);
        $invoice = Invoice::create(['hostel_id' => $this->hostel->id, 'student_id' => $student->id,
            'type' => 'other', 'title' => 'Small due', 'amount' => 300, 'status' => 'pending']);

        // ₹1,300 deducted: ₹300 settles the due, ₹1,000 kept for damages.
        $this->post(route('admin.security-deposits.refund', $deposit), [
            'refunded_amount' => 3700, 'deducted_amount' => 1300,
            'deduct_invoice_ids' => [$invoice->id],
            'refund_note' => 'Mattress damage',
        ])->assertSessionHas('success');

        $invoice->refresh();
        $this->assertEquals(300.0, (float) $invoice->paid_amount);   // due settled, never overpaid
        $this->assertSame('paid', $invoice->status);
        $this->assertEquals(1300.0, (float) $deposit->fresh()->deducted_amount);
    }

    /** New in W6.4 — a typo'd deposit used to be permanent. */
    public function test_deposit_is_editable_only_while_held(): void
    {
        $deposit = $this->deposit($this->student(), 5000);

        $this->patch(route('admin.security-deposits.update', $deposit), [
            'amount' => 6000, 'payment_mode_id' => $this->cash->id,
            'collected_on' => now()->subMonth()->toDateString(),
        ])->assertSessionHas('success');
        $this->assertEquals(6000.0, (float) $deposit->fresh()->amount);

        // Settle it, then editing must refuse.
        $this->post(route('admin.security-deposits.refund', $deposit), [
            'refunded_amount' => 6000, 'deducted_amount' => 0,
        ]);
        $this->patch(route('admin.security-deposits.update', $deposit), [
            'amount' => 1, 'payment_mode_id' => $this->cash->id,
            'collected_on' => now()->subMonth()->toDateString(),
        ])->assertSessionHas('error');
        $this->assertEquals(6000.0, (float) $deposit->fresh()->amount);
    }

    /**
     * A second deposit is allowed (top-up, re-admission) — but the picker
     * must SAY the student already holds one. Not knowing is how the same
     * money gets collected twice.
     */
    public function test_students_holding_a_deposit_are_flagged_for_the_picker(): void
    {
        $holder = $this->student('Already Paid');
        $this->deposit($holder, 2000);
        $this->deposit($holder, 1000);
        $fresh = $this->student('Fresh Face');

        $students = $this->get(route('admin.security-deposits.index'))->assertOk()->viewData('students');

        $h = $students->firstWhere('id', $holder->id);
        $this->assertSame(2, $h->held_count);
        $this->assertEquals(3000.0, (float) $h->held_total);

        // No held deposits → nothing to warn about.
        $f = $students->firstWhere('id', $fresh->id);
        $this->assertSame(0, $f->held_count);

        // A refunded deposit is settled — it must not raise the flag.
        $settled = $this->student('Settled Up');
        $this->deposit($settled, 5000)->update(['status' => 'refunded', 'refunded_amount' => 5000, 'refunded_on' => now()]);
        $s = $this->get(route('admin.security-deposits.index'))->viewData('students')->firstWhere('id', $settled->id);
        $this->assertSame(0, $s->held_count);
    }

    public function test_index_searches_and_filters_server_side(): void
    {
        $this->deposit($this->student('Findable Person'));
        $refunded = $this->deposit($this->student('Other Person'));
        $refunded->update(['status' => 'refunded', 'refunded_amount' => 5000, 'refunded_on' => now()]);

        $found = $this->get(route('admin.security-deposits.index', ['search' => 'findable']))
            ->assertOk()->viewData('deposits');
        $this->assertCount(1, $found);

        $held = $this->get(route('admin.security-deposits.index', ['status' => 'collected']))
            ->assertOk()->viewData('deposits');
        $this->assertCount(1, $held);
        $this->assertSame('Findable Person', $held->first()->student->name);
    }
}
