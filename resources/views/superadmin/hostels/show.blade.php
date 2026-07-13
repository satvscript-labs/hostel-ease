@extends('layouts.app')
@section('title', $hostel->name . ' - Profile')

@section('content')
<!-- Header Area -->
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div class="d-flex align-items-center gap-3">
        <a href="{{ route('superadmin.hostels.index') }}" class="btn btn-light rounded-circle shadow-sm d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
            <i class="fa-solid fa-arrow-left text-muted"></i>
        </a>
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h1 class="h3 fw-bold mb-0 text-dark tracking-tight">{{ $hostel->name }}</h1>
                @if($hostel->status === 'active')
                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3"><i class="fa-solid fa-circle me-1" style="font-size: 0.5rem; vertical-align: middle;"></i> Active</span>
                @elseif($hostel->status === 'expired')
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-3">Expired</span>
                @else
                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-3">{{ ucfirst($hostel->status) }}</span>
                @endif
            </div>
            <p class="text-muted mb-0 small"><i class="fa-solid fa-location-dot me-1"></i> {{ $hostel->city }}{{ $hostel->state ? ', '.$hostel->state : '' }}</p>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('superadmin.hostels.edit', $hostel) }}" class="btn btn-primary shadow-sm rounded-pill px-4">
            <i class="fa-solid fa-pen me-2"></i> Edit Profile
        </a>
    </div>
</div>

@include('superadmin.partials.credentials')

