@extends('layouts.app')
@section('title', 'Payment Ledger')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 fw-bold mb-0">Payment Ledger</h1>
    <a href="{{ route('admin.ledger.export.summary') }}" class="btn btn-success"><i class="fa-solid fa-file-excel me-1"></i> Export Excel</a>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card stat-card"><div class="card-body py-3"><div class="stat-value">{{ hsms_money($grand['billed']) }}</div><div class="stat-label">Total Billed</div></div></div></div>
    <div class="col-md-4"><div class="card stat-card"><div class="card-body py-3"><div class="stat-value text-success">{{ hsms_money($grand['paid']) }}</div><div class="stat-label">Total Collected</div></div></div></div>
    <div class="col-md-4"><div class="card stat-card"><div class="card-body py-3"><div class="stat-value text-danger">{{ hsms_money($grand['outstanding']) }}</div><div class="stat-label">Total Outstanding</div></div></div></div>
</div>

<div class="card stat-card"><div class="card-body">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" data-datatable>
            <thead><tr><th>Student</th><th>Occupation</th><th class="text-end">Billed</th><th class="text-end">Paid</th><th class="text-end">Outstanding</th><th class="text-end">Ledger</th></tr></thead>
            <tbody>
            @foreach($students as $s)
                <tr>
                    <td class="fw-semibold">{{ $s->name }}</td>
                    <td>{{ config('hsms.occupation_types.'.$s->occupation_type) }}</td>
                    <td class="text-end">{{ hsms_money($s->totals['billed']) }}</td>
                    <td class="text-end text-success">{{ hsms_money($s->totals['paid']) }}</td>
                    <td class="text-end {{ $s->totals['outstanding'] > 0 ? 'text-danger fw-semibold' : 'text-muted' }}">{{ hsms_money($s->totals['outstanding']) }}</td>
                    <td class="text-end"><a href="{{ route('admin.ledger.show', $s) }}" class="btn btn-sm btn-light"><i class="fa-solid fa-book-open"></i></a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div></div>
@endsection
