<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use App\Models\PaymentMode;
use App\Models\PocketMoney;
use App\Support\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FinanceController extends Controller
{
    public function index(Request $request): View
    {
        // W6.1: this filtering used to be unreachable — the search input had no
        // name and wasn't in a form, so ?search=/?status= only worked typed by
        // hand while the UI filtered client-side over an unbounded ->get().
        // Now the filter bar is a GET form driving this via data-fragment
        // (design law section 4.3), and both lists paginate. Distinct page
        // params (inv_page/txn_page) so paging one list never resets the other.

        $search = $request->input('search');
        $status = $request->input('status');

        // Stat tiles summarize the WHOLE book (unfiltered, unpaginated) — they
        // must not shrink when a search narrows the list below them.
        $totals = [
            'outstanding' => (float) Invoice::sum('balance'),
            'collected' => (float) Invoice::sum('paid_amount'),
            'invoiced' => (float) Invoice::sum('amount'),
        ];

        $invoicesQuery = Invoice::with('student')->latest('id');
        $paymentsQuery = Payment::with('student')->latest('paid_on')->latest('id');

        if ($search) {
            $invoicesQuery->where(function ($query) use ($search) {
                $query->whereHas('student', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")->orWhere('mobile', 'like', "%{$search}%");
                })->orWhere('title', 'like', "%{$search}%");
            });

            $paymentsQuery->where(function ($query) use ($search) {
                $query->whereHas('student', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")->orWhere('mobile', 'like', "%{$search}%");
                })->orWhere('receipt_number', 'like', "%{$search}%");
            });
        }

        if ($status) {
            $invoicesQuery->where('status', $status);
        }

        // withQueryString(): page links must carry search/status/tab, or
        // clicking page 2 would silently drop the active filter.
        $invoices = $invoicesQuery->paginate(15, ['*'], 'inv_page')->withQueryString();
        $payments = $paymentsQuery->paginate(15, ['*'], 'txn_page')->withQueryString();

        // Active modes only — feeds the Collect sheet's mode picker (W6.1).
        $paymentModes = PaymentMode::active()->orderBy('sort_order')->orderBy('name')->get();

        // Picker payload. Each student also carries:
        //  - room / room_rent: the room number disambiguates same-named
        //    students in the picker; the rent is the client-side preview's
        //    fallback when fee_amount isn't set (server re-resolves on submit);
        //  - last_invoiced_on + last_title: the duplicate-billing warning.
        //    This used to project a "covered till" date by adding 6/12 months
        //    to the last invoice — but that end date was a GUESS (owner
        //    decision: a semester has no knowable end date, which is exactly
        //    why the nightly generator is monthly-only now). So the warning
        //    states only what the database actually knows: the date this
        //    student was last invoiced. The owner judges from there.
        $students = Student::active()
            ->with('activeAssignment.bed.room')
            ->orderBy('name')
            ->get(['id', 'name', 'mobile', 'fee_frequency', 'fee_amount']);

        $lastFeeInvoices = Invoice::whereIn('student_id', $students->pluck('id'))
            ->whereIn('type', ['fee', 'rent'])
            ->whereIn('id', function ($q) {
                $q->selectRaw('MAX(id)')->from('invoices')
                    ->whereIn('type', ['fee', 'rent'])->groupBy('student_id');
            })
            ->get()
            ->keyBy('student_id');

        $pickerStudents = $students->map(function (Student $s) use ($lastFeeInvoices) {
            $last = $lastFeeInvoices->get($s->id);
            $room = optional(optional(optional($s->activeAssignment)->bed)->room);

            return [
                'id' => $s->id,
                'name' => $s->name,
                'mobile' => (string) $s->mobile,
                'fee_frequency' => $s->fee_frequency,
                'fee_amount' => $s->fee_amount !== null ? (float) $s->fee_amount : null,
                'room' => $room->room_number !== null ? (string) $room->room_number : null,
                'room_rent' => $room->rent !== null ? (float) $room->rent : null,
                // The day the invoice was raised — a recorded fact, not a projection.
                'last_invoiced_on' => optional(optional($last)->created_at)->format('Y-m-d'),
                'last_invoiced_label' => optional(optional($last)->created_at)->format('d M Y'),
                'last_title' => optional($last)->title,
            ];
        })->values();

        return view('admin.finance.index', compact('invoices', 'payments', 'paymentModes', 'pickerStudents', 'totals', 'search', 'status'));
    }

    /**
     * Generate fee invoices for one-or-more students at once (W6.1 redesign,
     * owner-approved). Fee types mirror config fee_frequencies exactly —
     * monthly / semester / yearly ("custom" dropped: no student can have that
     * frequency, so its picker filter matched nobody; its real job, an ad-hoc
     * manual amount, is what Other/Fine does).
     *
     * Each student's amount resolves server-side (the client preview is only a
     * preview): their saved fee_amount, else room rent × the fee-type
     * multiplier. All-or-nothing: if anyone's amount can't resolve, the whole
     * batch aborts with names — silently skipping people while the UI says
     * "generated" is how money goes missing.
     */
    public function generateFee(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['integer', 'distinct', Rule::exists('students', 'id')->where('hostel_id', Tenant::id())],
            'fee_type' => ['required', Rule::in(array_keys(config('hostelease.fee_frequencies')))],
            'title' => ['required', 'string', 'max:255'],
            'due_date' => ['nullable', 'date'],
        ]);

        $students = Student::with('activeAssignment.bed.room')
            ->whereIn('id', $data['student_ids'])->get();

        $multiplier = match ($data['fee_type']) {
            'yearly' => 12,
            'semester' => 6,
            default => 1,
        };

        $resolved = [];
        $unresolvable = [];
        foreach ($students as $student) {
            $amount = (float) ($student->fee_amount ?? 0);
            if ($amount <= 0) {
                $roomRent = (float) (optional(optional(optional($student->activeAssignment)->bed)->room)->rent ?? 0);
                $amount = $roomRent * $multiplier;
            }

            if ($amount <= 0) {
                $unresolvable[] = $student->name;
                continue;
            }
            $resolved[] = [$student, $amount];
        }

        if ($unresolvable !== []) {
            return back()->with('error', 'No invoices generated — no fee plan or room rent to derive an amount from for: '
                . implode(', ', $unresolvable) . '. Set their fee plan (or assign a bed) first.');
        }

        $dueDate = $data['due_date'] ?? now()->addDays(15)->toDateString();
        $cycleStart = now()->startOfDay();
        $cycleEnd = $cycleStart->copy()->addMonthsNoOverflow($multiplier)->subDay();

        \Illuminate\Support\Facades\DB::transaction(function () use ($resolved, $data, $dueDate, $cycleStart, $cycleEnd) {
            foreach ($resolved as [$student, $amount]) {
                // NOTE: there is no `billing_cycle` label column — the old code
                // wrote one but it never existed (not in the migration, not
                // fillable), so the write silently vanished for its whole life.
                // The REAL period data is billing_cycle_start/end, which the
                // covered-until warning reads.
                Invoice::create([
                    'hostel_id' => Tenant::id(),
                    'student_id' => $student->id,
                    'type' => 'fee',
                    'title' => $data['title'],
                    'amount' => $amount,
                    'due_date' => $dueDate,
                    'billing_cycle_start' => $cycleStart,
                    'billing_cycle_end' => $cycleEnd,
                ]);
            }
        });

        $count = count($resolved);
        $total = array_sum(array_map(fn ($r) => $r[1], $resolved));

        return back()->with('success', $count === 1
            ? "Fee invoice \"{$data['title']}\" generated for {$resolved[0][0]->name} — " . hostelease_money($resolved[0][1])
            : "Generated {$count} fee invoices totalling " . hostelease_money($total) . '.');
    }
}

