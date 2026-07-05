@extends('layouts.app')
@section('title', 'Admin Management')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 fw-bold mb-0">Admin Management</h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#adminModal"><i class="fa-solid fa-user-plus me-1"></i> Add Admin</button>
</div>

@include('superadmin.partials.credentials')

<div class="card stat-card"><div class="card-body">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" data-datatable>
            <thead><tr><th>Name</th><th>Hostel</th><th>Login (Mobile)</th><th>Last Login</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            @foreach($admins as $a)
                <tr>
                    <td class="fw-semibold">{{ $a->name }}</td>
                    <td>
                        {{ $a->hostel?->name ?? '—' }}
                        @if($a->hostels->count() > 1)
                            <span class="badge bg-primary-subtle text-primary">{{ $a->hostels->count() }} branches</span>
                        @endif
                    </td>
                    <td>{{ hsms_phone($a->mobile) }}</td>
                    <td>{{ $a->last_login_at ? $a->last_login_at->format('d M Y H:i') : 'Never' }}</td>
                    <td><span class="badge bg-{{ $a->is_active ? 'success' : 'secondary' }}">{{ $a->is_active ? 'Active' : 'Disabled' }}</span></td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-light" title="Branch access" data-bs-toggle="modal" data-bs-target="#br{{ $a->id }}"><i class="fa-solid fa-code-branch"></i></button>
                        <form action="{{ route('superadmin.admins.reset', $a) }}" method="POST" class="d-inline" data-confirm="Reset password for {{ $a->name }}?">
                            @csrf @method('PATCH')<button class="btn btn-sm btn-light" title="Reset password"><i class="fa-solid fa-key"></i></button>
                        </form>
                        <form action="{{ route('superadmin.admins.toggle', $a) }}" method="POST" class="d-inline">
                            @csrf @method('PATCH')
                            <button class="btn btn-sm btn-light {{ $a->is_active ? 'text-danger' : 'text-success' }}" title="{{ $a->is_active ? 'Disable' : 'Enable' }}">
                                <i class="fa-solid fa-{{ $a->is_active ? 'ban' : 'circle-check' }}"></i>
                            </button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div></div>

{{-- Per-admin branch access modals --}}
@foreach($admins as $a)
    @php($assigned = $a->hostels->pluck('id')->all())
    <div class="modal fade" id="br{{ $a->id }}" tabindex="-1"><div class="modal-dialog">
        <form class="modal-content" method="POST" action="{{ route('superadmin.admins.branches', $a) }}">@csrf @method('PUT')
            <div class="modal-header"><h5 class="modal-title">Branches — {{ $a->name }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <p class="small text-muted">Tick every branch this login can access &amp; switch between. Their primary hostel ({{ $a->hostel?->name ?? '—' }}) is always kept.</p>
                <div style="max-height:280px;overflow:auto;">
                    @foreach($hostels as $h)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="hostels[]" value="{{ $h->id }}"
                                   id="br{{ $a->id }}h{{ $h->id }}"
                                   @checked(in_array($h->id, $assigned) || $a->hostel_id == $h->id)
                                   @disabled($a->hostel_id == $h->id)>
                            <label class="form-check-label" for="br{{ $a->id }}h{{ $h->id }}">{{ $h->name }}@if($a->hostel_id == $h->id) <span class="text-muted">(primary)</span>@endif</label>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save Access</button></div>
        </form>
    </div></div>
@endforeach

<div class="modal fade" id="adminModal" tabindex="-1"><div class="modal-dialog">
    <form class="modal-content" method="POST" action="{{ route('superadmin.admins.store') }}">@csrf
        <div class="modal-header"><h5 class="modal-title">Add Admin</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="mb-3"><label class="form-label">Hostel</label>
                <select name="hostel_id" class="form-select" data-select2 required>
                    <option value="">Select…</option>@foreach($hostels as $h)<option value="{{ $h->id }}">{{ $h->name }}</option>@endforeach
                </select></div>
            <div class="mb-3"><label class="form-label">Name</label><input type="text" name="name" class="form-control" required></div>
            <div class="mb-3"><label class="form-label">Mobile (login)</label>
                <div class="input-group"><span class="input-group-text">+91</span><input type="tel" name="mobile" maxlength="10" class="form-control" required></div></div>
            <div class="mb-1"><label class="form-label">Email</label><input type="email" name="email" class="form-control"></div>
            <small class="text-muted">A password will be auto-generated and shown once.</small>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Create</button></div>
    </form>
</div></div>
@endsection
