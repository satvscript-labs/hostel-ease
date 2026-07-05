@extends('layouts.app')
@section('title', 'Expenses')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 fw-bold mb-0">Expenses & Profit/Loss</h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#expModal"><i class="fa-solid fa-plus me-1"></i> Add Expense</button>
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-6 col-md-3"><input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control form-control-sm"></div>
    <div class="col-6 col-md-3"><input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control form-control-sm"></div>
    <div class="col-6 col-md-3">
        <select name="category" class="form-select form-select-sm">
            <option value="">All categories</option>
            @foreach(config('hsms.expense_categories') as $k=>$l)<option value="{{ $k }}" @selected(request('category')===$k)>{{ $l }}</option>@endforeach
        </select>
    </div>
    <div class="col-6 col-md-3"><button class="btn btn-primary btn-sm"><i class="fa-solid fa-filter me-1"></i> Filter</button></div>
</form>

<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card stat-card"><div class="card-body py-3"><div class="stat-value text-success">{{ hsms_money($summary['income']) }}</div><div class="stat-label">Income (period)</div></div></div></div>
    <div class="col-md-4"><div class="card stat-card"><div class="card-body py-3"><div class="stat-value text-danger">{{ hsms_money($summary['expense']) }}</div><div class="stat-label">Expenses (period)</div></div></div></div>
    <div class="col-md-4"><div class="card stat-card"><div class="card-body py-3"><div class="stat-value {{ $summary['profit'] >= 0 ? 'text-success' : 'text-danger' }}">{{ hsms_money($summary['profit']) }}</div><div class="stat-label">{{ $summary['profit'] >= 0 ? 'Profit' : 'Loss' }}</div></div></div></div>
</div>

<div class="card stat-card"><div class="card-body">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" data-datatable>
            <thead><tr><th>Date</th><th>Category</th><th>Title</th><th>Paid To</th><th>Mode</th><th class="text-end">Amount</th><th></th></tr></thead>
            <tbody>
            @forelse($expenses as $e)
                <tr>
                    <td>{{ $e->expense_date->format('d-m-Y') }}</td>
                    <td><span class="badge bg-secondary-subtle text-secondary">{{ config('hsms.expense_categories.'.$e->category, $e->category) }}</span></td>
                    <td>{{ $e->title }}</td>
                    <td>{{ $e->paid_to ?? '—' }}</td>
                    <td class="text-uppercase">{{ $e->mode }}</td>
                    <td class="text-end fw-semibold">{{ hsms_money($e->amount) }}</td>
                    <td class="text-end">
                        <form action="{{ route('admin.expenses.destroy', $e) }}" method="POST" class="d-inline" data-confirm="Delete this expense?">
                            @csrf @method('DELETE')<button class="btn btn-sm btn-light text-danger"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No expenses in this period.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div></div>

<div class="modal fade" id="expModal" tabindex="-1"><div class="modal-dialog">
    <form class="modal-content" method="POST" action="{{ route('admin.expenses.store') }}">@csrf
        <div class="modal-header"><h5 class="modal-title">Add Expense</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="row g-3">
                <div class="col-6"><label class="form-label">Category</label>
                    <select name="category" class="form-select" required>@foreach(config('hsms.expense_categories') as $k=>$l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                <div class="col-6"><label class="form-label">Date</label><input type="date" name="expense_date" class="form-control" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required></div>
                <div class="col-12"><label class="form-label">Title</label><input type="text" name="title" class="form-control" required></div>
                <div class="col-6"><label class="form-label">Amount (₹)</label><input type="number" step="0.01" min="1" name="amount" class="form-control" required></div>
                <div class="col-6"><label class="form-label">Mode</label>
                    <select name="mode" class="form-select">@foreach(config('hsms.payment_modes') as $k=>$l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                <div class="col-6"><label class="form-label">Paid To</label><input type="text" name="paid_to" class="form-control"></div>
                <div class="col-6"><label class="form-label">Reference No.</label><input type="text" name="reference_number" class="form-control"></div>
                <div class="col-12"><label class="form-label">Notes</label><input type="text" name="notes" class="form-control"></div>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
    </form>
</div></div>
@endsection
