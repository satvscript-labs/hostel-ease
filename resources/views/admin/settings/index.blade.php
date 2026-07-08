@extends('layouts.app')
@section('title', __('Settings'))

@push('styles')
<style>
    /* Premium Tab Navigation */
    .settings-tabs {
        border-bottom: 2px solid rgba(0, 0, 0, 0.05);
        display: flex;
        gap: 2rem;
        margin-bottom: 2rem;
    }
    .settings-tab {
        padding: 1rem 0;
        font-weight: 600;
        color: #64748b;
        cursor: pointer;
        border-bottom: 2px solid transparent;
        margin-bottom: -2px;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .settings-tab:hover {
        color: var(--bs-primary);
    }
    .settings-tab.active {
        color: var(--bs-primary);
        border-bottom-color: var(--bs-primary);
    }

    /* Branch Card Premium Styles */
    .branch-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(20px);
        border: 1px solid rgba(0, 0, 0, 0.04);
        border-radius: 1.5rem;
        padding: 1.5rem;
        box-shadow: 0 10px 30px rgba(0,0,0,0.03);
        transition: all 0.4s cubic-bezier(0.25, 1, 0.5, 1);
        position: relative;
        overflow: hidden;
    }
    .branch-card:hover { 
        transform: translateY(-6px); 
        box-shadow: 0 20px 40px rgba(0,0,0,0.06); 
    }
    
    .branch-card-active {
        background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
        color: white;
        border: none;
        box-shadow: 0 15px 35px rgba(79, 70, 229, 0.2);
    }
    .branch-card-active .text-dark { color: white !important; }
    .branch-card-active .text-muted { color: rgba(255,255,255,0.7) !important; }
    .branch-card-active .border-top { border-color: rgba(255,255,255,0.1) !important; }
    .branch-card-active .bg-primary.bg-opacity-10 { background: rgba(255,255,255,0.1) !important; color: white !important; }
    
    /* Glowing orb behind active card */
    .branch-card-active::before {
        content: '';
        position: absolute;
        top: -50px; right: -50px;
        width: 150px; height: 150px;
        background: radial-gradient(circle, rgba(147, 51, 234, 0.4) 0%, transparent 70%);
        border-radius: 50%;
        filter: blur(20px);
        pointer-events: none;
    }

    .branch-status-badge {
        font-size: 0.7rem;
        font-weight: 800;
        text-transform: uppercase;
        padding: 0.45rem 1rem;
        border-radius: 50px;
        letter-spacing: 1px;
    }
    
    /* Plan Selection Cards */
    .plan-card {
        border: 1px solid rgba(0,0,0,0.08);
        border-radius: 1rem;
        padding: 1.5rem;
        cursor: pointer;
        transition: all 0.25s ease;
        height: 100%;
        background: #ffffff;
        position: relative;
        box-shadow: 0 2px 8px rgba(0,0,0,0.02);
    }
    .plan-card:hover {
        border-color: rgba(79, 70, 229, 0.3);
        box-shadow: 0 8px 24px rgba(79, 70, 229, 0.06);
    }
    .plan-card.selected {
        border: 1.5px solid var(--bs-primary);
        background: rgba(79, 70, 229, 0.02);
        box-shadow: 0 8px 24px rgba(79, 70, 229, 0.08);
    }

    /* Custom Overlay Modal CSS */
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
        transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.3s;
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
@endpush

