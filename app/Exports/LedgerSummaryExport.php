<?php

namespace App\Exports;

use App\Models\Student;
use App\Services\LedgerService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Excel export: one row per student with billed / paid / outstanding totals.
 */
class LedgerSummaryExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(protected LedgerService $ledger)
    {
    }

    public function collection()
    {
        return Student::with(['invoices', 'payments'])
            ->orderBy('name')->get();
    }

    public function map($student): array
    {
        $t = $this->ledger->totalsFor($student);

        return [
            $student->name,
            hostelease_phone($student->mobile),
            config('hostelease.occupation_types.'.$student->occupation_type),
            number_format($t['billed'], 2),
            number_format($t['paid'], 2),
            number_format($t['outstanding'], 2),
            ucfirst($student->status),
        ];
    }

    public function headings(): array
    {
        return ['Student', 'Mobile', 'Occupation', 'Billed', 'Paid', 'Outstanding', 'Status'];
    }
}

