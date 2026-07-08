<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentMode;
use App\Models\SecurityDeposit;
use App\Models\Student;
use App\Models\Invoice;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SecurityDepositController extends Controller
{
    public function index()
    {
        $deposits = SecurityDeposit::with(['student', 'paymentMode', 'creator'])
            ->latest('collected_on')
            ->get();

        $students = Student::active()->orderBy('name')->get();
        $paymentModes = PaymentMode::orderBy('name')->get();

        return view('admin.security_deposits.index', compact('deposits', 'students', 'paymentModes'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'amount' => ['required', 'numeric', 'min:1'],
            'payment_mode_id' => ['required', 'exists:payment_modes,id'],
            'collected_on' => ['required', 'date'],
        ]);

        // Generate receipt number e.g., SD-HOSTEL-00001
        $prefix = 'SD-' . Tenant::id() . '-';
        $latest = SecurityDeposit::where('receipt_number', 'like', "{$prefix}%")->count();
        $data['receipt_number'] = $prefix . str_pad($latest + 1, 5, '0', STR_PAD_LEFT);
        
        $data['hostel_id'] = Tenant::id();
        $data['created_by'] = $request->user()->id;
        $data['status'] = 'collected';

        SecurityDeposit::create($data);

        return back()->with('success', 'Security deposit recorded successfully.');
    }

    public function refund(Request $request, SecurityDeposit $securityDeposit)
    {
        $data = $request->validate([
            'refunded_amount' => ['required', 'numeric', 'min:0'],
            'deducted_amount' => ['required', 'numeric', 'min:0'],
            'refund_note' => ['nullable', 'string'],
            'deduct_invoice_ids' => ['nullable', 'array'],
            'deduct_invoice_ids.*' => ['exists:invoices,id'],
        ]);

        if ($securityDeposit->status !== 'collected') {
            return back()->with('error', 'Deposit has already been refunded.');
        }

        if (($data['refunded_amount'] + $data['deducted_amount']) > $securityDeposit->amount) {
            return back()->with('error', 'Total refunded and deducted amounts cannot exceed the deposit amount.');
        }

        // Process deductions on selected invoices
        if (!empty($data['deduct_invoice_ids']) && $data['deducted_amount'] > 0) {
            $invoices = Invoice::whereIn('id', $data['deduct_invoice_ids'])->get();
            $remainingDeduction = $data['deducted_amount'];

            foreach ($invoices as $invoice) {
                if ($remainingDeduction <= 0) break;

                $paymentAmount = min($invoice->balance, $remainingDeduction);
                
                if ($paymentAmount > 0) {
                    $invoice->payments()->create([
                        'student_id' => $invoice->student_id,
                        'hostel_id' => Tenant::id(),
                        'amount' => $paymentAmount,
                        'paid_on' => today(),
                        'payment_mode_id' => $securityDeposit->payment_mode_id,
                        'receipt_number' => 'REF-' . $securityDeposit->receipt_number . '-' . time(),
                        'note' => 'Deducted from Security Deposit',
                        'created_by' => $request->user()->id,
                    ]);

                    $invoice->paid_amount += $paymentAmount;
                    $invoice->balance -= $paymentAmount;
                    $invoice->status = $invoice->balance <= 0 ? 'paid' : 'partial';
                    $invoice->save();

                    $remainingDeduction -= $paymentAmount;
                }
            }
        }

        $securityDeposit->update([
            'status' => 'refunded',
            'refunded_on' => today(),
            'refunded_amount' => $data['refunded_amount'],
            'deducted_amount' => $data['deducted_amount'],
            'refund_note' => $data['refund_note'],
        ]);

        return back()->with('success', 'Security deposit refunded successfully.');
    }
}
