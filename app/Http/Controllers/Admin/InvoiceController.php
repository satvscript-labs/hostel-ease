<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Student;
use App\Services\ActivityLogger;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InvoiceController extends Controller
{
    public function __construct(
        protected PaymentService $payments,
        protected ActivityLogger $logger,
    ) {
    }

    /**
     * Charge one-or-more students an ad-hoc amount (Other / Fine), splitting
     * the TOTAL equally across everyone selected — two students break a
     * window, each owes half (W6.1 redesign, owner-approved).
     *
     * The split is remainder-correct: shares are rounded to paise and the
     * rounding remainder lands on the first student, so the invoiced sum
     * always equals the entered total exactly (same discipline as the AC
     * bill split).
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['integer', 'distinct', Rule::exists('students', 'id')->where('hostel_id', \App\Support\Tenant::id())],
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:1', 'max:9999999'],
            'due_date' => ['nullable', 'date'],
        ]);

        $students = Student::whereIn('id', $data['student_ids'])->get();
        $count = $students->count();

        $total = round((float) $data['amount'], 2);
        $share = floor(($total / $count) * 100) / 100;          // everyone's base share
        $firstShare = round($total - $share * ($count - 1), 2); // first absorbs the remainder

        \Illuminate\Support\Facades\DB::transaction(function () use ($students, $data, $share, $firstShare) {
            foreach ($students->values() as $i => $student) {
                $invoice = new Invoice([
                    'student_id' => $student->id,
                    'type' => 'other',
                    'title' => $data['title'],
                    'amount' => $i === 0 ? $firstShare : $share,
                    'due_date' => $data['due_date'] ?? null,
                ]);
                $invoice->paid_amount = 0;
                $invoice->recalculate();
                $invoice->save();
            }
        });

        $this->logger->log('invoice.create', "Charge \"{$data['title']}\" split across {$count} student(s) — " . hostelease_money($total));

        return back()->with('success', $count === 1
            ? 'Invoice generated successfully.'
            : "Charge of " . hostelease_money($total) . " split across {$count} students.");
    }

    public function update(Request $request, Invoice $invoice): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'due_date' => ['nullable', 'date'],
        ]);

        // Surfaced in W6.1 (was written but never routed). Guard added with the
        // UI: shrinking the amount below what's already collected would drive
        // the balance negative — reverse the receipt(s) first instead.
        if ((float) $data['amount'] < (float) $invoice->paid_amount) {
            return back()->with('error', 'Amount cannot be less than what has already been paid ('
                . hostelease_money($invoice->paid_amount) . ') — reverse the payment first.');
        }

        $invoice->fill($data);
        $invoice->recalculate();
        $invoice->save();

        $this->logger->log('invoice.update', "Invoice #{$invoice->id} edited: {$invoice->title}", $invoice);

        return back()->with('success', 'Invoice updated.');
    }

    public function destroy(Invoice $invoice): RedirectResponse
    {
        if ((float) $invoice->paid_amount > 0) {
            return back()->with('error', 'This invoice has payments against it — reverse the receipt(s) first.');
        }

        $invoice->delete();

        return back()->with('success', 'Invoice removed.');
    }
}
