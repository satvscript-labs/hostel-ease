<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentService
{
    public function __construct(protected ActivityLogger $logger)
    {
    }

    /**
     * Record a payment and automatically allocate it to the student's oldest unpaid invoices.
     */
    public function record(array $data): Payment
    {
        return DB::transaction(function () use ($data) {
            $hostelId = $data['hostel_id'] ?? \App\Support\Tenant::id();

            // Never trust a caller's credit_used beyond what the student actually has,
            // even though the controller also validates this (defense in depth).
            $studentForCredit = Student::find($data['student_id']);
            $creditUsed = min(
                (float) ($data['credit_used'] ?? 0),
                (float) ($studentForCredit->credit_balance ?? 0),
            );

            $payment = new Payment([
                'student_id' => $data['student_id'],
                'amount' => $data['amount'],
                'credit_used' => $creditUsed,
                'mode' => $data['mode'],
                'reference_number' => $data['reference_number'] ?? null,
                'paid_on' => $data['paid_on'] ?? now()->toDateString(),
                'remarks' => $data['remarks'] ?? null,
                'collected_by' => $data['collected_by'] ?? Auth::id(),
            ]);
            $payment->hostel_id = $hostelId;
            $payment->receipt_number = $this->uniqueReceiptNumber($hostelId);
            $payment->save();

            $student = $studentForCredit;
            if ($student && $payment->credit_used > 0) {
                $student->credit_balance = max(0, (float) $student->credit_balance - (float) $payment->credit_used);
                $student->save();
            }

            // Auto-allocate this payment to oldest unpaid invoices
            $unallocated = (float) $payment->amount + (float) $payment->credit_used;
            
            $invoices = Invoice::where('student_id', $payment->student_id)
                ->where('status', '!=', 'paid')
                ->orderBy('created_at', 'asc') // oldest first
                ->lockForUpdate()
                ->get();

            foreach ($invoices as $invoice) {
                if ($unallocated <= 0.001) break;
                
                $balance = (float) $invoice->balance;
                if ($balance <= 0) continue;
                
                $apply = min($unallocated, $balance);
                
                // Attach to pivot table
                $payment->invoices()->attach($invoice->id, ['amount' => $apply]);
                
                // Update invoice
                $invoice->paid_amount = (float) $invoice->paid_amount + $apply;
                $invoice->recalculate();
                $invoice->save();
                
                $unallocated -= $apply;
            }

            if ($unallocated > 0.001) {
                if ($student) {
                    $student->credit_balance = (float) $student->credit_balance + $unallocated;
                    $student->save();
                }
            }

            $this->logger->log('payment.create',
                "Received {$payment->amount} from student #{$payment->student_id} ({$payment->mode})",
                $payment,
                ['receipt' => $payment->receipt_number]);

            return $payment;
        });
    }

    /**
     * Reverse a payment: restore the linked invoices' balances, then delete the payment.
     */
    public function reverse(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $invoices = $payment->invoices()->lockForUpdate()->get();

            $totalApplied = 0;
            foreach ($invoices as $invoice) {
                $appliedAmount = (float) $invoice->pivot->amount;
                $totalApplied += $appliedAmount;
                
                $invoice->paid_amount = max(0, (float) $invoice->paid_amount - $appliedAmount);
                $invoice->recalculate();
                $invoice->save();
            }

            $unallocated = ((float) $payment->amount + (float) $payment->credit_used) - $totalApplied;
            
            $student = Student::find($payment->student_id);
            if ($student) {
                if ($payment->credit_used > 0) {
                    $student->credit_balance = (float) $student->credit_balance + (float) $payment->credit_used;
                }
                
                if ($unallocated > 0.001) {
                    $student->credit_balance = max(0, (float) $student->credit_balance - $unallocated);
                }
                $student->save();
            }

            // Detach pivot records
            $payment->invoices()->detach();

            $this->logger->log('payment.reverse',
                "Reversed {$payment->amount} (receipt {$payment->receipt_number})", $payment);

            $payment->delete();
        });
    }

    protected function uniqueReceiptNumber(int $hostelId): string
    {
        do {
            $number = sprintf('RCPT-%d-%s-%s',
                $hostelId,
                now()->format('ym'),
                strtoupper(Str::random(5)));
        } while (Payment::where('receipt_number', $number)->withTrashed()->exists());

        return $number;
    }
}
