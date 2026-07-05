@extends('layouts.app')
@section('title', 'Ledger · '.$student->name)

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="d-flex align-items-center gap-2">
        <a href="{{ route('admin.ledger.index') }}" class="btn btn-light btn-sm"><i class="fa-solid fa-arrow-left"></i></a>
        <h1 class="h4 fw-bold mb-0">Ledger — {{ $student->name }}</h1>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.ledger.pdf', $student) }}" class="btn btn-light"><i class="fa-solid fa-file-pdf me-1"></i> PDF</a>
        <a href="{{ route('admin.ledger.excel', $student) }}" class="btn btn-success"><i class="fa-solid fa-file-excel me-1"></i> Excel</a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card stat-card"><div class="card-body py-3"><div class="stat-value">{{ hsms_money($totals['billed']) }}</div><div class="stat-label">Total Fees</div></div></div></div>
    <div class="col-md-4"><div class="card stat-card"><div class="card-body py-3"><div class="stat-value text-success">{{ hsms_money($totals['paid']) }}</div><div class="stat-label">Total Paid</div></div></div></div>
    <div class="col-md-4"><div class="card stat-card"><div class="card-body py-3"><div class="stat-value text-danger">{{ hsms_money($totals['outstanding']) }}</div><div class="stat-label">Remaining</div></div></div></div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card stat-card h-100"><div class="card-body">
            <h2 class="h6 fw-bold mb-3">Obligations</h2>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                <thead><tr><th>Date</th><th>Particular</th><th class="text-end">Amount</th><th class="text-end">Balance</th><th>Status</th></tr></thead>
                <tbody>
                @forelse($obligations as $o)
                    <tr>
                        <td>{{ optional($o['date'])->format('d-m-Y') ?? '—' }}</td>
                        <td>{{ $o['particular'] }}</td>
                        <td class="text-end">{{ hsms_money($o['amount']) }}</td>
                        <td class="text-end">{{ hsms_money($o['balance']) }}</td>
                        <td><span class="badge bg-{{ $o['status']==='paid'?'success':($o['status']==='pending'||$o['status']==='due'?'danger':'warning text-dark') }}">{{ ucfirst($o['status']) }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-3">No obligations recorded.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div></div>
    </div>
    <div class="col-lg-6">
        <div class="card stat-card h-100"><div class="card-body">
            <h2 class="h6 fw-bold mb-3">Payment History</h2>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                <thead><tr><th>Receipt</th><th>Date</th><th>Mode</th><th class="text-end">Amount</th><th></th></tr></thead>
                <tbody>
                @forelse($payments as $p)
                    <tr>
                        <td>{{ $p->receipt_number }}</td>
                        <td>{{ $p->paid_on->format('d-m-Y') }}</td>
                        <td class="text-uppercase">{{ $p->mode }}</td>
                        <td class="text-end">{{ hsms_money($p->amount) }}</td>
                        <td class="text-end"><a href="{{ route('admin.payments.show', $p) }}" class="btn btn-sm btn-light"><i class="fa-solid fa-receipt"></i></a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-3">No payments yet.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div></div>
    </div>
</div>
@endsection
