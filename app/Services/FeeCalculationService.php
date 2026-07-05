<?php

namespace App\Services;

use App\Models\BedAssignment;
use Carbon\Carbon;

/**
 * Calculate fees based on student's join_date.
 * Monthly fee due 30 days after join_date (rolling anniversaries).
 * e.g., Student joins June 15 → Fee due July 15, Aug 15, Sept 15, etc.
 */
class FeeCalculationService
{
    /**
     * Calculate the next due date for a monthly fee based on join_date.
     * Returns the date 30 days after join_date (or anniversary).
     */
    public static function nextMonthlyDueDate(Carbon $joinDate, ?int $monthNumber = null): Carbon
    {
        if ($monthNumber === null) {
            $monthNumber = 1;
        }

        // Add 30 days for each month
        return $joinDate->clone()->addDays($monthNumber * 30);
    }

    /**
     * Get the month number (1, 2, 3...) based on current date and join_date.
     * Month 1 = join_date to join_date + 30 days
     * Month 2 = join_date + 30 to join_date + 60 days, etc.
     */
    public static function getCurrentMonthNumber(Carbon $joinDate, ?Carbon $asOf = null): int
    {
        $asOf = $asOf ?? now();
        $daysSinceJoin = $joinDate->diffInDays($asOf);

        // Calculate which 30-day period we're in
        return (int) ceil(($daysSinceJoin + 1) / 30);
    }

    /**
     * Calculate all due monthly fees for a student from join_date to present.
     * Returns array of due dates with fee amounts.
     * Example: Student joined June 15, fee_amount = 1000, frequency = monthly
     * Returns: [July 15 => 1000, Aug 15 => 1000, Sept 15 => 1000, ...]
     */
    public static function calculateMonthlyDuesDates(BedAssignment $assignment): array
    {
        $dues = [];
        $joinDate = $assignment->join_date;
        $now = now();

        // Generate monthly dues from join_date until today
        for ($month = 1; $month <= 60; $month++) { // Support up to 60 months (5 years)
            $dueDate = self::nextMonthlyDueDate($joinDate, $month);

            // Stop generating if due date is in the future
            if ($dueDate->isAfter($now)) {
                break;
            }

            $dues[$dueDate->toDateString()] = [
                'month' => $month,
                'due_date' => $dueDate,
                'amount' => $assignment->fee_amount,
            ];
        }

        return $dues;
    }

    /**
     * Generate a label for a monthly fee.
     * e.g., "June 15 - July 14" or "Month 1 (June 15 - July 14)"
     */
    public static function monthlyFeeLabel(Carbon $joinDate, int $monthNumber): string
    {
        $startDate = $joinDate->clone()->addDays(($monthNumber - 1) * 30);
        $endDate = $startDate->clone()->addDays(29); // 30-day period

        return "Month {$monthNumber} ({$startDate->format('M d')} - {$endDate->format('M d')})";
    }
}
