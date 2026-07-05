<?php

namespace App\Services;

use App\Models\Student;

/**
 * Aggregates a student's financial position across every obligation type
 * (semester fees, monthly rent, AC bills) and their payments.
 */
class LedgerService
{
    /**
     * @return array{billed:float, paid:float, outstanding:float}
     */
    public function totalsFor(Student $student): array
    {
        $billed = (float) $student->semesterFees()->sum('total_fee')
            + (float) $student->monthlyRents()->sum('amount')
            + (float) $student->acBillShares()->sum('amount');

        $outstanding = (float) $student->semesterFees()->sum('balance')
            + (float) $student->monthlyRents()->sum('balance')
            + (float) ($student->acBillShares()->sum('amount') - $student->acBillShares()->sum('paid_amount'));

        $paid = (float) $student->payments()->sum('amount');

        return [
            'billed' => round($billed, 2),
            'paid' => round($paid, 2),
            'outstanding' => round(max(0, $outstanding), 2),
        ];
    }

    /**
     * Combined, date-sorted list of obligations for a student's statement.
     */
    public function obligations(Student $student): \Illuminate\Support\Collection
    {
        $rows = collect();

        foreach ($student->semesterFees as $f) {
            $rows->push([
                'date' => $f->due_date,
                'particular' => "Semester {$f->semester} Fee",
                'amount' => (float) $f->total_fee,
                'paid' => (float) $f->paid_amount,
                'balance' => (float) $f->balance,
                'status' => $f->status,
            ]);
        }

        foreach ($student->monthlyRents as $r) {
            $rows->push([
                'date' => $r->rent_month,
                'particular' => 'Rent · '.$r->rent_month->format('M Y'),
                'amount' => (float) $r->amount,
                'paid' => (float) $r->paid_amount,
                'balance' => (float) $r->balance,
                'status' => $r->status,
            ]);
        }

        foreach ($student->acBillShares as $a) {
            $rows->push([
                'date' => $a->created_at,
                'particular' => 'AC Bill',
                'amount' => (float) $a->amount,
                'paid' => (float) $a->paid_amount,
                'balance' => (float) $a->amount - (float) $a->paid_amount,
                'status' => $a->status,
            ]);
        }

        return $rows->sortBy('date')->values();
    }
}
