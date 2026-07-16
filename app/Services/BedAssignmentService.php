<?php

namespace App\Services;

use App\Models\Bed;
use App\Models\BedAssignment;
use App\Models\Student;
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
    /**
     * Assign a student to a bed.
     */
    public function assign(Student $student, Bed $bed, array $data): BedAssignment
    {
        return DB::transaction(function () use ($student, $bed, $data) {
            // Lock the bed row to avoid a race between two concurrent admins.
            // with('room'): the re-fetch drops any eager load the caller had,
            // and strict mode (shouldBeStrict) turns the rent lookup below
            // into an exception rather than a lazy query.
            $bed = Bed::with('room')->whereKey($bed->id)->lockForUpdate()->firstOrFail();

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

            $assignment = $bed->assignments()->create([
                'hostel_id' => $bed->hostel_id,
                'student_id' => $student->id,
                'join_date' => $joinDate,
                // The room's AC meter at move-in (W6.3): the anchor that lets
                // the bill split on REAL consumption instead of a day
                // estimate. The controller requires it for AC rooms; the
                // service stays optional so seeders/tests/non-AC stay simple.
                'join_meter_reading' => $data['meter_reading'] ?? null,
                // The room's rent AT THIS MOMENT — room rents change; the
                // stay's history shouldn't silently change with them.
                'monthly_rent' => $bed->room?->rent ?? 0,
                'is_active' => true,
                'remarks' => $data['remarks'] ?? null,
            ]);

            $bed->update(['status' => 'occupied']);

            // Keep the student record consistent with their occupancy, and
            // re-price them for THIS room in the same transaction (W6.4): the
            // student holds one current plan and billing reads it, so a move
            // that didn't update it left every future invoice charging for a
            // room the student had already left.
            $studentUpdates = [
                'status' => 'active',
                'join_date' => $student->join_date ?? $joinDate,
                'leave_date' => null,
            ];
            if (isset($data['fee_amount'])) {
                $studentUpdates['fee_amount'] = $data['fee_amount'];
            }
            if (isset($data['fee_frequency'])) {
                $studentUpdates['fee_frequency'] = $data['fee_frequency'];
            }
            $student->forceFill($studentUpdates)->save();

            return $assignment;
        });
    }

    /**
     * Release a student from their bed, preserving the history row.
     * $meterReading = the room's AC meter at move-out (W6.3) — caps this
     * student's consumption in the month's bill at the real number.
     */
    public function release(BedAssignment $assignment, ?string $leaveDate = null, bool $markStudentLeft = false, ?float $meterReading = null): void
    {
        DB::transaction(function () use ($assignment, $leaveDate, $markStudentLeft, $meterReading) {
            $date = Carbon::parse($leaveDate ?? now());

            $assignment->forceFill([
                'leave_date' => $date,
                'leave_meter_reading' => $meterReading,
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
     *
     * TWO meters can be involved (W6.3): $data['old_meter_reading'] is the
     * room being LEFT (stamps leave_meter_reading on the closed row);
     * $data['meter_reading'] is the room being ENTERED (stamps
     * join_meter_reading on the new row, via assign()).
     */
    public function transfer(BedAssignment $assignment, Bed $targetBed, array $data = []): BedAssignment
    {
        return DB::transaction(function () use ($assignment, $targetBed, $data) {
            $student = $assignment->student;
            $this->release(
                $assignment,
                $data['join_date'] ?? now()->toDateString(),
                meterReading: isset($data['old_meter_reading']) ? (float) $data['old_meter_reading'] : null,
            );

            return $this->assign($student, $targetBed, $data);
        });
    }
}
