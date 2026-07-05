@extends('layouts.app')
@section('title', 'Monthly Rent')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 fw-bold mb-0">Monthly Rent <small class="text-muted">· {{ $month->format('M Y') }}</small></h1>
    <form method="POST" action="{{ route('admin.monthly-rents.generate') }}" data-confirm="Generate rent rows for {{ $month->format('M Y') }} from active working professionals' assignments?">
        @csrf
        <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
        <button class="btn btn-primary"><i class="fa-solid fa-wand-magic-sparkles me-1"></i> Generate for {{ $month->format('M Y') }}</button>
    </form>
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-6 col-md-3">
        <label class="form-label small mb-1">Month</label>
        <input type="month" name="month" value="{{ $month->format('Y-m') }}" class="form-control form-control-sm" onchange="this.form.submit()">
    </div>
    <div class="col-6 col-md-3">
        <label class="form-label small mb-1">Status</label>
        <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">All</option>
            @foreach(['due','partial','paid'] as $st)<option value="{{ $st }}" @selected(request('status')===$st)>{{ ucfirst($st) }}</option>@endforeach
        </select>
    </div>
</form>

<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card stat-card"><div class="card-body py-3"><div class="stat-value">{{ hostelease_money($summary['amount']) }}</div><div class="stat-label">Billed</div></div></div></div>
    <div class="col-md-4"><div class="card stat-card"><div class="card-body py-3"><div class="stat-value text-success">{{ hostelease_money($summary['paid']) }}</div><div class="stat-label">Collected</div></div></div></div>
    <div class="col-md-4"><div class="card stat-card"><div class="card-body py-3"><div class="stat-value text-danger">{{ hostelease_money($summary['due']) }}</div><div class="stat-label">Due</div></div></div></div>
</div>

<div class="card stat-card"><div class="card-body">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" data-datatable>
            <thead><tr><th>Student</th><th>Amount</th><th>Paid</th><th>Balance</th><th>Due Date</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            @forelse($rows as $r)
                <tr>
                    <td><a href="{{ route('admin.students.show', $r->student) }}" class="text-decoration-none">{{ $r->student->name }}</a></td>
                    <td>{{ hostelease_money($r->amount) }}</td>
                    <td class="text-success">{{ hostelease_money($r->paid_amount) }}</td>
                    <td class="text-danger">{{ hostelease_money($r->balance) }}</td>
                    <td>{{ optional($r->due_date)->format('d-m-Y') ?? '—' }}</td>
                    <td>
                        <span class="badge bg-{{ $r->status==='paid'?'success':($r->status==='partial'?'warning text-dark':'danger') }}">{{ ucfirst($r->status) }}</span>
                        @if($r->promise_date && $r->status !== 'paid')
                            <span class="badge bg-info-subtle text-info" title="{{ $r->promise_note }}"><i class="fa-solid fa-clock me-1"></i>{{ $r->promise_date->format('d-m') }}</span>
                        @endif
                    </td>
                    <td class="text-end">
                        @if($r->status !== 'paid')
                            <button class="btn btn-sm btn-primary" onclick="prepCollect('{{ route('admin.monthly-rents.collect', $r) }}', '{{ $month->format('M Y') }} · {{ $r->student->name }}', {{ $r->balance }})">
                                <i class="fa-solid fa-indian-rupee-sign me-1"></i> Collect
                            </button>
                            <button class="btn btn-sm btn-light" title="Promise to pay"
                                onclick="prepPromise('{{ route('admin.promise.update', ['monthly_rent', $r->id]) }}', '{{ $r->student->name }}', '{{ optional($r->promise_date)->format('Y-m-d') }}', @js($r->promise_note))">
                                <i class="fa-solid fa-calendar-check"></i>
                            </button>
                        @endif
                        <form action="{{ route('admin.monthly-rents.destroy', $r) }}" method="POST" class="d-inline" data-confirm="Delete this rent row?">
                            @csrf @method('DELETE')<button class="btn btn-sm btn-light text-danger"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">
                    No rent rows for {{ $month->format('M Y') }}. Click <strong>Generate</strong> to create them from active working professionals.
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div></div>

@include('admin.partials.collect_modal')
@include('admin.partials.promise_modal')
@endsection

