@extends('layouts.app')
@section('title', 'Users & Roles')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 fw-bold mb-0">Users &amp; Roles</h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal" onclick="resetUser()"><i class="fa-solid fa-user-plus me-1"></i> Add User</button>
</div>

@if(session('credentials'))
    <div class="alert alert-success">
        <strong>Login created — share once:</strong>
        Mobile: <code>{{ session('credentials')['mobile'] }}</code> ·
        Password: <code>{{ session('credentials')['password'] }}</code>
    </div>
@endif

<div class="alert alert-info py-2 small">
    <i class="fa-solid fa-circle-info me-1"></i>
    <strong>Manager</strong> — everything except users. <strong>Accountant</strong> — fees, expenses, reports.
    <strong>Warden</strong> — students, beds, visitors, staff. <strong>Viewer</strong> — read-only.
    Sub-users sign in to the mobile app with their mobile + password.
</div>

<div class="card stat-card"><div class="card-body">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Name</th><th>Login (Mobile)</th><th>Role</th><th>Branch</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            @forelse($users as $u)
                @php($ud = ['id'=>$u->id,'name'=>$u->name,'mobile'=>$u->mobile,'role'=>$u->role,'role_id'=>$u->role_id,'branch_id'=>$u->branch_id,'is_active'=>(bool)$u->is_active])
                <tr>
                    <td class="fw-semibold">{{ $u->name }}</td>
                    <td>{{ hostelease_phone($u->mobile) }}</td>
                    <td><span class="badge bg-primary-subtle text-primary">{{ $u->role ? ($allRoles->find($u->role_id)?->display_name ?? ucfirst($u->role)) : 'Unassigned' }}</span></td>
                    <td>{{ $u->branch?->name ?? '—' }}</td>
                    <td><span class="badge bg-{{ $u->is_active ? 'success' : 'secondary' }}">{{ $u->is_active ? 'Active' : 'Disabled' }}</span></td>
                    <td class="text-end text-nowrap">
                        <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#userModal" onclick="editUser(@js($ud))"><i class="fa-solid fa-pen"></i></button>
                        <form action="{{ route('admin.users.reset', $u) }}" method="POST" class="d-inline" data-confirm="Reset password for {{ $u->name }}?">@csrf @method('PATCH')<button class="btn btn-sm btn-light" title="Reset password"><i class="fa-solid fa-key"></i></button></form>
                        <form action="{{ route('admin.users.destroy', $u) }}" method="POST" class="d-inline" data-confirm="Remove {{ $u->name }}?">@csrf @method('DELETE')<button class="btn btn-sm btn-light text-danger"><i class="fa-solid fa-trash"></i></button></form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">No sub-users yet. Add one.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div></div>

<div class="modal fade" id="userModal" tabindex="-1"><div class="modal-dialog">
    <form class="modal-content" id="userForm" method="POST" action="{{ route('admin.users.store') }}">@csrf
        <input type="hidden" name="_method" id="userMethod" value="POST">
        <div class="modal-header"><h5 class="modal-title" id="userTitle">Add User</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Name *</label><input type="text" name="name" id="u_name" class="form-control" required></div>
                <div class="col-md-6" id="u_mobile_wrap"><label class="form-label">Mobile (login) *</label><div class="input-group"><span class="input-group-text">+91</span><input type="tel" name="mobile" id="u_mobile" maxlength="10" inputmode="numeric" class="form-control"></div></div>
                <div class="col-md-6"><label class="form-label">Role *</label>
                    <select name="role" id="u_role" class="form-select" required>
                        @foreach($roles as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                    </select></div>
                <div class="col-md-6"><label class="form-label">Role ID (New) *</label>
                    <select name="role_id" id="u_role_id" class="form-select">
                        <option value="">-- Select Role --</option>
                        @foreach($allRoles as $role)
                            <option value="{{ $role->id }}">{{ $role->display_name }}</option>
                        @endforeach
                    </select></div>
                <div class="col-12"><label class="form-label">Branch *</label>
                    <select name="branch_id" id="u_branch_id" class="form-select">
                        <option value="">-- Select Branch --</option>
                        @foreach($branches as $b)
                            <option value="{{ $b->id }}">{{ $b->name }}</option>
                        @endforeach
                    </select></div>
                <div class="col-12 form-check" id="u_active_wrap" style="display:none;">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="u_active" checked><label class="form-check-label" for="u_active">Active</label></div>
            </div>
            <small class="text-muted" id="u_pw_hint">A password is generated and shown once after saving.</small>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
    </form>
</div></div>
@endsection

@push('scripts')
<script>
    const userForm = document.getElementById('userForm');
    const userStore = "{{ route('admin.users.store') }}";
    function resetUser() {
        userForm.action = userStore; document.getElementById('userMethod').value = 'POST';
        document.getElementById('userTitle').textContent = 'Add User';
        document.getElementById('u_name').value = '';
        document.getElementById('u_mobile').value = '';
        document.getElementById('u_mobile_wrap').style.display = '';
        document.getElementById('u_active_wrap').style.display = 'none';
        document.getElementById('u_pw_hint').style.display = '';
    }
    function editUser(u) {
        userForm.action = "{{ url('admin/users') }}/" + u.id; document.getElementById('userMethod').value = 'PUT';
        document.getElementById('userTitle').textContent = 'Edit User';
        document.getElementById('u_name').value = u.name || '';
        document.getElementById('u_mobile_wrap').style.display = 'none';
        document.getElementById('u_mobile').value = (u.mobile || '').slice(-10);
        document.getElementById('u_role').value = u.role;
        document.getElementById('u_role_id').value = u.role_id || '';
        document.getElementById('u_branch_id').value = u.branch_id || '';
        document.getElementById('u_active_wrap').style.display = '';
        document.getElementById('u_active').checked = !!u.is_active;
        document.getElementById('u_pw_hint').style.display = 'none';
    }
</script>
@endpush

