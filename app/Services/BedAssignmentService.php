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

            $assignment = $bed->assignments()->create([
                'hostel_id' => $bed->hostel_id,
                'student_id' => $student->id,
                'join_date' => $joinDate,
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

            return $assignment;
        });
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
            $this->release($assignment, $data['join_date'] ?? now()->toDateString());
            return $this->assign($student, $targetBed, $data);
        });
    }
}
