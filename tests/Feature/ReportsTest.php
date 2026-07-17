<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Hostel;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use App\Services\ReportService;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * W8 rebuild. The tests that matter most are the MONEY ones: the legacy
 * service summed every payment row, so 'credit' (re-applied money, already
 * income once) and 'credit_note' (a refund liability) inflated every report.
 */
class ReportsTest extends TestCase
{
    use RefreshDatabase;

    protected Hostel $hostel;
    protected Student $student;
    protected ReportService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hostel = Hostel::factory()->create();
        $admin = User::factory()->create(['hostel_id' => $this->hostel->id, 'role' => 'hostel_admin']);
        Tenant::set($this->hostel->id);
        $this->actingAs($admin);

        $this->student = Student::create(['hostel_id' => $this->hostel->id, 'name' => 'Amit',
            'mobile' => '9800000001', 'occupation_type' => 'student', 'status' => 'active']);
        $this->svc = app(ReportService::class);
    }

    protected function pay(float $amount, string $mode = 'cash', $on = null): Payment
    {
        return Payment::create(['hostel_id' => $this->hostel->id, 'student_id' => $this->student->id,
            'amount' => $amount, 'mode' => $mode, 'paid_on' => $on ?? now(),
            'receipt_number' => 'R-'.uniqid()]);
    }

    /** THE fix: credit / credit_note rows must not count as income anywhere. */
    public function test_credit_and_credit_note_rows_are_not_income(): void
    {
        $this->pay(5000, 'cash');
        $this->pay(2000, 'upi');
        $this->pay(1500, 'credit');       // re-applied balance — counted once already
        $this->pay(800, 'credit_note');   // refund owed back — a liability

        $collection = $this->svc->collection('monthly', now()->startOfMonth(), now());
        $this->assertEquals(7000.0, $collection['total']);

        $byMode = $this->svc->incomeByMode(now()->startOfMonth(), now());
        $this->assertEquals(7000.0, $byMode['total']);
        $this->assertNotContains('credit', collect($byMode['rows'])->pluck(0)->map(fn ($m) => strtolower($m))->all());

        $pnl = $this->svc->profitLoss(now()->startOfMonth(), now());
        $this->assertEquals(7000.0, $pnl['rows'][0][1], 'P&L income must use the same income scope.');
    }

    public function test_profit_and_loss_nets_income_against_expenses_month_by_month(): void
    {
        $this->pay(10000, 'cash', now()->subMonthNoOverflow());
        $this->pay(4000, 'cash', now());
        Expense::create(['hostel_id' => $this->hostel->id, 'category' => 'maintenance', 'title' => 'Fix',
            'amount' => 2500, 'expense_date' => now(), 'mode' => 'cash', 'recorded_by' => 1]);

        $pnl = $this->svc->profitLoss(now()->subMonthNoOverflow()->startOfMonth(), now());

        $this->assertCount(2, $pnl['rows']); // every month appears, even quiet ones
        [$m1, $m2] = $pnl['rows'];
        $this->assertEquals([10000.0, 0.0, 10000.0], array_slice($m1, 1));
        $this->assertEquals([4000.0, 2500.0, 1500.0], array_slice($m2, 1));
        $this->assertEquals(11500.0, $pnl['total']);
    }

    public function test_dues_aging_buckets_by_days_overdue(): void
    {
        $mk = fn ($balanceDue, $due) => Invoice::create([
            'hostel_id' => $this->hostel->id, 'student_id' => $this->student->id,
            'type' => 'other', 'title' => 'Due', 'amount' => $balanceDue,
            'status' => 'pending', 'due_date' => $due,
        ]);
        $mk(1000, now()->addDays(5));    // not yet due → current
        $mk(2000, now()->subDays(10));   // 1–30
        $mk(3000, now()->subDays(45));   // 31–60
        $mk(4000, now()->subDays(120));  // 60+

        $dues = $this->svc->duesAging();

        $this->assertEquals(10000.0, $dues['total']);
        // chart series carries the buckets in order: current, ≤30, ≤60, 60+
        $this->assertEquals([1000.0, 2000.0, 3000.0, 4000.0], $dues['chart']['series'][0][1]);
        // one student row aggregating all four, oldest due leading
        $this->assertCount(1, $dues['rows']);
        $this->assertEquals(10000.0, $dues['rows'][0][4]);
        $this->assertSame(120, $dues['rows'][0][3]);
    }

    public function test_dues_pagination_pages_but_exports_carry_every_row(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $s = Student::create(['hostel_id' => $this->hostel->id, 'name' => "S{$i}",
                'mobile' => '98000001'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'occupation_type' => 'student', 'status' => 'active']);
            Invoice::create(['hostel_id' => $this->hostel->id, 'student_id' => $s->id,
                'type' => 'other', 'title' => 'Due', 'amount' => 100, 'status' => 'pending']);
        }

        $page = $this->svc->duesAging(page: 1, perPage: 15);
        $this->assertCount(15, $page['rows']);
        $this->assertSame(20, $page['paginator']->total());

        $all = $this->svc->duesAging(all: true);
        $this->assertCount(20, $all['rows']);
        $this->assertNull($all['paginator']);
    }

    public function test_expenses_report_groups_by_category_or_month(): void
    {
        Expense::create(['hostel_id' => $this->hostel->id, 'category' => 'electricity', 'title' => 'Bill',
            'amount' => 900, 'expense_date' => now(), 'mode' => 'cash', 'recorded_by' => 1]);
        Expense::create(['hostel_id' => $this->hostel->id, 'category' => 'electricity', 'title' => 'Bill 2',
            'amount' => 100, 'expense_date' => now()->subMonthNoOverflow(), 'mode' => 'cash', 'recorded_by' => 1]);

        $from = now()->subMonthNoOverflow()->startOfMonth();
        $byCat = $this->svc->expenses('category', $from, now());
        $this->assertCount(1, $byCat['rows']);          // one category
        $this->assertEquals(1000.0, $byCat['total']);

        $byMonth = $this->svc->expenses('month', $from, now());
        $this->assertCount(2, $byMonth['rows']);        // two months
        $this->assertEquals(1000.0, $byMonth['total']);
    }

    // ── HTTP layer ──

    public function test_the_hub_renders_with_live_stats(): void
    {
        $this->pay(5000, 'cash');
        $this->pay(1000, 'credit'); // must not appear in the hub stat either

        $this->get(route('admin.reports.index'))
            ->assertOk()
            ->assertSee('Profit &amp; Loss', false)
            ->assertSee(hostelease_money(5000).' this month');
    }

    public function test_every_report_type_renders_and_unknown_404s(): void
    {
        foreach (array_keys(\App\Http\Controllers\Admin\ReportController::TYPES) as $type) {
            $this->get(route('admin.reports.show', $type))->assertOk();
        }
        $this->get(route('admin.reports.show', 'made-up'))->assertNotFound();
    }

    public function test_garbage_dates_and_swapped_bounds_do_not_500(): void
    {
        $this->get(route('admin.reports.show', ['type' => 'collection', 'from' => 'not-a-date']))->assertOk();
        $this->get(route('admin.reports.show', ['type' => 'pnl',
            'from' => now()->toDateString(), 'to' => now()->subMonth()->toDateString()]))->assertOk();
    }

    public function test_excel_export_downloads(): void
    {
        $this->pay(5000);

        $this->get(route('admin.reports.show', ['type' => 'collection', 'export' => 'excel']))
            ->assertOk()
            ->assertDownload('collection-report.xlsx');
    }

    public function test_reports_are_tenant_scoped(): void
    {
        $this->pay(5000, 'cash');

        $other = Hostel::factory()->create();
        $otherAdmin = User::factory()->create(['hostel_id' => $other->id, 'role' => 'hostel_admin']);
        Tenant::set($other->id);

        $data = app(ReportService::class)->collection('monthly', now()->startOfMonth(), now());
        $this->assertEquals(0.0, $data['total'], "Another hostel's payments leaked into the report.");
    }
}
