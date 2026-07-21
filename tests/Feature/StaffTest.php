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
        // Uploads land on 'private' now (P2); 'public' is faked too because
        // purge() cleans both during the migration window.
        Storage::fake('private');
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

        // Mobile stored in exactly one shape regardless of how it was typed — the
        // model normalises now, so the seeder/importer can't invent a second shape.
        $this->assertDatabaseHas('staff', [
            'name' => 'Sita Devi', 'mobile' => '+919876543210',
        ]);

        $staff = Staff::where('name', 'Sita Devi')->firstOrFail();

        // P5: the Aadhaar NUMBER is encrypted at rest — the model round-trips the
        // digits (separators stripped), but the raw column holds ciphertext, never
        // the plaintext number.
        $this->assertSame('123456789012', $staff->aadhaar_number);
        $raw = \Illuminate\Support\Facades\DB::table('staff')->where('id', $staff->id)->value('aadhaar_number');
        $this->assertNotSame('123456789012', $raw);
        $this->assertStringNotContainsString('123456789012', (string) $raw);

        // P2: the Aadhaar CARD lands on the PRIVATE disk, tenant-scoped, and NOT
        // on the public web-root disk. This is the whole point of the migration.
        $this->assertStringStartsWith("staff/{$this->hostel->id}/aadhaar/", $staff->aadhaar_file);
        Storage::disk('private')->assertExists($staff->aadhaar_file);
        Storage::disk('public')->assertMissing($staff->aadhaar_file);
    }

    /** photo_url is the guarded route now, never a public Storage URL (P2). */
    public function test_the_staff_photo_url_is_the_guarded_route(): void
    {
        $staff = $this->staff(['photo' => 'staff/1/photos/p.webp']);

        $this->assertSame(route('admin.files.show', ['staff', $staff->id, 'photo']), $staff->photo_url);

        $noPhoto = $this->staff(['photo' => null, 'mobile' => '9800000044']);
        $this->assertNull($noPhoto->photo_url);
    }

    /** Replacing a photo deletes the old private file and writes the new one. */
    public function test_replacing_a_photo_deletes_the_old_private_file(): void
    {
        Storage::disk('private')->put("staff/{$this->hostel->id}/photos/old.webp", 'OLD');
        $staff = $this->staff(['photo' => "staff/{$this->hostel->id}/photos/old.webp"]);

        $this->put(route('admin.staff.update', $staff), [
            'name' => $staff->name, 'mobile' => '9800000001',
            'aadhaar_number' => '123456789012', 'monthly_salary' => 13000, 'is_active' => '1',
            'photo' => UploadedFile::fake()->image('new.jpg'),
        ])->assertSessionHas('success');

        Storage::disk('private')->assertMissing("staff/{$this->hostel->id}/photos/old.webp");
        Storage::disk('private')->assertExists($staff->fresh()->photo);
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
     * saveAttendance loops the client's `marks` keys. Unvalidated, a crafted
     * request writes attendance rows against ANOTHER hostel's staff — junk data
     * at best, a unique-constraint 500 at worst.
     */
    public function test_attendance_ignores_staff_ids_from_another_hostel(): void
    {
        $mine = $this->staff();

        $other = Hostel::factory()->create();
        $theirs = Staff::create(['hostel_id' => $other->id, 'name' => 'Not Mine',
            'mobile' => '9700000001', 'monthly_salary' => 1000, 'is_active' => true]);

        $this->postJson(route('admin.staff.attendance.save'), [
            'date' => now()->toDateString(),
            'marks' => [$mine->id => 'present', $theirs->id => 'absent'],
        ])->assertOk();

        $this->assertDatabaseHas('staff_attendances', ['staff_id' => $mine->id, 'status' => 'present']);
        $this->assertDatabaseMissing('staff_attendances', ['staff_id' => $theirs->id]);
    }

    // ── W7.3: attendance ─────────────────────────────────────────────────

    /**
     * THE bug (F4). The old page rendered every unmarked person with "Present"
     * pre-selected and posted a status for ALL of them, so opening a past date
     * and pressing Save stamped the whole roster present for a day nobody had
     * reviewed — attendance you never took looked identical to attendance you
     * did. Now only what actually changed is ever sent, and a person with no
     * mark has no row.
     */
    public function test_only_the_marks_actually_sent_are_written(): void
    {
        $marked = $this->staff(['name' => 'Marked', 'mobile' => '9811111111']);
        $untouched = $this->staff(['name' => 'Untouched', 'mobile' => '9822222222']);

        $this->postJson(route('admin.staff.attendance.save'), [
            'date' => now()->toDateString(),
            'marks' => [$marked->id => 'absent'],
        ])->assertOk()->assertJson(['saved' => 1]);

        $this->assertDatabaseHas('staff_attendances', ['staff_id' => $marked->id, 'status' => 'absent']);
        // Nobody invented a "present" for the person nobody looked at.
        $this->assertDatabaseMissing('staff_attendances', ['staff_id' => $untouched->id]);
        $this->assertSame(1, StaffAttendance::count());
    }

    /** Auto-save means a mis-tap is already persisted — un-marking must work. */
    public function test_a_mark_can_be_cleared(): void
    {
        $staff = $this->staff();
        StaffAttendance::create(['hostel_id' => $this->hostel->id, 'staff_id' => $staff->id,
            'date' => now()->toDateString(), 'status' => 'present']);

        $this->postJson(route('admin.staff.attendance.save'), [
            'date' => now()->toDateString(),
            'marks' => [$staff->id => null],
        ])->assertOk()->assertJson(['cleared' => 1]);

        $this->assertSame(0, StaffAttendance::count());
    }

    public function test_re_marking_the_same_day_updates_rather_than_duplicates(): void
    {
        $staff = $this->staff();
        $date = now()->toDateString();

        $this->postJson(route('admin.staff.attendance.save'), ['date' => $date, 'marks' => [$staff->id => 'present']]);
        $this->postJson(route('admin.staff.attendance.save'), ['date' => $date, 'marks' => [$staff->id => 'leave']])->assertOk();

        $this->assertSame(1, StaffAttendance::count());
        $this->assertDatabaseHas('staff_attendances', ['staff_id' => $staff->id, 'status' => 'leave']);
    }

    /** You cannot know whether someone turned up tomorrow. */
    public function test_attendance_cannot_be_marked_for_a_future_date(): void
    {
        $staff = $this->staff();

        $this->postJson(route('admin.staff.attendance.save'), [
            'date' => now()->addDay()->toDateString(),
            'marks' => [$staff->id => 'present'],
        ])->assertStatus(422);

        $this->assertSame(0, StaffAttendance::count());
    }

    public function test_an_unknown_status_is_rejected(): void
    {
        $this->postJson(route('admin.staff.attendance.save'), [
            'date' => now()->toDateString(),
            'marks' => [$this->staff()->id => 'holiday'],
        ])->assertStatus(422);
    }

    /**
     * The strip ships the whole visible week so it can show which days were
     * MISSED without opening each one — and it must never offer a future day.
     */
    public function test_the_board_ships_the_visible_week_and_never_a_future_day(): void
    {
        $staff = $this->staff();
        StaffAttendance::create(['hostel_id' => $this->hostel->id, 'staff_id' => $staff->id,
            'date' => now()->subDays(2)->toDateString(), 'status' => 'present']);

        $board = $this->get(route('admin.staff.index', ['tab' => 'attendance']))->assertOk()->viewData('attendance');

        $this->assertCount(7, $board['strip']);
        $this->assertSame(now()->toDateString(), $board['date']);
        $this->assertTrue($board['at_today']);

        foreach ($board['strip'] as $day) {
            $this->assertLessThanOrEqual(now()->toDateString(), $day['date'], 'The strip must never offer a future day.');
        }

        // Every strip day is keyed even when empty: "loaded, nobody marked" and
        // "not loaded" must not look the same to the client.
        foreach ($board['strip'] as $day) {
            $this->assertArrayHasKey($day['date'], $board['marks']);
        }
        $this->assertSame('present', $board['marks'][now()->subDays(2)->toDateString()][(string) $staff->id]);
    }

    /** A future ?date is clamped, not obeyed — and never 500s on garbage. */
    public function test_the_board_clamps_a_future_or_invalid_date(): void
    {
        $this->staff();

        $future = $this->get(route('admin.staff.index', ['tab' => 'attendance', 'date' => now()->addYear()->toDateString()]))
            ->assertOk()->viewData('attendance');
        $this->assertSame(now()->toDateString(), $future['date']);

        $garbage = $this->get(route('admin.staff.index', ['tab' => 'attendance', 'date' => 'not-a-date']))
            ->assertOk()->viewData('attendance');
        $this->assertSame(now()->toDateString(), $garbage['date']);
    }

    /** Browsing history centres the day so its neighbours are one tap away. */
    public function test_browsing_history_centres_the_selected_day_in_the_strip(): void
    {
        $this->staff();
        $target = now()->subDays(20)->toDateString();

        $board = $this->get(route('admin.staff.index', ['tab' => 'attendance', 'date' => $target]))
            ->assertOk()->viewData('attendance');

        $this->assertSame($target, $board['date']);
        $this->assertFalse($board['at_today']);
        $this->assertContains($target, array_column($board['strip'], 'date'));
        // 3 days either side.
        $this->assertSame(now()->subDays(23)->toDateString(), $board['strip'][0]['date']);
        $this->assertSame(now()->subDays(17)->toDateString(), $board['strip'][6]['date']);
    }

    /** Only active staff take attendance — the roster is the same list the
     *  save endpoint validates against. */
    public function test_the_roster_holds_only_active_staff(): void
    {
        $this->staff(['name' => 'Working', 'mobile' => '9833333333']);
        $this->staff(['name' => 'Not Working', 'mobile' => '9844444444', 'is_active' => false]);

        $board = $this->get(route('admin.staff.index', ['tab' => 'attendance']))->assertOk()->viewData('attendance');

        $this->assertCount(1, $board['roster']);
        $this->assertSame('Working', $board['roster']->first()->name);
    }

    public function test_an_inactive_staff_member_cannot_be_marked(): void
    {
        $inactive = $this->staff(['is_active' => false]);

        $this->postJson(route('admin.staff.attendance.save'), [
            'date' => now()->toDateString(),
            'marks' => [$inactive->id => 'present'],
        ])->assertOk()->assertJson(['saved' => 0]);

        $this->assertSame(0, StaffAttendance::count());
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

    public function test_future_salary_months_are_rejected(): void
    {
        $this->post(route('admin.staff.salary', $this->staff()), [
            'salary_month' => now()->addMonth()->format('Y-m'), 'amount' => 12000,
            'paid_on' => now()->toDateString(), 'mode' => 'cash',
        ])->assertSessionHasErrors('salary_month');
    }

    // ── W7.2: reference numbers, duplicate warning, attendance summary ───

    /**
     * A cheque salary with no cheque number is a payment the record cannot
     * trace. The column and the fillable existed all along and nothing ever
     * collected it — Collect Payment has enforced this since W6.1.
     */
    public function test_modes_that_require_a_reference_refuse_to_pay_without_one(): void
    {
        $staff = $this->staff();

        $this->post(route('admin.staff.salary', $staff), [
            'salary_month' => now()->format('Y-m'), 'amount' => 12000,
            'paid_on' => now()->toDateString(), 'mode' => 'cheque',
        ])->assertSessionHasErrors('reference_number');
        $this->assertSame(0, StaffSalaryPayment::count());

        $this->post(route('admin.staff.salary', $staff), [
            'salary_month' => now()->format('Y-m'), 'amount' => 12000,
            'paid_on' => now()->toDateString(), 'mode' => 'cheque',
            'reference_number' => 'CHQ-889112',
        ])->assertSessionHas('success');

        // It reaches the salary row AND its expense mirror — a reference that
        // only lands on one of the two makes the pair contradict each other.
        $this->assertDatabaseHas('staff_salary_payments', ['reference_number' => 'CHQ-889112']);
        $this->assertDatabaseHas('expenses', ['reference_number' => 'CHQ-889112', 'category' => 'staff_salary']);
    }

    public function test_modes_that_do_not_require_a_reference_still_pay_without_one(): void
    {
        $this->post(route('admin.staff.salary', $this->staff()), [
            'salary_month' => now()->format('Y-m'), 'amount' => 12000,
            'paid_on' => now()->toDateString(), 'mode' => 'cash',
        ])->assertSessionHas('success');

        $this->assertSame(1, StaffSalaryPayment::count());
    }

    /**
     * A second payment for the same month is legitimate (advance, correction,
     * held-back balance) so it must never be blocked — but the sheet has to
     * SAY what's already recorded, keyed by month.
     */
    public function test_already_paid_totals_are_exposed_per_month_and_never_block(): void
    {
        $staff = $this->staff();
        $this->salary($staff, ['amount' => 5000, 'salary_month' => now()->startOfMonth()]);
        $this->salary($staff, ['amount' => 3000, 'salary_month' => now()->startOfMonth()]);
        $this->salary($staff, ['amount' => 9000, 'salary_month' => now()->subMonth()->startOfMonth()]);

        $payroll = $this->get(route('admin.staff.index'))->assertOk()->viewData('payroll');

        $this->assertEquals(8000.0, $payroll['paid'][$staff->id][now()->format('Y-m')]);
        $this->assertEquals(9000.0, $payroll['paid'][$staff->id][now()->subMonth()->format('Y-m')]);

        // Warned about, not prevented.
        $this->post(route('admin.staff.salary', $staff), [
            'salary_month' => now()->format('Y-m'), 'amount' => 4000,
            'paid_on' => now()->toDateString(), 'mode' => 'cash',
        ])->assertSessionHas('success');

        $this->assertSame(4, StaffSalaryPayment::count());
    }

    /**
     * The summary is INFORMATIONAL — it must report what was marked and never
     * derive an amount from it.
     */
    public function test_attendance_summary_is_exposed_per_month_within_the_window(): void
    {
        $staff = $this->staff();
        $month = now()->startOfMonth();

        foreach (['present', 'present', 'present', 'absent', 'half_day', 'leave'] as $i => $status) {
            StaffAttendance::create(['hostel_id' => $this->hostel->id, 'staff_id' => $staff->id,
                'date' => $month->copy()->addDays($i), 'status' => $status]);
        }

        $payroll = $this->get(route('admin.staff.index'))->assertOk()->viewData('payroll');
        $summary = $payroll['attendance'][$staff->id][now()->format('Y-m')];

        $this->assertSame(3, $summary['present']);
        $this->assertSame(1, $summary['absent']);
        $this->assertSame(1, $summary['half_day']);
        $this->assertSame(1, $summary['leave']);

        // The window is what lets the sheet tell "nobody marked it" apart from
        // "we didn't load it" — without it, silence and zero look identical.
        $this->assertContains(now()->format('Y-m'), $payroll['window']);
        $this->assertNotContains(now()->subMonths(6)->format('Y-m'), $payroll['window']);
    }

    public function test_payroll_meta_never_leaks_another_hostels_figures(): void
    {
        $mine = $this->staff();
        $this->salary($mine, ['amount' => 12000]);

        $other = Hostel::factory()->create();
        $theirs = Staff::create(['hostel_id' => $other->id, 'name' => 'Not Mine',
            'mobile' => '9700000002', 'monthly_salary' => 5000, 'is_active' => true]);
        StaffSalaryPayment::create(['hostel_id' => $other->id, 'staff_id' => $theirs->id,
            'salary_month' => now()->startOfMonth(), 'amount' => 5000, 'paid_on' => now(), 'mode' => 'cash']);

        $payroll = $this->get(route('admin.staff.index'))->assertOk()->viewData('payroll');

        $this->assertArrayHasKey($mine->id, $payroll['paid']);
        $this->assertArrayNotHasKey($theirs->id, $payroll['paid']);
    }

    public function test_deleting_a_salary_takes_its_expense_mirror_with_it(): void
    {
        $staff = $this->staff();
        $this->post(route('admin.staff.salary', $staff), [
            'salary_month' => now()->format('Y-m'), 'amount' => 12000,
            'paid_on' => now()->toDateString(), 'mode' => 'cash',
        ]);

        $payment = StaffSalaryPayment::firstOrFail();
        $this->delete(route('admin.staff.salary.destroy', [$staff, $payment]))
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

        $this->get(route('admin.staff.show', $staff))->assertOk()->assertSee('Govind');

        $this->delete(route('admin.staff.salary.destroy', [$staff, $payment]))
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

        $this->post(route('admin.staff.restore', $staff))->assertSessionHas('success');
        $this->assertNotSoftDeleted('staff', ['id' => $staff->id]);
        $this->assertCount(1, $this->get(route('admin.staff.index'))->viewData('staff'));
    }

    /** A removed member is off the books — no new money may be paid to them. */
    public function test_a_removed_staff_member_cannot_be_paid(): void
    {
        $staff = $this->staff();
        $this->delete(route('admin.staff.destroy', $staff));

        $this->post(route('admin.staff.salary', $staff), [
            'salary_month' => now()->format('Y-m'), 'amount' => 12000,
            'paid_on' => now()->toDateString(), 'mode' => 'cash',
        ])->assertNotFound();

        $this->assertSame(0, StaffSalaryPayment::count());
    }

    // ── Public-ID hardening (U1): opaque ULID route key ───────────────────

    public function test_staff_get_a_public_id_and_the_profile_url_uses_it(): void
    {
        $staff = $this->staff();

        $this->assertSame(26, strlen($staff->public_id));
        $this->assertSame($staff->public_id, $staff->getRouteKey());

        $url = route('admin.staff.show', $staff);
        $this->assertStringEndsWith('/'.$staff->public_id, $url);

        // Opaque URL resolves; the old sequential integer no longer does.
        $this->get($url)->assertOk();
        $this->get('/admin/staff/'.$staff->id)->assertNotFound();
    }

    /**
     * The whereNumber('staff') landmine: restore/aadhaar used to constrain the
     * route param to digits, which would 404 an alphanumeric ULID. Removed in
     * U1 — a trashed staff still restores by its opaque id.
     */
    public function test_a_trashed_staff_restores_via_its_opaque_id(): void
    {
        $staff = $this->staff();
        $this->delete(route('admin.staff.destroy', $staff));
        $this->assertSoftDeleted('staff', ['id' => $staff->id]);

        // The URL carries the ULID, not a number — and it still works.
        $restoreUrl = route('admin.staff.restore', $staff);
        $this->assertStringContainsString($staff->public_id, $restoreUrl);

        $this->post($restoreUrl)->assertSessionHas('success');
        $this->assertNotSoftDeleted('staff', ['id' => $staff->id]);
    }
}
