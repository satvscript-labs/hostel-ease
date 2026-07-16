<?php

namespace Tests\Feature;

use App\Models\AcBill;
use App\Models\BedAssignment;
use App\Models\Floor;
use App\Models\Hostel;
use App\Models\Invoice;
use App\Models\Room;
use App\Models\Student;
use App\Models\User;
use App\Services\BedAssignmentService;
use App\Services\BedGenerator;
use App\Services\ReportService;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rewritten in W6.3 with the module: AC bills now split DAY-WISE from
 * bed-assignment history (the day-ledger — see AcBillSplitService),
 * remainder-correct; duplicate months are guarded; bills are editable with a
 * paid-money guard; generation is multi-month with chained readings; the
 * modal previews through the same service store() uses.
 */
class AcBillTest extends TestCase
{
    use RefreshDatabase;

    protected Hostel $hostel;
    protected User $admin;
    protected Room $room;
    protected Floor $floor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hostel = Hostel::factory()->create();
        $this->admin = User::factory()->create(['hostel_id' => $this->hostel->id, 'role' => 'hostel_admin']);
        Tenant::set($this->hostel->id);

        $this->floor = Floor::create(['hostel_id' => $this->hostel->id, 'name' => 'GF']);
        $this->room = Room::create(['hostel_id' => $this->hostel->id, 'floor_id' => $this->floor->id,
            'room_number' => '301', 'room_type' => 'ac', 'sharing' => 3, 'rent' => 6000]);
        app(BedGenerator::class)->sync($this->room);

        // Occupy all three beds, backdated far enough that any recent billing
        // month is FULLY occupied by all three (the day-ledger reads history).
        foreach ($this->room->beds as $i => $bed) {
            $s = Student::create(['hostel_id' => $this->hostel->id, 'name' => "S{$i}",
                'mobile' => (string) (9000000000 + $i), 'occupation_type' => 'working', 'status' => 'active']);
            app(BedAssignmentService::class)->assign($s, $bed, []);
        }
        BedAssignment::query()->update(['join_date' => now()->subMonths(6)->toDateString()]);

