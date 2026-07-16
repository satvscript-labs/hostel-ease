<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Hostel;
use App\Models\Payment;
use App\Models\PaymentMode;
use App\Models\Staff;
use App\Models\StaffSalaryPayment;
use App\Models\Student;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rewritten in W6.2 with the module: expenses now validate modes against the
 * tenant's payment_modes table, the P&L counts cash-only income, staff
 * salaries mirror into expenses, and expenses are editable. (The old
 * profit-loss test also predated the Payment schema — it wrote a
 * payment_type column that no longer exists.)
 */
class ExpenseTest extends TestCase
{
    use RefreshDatabase;

    protected Hostel $hostel;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hostel = Hostel::factory()->create();
        $this->admin = User::factory()->create(['hostel_id' => $this->hostel->id, 'role' => 'hostel_admin']);
        Tenant::set($this->hostel->id);

        foreach ([['cash', 'Cash'], ['upi', 'UPI']] as $i => [$code, $name]) {
            PaymentMode::create(['hostel_id' => $this->hostel->id, 'code' => $code, 'name' => $name,
                'is_active' => true, 'requires_reference' => false, 'sort_order' => $i]);
        }

        $this->actingAs($this->admin);
    }

    protected function student(): Student
    {
        return Student::create(['hostel_id' => $this->hostel->id, 'name' => 'A', 'mobile' => '9000000001',
            'occupation_type' => 'student', 'status' => 'active']);
    }

    protected function expense(array $attrs = []): Expense
    {
        return Expense::create(array_merge([
            'hostel_id' => $this->hostel->id, 'category' => 'maintenance', 'title' => 'Plumbing',
            'amount' => 2500, 'expense_date' => now(), 'mode' => 'cash',
        ], $attrs));
    }

    protected function staff(): Staff
    {
        return Staff::create(['hostel_id' => $this->hostel->id, 'name' => 'Ramesh',
            'designation' => 'Warden', 'monthly_salary' => 12000, 'is_active' => true]);
    }

    public function test_admin_can_record_an_expense(): void
    {
        $this->post(route('admin.expenses.store'), [
            'category' => 'electricity', 'title' => 'May bill', 'amount' => 3200,
            'expense_date' => now()->toDateString(), 'mode' => 'upi',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('expenses', ['title' => 'May bill', 'recorded_by' => $this->admin->id]);
    }

    /** W6.2: modes come from the tenant's payment_modes table, not config. */
    public function test_expense_mode_must_exist_in_the_payment_modes_table(): void
    {
        // 'rtgs' was valid in the old hardcoded config — this hostel never
        // added it, so it must be rejected now.
        $this->post(route('admin.expenses.store'), [
            'category' => 'electricity', 'title' => 'May bill', 'amount' => 3200,
            'expense_date' => now()->toDateString(), 'mode' => 'rtgs',
        ])->assertSessionHasErrors('mode');

        // An owner-defined mode works the moment it exists.
        PaymentMode::create(['hostel_id' => $this->hostel->id, 'code' => 'phonepe', 'name' => 'PhonePe',
            'is_active' => true, 'requires_reference' => false, 'sort_order' => 5]);

        $this->post(route('admin.expenses.store'), [
            'category' => 'electricity', 'title' => 'June bill', 'amount' => 3300,
            'expense_date' => now()->toDateString(), 'mode' => 'phonepe',
        ])->assertSessionHasNoErrors();
    }

    /**
     * W6.2 owner decision: income is CASH-ONLY. A 'credit' payment applies
     * money that was already income once; a 'credit_note' is a refund. The
     * old sum('amount') counted both as fresh revenue.
     */
    public function test_profit_loss_counts_cash_only_income(): void
    {
        $student = $this->student();
        $base = ['hostel_id' => $this->hostel->id, 'student_id' => $student->id, 'paid_on' => now()];

        Payment::create($base + ['receipt_number' => 'R1', 'amount' => 10000, 'mode' => 'cash']);
        Payment::create($base + ['receipt_number' => 'R2', 'amount' => 4000, 'mode' => 'credit']);       // credit applied — not income
        Payment::create($base + ['receipt_number' => 'R3', 'amount' => 1500, 'mode' => 'credit_note']);  // refund — not income
        $this->expense(['amount' => 2500]);

        $summary = $this->get(route('admin.expenses.index'))->assertOk()->viewData('summary');

        $this->assertEquals(10000, $summary['income']);
        $this->assertEquals(2500, $summary['expense']);
        $this->assertEquals(7500, $summary['profit']);
    }

    /** The P&L covers the whole window even when the list paginates. */
    public function test_summary_aggregates_the_window_not_the_page(): void
    {
        foreach (range(1, 18) as $i) {
            $this->expense(['title' => "Expense {$i}", 'amount' => 100]);
        }

        $response = $this->get(route('admin.expenses.index'))->assertOk();

        $this->assertCount(15, $response->viewData('expenses')); // page 1
        $this->assertEquals(1800, $response->viewData('summary')['expense']); // whole window
        $this->assertEquals(1800, $response->viewData('summary')['by_category']['maintenance']);
    }

    public function test_search_and_category_filter_are_server_side(): void
    {
        $this->expense(['title' => 'Plumber visit']);
        $this->expense(['title' => 'Diesel for generator', 'category' => 'other']);

        $filtered = $this->get(route('admin.expenses.index', ['search' => 'diesel']))->assertOk()->viewData('expenses');
        $this->assertCount(1, $filtered);
        $this->assertSame('Diesel for generator', $filtered->first()->title);

        $byCat = $this->get(route('admin.expenses.index', ['category' => 'maintenance']))->assertOk()->viewData('expenses');
        $this->assertCount(1, $byCat);
        $this->assertSame('Plumber visit', $byCat->first()->title);
    }

    /** ?from=garbage used to throw InvalidFormatException → 500. */
    public function test_garbage_date_filters_fall_back_instead_of_crashing(): void
    {
        $this->expense();

        $this->get(route('admin.expenses.index', ['from' => 'garbage', 'to' => 'also-garbage']))->assertOk();
    }

    /** New in W6.2 — a typo'd amount used to mean delete + re-enter. */
    public function test_expense_can_be_edited(): void
    {
        $expense = $this->expense(['amount' => 2500]);

        $this->patch(route('admin.expenses.update', $expense), [
            'category' => 'maintenance', 'title' => 'Plumbing — corrected', 'amount' => 2900,
            'expense_date' => now()->toDateString(), 'mode' => 'upi',
        ])->assertRedirect()->assertSessionHas('success');

        $expense->refresh();
        $this->assertSame('Plumbing — corrected', $expense->title);
        $this->assertEquals(2900.0, (float) $expense->amount);
        $this->assertSame('upi', $expense->mode);
    }

    /** W6.2 owner decision: paying a salary logs the expense automatically. */
    public function test_paying_a_salary_creates_a_linked_expense_mirror(): void
    {
        $staff = $this->staff();

        $this->post(route('admin.staff.salary', $staff), [
            'salary_month' => now()->format('Y-m'), 'amount' => 12000,
            'paid_on' => now()->toDateString(), 'mode' => 'cash',
        ])->assertRedirect()->assertSessionHas('success');

        $payment = StaffSalaryPayment::firstOrFail();
        $mirror = Expense::where('staff_salary_payment_id', $payment->id)->firstOrFail();

        $this->assertSame('staff_salary', $mirror->category);
        $this->assertEquals(12000.0, (float) $mirror->amount);
        $this->assertSame('Ramesh', $mirror->paid_to);
        $this->assertStringContainsString('Ramesh', $mirror->title);
        $this->assertSame(now()->toDateString(), $mirror->expense_date->toDateString());
    }

    /** Deleting the salary entry takes its expense mirror with it. */
    public function test_deleting_a_salary_removes_its_expense_mirror(): void
    {
        $staff = $this->staff();
        $this->post(route('admin.staff.salary', $staff), [
            'salary_month' => now()->format('Y-m'), 'amount' => 12000,
            'paid_on' => now()->toDateString(), 'mode' => 'cash',
        ]);
        $payment = StaffSalaryPayment::firstOrFail();

        $this->delete(route('admin.staff.salary.destroy', [$staff, $payment]))->assertRedirect();

        $this->assertSoftDeleted('staff_salary_payments', ['id' => $payment->id]);
        $this->assertSame(0, Expense::count());
    }

    /** Salary mirrors are managed from the Staff page — not editable here. */
    public function test_salary_mirror_expenses_cannot_be_edited_or_deleted_directly(): void
    {
        $staff = $this->staff();
        $this->post(route('admin.staff.salary', $staff), [
            'salary_month' => now()->format('Y-m'), 'amount' => 12000,
            'paid_on' => now()->toDateString(), 'mode' => 'cash',
        ]);
        $mirror = Expense::whereNotNull('staff_salary_payment_id')->firstOrFail();

        $this->patch(route('admin.expenses.update', $mirror), [
            'category' => 'staff_salary', 'title' => 'Tampered', 'amount' => 1,
            'expense_date' => now()->toDateString(), 'mode' => 'cash',
        ])->assertSessionHas('error');
        $this->assertNotEquals('Tampered', $mirror->fresh()->title);

        $this->delete(route('admin.expenses.destroy', $mirror))->assertSessionHas('error');
        $this->assertNotSoftDeleted('expenses', ['id' => $mirror->id]);
    }

    /** A salary paid through a mode the hostel hasn't defined is rejected. */
    public function test_salary_mode_must_exist_in_the_payment_modes_table(): void
    {
        $staff = $this->staff();

        $this->post(route('admin.staff.salary', $staff), [
            'salary_month' => now()->format('Y-m'), 'amount' => 12000,
            'paid_on' => now()->toDateString(), 'mode' => 'bank',
        ])->assertSessionHasErrors('mode');

        $this->assertSame(0, StaffSalaryPayment::count());
        $this->assertSame(0, Expense::count());
    }
}
