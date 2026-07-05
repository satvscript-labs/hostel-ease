<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Payment;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    public function __construct(protected ActivityLogger $logger)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $from = $request->filled('from') ? Carbon::parse($request->date('from')) : now()->startOfMonth();
        $to = $request->filled('to') ? Carbon::parse($request->date('to')) : now()->endOfDay();

        $expenses = Expense::between($from, $to)
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->category))
            ->orderByDesc('expense_date')->orderByDesc('id')
            ->get();

        $income = (float) Payment::whereBetween('paid_on', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->sum('amount');
        $totalExpense = (float) $expenses->sum('amount');

        return response()->json([
            'expenses' => $expenses->map(fn ($e) => [
                'id' => $e->id,
                'category' => $e->category,
                'category_label' => config('hostelease.expense_categories.'.$e->category, $e->category),
                'title' => $e->title,
                'amount' => (float) $e->amount,
                'expense_date' => $e->expense_date?->toDateString(),
                'paid_to' => $e->paid_to,
                'mode' => $e->mode,
            ]),
            'summary' => [
                'income' => $income,
                'expense' => $totalExpense,
                'profit' => $income - $totalExpense,
            ],
            'categories' => config('hostelease.expense_categories'),
            'modes' => config('hostelease.payment_modes'),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category' => ['required', Rule::in(array_keys(config('hostelease.expense_categories')))],
            'title' => ['required', 'string', 'max:150'],
            'amount' => ['required', 'numeric', 'min:1', 'max:9999999'],
            'expense_date' => ['required', 'date', 'before_or_equal:today'],
            'paid_to' => ['nullable', 'string', 'max:150'],
            'mode' => ['required', Rule::in(array_keys(config('hostelease.payment_modes')))],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $expense = Expense::create($data + ['recorded_by' => $request->user()->id]);
        $this->logger->log('expense.create', "Expense {$expense->category}", $expense);

        return response()->json(['message' => 'Expense recorded.', 'id' => $expense->id], 201);
    }

    public function destroy(int $expense): JsonResponse
    {
        $model = Expense::findOrFail($expense);
        $this->logger->log('expense.delete', "Deleted expense #{$model->id}", $model);
        $model->delete();

        return response()->json(['message' => 'Expense removed.']);
    }
}

