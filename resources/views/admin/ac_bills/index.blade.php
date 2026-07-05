@extends('layouts.app')
@section('title', 'AC Bills')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 fw-bold mb-0">AC Bills</h1>
    <a href="{{ route('admin.ac-bills.create') }}" class="btn btn-primary"><i class="fa-solid fa-plus me-1"></i> Generate AC Bill</a>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card stat-card"><div class="card-body py-3"><div class="stat-value">{{ hostelease_money($summary['billed']) }}</div><div class="stat-label">Total Billed</div></div></div></div>
    <div class="col-md-4"><div class="card stat-card"><div class="card-body py-3"><div class="stat-value text-success">{{ hostelease_money($summary['collected']) }}</div><div class="stat-label">AC Income (Collected)</div></div></div></div>
    <div class="col-md-4"><div class="card stat-card"><div class="card-body py-3"><div class="stat-value text-danger">{{ hostelease_money($summary['due']) }}</div><div class="stat-label">AC Due</div></div></div></div>
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-6 col-md-3">
        <select name="room" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">All AC rooms</option>
            @foreach($acRooms as $r)<option value="{{ $r->id }}" @selected(request('room')==$r->id)>Room {{ $r->room_number }}</option>@endforeach
        </select>
    </div>
</form>

<div class="card stat-card"><div class="card-body">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" data-datatable>
            <thead><tr><th>Month</th><th>Room</th><th>Units</th><th>Rate</th><th class="text-end">Total</th><th>Split</th><th class="text-end">Collected</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            @foreach($bills as $b)
                <tr>
                    <td>{{ $b->bill_month->format('M Y') }}</td>
                    <td>{{ $b->room->room_number }} <small class="text-muted">({{ $b->room->floor->name }})</small></td>
                    <td>{{ rtrim(rtrim(number_format($b->total_units, 2), '0'), '.') }}</td>
                    <td>{{ hostelease_money($b->unit_price) }}</td>
                    <td class="text-end fw-semibold">{{ hostelease_money($b->total_amount) }}</td>
                    <td><span class="badge bg-secondary-subtle text-secondary">{{ ucfirst($b->distribution) }} · {{ $b->shares_count }}</span></td>
                    <td class="text-end text-success">{{ hostelease_money($b->collected) }}</td>
                    <td class="text-end">
                        <a href="{{ route('admin.ac-bills.show', $b) }}" class="btn btn-sm btn-light"><i class="fa-solid fa-eye"></i></a>
                        <form action="{{ route('admin.ac-bills.destroy', $b) }}" method="POST" class="d-inline" data-confirm="Delete this AC bill and its shares?">
                            @csrf @method('DELETE')<button class="btn btn-sm btn-light text-danger"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div></div>
@endsection