@section('content')
<div x-data="settingsManager()" class="page-enter pb-5">

    <!-- Header & Tabs -->
    <div class="mb-4">
        <h1 class="h3 fw-bold text-dark mb-1 tracking-tight">{{ __('Settings') }}</h1>
        <p class="text-muted mb-4 small">{{ __('Manage your account, staff access, branches, and subscriptions.') }}</p>
        
        <div class="settings-tabs">
            <div class="settings-tab" :class="{ 'active': activeTab === 'users' }" @click="activeTab = 'users'">
                <i class="fa-solid fa-users-gear"></i> {{ __('Users & Roles') }}
            </div>
            <div class="settings-tab" :class="{ 'active': activeTab === 'branches' }" @click="activeTab = 'branches'">
                <i class="fa-solid fa-building-circle-check"></i> {{ __('My Branches') }}
            </div>
        </div>
    </div>

    @if(session('credentials'))
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center border-0 shadow-sm rounded-4 mb-4 stagger-2" role="alert">
            <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width: 42px; height: 42px;">
                <i class="fa-solid fa-check"></i>
            </div>
            <div>
                <h6 class="fw-bold mb-1">{{ __('Login created successfully!') }}</h6>
                <p class="mb-0 small">
                    {{ __('Please share these credentials once:') }}<br>
                    <span class="text-dark fw-medium">{{ __('Mobile:') }}</span> <code class="fs-6 bg-transparent text-dark fw-bold p-0">{{ session('credentials')['mobile'] }}</code> &mdash;
                    <span class="text-dark fw-medium">{{ __('Password:') }}</span> <code class="fs-6 bg-transparent text-dark fw-bold p-0">{{ session('credentials')['password'] }}</code>
                </p>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4 stagger-2">
            <i class="fa-solid fa-circle-check me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- TAB 1: USERS & ROLES -->
    <div x-show="activeTab === 'users'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 transform translate-y-4" x-transition:enter-end="opacity-100 transform translate-y-0" style="display: none;">
        
        <div class="d-flex justify-content-end mb-3">
            <button type="button" @click="openUserModal()" class="btn btn-primary rounded-pill shadow-sm px-4 fw-semibold tactile-btn">
                <i class="fa-solid fa-user-plus me-1"></i> {{ __('Add User') }}
            </button>
        </div>

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
                                    <button type="button" @click='openUserModal(<?php echo json_encode($ud); ?>)' class="btn btn-sm btn-light rounded-circle shadow-sm mx-1" style="width: 32px; height: 32px" title="{{ __('Edit User') }}">
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
                                        <button type="button" @click="openUserModal()" class="btn btn-primary rounded-pill shadow-sm px-4 fw-semibold tactile-btn">
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
    </div> <!-- END TAB 1 -->

    <!-- TAB 2: MY BRANCHES -->
    <div x-show="activeTab === 'branches'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 transform translate-y-4" x-transition:enter-end="opacity-100 transform translate-y-0" style="display: none;">
        
        <div class="d-flex justify-content-end mb-3">
            <button type="button" @click="openBranchModal()" class="btn btn-primary rounded-pill shadow-sm px-4 fw-semibold tactile-btn">
                <i class="fa-solid fa-plus me-1"></i> {{ __('Add New Branch') }}
            </button>
        </div>

        <div class="row g-4 stagger">
            @forelse($myBranches as $branch)
            <div class="col-md-6 col-lg-4">
                <div class="branch-card {{ session('active_hostel_id') == $branch->id ? 'branch-card-active' : '' }} d-flex flex-column h-100">
                    
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                                <i class="fa-solid fa-building fs-4"></i>
                            </div>
                            <div>
                                <h4 class="fw-bold mb-0 text-dark">{{ $branch->name }}</h4>
                                <div class="text-muted small">{{ $branch->city ?? 'Location pending' }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex align-items-center gap-2 mb-4 mt-2">
                        @if($branch->isActive())
                            <span class="branch-status-badge bg-success bg-opacity-10 text-success">
                                <i class="fa-solid fa-circle-check me-1"></i> Active
                            </span>
                            <span class="small text-muted fw-bold">Ends {{ $branch->subscription_end?->format('d M, Y') }}</span>
                        @else
                            <span class="branch-status-badge bg-danger bg-opacity-10 text-danger">
                                <i class="fa-solid fa-triangle-exclamation me-1"></i> Expired
                            </span>
                            <span class="small text-muted fw-bold">Inactive Branch</span>
                        @endif
                    </div>

                    <div class="mt-auto pt-4 border-top d-flex gap-3 align-items-center">
                        @if(session('active_hostel_id') == $branch->id)
                            <div class="d-flex align-items-center gap-2 text-success fw-bold flex-grow-1">
                                <i class="fa-solid fa-circle-check fs-5"></i> Current Branch
                            </div>
                        @else
                            <a href="{{ route('branch.switch', $branch->id) }}" class="btn btn-primary rounded-pill flex-grow-1 fw-bold shadow-sm d-flex justify-content-center align-items-center gap-2">
                                <i class="fa-solid fa-right-left"></i> Switch
                            </a>
                        @endif
                        
                        <button type="button" @click="openRenewModal({{ $branch->id }}, '{{ addslashes($branch->name) }}')" 
                                class="btn {{ session('active_hostel_id') == $branch->id ? 'btn-light text-dark' : 'btn-outline-primary' }} rounded-circle d-flex align-items-center justify-content-center" 
                                style="width: 44px; height: 44px; transition: transform 0.2s;" title="Renew Subscription"
                                onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'">
                            <i class="fa-solid fa-bolt text-warning" style="font-size: 1.1rem;"></i>
                        </button>
                    </div>
                </div>
            </div>
            @empty
            <div class="col-12 text-center py-5 opacity-50">
                <i class="fa-solid fa-building-circle-exclamation fs-1 mb-3 text-muted"></i>
                <h4 class="fw-bold text-muted">No branches found</h4>
                <p class="text-muted">You don't have any branches in your account yet.</p>
            </div>
            @endforelse
        </div>
    </div> <!-- END TAB 2 -->

    <!-- MODALS -->

    <!-- User Modal (Teleported) -->
    <template x-teleport="body">
        <div x-show="modals.user.open" class="custom-overlay-backdrop" style="display: none;" x-transition.opacity @click="modals.user.open = false" x-cloak>
            <form :action="modals.user.action" method="POST" class="custom-overlay-modal" :class="{ 'is-open': modals.user.open }" x-show="modals.user.open" x-transition.opacity @click.stop style="display: none;">
                @csrf
                <input type="hidden" name="_method" :value="modals.user.method">
                
                <div class="custom-overlay-header">
                    <h5 class="fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                        <i class="fa-solid fa-user-plus text-primary"></i>
                        <span x-text="modals.user.title"></span>
                    </h5>
                    <button type="button" @click="modals.user.open = false" class="btn-close shadow-none"></button>
                </div>

                <div class="custom-overlay-body">
                    <h6 class="fw-bold text-muted text-uppercase mb-3" style="font-size: 0.75rem; letter-spacing: 1px;">User Details</h6>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" x-model="modals.user.form.name" class="form-control bg-light" required placeholder="Enter user name">
                    </div>
                    
                    <div class="mb-3" x-show="!modals.user.isEdit">
                        <label class="form-label fw-bold small">Mobile (Login ID) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border text-muted fw-bold">+91</span>
                            <input type="tel" name="mobile" x-model="modals.user.form.mobile" maxlength="10" inputmode="numeric" class="form-control bg-light border-start-0 ps-2" placeholder="9876543210" :required="!modals.user.isEdit">
                        </div>
                    </div>
                    
                    <!-- Premium Alpine Dropdown for Role (Resized) -->
                    <div class="mb-3" x-data="{ roleOpen: false }">
                        <label class="form-label fw-bold small">Role & Permissions <span class="text-danger">*</span></label>
                        
                        <div class="position-relative">
                            <input type="hidden" name="role" x-model="modals.user.form.role" required>
                            
                            <div class="d-flex align-items-center justify-content-between bg-light p-2 rounded border transition-all" 
                                 @click="roleOpen = !roleOpen" 
                                 style="cursor: pointer;"
                                 :class="roleOpen ? 'border-primary shadow-sm bg-white' : ''">
                                
                                <div class="d-flex align-items-center gap-2">
                                    <div class="bg-white text-primary rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 32px; height: 32px;">
                                        <i class="fa-solid fa-user-shield" style="font-size: 0.8rem;"></i>
                                    </div>
                                    <div class="d-flex flex-column">
                                        <span class="fw-bold text-dark" style="font-size: 0.9rem; line-height: 1;" x-text="modals.user.form.role ? modals.user.form.role.charAt(0).toUpperCase() + modals.user.form.role.slice(1) : '{{ __('-- Select Role --') }}'"></span>
                                        <span class="text-muted small mt-1" style="font-size: 0.7rem;" x-show="modals.user.form.role === ''">Choose level of access</span>
                                    </div>
                                </div>
                                <i class="fa-solid fa-chevron-down text-muted small me-2 transition-all" :class="{'fa-chevron-up': roleOpen}"></i>
                            </div>
                            
                            <div x-show="roleOpen" @click.outside="roleOpen = false" x-transition.opacity.duration.200ms class="position-absolute bg-white border rounded shadow-lg mt-1 w-100" style="display: none; z-index: 1050; max-height: 200px; overflow-y: auto;">
                                <div class="list-group list-group-flush py-1">
                                    @foreach($roles as $roleKey => $roleName)
                                    <a href="javascript:void(0)" class="list-group-item list-group-item-action border-0 py-2 px-3 transition-all" 
                                       :class="modals.user.form.role == '{{ $roleKey }}' ? 'active bg-primary text-white' : 'text-dark'" 
                                       @click="modals.user.form.role = '{{ $roleKey }}'; roleOpen = false">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <span class="fw-bold" style="font-size: 0.9rem;">{{ $roleName }}</span>
                                            <i class="fa-solid fa-circle-check" style="font-size: 0.9rem;" :class="modals.user.form.role == '{{ $roleKey }}' ? '' : 'opacity-0'"></i>
                                        </div>
                                    </a>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr class="border-secondary opacity-10 my-4">
                    <h6 class="fw-bold text-muted text-uppercase mb-3" style="font-size: 0.75rem; letter-spacing: 1px;">Branch Assignment <span class="text-danger">*</span></h6>
                    
                    <div class="row g-2 mb-3">
                        @forelse($userBranches as $b)
                            <div class="col-sm-6">
                                <label class="w-100 cursor-pointer h-100 m-0">
                                    <input type="checkbox" name="branches[]" value="{{ $b->id }}" x-model="modals.user.form.branches" class="d-none peer-checkbox">
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
                    
                    <div x-show="modals.user.isEdit" style="display: none;">
                        <div class="form-check form-switch mt-2 bg-light p-2 rounded d-flex align-items-center justify-content-between">
                            <label class="form-check-label fw-bold text-dark m-0" for="u_active" style="font-size: 0.9rem;">{{ __('Account Active') }}</label>
                            <input class="form-check-input m-0 fs-5 shadow-none cursor-pointer" type="checkbox" role="switch" name="is_active" value="1" id="u_active" x-model="modals.user.form.is_active">
                        </div>
                    </div>
                    
                    <div x-show="!modals.user.isEdit" class="mt-2 p-2 bg-primary-subtle text-primary-emphasis rounded small fw-medium d-flex gap-2 align-items-center">
                        <i class="fa-solid fa-lock fs-5 opacity-75 flex-shrink-0"></i> 
                        <span style="font-size: 0.8rem; line-height: 1.3;">{{ __('A secure password will be generated automatically and displayed to you after saving.') }}</span>
                    </div>
                </div>

                <div class="custom-overlay-footer">
                    <button type="button" @click="modals.user.open = false" class="btn btn-light rounded-pill px-4 fw-bold">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">
                        <i class="fa-solid fa-check me-1"></i> <span x-text="modals.user.isEdit ? 'Update' : 'Save'"></span>
                    </button>
                </div>
            </form>
        </div>
    </template>

    <!-- Branch Modal (Teleported) -->
    <template x-teleport="body">
        <div class="custom-overlay-backdrop" x-show="modals.branch.open" x-transition.opacity @click="modals.branch.open = false" x-cloak style="display: none;">
            <form action="{{ route('admin.branches.store') }}" method="POST" class="custom-overlay-modal" :class="{ 'is-open': modals.branch.open }" x-show="modals.branch.open" @click.stop style="display: none;">
                @csrf
                <div class="custom-overlay-header">
                    <h5 class="fw-bold mb-0">Add New Branch</h5>
                    <button type="button" class="btn-close" @click="modals.branch.open = false"></button>
                </div>
                <div class="custom-overlay-body">
                    <div class="mb-4">
                        <label class="form-label fw-bold small text-uppercase text-muted">Branch Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control form-control-lg bg-light border-0" placeholder="e.g. Skyline Hostel North" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase text-muted">Address</label>
                        <input type="text" name="address" class="form-control bg-light border-0" placeholder="Street Address">
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <label class="form-label fw-bold small text-uppercase text-muted">City</label>
                            <input type="text" name="city" class="form-control bg-light border-0" placeholder="City">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small text-uppercase text-muted">State</label>
                            <input type="text" name="state" class="form-control bg-light border-0" placeholder="State">
                        </div>
                    </div>
                </div>
                <div class="custom-overlay-footer bg-light">
                    <button type="button" class="btn btn-light rounded-pill px-4 fw-bold" @click="modals.branch.open = false">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">Create Branch</button>
                </div>
            </form>
        </div>
    </template>

    <!-- Renew Modal (Teleported) -->
    <template x-teleport="body">
        <div class="custom-overlay-backdrop" x-show="modals.renew.open" x-transition.opacity @click="modals.renew.open = false" x-cloak style="display: none;">
            <div class="custom-overlay-modal" :class="{ 'is-open': modals.renew.open }" x-show="modals.renew.open" @click.stop style="display: none; max-width: 600px;">
                <div class="custom-overlay-header">
                    <div>
                        <h5 class="fw-bold mb-1">Renew Subscription</h5>
                        <div class="text-muted small" x-text="modals.renew.branchName"></div>
                    </div>
                    <button type="button" class="btn-close" @click="modals.renew.open = false"></button>
                </div>
                <div class="custom-overlay-body px-4 py-4">
                    
                    <div class="mb-4 text-center">
                        <h5 class="fw-bold mb-1 text-dark">Select a Plan</h5>
                        <p class="text-muted small mb-0">Choose a subscription to unlock full access for this branch.</p>
                    </div>
                    
                    <div class="row g-3 mb-2">
                        <div class="col-6">
                            <div class="plan-card d-flex flex-column h-100" :class="{ 'selected': modals.renew.period === 'monthly' }" @click="modals.renew.period = 'monthly'">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div class="fw-bold text-uppercase small text-muted" :class="{'text-primary': modals.renew.period === 'monthly'}">Monthly</div>
                                    <div class="text-primary small" x-show="modals.renew.period === 'monthly'"><i class="fa-solid fa-circle-check fs-6"></i></div>
                                </div>
                                <h3 class="fw-bold text-dark mb-1">{{ hostelease_money($monthlyPrice) }}</h3>
                                <div class="small text-muted mt-auto pt-2">Billed monthly</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="plan-card d-flex flex-column h-100" :class="{ 'selected': modals.renew.period === 'yearly' }" @click="modals.renew.period = 'yearly'">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div class="fw-bold text-uppercase small text-muted" :class="{'text-primary': modals.renew.period === 'yearly'}">Yearly</div>
                                    <div class="text-primary small" x-show="modals.renew.period === 'yearly'"><i class="fa-solid fa-circle-check fs-6"></i></div>
                                </div>
                                <h3 class="fw-bold text-dark mb-1">{{ hostelease_money($yearlyPrice) }}</h3>
                                <div class="small text-success fw-bold mt-auto pt-2">Save 16% annually</div>
                            </div>
                        </div>
                    </div>
                    
                </div>
                <div class="custom-overlay-footer bg-light">
                    <button type="button" class="btn btn-light rounded-pill px-4 fw-bold" @click="modals.renew.open = false">Cancel</button>
                    @if($razorpayEnabled)
                        <button type="button" @click="payWithRazorpay()" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm d-flex align-items-center gap-2" :disabled="modals.renew.loading">
                            <span x-show="!modals.renew.loading">Proceed to Payment</span>
                            <span x-show="modals.renew.loading" class="spinner-border spinner-border-sm"></span>
                        </button>
                    @else
                        <button type="button" class="btn btn-secondary rounded-pill px-5 fw-bold" disabled>Payments Disabled</button>
                    @endif
                </div>
            </div>
        </div>
    </template>

</div>
@endsection

@push('scripts')
@if($razorpayEnabled)
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
@endif
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('settingsManager', () => ({
            activeTab: '{{ session('active_tab', request('tab', 'users')) }}',
            
            modals: {
                user: {
                    open: false,
                    isEdit: false,
                    title: '{{ __('Add User') }}',
                    action: '{{ route('admin.users.store') }}',
                    method: 'POST',
                    form: {
                        id: null,
                        name: '',
                        mobile: '',
                        role: '',
                        branches: [],
                        is_active: true
                    }
                },
                branch: {
                    open: false
                },
                renew: {
                    open: false,
                    branchId: null,
                    branchName: '',
                    period: 'yearly',
                    loading: false
                }
            },
            
            openUserModal(user = null) {
                if (user) {
                    this.modals.user.isEdit = true;
                    this.modals.user.title = '{{ __('Edit User') }}';
                    this.modals.user.action = '{{ url('admin/users') }}/' + user.id;
                    this.modals.user.method = 'PUT';
                    this.modals.user.form.id = user.id;
                    this.modals.user.form.name = user.name || '';
                    this.modals.user.form.mobile = (user.mobile || '').slice(-10);
                    this.modals.user.form.role = user.role || '';
                    this.modals.user.form.branches = (user.branches || []).map(String); 
                    this.modals.user.form.is_active = user.is_active;
                } else {
                    this.modals.user.isEdit = false;
                    this.modals.user.title = '{{ __('Add User') }}';
                    this.modals.user.action = '{{ route('admin.users.store') }}';
                    this.modals.user.method = 'POST';
                    this.modals.user.form.id = null;
                    this.modals.user.form.name = '';
                    this.modals.user.form.mobile = '';
                    this.modals.user.form.role = '';
                    this.modals.user.form.branches = [];
                    this.modals.user.form.is_active = true;
                }
                this.modals.user.open = true;
                document.body.style.overflow = 'hidden';
            },

            openBranchModal() {
                this.modals.branch.open = true;
                document.body.style.overflow = 'hidden';
            },
            
            openRenewModal(id, name) {
                this.modals.renew.branchId = id;
                this.modals.renew.branchName = name;
                this.modals.renew.period = 'yearly';
                this.modals.renew.loading = false;
                this.modals.renew.open = true;
                document.body.style.overflow = 'hidden';
            },
            
            async payWithRazorpay() {
                this.modals.renew.loading = true;
                try {
                    // 1. Create order on server
                    const orderRes = await fetch('{{ route('admin.branches.order') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            branch_id: this.modals.renew.branchId,
                            period: this.modals.renew.period
                        })
                    });
                    
                    const orderData = await orderRes.json();
                    
                    if (!orderRes.ok) {
                        throw new Error(orderData.message || 'Failed to create order');
                    }
                    
                    // 2. Open Razorpay Checkout
                    const options = {
                        key: orderData.key,
                        amount: orderData.amount,
                        currency: orderData.currency,
                        name: orderData.name,
                        description: orderData.description,
                        order_id: orderData.order_id,
                        prefill: orderData.prefill,
                        theme: { color: '#4f46e5' },
                        handler: async (response) => {
                            // 3. Verify payment on server
                            const verifyRes = await fetch('{{ route('admin.branches.verify') }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                    'Accept': 'application/json'
                                },
                                body: JSON.stringify({
                                    branch_id: this.modals.renew.branchId,
                                    period: orderData.period,
                                    razorpay_order_id: response.razorpay_order_id,
                                    razorpay_payment_id: response.razorpay_payment_id,
                                    razorpay_signature: response.razorpay_signature
                                })
                            });
                            
                            const verifyData = await verifyRes.json();
                            
                            if (verifyRes.ok) {
                                window.location.href = verifyData.redirect;
                            } else {
                                alert(verifyData.message || 'Payment verification failed');
                            }
                        }
                    };
                    
                    const rzp = new Razorpay(options);
                    rzp.on('payment.failed', function (response) {
                        alert('Payment Failed: ' + response.error.description);
                    });
                    rzp.open();
                    
                } catch (error) {
                    alert(error.message);
                } finally {
                    this.modals.renew.loading = false;
                }
            }
        }));
    });
</script>
@endpush
