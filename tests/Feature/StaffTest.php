<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Hostel;
use App\Models\PaymentMode;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\StaffSalaryPayment;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * First tests this module has ever had (W7.1). The ones that matter most:
 * cross-tenant isolation, the attendance roster's unchecked staff ids, and the
 * delete story — removing a staff member must keep their salary history AND
 * leave the expense mirror deletable, or the mirror is stranded forever.
 */
class StaffTest extends TestCase
{
    use RefreshDatabase;

    protected Hostel $hostel;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->hostel = Hostel::factory()->create();
        $this->admin = User::factory()->create(['hostel_id' => $this->hostel->id, 'role' => 'hostel_admin']);
        Tenant::set($this->hostel->id);

        foreach ([['cash', 'Cash'], ['upi', 'UPI'], ['cheque', 'Cheque']] as $i => [$code, $name]) {
            PaymentMode::create(['hostel_id' => $this->hostel->id, 'code' => $code, 'name' => $name,
                'is_active' => true, 'requires_reference' => $code === 'cheque', 'sort_order' => $i]);
        }

        $this->actingAs($this->admin);
    }

    protected function staff(array $attrs = []): Staff
    {
        return Staff::create(array_merge([
            'hostel_id' => $this->hostel->id, 'name' => 'Govind', 'designation' => 'Guard',
            'mobile' => '9800000001', 'monthly_salary' => 12000, 'is_active' => true,
        ], $attrs));
    }

    protected function salary(Staff $staff, array $attrs = []): StaffSalaryPayment
    {
        return StaffSalaryPayment::create(array_merge([
            'hostel_id' => $this->hostel->id, 'staff_id' => $staff->id,
            'salary_month' => now()->startOfMonth(), 'amount' => 12000,
            'paid_on' => now(), 'mode' => 'cash',
        ], $attrs));
    }

    // ── Directory ────────────────────────────────────────────────────────

    public function test_admin_can_add_staff_and_the_mobile_is_normalised(): void
    {
        $this->post(route('admin.staff.store'), [
            'name' => 'Sita Devi', 'designation' => 'Housekeeping',
            'mobile' => '98 7654 3210', 'aadhaar_number' => '1234 5678 9012',
            'aadhaar_file' => UploadedFile::fake()->image('aadhaar.jpg'),
            'monthly_salary' => 10000, 'is_active' => '1',
        ])->assertRedirect()->assertSessionHas('success');

        // Stored in exactly one shape regardless of how it was typed — the model
        // normalises now, so the seeder/importer can't invent a second shape.
        $this->assertDatabaseHas('staff', [
            'name' => 'Sita Devi', 'mobile' => '+919876543210', 'aadhaar_number' => '123456789012',
        ]);
    }

    public function test_future_join_dates_are_rejected(): void
    {
        $this->post(route('admin.staff.store'), [
            'name' => 'Time Traveller', 'mobile' => '9800000009', 'aadhaar_number' => '123456789012',
            'aadhaar_file' => UploadedFile::fake()->image('a.jpg'),
            'monthly_salary' => 5000, 'join_date' => now()->addWeek()->toDateString(),
        ])->assertSessionHasErrors('join_date');
    }

    /** A file field left empty on edit must never blank the stored path. */
    public function test_editing_without_reuploading_keeps_the_existing_files(): void
    {
        $staff = $this->staff(['photo' => 'staff/photos/p.webp', 'aadhaar_file' => 'staff/documents/a.webp']);

        $this->put(route('admin.staff.update', $staff), [
            'name' => 'Govind Kumar', 'mobile' => '9800000001',
            'aadhaar_number' => '123456789012', 'monthly_salary' => 13000, 'is_active' => '1',
        ])->assertSessionHas('success');

        $staff->refresh();
        $this->assertSame('Govind Kumar', $staff->name);
        $this->assertSame('staff/photos/p.webp', $staff->photo);
        $this->assertSame('staff/documents/a.webp', $staff->aadhaar_file);
    }

    /**
     * The old page filtered client-side by interpolating each name into an
     * Alpine expression, so an apostrophe was a JS syntax error and the row
     * vanished. Server-side search just finds them.
     */
    public function test_search_finds_a_name_containing_an_apostrophe(): void
    {
        $this->staff(['name' => "O'Brien", 'mobile' => '9800000002']);
        $this->staff(['name' => 'Someone Else', 'mobile' => '9800000003']);

        $found = $this->get(route('admin.staff.index', ['search' => "O'Brien"]))
            ->assertOk()->viewData('staff');

        $this->assertCount(1, $found);
        $this->assertSame("O'Brien", $found->first()->name);
    }

    /**
     * A text search strips to no digits, and the mobile clause was built as
     * LIKE '%'.$digits.'%' — so LIKE '%%' matched every row and searching a
     * name returned the whole directory. Caught by the apostrophe test above
     * before this ever ran anywhere.
     */
    public function test_a_search_with_no_digits_does_not_match_every_mobile(): void
    {
        $this->staff(['name' => 'Findable', 'mobile' => '9800000006']);
        $this->staff(['name' => 'Unrelated', 'mobile' => '9800000007']);

        $found = $this->get(route('admin.staff.index', ['search' => 'Findable']))
            ->assertOk()->viewData('staff');

        $this->assertCount(1, $found);
    }

    public function test_search_matches_a_mobile_typed_with_separators(): void
    {
        $this->staff(['name' => 'Reachable', 'mobile' => '9812345678']);
        $this->staff(['name' => 'Other', 'mobile' => '9899999999']);

        $found = $this->get(route('admin.staff.index', ['search' => '98123 45678']))
            ->assertOk()->viewData('staff');

        $this->assertCount(1, $found);
        $this->assertSame('Reachable', $found->first()->name);
    }

    public function test_index_filters_by_status_and_counts_the_month(): void
    {
        $active = $this->staff(['name' => 'Active Person', 'mobile' => '9800000004']);
        $this->staff(['name' => 'Inactive Person', 'mobile' => '9800000005', 'is_active' => false]);

        StaffAttendance::create(['hostel_id' => $this->hostel->id, 'staff_id' => $active->id,
            'date' => now()->startOfMonth(), 'status' => 'present']);
        StaffAttendance::create(['hostel_id' => $this->hostel->id, 'staff_id' => $active->id,
            'date' => now()->startOfMonth()->addDay(), 'status' => 'half_day']);
        StaffAttendance::create(['hostel_id' => $this->hostel->id, 'staff_id' => $active->id,
            'date' => now()->startOfMonth()->addDays(2), 'status' => 'absent']);
        $this->salary($active, ['amount' => 4000]);

        $onlyActive = $this->get(route('admin.staff.index', ['status' => 'active']))->assertOk()->viewData('staff');
        $this->assertCount(1, $onlyActive);

        // present + half_day count as attended; absent does not.
        $row = $onlyActive->first();
        $this->assertSame(2, $row->present_this_month);
        $this->assertEquals(4000.0, (float) $row->paid_this_month);

        $onlyInactive = $this->get(route('admin.staff.index', ['status' => 'inactive']))->assertOk()->viewData('staff');
        $this->assertCount(1, $onlyInactive);
        $this->assertSame('Inactive Person', $onlyInactive->first()->name);
    }

    // ── Tenancy ──────────────────────────────────────────────────────────

    public function test_another_hostels_staff_are_invisible_and_uneditable(): void
    {
        $staff = $this->staff();

        $other = Hostel::factory()->create();
        $otherAdmin = User::factory()->create(['hostel_id' => $other->id, 'role' => 'hostel_admin']);

        $this->actingAs($otherAdmin)->get(route('admin.staff.show', $staff))->assertNotFound();
        $this->actingAs($otherAdmin)->delete(route('admin.staff.destroy', $staff))->assertNotFound();

        $this->assertNotSoftDeleted('staff', ['id' => $staff->id]);
    }

    /**
     * saveAttendance loops the form's `status[<id>]` keys. Unvalidated, a
     * crafted POST writes attendance rows against ANOTHER hostel's staff —
     * junk data at best, a unique-constraint 500 at worst.
     */
    public function test_attendance_ignores_staff_ids_from_another_hostel(): void
    {
        $mine = $this->staff();

        $other = Hostel::factory()->create();
        $theirs = Staff::create(['hostel_id' => $other->id, 'name' => 'Not Mine',
            'mobile' => '9700000001', 'monthly_salary' => 1000, 'is_active' => true]);

        $this->post(route('admin.staff.attendance.save'), [
            'date' => now()->toDateString(),
            'status' => [$mine->id => 'present', $theirs->id => 'absent'],
        ])->assertRedirect();

        $this->assertDatabaseHas('staff_attendances', ['staff_id' => $mine->id, 'status' => 'present']);
        $this->assertDatabaseMissing('staff_attendances', ['staff_id' => $theirs->id]);
    }

    // ── Payroll + the expense mirror ─────────────────────────────────────

    public function test_paying_salary_mirrors_into_expenses_exactly_once(): void
    {
        $staff = $this->staff();

        $this->post(route('admin.staff.salary', $staff), [
            'salary_month' => now()->format('Y-m'), 'amount' => 12000,
            'paid_on' => now()->toDateString(), 'mode' => 'upi',
        ])->assertRedirect()->assertSessionHas('success');

        $payment = StaffSalaryPayment::firstOrFail();
        $this->assertDatabaseHas('expenses', [
            'category' => 'staff_salary', 'amount' => 12000, 'mode' => 'upi',
            'paid_to' => 'Govind', 'staff_salary_payment_id' => $payment->id,
        ]);
        $this->assertSame(1, Expense::count());
    }

    /**
     * THE W7.1 bug: the Board's Pay Salary modal offered cash/upi/**bank**
     * while paySalary validates against the tenant's payment_modes, where the
     * default vocabulary is cash/upi/cheque/rtgs. 'bank' is not a mode, so
     * "Bank Transfer" failed validation every time.
     */
    public function test_a_mode_outside_the_tenants_payment_modes_is_rejected(): void
    {
        $staff = $this->staff();

        $this->post(route('admin.staff.salary', $staff), [
            'salary_month' => now()->format('Y-m'), 'amount' => 12000,
            'paid_on' => now()->toDateString(), 'mode' => 'bank',
        ])->assertSessionHasErrors('mode');

        $this->assertSame(0, StaffSalaryPayment::count());
        $this->assertSame(0, Expense::count());
    }

    public function test_future_payment_dates_are_rejected(): void
    {
        $this->post(route('admin.staff.salary', $this->staff()), [
            'salary_month' => now()->format('Y-m'), 'amount' => 12000,
            'paid_on' => now()->addDay()->toDateString(), 'mode' => 'cash',
        ])->assertSessionHasErrors('paid_on');
    }

    public function test_deleting_a_salary_takes_its_expense_mirror_with_it(): void
    {
        $staff = $this->staff();
        $this->post(route('admin.staff.salary', $staff), [
            'salary_month' => now()->format('Y-m'), 'amount' => 12000,
            'paid_on' => now()->toDateString(), 'mode' => 'cash',
        ]);

        $payment = StaffSalaryPayment::firstOrFail();
        $this->delete(route('admin.staff.salary.destroy', [$staff->id, $payment->id]))
            ->assertSessionHas('success');

        // A phantom expense left behind would inflate the P&L forever.
        $this->assertSame(0, Expense::count());
        $this->assertSame(0, StaffSalaryPayment::count());
    }

    // ── Removal (owner decision, W7.1) ───────────────────────────────────

    public function test_removing_staff_keeps_their_salary_history_and_expenses(): void
    {
        $staff = $this->staff();
        $this->post(route('admin.staff.salary', $staff), [
            'salary_month' => now()->format('Y-m'), 'amount' => 12000,
            'paid_on' => now()->toDateString(), 'mode' => 'cash',
        ]);

        $this->delete(route('admin.staff.destroy', $staff))->assertSessionHas('success');

        $this->assertSoftDeleted('staff', ['id' => $staff->id]);
        // Money that left is money that left.
        $this->assertSame(1, StaffSalaryPayment::count());
        $this->assertSame(1, Expense::count());
    }

    /**
     * THE deadlock this decision creates if the profile 404s: Expenses refuses
     * to delete a salary mirror and points at the staff member's page. If that
     * page were unreachable once they were removed, the mirror would be
     * un-deletable from BOTH sides — a permanent phantom expense. `show` and
     * `salary.destroy` bind withTrashed for exactly this reason.
     */
    public function test_a_removed_staff_profile_stays_reachable_and_its_salary_still_deletable(): void
    {
        $staff = $this->staff();
        $this->post(route('admin.staff.salary', $staff), [
            'salary_month' => now()->format('Y-m'), 'amount' => 12000,
            'paid_on' => now()->toDateString(), 'mode' => 'cash',
        ]);
        $payment = StaffSalaryPayment::firstOrFail();

        $this->delete(route('admin.staff.destroy', $staff));

        $this->get(route('admin.staff.show', $staff->id))->assertOk()->assertSee('Govind');

        $this->delete(route('admin.staff.salary.destroy', [$staff->id, $payment->id]))
            ->assertSessionHas('success');
        $this->assertSame(0, Expense::count());
    }

    public function test_removed_staff_appear_only_under_the_removed_filter_and_can_be_restored(): void
    {
        $staff = $this->staff();
        $this->delete(route('admin.staff.destroy', $staff));

        $this->assertCount(0, $this->get(route('admin.staff.index'))->viewData('staff'));

        $removed = $this->get(route('admin.staff.index', ['status' => 'removed']))->assertOk()->viewData('staff');
        $this->assertCount(1, $removed);

        $this->post(route('admin.staff.restore', $staff->id))->assertSessionHas('success');
        $this->assertNotSoftDeleted('staff', ['id' => $staff->id]);
        $this->assertCount(1, $this->get(route('admin.staff.index'))->viewData('staff'));
    }

    /** A removed member is off the books — no new money may be paid to them. */
    public function test_a_removed_staff_member_cannot_be_paid(): void
    {
        $staff = $this->staff();
        $this->delete(route('admin.staff.destroy', $staff));

        $this->post(route('admin.staff.salary', $staff->id), [
            'salary_month' => now()->format('Y-m'), 'amount' => 12000,
            'paid_on' => now()->toDateString(), 'mode' => 'cash',
        ])->assertNotFound();

        $this->assertSame(0, StaffSalaryPayment::count());
    }
}
