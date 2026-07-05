@extends('layouts.app')
@section('title', 'Fees & Payments')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 fw-bold mb-0">Fees & Payments</h1>
    <a href="{{ route('admin.payments.create') }}" class="btn btn-primary">
        <i class="fa-solid fa-plus me-1"></i> Record Payment
    </a>
</div>

<form method="GET" class="card stat-card mb-3"><div class="card-body">
    <div class="row g-2 align-items-end">
        <div class="col-6 col-md-3">
            <label class="form-label small mb-1">From</label>
            <input type="date" name="from" value="{{ request('from') }}" class="form-control form-control-sm">
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label small mb-1">To</label>
            <input type="date" name="to" value="{{ request('to') }}" class="form-control form-control-sm">
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label small mb-1">Mode</label>
            <select name="mode" class="form-select form-select-sm">
                <option value="">All modes</option>
                @foreach(\App\Models\PaymentMode::options() as $m)<option value="{{ $m->code }}" @selected(request('mode')===$m->code)>{{ $m->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-6 col-md-3 d-flex gap-2">
            <button class="btn btn-primary btn-sm flex-grow-1"><i class="fa-solid fa-filter me-1"></i> Filter</button>
            <a href="{{ route('admin.payments.index') }}" class="btn btn-light btn-sm">Reset</a>
        </div>
    </div>
</div></form>

<div class="alert alert-success py-2"><strong>Total (filtered):</strong> {{ hsms_money($total) }} across {{ $payments->count() }} payment(s).</div>

<div class="card stat-card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" data-datatable>
                <thead>
                    <tr><th>Receipt</th><th>Date</th><th>Student</th><th>Type</th><th>Mode</th><th>Ref.</th><th class="text-end">Amount</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                @foreach($payments as $p)
                    <tr>
                        <td class="fw-semibold">{{ $p->receipt_number }}</td>
                        <td>{{ $p->paid_on->format('d-m-Y') }}</td>
                        <td><a href="{{ route('admin.students.show', $p->student) }}" class="text-decoration-none">{{ $p->student->name }}</a></td>
                        <td>{{ config('hsms.payment_types.'.$p->payment_type) }}</td>
                        <td><span class="badge bg-secondary-subtle text-secondary text-uppercase">{{ $p->mode }}</span></td>
                        <td>{{ $p->reference_number ?? '—' }}</td>
                        <td class="text-end fw-semibold">{{ hsms_money($p->amount) }}</td>
                        <td class="text-end">
                            <a href="{{ route('admin.payments.show', $p) }}" class="btn btn-sm btn-light"><i class="fa-solid fa-receipt"></i></a>
                            <a href="{{ route('admin.payments.pdf', $p) }}" class="btn btn-sm btn-light"><i class="fa-solid fa-file-pdf"></i></a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
