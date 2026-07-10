@extends('layouts.app')
@section('title', 'Activity Logs')

@section('content')
<h1 class="h4 fw-bold mb-3">Activity Logs</h1>

<form method="GET" class="row g-2 mb-3">
    <div class="col-6 col-md-3">
        <x-he-select name="hostel" icon="building" :selected="request('hostel', '')"
            :options="['' => 'All hostels'] + $hostels->pluck('name', 'id')->all()" />
    </div>
    <div class="col-6 col-md-3">
        <input type="text" name="action" value="{{ request('action') }}" class="form-control form-control-sm" placeholder="Action (e.g. payment, login)">
    </div>
    <div class="col-6 col-md-2"><button class="btn btn-primary btn-sm"><i class="fa-solid fa-filter me-1"></i> Filter</button></div>
</form>

<div class="card stat-card"><div class="card-body p-0">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>When</th><th>User</th><th>Hostel</th><th>Action</th><th>Description</th><th>IP</th></tr></thead>
            <tbody>
            @forelse($logs as $log)
                <tr>
                    <td class="text-nowrap small">{{ $log->created_at->format('d M Y H:i') }}</td>
                    <td>{{ $log->user?->name ?? '—' }}</td>
                    <td>{{ $log->hostel?->name ?? '—' }}</td>
                    <td><span class="badge bg-primary-subtle text-primary">{{ $log->action }}</span></td>
                    <td class="small">{{ $log->description }}</td>
                    <td class="small text-muted">{{ $log->ip_address }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">No activity logged.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div></div>

<div class="mt-3">{{ $logs->withQueryString()->links() }}</div>
@endsection
