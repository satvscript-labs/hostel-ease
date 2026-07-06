@extends('layouts.app')
@section('title', __('Expenses'))

@section('content')
<div x-data="{ search: '' }" class="page-enter">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0">{{ __('Expenses & Outflows') }}</h1>
            <p class="text-secondary">{{ __('Manage your hostel expenses and view profit/loss.') }}</p>
        </div>
        <div>
            <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                <i class="fa-solid fa-plus me-1"></i> {{ __('Log Expense') }}
            </button>
        </div>
    </div>

    <!-- Bento Stats -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card bg-success-subtle border-success-subtle h-100">
                <div class="card-body">
                    <div class="text-success small fw-medium mb-1 text-uppercase tracking-wider">Total Income (Selected Period)</div>
                    <div class="h3 text-success mb-0 fw-bold">{{ hostelease_money($summary['income']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-danger-subtle border-danger-subtle h-100">
                <div class="card-body">
                    <div class="text-danger small fw-medium mb-1 text-uppercase tracking-wider">Total Expenses</div>
                    <div class="h3 text-danger mb-0 fw-bold">{{ hostelease_money($summary['expense']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card {{ $summary['profit'] >= 0 ? 'bg-primary-subtle border-primary-subtle' : 'bg-warning-subtle border-warning-subtle' }} h-100">
                <div class="card-body">
                    <div class="{{ $summary['profit'] >= 0 ? 'text-primary' : 'text-warning-emphasis' }} small fw-medium mb-1 text-uppercase tracking-wider">{{ $summary['profit'] >= 0 ? 'Net Profit' : 'Net Loss' }}</div>
                    <div class="h3 {{ $summary['profit'] >= 0 ? 'text-primary' : 'text-warning-emphasis' }} mb-0 fw-bold">{{ hostelease_money($summary['profit']) }}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <form method="GET" class="card card-premium mb-4">
        <div class="card-body row g-3">
            <div class="col-md-3">
                <label class="form-label text-secondary small">From Date</label>
                <input type="date" name="from" class="form-control" value="{{ $from->toDateString() }}" onchange="this.form.submit()">
            </div>
            <div class="col-md-3">
                <label class="form-label text-secondary small">To Date</label>
                <input type="date" name="to" class="form-control" value="{{ $to->toDateString() }}" onchange="this.form.submit()">
            </div>
            <div class="col-md-3">
                <label class="form-label text-secondary small">Category</label>
                <select name="category" class="form-select" onchange="this.form.submit()">
                    <option value="">All Categories</option>
                    @foreach(config('hostelease.expense_categories') as $k => $l)
                        <option value="{{ $k }}" @selected(request('category') === $k)>{{ $l }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label text-secondary small">Search</label>
                <input type="text" x-model="search" class="form-control" placeholder="Search by title...">
            </div>
        </div>
    </form>

    <!-- Expenses Table -->
    <div class="card card-premium">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Category') }}</th>
                            <th>{{ __('Title & Details') }}</th>
                            <th>{{ __('Amount') }}</th>
                            <th class="text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($expenses as $expense)
                        <tr x-show="search === '' || '{{ strtolower($expense->title . ' ' . $expense->paid_to) }}'.includes(search.toLowerCase())">
                            <td>
                                <div class="fw-medium">{{ $expense->expense_date->format('d M Y') }}</div>
                                <div class="text-secondary small">{{ $expense->mode }}</div>
                            </td>
                            <td>
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill">
                                    {{ config('hostelease.expense_categories.'.$expense->category, $expense->category) }}
                                </span>
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $expense->title }}</div>
                                @if($expense->paid_to)
                                    <div class="text-secondary small"><i class="fa-solid fa-user-tag me-1"></i>{{ $expense->paid_to }}</div>
                                @endif
                                @if($expense->reference_number)
                                    <div class="text-secondary small"><i class="fa-solid fa-hashtag me-1"></i>{{ $expense->reference_number }}</div>
                                @endif
                            </td>
                            <td class="text-danger fw-bold fs-5">{{ hostelease_money($expense->amount) }}</td>
                            <td class="text-end">
                                <form action="{{ route('admin.expenses.destroy', $expense) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this expense?');">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-light text-danger"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <div class="empty-state">
                                    <i class="fa-solid fa-money-bill-trend-up text-secondary fs-1 mb-2"></i>
                                    <div class="text-secondary">No expenses found for this period.</div>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- Add Expense Modal -->
<div class="modal fade" id="addExpenseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" action="{{ route('admin.expenses.store') }}" method="POST">
            @csrf
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Log Expense</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Category *</label>
                        <select name="category" class="form-select" required>
                            @foreach(config('hostelease.expense_categories') as $k=>$l)
                                <option value="{{ $k }}">{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Date *</label>
                        <input type="date" name="expense_date" class="form-control" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Title / Description *</label>
                        <input type="text" name="title" class="form-control" required placeholder="e.g. Plumber for Room 101">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Amount *</label>
                        <div class="input-group">
                            <span class="input-group-text">₹</span>
                            <input type="number" step="0.01" min="1" name="amount" class="form-control" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Payment Mode</label>
                        <select name="mode" class="form-select">
                            @foreach(config('hostelease.payment_modes') as $k=>$l)
                                <option value="{{ $k }}">{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Paid To</label>
                        <input type="text" name="paid_to" class="form-control" placeholder="Person or Vendor Name">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Reference No.</label>
                        <input type="text" name="reference_number" class="form-control" placeholder="Invoice or Bill no.">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes (Optional)</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light rounded-bottom">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-dark">Log Expense</button>
            </div>
        </form>
    </div>
</div>
@endsection