        $this->actingAs($this->admin);
    }

    /**
     * A fresh AC room with hand-written occupancy history. Each stay:
     * [name, join, leave, joinMeterReading?, leaveMeterReading?] — the
     * readings are the W6.3 anchors that make the split metered-exact.
     */
    protected function roomWithHistory(array $stays): Room
    {
        $room = Room::create(['hostel_id' => $this->hostel->id, 'floor_id' => $this->floor->id,
            'room_number' => '30'.rand(2, 9).rand(0, 9), 'room_type' => 'ac', 'sharing' => 4, 'rent' => 6000]);
        app(BedGenerator::class)->sync($room);

        foreach ($stays as $i => $stay) {
            [$name, $join, $leave] = $stay;
            $s = Student::create(['hostel_id' => $this->hostel->id, 'name' => $name,
                'mobile' => (string) (9100000000 + rand(1000, 999999)), 'occupation_type' => 'student',
                'status' => $leave ? 'left' : 'active']);
            BedAssignment::create([
                'hostel_id' => $this->hostel->id,
                'bed_id' => $room->beds[$i]->id,
                'student_id' => $s->id,
                'join_date' => $join,
                'leave_date' => $leave,
                'join_meter_reading' => $stay[3] ?? null,
                'leave_meter_reading' => $stay[4] ?? null,
                'is_active' => $leave === null,
            ]);
        }

        return $room;
    }

    protected function generate(Room $room, array $months, array $readings, float $prev = 0, float $rate = 10)
    {
        return $this->post(route('admin.ac-bills.store'), [
            'room_id' => $room->id,
            'unit_price' => $rate,
            'prev_reading' => $prev,
            'months' => $months,
            'readings' => $readings,
        ]);
    }

    public function test_full_month_split_is_equal_and_remainder_correct(): void
    {
        $month = now()->subMonth()->format('Y-m');

        // 100 units × 10 = 1000 across three full-month occupants.
        $this->generate($this->room, [$month], [100])->assertRedirect()->assertSessionHas('success');

        $bill = AcBill::firstOrFail();
        $this->assertEquals(1000.0, (float) $bill->total_amount);
        $this->assertNotNull($bill->split_breakdown);

        $invoices = Invoice::where('ac_bill_id', $bill->id)->get();
        $this->assertCount(3, $invoices);
        $this->assertTrue($invoices->every(fn ($i) => $i->type === 'ac' && $i->due_date !== null));
        // Exactly the total — the old flat round() under/over-billed by paise
        // (and W6.1's fine split falsely cited it as remainder-correct).
        $this->assertEquals(1000.0, (float) $invoices->sum('amount'));
        $this->assertEqualsCanonicalizing([333.34, 333.33, 333.33], $invoices->pluck('amount')->map(fn ($a) => (float) $a)->all());
    }

    /** The owner's scenario: joined mid-month, must pay only their days. */
    public function test_mid_month_joiner_pays_only_their_days(): void
    {
        // June 2026 has 30 days. A: all June. B: joined 16 Jun (15 days).
        // Day-ledger: days 1–15 A alone (weight 15); days 16–30 shared
        // (7.5 each). A = 22.5/30 → ₹2,250 of ₹3,000; B = 7.5/30 → ₹750.
        $room = $this->roomWithHistory([
            ['Amit', '2026-05-01', null],
            ['Bala', '2026-06-16', null],
        ]);

        $this->generate($room, ['2026-06'], [300])->assertSessionHas('success');

        $bill = AcBill::where('room_id', $room->id)->firstOrFail();
        $this->assertEquals(3000.0, (float) $bill->total_amount);

        $shares = Invoice::where('ac_bill_id', $bill->id)->with('student')->get()
            ->mapWithKeys(fn ($i) => [$i->student->name => (float) $i->amount]);
        $this->assertEquals(2250.0, $shares['Amit']);
        $this->assertEquals(750.0, $shares['Bala']);

        // The stored explanation carries the story.
        $b = collect($bill->split_breakdown['students'])->keyBy('name');
        $this->assertSame(30, $b['Amit']['days']);
        $this->assertSame(15, $b['Bala']['days']);
        $this->assertTrue($b['Bala']['joined_mid']);
        $this->assertStringContainsString('15 of 30 days', Invoice::where('ac_bill_id', $bill->id)->whereRelation('student', 'name', 'Bala')->value('title'));
    }

    /** Owner decision: a student who LEFT mid-month still bears their days. */
    public function test_departed_student_is_still_billed_their_days(): void
    {
        // A: 1–14 Jun then left (14 days). B: all June.
        // Days 1–14 shared (7 each); 15–30 B alone (16). A=7/30, B=23/30.
        $room = $this->roomWithHistory([
            ['Gone', '2026-05-01', '2026-06-14'],
            ['Here', '2026-05-01', null],
        ]);

        $this->generate($room, ['2026-06'], [300])->assertSessionHas('success');

        $bill = AcBill::where('room_id', $room->id)->firstOrFail();
        $shares = Invoice::where('ac_bill_id', $bill->id)->with('student')->get()
            ->mapWithKeys(fn ($i) => [$i->student->name => (float) $i->amount]);

        $this->assertEquals(700.0, $shares['Gone']);
        $this->assertEquals(2300.0, $shares['Here']);
        $this->assertTrue(collect($bill->split_breakdown['students'])->firstWhere('name', 'Gone')['left']);
    }

    /** Owner's market rule: occupants bear the FULL meter — empty days spread. */
    public function test_empty_days_cost_spreads_across_occupants(): void
    {
        // Room empty 1–10 Jun; C occupies 11–30. C bears the whole ₹3,000.
        $room = $this->roomWithHistory([
            ['Solo', '2026-06-11', null],
        ]);

        $this->generate($room, ['2026-06'], [300])->assertSessionHas('success');

        $bill = AcBill::where('room_id', $room->id)->firstOrFail();
        $invoice = Invoice::where('ac_bill_id', $bill->id)->firstOrFail();

        $this->assertEquals(3000.0, (float) $invoice->amount); // full recovery, never the hostel
        $this->assertSame(10, $bill->split_breakdown['empty_days']);
        $this->assertNotNull($bill->split_breakdown['note']);
    }

    public function test_duplicate_month_is_blocked_with_a_sentence(): void
    {
        $month = now()->subMonth()->format('Y-m');
        $this->generate($this->room, [$month], [100]);

        $this->generate($this->room, [$month], [150], prev: 100)
            ->assertSessionHas('error');

        $this->assertSame(1, AcBill::count());
    }

    public function test_empty_month_is_blocked_with_a_sentence(): void
    {
        $room = $this->roomWithHistory([]); // no occupancy at all

        $this->generate($room, ['2026-06'], [300])->assertSessionHas('error');
        $this->assertSame(0, AcBill::where('room_id', $room->id)->count());
    }

    public function test_multi_month_generation_chains_the_readings(): void
    {
        $m1 = now()->subMonths(2)->format('Y-m');
        $m2 = now()->subMonth()->format('Y-m');

        // Start 1000 → 1100 (m1: 100u = ₹1,000) → 1250 (m2: 150u = ₹1,500).
        $this->generate($this->room, [$m1, $m2], [1100, 1250], prev: 1000)
            ->assertSessionHas('success');

        $this->assertSame(2, AcBill::count());
        // whereDate: the date cast stores a full datetime on SQLite.
        $b1 = AcBill::whereDate('bill_month', $m1.'-01')->firstOrFail();
        $b2 = AcBill::whereDate('bill_month', $m2.'-01')->firstOrFail();

        $this->assertEquals([1000.0, 1100.0], [(float) $b1->previous_reading, (float) $b1->current_reading]);
        $this->assertEquals([1100.0, 1250.0], [(float) $b2->previous_reading, (float) $b2->current_reading]);
        $this->assertEquals(1000.0, (float) Invoice::where('ac_bill_id', $b1->id)->sum('amount'));
        $this->assertEquals(1500.0, (float) Invoice::where('ac_bill_id', $b2->id)->sum('amount'));
    }

    public function test_decreasing_chained_readings_are_rejected(): void
    {
        $m1 = now()->subMonths(2)->format('Y-m');
        $m2 = now()->subMonth()->format('Y-m');

        // m2's reading below m1's — meters only go up.
        $this->generate($this->room, [$m1, $m2], [1100, 1050], prev: 1000)->assertStatus(422);
        $this->assertSame(0, AcBill::count());
    }

    public function test_edit_recomputes_shares_and_updates_invoices(): void
    {
        $month = now()->subMonth()->format('Y-m');
        $this->generate($this->room, [$month], [100]); // ₹1,000
        $bill = AcBill::firstOrFail();

        // Meter typo: it was actually 120 units.
        $this->patch(route('admin.ac-bills.update', $bill), [
            'previous_reading' => 0, 'current_reading' => 120, 'unit_price' => 10,
        ])->assertSessionHas('success');

        $bill->refresh();
        $this->assertEquals(1200.0, (float) $bill->total_amount);
        $this->assertEquals(1200.0, (float) $bill->invoices()->sum('amount'));
    }

    /** Owner's rule: an edit may never drop a share below money already paid. */
    public function test_edit_refuses_to_drop_a_share_below_paid_money(): void
    {
        $month = now()->subMonth()->format('Y-m');
        $this->generate($this->room, [$month], [100]); // shares ≈ ₹333
        $bill = AcBill::firstOrFail();

        $paidInvoice = $bill->invoices()->first();
        $paidInvoice->update(['paid_amount' => 300, 'status' => 'partial']);

        // Shrinking to 60 units (₹600) puts every share at ₹200 < ₹300 paid.
        $this->patch(route('admin.ac-bills.update', $bill), [
            'previous_reading' => 0, 'current_reading' => 60, 'unit_price' => 10,
        ])->assertSessionHas('error');

        $this->assertEquals(1000.0, (float) $bill->fresh()->total_amount); // untouched
    }

    public function test_preview_returns_the_same_shares_store_will_invoice(): void
    {
        $month = now()->subMonth()->format('Y-m');
        $payload = [
            'room_id' => $this->room->id, 'unit_price' => 10,
            'prev_reading' => 0, 'months' => [$month], 'readings' => [100],
        ];

        $preview = $this->postJson(route('admin.ac-bills.preview'), $payload)->assertOk()->json();
        $previewShares = collect($preview['months'][0]['students'])->pluck('share')->sort()->values();

        $this->post(route('admin.ac-bills.store'), $payload);
        $invoiced = Invoice::where('type', 'ac')->pluck('amount')->map(fn ($a) => (float) $a)->sort()->values();

        $this->assertEquals($previewShares->all(), $invoiced->all());
        $this->assertFalse($preview['months'][0]['already_billed']);
    }

    public function test_unit_rate_saves_as_the_hostel_default(): void
    {
        $this->patchJson(route('admin.ac-bills.unit-rate'), ['unit_price' => 9.5])->assertOk();

        $this->assertEquals(9.5, Hostel::find($this->hostel->id)->settings['ac_unit_price']);
        $this->assertEquals(9.5, $this->get(route('admin.ac-bills.index'))->viewData('defaultUnitPrice'));
    }

    public function test_garbage_month_filter_falls_back_instead_of_crashing(): void
    {
        $this->get(route('admin.ac-bills.index', ['month' => 'garbage']))->assertOk();
    }

    public function test_ac_report_is_reachable_and_reconciles_exactly(): void
    {
        $month = now()->subMonth()->format('Y-m');
        $this->generate($this->room, [$month], [90]); // ₹900

        // Surfaced in W6.3 — it existed since day one but no UI could reach it.
        $this->get(route('admin.reports.show', ['type' => 'ac', 'from' => $month.'-01', 'to' => now()->toDateString()]))
            ->assertOk();

        $data = app(ReportService::class)->acReport(now()->subMonth()->startOfMonth(), now()->endOfMonth());
        // Remainder-correct split: the invoice sum IS the bill total now —
        // the page and the report finally agree to the paisa.
        $this->assertEquals(900.0, $data['rows'][0][3]);
    }

    /**
     * The owner's exact scenario (W6.3 metered split): usage is NOT uniform —
     * 30 units burned before the mid-month join, 10 after. The joiner splits
     * only the 10 they were present for, because the meter reading recorded
     * at their move-in (50) anchors the segment.
     */
    public function test_metered_join_splits_on_real_consumption_not_days(): void
    {
        $room = $this->roomWithHistory([
            ['Amit', '2026-05-01', null],
            ['Bala', '2026-05-01', null],
            ['Chetan', '2026-06-15', null, 50.0], // join reading = 50
        ]);

        // June: meter 20 → 60 (40 units × ₹10 = ₹400).
        $this->generate($room, ['2026-06'], [60], prev: 20)->assertSessionHas('success');

        $bill = AcBill::where('room_id', $room->id)->firstOrFail();
        $shares = Invoice::where('ac_bill_id', $bill->id)->with('student')->get()
            ->mapWithKeys(fn ($i) => [$i->student->name => (float) $i->amount]);

        // Segment 1 (20→50, ₹300): Amit+Bala → ₹150 each.
        // Segment 2 (50→60, ₹100): all three → ₹33.33 each. Remainder → largest.
        $this->assertEquals(33.33, $shares['Chetan']);
        $this->assertEqualsCanonicalizing([183.34, 183.33, 33.33], array_values($shares->all()));
        $this->assertEquals(400.0, (float) Invoice::where('ac_bill_id', $bill->id)->sum('amount'));

        // The breakdown carries the proof: two segments, both metered.
        $segments = $bill->split_breakdown['segments'];
        $this->assertCount(2, $segments);
        $this->assertEquals([30.0, 10.0], [(float) $segments[0]['units'], (float) $segments[1]['units']]);
        $this->assertTrue(collect($segments)->every(fn ($s) => $s['metered'] && ! $s['estimated']));
    }

    /** The mirror case: the move-OUT reading caps what the leaver bears. */
    public function test_metered_leave_caps_the_departed_students_share(): void
    {
        $room = $this->roomWithHistory([
            ['Gone', '2026-05-01', '2026-06-14', null, 50.0], // leave reading = 50
            ['Here', '2026-05-01', null],
        ]);

        // June: 20 → 60 (₹400). Segment 1 (20→50, ₹300) shared → 150 each;
        // segment 2 (50→60, ₹100) Here alone.
        $this->generate($room, ['2026-06'], [60], prev: 20)->assertSessionHas('success');

        $shares = Invoice::where('ac_bill_id', AcBill::where('room_id', $room->id)->value('id'))
            ->with('student')->get()->mapWithKeys(fn ($i) => [$i->student->name => (float) $i->amount]);

        $this->assertEquals(150.0, $shares['Gone']);
        $this->assertEquals(250.0, $shares['Here']);
    }

    /** A reading that contradicts the meter is ignored OUT LOUD, never obeyed. */
    public function test_inconsistent_event_reading_falls_back_to_days_with_a_note(): void
    {
        $room = $this->roomWithHistory([
            ['Amit', '2026-05-01', null],
            ['Bala', '2026-05-01', null],
            ['Chetan', '2026-06-15', null, 90.0], // impossible: bill ends at 60
        ]);

        $this->generate($room, ['2026-06'], [60], prev: 20)->assertSessionHas('success');

        $bill = AcBill::where('room_id', $room->id)->firstOrFail();
        $shares = Invoice::where('ac_bill_id', $bill->id)->with('student')->get()
            ->mapWithKeys(fn ($i) => [$i->student->name => (float) $i->amount]);

        // Still remainder-correct, but Chetan's share is the DAY estimate
        // (≈₹71), not the metered ₹33.33 — and the breakdown says why.
        $this->assertEquals(400.0, (float) Invoice::where('ac_bill_id', $bill->id)->sum('amount'));
        $this->assertGreaterThan(33.34, $shares['Chetan']);
        $this->assertStringContainsString('ignored', $bill->split_breakdown['note']);
    }

    /** A transfer touches TWO meters: out of the old room, into the new. */
    public function test_transfer_records_both_meters(): void
    {
        $assignment = BedAssignment::where('is_active', true)->firstOrFail();
        $targetRoom = $this->roomWithHistory([]); // empty AC room
        $targetBed = $targetRoom->beds->first();

        app(BedAssignmentService::class)->transfer($assignment, $targetBed, [
            'join_date' => now()->toDateString(),
            'old_meter_reading' => 500.0,
            'meter_reading' => 120.0,
        ]);

        $this->assertEquals(500.0, (float) $assignment->fresh()->leave_meter_reading);
        $new = BedAssignment::where('bed_id', $targetBed->id)->where('is_active', true)->firstOrFail();
        $this->assertEquals(120.0, (float) $new->join_meter_reading);
    }

    /** Owner decision: an AC move without its meter reading does not happen. */
    public function test_assigning_to_an_ac_room_requires_the_meter_reading(): void
    {
        $room = $this->roomWithHistory([]);
        $bed = $room->beds->first();
        $student = Student::create(['hostel_id' => $this->hostel->id, 'name' => 'New Kid',
            'mobile' => '9111111111', 'occupation_type' => 'student', 'status' => 'active',
            'fee_frequency' => 'monthly', 'fee_amount' => 5000]);

        // The plan is confirmed on every move (W6.4) — sent here so the only
        // thing under test is the meter requirement.
        $plan = ['fee_amount' => 5000, 'fee_frequency' => 'monthly'];

        $this->post(route('admin.property.assign'), [
            'student_id' => $student->id, 'bed_id' => $bed->id,
        ] + $plan)->assertSessionHasErrors('meter_reading');
        $this->assertSame(0, BedAssignment::where('bed_id', $bed->id)->count());

        $this->post(route('admin.property.assign'), [
            'student_id' => $student->id, 'bed_id' => $bed->id, 'meter_reading' => 42.5,
        ] + $plan)->assertSessionHasNoErrors();
        $this->assertEquals(42.5, (float) BedAssignment::where('bed_id', $bed->id)->value('join_meter_reading'));
    }

    public function test_deleting_a_bill_soft_deletes_it_with_its_invoices(): void
    {
        $month = now()->subMonth()->format('Y-m');
        $this->generate($this->room, [$month], [100]);
        $bill = AcBill::firstOrFail();

        $this->delete(route('admin.ac-bills.destroy', $bill))->assertSessionHas('success');

        $this->assertSoftDeleted('ac_bills', ['id' => $bill->id]);
        $this->assertSame(0, Invoice::where('ac_bill_id', $bill->id)->count());
    }
}