<!-- Key Stats Row -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card stat-card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4 d-flex align-items-center gap-3">
                <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; font-size: 1.25rem;">
                    <i class="fa-solid fa-users"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold text-uppercase tracking-wider mb-1">Students</div>
                    <div class="fs-4 fw-bold text-dark lh-1">{{ $hostel->students_count }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4 d-flex align-items-center gap-3">
                <div class="rounded-circle bg-info-subtle text-info d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; font-size: 1.25rem;">
                    <i class="fa-solid fa-door-open"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold text-uppercase tracking-wider mb-1">Rooms</div>
                    <div class="fs-4 fw-bold text-dark lh-1">{{ $hostel->rooms_count }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4 d-flex align-items-center gap-3">
                <div class="rounded-circle bg-warning-subtle text-warning d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; font-size: 1.25rem;">
                    <i class="fa-solid fa-bed"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold text-uppercase tracking-wider mb-1">Beds</div>
                    <div class="fs-4 fw-bold text-dark lh-1">{{ $hostel->beds_count }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4 d-flex align-items-center gap-3">
                @php $days = $hostel->daysUntilExpiry(); @endphp
                <div class="rounded-circle bg-{{ $days && $days <= 30 ? 'danger' : 'success' }}-subtle text-{{ $days && $days <= 30 ? 'danger' : 'success' }} d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; font-size: 1.25rem;">
                    <i class="fa-solid fa-calendar-check"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold text-uppercase tracking-wider mb-1">Expiry</div>
                    <div class="fs-5 fw-bold text-dark lh-1">{{ optional($hostel->subscription_end)->format('d M Y') ?? '—' }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Left Column: Details & Admins -->
    <div class="col-lg-4">
        <div class="card stat-card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4">
                <h2 class="h5 fw-bold mb-4 text-dark d-flex align-items-center gap-2">
                    <i class="fa-solid fa-building text-primary"></i> Tenant Details
                </h2>
                
                <div class="d-flex flex-column gap-3">
                    <div>
                        <div class="text-muted small fw-semibold mb-1">Owner Name</div>
                        <div class="fw-medium text-dark">{{ $hostel->owner_name }}</div>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold mb-1">Contact Mobile</div>
                        <div class="fw-medium text-dark"><x-mobile-link :mobile="$hostel->mobile" /></div>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold mb-1">Email Address</div>
                        <div class="fw-medium text-dark">{{ $hostel->email ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold mb-1">GST Number</div>
                        <div class="fw-medium text-dark">{{ $hostel->gst_number ?? '—' }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div x-data="adminManager()" class="card stat-card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="h5 fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                        <i class="fa-solid fa-user-shield text-primary"></i> Admins
                    </h2>
                    <button class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm fw-medium" @click="openModal()">
                        <i class="fa-solid fa-plus me-1"></i> Add Admin
                    </button>
                </div>
                
                <div class="table-responsive rounded-3 border">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.5px;">
                            <tr>
                                <th class="py-3 px-4 text-muted fw-semibold border-0">Admin</th>
                                <th class="py-3 px-4 text-muted fw-semibold border-0 text-center">Status</th>
                                <th class="py-3 px-4 border-0"></th>
                            </tr>
                        </thead>
                        <tbody class="border-top-0">
                            @forelse($hostel->admins as $a)
                                @php
                                    $initials = collect(explode(' ', $a->name))->map(fn($w) => substr($w, 0, 1))->take(2)->join('');
                                    $colors = ['primary', 'success', 'warning', 'info', 'danger'];
                                    $avatarColor = $colors[$a->id % 5];
                                @endphp
                                <tr>
                                    <td class="px-4 py-3 text-nowrap">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="rounded-circle bg-{{ $avatarColor }}-subtle text-{{ $avatarColor }} d-flex align-items-center justify-content-center fw-bold flex-shrink-0" style="width: 36px; height: 36px; font-size: 0.8rem;">
                                                {{ strtoupper($initials) }}
                                            </div>
                                            <div>
                                                <div class="fw-semibold text-dark lh-1 mb-1">{{ $a->name }}</div>
                                                <div class="small text-muted lh-1 mb-1"><x-mobile-link :mobile="$a->mobile" /></div>
                                                @if($a->hostels->where('id', '!=', $hostel->id)->isNotEmpty())
                                                    <div class="d-flex flex-wrap gap-1 mt-1">
                                                        @foreach($a->hostels->where('id', '!=', $hostel->id) as $assignedBranch)
                                                            <span class="badge bg-light border text-secondary rounded-pill" style="font-size: 0.65rem;">{{ $assignedBranch->name }}</span>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-center text-nowrap">
                                        @if($a->is_active)
                                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill"><i class="fa-solid fa-circle me-1" style="font-size: 0.4rem; vertical-align: middle;"></i> Active</span>
                                        @else
                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill">Disabled</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-end text-nowrap">
                                        <form action="{{ route('superadmin.admins.toggle', $a) }}" method="POST" class="d-inline">
                                            @csrf @method('PATCH')
                                            <button class="btn btn-sm btn-light rounded-circle shadow-sm" style="width: 32px; height: 32px;" title="{{ $a->is_active ? 'Disable Admin' : 'Enable Admin' }}">
                                                <i class="fa-solid {{ $a->is_active ? 'fa-ban text-warning' : 'fa-check text-success' }}"></i>
                                            </button>
                                        </form>
                                        <form action="{{ route('superadmin.admins.reset', $a) }}" method="POST" class="d-inline mx-1" data-confirm="Generate a new random password for {{ $a->name }}?">
                                            @csrf @method('PATCH')
                                            <button class="btn btn-sm btn-light rounded-circle shadow-sm" style="width: 32px; height: 32px;" title="Reset Password">
                                                <i class="fa-solid fa-key text-muted"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-4 small">
                                        <i class="fa-solid fa-users fs-4 mb-2 text-light"></i><br>
                                        No admins assigned.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Subscriptions & Billing -->
    <div class="col-lg-8">
        <div class="card stat-card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="h5 fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                        <i class="fa-solid fa-receipt text-primary"></i> Billing & Subscriptions
                    </h2>
                    <a href="{{ $account ? route('superadmin.accounts.show', $account) : route('superadmin.subscriptions.index') }}" class="btn btn-primary rounded-pill px-4 shadow-sm">
                        <i class="fa-solid fa-rotate me-2"></i> Add / Renew
                    </a>
                </div>
                
                <div class="table-responsive rounded-3 border">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.5px;">
                            <tr>
                                <th class="py-3 px-4 text-muted fw-semibold border-0">Period</th>
                                <th class="py-3 px-4 text-muted fw-semibold border-0 text-end">Amount</th>
                                <th class="py-3 px-4 text-muted fw-semibold border-0">Status</th>
                            </tr>
                        </thead>
                        <tbody class="border-top-0">
                        @forelse($hostel->subscriptions as $s)
                            <tr>
                                <td class="px-4 py-3">
                                    <div class="fw-semibold text-dark">{{ $s->start_date->format('d M Y') }} — {{ $s->end_date->format('d M Y') }}</div>
                                    <div class="small text-muted">{{ $s->plan ? ucfirst($s->plan) . ' Plan' : 'Custom Period' }}</div>
                                </td>
                                <td class="px-4 py-3 text-end">
                                    <div class="fw-bold text-dark fs-6">{{ hostelease_money($s->amount) }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    @if($s->payment_status === 'paid')
                                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3">Paid</span>
                                    @elseif($s->payment_status === 'pending')
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-3">Pending</span>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-3">{{ ucfirst($s->payment_status) }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted py-5">No billing history found for this tenant.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

    <!-- Alpine Premium Modal -->
    <template x-teleport="body">
        <div x-show="isModalOpen" class="custom-overlay-backdrop" style="display: none;" x-transition.opacity.duration.300ms x-cloak>
            <div x-show="isModalOpen" @click.away="closeModal()" class="custom-overlay-modal is-open" x-transition.scale.95.duration.300ms>
                <form action="{{ route('superadmin.admins.store') }}" method="POST" class="d-flex flex-column h-100 mb-0">
                    @csrf
                    <input type="hidden" name="hostel_id" value="{{ $hostel->id }}">
                    
                    <div class="custom-overlay-header">
                        <h5 class="fw-bold mb-0 text-dark">Add Admin for {{ $hostel->name }}</h5>
                        <button type="button" @click="closeModal()" class="btn-close shadow-none"></button>
                    </div>

                    <div class="custom-overlay-body">
                        <p class="text-muted small mb-4">This will create a new administrator account. A password will be auto-generated.</p>
                        
                        <div class="row g-4">
                            <div class="col-12">
                                <label class="form-label fw-semibold text-secondary small text-uppercase">Full Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control form-control-lg bg-light border-0" required placeholder="e.g. Ramesh Patel">
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label fw-semibold text-secondary small text-uppercase">Mobile Number <span class="text-danger">*</span></label>
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text bg-light border-0 text-muted">+91</span>
                                    <input type="tel" name="mobile" maxlength="10" inputmode="numeric" class="form-control bg-light border-0" required placeholder="9876543210">
                                </div>
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label fw-semibold text-secondary small text-uppercase">Email Address (Optional)</label>
                                <input type="email" name="email" class="form-control form-control-lg bg-light border-0" placeholder="admin@example.com">
                            </div>
                            
                            <!-- Premium Multi-Branch Checkbox Tiles -->
                            <div class="col-12">
                                <label class="form-label fw-bold small text-uppercase letter-spacing-1 mb-3">Assigned Branches <span class="text-muted text-lowercase fw-normal">(Optional)</span></label>
                                <div class="row g-3">
                                    @forelse($branches as $b)
                                        <div class="col-sm-6">
                                            <label class="w-100 cursor-pointer h-100 m-0">
                                                <input type="checkbox" name="branches[]" value="{{ $b->id }}" class="d-none peer-checkbox">
                                                <div class="card bg-light border-0 rounded-4 transition-all h-100 checkbox-tile">
                                                    <div class="card-body p-3 d-flex align-items-center gap-3">
                                                        <div class="check-circle rounded-circle border d-flex align-items-center justify-content-center bg-white text-white flex-shrink-0" style="width: 24px; height: 24px; transition: all 0.2s;">
                                                            <i class="fa-solid fa-check" style="font-size: 0.7rem;"></i>
                                                        </div>
                                                        <span class="fw-semibold text-dark lh-sm" style="font-size: 0.9rem;">{{ $b->name }}</span>
                                                    </div>
                                                </div>
                                            </label>
                                        </div>
                                    @empty
                                        <div class="col-12 text-muted small">No active branches found.</div>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="custom-overlay-footer">
                        <button type="button" @click="closeModal()" class="btn btn-light rounded-pill px-4 fw-medium">Cancel</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4 shadow-sm fw-semibold">
                            <i class="fa-solid fa-check me-2"></i> Create Admin
                        </button>
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
        Alpine.data('adminManager', () => ({
            isModalOpen: false,
            openModal() {
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
<style>
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
