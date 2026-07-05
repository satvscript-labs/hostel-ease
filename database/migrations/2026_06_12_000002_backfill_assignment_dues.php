<?php

use App\Models\BedAssignment;
use App\Models\MonthlyRent;
use App\Models\Scopes\TenantScope;
use App\Models\SemesterFee;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;

/**
 * One-time backfill: students assigned BEFORE fee-due auto-creation shipped have
 * a fee plan on their bed assignment but no actual payable due, so their profile
 * shows ₹0 billed. Create the missing dues from each active assignment's stored
 * fee_amount / fee_frequency, mirroring BedAssignmentService::createInitialDue().
 *
 * Idempotent & conservative:
 *  - monthly  → adds the join-month rent only if that month's row is missing.
 *  - semester → adds Semester 1 only if the student has NO semester fee at all
 *               (never duplicates an existing/manually-added fee).
 * Runs across every hostel (no tenant bound in console/installer context).
 */
return new class extends Migration
{
    public function up(): void
    {
        BedAssignment::withoutGlobalScope(TenantScope::class)
            ->where('is_active', true)
            ->where('fee_amount', '>', 0)
            ->with('student')
            ->chunkById(200, function ($assignments) {
                foreach ($assignments as $a) {
                    if (! $a->student) {
                        continue;
                    }

                    $join = $a->join_date ? Carbon::parse($a->join_date) : now();

                    if ($a->fee_frequency === 'monthly') {
                        $month = $join->copy()->startOfMonth();

                        $exists = MonthlyRent::withoutGlobalScope(TenantScope::class)
                            ->where('hostel_id', $a->hostel_id)
                            ->where('student_id', $a->student_id)
                            ->whereDate('rent_month', $month->toDateString())
                            ->exists();

                        if (! $exists) {
                            $rent = new MonthlyRent([
                                'student_id' => $a->student_id,
                                'rent_month' => $month,
                                'amount' => $a->fee_amount,
                                'paid_amount' => 0,
                                'due_date' => $month->copy()->day(5),
                            ]);
                            $rent->hostel_id = $a->hostel_id;
                            $rent->recalculate();
                            $rent->save();
                        }
                    } elseif ($a->fee_frequency === 'semester') {
                        $hasAny = SemesterFee::withoutGlobalScope(TenantScope::class)
                            ->where('student_id', $a->student_id)
                            ->exists();

                        if (! $hasAny) {
                            $fee = new SemesterFee([
                                'student_id' => $a->student_id,
                                'semester' => 1,
                                'total_fee' => $a->fee_amount,
                                'paid_amount' => 0,
                                'due_date' => $join->copy()->addDays(15),
                            ]);
                            $fee->hostel_id = $a->hostel_id;
                            $fee->recalculate();
                            $fee->save();
                        }
                    }
                }
            });
    }

    public function down(): void
    {
        // No-op: a data backfill isn't reversed.
    }
};
