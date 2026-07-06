<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Student;
use App\Models\Invoice;
use Carbon\Carbon;
use App\Services\PaymentService;

class HostelEaseGenerateInvoices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hostel:generate-invoices';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate automated invoices based on student fee settings';

    /**
     * Execute the console command.
     */
    public function handle(PaymentService $paymentService)
    {
        $this->info('Starting automated invoice generation...');

        $students = Student::where('status', 'active')
            ->whereNotNull('fee_amount')
            ->whereNotNull('fee_frequency')
            ->whereNotNull('join_date')
            ->get();

        $generatedCount = 0;

        foreach ($students as $student) {
            $lastInvoice = $student->invoices()->where('type', 'fee')->latest('due_date')->first();
            $joinDate = Carbon::parse($student->join_date);

            $shouldGenerate = false;
            $dueDate = null;
            $periodLabel = '';

            $monthsToAdd = 1;
            if ($student->fee_frequency === 'semester') {
                $monthsToAdd = 6;
            } elseif ($student->fee_frequency === 'yearly') {
                $monthsToAdd = 12;
            }

            if (!$lastInvoice) {
                // Should have been generated on save, but just in case
                $shouldGenerate = true;
                $dueDate = $joinDate;
                $periodLabel = 'Initial';
            } else {
                $lastDueDate = Carbon::parse($lastInvoice->due_date);

                $nextDueDate = $lastDueDate->copy()->addMonthsNoOverflow($monthsToAdd);

                // Ensure the day of month matches the join_date day
                $nextDueDate->day(min($joinDate->day, $nextDueDate->daysInMonth));

                if (now()->startOfDay()->gte($nextDueDate->startOfDay())) {
                    $shouldGenerate = true;
                    $dueDate = $nextDueDate;
                    $periodLabel = $nextDueDate->format('M Y');
                }
            }

            if ($shouldGenerate) {
                $title = "Rent for $periodLabel";
                if ($student->fee_frequency === 'semester') {
                    $title = "Semester Fee ($periodLabel)";
                } elseif ($student->fee_frequency === 'yearly') {
                    $title = "Yearly Fee ($periodLabel)";
                }

                $cycleEnd = $dueDate->copy()->addMonthsNoOverflow($monthsToAdd)->subDay();

                $invoice = Invoice::create([
                    'hostel_id' => $student->hostel_id,
                    'student_id' => $student->id,
                    'type' => 'fee',
                    'title' => $title,
                    'amount' => $student->fee_amount,
                    'billing_cycle_start' => $dueDate,
                    'billing_cycle_end' => $cycleEnd,
                    'due_date' => $dueDate,
                    'status' => 'pending',
                ]);
                $generatedCount++;

                $this->info("Generated invoice #{$invoice->id} for {$student->name} (Amount: {$student->fee_amount})");

                $invoice->refresh();

                // Auto-apply credit if available
                if ($student->credit_balance > 0) {
                    $creditToApply = min((float)$student->credit_balance, (float)$invoice->balance);
                    
                    if ($creditToApply > 0) {
                        // Create a payment record to represent the credit application
                        // The PaymentService automatically deducts from credit_balance in reverse, but here we just need to use the credit.
                        // Wait, PaymentService record() function adds to credit if unallocated > 0. 
                        // If we use it here, we should pass an amount and a special mode.
                        // We need to bypass the PaymentService adding to credit, because we are using existing credit.
                        
                        // Let's do it manually to be safe.
                        $payment = \App\Models\Payment::create([
                            'student_id' => $student->id,
                            'hostel_id' => $student->hostel_id,
                            'amount' => $creditToApply,
                            'payment_type' => 'full',
                            'mode' => 'credit', // We should ensure a 'credit' mode exists or just use a default string.
                            'reference_number' => 'Auto-applied Credit',
                            'paid_on' => now()->toDateString(),
                            'remarks' => 'Automatically applied from student credit balance',
                            'receipt_number' => 'CRDT-' . strtoupper(\Illuminate\Support\Str::random(8)),
                            'collected_by' => 1, // Admin
                        ]);

                        $payment->invoices()->attach($invoice->id, ['amount' => $creditToApply]);
                        
                        $invoice->paid_amount = (float) $invoice->paid_amount + $creditToApply;
                        $invoice->recalculate();
                        $invoice->save();

                        // Deduct from student credit
                        $student->credit_balance = (float) $student->credit_balance - $creditToApply;
                        $student->save();
                        
                        $this->info("  -> Auto-applied {$creditToApply} from credit balance.");
                    }
                }
            }
        }

        $this->info("Done! Generated $generatedCount invoices.");
    }
}
