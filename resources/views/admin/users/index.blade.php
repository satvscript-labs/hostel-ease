@extends('layouts.app')
@section('title', __('Users & Roles'))

@section('content')
<div x-data="usersManager()" class="page-enter">
    
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4 stagger-1">
        <div>
            <h1 class="h3 fw-bold text-dark mb-1 tracking-tight">{{ __('Users & Roles') }}</h1>
            <p class="text-muted mb-0 small">{{ __('Manage staff access, roles, and branch assignments.') }}</p>
        </div>
        <button type="button" @click="openModal()" class="btn btn-primary rounded-pill shadow-sm px-4 fw-semibold tactile-btn">
            <i class="fa-solid fa-user-plus me-1"></i> {{ __('Add User') }}
        </button>
    </div>

    @if(session('credentials'))
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center border-0 shadow-sm rounded-4 mb-4 stagger-2" role="alert">
            <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width: 42px; height: 42px;">
                <i class="fa-solid fa-check"></i>
            </div>
            <div>
                <h6 class="fw-bold mb-1">{{ __('Login created successfully!') }}</h6>
                <p class="mb-0 small">
                    {{ __('Please share these credentials once (they will not be shown again):') }}<br>
                    <span class="text-dark fw-medium">{{ __('Mobile:') }}</span> <code class="fs-6 bg-transparent text-dark fw-bold p-0">{{ session('credentials')['mobile'] }}</code> &mdash;
                    <span class="text-dark fw-medium">{{ __('Password:') }}</span> <code class="fs-6 bg-transparent text-dark fw-bold p-0">{{ session('credentials')['password'] }}</code>
                </p>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Premium Role Hierarchy Breakdown -->
    <div class="row g-3 mb-4 stagger-2">
        <div class="col-sm-6 col-lg-3">
            <div class="card card-premium bg-primary-subtle border-0 shadow-sm h-100">
                <div class="card-body p-4 d-flex flex-column">
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <div class="bg-white text-primary rounded-circle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 40px; height: 40px;">
                            <i class="fa-solid fa-crown fs-5"></i>
                        </div>
                        <h6 class="fw-bold mb-0 text-primary-emphasis">{{ __('Manager') }}</h6>
                    </div>
                    <p class="text-primary-emphasis opacity-75 small mb-0 mt-auto" style="line-height: 1.4;">{{ __('Full access across assigned branches except user management.') }}</p>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card card-premium bg-info-subtle border-0 shadow-sm h-100">
                <div class="card-body p-4 d-flex flex-column">
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <div class="bg-white text-info rounded-circle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 40px; height: 40px;">
                            <i class="fa-solid fa-calculator fs-5"></i>
                        </div>
                        <h6 class="fw-bold mb-0 text-info-emphasis">{{ __('Accountant') }}</h6>
                    </div>
                    <p class="text-info-emphasis opacity-75 small mb-0 mt-auto" style="line-height: 1.4;">{{ __('Manage fees, expenses, and financial reports only.') }}</p>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card card-premium bg-warning-subtle border-0 shadow-sm h-100">
                <div class="card-body p-4 d-flex flex-column">
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <div class="bg-white text-warning rounded-circle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 40px; height: 40px;">
                            <i class="fa-solid fa-user-shield fs-5"></i>
                        </div>
                        <h6 class="fw-bold mb-0 text-warning-emphasis">{{ __('Warden') }}</h6>
                    </div>
                    <p class="text-warning-emphasis opacity-75 small mb-0 mt-auto" style="line-height: 1.4;">{{ __('Manage students, beds, visitors, and complaints.') }}</p>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card card-premium bg-secondary-subtle border-0 shadow-sm h-100">
                <div class="card-body p-4 d-flex flex-column">
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <div class="bg-white text-secondary rounded-circle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 40px; height: 40px;">
                            <i class="fa-regular fa-eye fs-5"></i>
                        </div>
                        <h6 class="fw-bold mb-0 text-secondary-emphasis">{{ __('Viewer') }}</h6>
                    </div>
                    <p class="text-secondary-emphasis opacity-75 small mb-0 mt-auto" style="line-height: 1.4;">{{ __('Read-only access to all assigned branch records.') }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card card-premium border-0 shadow-sm overflow-hidden stagger-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                        <tr>
                            <th class="py-3 px-4 text-muted fw-semibold border-0">{{ __('Name') }}</th>
                            <th class="py-3 px-4 text-muted fw-semibold border-0">{{ __('Login (Mobile)') }}</th>
                            <th class="py-3 px-4 text-muted fw-semibold border-0">{{ __('Role') }}</th>
                            <th class="py-3 px-4 text-muted fw-semibold border-0">{{ __('Assigned Branches') }}</th>
                            <th class="py-3 px-4 text-muted fw-semibold border-0 text-center">{{ __('Status') }}</th>
                            <th class="py-3 px-4 text-end border-0"></th>
                        </tr>
                    </thead>
                    <tbody class="border-top-0">
                    @forelse($users as $u)
                        @php
                            $assignedBranches = $u->hostels->pluck('id')->toArray();
                            $ud = [
                                'id' => $u->id,
                                'name' => $u->name,
                                'mobile' => $u->mobile,
                                'role' => $u->role,
                                'branches' => $assignedBranches,
                                'is_active' => (bool)$u->is_active
                            ];
                            $roleName = $u->role ? ($roles[$u->role] ?? ucfirst($u->role)) : 'Unassigned';
                        @endphp
                        <tr class="stagger-{{ $loop->iteration % 5 + 1 }}">
                            <td class="px-4 py-3 fw-semibold text-dark">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="bg-light text-secondary rounded-circle d-flex align-items-center justify-content-center fw-bold shadow-sm" style="width: 38px; height: 38px;">
                                        {{ strtoupper(substr($u->name, 0, 1)) }}
                                    </div>
                                    <div class="d-flex flex-column">
                                        <span>{{ $u->name }}</span>
                                        <span class="text-muted small fw-normal d-block d-md-none">{{ hostelease_phone($u->mobile) }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="text-secondary fw-medium d-none d-md-table-cell">{{ hostelease_phone($u->mobile) }}</td>
                            <td>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-2 fw-semibold">
                                    {{ $roleName }}
                                </span>
                            </td>
                            <td>
                                @if(empty($assignedBranches))
                                    <span class="text-muted fst-italic small">—</span>
                                @else
                                    <div class="d-flex flex-wrap gap-1">
                                        @foreach($u->hostels as $bh)
                                            <span class="badge bg-light border text-secondary rounded-pill fw-medium">{{ $bh->name }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 text-center">
                                @if($u->is_active)
                                    <span class="badge bg-success-subtle text-success rounded-pill px-3 py-2 border border-success-subtle fw-semibold"><i class="fa-solid fa-circle me-1" style="font-size: 0.4rem; vertical-align: middle;"></i> {{ __('Active') }}</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary rounded-pill px-3 py-2 border border-secondary-subtle fw-semibold">{{ __('Disabled') }}</span>
                                @endif
                            </td>
                            <td class="px-4 text-end text-nowrap">
                                <button type="button" @click='openModal(<?php echo json_encode($ud); ?>)' class="btn btn-sm btn-light rounded-circle shadow-sm mx-1" style="width: 32px; height: 32px" title="{{ __('Edit User') }}">
                                    <i class="fa-solid fa-pen text-secondary" style="font-size: 0.8rem;"></i>
                                </button>
                                <form action="{{ route('admin.users.reset', $u) }}" method="POST" class="d-inline" data-confirm="{{ __('Reset password for :name?', ['name' => $u->name]) }}">
                                    @csrf @method('PATCH')
                                    <button class="btn btn-sm btn-light rounded-circle shadow-sm mx-1" style="width: 32px; height: 32px" title="{{ __('Reset password') }}"><i class="fa-solid fa-key text-warning" style="font-size: 0.8rem;"></i></button>
                                </form>
                                <form action="{{ route('admin.users.destroy', $u) }}" method="POST" class="d-inline" data-confirm="{{ __('Remove :name?', ['name' => $u->name]) }}">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-light rounded-circle shadow-sm mx-1" style="width: 32px; height: 32px" title="{{ __('Delete') }}"><i class="fa-solid fa-trash text-danger" style="font-size: 0.8rem;"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <div class="d-flex flex-column align-items-center py-4 stagger-1">
                                    <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center mb-3 shadow-sm" style="width: 80px; height: 80px;">
                                        <i class="fa-solid fa-users-gear fs-1"></i>
                                    </div>
                                    <h5 class="fw-bold text-dark mb-1">{{ __('No Staff Users Found') }}</h5>
                                    <p class="text-muted small mb-4">{{ __('Add staff members to help manage your branches.') }}</p>
                                    <button type="button" @click="openModal()" class="btn btn-primary rounded-pill shadow-sm px-4 fw-semibold tactile-btn">
                                        <i class="fa-solid fa-plus me-1"></i> {{ __('Create First User') }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Ultra-Premium Alpine Modal -->
    <template x-teleport="body">
        <div x-show="isModalOpen" class="custom-overlay-backdrop" style="display: none;" x-transition.opacity @click="closeModal()" x-cloak>
            <form :action="formAction" method="POST" class="custom-overlay-modal" :class="{ 'is-open': isModalOpen }" x-show="isModalOpen" x-transition.opacity @click.stop style="display: none;">
                @csrf
                <input type="hidden" name="_method" :value="formMethod">
                
                <div class="custom-overlay-header">
                    <h5 class="fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                        <i class="fa-solid fa-user-plus text-primary"></i>
                        <span x-text="modalTitle"></span>
                    </h5>
                    <button type="button" @click="closeModal()" class="btn-close shadow-none"></button>
                </div>

                <div class="custom-overlay-body">
                    <h6 class="fw-bold text-muted text-uppercase mb-3" style="font-size: 0.75rem; letter-spacing: 1px;">User Details</h6>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" x-model="form.name" class="form-control bg-light" required placeholder="Enter user name">
                    </div>
                    
                    <div class="mb-3" x-show="!isEdit">
                        <label class="form-label fw-bold small">Mobile (Login ID) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border text-muted fw-bold">+91</span>
                            <input type="tel" name="mobile" x-model="form.mobile" maxlength="10" inputmode="numeric" class="form-control bg-light border-start-0 ps-2" placeholder="9876543210" :required="!isEdit">
                        </div>
                    </div>
                    
                    <!-- Premium Alpine Dropdown for Role (Resized) -->
                    <div class="mb-3" x-data="{ roleOpen: false }">
                        <label class="form-label fw-bold small">Role & Permissions <span class="text-danger">*</span></label>
                        
                        <div class="position-relative">
                            <input type="hidden" name="role" x-model="form.role" required>
                            
                            <div class="d-flex align-items-center justify-content-between bg-light p-2 rounded border transition-all" 
                                 @click="roleOpen = !roleOpen" 
                                 style="cursor: pointer;"
                                 :class="roleOpen ? 'border-primary shadow-sm bg-white' : ''">
                                
                                <div class="d-flex align-items-center gap-2">
                                    <div class="bg-white text-primary rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 32px; height: 32px;">
                                        <i class="fa-solid fa-user-shield" style="font-size: 0.8rem;"></i>
                                    </div>
                                    <div class="d-flex flex-column">
                                        <span class="fw-bold text-dark" style="font-size: 0.9rem; line-height: 1;" x-text="form.role ? form.role.charAt(0).toUpperCase() + form.role.slice(1) : '{{ __('-- Select Role --') }}'"></span>
                                        <span class="text-muted small mt-1" style="font-size: 0.7rem;" x-show="form.role === ''">Choose level of access</span>
                                    </div>
                                </div>
                                <i class="fa-solid fa-chevron-down text-muted small me-2 transition-all" :class="{'fa-chevron-up': roleOpen}"></i>
                            </div>
                            
                            <div x-show="roleOpen" @click.outside="roleOpen = false" x-transition.opacity.duration.200ms class="position-absolute bg-white border rounded shadow-lg mt-1 w-100" style="display: none; z-index: 1050; max-height: 200px; overflow-y: auto;">
                                <div class="list-group list-group-flush py-1">
                                    @foreach($roles as $roleKey => $roleName)
                                    <a href="javascript:void(0)" class="list-group-item list-group-item-action border-0 py-2 px-3 transition-all" 
                                       :class="form.role == '{{ $roleKey }}' ? 'active bg-primary text-white' : 'text-dark'" 
                                       @click="form.role = '{{ $roleKey }}'; roleOpen = false">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <span class="fw-bold" style="font-size: 0.9rem;">{{ $roleName }}</span>
                                            <i class="fa-solid fa-circle-check" style="font-size: 0.9rem;" :class="form.role == '{{ $roleKey }}' ? '' : 'opacity-0'"></i>
                                        </div>
                                    </a>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr class="border-secondary opacity-10 my-4">
                    <h6 class="fw-bold text-muted text-uppercase mb-3" style="font-size: 0.75rem; letter-spacing: 1px;">Branch Assignment <span class="text-danger">*</span></h6>
                    
                    <!-- Premium Multi-Branch Checkbox Tiles -->
                    <div class="row g-2 mb-3">
                        @forelse($branches as $b)
                            <div class="col-sm-6">
                                <label class="w-100 cursor-pointer h-100 m-0">
                                    <input type="checkbox" name="branches[]" value="{{ $b->id }}" x-model="form.branches" class="d-none peer-checkbox">
                                    <div class="card bg-light border-0 rounded transition-all h-100 checkbox-tile">
                                        <div class="card-body p-2 d-flex align-items-center gap-2">
                                            <div class="check-circle rounded-circle border d-flex align-items-center justify-content-center bg-white text-white flex-shrink-0" style="width: 20px; height: 20px; transition: all 0.2s;">
                                                <i class="fa-solid fa-check" style="font-size: 0.6rem;"></i>
                                            </div>
                                            <span class="fw-semibold text-dark lh-sm" style="font-size: 0.85rem;">{{ $b->name }}</span>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        @empty
                            <div class="col-12">
                                <div class="alert bg-warning-subtle text-warning-emphasis border-0 rounded mb-0 p-3 d-flex align-items-center gap-3">
                                    <i class="fa-solid fa-triangle-exclamation fs-5"></i>
                                    <div>
                                        <strong>No branches available.</strong><br>
                                        <span class="small">You must have an active branch to assign staff.</span>
                                    </div>
                                </div>
                            </div>
                        @endforelse
                    </div>
                    
                    <div x-show="isEdit" style="display: none;">
                        <div class="form-check form-switch mt-2 bg-light p-2 rounded d-flex align-items-center justify-content-between">
                            <label class="form-check-label fw-bold text-dark m-0" for="u_active" style="font-size: 0.9rem;">{{ __('Account Active') }}</label>
                            <input class="form-check-input m-0 fs-5 shadow-none cursor-pointer" type="checkbox" role="switch" name="is_active" value="1" id="u_active" x-model="form.is_active">
                        </div>
                    </div>
                    
                    <div x-show="!isEdit" class="mt-2 p-2 bg-primary-subtle text-primary-emphasis rounded small fw-medium d-flex gap-2 align-items-center">
                        <i class="fa-solid fa-lock fs-5 opacity-75 flex-shrink-0"></i> 
                        <span style="font-size: 0.8rem; line-height: 1.3;">{{ __('A secure password will be generated automatically and displayed to you after saving.') }}</span>
                    </div>
                </div>

                <div class="custom-overlay-footer">
                    <button type="button" @click="closeModal()" class="btn btn-light rounded-pill px-4 fw-bold">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">
                        <i class="fa-solid fa-check me-1"></i> <span x-text="isEdit ? 'Update' : 'Save'"></span>
                    </button>
                </div>
            </form>
        </div>
    </template>
</div>
@endsection

@push('scripts')
<style>
    /* Custom Overlay Modal CSS (to match Add Visitor) */
    .custom-overlay-backdrop {
        position: fixed; inset: 0;
        background: rgba(15, 23, 42, 0.6);
        backdrop-filter: blur(8px);
        z-index: 1040;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }
    .custom-overlay-modal {
        width: 100%; max-width: 550px;
        background: #fff;
        border-radius: 1.25rem;
        box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
        display: flex;
        flex-direction: column;
        max-height: 85vh;
        transform: scale(0.95);
        opacity: 0;
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        overflow: hidden;
    }
    .custom-overlay-modal.is-open { transform: scale(1); opacity: 1; }
    .custom-overlay-header {
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid rgba(0,0,0,0.05);
        display: flex; justify-content: space-between; align-items: center;
        background: #fff;
    }
    .custom-overlay-body {
        padding: 1.5rem;
        overflow-y: auto;
        flex-grow: 1;
        background: #fafafa;
    }
    .custom-overlay-footer {
        padding: 1.25rem 1.5rem;
        border-top: 1px solid rgba(0,0,0,0.05);
        background: #fff;
        display: flex; gap: 1rem; justify-content: flex-end;
    }

    /* Premium Checkbox Tile Styles */
    .peer-checkbox:checked + .checkbox-tile {
        background-color: var(--bs-primary-bg-subtle) !important;
        box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
        transform: translateY(-2px);
    }
    .peer-checkbox:checked + .checkbox-tile .check-circle {
        background-color: var(--bs-primary) !important;
        border-color: var(--bs-primary) !important;
    }
    .checkbox-tile:hover {
        background-color: #f1f5f9;
    }
    .cursor-pointer {
        cursor: pointer;
    }
</style>
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('usersManager', () => ({
            isModalOpen: false,
            isEdit: false,
            modalTitle: '{{ __('Add User') }}',
            formAction: '{{ route('admin.users.store') }}',
            formMethod: 'POST',
            form: {
                id: null,
                name: '',
                mobile: '',
                role: '',
                branches: [],
                is_active: true
            },
            
            openModal(user = null) {
                if (user) {
                    this.isEdit = true;
                    this.modalTitle = '{{ __('Edit User') }}';
                    this.formAction = '{{ url('admin/users') }}/' + user.id;
                    this.formMethod = 'PUT';
                    this.form.id = user.id;
                    this.form.name = user.name || '';
                    this.form.mobile = (user.mobile || '').slice(-10);
                    this.form.role = user.role || '';
                    this.form.branches = (user.branches || []).map(String); 
                    this.form.is_active = user.is_active;
                } else {
                    this.isEdit = false;
                    this.modalTitle = '{{ __('Add User') }}';
                    this.formAction = '{{ route('admin.users.store') }}';
                    this.formMethod = 'POST';
                    this.form.id = null;
                    this.form.name = '';
                    this.form.mobile = '';
                    this.form.role = '';
                    this.form.branches = [];
                    this.form.is_active = true;
                }
                this.isModalOpen = true;
                document.body.style.overflow = 'hidden';
            },
            
            closeModal() {
                this.isModalOpen = false;
                document.body.style.overflow = '';
            }
        }));
    });
</script>
@endpush
