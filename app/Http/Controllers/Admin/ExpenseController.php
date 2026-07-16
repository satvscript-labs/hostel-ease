<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExpenseRequest;
use App\Models\Expense;
use App\Models\Payment;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function __construct(protected ActivityLogger $logger)
    {
    }

    public function index(Request $request): View
    {
        // rescue(): ?from=garbage used to 500 the page — $request->date()
        // throws InvalidFormatException on unparseable input (same class of
        // crash fixed on Front Desk in W5). Bad input falls back to defaults.
        $from = rescue(fn () => $request->filled('from') ? Carbon::parse($request->date('from'))->startOfDay() : null, null, false)
            ?? now()->startOfMonth();
        $to = rescue(fn () => $request->filled('to') ? Carbon::parse($request->date('to'))->endOfDay() : null, null, false)
            ?? now()->endOfDay();

        $search = $request->input('search');
        $category = $request->input('category');

        // W6.2: search/filter moved server-side (the old page filtered
        // client-side over an unbounded ->get()) and the list paginates.
        $expensesQuery = Expense::with('recorder', 'salaryPayment.staff')
            ->whereBetween('expense_date', [$from, $to])
            ->when($category, fn ($q) => $q->where('category', $category))
            ->when($search, fn ($q) => $q->where(function ($query) use ($search) {
                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('paid_to', 'like', "%{$search}%")
                    ->orWhere('reference_number', 'like', "%{$search}%");
            }))
            ->orderByDesc('expense_date')->orderByDesc('id');

        $expenses = $expensesQuery->paginate(15)->withQueryString();

        // The P&L summarizes the WHOLE date window, never the current page or
        // the search — a search that narrows the list must not shrink the
        // month's profit. Aggregates run as their own queries (the old page
        // derived by_category from the render collection, which pagination
        // would have silently broken).
        //
        // Income is cash-only via Payment::income() (owner decision, W6.2):
        // 'credit' rows re-count money that was already income when it first
        // arrived, and 'credit_note' rows are refunds. The old sum('amount')
        // over every row overstated profit by both.
        $income = (float) Payment::income()
            ->whereBetween('paid_on', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->sum('amount');

        $windowExpenses = Expense::whereBetween('expense_date', [$from, $to])
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->pluck('total', 'category')
            ->map(fn ($t) => (float) $t);

        $totalExpense = (float) $windowExpenses->sum();

        $summary = [
            'income' => $income,
            'expense' => $totalExpense,
            'profit' => $income - $totalExpense,
            'by_category' => $windowExpenses->sortDesc(),
        ];

        // Active modes feed the Log/Edit form; the label map includes INACTIVE
        // modes too — a mode the owner retired last month still names the
        // historical rows that were paid through it.
        $paymentModes = \App\Models\PaymentMode::active()->orderBy('sort_order')->orderBy('name')->get();
        $modeNames = \App\Models\PaymentMode::pluck('name', 'code');

        return view('admin.expenses.index', compact('expenses', 'summary', 'from', 'to', 'search', 'category', 'paymentModes', 'modeNames'));
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $expense = Expense::create($request->validated() + ['recorded_by' => Auth::id()]);

        $this->logger->log('expense.create', "Expense {$expense->category} ".hostelease_money($expense->amount), $expense);

        return back()->with('success', 'Expense recorded.');
    }

    /** New in W6.2 — a typo'd amount used to mean delete + re-enter. */
    public function update(StoreExpenseRequest $request, Expense $expense): RedirectResponse
    {
        // Salary mirrors are managed from the Staff page: editing one here
        // would desync it from the salary payment it exists to reflect.
        if ($expense->isSalaryLinked()) {
            return back()->with('error', 'This entry mirrors a staff salary payment — edit it from the staff member\'s page instead.');
        }

        $expense->update($request->validated());

        $this->logger->log('expense.update', "Expense #{$expense->id} edited: {$expense->title} ".hostelease_money($expense->amount), $expense);

        return back()->with('success', 'Expense updated.');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        if ($expense->isSalaryLinked()) {
            return back()->with('error', 'This entry mirrors a staff salary payment — remove the salary entry from the staff member\'s page and this expense goes with it.');
        }

        $this->logger->log('expense.delete', "Deleted expense #{$expense->id}", $expense);
        $expense->delete();

        return back()->with('success', 'Expense removed.');
    }
}
