<?php

namespace App\Services;

use App\Models\Bed;
use App\Models\BedAssignment;
use App\Models\MonthlyRent;
use App\Models\SemesterFee;
use App\Models\Student;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Owns the lifecycle of a student ↔ bed relationship.
 *
 * Guarantees:
 *  - a bed never holds two active occupants (double-allocation guard),
 *  - a student never holds two active beds,
 *  - releasing frees the bed but keeps the assignment row for history.
 */
class BedAssignmentService
{
    public function __construct(protected PaymentService $payments)
    {
    }

    /**
     * Assign a student to a bed.
     */
    public function assign(Student $student, Bed $bed, array $data): BedAssignment
    {
        return DB::transaction(function () use ($student, $bed, $data) {
            // Lock the bed row to avoid a race between two concurrent admins.
            $bed = Bed::whereKey($bed->id)->lockForUpdate()->firstOrFail();

            if (in_array($bed->status, ['occupied', 'maintenance'], true) || $bed->activeAssignment()->exists()) {
                throw ValidationException::withMessages([
                    'bed_id' => "Bed {$bed->bed_number} is not available for allocation.",
                ]);
            }

            if ($student->activeAssignment()->exists()) {
                throw ValidationException::withMessages([
                    'student_id' => "{$student->name} is already assigned to a bed. Release or transfer them first.",
                ]);
            }

            $joinDate = Carbon::parse($data['join_date'] ?? now());
            $feeAmount = (float) ($data['fee_amount'] ?? 0);
            $feeFrequency = $data['fee_frequency'] ?? 'semester';

            $assignment = $bed->assignments()->create([
                'hostel_id' => $bed->hostel_id,
                'student_id' => $student->id,
                'join_date' => $joinDate,
                'fee_amount' => $feeAmount,
                'fee_frequency' => $feeFrequency,
                // Kept in sync for the monthly-rent generator / history displays.
                'monthly_rent' => $feeFrequency === 'monthly' ? $feeAmount : 0,
                'is_active' => true,
                'remarks' => $data['remarks'] ?? null,
            ]);

            $bed->update(['status' => 'occupied']);

            // Keep the student record consistent with their occupancy.
            $student->forceFill([
                'status' => 'active',
                'join_date' => $student->join_date ?? $joinDate,
                'leave_date' => null,
            ])->save();

            // Turn the chosen fee into an actual payable due so it shows on the
            // student's profile immediately. Skipped on transfer (the due already
            // exists from the original assignment).
            if (empty($data['_is_transfer'])) {
                $this->createInitialDue($student, $joinDate, $feeAmount, $feeFrequency, $data);
            }

            return $assignment;
        });
    }

    /**
     * Create the first fee obligation for a freshly assigned student so the fee
     * entered at assignment is reflected as a due on their profile right away.
     *  - monthly   → a MonthlyRent row for the join month
     *  - semester  → a SemesterFee row for the chosen (or next free) semester
     * Idempotent: an existing row for the same period is left untouched.
     */
    protected function createInitialDue(Student $student, Carbon $joinDate, float $feeAmount, string $feeFrequency, array $data): void
    {
        if ($feeAmount <= 0) {
            return;
        }

        if ($feeFrequency === 'monthly') {
            $month = $joinDate->copy()->startOfMonth();
            $rent = MonthlyRent::firstOrNew([
                'hostel_id' => $student->hostel_id,
                'student_id' => $student->id,
                'rent_month' => $month,
            ]);

            if (! $rent->exists) {
                $rent->fill([
                    'amount' => $feeAmount,
                    'paid_amount' => 0,
                    'due_date' => $month->copy()->day(5),
                ]);
                $rent->recalculate();
                $rent->save();

                // Settle any advance the student already paid against this rent.
                $this->payments->applyAdvances($student, $rent);
            }

            return;
        }

        // Both "semester" and "yearly" are lump fees stored in semester_fees,
        // distinguished by period_type. Yearly auto-sequences (Year 1, Year 2…).
        if (in_array($feeFrequency, ['semester', 'yearly'], true)) {
            $periodType = $feeFrequency;
            $semester = $periodType === 'yearly' ? 0 : (int) ($data['semester'] ?? 0);

            if ($semester < 1) {
                // Pick the next unused period number (1..8) for this period type.
                $used = $student->semesterFees()->where('period_type', $periodType)->pluck('semester')->all();
                $semester = 1;
                while (in_array($semester, $used, true) && $semester < 8) {
                    $semester++;
                }
            }

            $fee = SemesterFee::firstOrNew([
                'hostel_id' => $student->hostel_id,
                'student_id' => $student->id,
                'period_type' => $periodType,
                'semester' => $semester,
            ]);

            if (! $fee->exists) {
                $fee->fill([
                    'total_fee' => $feeAmount,
                    'paid_amount' => 0,
                    'due_date' => $joinDate->copy()->addDays(15),
                ]);
                $fee->recalculate();
                $fee->save();

                // Settle any advance the student already paid against this fee.
                $this->payments->applyAdvances($student, $fee);
            }
        }
    }

