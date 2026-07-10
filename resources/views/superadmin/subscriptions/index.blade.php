@extends('layouts.app')
@section('title', 'Subscriptions & Billing')

@push('styles')
<style>
    /* Premium Dashboard Aesthetic */
    .stat-card {
        background: rgba(255,255,255,0.85);
        backdrop-filter: blur(20px);
        border: 1px solid rgba(0,0,0,0.05);
        transition: all 0.3s cubic-bezier(0.25, 1, 0.5, 1);
    }
    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 30px rgba(0,0,0,0.04) !important;
    }
    .stat-value {
        font-size: 1.75rem;
        font-weight: 800;
        letter-spacing: -0.5px;
    }
    .stat-label {
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #64748b;
    }
    
    .hover-bg-light:hover {
        background-color: #f8fafc !important;
    }
    .cursor-pointer {
        cursor: pointer;
    }

    /* Custom Overlay Modal */
    .custom-overlay-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.6);
        backdrop-filter: blur(8px);
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }
    .custom-overlay-modal {
        width: 100%;
        background: #fff;
        border-radius: 1.25rem;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        display: flex;
        flex-direction: column;
        max-height: 90vh;
        transform: scale(0.95);
        opacity: 0;
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        overflow: hidden;
    }
    .custom-overlay-modal.is-open {
        transform: scale(1);
        opacity: 1;
    }
    .custom-overlay-header {
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #fff;
        border-top-left-radius: 1.25rem;
        border-top-right-radius: 1.25rem;
    }
    .custom-overlay-body {
        padding: 1.5rem;
        overflow-y: auto;
        flex-grow: 1;
        background: #fafafa;
    }
    .custom-overlay-footer {
        padding: 1.25rem 1.5rem;
        border-top: 1px solid rgba(0, 0, 0, 0.05);
        background: #fff;
        display: flex;
        gap: 1rem;
        justify-content: flex-end;
        border-bottom-left-radius: 1.25rem;
        border-bottom-right-radius: 1.25rem;
    }
</style>
@endpush

