<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ReportExport;
use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    /** Report definitions: key => [label, needsDateRange]. */
    protected array $types = [
        'collection' => ['Collection', true],
        'income' => ['Income by Mode', true],
        'occupancy' => ['Occupancy', false],
        'pending' => ['Pending Fees', false],
        'ac' => ['AC Bills', true],
        'expenses' => ['Expenses by Category', true],
        'expense_monthly' => ['Expenses by Month', true],
    ];

    public function __construct(protected ReportService $reports)
    {
    }

    public function index(): View
    {
        return view('admin.reports.index', ['types' => $this->types]);
    }

    public function show(Request $request, string $type)
    {
        abort_unless(isset($this->types[$type]), 404);

        [$label, $needsRange] = $this->types[$type];

        $period = $request->input('period', 'monthly');
        $from = $request->filled('from') ? Carbon::parse($request->date('from')) : now()->startOfMonth();
        $to = $request->filled('to') ? Carbon::parse($request->date('to')) : now()->endOfDay();

        $data = match ($type) {
            'collection' => $this->reports->collection($period, $from, $to),
            'income' => $this->reports->incomeByMode($from, $to),
            'occupancy' => $this->reports->occupancy(),
            'pending' => $this->reports->pendingFees(),
            'ac' => $this->reports->acReport($from, $to),
            'expenses' => $this->reports->expensesByCategory($from, $to),
            'expense_monthly' => $this->reports->expensesByMonth($from, $to),
        };

        $title = $label.' Report';

        if ($request->input('export') === 'excel') {
            return Excel::download(
                new ReportExport($data['headings'], $data['rows'], $title),
                str($type)->slug().'-report.xlsx'
            );
        }

        if ($request->input('export') === 'pdf') {
            return Pdf::loadView('admin.reports.pdf', compact('title', 'data', 'from', 'to', 'needsRange', 'period'))
                ->download(str($type)->slug().'-report.pdf');
        }

        return view('admin.reports.show', compact('type', 'label', 'title', 'data', 'needsRange', 'period', 'from', 'to'))
            ->with('types', $this->types);
    }
}
