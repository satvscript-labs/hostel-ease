@extends('layouts.app')
@section('title', 'My Branches')

@push('styles')
<style>
    /* Premium Header Layout */
    .pb-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
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
        border: 1.5px solid var(--he-primary);
        background: rgba(79, 70, 229, 0.02);
        box-shadow: 0 8px 24px rgba(79, 70, 229, 0.08);
    }
</style>
@endpush

@section('content')
<div x-data="branchManager()" class="page-enter pb-5">
    
    <!-- Header -->
    <div class="pb-header flex-wrap gap-3">
        <div>
            <h1 class="display-6 fw-bold mb-1">My Branches</h1>
            <p class="text-muted mb-0">Manage all your hostel locations, subscriptions, and settings.</p>
        </div>
        <div class="d-flex gap-3 align-items-center">
            <button @click="openAddBranchModal()" class="btn btn-primary rounded-pill px-4 py-2 shadow-sm fw-bold">
                <i class="fa-solid fa-plus me-2"></i>Add New Branch
            </button>
        </div>
    </div>

    <!-- Branch Grid -->
    <div class="row g-4 stagger">
        @forelse($branches as $branch)
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
                    
                    <button @click="openRenewModal({{ $branch->id }}, '{{ addslashes($branch->name) }}')" 
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

    <!-- Add Branch Modal (Teleported) -->
    <template x-teleport="body">
        <div class="custom-overlay-backdrop" x-show="modals.add.open" x-transition.opacity @click="modals.add.open = false" x-cloak style="display: none;">
            <form action="{{ route('admin.branches.store') }}" method="POST" class="custom-overlay-modal" :class="{ 'is-open': modals.add.open }" x-show="modals.add.open" @click.stop style="display: none;">
                @csrf
                <div class="custom-overlay-header">
                    <h5 class="fw-bold mb-0">Add New Branch</h5>
                    <button type="button" class="btn-close" @click="modals.add.open = false"></button>
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
                    <button type="button" class="btn btn-light rounded-pill px-4 fw-bold" @click="modals.add.open = false">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">Create Branch</button>
                </div>
            </form>
        </div>
    </template>

    <!-- Renew Branch Modal (Teleported) -->
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
    Alpine.data('branchManager', () => ({
        modals: {
            add: { open: false },
            renew: { open: false, branchId: null, branchName: '', period: 'yearly', loading: false }
        },
        
        openAddBranchModal() {
            this.modals.add.open = true;
        },
        
        openRenewModal(id, name) {
            this.modals.renew.branchId = id;
            this.modals.renew.branchName = name;
            this.modals.renew.period = 'yearly';
            this.modals.renew.loading = false;
            this.modals.renew.open = true;
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