@section('content')
<div x-data="superadminSubscriptions()">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark tracking-tight">Subscriptions &amp; Billing</h1>
            <p class="text-muted mb-0 small">Manage SaaS renewals, track revenue, and issue payment bypasses.</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-primary shadow-sm rounded-pill px-4" @click="openCreateModal()">
                <i class="fa-solid fa-plus me-2"></i> Record Payment
            </button>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="card stat-card shadow-sm rounded-4"><div class="card-body py-4"><div class="stat-value text-success mb-1">{{ hostelease_money($summary['total']) }}</div><div class="stat-label">Total Collected</div></div></div></div>
        <div class="col-md-3"><div class="card stat-card shadow-sm rounded-4"><div class="card-body py-4"><div class="stat-value text-warning mb-1">{{ hostelease_money($summary['pending']) }}</div><div class="stat-label">Pending Payments</div></div></div></div>
        <div class="col-md-3"><div class="card stat-card shadow-sm rounded-4"><div class="card-body py-4"><div class="stat-value text-primary mb-1">{{ $summary['active_branches'] }}</div><div class="stat-label">Active Branches</div></div></div></div>
        <div class="col-md-3"><div class="card stat-card shadow-sm rounded-4"><div class="card-body py-4"><div class="stat-value text-danger mb-1">{{ $summary['expired_branches'] }}</div><div class="stat-label">Expired Branches</div></div></div></div>
    </div>

    {{-- Per-branch billing --}}
    <div class="card stat-card border-0 shadow-sm rounded-4 mb-5 overflow-hidden"><div class="card-body p-0">
        <div class="p-4 border-bottom bg-light bg-opacity-50">
            <h5 class="fw-bold mb-1 text-dark">Hostel Branches</h5>
            <div class="text-muted small">Subscriptions are now tracked and renewed individually per branch.</div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.5px;"><tr>
                    <th class="py-3 px-4 border-0">Branch</th>
                    <th class="py-3 px-4 border-0">Owner</th>
                    <th class="py-3 px-4 border-0">Contact</th>
                    <th class="py-3 px-4 border-0 text-center">Status</th>
                    <th class="py-3 px-4 border-0">Valid until</th>
                    <th class="py-3 px-4 border-0 text-end"></th>
                </tr></thead>
                <tbody class="border-top-0">
                @forelse($branches as $b)
                    <tr>
                        <td class="px-4 py-3 fw-bold text-dark fs-6">{{ $b['branch']->name }}</td>
                        <td class="px-4 py-3 fw-medium text-secondary">{{ $b['branch']->owner_name }}</td>
                        <td class="px-4 py-3 text-muted small"><i class="fa-solid fa-mobile-screen text-primary me-1"></i> {{ $b['branch']->mobile }}</td>
                        <td class="px-4 py-3 text-center">
                            @if($b['active'])
                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-2"><i class="fa-solid fa-circle me-1" style="font-size: 0.4rem; vertical-align: middle;"></i> Active</span>
                            @else
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-3 py-2">Expired</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 fw-medium">{{ $b['end'] ? $b['end']->format('d M Y') : '—' }}</td>
                        <td class="px-4 py-3 text-end">
                            <button class="btn btn-sm btn-light text-primary rounded-pill fw-semibold px-3 shadow-sm"
                                    @click="openCreateModal({{ $b['branch']->id }})">
                                Renew
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-5">No hostel branches yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div></div>

    {{-- Payment history --}}
    <div class="card stat-card border-0 shadow-sm rounded-4 overflow-hidden mb-4"><div class="card-body p-0">
        <div class="p-4 border-bottom bg-light bg-opacity-50">
            <h5 class="fw-bold mb-0 text-dark">Payment History</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.5px;"><tr>
                    <th class="py-3 px-4 border-0">Amount</th>
                    <th class="py-3 px-4 border-0">Status</th>
                    <th class="py-3 px-4 border-0">Branch / Plan</th>
                    <th class="py-3 px-4 border-0">Period</th>
                    <th class="py-3 px-4 border-0">Method & Txn</th>
                    <th class="py-3 px-4 border-0 text-end"></th>
                </tr></thead>
                <tbody class="border-top-0">
                @foreach($subscriptions as $s)
                    <tr>
                        <td class="px-4 py-3">
                            <div class="fw-bold fs-5 {{ $s->amount == 0 ? 'text-secondary' : 'text-dark' }}">{{ hostelease_money($s->amount) }}</div>
                            <div class="small text-muted">{{ $s->created_at?->format('d M Y') }}</div>
                        </td>
                        <td class="px-4 py-3">
                            @if($s->payment_status === 'paid')
                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1">Paid</span>
                            @elseif($s->payment_status === 'pending')
                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-3 py-1">Pending</span>
                            @else
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-3 py-1">{{ ucfirst($s->payment_status) }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="fw-bold text-dark">{{ $s->hostel->name ?? '—' }}</div>
                            <div class="small text-muted">{{ ucfirst($s->plan) }} Plan</div>
                        </td>
                        <td class="px-4 py-3 fw-medium">
                            {{ $s->start_date->format('d M Y') }} <i class="fa-solid fa-arrow-right mx-1 text-muted small"></i> {{ $s->end_date->format('d M Y') }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="fw-semibold text-dark">{{ $s->payment_method ? ucfirst($s->payment_method) : '—' }}</div>
                            <div class="small text-muted text-truncate" style="max-width: 150px;">{{ $s->transaction_number ?? '—' }}</div>
                        </td>
                        <td class="px-4 py-3 text-end text-nowrap">
                            @if($s->payment_status !== 'paid')
                                <form action="{{ route('superadmin.subscriptions.accept', $s) }}" method="POST" class="d-inline" data-confirm="Accept this payment and extend branch to {{ $s->end_date->format('d M Y') }}?">
                                    @csrf @method('PATCH')<button class="btn btn-sm btn-success rounded-circle shadow-sm" style="width: 32px; height: 32px;" title="Mark Paid"><i class="fa-solid fa-check"></i></button>
                                </form>
                            @endif
                            <button class="btn btn-sm btn-light rounded-circle shadow-sm mx-1" style="width: 32px; height: 32px;" title="Edit"
                                    @click="openEditModal({{ $s->id }})">
                                <i class="fa-solid fa-pen text-primary"></i>
                            </button>
                            <form action="{{ route('superadmin.subscriptions.destroy', $s) }}" method="POST" class="d-inline" data-confirm="Delete this subscription record?">
                                @csrf @method('DELETE')<button class="btn btn-sm btn-light rounded-circle shadow-sm" style="width: 32px; height: 32px;" title="Delete"><i class="fa-solid fa-trash text-danger"></i></button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if($subscriptions->hasPages())
            <div class="p-3 border-top">
                {{ $subscriptions->links() }}
            </div>
        @endif
    </div></div>

    {{-- Teleported Custom Modals --}}
    <template x-teleport="body">
        <div>
            <!-- Create/Renew Modal -->
            <div class="custom-overlay-backdrop" x-show="createModalOpen" x-transition.opacity @click.self="createModalOpen = false" x-cloak style="display: none;">
                <form method="POST" action="{{ route('superadmin.subscriptions.store') }}" class="custom-overlay-modal" style="max-width: 650px;" :class="{ 'is-open': createModalOpen }" x-show="createModalOpen" x-transition.opacity style="display: none;">
                    @csrf
                    
                    <div class="custom-overlay-header">
                        <h5 class="fw-bold mb-0 text-dark">Record Branch Subscription</h5>
                        <button type="button" class="btn-close" @click="createModalOpen = false"></button>
                    </div>

                    <div class="custom-overlay-body">
                        <!-- Branch Selection Dropdown -->
                        <div class="mb-4">
                            <label class="form-label fw-bold small text-muted">SELECT BRANCH</label>
                            <div x-data="{ dropOpen: false, search: '' }" class="position-relative">
                                <button type="button" @click="dropOpen = !dropOpen" class="form-control form-control-lg bg-white border shadow-sm d-flex justify-content-between align-items-center">
                                    <span x-text="c_branch ? c_branch.name + ' (' + c_branch.owner + ')' : 'Search or select branch…'" :class="c_branch ? 'text-dark fw-bold' : 'text-muted'"></span>
                                    <i class="fa-solid fa-chevron-down text-muted small"></i>
                                </button>
                                <div x-show="dropOpen" @click.outside.capture="dropOpen = false" x-transition class="position-absolute w-100 bg-white shadow-lg rounded-4 mt-2 overflow-hidden border" style="max-height: 250px; display: none; z-index: 1060;">
                                    <div class="p-2 border-bottom bg-light">
                                        <input x-model="search" type="text" class="form-control border-0 shadow-none bg-white rounded-pill px-3" placeholder="Search branches...">
                                    </div>
                                    <div class="overflow-y-auto" style="max-height: 190px;">
                                        <template x-for="b in branches.filter(x => x.name.toLowerCase().includes(search.toLowerCase()) || x.owner.toLowerCase().includes(search.toLowerCase()))" :key="b.id">
                                            <div @click="c_branch = b; dropOpen = false" class="px-3 py-2 cursor-pointer border-bottom hover-bg-light">
                                                <div class="fw-bold text-dark" x-text="b.name"></div>
                                                <div class="small text-muted" x-text="'Owner: ' + b.owner"></div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                                <input type="hidden" name="branch_id" :value="c_branch?.id" required>
                            </div>
                        </div>

                        <div class="row g-4">
                            <!-- Plan Dropdown -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">PLAN PERIOD</label>
                                <div x-data="{ dropOpen: false }" class="position-relative">
                                    <button type="button" @click="dropOpen = !dropOpen" class="form-control bg-white border shadow-sm d-flex justify-content-between align-items-center">
                                        <span x-text="c_period === 'yearly' ? 'Yearly' : (c_period === 'monthly' ? 'Monthly' : 'Trial (14 Days)')" class="text-dark fw-bold"></span>
                                        <i class="fa-solid fa-chevron-down text-muted small"></i>
                                    </button>
                                    <div x-show="dropOpen" @click.outside.capture="dropOpen = false" x-transition class="position-absolute w-100 bg-white shadow-lg rounded-3 mt-1 overflow-hidden border" style="display: none; z-index: 1050;">
                                        <div @click="c_period = 'yearly'; dropOpen = false" class="px-3 py-2 border-bottom hover-bg-light cursor-pointer">
                                            <div class="fw-bold" :class="c_period === 'yearly' ? 'text-primary' : 'text-dark'">Yearly</div>
                                        </div>
                                        <div @click="c_period = 'monthly'; dropOpen = false" class="px-3 py-2 border-bottom hover-bg-light cursor-pointer">
                                            <div class="fw-bold" :class="c_period === 'monthly' ? 'text-primary' : 'text-dark'">Monthly</div>
                                        </div>
                                        <div @click="c_period = 'trial'; dropOpen = false" class="px-3 py-2 hover-bg-light cursor-pointer">
                                            <div class="fw-bold" :class="c_period === 'trial' ? 'text-primary' : 'text-dark'">Trial (14 Days)</div>
                                        </div>
                                    </div>
                                    <input type="hidden" name="period" :value="c_period" required>
                                </div>
                            </div>

                            <!-- Amount -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted d-flex justify-content-between">
                                    AMOUNT (₹)
                                    <span class="text-primary fw-medium cursor-pointer" @click="recalcCreate()"><i class="fa-solid fa-rotate-left"></i> Auto-calc</span>
                                </label>
                                <input type="number" step="0.01" name="amount" x-model="c_amount" class="form-control fw-bold text-dark fs-5 bg-white border shadow-sm" required>
                            </div>

                            <!-- Status Dropdown -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">PAYMENT STATUS</label>
                                <div x-data="{ dropOpen: false }" class="position-relative">
                                    <button type="button" @click="dropOpen = !dropOpen" class="form-control bg-white border shadow-sm d-flex justify-content-between align-items-center">
                                        <span x-text="c_status === 'paid' ? 'Paid' : (c_status === 'pending' ? 'Pending' : 'Failed')" class="fw-bold" :class="c_status === 'paid' ? 'text-success' : (c_status === 'pending' ? 'text-warning' : 'text-danger')"></span>
                                        <i class="fa-solid fa-chevron-down text-muted small"></i>
                                    </button>
                                    <div x-show="dropOpen" @click.outside.capture="dropOpen = false" x-transition class="position-absolute w-100 bg-white shadow-lg rounded-3 mb-1 overflow-hidden border" style="display: none; z-index: 1050; bottom: 100%; top: auto;">
                                        <div @click="c_status = 'paid'; dropOpen = false" class="px-3 py-2 border-bottom hover-bg-light cursor-pointer text-success fw-bold">Paid</div>
                                        <div @click="c_status = 'pending'; dropOpen = false" class="px-3 py-2 border-bottom hover-bg-light cursor-pointer text-warning fw-bold">Pending</div>
                                        <div @click="c_status = 'failed'; dropOpen = false" class="px-3 py-2 hover-bg-light cursor-pointer text-danger fw-bold">Failed</div>
                                    </div>
                                    <input type="hidden" name="payment_status" :value="c_status" required>
                                </div>
                            </div>

                            <!-- Method Dropdown -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">PAYMENT METHOD</label>
                                <div x-data="{ dropOpen: false }" class="position-relative">
                                    <button type="button" @click="dropOpen = !dropOpen" class="form-control bg-white border shadow-sm d-flex justify-content-between align-items-center">
                                        <span x-text="c_method ? (c_method === 'online' ? 'Online' : (c_method === 'comp' ? 'Comp / Free' : paymentModes[c_method] || c_method)) : '— Select Method —'" :class="c_method ? 'fw-bold text-dark' : 'text-muted'"></span>
                                        <i class="fa-solid fa-chevron-down text-muted small"></i>
                                    </button>
                                    <div x-show="dropOpen" @click.outside.capture="dropOpen = false" x-transition class="position-absolute w-100 bg-white shadow-lg rounded-3 mb-1 overflow-hidden border" style="display: none; z-index: 1050; max-height: 200px; overflow-y: auto; bottom: 100%; top: auto;">
                                        <div @click="c_method = ''; dropOpen = false" class="px-3 py-2 border-bottom hover-bg-light cursor-pointer text-muted">— Select Method —</div>
                                        <template x-for="(label, key) in paymentModes" :key="key">
                                            <div @click="c_method = key; dropOpen = false" class="px-3 py-2 border-bottom hover-bg-light cursor-pointer fw-medium" x-text="label"></div>
                                        </template>
                                        <div @click="c_method = 'online'; dropOpen = false" class="px-3 py-2 border-bottom hover-bg-light cursor-pointer fw-medium">Online</div>
                                        <div @click="c_method = 'comp'; dropOpen = false" class="px-3 py-2 hover-bg-light cursor-pointer fw-bold text-primary">Comp / Bypassed (Free)</div>
                                    </div>
                                    <input type="hidden" name="payment_method" :value="c_method">
                                </div>
                            </div>

                            <!-- Remarks / Txn -->
                            <div class="col-12 mt-2" x-data="{ showAdvanced: false }">
                                <button type="button" @click="showAdvanced = !showAdvanced" class="btn btn-link text-muted fw-bold text-decoration-none p-0 d-flex align-items-center">
                                    <i class="fa-solid fa-gear me-2"></i> Advanced Options
                                </button>
                                <div class="mt-3 row g-2" x-show="showAdvanced" x-transition style="display: none;">
                                    <div class="col-12">
                                        <label class="form-label fw-bold small text-muted">REMARKS / TXN NO.</label>
                                        <div class="row g-2">
                                            <div class="col-md-5"><input type="text" name="transaction_number" class="form-control bg-white border shadow-sm" placeholder="Transaction Ref"></div>
                                            <div class="col-md-7"><input type="text" name="remarks" x-model="c_remarks" class="form-control bg-white border shadow-sm" placeholder="Optional remarks"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="custom-overlay-footer">
                        <button type="button" class="btn btn-light rounded-pill px-4 fw-bold" @click="createModalOpen = false">Cancel</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">Save Record</button>
                    </div>
                </form>
            </div>

            <!-- Edit Modal -->
            <div class="custom-overlay-backdrop" x-show="editModalOpen" x-transition.opacity @click.self="editModalOpen = false" x-cloak style="display: none;">
                <form method="POST" :action="editUrl" class="custom-overlay-modal" style="max-width: 600px;" :class="{ 'is-open': editModalOpen }" x-show="editModalOpen" x-transition.opacity style="display: none;">
                    @csrf @method('PUT')
                    
                    <div class="custom-overlay-header">
                        <h5 class="fw-bold mb-0 text-dark">Edit Subscription Record</h5>
                        <button type="button" class="btn-close" @click="editModalOpen = false"></button>
                    </div>

                    <div class="custom-overlay-body">
                        <div class="row g-4">
                            <!-- Edit Plan Dropdown -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">PLAN PERIOD</label>
                                <div x-data="{ dropOpen: false }" class="position-relative">
                                    <button type="button" @click="dropOpen = !dropOpen" class="form-control bg-white border shadow-sm d-flex justify-content-between align-items-center">
                                        <span x-text="e_plan === 'yearly' ? 'Yearly' : (e_plan === 'monthly' ? 'Monthly' : 'Trial (14 Days)')" class="text-dark fw-bold"></span>
                                        <i class="fa-solid fa-chevron-down text-muted small"></i>
                                    </button>
                                    <div x-show="dropOpen" @click.outside.capture="dropOpen = false" x-transition class="position-absolute w-100 bg-white shadow-lg rounded-3 mt-1 overflow-hidden border" style="display: none; z-index: 1050;">
                                        <div @click="e_plan = 'yearly'; dropOpen = false" class="px-3 py-2 border-bottom hover-bg-light cursor-pointer">
                                            <div class="fw-bold" :class="e_plan === 'yearly' ? 'text-primary' : 'text-dark'">Yearly</div>
                                        </div>
                                        <div @click="e_plan = 'monthly'; dropOpen = false" class="px-3 py-2 border-bottom hover-bg-light cursor-pointer">
                                            <div class="fw-bold" :class="e_plan === 'monthly' ? 'text-primary' : 'text-dark'">Monthly</div>
                                        </div>
                                        <div @click="e_plan = 'trial'; dropOpen = false" class="px-3 py-2 hover-bg-light cursor-pointer">
                                            <div class="fw-bold" :class="e_plan === 'trial' ? 'text-primary' : 'text-dark'">Trial (14 Days)</div>
                                        </div>
                                    </div>
                                    <input type="hidden" name="plan" :value="e_plan" required>
                                </div>
                            </div>
                            
                            <!-- Edit Amount -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">AMOUNT (₹)</label>
                                <input type="number" step="0.01" name="amount" x-model="e_amount" class="form-control fw-bold text-dark fs-5 bg-white border shadow-sm" required>
                            </div>
                            
                            <!-- Edit Dates -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">START DATE</label>
                                <input type="date" name="start_date" x-model="e_start" class="form-control bg-white border shadow-sm" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">END DATE</label>
                                <input type="date" name="end_date" x-model="e_end" class="form-control bg-white border shadow-sm" required>
                            </div>
                            
                            <!-- Edit Status Dropdown -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">STATUS</label>
                                <div x-data="{ dropOpen: false }" class="position-relative">
                                    <button type="button" @click="dropOpen = !dropOpen" class="form-control bg-white border shadow-sm d-flex justify-content-between align-items-center">
                                        <span x-text="e_status === 'paid' ? 'Paid' : (e_status === 'pending' ? 'Pending' : 'Failed')" class="fw-bold" :class="e_status === 'paid' ? 'text-success' : (e_status === 'pending' ? 'text-warning' : 'text-danger')"></span>
                                        <i class="fa-solid fa-chevron-down text-muted small"></i>
                                    </button>
                                    <div x-show="dropOpen" @click.outside.capture="dropOpen = false" x-transition class="position-absolute w-100 bg-white shadow-lg rounded-3 mb-1 overflow-hidden border" style="display: none; z-index: 1050; bottom: 100%; top: auto;">
                                        <div @click="e_status = 'paid'; dropOpen = false" class="px-3 py-2 border-bottom hover-bg-light cursor-pointer text-success fw-bold">Paid</div>
                                        <div @click="e_status = 'pending'; dropOpen = false" class="px-3 py-2 border-bottom hover-bg-light cursor-pointer text-warning fw-bold">Pending</div>
                                        <div @click="e_status = 'failed'; dropOpen = false" class="px-3 py-2 hover-bg-light cursor-pointer text-danger fw-bold">Failed</div>
                                    </div>
                                    <input type="hidden" name="payment_status" :value="e_status" required>
                                </div>
                            </div>
                            
                            <!-- Edit Method Dropdown -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">METHOD</label>
                                <div x-data="{ dropOpen: false }" class="position-relative">
                                    <button type="button" @click="dropOpen = !dropOpen" class="form-control bg-white border shadow-sm d-flex justify-content-between align-items-center">
                                        <span x-text="e_method ? (e_method === 'online' ? 'Online' : (e_method === 'comp' ? 'Comp (Free)' : paymentModes[e_method] || e_method)) : '— Select Method —'" :class="e_method ? 'fw-bold text-dark' : 'text-muted'"></span>
                                        <i class="fa-solid fa-chevron-down text-muted small"></i>
                                    </button>
                                    <div x-show="dropOpen" @click.outside.capture="dropOpen = false" x-transition class="position-absolute w-100 bg-white shadow-lg rounded-3 mb-1 overflow-hidden border" style="display: none; z-index: 1050; max-height: 200px; overflow-y: auto; bottom: 100%; top: auto;">
                                        <div @click="e_method = ''; dropOpen = false" class="px-3 py-2 border-bottom hover-bg-light cursor-pointer text-muted">— Select Method —</div>
                                        <template x-for="(label, key) in paymentModes" :key="key">
                                            <div @click="e_method = key; dropOpen = false" class="px-3 py-2 border-bottom hover-bg-light cursor-pointer fw-medium" x-text="label"></div>
                                        </template>
                                        <div @click="e_method = 'online'; dropOpen = false" class="px-3 py-2 border-bottom hover-bg-light cursor-pointer fw-medium">Online</div>
                                        <div @click="e_method = 'comp'; dropOpen = false" class="px-3 py-2 hover-bg-light cursor-pointer fw-bold text-primary">Comp / Bypassed (Free)</div>
                                    </div>
                                    <input type="hidden" name="payment_method" :value="e_method">
                                </div>
                            </div>

                            <!-- Remarks / Txn -->
                            <div class="col-12 mt-2" x-data="{ showAdvanced: false }">
                                <button type="button" @click="showAdvanced = !showAdvanced" class="btn btn-link text-muted fw-bold text-decoration-none p-0 d-flex align-items-center">
                                    <i class="fa-solid fa-gear me-2"></i> Advanced Options
                                </button>
                                <div class="mt-3 row g-3" x-show="showAdvanced" x-transition style="display: none;">
                                    <div class="col-12">
                                        <label class="form-label fw-bold small text-muted">TXN NO.</label>
                                        <input type="text" name="transaction_number" x-model="e_txn" class="form-control bg-white border shadow-sm">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-bold small text-muted">REMARKS</label>
                                        <input type="text" name="remarks" x-model="e_remarks" class="form-control bg-white border shadow-sm">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info border-0 bg-primary-subtle text-primary mt-4 mb-0 rounded-3">
                            <i class="fa-solid fa-circle-info me-1"></i> Saving with status <strong>Paid</strong> extends the branch's validity to the End Date.
                        </div>
                    </div>

                    <div class="custom-overlay-footer">
                        <button type="button" class="btn btn-light rounded-pill px-4 fw-bold" @click="editModalOpen = false">Cancel</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">Save Changes</button>
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
    Alpine.data('superadminSubscriptions', () => ({
        createModalOpen: false,
        editModalOpen: false,
        
        branches: <?php echo json_encode(
            $branches->map(fn($b) => [
                'id' => $b['branch']->id,
                'name' => $b['branch']->name,
                'owner' => $b['branch']->owner_name,
                'yearly' => $b['yearly'],
                'monthly' => $b['monthly']
            ])->values()
        ); ?>,
        subs: @json($subsJson),
        paymentModes: @json(config('hostelease.payment_modes')),
        editBaseUrl: @json(url('superadmin/subscriptions')),

        // Create form state
        c_branch: null,
        c_period: 'yearly',
        c_amount: '',
        c_status: 'paid',
        c_method: '',
        c_remarks: '',

        init() {
            this.$watch('c_branch', () => this.recalcCreate());
            this.$watch('c_period', () => this.recalcCreate());
            this.$watch('c_method', (val) => {
                if (val === 'comp') {
                    this.c_amount = 0;
                    this.c_status = 'paid';
                    if (!this.c_remarks) this.c_remarks = 'Comp / Payment bypassed by Superadmin';
                }
            });
        },
        recalcCreate() {
            if (!this.c_branch) return;
            this.c_amount = this.c_period === 'trial' ? 0 : (this.c_period === 'monthly' ? this.c_branch.monthly : this.c_branch.yearly);
        },
        openCreateModal(branchId = null) {
            if (branchId) {
                this.c_branch = this.branches.find(b => b.id == branchId);
            } else {
                this.c_branch = null;
                this.c_amount = '';
            }
            this.createModalOpen = true;
        },

        // Edit form state
        e_id: null,
        e_plan: 'yearly',
        e_amount: '',
        e_start: '',
        e_end: '',
        e_status: 'pending',
        e_method: '',
        e_txn: '',
        e_remarks: '',

        get editUrl() {
            return this.e_id ? this.editBaseUrl + '/' + this.e_id : '';
        },

        openEditModal(id) {
            const s = this.subs[id];
            if (!s) return;
            this.e_id = id;
            this.e_plan = s.plan === 'monthly' ? 'monthly' : (s.plan === 'trial' ? 'trial' : 'yearly');
            this.e_amount = s.amount;
            this.e_start = (s.start_date || '').substring(0, 10);
            this.e_end = (s.end_date || '').substring(0, 10);
            this.e_status = s.payment_status || 'pending';
            this.e_method = s.payment_method || '';
            this.e_txn = s.transaction_number || '';
            this.e_remarks = s.remarks || '';
            
            this.editModalOpen = true;
        }
    }))
});
</script>
@endpush
