<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcBill;
use App\Models\Invoice;
use App\Models\Room;
use App\Services\AcBillSplitService;
use App\Services\ActivityLogger;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AcBillController extends Controller
{
    public function __construct(
        protected AcBillSplitService $splitter,
        protected ActivityLogger $logger,
    ) {
    }

    public function index(Request $request): View
    {
        // rescue(): ?month=garbage used to throw straight out of Carbon::parse
        // and 500 the page (same crash class fixed on Front Desk + Expenses).
        $filterMonth = rescue(fn () => $request->filled('month') ? Carbon::parse($request->input('month'))->startOfMonth() : null, null, false)
            ?? now()->startOfMonth();
        $filterFloor = $request->input('floor');

        $from = $filterMonth->copy()->startOfMonth();
        $to = $filterMonth->copy()->endOfMonth();

        $billsQuery = AcBill::with(['room.floor', 'invoices.student'])
            ->withSum('invoices as collected', 'paid_amount')
            ->whereBetween('bill_month', [$from->toDateString(), $to->toDateString()])
            ->when($filterFloor, fn ($q) => $q->whereHas('room', fn ($r) => $r->where('floor_id', $filterFloor)));

        $bills = $billsQuery->orderByDesc('bill_month')->orderByDesc('id')
            ->paginate(12)->withQueryString();

        $bills->getCollection()->each(fn ($bill) => $bill->collected = (float) ($bill->collected ?? 0));

        // Tiles summarize the WHOLE filter window, never the current page.
        $windowBills = AcBill::whereBetween('bill_month', [$from->toDateString(), $to->toDateString()])
            ->when($filterFloor, fn ($q) => $q->whereHas('room', fn ($r) => $r->where('floor_id', $filterFloor)))
            ->withSum('invoices as collected', 'paid_amount')
            ->get(['id', 'total_amount']);

        $summary = [
            'billed' => (float) $windowBills->sum('total_amount'),
            'collected' => (float) $windowBills->sum(fn ($b) => (float) ($b->collected ?? 0)),
        ];
        $summary['due'] = $summary['billed'] - $summary['collected'];

        // ALL AC rooms — not just currently-occupied ones. The split is
        // history-based (W6.3): a room that emptied last week can still be
        // billed for last month, when it was occupied. The picker payload
        // carries what the owner needs to choose: floor, occupants today,
        // last recorded reading and last billed month (the chaining anchors).
        $lastBills = AcBill::whereIn('id', function ($q) {
            $q->selectRaw('MAX(id)')->from('ac_bills')->whereNull('deleted_at')->groupBy('room_id');
        })->get()->keyBy('room_id');

        $pickerRooms = Room::where('room_type', 'ac')
            ->with(['floor', 'beds.activeAssignment.student'])
            ->orderBy('room_number')
            ->get()
            ->map(function (Room $room) use ($lastBills) {
                $occupants = $room->beds->map(fn ($b) => $b->activeAssignment?->student?->name)->filter()->values();
                $last = $lastBills->get($room->id);

                return [
                    'id' => $room->id,
                    'number' => (string) $room->room_number,
                    'floor' => $room->floor?->name,
                    'occupants' => $occupants->all(),
                    'last_reading' => $last !== null ? (float) $last->current_reading : 0.0,
                    'last_billed_month' => $last?->bill_month->format('Y-m'),
                    'last_billed_label' => $last?->bill_month->format('M Y'),
                ];
            })->values();

        $floors = \App\Models\Floor::ordered()->get();

        // Per-hostel saved rate (W6.3: inline-saveable from the modal), falling
        // back to the last bill's rate, then a sane default.
        $hostel = \App\Models\Hostel::find(Tenant::id());
        $defaultUnitPrice = (float) ($hostel->settings['ac_unit_price']
            ?? AcBill::latest('id')->value('unit_price')
            ?? 12.0);

        return view('admin.ac_bills.index', compact(
            'bills', 'summary', 'filterMonth', 'filterFloor', 'floors', 'pickerRooms', 'defaultUnitPrice'
        ));
    }

    /**
     * The modal's live summary (W6.3). Same service as store() — the shares
     * the owner reads before generating ARE the shares that get invoiced.
     * Accepts the chained multi-month payload and answers with per-month
     * units, amounts, per-student prorated shares, duplicate-month flags and
     * unbilled-gap warnings.
     */
    public function preview(Request $request): JsonResponse
    {
        $data = $this->validateBillInput($request);
        $room = Room::findOrFail($data['room_id']);

        return response()->json([
            'months' => $this->computeMonths($room, $data),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateBillInput($request);
        $room = Room::with('beds.activeAssignment.student')->findOrFail($data['room_id']);

        $months = $this->computeMonths($room, $data);

        // All-or-nothing, with sentences instead of surprises.
        $blocked = collect($months)->filter(fn ($m) => $m['already_billed'])->pluck('label');
        if ($blocked->isNotEmpty()) {
            return back()->with('error', __('Already billed for :months — edit the existing bill instead of generating a duplicate.', ['months' => $blocked->implode(', ')]));
        }
        $empty = collect($months)->filter(fn ($m) => $m['students'] === [])->pluck('label');
        if ($empty->isNotEmpty()) {
            return back()->with('error', __('No one occupied this room during :months — there is nobody to bill.', ['months' => $empty->implode(', ')]));
        }

        DB::transaction(function () use ($months, $room) {
            foreach ($months as $m) {
                $bill = AcBill::create([
                    'hostel_id' => $room->hostel_id,
                    'room_id' => $room->id,
                    'bill_month' => $m['month'].'-01',
                    'previous_reading' => $m['prev_reading'],
                    'current_reading' => $m['reading'],
                    'total_units' => $m['units'],
                    'unit_price' => $m['unit_price'],
                    'total_amount' => $m['amount'],
                    'split_breakdown' => $m['breakdown'],
                ]);

                foreach ($m['students'] as $s) {
                    Invoice::create([
                        'hostel_id' => $room->hostel_id,
                        'student_id' => $s['student_id'],
                        'type' => 'ac',
                        'ac_bill_id' => $bill->id,
                        // The title carries the explanation to the student's
                        // profile: which month, which room, how many days.
                        'title' => __('AC Bill — :month (Room :room) · :days of :total days', [
                            'month' => $m['label'], 'room' => $room->room_number,
                            'days' => $s['days'], 'total' => $m['days_in_month'],
                        ]),
                        'amount' => $s['share'],
                        'status' => 'pending',
                        'due_date' => now()->addDays(15)->toDateString(),
                        'is_generated_by_system' => true,
                    ]);
                }
            }
        });

        $labels = collect($months)->pluck('label')->implode(', ');
        $this->logger->log('ac_bill.create', "AC bill(s) generated for Room {$room->room_number}: {$labels}");

        return back()->with('success', count($months) === 1
            ? __('AC bill generated and split day-wise among the occupants.')
            : __(':n AC bills generated (:months) and split day-wise among the occupants.', ['n' => count($months), 'months' => $labels]));
    }

    /**
     * Edit a bill's readings/rate (W6.3, owner-approved): recomputes the
     * day-ledger split and rebuilds the invoice set. Guard rule (owner's
     * pick): allowed unless any student's NEW share would drop below what
     * they have ALREADY PAID — that would drive a balance negative, so it
     * aborts with names instead.
     */
    public function update(Request $request, AcBill $acBill): RedirectResponse
    {
        $data = $request->validate([
            'previous_reading' => ['required', 'numeric', 'min:0'],
            'current_reading' => ['required', 'numeric', 'gte:previous_reading'],
            'unit_price' => ['required', 'numeric', 'min:0.01', 'max:99999'],
        ]);

        $units = round($data['current_reading'] - $data['previous_reading'], 2);
        $amount = round($units * $data['unit_price'], 2);

        $split = $this->splitter->split(
            $acBill->room, $acBill->bill_month->copy(), $amount,
            (float) $data['previous_reading'], (float) $data['current_reading'],
        );
        if ($split['students'] === []) {
            return back()->with('error', __('No one occupied this room during that month — there is nobody to bill.'));
        }

        $invoices = $acBill->invoices()->with('student')->get()->keyBy('student_id');
        $newShares = collect($split['students'])->keyBy('student_id');

        // Two ways the recompute can strand paid money; both abort with names.
        $violations = collect();
        foreach ($newShares as $sid => $s) {
            $paid = (float) ($invoices->get($sid)?->paid_amount ?? 0);
            if ($s['share'] < $paid) {
                $violations->push($s['name'].' ('.__('paid').' '.hostelease_money($paid).' > '.__('new share').' '.hostelease_money($s['share']).')');
            }
        }
        foreach ($invoices as $sid => $invoice) {
            if (! $newShares->has($sid) && (float) $invoice->paid_amount > 0) {
                $violations->push(($invoice->student?->name ?? "#{$sid}").' ('.__('paid but no longer in the split').')');
            }
        }
        if ($violations->isNotEmpty()) {
            return back()->with('error', __('Cannot apply this edit — it would drop a share below money already collected: :names. Reverse those receipts first.', ['names' => $violations->implode('; ')]));
        }

        DB::transaction(function () use ($acBill, $data, $units, $amount, $split, $invoices, $newShares) {
            $acBill->update([
                'previous_reading' => $data['previous_reading'],
                'current_reading' => $data['current_reading'],
                'unit_price' => $data['unit_price'],
                'total_units' => $units,
                'total_amount' => $amount,
                'split_breakdown' => $this->breakdownFor($split),
            ]);

            $label = $acBill->bill_month->format('M Y');
            foreach ($newShares as $sid => $s) {
                $title = __('AC Bill — :month (Room :room) · :days of :total days', [
                    'month' => $label, 'room' => $acBill->room->room_number,
                    'days' => $s['days'], 'total' => $split['days_in_month'],
                ]);
                if ($existing = $invoices->get($sid)) {
                    $existing->fill(['title' => $title, 'amount' => $s['share']]);
                    $existing->recalculate();
                    $existing->save();
                } else {
                    Invoice::create([
                        'hostel_id' => $acBill->hostel_id, 'student_id' => $sid, 'type' => 'ac',
                        'ac_bill_id' => $acBill->id, 'title' => $title, 'amount' => $s['share'],
                        'status' => 'pending', 'due_date' => now()->addDays(15)->toDateString(),
                        'is_generated_by_system' => true,
                    ]);
                }
            }
            // Unpaid invoices for students the recompute dropped go with it.
            foreach ($invoices as $sid => $invoice) {
                if (! $newShares->has($sid)) {
                    $invoice->delete();
                }
            }
        });

        $this->logger->log('ac_bill.update', "AC bill #{$acBill->id} edited (Room {$acBill->room->room_number}, {$acBill->bill_month->format('M Y')})", $acBill);

        return back()->with('success', __('AC bill updated — shares recomputed day-wise.'));
    }

    public function destroy(AcBill $acBill): RedirectResponse
    {
        $invoices = $acBill->invoices;

        if ($invoices->where('paid_amount', '>', 0)->isNotEmpty()) {
            return back()->with('error', __('Cannot delete this AC bill — students have already paid against it. Reverse those receipts first.'));
        }

        DB::transaction(function () use ($acBill, $invoices) {
            $invoices->each->delete();
            $acBill->delete(); // soft, like its invoices — the trail survives whole (W6.3)
        });

        $this->logger->log('ac_bill.delete', "AC bill #{$acBill->id} deleted (Room {$acBill->room->room_number})");

        return back()->with('success', __('AC bill and its pending invoices removed.'));
    }

    /** Persist the modal's unit rate as the hostel default (W6.3 inline save). */
    public function saveUnitRate(Request $request): JsonResponse
    {
        $data = $request->validate(['unit_price' => ['required', 'numeric', 'min:0.01', 'max:99999']]);

        $hostel = \App\Models\Hostel::findOrFail(Tenant::id());
        $hostel->update(['settings' => array_merge($hostel->settings ?? [], ['ac_unit_price' => (float) $data['unit_price']])]);

        return response()->json(['saved' => true, 'unit_price' => (float) $data['unit_price']]);
    }

    /** Shared validation for preview + store (the chained multi-month payload). */
    protected function validateBillInput(Request $request): array
    {
        $data = $request->validate([
            'room_id' => ['required', 'integer', Rule::exists('rooms', 'id')->where('hostel_id', Tenant::id())],
            'unit_price' => ['required', 'numeric', 'min:0.01', 'max:99999'],
            'prev_reading' => ['required', 'numeric', 'min:0'],
            'months' => ['required', 'array', 'min:1', 'max:12'],
            'months.*' => ['required', 'date_format:Y-m', 'distinct'],
            'readings' => ['required', 'array', 'size:'.count((array) $request->input('months', []))],
            'readings.*' => ['required', 'numeric', 'min:0'],
        ]);

        // Chronological chain: sort month/reading pairs together, then each
        // month's end reading must be >= its start (the previous month's end).
        $pairs = collect($data['months'])
            ->map(fn ($m, $i) => ['month' => $m, 'reading' => (float) $data['readings'][$i]])
            ->sortBy('month')->values();

        $prev = (float) $data['prev_reading'];
        foreach ($pairs as $pair) {
            if ($pair['reading'] < $prev) {
                abort(422, __('Reading for :month (:r) is lower than the month before it (:p) — meter readings only go up.', [
                    'month' => Carbon::parse($pair['month'].'-01')->format('M Y'), 'r' => $pair['reading'], 'p' => $prev,
                ]));
            }
            $prev = $pair['reading'];
        }

        return [
            'room_id' => (int) $data['room_id'],
            'unit_price' => (float) $data['unit_price'],
            'prev_reading' => (float) $data['prev_reading'],
            'pairs' => $pairs->all(),
        ];
    }

    /** Chain the readings and split every month via the one true service. */
    protected function computeMonths(Room $room, array $data): array
    {
        // Compared in PHP after formatting, not with whereIn on the raw
        // column: the date cast stores "Y-m-d 00:00:00" on SQLite, so a
        // string equality against "Y-m-01" silently never matches — the
        // friendly duplicate check would miss and the user would meet the
        // DB unique constraint as a 500 instead of a sentence.
        $billed = AcBill::where('room_id', $room->id)
            ->pluck('bill_month')->map(fn ($d) => Carbon::parse($d)->format('Y-m'))->all();

        $lastBilled = AcBill::where('room_id', $room->id)->max('bill_month');
        $expectedNext = $lastBilled ? Carbon::parse($lastBilled)->addMonthNoOverflow()->format('Y-m') : null;

        $months = [];
        $prev = $data['prev_reading'];
        foreach ($data['pairs'] as $pair) {
            $monthStart = Carbon::parse($pair['month'].'-01');
            $units = round($pair['reading'] - $prev, 2);
            $amount = round($units * $data['unit_price'], 2);
            // Start/end readings anchor the metered segments — event readings
            // recorded at assign/release/transfer land between them.
            $split = $this->splitter->split($room, $monthStart, $amount, $prev, $pair['reading']);

            $months[] = [
                'month' => $pair['month'],
                'label' => $monthStart->format('M Y'),
                'prev_reading' => $prev,
                'reading' => $pair['reading'],
                'units' => $units,
                'unit_price' => $data['unit_price'],
                'amount' => $amount,
                'already_billed' => in_array($pair['month'], $billed, true),
                // An unbilled month sits between this one and the last known
                // bill — its consumption lands here. Flagged, never hidden.
                'gap_before' => $expectedNext !== null && $pair['month'] > $expectedNext,
                'days_in_month' => $split['days_in_month'],
                'empty_days' => $split['empty_days'],
                'note' => $split['note'],
                'students' => $split['students'],
                'segments' => $split['segments'],
                'breakdown' => $this->breakdownFor($split),
            ];

            $prev = $pair['reading'];
            $expectedNext = Carbon::parse($pair['month'].'-01')->addMonthNoOverflow()->format('Y-m');
        }

        return $months;
    }

    /** The persisted explanation, trimmed to what the row needs to retell it. */
    protected function breakdownFor(array $split): array
    {
        return [
            'days_in_month' => $split['days_in_month'],
            'occupied_days' => $split['occupied_days'],
            'empty_days' => $split['empty_days'],
            'note' => $split['note'],
            // Metered segments: which stretch of the meter each occupant set
            // consumed — the visible proof behind every share.
            'segments' => $split['segments'],
            'students' => array_map(fn ($s) => [
                'student_id' => $s['student_id'],
                'name' => $s['name'],
                'days' => $s['days'],
                'from' => $s['from'],
                'to' => $s['to'],
                'share' => $s['share'],
                'joined_mid' => $s['joined_mid'],
                'left' => $s['left'],
            ], $split['students']),
        ];
    }
}
