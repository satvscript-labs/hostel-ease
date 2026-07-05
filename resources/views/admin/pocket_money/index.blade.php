@extends('layouts.app')
@section('title', 'Pocket Money')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 fw-bold mb-0">Pocket Money <span class="badge bg-success-subtle text-success">Total held {{ hostelease_money($total) }}</span></h1>
</div>

<div class="card stat-card"><div class="card-body">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" data-datatable>
            <thead><tr><th>Student</th><th>Mobile</th><th>Balance</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            @foreach($students as $s)
                <tr>
                    <td class="fw-semibold">{{ $s->name }}</td>
                    <td>@if($s->mobile)<x-mobile-link :mobile="$s->mobile" />@else — @endif</td>
                    <td class="fw-bold {{ $s->pocket_balance > 0 ? 'text-success' : 'text-muted' }}">{{ hostelease_money($s->pocket_balance) }}</td>
                    <td class="text-end"><a href="{{ route('admin.pocket-money.show', $s) }}" class="btn btn-sm btn-primary"><i class="fa-solid fa-wallet me-1"></i> Manage</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div></div>
@endsection

