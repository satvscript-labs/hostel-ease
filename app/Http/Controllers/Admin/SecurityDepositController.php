<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\PaymentMode;
use App\Models\SecurityDeposit;
use App\Models\Student;
use App\Services\ActivityLogger;
use App\Support\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SecurityDepositController extends Controller
{
    public function __construct(protected ActivityLogger $logger)
    {
    }

    public function index(Request $request): View
    {
        $search = $request->input('search');
        $status = $request->input('status');

        // W6.4: server-side search + status filter + pagination (the old page
        // loaded every deposit ever, unbounded, with 2 invoice queries per
        // row). Tenancy comes from the model's global scope now — the old
        // query only LOOKED scoped because whereHas('student') happened to
        // ride the Student scope.
        $deposits = SecurityDeposit::with(['student', 'paymentMode', 'creator'])
            ->when($search, fn ($q) => $q->where(function ($qq) use ($search) {
                $qq->whereHas('student', fn ($s) => $s->where('name', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%"))
                    ->orWhere('receipt_number', 'like', "%{$search}%");
            }))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest('collected_on')->latest('id')
            ->paginate(15)->withQueryString();

        // Stats are the WHOLE book (owner decision: deposits stay off-books
        // everywhere else, so this page is the one place the custody total
        // lives). Never filter-scoped — a search must not shrink the truth.
        $totals = [
            'held' => (float) SecurityDeposit::where('status', 'collected')->sum('amount'),
            'held_count' => SecurityDeposit::where('status', 'collected')->count(),
            'refunded' => (float) SecurityDeposit::where('status', 'refunded')->sum('refunded_amount'),
            'deducted' => (float) SecurityDeposit::where('status', 'refunded')->sum('deducted_amount'),
        ];

        // The refund sheet needs each page-row student's unpaid invoices. ONE
        // query for the whole page — the old view ran two per row, inside the
        // loop, unpaginated.
        $pendingInvoices = Invoice::whereIn('student_id', $deposits->pluck('student_id')->unique())
            ->where('status', '!=', 'paid')
            ->orderBy('due_date')
            ->get(['id', 'student_id', 'title', 'balance'])
            ->groupBy('student_id');

        // Deposits a student ALREADY holds. A second deposit is legitimate
        // (a top-up, a re-admission) so this never blocks — but the picker
        // tags them and the sheet says so before you record another, because
        // the commonest way to double-charge is not knowing.
        $students = Student::active()
            ->withSum(['securityDeposits as held_total' => fn ($q) => $q->where('status', 'collected')], 'amount')
            ->withCount(['securityDeposits as held_count' => fn ($q) => $q->where('status', 'collected')])
            ->orderBy('name')
            ->get(['id', 'name', 'mobile']);

        $paymentModes = PaymentMode::active()->ordered()->get();

        return view('admin.security_deposits.index', compact(
            'deposits', 'totals', 'pendingInvoices', 'students', 'paymentModes', 'search', 'status'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            // Tenant-scoped exists (W6.4): the bare exists: rules accepted ids
            // from other hostels.
            'student_id' => ['required', Rule::exists('students', 'id')->where('hostel_id', Tenant::id())],
            'amount' => ['required', 'numeric', 'min:1', 'max:9999999'],
            'payment_mode_id' => ['required', Rule::exists('payment_modes', 'id')->where('hostel_id', Tenant::id())],
            'collected_on' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $deposit = SecurityDeposit::create($data + [
            'receipt_number' => $this->nextReceiptNumber(),
            'created_by' => $request->user()->id,
            'status' => 'collected',
        ]);

        $this->logger->log('deposit.create', "Security deposit {$deposit->receipt_number} — "
            .hostelease_money($deposit->amount)." from {$deposit->student->name}", $deposit);

        return back()->with('success', 'Security deposit recorded.');
    }

    /**
     * New in W6.4 (owner decision): a typo'd deposit used to be permanent.
     * Editable only while still held — a refunded deposit's numbers are part
     * of a settlement that already happened.
     */
    public function update(Request $request, SecurityDeposit $securityDeposit): RedirectResponse
    {
        if ($securityDeposit->status !== 'collected') {
            return back()->with('error', 'This deposit has been refunded — its record is settled. Revert the refund first if something is wrong.');
        }

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:9999999'],
            'payment_mode_id' => ['required', Rule::exists('payment_modes', 'id')->where('hostel_id', Tenant::id())],
            'collected_on' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $securityDeposit->update($data);

        $this->logger->log('deposit.update', "Security deposit {$securityDeposit->receipt_number} edited — "
            .hostelease_money($securityDeposit->amount), $securityDeposit);

        return back()->with('success', 'Deposit updated.');
    }

    public function refund(Request $request, SecurityDeposit $securityDeposit): RedirectResponse
    {
        $data = $request->validate([
            'refunded_amount' => ['required', 'numeric', 'min:0'],
            'deducted_amount' => ['required', 'numeric', 'min:0'],
            'refund_note' => ['nullable', 'string', 'max:255'],
            'deduct_invoice_ids' => ['nullable', 'array'],
            'deduct_invoice_ids.*' => [Rule::exists('invoices', 'id')
                ->where('hostel_id', Tenant::id())
                ->where('student_id', $securityDeposit->student_id)],
        ]);

        if ($securityDeposit->status !== 'collected') {
            return back()->with('error', 'Deposit has already been refunded.');
        }

        // FULL settlement (owner decision, W6.4): refunded + deducted must
        // equal the deposit exactly. The old guard was only "not more than",
        // so a ₹5,000 deposit could be closed with ₹3,000 accounted for and
        // ₹2,000 silently gone.
        $settled = round((float) $data['refunded_amount'] + (float) $data['deducted_amount'], 2);
        if (abs($settled - (float) $securityDeposit->amount) > 0.009) {
            return back()->with('error', 'Refunded + deducted must equal the full deposit ('
                .hostelease_money($securityDeposit->amount).') — every rupee accounted for. You entered '
                .hostelease_money($settled).'.');
        }

        // A deduction has two parts, and BOTH are legitimate (owner rule):
        //  · towards dues — settles the invoices ticked in the sheet;
        //  · retained     — kept for something with no invoice behind it
        //                   (room damage, cleaning). Needs a written reason,
        //                   or the record says money was kept and never why.
        $deduction = round((float) $data['deducted_amount'], 2);
        $invoices = empty($data['deduct_invoice_ids'])
            ? collect()
            : Invoice::whereIn('id', $data['deduct_invoice_ids'])->orderBy('due_date')->get();

        $capacity = round((float) $invoices->sum('balance'), 2);
        $towardsDues = round(min($deduction, $capacity), 2);
        $retained = round($deduction - $towardsDues, 2);

        if ($retained > 0.009 && blank($data['refund_note'] ?? null)) {
            return back()->with('error', hostelease_money($retained)
                .' of this deduction is not settling any due — say what it is for in the note (e.g. room damage).');
        }

        DB::transaction(function () use ($securityDeposit, $data, $towardsDues, $invoices, $request) {
            $remaining = $towardsDues;
            foreach ($invoices as $invoice) {
                if ($remaining <= 0) {
                    break;
                }

                $paymentAmount = round(min((float) $invoice->balance, $remaining), 2);
                if ($paymentAmount <= 0) {
                    continue;
                }

                $invoice->payments()->create([
                    'student_id' => $invoice->student_id,
                    'hostel_id' => Tenant::id(),
                    'amount' => $paymentAmount,
                    'paid_on' => today(),
                    'mode' => $securityDeposit->paymentMode?->code ?? 'cash',
                    'receipt_number' => 'REF-'.$securityDeposit->receipt_number.'-'.$invoice->id,
                    'remarks' => 'Deducted from security deposit '.$securityDeposit->receipt_number,
                    'collected_by' => $request->user()->id,
                ], ['amount' => $paymentAmount]);

                $invoice->paid_amount = (float) $invoice->paid_amount + $paymentAmount;
                $invoice->recalculate();
                $invoice->save();

                $remaining = round($remaining - $paymentAmount, 2);
            }

            $securityDeposit->update([
                'status' => 'refunded',
                'refunded_on' => today(),
                'refunded_amount' => $data['refunded_amount'],
                'deducted_amount' => $data['deducted_amount'],
                'refund_note' => $data['refund_note'] ?? null,
            ]);
        });

        $this->logger->log('deposit.refund', "Security deposit {$securityDeposit->receipt_number} settled — "
            .hostelease_money($data['refunded_amount']).' refunded, '
            .hostelease_money($data['deducted_amount']).' deducted against dues', $securityDeposit);

        return back()->with('success', 'Security deposit settled — every rupee accounted for.');
    }

    public function revertRefund(SecurityDeposit $securityDeposit, \App\Services\PaymentService $paymentService): RedirectResponse
    {
        if ($securityDeposit->status !== 'refunded') {
            return back()->with('error', 'Deposit is not currently refunded.');
        }

        DB::transaction(function () use ($securityDeposit, $paymentService) {
            $payments = \App\Models\Payment::where('receipt_number', 'like', 'REF-'.$securityDeposit->receipt_number.'-%')->get();
            foreach ($payments as $payment) {
                $paymentService->reverse($payment);
            }

            $securityDeposit->update([
                'status' => 'collected',
                'refunded_on' => null,
                'refunded_amount' => 0,
                'deducted_amount' => 0,
                'refund_note' => null,
            ]);
        });

        $this->logger->log('deposit.revert', "Security deposit {$securityDeposit->receipt_number} refund reverted — deposit held again", $securityDeposit);

        return back()->with('success', 'Refund reverted — deducted dues reinstated, deposit held again.');
    }

    /**
     * Next SD-{hostel}-NNNNN. Derived from the highest existing suffix (incl.
     * trashed rows), not count()+1 — counting breaks the moment a row is ever
     * removed, and two counts can race to the same number.
     */
    protected function nextReceiptNumber(): string
    {
        $prefix = 'SD-'.Tenant::id().'-';

        $last = SecurityDeposit::withTrashed()
            ->where('receipt_number', 'like', "{$prefix}%")
            ->orderByDesc('id')
            ->value('receipt_number');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        while (SecurityDeposit::withTrashed()->where('receipt_number', $prefix.str_pad((string) $next, 5, '0', STR_PAD_LEFT))->exists()) {
            $next++;
        }

        return $prefix.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }
}
