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
        // For the Finance Dashboard, we need:
        // 1. All invoices (Dues)
        // 2. All payments (Transactions)
        // 3. Pocket money ledger
        // 4. Payment modes

        $search = $request->input('search');
        $status = $request->input('status');
        $sort = $request->input('sort', 'id');
        $direction = $request->input('direction', 'desc');

        // Whitelist sort columns
        $validInvoiceSorts = ['id', 'created_at', 'amount', 'paid_amount', 'balance'];
        $validPaymentSorts = ['id', 'paid_on', 'amount'];
        $invoiceSort = in_array($sort, $validInvoiceSorts) ? $sort : 'id';
        $paymentSort = in_array($sort, $validPaymentSorts) ? $sort : 'paid_on';
        $direction = in_array($direction, ['asc', 'desc']) ? $direction : 'desc';

        $invoicesQuery = Invoice::with('student')->orderBy($invoiceSort, $direction);
        $paymentsQuery = Payment::with('student', 'invoices')->orderBy($paymentSort, $direction);

        if ($search) {
            $invoicesQuery->where(function($query) use ($search) {
                $query->whereHas('student', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")->orWhere('mobile', 'like', "%{$search}%");
                })->orWhere('title', 'like', "%{$search}%");
            });

            $paymentsQuery->where(function($query) use ($search) {
                $query->whereHas('student', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")->orWhere('mobile', 'like', "%{$search}%");
                })->orWhere('receipt_number', 'like', "%{$search}%");
            });
        }

        if ($status) {
            $invoicesQuery->where('status', $status);
        }

        $invoices = $invoicesQuery->get();
        $payments = $paymentsQuery->get();

        $paymentModes = PaymentMode::orderBy('name')->get();

        $students = Student::active()->orderBy('name')->whereIn('fee_frequency', ['semester', 'yearly'])->get(['id', 'name', 'mobile', 'fee_frequency']);

        $studentsJson = $students->map(fn($s) => [
            'id'     => $s->id,
            'name'   => $s->name,
            'mobile' => $s->mobile,
            'freq'   => $s->fee_frequency,
        ])->values();

        return view('admin.finance.index', compact('invoices', 'payments', 'paymentModes', 'students', 'studentsJson', 'search', 'status', 'sort', 'direction'));
    }

    /**
     * Generate a fee invoice for a student (Semester / Yearly / Custom).
     */
    public function generateFee(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'fee_type' => ['required', Rule::in(['semester', 'yearly', 'custom'])],
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['nullable', 'numeric', 'min:1'],
            'due_date' => ['nullable', 'date'],
        ]);

        $student = Student::with('activeAssignment.bed.room')->findOrFail($data['student_id']);

        // Calculate amount based on fee type if not custom
        if (empty($data['amount'])) {
            $roomRent = optional(optional(optional($student->activeAssignment)->bed)->room)->rent ?? 0;
            $data['amount'] = match ($data['fee_type']) {
                'semester' => $roomRent * 6,
                'yearly' => $roomRent * 12,
                default => $roomRent,
            };
        }

        if ($data['amount'] <= 0) {
            return back()->with('error', 'Could not determine fee amount. The student may not have a room assignment or rent configured.');
        }

        $invoice = Invoice::create([
            'hostel_id' => Tenant::id(),
            'student_id' => $student->id,
            'type' => 'fee',
            'title' => $data['title'],
            'amount' => $data['amount'],
            'due_date' => $data['due_date'] ?? now()->addDays(15),
            'billing_cycle' => $data['fee_type'],
        ]);

        return back()->with('success', "Fee invoice \"{$invoice->title}\" generated for {$student->name} — " . config('hostelease.currency') . number_format($invoice->amount, 2));
    }
}

