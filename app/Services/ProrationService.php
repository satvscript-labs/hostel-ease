<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProrationService
{
    public function __construct(
        protected ActivityLogger $logger
    ) {}

    public function preview(Student $student, float $newFeeAmount, string $newFrequency)
    {
        $lastInvoice = $student->invoices()->where('type', 'fee')->latest('due_date')->first();
        
        $creditAmount = 0;
        $hasActiveCycle = false;
        $daysUsed = 0;
        $daysTotal = 0;
        $daysUnused = 0;
        $oldAmount = 0;

        if ($lastInvoice && $lastInvoice->billing_cycle_start && $lastInvoice->billing_cycle_end) {
            $start = Carbon::parse($lastInvoice->billing_cycle_start)->startOfDay();
            $end = Carbon::parse($lastInvoice->billing_cycle_end)->endOfDay();
            $today = now()->startOfDay();

            if ($today->between($start, $end)) {
                $hasActiveCycle = true;
                $daysTotal = $start->diffInDays($end) + 1;
                $daysUsed = $start->diffInDays($today); // Not including today, today starts new cycle
                $daysUnused = $daysTotal - $daysUsed;
                $oldAmount = (float) $lastInvoice->amount;
                
                if ($daysTotal > 0 && $daysUnused > 0) {
                    $creditAmount = round(($oldAmount / $daysTotal) * $daysUnused, 2);
                }
            }
        }

        // Calculate new invoice
        $monthsToAdd = 1;
        if ($newFrequency === 'semester') {
            $monthsToAdd = 6;
        } elseif ($newFrequency === 'yearly') {
            $monthsToAdd = 12;
        }

        $newCycleStart = now()->startOfDay();
        $newCycleEnd = $newCycleStart->copy()->addMonthsNoOverflow($monthsToAdd)->subDay()->endOfDay();
        
        $newInvoiceAmount = $newFeeAmount;
        $netDue = max(0, $newInvoiceAmount - $creditAmount - (float) $student->credit_balance);
        $projectedCreditBalance = max(0, (float) $student->credit_balance + $creditAmount - $newInvoiceAmount);

        return [
            'has_active_cycle' => $hasActiveCycle,
            'old_amount' => $oldAmount,
            'days_used' => $daysUsed,
            'days_unused' => $daysUnused,
            'days_total' => $daysTotal,
            'refund_credit' => $creditAmount,
            'current_credit_balance' => (float) $student->credit_balance,
            'new_invoice_amount' => $newInvoiceAmount,
            'new_frequency' => $newFrequency,
            'net_due' => $netDue,
            'projected_credit_balance' => $projectedCreditBalance,
            'last_invoice' => $lastInvoice,
        ];
    }

    public function apply(Student $student, array $newSettings)
    {
        return DB::transaction(function () use ($student, $newSettings) {
            $preview = $this->preview($student, $newSettings['fee_amount'], $newSettings['fee_frequency']);
            
            // 1. Apply refund if applicable
            if ($preview['refund_credit'] > 0) {
                // Update old invoice cycle end to yesterday
                $lastInvoice = $preview['last_invoice'];
                if ($lastInvoice) {
                    $lastInvoice->billing_cycle_end = now()->subDay()->endOfDay();
                    $lastInvoice->save();
                }

                // Add to credit balance
                $student->credit_balance = (float) $student->credit_balance + $preview['refund_credit'];
                $student->save();

                // Create a credit note record (a Payment record with negative or just a log)
                // For now, an activity log is enough, or a 0 amount payment with remarks
                Payment::create([
                    'hostel_id' => $student->hostel_id,
                    'student_id' => $student->id,
                    'amount' => $preview['refund_credit'],
                    'mode' => 'credit_note',
                    'reference_number' => 'PRORATION-REFUND',
                    'paid_on' => now(),
                    'remarks' => "Prorated refund for unused {$preview['days_unused']} days of previous plan.",
                    'receipt_number' => 'CR-' . strtoupper(Str::random(8)),
                    'collected_by' => auth()->id() ?? 1,
                ]);

                $this->logger->log('finance.proration', "Refunded {$preview['refund_credit']} to credit balance for {$preview['days_unused']} unused days.", $student);
            }

            // 2. Update Student Settings
            $student->update([
                'room_preference' => $newSettings['room_preference'] ?? $student->room_preference,
                'sharing_preference' => $newSettings['sharing_preference'] ?? $student->sharing_preference,
                'fee_amount' => $newSettings['fee_amount'],
                'fee_frequency' => $newSettings['fee_frequency'],
            ]);

            // 3. Generate New Invoice immediately
            $monthsToAdd = 1;
            if ($newSettings['fee_frequency'] === 'semester') {
                $monthsToAdd = 6;
            } elseif ($newSettings['fee_frequency'] === 'yearly') {
                $monthsToAdd = 12;
            }

            $startDate = now()->startOfDay();
            $endDate = $startDate->copy()->addMonthsNoOverflow($monthsToAdd)->subDay();
            
            $periodLabel = $startDate->format('M Y');
            $title = "Rent for $periodLabel";
            if ($newSettings['fee_frequency'] === 'semester') {
                $title = "Semester Fee ($periodLabel)";
            } elseif ($newSettings['fee_frequency'] === 'yearly') {
                $title = "Yearly Fee ($periodLabel)";
            }

            $invoice = Invoice::create([
                'hostel_id' => $student->hostel_id,
                'student_id' => $student->id,
                'type' => 'fee',
                'title' => $title,
                'amount' => $newSettings['fee_amount'],
                'billing_cycle_start' => $startDate,
                'billing_cycle_end' => $endDate,
                'due_date' => $startDate,
                'status' => 'pending',
                'is_generated_by_system' => true,
            ]);

            $this->logger->log('invoice.create', "Generated prorated invoice #{$invoice->id} for new plan.", $invoice);

            // 4. Auto-apply credit balance
            $invoice->refresh();
            $student->refresh();
            if ($student->credit_balance > 0) {
                $creditToApply = min((float)$student->credit_balance, (float)$invoice->balance);
                
                if ($creditToApply > 0) {
                    $payment = Payment::create([
                        'student_id' => $student->id,
                        'hostel_id' => $student->hostel_id,
                        'amount' => $creditToApply,
                        'mode' => 'credit',
                        'reference_number' => 'Auto-applied Credit',
                        'paid_on' => now()->toDateString(),
                        'remarks' => 'Automatically applied from student credit balance during plan change',
                        'receipt_number' => 'CRDT-' . strtoupper(Str::random(8)),
                        'collected_by' => auth()->id() ?? 1,
                    ]);

                    $payment->invoices()->attach($invoice->id, ['amount' => $creditToApply]);
                    
                    $invoice->paid_amount = (float) $invoice->paid_amount + $creditToApply;
                    $invoice->recalculate();
                    $invoice->save();

                    $student->credit_balance = (float) $student->credit_balance - $creditToApply;
                    $student->save();
                }
            }

            return true;
        });
    }
}
