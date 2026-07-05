<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Hostel;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

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
    }

    public function test_admin_can_record_an_expense(): void
    {
        $this->actingAs($this->admin)->post(route('admin.expenses.store'), [
            'category' => 'electricity', 'title' => 'May bill', 'amount' => 3200,
            'expense_date' => now()->toDateString(), 'mode' => 'upi',
        ])->assertRedirect();

        $this->assertDatabaseHas('expenses', ['title' => 'May bill', 'recorded_by' => $this->admin->id]);
    }

    public function test_profit_loss_reflects_income_minus_expenses(): void
    {
        $student = Student::create(['hostel_id' => $this->hostel->id, 'name' => 'A', 'mobile' => '9000000001',
            'occupation_type' => 'student', 'status' => 'active']);
        Payment::create(['hostel_id' => $this->hostel->id, 'student_id' => $student->id, 'receipt_number' => 'R1',
            'amount' => 10000, 'payment_type' => 'full', 'mode' => 'cash', 'paid_on' => now()]);
        Expense::create(['hostel_id' => $this->hostel->id, 'category' => 'maintenance', 'title' => 'Plumbing',
            'amount' => 2500, 'expense_date' => now(), 'mode' => 'cash']);

        $response = $this->actingAs($this->admin)->get(route('admin.expenses.index'));
        $response->assertOk();
        $summary = $response->viewData('summary');

        $this->assertEquals(10000, $summary['income']);
        $this->assertEquals(2500, $summary['expense']);
        $this->assertEquals(7500, $summary['profit']);
    }
}
