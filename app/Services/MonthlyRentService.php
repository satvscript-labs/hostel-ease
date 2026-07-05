<?php

namespace App\Services;

use App\Models\MonthlyRent;
use App\Models\Student;
use Illuminate\Support\Carbon;

/**
 * Generates monthly rent rows for working professionals based on their active
 * bed assignment, and keeps each row's balance/status in sync.
 */
class MonthlyRentService
{
    /**
     * Create rent rows for all active working professionals for the given month.
     * Idempotent: existing rows for the month are skipped.
     *
     * @return int number of rows created
     */
    public function generateForMonth(Carbon $month, ?int $hostelId = null): int
    {
        $month = $month->copy()->startOfMonth();
        $created = 0;

        $students = Student::query()
            ->active()
            ->whereHas('activeAssignment', fn ($q) => $q->where('fee_frequency', 'monthly'))
            ->with('activeAssignment')
            ->when($hostelId, fn ($q) => $q->where('hostel_id', $hostelId))
            ->get();

        foreach ($students as $student) {
            $amount = (float) ($student->activeAssignment->fee_amount ?? 0);

            if ($amount <= 0) {
                continue;
            }

            // Match on the same Carbon value used when storing, so the date
            // serialises identically on both the WHERE and the INSERT (keeps
            // generation idempotent — string 'Y-m-d' would not match the stored
            // 'Y-m-d 00:00:00').
            $rent = MonthlyRent::firstOrNew([
                'hostel_id' => $student->hostel_id,
                'student_id' => $student->id,
                'rent_month' => $month->copy(),
            ]);

            if ($rent->exists) {
                continue;
            }

            $rent->fill([
                'amount' => $amount,
                'paid_amount' => 0,
                'due_date' => $month->copy()->day(5),
            ]);
            $rent->recalculate();
            $rent->save();
            $created++;
        }

        return $created;
    }
}
