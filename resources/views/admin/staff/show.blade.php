@extends('layouts.app')
@section('title', $staff->name)

@section('content')
<style>
    .glass-hero {
        background: var(--he-gradient-mesh);
        position: relative;
        overflow: hidden;
    }
    .glass-hero::after {
        content: '';
        position: absolute;
        inset: 0;
        background: radial-gradient(circle at top right, rgba(147, 51, 234, 0.4), transparent 60%);
        z-index: 1;
    }
    .glass-hero-content {
        position: relative;
        z-index: 2;
    }
    
    .glass-tile {
        background: #ffffff;
        border: 1px solid rgba(0,0,0,0.02);
        transition: transform 0.4s cubic-bezier(0.25, 1, 0.5, 1), box-shadow 0.4s cubic-bezier(0.25, 1, 0.5, 1);
    }
    .glass-tile:hover {
        transform: translateY(-4px);
        box-shadow: 0 15px 35px rgba(0,0,0,0.05) !important;
    }

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
    
    .tactile-btn {
        transition: transform 0.2s cubic-bezier(0.25, 1, 0.5, 1), background-color 0.2s ease, box-shadow 0.2s ease;
    }
    .tactile-btn:active {
        transform: scale(0.97);
    }

    /* Staggered Fade Up Animations */
    .stagger-1 { animation: fadeUp 0.6s cubic-bezier(0.25, 1, 0.5, 1) 0.1s both; }
    .stagger-2 { animation: fadeUp 0.6s cubic-bezier(0.25, 1, 0.5, 1) 0.15s both; }
    
    @keyframes fadeUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Timeline Feed */
    .timeline {
        position: relative;
        padding-left: 2.5rem;
    }
    .timeline::before {
        content: '';
        position: absolute;
        left: 0.95rem;
        top: 0;
        bottom: 0;
        width: 2px;
        background: rgba(0,0,0,0.05);
    }
    .timeline-item {
        position: relative;
        margin-bottom: 2rem;
    }
    .timeline-item:last-child { margin-bottom: 0; }
    .timeline-icon {
        position: absolute;
        left: -2.5rem;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 2;
        background: #fff;
        border: 4px solid #fff;
        box-shadow: 0 4px 10px rgba(0,0,0,0.05);
    }
</style>

