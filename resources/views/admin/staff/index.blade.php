@extends('layouts.app')
@section('title', __('Staff Board'))

@section('content')
<style>
    /* iOS Smoothness & Hover Micro-Motion */
    .glass-tile {
        background: #ffffff;
        border: 1px solid rgba(0,0,0,0.02);
        transition: transform 0.4s cubic-bezier(0.25, 1, 0.5, 1), box-shadow 0.4s cubic-bezier(0.25, 1, 0.5, 1);
    }
    .glass-tile:hover {
        transform: translateY(-4px);
        box-shadow: 0 15px 35px rgba(0,0,0,0.05) !important;
    }
    .tactile-btn {
        transition: transform 0.2s cubic-bezier(0.25, 1, 0.5, 1), background-color 0.2s ease, box-shadow 0.2s ease;
    }
    .tactile-btn:active {
        transform: scale(0.97);
    }
    
    /* Glowing Avatar wrapper */
    .tile-icon-wrapper {
        position: relative;
        z-index: 2;
    }
    .tile-icon-wrapper::after {
        content: '';
        position: absolute;
        inset: 0;
        background: inherit;
        filter: blur(12px);
        opacity: 0.6;
        z-index: -1;
        border-radius: inherit;
        transition: opacity 0.4s ease;
    }
    .glass-tile:hover .tile-icon-wrapper::after {
        opacity: 0.9;
    }

    /* Staggered Fade Up Animations */
    .stagger-1 { animation: fadeUp 0.6s cubic-bezier(0.25, 1, 0.5, 1) 0.1s both; }
    .stagger-2 { animation: fadeUp 0.6s cubic-bezier(0.25, 1, 0.5, 1) 0.15s both; }
    .stagger-3 { animation: fadeUp 0.6s cubic-bezier(0.25, 1, 0.5, 1) 0.2s both; }
    
    @keyframes fadeUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Fluid Attendance Pills */
    .attendance-pill-group {
        background: #f8fafc;
        border: 1px solid rgba(0,0,0,0.05);
        padding: 4px;
        border-radius: 50px;
    }
    .attendance-pill {
        border-radius: 50px;
        transition: all 0.3s cubic-bezier(0.25, 1, 0.5, 1);
        color: var(--he-text-muted);
        font-weight: 600;
        border: none;
        background: transparent;
    }
    .attendance-pill:active { transform: scale(0.95); }
    
    .btn-check:checked + .att-success { background: var(--he-success); color: white; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3); }
    .btn-check:checked + .att-danger { background: var(--he-danger); color: white; box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3); }
    .btn-check:checked + .att-warning { background: var(--he-warning); color: #000; box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3); }
    .btn-check:checked + .att-secondary { background: var(--he-text-muted); color: white; box-shadow: 0 4px 15px rgba(100, 116, 139, 0.3); }
</style>

<div x-data="staffBoard()" @tab-changed.window="tab = $event.detail" class="page-enter">

    <div class="d-flex align-items-center justify-content-between mb-4 stagger-1">
        <div>
            <h1 class="h3 mb-0 fw-bold tracking-tight text-dark">{{ __('Staff & Payroll') }}</h1>
            <p class="text-secondary">{{ __('Manage staff directory, payroll, and daily attendance.') }}</p>
        </div>
        <div>
            <button class="btn btn-dark rounded-pill shadow-sm px-4 fw-semibold tactile-btn" @click="staffModalOpen = true; resetStaff()">
                <i class="fa-solid fa-user-plus me-2"></i> {{ __('Add Staff') }}
            </button>
        </div>
    </div>

    <!-- Hero Mesh & Glass Tiles -->
    <div class="row g-4 mb-4 stagger-2">
        <div class="col-lg-8">
            <div class="card border-0 rounded-4 overflow-hidden h-100 position-relative shadow-sm" style="background: var(--he-gradient-mesh);">
                <div class="position-absolute top-0 start-0 w-100 h-100 opacity-50" style="background-image: radial-gradient(circle at top right, rgba(147,51,234,0.4), transparent 60%); z-index: 1;"></div>
                <div class="card-body p-4 position-relative d-flex flex-column justify-content-center" style="z-index: 2;">
                    <div class="d-flex align-items-center mb-3">
                        <span class="badge bg-white bg-opacity-10 text-white rounded-pill px-3 py-2 fw-semibold" style="backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.1);"><i class="fa-solid fa-users me-2 text-info"></i> Total Active Staff</span>
                    </div>
                    <div class="display-5 fw-bold text-white mb-1 tracking-tight">{{ $summary['active'] }} <span class="fs-4 text-white opacity-50 fw-medium">/ {{ $summary['total'] }}</span></div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card glass-tile rounded-4 h-100">
                <div class="card-body p-4 d-flex flex-column justify-content-center">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="text-secondary fw-bold text-uppercase letter-spacing-1 small">Monthly Payroll</div>
                        <div class="tile-icon-wrapper bg-success bg-opacity-10 text-success rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                            <i class="fa-solid fa-money-bill-wave"></i>
                        </div>
                    </div>
                    <div class="h2 fw-bold text-success mb-0 tracking-tight" style="font-feature-settings: 'tnum';">{{ hostelease_money($summary['payroll']) }}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs (Fluid switching) -->
    <div class="he-tabs mb-4 stagger-3 border-bottom">
        <button class="he-tab bg-transparent border-0 py-3 px-4 fw-medium text-secondary position-relative tactile-btn" 
                :class="{ 'text-dark fw-bold': tab === 'directory' }" @click="switchTab('directory')">
            <i class="fa-solid fa-address-book me-1"></i> {{ __('Directory & Payroll') }}
            <div x-show="tab === 'directory'" class="position-absolute bottom-0 start-0 w-100" style="height: 3px; background: var(--he-primary); border-radius: 3px 3px 0 0;" x-transition></div>
        </button>
        <button class="he-tab bg-transparent border-0 py-3 px-4 fw-medium text-secondary position-relative tactile-btn" 
                :class="{ 'text-dark fw-bold': tab === 'attendance' }" @click="switchTab('attendance')">
            <i class="fa-solid fa-clipboard-user me-1"></i> {{ __('Attendance') }}
            <div x-show="tab === 'attendance'" class="position-absolute bottom-0 start-0 w-100" style="height: 3px; background: var(--he-primary); border-radius: 3px 3px 0 0;" x-transition></div>
        </button>
    </div>

    <!-- Directory Tab -->
    <div x-show="tab === 'directory'" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-4"
         x-transition:enter-end="opacity-100 translate-y-0"
         style="display: none;">
         
        <!-- Pill Search Bar -->
        <div class="mb-4 position-relative">
            <i class="fa-solid fa-magnifying-glass position-absolute text-primary" style="left: 1.5rem; top: 50%; transform: translateY(-50%); z-index: 3;"></i>
            <input type="text" x-model="search" class="form-control rounded-pill bg-white shadow-sm border-0 position-relative" style="padding-left: 3.5rem; height: 3.5rem; font-size: 1.05rem; z-index: 2; transition: all 0.3s ease; box-shadow: 0 5px 15px rgba(0,0,0,0.03) !important;" placeholder="Search by name, role or mobile..." onfocus="this.style.boxShadow='0 0 0 4px var(--he-primary-soft), 0 10px 25px rgba(0,0,0,0.05)'" onblur="this.style.boxShadow='0 5px 15px rgba(0,0,0,0.03)'">
        </div>

        <div class="row g-4">
            @forelse($staff as $index => $s)
            <div class="col-md-6 col-lg-4" x-show="search === '' || '{{ strtolower($s->name . ' ' . $s->designation . ' ' . $s->mobile) }}'.includes(search.toLowerCase())" x-transition.opacity.duration.300ms>
                <div class="card glass-tile rounded-4 h-100 position-relative overflow-hidden {{ !$s->is_active ? 'opacity-75' : '' }}" style="animation: fadeUp 0.6s cubic-bezier(0.25, 1, 0.5, 1) {{ min($index * 0.05, 0.5) }}s both;">
                    <a href="{{ route('admin.staff.show', $s) }}" class="stretched-link" style="z-index: 1;"></a>
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start mb-4">
                            <div class="d-flex gap-3 align-items-center">
                                <div class="tile-icon-wrapper bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 56px; height: 56px; font-size: 1.25rem; font-weight: 700;">
                                    {{ substr($s->name, 0, 1) }}
                                </div>
                                <div>
                                    <h5 class="mb-1 fw-bold text-dark tracking-tight">{{ $s->name }}</h5>
                                    <div class="text-primary small fw-bold text-uppercase letter-spacing-1 bg-primary bg-opacity-10 px-2 py-1 rounded d-inline-block">{{ $s->designation ?? 'Staff Member' }}</div>
                                </div>
                            </div>
                            <div style="position: relative; z-index: 2;">
                                @if($s->is_active)
                                    <div class="rounded-circle bg-success shadow-sm" style="width: 12px; height: 12px; box-shadow: 0 0 10px rgba(16, 185, 129, 0.5) !important;"></div>
                                @else
                                    <div class="rounded-circle bg-secondary shadow-sm" style="width: 12px; height: 12px;"></div>
                                @endif
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-6">
                                <div class="text-secondary fw-bold text-uppercase letter-spacing-1 small" style="font-size: 0.65rem;">Mobile</div>
                                <div class="fw-bold text-dark" style="position: relative; z-index: 2;">
                                    @if($s->mobile)
                                        <x-mobile-link :mobile="$s->mobile" />
                                    @else
                                        <span class="text-muted fw-normal">—</span>
                                    @endif
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-secondary fw-bold text-uppercase letter-spacing-1 small" style="font-size: 0.65rem;">Salary</div>
                                <div class="fw-bold text-dark" style="font-feature-settings: 'tnum';">{{ hostelease_money($s->monthly_salary) }}</div>
                            </div>
                            <div class="col-6">
                                <div class="text-secondary fw-bold text-uppercase letter-spacing-1 small" style="font-size: 0.65rem;">Attendance</div>
                                <div class="fw-bold text-dark">{{ $s->present_this_month }} <span class="text-muted fw-normal small">days</span></div>
                            </div>
                            <div class="col-6">
                                <div class="text-secondary fw-bold text-uppercase letter-spacing-1 small" style="font-size: 0.65rem;">Paid This Month</div>
                                <div class="fw-bold text-success" style="font-feature-settings: 'tnum';">{{ hostelease_money($s->paid_this_month) }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-light border-0 p-3 d-flex gap-2 rounded-bottom-4" style="position: relative; z-index: 2;">
                        <button class="btn btn-success fw-semibold flex-grow-1 shadow-sm rounded-pill tactile-btn" @click.prevent="salaryModalOpen = true; paySalary({{ $s->id }}, @js($s->name))">
                            <i class="fa-solid fa-money-bill-wave me-1"></i> Pay Salary
                        </button>
                    </div>
                </div>
            </div>
            @empty
            <div class="col-12 text-center py-5">
                <div class="empty-state">
                    <i class="fa-solid fa-id-badge text-secondary fs-1 mb-3 opacity-25" style="font-size: 3rem !important;"></i>
                    <h5 class="fw-bold text-dark">No staff members found</h5>
                    <div class="text-secondary">Click "Add Staff" to create your first employee record.</div>
                </div>
            </div>
            @endforelse
        </div>
    </div>

    <!-- Attendance Tab -->
    <div x-show="tab === 'attendance'" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-4"
         x-transition:enter-end="opacity-100 translate-y-0"
         style="display: none;">
         
        <div class="card glass-tile rounded-4 overflow-hidden">
            <div class="card-header bg-white border-bottom p-4 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="fw-bold text-dark mb-1">Daily Attendance</h5>
                    <div class="text-secondary small">Mark presence, absence, or leaves for your staff.</div>
                </div>
                <form method="GET" class="d-flex align-items-center gap-2">
                    <input type="hidden" name="tab" value="attendance">
                    <div class="bg-light rounded-pill px-3 py-2 d-flex align-items-center border border-light shadow-sm">
                        <i class="fa-regular fa-calendar text-primary me-2"></i>
                        <input type="date" name="date" value="{{ $date }}" class="form-control form-control-sm bg-transparent border-0 shadow-none p-0 fw-semibold" style="width: 120px;" onchange="this.form.submit()">
                    </div>
                </form>
            </div>
            <div class="card-body p-0">
                <form method="POST" action="{{ route('admin.staff.attendance.save') }}">
                    @csrf
                    <input type="hidden" name="date" value="{{ $date }}">
                    
                    @if($staff->where('is_active', true)->isEmpty())
                        <div class="empty-state py-5 text-center">
                            <i class="fa-solid fa-clipboard-check text-secondary fs-1 mb-3 opacity-25" style="font-size: 3rem !important;"></i>
                            <h5 class="fw-bold text-dark">No active staff</h5>
                            <div class="text-secondary">Add active staff members to mark attendance.</div>
                        </div>
                    @else
                        <div class="d-flex flex-column">
                            @foreach($staff->where('is_active', true) as $index => $s)
                                @php($cur = $marks[$s->id]->status ?? 'present')
                                <div class="p-4 border-bottom d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 bg-white" style="animation: fadeUp 0.4s cubic-bezier(0.25, 1, 0.5, 1) {{ min($index * 0.05, 0.4) }}s both;">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="tile-icon-wrapper bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 48px; height: 48px;">
                                            {{ substr($s->name, 0, 1) }}
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark fs-5 mb-1">{{ $s->name }}</div>
                                            <div class="text-secondary small fw-bold text-uppercase letter-spacing-1">{{ $s->designation ?? 'Staff Member' }}</div>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <div class="attendance-pill-group d-flex" role="group">
                                            @foreach([
                                                'present' => ['label' => 'Present', 'color' => 'success', 'icon' => 'check'],
                                                'absent' => ['label' => 'Absent', 'color' => 'danger', 'icon' => 'xmark'],
                                                'half_day' => ['label' => 'Half', 'color' => 'warning', 'icon' => 'star-half-stroke'],
                                                'leave' => ['label' => 'Leave', 'color' => 'secondary', 'icon' => 'calendar-minus']
                                            ] as $val => $opt)
                                                <input type="radio" class="btn-check" name="status[{{ $s->id }}]" id="a{{ $s->id }}_{{ $val }}" value="{{ $val }}" @checked($cur===$val)>
                                                <label class="btn btn-sm px-3 attendance-pill att-{{ $opt['color'] }}" for="a{{ $s->id }}_{{ $val }}">
                                                    <i class="fa-solid fa-{{ $opt['icon'] }} me-1 d-none d-sm-inline"></i> {{ $opt['label'] }}
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="p-4 bg-light d-flex justify-content-end border-top">
                            <button class="btn btn-dark fw-semibold rounded-pill px-5 py-2 shadow-sm tactile-btn"><i class="fa-solid fa-save me-2"></i> Save Attendance</button>
                        </div>
                    @endif
                </form>
            </div>
        </div>
    </div>

{{-- Add Staff Modal (Teleported) --}}
<template x-teleport="body">
    <div class="custom-overlay-backdrop" x-show="staffModalOpen" x-transition.opacity.duration.300ms @click="staffModalOpen = false" x-cloak style="display: none;">
        <div class="custom-overlay-modal" :class="{ 'is-open': staffModalOpen }" @click.stop x-show="staffModalOpen" x-transition.opacity style="display: none;">
            
            <form id="staffForm" method="POST" action="{{ route('admin.staff.store') }}">
                @csrf
                <input type="hidden" name="_method" id="staffMethod" value="POST">
                
                <div class="custom-overlay-header">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-user-plus text-primary me-2"></i><span id="staffTitle">Add Staff</span></h5>
                    <button type="button" class="btn-close shadow-none tactile-btn" @click="staffModalOpen = false"></button>
                </div>
                
                <div class="custom-overlay-body">
                    <div class="row g-4">
                        <div class="col-md-12">
                            <label class="form-label fw-bold text-dark small text-uppercase letter-spacing-1">Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="st_name" class="form-control form-control-lg bg-light border-0 shadow-none" required placeholder="e.g. John Doe">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark small text-uppercase letter-spacing-1">Designation</label>
                            <input type="text" name="designation" id="st_designation" class="form-control bg-light border-0 shadow-none" placeholder="Cook, Guard…">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark small text-uppercase letter-spacing-1">Mobile</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0 text-muted fw-bold">+91</span>
                                <input type="tel" name="mobile" id="st_mobile" maxlength="10" class="form-control bg-light border-0 shadow-none" placeholder="10-digit number">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark small text-uppercase letter-spacing-1">Monthly Salary <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0 text-muted fw-bold">₹</span>
                                <input type="number" step="0.01" name="monthly_salary" id="st_salary" class="form-control bg-light border-0 shadow-none" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark small text-uppercase letter-spacing-1">Join Date</label>
                            <input type="date" name="join_date" id="st_join" class="form-control bg-light border-0 shadow-none">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold text-dark small text-uppercase letter-spacing-1">Address</label>
                            <input type="text" name="address" id="st_address" class="form-control bg-light border-0 shadow-none" placeholder="Full residential address">
                        </div>
                        <div class="col-12">
                            <div class="card bg-primary bg-opacity-10 border-0 rounded-4">
                                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                                    <div>
                                        <div class="fw-bold text-primary mb-1">Active Status</div>
                                        <div class="small text-primary opacity-75">Enable if this staff member is currently working.</div>
                                    </div>
                                    <div class="form-check form-switch fs-4 mb-0">
                                        <input class="form-check-input shadow-none" type="checkbox" role="switch" name="is_active" value="1" id="st_active" checked>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="custom-overlay-footer bg-light">
                    <button type="button" class="btn btn-white border fw-semibold rounded-pill px-4 tactile-btn" @click="staffModalOpen = false">Cancel</button>
                    <button type="submit" class="btn btn-dark fw-semibold rounded-pill px-4 shadow-sm tactile-btn"><i class="fa-solid fa-save me-2"></i> Save Details</button>
                </div>
            </form>
        </div>
    </div>
</template>

{{-- Pay Salary Modal (Teleported) --}}
<template x-teleport="body">
    <div class="custom-overlay-backdrop" x-show="salaryModalOpen" x-transition.opacity.duration.300ms @click="salaryModalOpen = false" x-cloak style="display: none;">
        <div class="custom-overlay-modal" :class="{ 'is-open': salaryModalOpen }" @click.stop x-show="salaryModalOpen" x-transition.opacity style="display: none;">
            
            <form id="salaryForm" method="POST">
                @csrf
                <div class="custom-overlay-header">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-money-bill-wave text-success me-2"></i>Record Salary Payment</h5>
                    <button type="button" class="btn-close shadow-none tactile-btn" @click="salaryModalOpen = false"></button>
                </div>
                
                <div class="custom-overlay-body">
                    <div class="card border border-success-subtle bg-success bg-opacity-10 rounded-4 mb-4">
                        <div class="card-body p-3 d-flex align-items-center gap-3">
                            <div class="tile-icon-wrapper bg-success text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;"><i class="fa-solid fa-user"></i></div>
                            <div>
                                <div class="text-success small fw-bold text-uppercase letter-spacing-1">Paying To</div>
                                <div class="fw-bold text-dark fs-5" id="sal_name"></div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark small text-uppercase letter-spacing-1">Salary Month <span class="text-danger">*</span></label>
                            <input type="month" name="salary_month" class="form-control bg-light border-0 shadow-none" value="{{ now()->format('Y-m') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark small text-uppercase letter-spacing-1">Paid On <span class="text-danger">*</span></label>
                            <input type="date" name="paid_on" class="form-control bg-light border-0 shadow-none" value="{{ now()->format('Y-m-d') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark small text-uppercase letter-spacing-1">Amount <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0 text-muted fw-bold">₹</span>
                                <input type="number" step="0.01" name="amount" class="form-control bg-light border-0 shadow-none fw-bold text-success fs-5" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark small text-uppercase letter-spacing-1">Mode</label>
                            <select name="mode" class="form-select bg-light border-0 shadow-none">
                                <option value="cash">Cash</option>
                                <option value="upi">UPI</option>
                                <option value="bank">Bank Transfer</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold text-dark small text-uppercase letter-spacing-1">Notes / Ref</label>
                            <input type="text" name="notes" class="form-control bg-light border-0 shadow-none" placeholder="Optional notes...">
                        </div>
                    </div>
                </div>
                
                <div class="custom-overlay-footer bg-light">
                    <button type="button" class="btn btn-white border fw-semibold rounded-pill px-4 tactile-btn" @click="salaryModalOpen = false">Cancel</button>
                    <button type="submit" class="btn btn-success fw-semibold rounded-pill px-4 shadow-sm tactile-btn"><i class="fa-solid fa-check me-2"></i> Record Payment</button>
                </div>
            </form>
        </div>
    </div>
</template>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('staffBoard', () => ({
            tab: '{{ request('tab', 'directory') }}',
            search: '',
            staffModalOpen: false,
            salaryModalOpen: false,
            
            switchTab(newTab) {
                this.tab = '';
                setTimeout(() => { 
                    this.tab = newTab; 
                    const url = new URL(window.location);
                    url.searchParams.set('tab', newTab);
                    window.history.replaceState({}, '', url);
                }, 300);
            }
        }))
    })

    const staffForm = document.getElementById('staffForm');
    const storeUrl = "{{ route('admin.staff.store') }}";
    
    function resetStaff() {
        staffForm.action = storeUrl; 
        document.getElementById('staffMethod').value = 'POST';
        document.getElementById('staffTitle').textContent = 'Add Staff';
        ['name','designation','mobile','salary','join','address'].forEach(f => document.getElementById('st_'+f).value = '');
        document.getElementById('st_active').checked = true;
    }
    
    function paySalary(id, name) {
        document.getElementById('salaryForm').action = "{{ url('admin/staff') }}/" + id + "/salary";
        document.getElementById('sal_name').textContent = name;
    }
</script>
@endpush
