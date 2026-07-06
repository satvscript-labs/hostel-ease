@extends('layouts.app')
@section('title', __('Users & Roles'))

@section('content')
<div class="page-enter">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold text-dark mb-1">{{ __('Users & Roles') }}</h1>
            <p class="text-muted mb-0">{{ __('Manage administrative access and sub-user accounts.') }}</p>
        </div>
        <button class="btn btn-primary rounded-pill shadow-sm px-4" data-bs-toggle="modal" data-bs-target="#userModal" onclick="resetUser()">
            <i class="fa-solid fa-user-plus me-1"></i> {{ __('Add User') }}
        </button>
    </div>

    @if(session('credentials'))
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center border-0 shadow-sm rounded-4 mb-4" role="alert">
            <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px; flex-shrink: 0;">
                <i class="fa-solid fa-check"></i>
            </div>
            <div>
                <h6 class="fw-bold mb-1">{{ __('Login created successfully!') }}</h6>
                <p class="mb-0 small">
                    {{ __('Please share these credentials once (they will not be shown again):') }}<br>
                    {{ __('Mobile:') }} <code class="fs-6">{{ session('credentials')['mobile'] }}</code> &mdash;
                    {{ __('Password:') }} <code class="fs-6">{{ session('credentials')['password'] }}</code>
                </p>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="alert alert-primary bg-primary-subtle text-primary-emphasis border-0 shadow-sm rounded-4 mb-4 d-flex align-items-start p-4">
        <i class="fa-solid fa-circle-info fs-4 me-3 mt-1"></i>
        <div class="small">
            <p class="mb-2"><strong>{{ __('Role Permissions Breakdown:') }}</strong></p>
            <ul class="mb-0 list-unstyled d-flex flex-wrap gap-4">
                <li><i class="fa-solid fa-crown opacity-50 me-1"></i> <strong>{{ __('Manager') }}</strong>: {{ __('Full access except user management.') }}</li>
                <li><i class="fa-solid fa-calculator opacity-50 me-1"></i> <strong>{{ __('Accountant') }}</strong>: {{ __('Manage fees, expenses, and reports.') }}</li>
                <li><i class="fa-solid fa-user-shield opacity-50 me-1"></i> <strong>{{ __('Warden') }}</strong>: {{ __('Manage students, beds, visitors, staff.') }}</li>
                <li><i class="fa-regular fa-eye opacity-50 me-1"></i> <strong>{{ __('Viewer') }}</strong>: {{ __('Read-only access to records.') }}</li>
            </ul>
        </div>
    </div>

    <div class="card stat-card border-0 shadow-sm overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="text-uppercase small fw-semibold text-secondary py-3 px-4">{{ __('Name') }}</th>
                        <th class="text-uppercase small fw-semibold text-secondary py-3">{{ __('Login (Mobile)') }}</th>
                        <th class="text-uppercase small fw-semibold text-secondary py-3">{{ __('Role') }}</th>
                        <th class="text-uppercase small fw-semibold text-secondary py-3">{{ __('Branch') }}</th>
                        <th class="text-uppercase small fw-semibold text-secondary py-3">{{ __('Status') }}</th>
                        <th class="text-uppercase small fw-semibold text-secondary py-3 px-4 text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($users as $u)
                    @php
                        $ud = ['id'=>$u->id,'name'=>$u->name,'mobile'=>$u->mobile,'role'=>$u->role,'role_id'=>$u->role_id,'branch_id'=>$u->branch_id,'is_active'=>(bool)$u->is_active];
                        $roleName = $u->role ? ($allRoles->find($u->role_id)?->display_name ?? ucfirst($u->role)) : 'Unassigned';
                    @endphp
                    <tr>
                        <td class="px-4 fw-semibold text-dark">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-secondary-subtle text-secondary rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 35px; height: 35px;">
                                    {{ strtoupper(substr($u->name, 0, 1)) }}
                                </div>
                                {{ $u->name }}
                            </div>
                        </td>
                        <td class="text-secondary">{{ hostelease_phone($u->mobile) }}</td>
                        <td><span class="badge bg-primary-subtle text-primary rounded-pill px-3">{{ $roleName }}</span></td>
                        <td class="text-secondary">{{ $u->branch?->name ?? '—' }}</td>
                        <td>
                            @if($u->is_active)
                                <span class="badge bg-success-subtle text-success rounded-pill px-3"><i class="fa-solid fa-circle me-1" style="font-size: 0.5rem"></i> {{ __('Active') }}</span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary rounded-pill px-3">{{ __('Disabled') }}</span>
                            @endif
                        </td>
                        <td class="px-4 text-end text-nowrap">
                            <button class="btn btn-sm btn-light rounded-circle shadow-sm" style="width: 32px; height: 32px" data-bs-toggle="modal" data-bs-target="#userModal" onclick="editUser(@js($ud))" title="{{ __('Edit') }}"><i class="fa-solid fa-pen text-secondary"></i></button>
                            <form action="{{ route('admin.users.reset', $u) }}" method="POST" class="d-inline" data-confirm="{{ __('Reset password for :name?', ['name' => $u->name]) }}">
                                @csrf @method('PATCH')
                                <button class="btn btn-sm btn-light rounded-circle shadow-sm" style="width: 32px; height: 32px" title="{{ __('Reset password') }}"><i class="fa-solid fa-key text-warning"></i></button>
                            </form>
                            <form action="{{ route('admin.users.destroy', $u) }}" method="POST" class="d-inline" data-confirm="{{ __('Remove :name?', ['name' => $u->name]) }}">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-light rounded-circle shadow-sm" style="width: 32px; height: 32px" title="{{ __('Delete') }}"><i class="fa-solid fa-trash text-danger"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-5">
                            <div class="text-muted">
                                <i class="fa-solid fa-users-slash fs-1 mb-3 opacity-25"></i>
                                <p class="mb-0">{{ __('No sub-users yet.') }}</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Premium User Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow-lg" id="userForm" method="POST" action="{{ route('admin.users.store') }}">
            @csrf
            <input type="hidden" name="_method" id="userMethod" value="POST">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold" id="userTitle">{{ __('Add User') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-3">
                <div class="row g-4">
                    <div class="col-12">
                        <label class="form-label fw-semibold text-secondary small text-uppercase">{{ __('Full Name') }} <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="u_name" class="form-control form-control-lg bg-light border-0" required placeholder="{{ __('Enter user name') }}">
                    </div>
                    <div class="col-12" id="u_mobile_wrap">
                        <label class="form-label fw-semibold text-secondary small text-uppercase">{{ __('Mobile (Login)') }} <span class="text-danger">*</span></label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-light border-0 text-muted">+91</span>
                            <input type="tel" name="mobile" id="u_mobile" maxlength="10" inputmode="numeric" class="form-control bg-light border-0" placeholder="9876543210">
                        </div>
                    </div>
                    
                    <div class="col-12">
                        <label class="form-label fw-semibold text-secondary small text-uppercase">{{ __('Role') }} <span class="text-danger">*</span></label>
                        <select name="role_id" id="u_role_id" class="form-select form-select-lg bg-light border-0" required>
                            <option value="">{{ __('-- Select Role --') }}</option>
                            @foreach($allRoles as $role)
                                <option value="{{ $role->id }}">{{ $role->display_name }}</option>
                            @endforeach
                        </select>
                        <input type="hidden" name="role" id="u_role" value="viewer">
                    </div>
                    
                    <div class="col-12">
                        <label class="form-label fw-semibold text-secondary small text-uppercase">{{ __('Assigned Branch') }} <span class="text-danger">*</span></label>
                        <select name="branch_id" id="u_branch_id" class="form-select form-select-lg bg-light border-0">
                            <option value="">{{ __('-- Select Branch --') }}</option>
                            @foreach($branches as $b)
                                <option value="{{ $b->id }}">{{ $b->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div class="col-12" id="u_active_wrap" style="display:none;">
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="u_active" checked style="width: 2.5em; height: 1.25em;">
                            <label class="form-check-label ms-2 mt-1 fw-semibold" for="u_active">{{ __('Account Active') }}</label>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4 p-3 bg-primary-subtle text-primary-emphasis rounded-3 small" id="u_pw_hint">
                    <i class="fa-solid fa-lock me-2"></i> {{ __('A secure password will be generated automatically and displayed to you after saving.') }}
                </div>
            </div>
            <div class="modal-footer border-top-0 pt-0 pb-4 px-4">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="submit" class="btn btn-primary rounded-pill px-4 shadow-sm">{{ __('Save User') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const userForm = document.getElementById('userForm');
    const userStore = "{{ route('admin.users.store') }}";
    
    // Auto sync old role field if needed by backend
    document.getElementById('u_role_id').addEventListener('change', function() {
        const text = this.options[this.selectedIndex].text.toLowerCase();
        document.getElementById('u_role').value = text;
    });

    function resetUser() {
        userForm.action = userStore; 
        document.getElementById('userMethod').value = 'POST';
        document.getElementById('userTitle').textContent = 'Add User';
        document.getElementById('u_name').value = '';
        document.getElementById('u_mobile').value = '';
        document.getElementById('u_mobile_wrap').style.display = '';
        document.getElementById('u_active_wrap').style.display = 'none';
        document.getElementById('u_pw_hint').style.display = '';
        document.getElementById('u_role_id').value = '';
        document.getElementById('u_branch_id').value = '';
    }
    
    function editUser(u) {
        userForm.action = "{{ url('admin/users') }}/" + u.id; 
        document.getElementById('userMethod').value = 'PUT';
        document.getElementById('userTitle').textContent = 'Edit User';
        document.getElementById('u_name').value = u.name || '';
        document.getElementById('u_mobile_wrap').style.display = 'none';
        document.getElementById('u_mobile').value = (u.mobile || '').slice(-10);
        document.getElementById('u_role').value = u.role || 'viewer';
        document.getElementById('u_role_id').value = u.role_id || '';
        document.getElementById('u_branch_id').value = u.branch_id || '';
        document.getElementById('u_active_wrap').style.display = '';
        document.getElementById('u_active').checked = !!u.is_active;
        document.getElementById('u_pw_hint').style.display = 'none';
    }
</script>
@endpush
