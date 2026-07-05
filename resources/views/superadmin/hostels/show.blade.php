@extends('layouts.app')
@section('title', $hostel->name)

@section('content')
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <a href="{{ route('superadmin.hostels.index') }}" class="btn btn-light btn-sm"><i class="fa-solid fa-arrow-left"></i></a>
    <h1 class="h4 fw-bold mb-0">{{ $hostel->name }}</h1>
    <span class="badge bg-{{ $hostel->status==='active'?'success':($hostel->status==='expired'?'danger':'secondary') }}">{{ ucfirst($hostel->status) }}</span>
    <a href="{{ route('superadmin.hostels.edit', $hostel) }}" class="btn btn-primary btn-sm ms-auto"><i class="fa-solid fa-pen me-1"></i> Edit</a>
</div>

@include('superadmin.partials.credentials')

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="card stat-card"><div class="card-body py-3"><div class="stat-value">{{ $hostel->students_count }}</div><div class="stat-label">Students</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card stat-card"><div class="card-body py-3"><div class="stat-value">{{ $hostel->rooms_count }}</div><div class="stat-label">Rooms</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card stat-card"><div class="card-body py-3"><div class="stat-value">{{ $hostel->beds_count }}</div><div class="stat-label">Beds</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card stat-card"><div class="card-body py-3"><div class="stat-value">{{ optional($hostel->subscription_end)->format('d M Y') ?? '—' }}</div><div class="stat-label">Expires</div></div></div></div>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card stat-card h-100"><div class="card-body">
            <h2 class="h6 fw-bold mb-3">Details</h2>
            <dl class="row mb-0 small">
                <dt class="col-5 text-muted">Owner</dt><dd class="col-7">{{ $hostel->owner_name }}</dd>
                <dt class="col-5 text-muted">Mobile</dt><dd class="col-7"><x-mobile-link :mobile="$hostel->mobile" /></dd>
                <dt class="col-5 text-muted">Email</dt><dd class="col-7">{{ $hostel->email ?? '—' }}</dd>
                <dt class="col-5 text-muted">GST</dt><dd class="col-7">{{ $hostel->gst_number ?? '—' }}</dd>
                <dt class="col-5 text-muted">Location</dt><dd class="col-7">{{ $hostel->city }}{{ $hostel->state ? ', '.$hostel->state : '' }}</dd>
            </dl>
            <hr>
            <h2 class="h6 fw-bold mb-2">Admins</h2>
            @foreach($hostel->admins as $a)
                <div class="d-flex justify-content-between align-items-center small mb-1">
                    <span>{{ $a->name }} · {{ hsms_phone($a->mobile) }}</span>
                    <span class="badge bg-{{ $a->is_active ? 'success' : 'secondary' }}">{{ $a->is_active ? 'Active' : 'Disabled' }}</span>
                </div>
            @endforeach
            <a href="{{ route('superadmin.admins.index') }}" class="small">Manage admins →</a>
        </div></div>
    </div>
    <div class="col-lg-7">
        <div class="card stat-card h-100"><div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h6 fw-bold mb-0">Subscriptions</h2>
                <a href="{{ route('superadmin.subscriptions.index') }}" class="btn btn-sm btn-light">Add / Renew</a>
            </div>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                <thead><tr><th>Start</th><th>End</th><th class="text-end">Amount</th><th>Status</th></tr></thead>
                <tbody>
                @forelse($hostel->subscriptions as $s)
                    <tr>
                        <td>{{ $s->start_date->format('d M Y') }}</td>
                        <td>{{ $s->end_date->format('d M Y') }}</td>
                        <td class="text-end">{{ hsms_money($s->amount) }}</td>
                        <td><span class="badge bg-{{ $s->payment_status==='paid'?'success':($s->payment_status==='pending'?'warning text-dark':'danger') }}">{{ ucfirst($s->payment_status) }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-3">No subscriptions yet.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div></div>
    </div>
</div>
@endsection
