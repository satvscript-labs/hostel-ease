<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Student;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The single entry point for recording money received.
 *
 * Fees, Semester Fees, Monthly Rent and AC Bills all post through here so that
 * receipt numbering, the audit trail and obligation balances stay consistent.
 */
class PaymentService
{
    public function __construct(protected ActivityLogger $logger)
    {
    }

    /**
     * Record a payment, optionally settling a payable obligation
     * (SemesterFee / MonthlyRent / AcBillStudent).
     */
    public function record(array $data, ?Model $payable = null): Payment
    {
        return DB::transaction(function () use ($data, $payable) {
            $hostelId = $data['hostel_id'] ?? \App\Support\Tenant::id();

            $payment = new Payment([
                'student_id' => $data['student_id'],
                'amount' => $data['amount'],
                'payment_type' => $data['payment_type'] ?? 'full',
                'mode' => $data['mode'],
                'reference_number' => $data['reference_number'] ?? null,
                'paid_on' => $data['paid_on'] ?? now()->toDateString(),
                'remarks' => $data['remarks'] ?? null,
                'collected_by' => $data['collected_by'] ?? Auth::id(),
            ]);
            $payment->hostel_id = $hostelId;
            $payment->receipt_number = $this->uniqueReceiptNumber($hostelId);

            if ($payable) {
                $payment->payable()->associate($payable);
            }

            $payment->save();

            if ($payable && method_exists($payable, 'recalculate')) {
                $payable->paid_amount = (float) $payable->paid_amount + (float) $payment->amount;
                $payable->recalculate();
                $payable->save();
            }

            $this->logger->log('payment.create',
                "Received {$payment->amount} from student #{$payment->student_id} ({$payment->mode})",
                $payment,
                ['receipt' => $payment->receipt_number]);

            return $payment;
        });
    }

    /**
     * Apply any of the student's unallocated credit (advance payments that
     * aren't tied to an obligation yet) to a newly created due, oldest first.
     *
     * This makes "pay now, get a bed/fee later" work: a ₹25,000 advance taken
     * before a bed is assigned is automatically settled against the ₹50,000 fee
     * when it appears, so the profile shows paid ₹25,000 / pending ₹25,000
     * instead of pending ₹50,000.
     */
    public function applyAdvances(Student $student, Model $payable): void
    {
        if (! method_exists($payable, 'recalculate')) {
            return;
        }

        DB::transaction(function () use ($student, $payable) {
            $payable->refresh();
            $balance = (float) $payable->balance;
            if ($balance <= 0.001) {
                return;
            }

            $advances = Payment::where('student_id', $student->id)
                ->whereNull('payable_id')
                ->orderBy('paid_on')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($advances as $adv) {
                if ($balance <= 0.001) {
                    break;
                }
                $amt = (float) $adv->amount;
                if ($amt <= 0) {
                    continue;
                }
                $apply = min($amt, $balance);

                if ($apply >= $amt - 0.001) {
                    // Whole advance fits — just link it to this obligation.
                    $adv->payable()->associate($payable);
                    $adv->payment_type = $apply >= $balance - 0.001 ? 'full' : 'partial';
                    $adv->save();
                } else {
                    // Advance is larger than the due — keep the remainder as
                    // credit and split off a slice that settles this obligation.
                    $adv->amount = round($amt - $apply, 2);
                    $adv->save();

                    $slice = $adv->replicate(['receipt_number']);
                    $slice->amount = round($apply, 2);
                    $slice->payment_type = 'full';
                    $slice->receipt_number = $this->uniqueReceiptNumber((int) $adv->hostel_id);
                    $slice->payable()->associate($payable);
                    $slice->save();
                }

                $payable->paid_amount = (float) $payable->paid_amount + $apply;
                $payable->recalculate();
                $payable->save();
                $balance -= $apply;
            }
        });
    }

    /**
     * Reverse a payment: restore the linked obligation's balance/status, then
     * delete the payment. Keeps Outstanding correct when a receipt is removed.
     */
    public function reverse(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $payable = $payment->payable; // morphTo (SemesterFee / MonthlyRent / AcBillStudent) or null

            if ($payable && method_exists($payable, 'recalculate')) {
                $payable->paid_amount = max(0, (float) $payable->paid_amount - (float) $payment->amount);
                $payable->recalculate();
                $payable->save();
            }

            $this->logger->log('payment.reverse',
                "Reversed {$payment->amount} (receipt {$payment->receipt_number})", $payment);

            $payment->delete();
        });
    }

    /**
     * Generate a collision-free receipt number for the hostel.
     */
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
