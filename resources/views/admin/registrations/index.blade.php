@extends('layouts.app')
@section('title', 'Registrations')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 fw-bold mb-0">Student Registrations <span class="badge bg-warning text-dark">{{ $pending->count() }} pending</span></h1>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-7">
        <div class="card stat-card"><div class="card-body">
            <h6 class="fw-bold">Self-registration link</h6>
            <p class="text-muted small mb-2">Share this link or QR with students so they fill their own entry form. Submissions appear below for your approval.</p>
            <div class="input-group mb-2">
                <input type="text" class="form-control" value="{{ $url }}" id="reg-url" readonly>
                <button class="btn btn-outline-primary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('reg-url').value)"><i class="fa-solid fa-copy"></i> Copy</button>
                <a class="btn btn-outline-secondary" href="{{ $url }}" target="_blank"><i class="fa-solid fa-arrow-up-right-from-square"></i></a>
            </div>
            <form action="{{ route('admin.registrations.regenerate') }}" method="POST" data-confirm="Generate a new link? The current link/QR will stop working.">@csrf
                <button class="btn btn-sm btn-light"><i class="fa-solid fa-rotate me-1"></i> Generate new link</button>
            </form>
        </div></div>
    </div>
    <div class="col-lg-5">
        <div class="card stat-card"><div class="card-body text-center">
            <h6 class="fw-bold">QR code</h6>
            @if($qr)<div class="d-inline-block">{!! $qr !!}</div>@else<p class="text-muted small">QR unavailable.</p>@endif
        </div></div>
    </div>
</div>

<div class="card stat-card"><div class="card-body">
    <h6 class="fw-bold">Pending submissions</h6>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Name</th><th>Mobile</th><th>Occupation</th><th>City</th><th>Submitted</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            @forelse($pending as $r)
                <tr>
                    <td class="fw-semibold">{{ $r->name }}</td>
                    <td>{{ hostelease_phone($r->mobile) }}</td>
                    <td>{{ config('hostelease.occupation_types.'.$r->occupation_type, $r->occupation_type) }}</td>
                    <td>{{ $r->city ?? '—' }}</td>
                    <td class="small text-nowrap">{{ $r->created_at->format('d M Y H:i') }}</td>
                    <td class="text-end text-nowrap">
                        <form action="{{ route('admin.registrations.approve', $r) }}" method="POST" class="d-inline" data-confirm="Approve {{ $r->name }} and create the student?">@csrf<button class="btn btn-sm btn-success"><i class="fa-solid fa-check me-1"></i>Approve</button></form>
                        <form action="{{ route('admin.registrations.reject', $r) }}" method="POST" class="d-inline" data-confirm="Reject {{ $r->name }}?">@csrf<button class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-xmark"></i></button></form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">No pending registrations.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div></div>
@endsection

