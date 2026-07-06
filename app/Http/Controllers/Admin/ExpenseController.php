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
        $from = $request->filled('from') ? Carbon::parse($request->date('from')) : now()->startOfMonth();
        $to = $request->filled('to') ? Carbon::parse($request->date('to')) : now()->endOfDay();

        $expenses = Expense::with('recorder')
            ->whereBetween('expense_date', [$from, $to])
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->category))
            ->orderByDesc('expense_date')->orderByDesc('id')
            ->get();

        // Profit / loss against income (payments) for the same window.
        $income = (float) Payment::whereBetween('paid_on', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->sum('amount');
        $totalExpense = (float) $expenses->sum('amount');

        $summary = [
            'income' => $income,
            'expense' => $totalExpense,
            'profit' => $income - $totalExpense,
            'by_category' => $expenses->groupBy('category')->map->sum('amount'),
        ];

        return view('admin.expenses.index', compact('expenses', 'summary', 'from', 'to'));
    }



    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $expense = Expense::create($request->validated() + ['recorded_by' => Auth::id()]);

        $this->logger->log('expense.create', "Expense {$expense->category} ".hostelease_money($expense->amount), $expense);

        return back()->with('success', 'Expense recorded.');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $this->logger->log('expense.delete', "Deleted expense #{$expense->id}", $expense);
        $expense->delete();

        return back()->with('success', 'Expense removed.');
    }
}

