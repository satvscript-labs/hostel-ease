<?php

namespace App\Http\Controllers\Admin;

use App\Exports\LedgerSummaryExport;
use App\Exports\PaymentHistoryExport;
use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\LedgerService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class LedgerController extends Controller
{
    public function __construct(protected LedgerService $ledger)
    {
    }

    public function index(): View
    {
        $students = Student::with(['semesterFees', 'monthlyRents', 'acBillShares', 'payments'])
            ->orderBy('name')->get()
            ->map(function ($student) {
                $student->totals = $this->ledger->totalsFor($student);

                return $student;
            });

        $grand = [
            'billed' => $students->sum(fn ($s) => $s->totals['billed']),
            'paid' => $students->sum(fn ($s) => $s->totals['paid']),
            'outstanding' => $students->sum(fn ($s) => $s->totals['outstanding']),
        ];

        return view('admin.ledger.index', compact('students', 'grand'));
    }

    public function show(Student $student): View
    {
        $student->load(['semesterFees', 'monthlyRents', 'acBillShares', 'payments.collector']);

        $totals = $this->ledger->totalsFor($student);
        $obligations = $this->ledger->obligations($student);
        $payments = $student->payments->sortBy('paid_on')->values();

        return view('admin.ledger.show', compact('student', 'totals', 'obligations', 'payments'));
    }

    public function pdf(Student $student): Response
    {
        $student->load(['semesterFees', 'monthlyRents', 'acBillShares', 'payments', 'hostel']);
        $totals = $this->ledger->totalsFor($student);
        $obligations = $this->ledger->obligations($student);
        $payments = $student->payments->sortBy('paid_on')->values();

        $pdf = Pdf::loadView('admin.ledger.pdf', compact('student', 'totals', 'obligations', 'payments'));

        return $pdf->download('ledger-'.$student->id.'.pdf');
    }

    public function excel(Student $student)
    {
        return Excel::download(new PaymentHistoryExport($student), 'ledger-'.$student->id.'.xlsx');
    }

    public function exportSummary()
    {
        return Excel::download(app(LedgerSummaryExport::class), 'ledger-summary.xlsx');
    }
}
