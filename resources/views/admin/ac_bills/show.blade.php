@extends('layouts.app')
@section('title', 'AC Bill · '.$bill->room->room_number)

@section('content')
<div class="d-flex align-items-center gap-2 mb-3">
    <a href="{{ route('admin.ac-bills.index') }}" class="btn btn-light btn-sm"><i class="fa-solid fa-arrow-left"></i></a>
    <h1 class="h4 fw-bold mb-0">AC Bill — Room {{ $bill->room->room_number }} · {{ $bill->bill_month->format('M Y') }}</h1>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="card stat-card"><div class="card-body py-3"><div class="stat-value">{{ rtrim(rtrim(number_format($bill->total_units, 2), '0'), '.') }}</div><div class="stat-label">Units ({{ $bill->previous_unit }} → {{ $bill->current_unit }})</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card stat-card"><div class="card-body py-3"><div class="stat-value">{{ hostelease_money($bill->unit_price) }}</div><div class="stat-label">Per Unit</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card stat-card"><div class="card-body py-3"><div class="stat-value text-primary">{{ hostelease_money($bill->total_amount) }}</div><div class="stat-label">Total Bill</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card stat-card"><div class="card-body py-3"><div class="stat-value">{{ ucfirst($bill->distribution) }}</div><div class="stat-label">Distribution · {{ $bill->shares->count() }} students</div></div></div></div>
</div>

<div class="card stat-card"><div class="card-body">
    <h2 class="h6 fw-bold mb-3">Per-Student Shares</h2>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Student</th><th class="text-end">Share</th><th class="text-end">Paid</th><th class="text-end">Balance</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            @foreach($bill->shares as $share)
                <tr>
                    <td><a href="{{ route('admin.students.show', $share->student) }}" class="text-decoration-none">{{ $share->student->name }}</a></td>
                    <td class="text-end">{{ hostelease_money($share->amount) }}</td>
                    <td class="text-end text-success">{{ hostelease_money($share->paid_amount) }}</td>
                    <td class="text-end text-danger">{{ hostelease_money($share->balance) }}</td>
                    <td>
                        <span class="badge bg-{{ $share->status==='paid'?'success':($share->status==='partial'?'warning text-dark':'danger') }}">{{ ucfirst($share->status) }}</span>
                        @if($share->promise_date && $share->status !== 'paid')
                            <span class="badge bg-info-subtle text-info" title="{{ $share->promise_note }}"><i class="fa-solid fa-clock me-1"></i>{{ $share->promise_date->format('d-m') }}</span>
                        @endif
                    </td>
                    <td class="text-end">
                        @if($share->status !== 'paid')
                            <button class="btn btn-sm btn-primary" onclick="prepCollect('{{ route('admin.ac-bills.collect', $share) }}', 'AC · {{ $share->student->name }}', {{ $share->balance }})">
                                <i class="fa-solid fa-indian-rupee-sign me-1"></i> Collect
                            </button>
                            <button class="btn btn-sm btn-light" title="Promise to pay"
                                onclick="prepPromise('{{ route('admin.promise.update', ['ac_bill_student', $share->id]) }}', '{{ $share->student->name }}', '{{ optional($share->promise_date)->format('Y-m-d') }}', @js($share->promise_note))">
                                <i class="fa-solid fa-calendar-check"></i>
                            </button>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div></div>

@include('admin.partials.collect_modal')
@include('admin.partials.promise_modal')
@endsection

