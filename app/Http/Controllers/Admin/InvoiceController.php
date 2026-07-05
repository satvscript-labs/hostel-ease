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

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['required', Rule::exists('students', 'id')->where('hostel_id', \App\Support\Tenant::id())],
            'type' => ['required', Rule::in(['fee', 'rent', 'ac', 'other'])],
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'due_date' => ['nullable', 'date'],
        ]);

        $invoice = new Invoice($data);
        $invoice->paid_amount = 0;
        $invoice->recalculate();
        $invoice->save();

        $this->logger->log('invoice.create', "Invoice generated: {$invoice->title} for student #{$invoice->student_id}", $invoice);

        return back()->with('success', 'Invoice generated successfully.');
    }

    public function update(Request $request, Invoice $invoice): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'due_date' => ['nullable', 'date'],
        ]);

        $invoice->fill($data);
        $invoice->recalculate();
        $invoice->save();

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

    public function generateRent(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'due_date' => ['nullable', 'date'],
        ]);

        $monthDate = \Carbon\Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth();
        $title = 'Rent - ' . $monthDate->format('M Y');

        // Find all active students with a bed assignment
        $students = Student::active()->whereHas('assignments', function($q) {
            $q->whereNull('released_on');
        })->with(['assignments' => function($q) {
            $q->whereNull('released_on')->latest();
        }])->get();

        $count = 0;

        foreach ($students as $student) {
            // Check if rent invoice already exists for this month
            $exists = Invoice::where('student_id', $student->id)
                ->where('type', 'rent')
                ->where('title', $title)
                ->exists();

            if ($exists) continue;

            $assignment = $student->assignments->first();
            if (!$assignment) continue;

            $amount = $assignment->monthly_fee ?? 0;
            if ($amount <= 0) continue;

            $invoice = new Invoice([
                'student_id' => $student->id,
                'type' => 'rent',
                'title' => $title,
                'amount' => $amount,
                'due_date' => $data['due_date'] ?? null,
                'is_generated_by_system' => true,
            ]);
            $invoice->paid_amount = 0;
            $invoice->recalculate();
            $invoice->save();

            $count++;
        }

        $this->logger->log('invoice.bulk', "Generated $count rent invoices for " . $monthDate->format('M Y'));

        return back()->with('success', "Generated $count rent invoices successfully.");
    }
}
