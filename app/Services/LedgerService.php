<?php

namespace App\Services;

use App\Models\Student;

/**
 * Aggregates a student's financial position from their unified invoices
 * (fee, rent, ac, other) and payments.
 */
class LedgerService
{
    /**
     * @return array{billed:float, paid:float, outstanding:float}
     */
    public function totalsFor(Student $student): array
    {
        $billed = (float) $student->invoices()->sum('amount');
        $outstanding = (float) $student->invoices()->where('status', '!=', 'paid')->sum('balance');
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
        return $student->invoices()
            ->get()
            ->map(fn ($invoice) => [
                'date' => $invoice->due_date ?? $invoice->created_at,
                'particular' => $invoice->title,
                'amount' => (float) $invoice->amount,
                'paid' => (float) $invoice->paid_amount,
                'balance' => (float) $invoice->balance,
                'status' => $invoice->status,
            ])
            ->sortBy('date')
            ->values();
    }
}