    /**
     * Correct a mistakenly-entered fee amount / frequency on an active
     * assignment and re-sync the auto-created due.
     *  - same frequency  → adjust the existing due's amount
     *  - changed freq    → drop the old unpaid due, create the right one
     * Changing frequency is blocked once a payment exists on the old due.
     */
    public function updateFee(BedAssignment $assignment, array $data): BedAssignment
    {
        return DB::transaction(function () use ($assignment, $data) {
            $student = $assignment->student;
            $newAmount = (float) ($data['fee_amount'] ?? 0);
            $newFreq = $data['fee_frequency'] ?? $assignment->fee_frequency;
            $oldFreq = $assignment->fee_frequency;

            $oldDue = $this->latestDueFor($student, $oldFreq);
            $hasPayments = $oldDue && (float) $oldDue->paid_amount > 0;

            if ($hasPayments && $newFreq !== $oldFreq) {
                throw ValidationException::withMessages([
                    'fee_frequency' => 'A payment was already collected on this fee, so the frequency can\'t be changed. Adjust the amount only, or reverse the payment first.',
                ]);
            }

            $assignment->forceFill([
                'fee_amount' => $newAmount,
                'fee_frequency' => $newFreq,
                'monthly_rent' => $newFreq === 'monthly' ? $newAmount : 0,
            ])->save();

            if ($newFreq === $oldFreq && $oldDue) {
                // Same type — just correct the amount on the existing due.
                if ($oldDue instanceof MonthlyRent) {
                    $oldDue->amount = $newAmount;
                } else {
                    $oldDue->total_fee = $newAmount;
                }
                $oldDue->recalculate();
                $oldDue->save();
            } else {
                // Frequency changed (no payments taken) — remove the wrong due
                // and create the correct one for the new plan.
                if ($oldDue && (float) $oldDue->paid_amount <= 0) {
                    $oldDue->delete();
                }
                $this->createInitialDue($student, Carbon::parse($assignment->join_date), $newAmount, $newFreq, $data);
            }

            return $assignment;
        });
    }

    /**
     * The due auto-created for a given fee frequency (most recent), used when
     * re-syncing after a fee edit.
     */
    protected function latestDueFor(Student $student, string $frequency): ?Model
    {
        if ($frequency === 'monthly') {
            return $student->monthlyRents()->orderByDesc('rent_month')->first();
        }
        $periodType = $frequency === 'yearly' ? 'yearly' : 'semester';

        return $student->semesterFees()->where('period_type', $periodType)->orderByDesc('id')->first();
    }

    /**
     * Release a student from their bed, preserving the history row.
     */
    public function release(BedAssignment $assignment, ?string $leaveDate = null, bool $markStudentLeft = false): void
    {
        DB::transaction(function () use ($assignment, $leaveDate, $markStudentLeft) {
            $date = Carbon::parse($leaveDate ?? now());

            $assignment->forceFill([
                'leave_date' => $date,
                'is_active' => false,
            ])->save();

            $assignment->bed()->update(['status' => 'empty']);

            if ($markStudentLeft) {
                $assignment->student()->update(['status' => 'left', 'leave_date' => $date]);
            }
        });
    }

    /**
     * Move a student from their current bed to another available bed.
     * The old assignment is closed (history kept) and a new one opened.
     */
    public function transfer(BedAssignment $assignment, Bed $targetBed, array $data = []): BedAssignment
    {
        return DB::transaction(function () use ($assignment, $targetBed, $data) {
            $student = $assignment->student;

            // Carry the existing fee plan to the new bed (a transfer keeps the
            // same fee) and flag it so we don't create a second due.
            $data['fee_amount'] = $data['fee_amount'] ?? (float) $assignment->fee_amount;
            $data['fee_frequency'] = $data['fee_frequency'] ?? $assignment->fee_frequency;
            $data['_is_transfer'] = true;

            $this->release($assignment, $data['join_date'] ?? now()->toDateString());

            return $this->assign($student, $targetBed, $data);
        });
    }
}
