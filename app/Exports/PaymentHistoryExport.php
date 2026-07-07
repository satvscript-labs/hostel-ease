<?php

namespace App\Exports;

use App\Models\Student;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Excel export: a single student's payment history.
 */
class PaymentHistoryExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(protected Student $student)
    {
    }

    public function collection()
    {
        return $this->student->payments()->orderBy('paid_on')->get();
    }

    public function map($payment): array
    {
        return [
            $payment->receipt_number,
            $payment->paid_on->format('d-m-Y'),
            $payment->credit_used > 0 ? "Credit Used: " . number_format($payment->credit_used, 2) : '',
            strtoupper($payment->mode),
            $payment->reference_number,
            number_format((float) $payment->amount, 2),
        ];
    }

    public function headings(): array
    {
        return ['Receipt', 'Date', 'Type', 'Mode', 'Reference', 'Amount'];
    }
}