<div x-data="staffProfile()" class="page-enter">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4 stagger-1">
        <div>
            <h1 class="h3 fw-bold mb-0 text-dark tracking-tight">Staff Profile</h1>
            <p class="text-secondary mb-0">Detailed view of personnel records and payroll.</p>
        </div>
        <a href="{{ route('admin.staff.index') }}" class="btn btn-white border shadow-sm rounded-pill px-4 fw-semibold tactile-btn"><i class="fa-solid fa-arrow-left me-2"></i> Back to Directory</a>
    </div>

    <div class="row g-4">
        <!-- Left Column: Profile Hero -->
        <div class="col-lg-4 stagger-2">
            <div class="card border-0 rounded-4 shadow-sm overflow-hidden mb-4 glass-hero">
                <div class="card-body p-4 pt-5 text-center glass-hero-content">
                    <div class="avatar bg-white text-primary rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3 position-relative" style="width: 100px; height: 100px; font-size: 2.5rem; font-weight: 800; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
                        {{ substr($staff->name, 0, 1) }}
                    </div>
                    <h3 class="fw-bold text-white mb-1 tracking-tight">{{ $staff->name }}</h3>
                    <div class="text-info fw-bold text-uppercase letter-spacing-1 small mb-4">{{ $staff->designation ?? 'Staff Member' }}</div>
                    
                    <div class="d-flex justify-content-center mb-4">
                        @if($staff->is_active)
                            <span class="badge bg-white bg-opacity-25 text-white rounded-pill px-3 py-2" style="backdrop-filter: blur(10px);"><i class="fa-solid fa-circle-check text-success me-1"></i> Active Employee</span>
                        @else
                            <span class="badge bg-black bg-opacity-25 text-white rounded-pill px-3 py-2" style="backdrop-filter: blur(10px);">Inactive</span>
                        @endif
                    </div>

                    <button class="btn btn-light w-100 rounded-pill shadow-sm fw-bold tactile-btn mb-4 text-primary" @click="editModalOpen = true">
                        <i class="fa-solid fa-user-pen me-2"></i> Edit Profile
                    </button>
                </div>
            </div>
            
            <div class="card glass-tile rounded-4 mb-4">
                <div class="card-body p-4">
                    <div class="mb-4">
                        <div class="text-secondary fw-bold text-uppercase letter-spacing-1 small mb-1" style="font-size: 0.7rem;">Mobile Number</div>
                        <div class="fw-bold text-dark fs-5">
                            @if($staff->mobile)
                                <x-mobile-link :mobile="$staff->mobile" />
                            @else
                                <span class="text-muted fw-normal">—</span>
                            @endif
                        </div>
                    </div>
                    <div class="mb-4">
                        <div class="text-secondary fw-bold text-uppercase letter-spacing-1 small mb-1" style="font-size: 0.7rem;">Monthly Salary</div>
                        <div class="fw-bold text-success fs-5" style="font-feature-settings: 'tnum';">{{ hostelease_money($staff->monthly_salary) }}</div>
                    </div>
                    <div class="mb-4">
                        <div class="text-secondary fw-bold text-uppercase letter-spacing-1 small mb-1" style="font-size: 0.7rem;">Join Date</div>
                        <div class="fw-bold text-dark">{{ $staff->join_date ? $staff->join_date->format('d M Y') : 'Not specified' }}</div>
                    </div>
                    <div>
                        <div class="text-secondary fw-bold text-uppercase letter-spacing-1 small mb-1" style="font-size: 0.7rem;">Address</div>
                        <div class="text-dark fw-medium">{{ $staff->address ?: 'No address provided' }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Analytics & Timeline -->
        <div class="col-lg-8 stagger-2">
            <!-- Attendance Analytics (Glass Tiles) -->
            <div class="d-flex align-items-center mb-3">
                <div class="tile-icon-wrapper bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                    <i class="fa-solid fa-chart-pie"></i>
                </div>
                <h5 class="fw-bold text-dark mb-0 tracking-tight">Attendance (This Month)</h5>
            </div>
            
            <div class="row g-3 mb-5">
                <div class="col-6 col-md-3">
                    <div class="card glass-tile border-success-subtle rounded-4 h-100">
                        <div class="card-body p-3 text-center">
                            <div class="display-5 fw-bold text-success mb-1 tracking-tight">{{ $counts['present'] }}</div>
                            <div class="text-success fw-bold text-uppercase letter-spacing-1 small">Present</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card glass-tile border-danger-subtle rounded-4 h-100">
                        <div class="card-body p-3 text-center">
                            <div class="display-5 fw-bold text-danger mb-1 tracking-tight">{{ $counts['absent'] }}</div>
                            <div class="text-danger fw-bold text-uppercase letter-spacing-1 small">Absent</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card glass-tile border-warning-subtle rounded-4 h-100">
                        <div class="card-body p-3 text-center">
                            <div class="display-5 fw-bold text-warning mb-1 tracking-tight">{{ $counts['half_day'] }}</div>
                            <div class="text-warning fw-bold text-uppercase letter-spacing-1 small">Half Day</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card glass-tile border-secondary-subtle rounded-4 h-100">
                        <div class="card-body p-3 text-center">
                            <div class="display-5 fw-bold text-secondary mb-1 tracking-tight">{{ $counts['leave'] }}</div>
                            <div class="text-secondary fw-bold text-uppercase letter-spacing-1 small">Leave</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Salary History (Timeline Feed) -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="d-flex align-items-center">
                    <div class="tile-icon-wrapper bg-success bg-opacity-10 text-success rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                        <i class="fa-solid fa-money-check-dollar"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-0 tracking-tight">Salary History</h5>
                </div>
                <button class="btn btn-success rounded-pill fw-semibold shadow-sm px-4 tactile-btn" @click="salaryModalOpen = true">
                    <i class="fa-solid fa-plus me-2"></i> Record Pay
                </button>
            </div>
            
            <div class="card glass-tile rounded-4 p-4 p-md-5">
                @if($payments->isEmpty())
                <div class="text-center py-4">
                    <div class="empty-state">
                        <i class="fa-solid fa-file-invoice-dollar text-secondary fs-1 mb-3 opacity-25" style="font-size: 3rem !important;"></i>
                        <h5 class="fw-bold text-dark tracking-tight">No salary records</h5>
                        <div class="text-secondary">No salary payments have been recorded yet.</div>
                    </div>
                </div>
                @else
                <div class="timeline">
                    @foreach($payments as $index => $p)
                    <div class="timeline-item d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3" style="animation: fadeUp 0.6s cubic-bezier(0.25, 1, 0.5, 1) {{ min($index * 0.1, 0.5) }}s both;">
                        <div class="timeline-icon bg-success text-white">
                            @if($p->mode === 'cash')
                                <i class="fa-solid fa-money-bill-wave small"></i>
                            @elseif($p->mode === 'upi')
                                <i class="fa-solid fa-mobile-screen small"></i>
                            @else
                                <i class="fa-solid fa-building-columns small"></i>
                            @endif
                        </div>
                        
                        <div class="flex-grow-1">
                            <div class="fw-bold text-dark fs-5 mb-1">{{ $p->salary_month->format('F Y') }}</div>
                            <div class="text-secondary small fw-bold text-uppercase letter-spacing-1">Paid on {{ $p->paid_on->format('d M Y') }} &bull; {{ ucfirst($p->mode) }}</div>
                        </div>
                        
                        <div class="text-md-end d-flex align-items-center gap-3">
                            <div class="text-success fw-bold h4 mb-0 tracking-tight" style="font-feature-settings: 'tnum';">
                                {{ hostelease_money($p->amount) }}
                            </div>
                            <form action="{{ route('admin.staff.salary.destroy', [$staff, $p]) }}" method="POST" onsubmit="return confirm('Delete this salary entry?');">
                                @csrf @method('DELETE')
                                <button class="btn btn-white border rounded-circle text-danger shadow-sm tactile-btn d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;" title="Delete Record">
                                    <i class="fa-solid fa-trash small"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
        </div>
    </div>

{{-- Edit Staff Modal (Teleported) --}}
<template x-teleport="body">
    <div class="custom-overlay-backdrop" x-show="editModalOpen" x-transition.opacity.duration.300ms @click="editModalOpen = false" x-cloak style="display: none;">
        <div class="custom-overlay-modal" :class="{ 'is-open': editModalOpen }" @click.stop x-show="editModalOpen" x-transition.opacity style="display: none;">
            
            <form method="POST" action="{{ route('admin.staff.update', $staff) }}">
                @csrf
                @method('PUT')
                
                <div class="custom-overlay-header">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-user-pen text-primary me-2"></i>Edit Staff Profile</h5>
                    <button type="button" class="btn-close shadow-none tactile-btn" @click="editModalOpen = false"></button>
                </div>
                
                <div class="custom-overlay-body">
                    <div class="row g-4">
                        <div class="col-md-12">
                            <label class="form-label fw-bold text-dark small text-uppercase letter-spacing-1">Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control form-control-lg bg-light border-0 shadow-none" required value="{{ $staff->name }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark small text-uppercase letter-spacing-1">Designation</label>
                            <input type="text" name="designation" class="form-control bg-light border-0 shadow-none" value="{{ $staff->designation }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark small text-uppercase letter-spacing-1">Mobile</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0 text-muted fw-bold">+91</span>
                                <input type="tel" name="mobile" maxlength="10" class="form-control bg-light border-0 shadow-none" value="{{ str_replace('+91', '', $staff->mobile) }}">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark small text-uppercase letter-spacing-1">Monthly Salary <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0 text-muted fw-bold">₹</span>
                                <input type="number" step="0.01" name="monthly_salary" class="form-control bg-light border-0 shadow-none" required value="{{ $staff->monthly_salary }}">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark small text-uppercase letter-spacing-1">Join Date</label>
                            <input type="date" name="join_date" class="form-control bg-light border-0 shadow-none" value="{{ optional($staff->join_date)->format('Y-m-d') }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold text-dark small text-uppercase letter-spacing-1">Address</label>
                            <input type="text" name="address" class="form-control bg-light border-0 shadow-none" value="{{ $staff->address }}">
                        </div>
                        <div class="col-12">
                            <div class="card bg-primary bg-opacity-10 border-0 rounded-4">
                                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                                    <div>
                                        <div class="fw-bold text-primary mb-1">Active Status</div>
                                        <div class="small text-primary opacity-75">Enable if this staff member is currently working.</div>
                                    </div>
                                    <div class="form-check form-switch fs-4 mb-0">
                                        <input class="form-check-input shadow-none" type="checkbox" role="switch" name="is_active" value="1" @checked($staff->is_active)>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="custom-overlay-footer bg-light">
                    <button type="button" class="btn btn-white border fw-semibold rounded-pill px-4 tactile-btn" @click="editModalOpen = false">Cancel</button>
                    <button type="submit" class="btn btn-dark fw-semibold rounded-pill px-4 shadow-sm tactile-btn"><i class="fa-solid fa-save me-2"></i> Update Profile</button>
                </div>
            </form>
        </div>
    </div>
</template>

{{-- Pay Salary Modal (Teleported) --}}
<template x-teleport="body">
    <div class="custom-overlay-backdrop" x-show="salaryModalOpen" x-transition.opacity.duration.300ms @click="salaryModalOpen = false" x-cloak style="display: none;">
        <div class="custom-overlay-modal" :class="{ 'is-open': salaryModalOpen }" @click.stop x-show="salaryModalOpen" x-transition.opacity style="display: none;">
            
            <form method="POST" action="{{ route('admin.staff.salary', $staff) }}">
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
                                <div class="fw-bold text-dark fs-5">{{ $staff->name }}</div>
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
                                <input type="number" step="0.01" name="amount" class="form-control bg-light border-0 shadow-none fw-bold text-success fs-5" required value="{{ $staff->monthly_salary }}">
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
        Alpine.data('staffProfile', () => ({
            editModalOpen: false,
            salaryModalOpen: false,
        }))
    })
</script>
@endpush
