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

        // MONTHLY ONLY (W6.1 owner decision). Monthly is the one frequency where
        // "last due + N months" is genuinely true — a month is a month. Semester
        // and yearly terms end when the student's academic calendar says they
        // end, which only the owner knows; auto-billing them on a blind
        // 6/12-month assumption generated real invoices on fictional dates.
        // Recurring semester/yearly billing is now owner-driven via the Finance
        // Board's bulk Hostel Fee flow (with a covered-until warning as the
        // memory aid). The initial invoice on plan save is untouched — that's
        // an explicit owner action with a proration preview.
        // has('activeAssignment') (W6.4, owner-approved): rent is for a BED.
        // Without this, a student released from their bed but not marked
        // "left" kept accruing monthly rent for a room they no longer
        // occupied — money invented out of an empty seat.
        $students = Student::where('status', 'active')
            ->has('activeAssignment')
            ->where('fee_frequency', 'monthly')
            ->whereNotNull('fee_amount')
            ->whereNotNull('join_date')
            ->get();

        $generatedCount = 0;

        foreach ($students as $student) {
            $lastInvoice = $student->invoices()->where('type', 'fee')->latest('due_date')->first();
            $joinDate = Carbon::parse($student->join_date);

            $shouldGenerate = false;
            $dueDate = null;
            $periodLabel = '';

            $monthsToAdd = 1; // monthly-only command (see query above)

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
