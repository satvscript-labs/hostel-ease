@extends('layouts.app')
@section('title', 'My Subscription')

{{-- ─────────────────────────────────────────────────────────────────────────
     REFERENCE (P4 item 15 — do not remove until the owner self-serve redesign):
     The Super Admin billing terminal (Account 360) was heavily reworked in the
     P4 fix rounds — live discount-aware summaries, per-period custom prices,
     scoped comps, explicit owner FK (see _artifact/subscription_update/
     07_phase4_manual_testing_fixes.md). THIS owner-facing page has NOT been
     redesigned to match yet; it is deliberately deferred.

     Until then it runs PRODUCTION-LOCKED via config('hostelease.owner_self_serve')
     (default false): owners can view plans/coverage/history, but renewals,
     add-branch payments, and self-serve branch creation are supervised — the
     Super Admin performs them from Account 360. The same flag gates the
     server-side ops (Admin\SubscriptionController / BranchManagerController).
   ───────────────────────────────────────────────────────────────────────── --}}

@php
    use App\Enums\AccountStatus;
    $status = $account->status;
    $days = $account->daysUntilAnchor();
    $q = $quotes[$displayPeriod];
    $anchorFmt = $account->current_period_end?->format('d M Y');

    // Hero presentation per lifecycle state.
    $hero = match ($status) {
        AccountStatus::Trial => ['tone' => 'trial', 'icon' => 'gift', 'label' => 'Free trial', 'cta' => 'Subscribe now'],
        AccountStatus::Grace => ['tone' => 'warn',  'icon' => 'triangle-exclamation', 'label' => 'Expired — grace period', 'cta' => 'Renew now to restore'],
        AccountStatus::Expired => ['tone' => 'danger', 'icon' => 'circle-xmark', 'label' => 'Subscription expired', 'cta' => 'Renew now'],
        AccountStatus::Suspended => ['tone' => 'muted', 'icon' => 'lock', 'label' => 'Account on hold', 'cta' => null],
        default => ['tone' => ($days !== null && $days <= 30) ? 'due' : 'active', 'icon' => 'circle-check', 'label' => 'Active', 'cta' => 'Renew all now'],
    };
@endphp

@push('styles')
<style>
    .sub-hero { border-radius: 1.4rem; position: relative; overflow: hidden; color:#fff; }
    .sub-hero-bg { position:absolute; inset:0; border-radius:inherit; overflow:hidden; z-index:0; pointer-events:none; }
    .sub-hero-bg::after { content:''; position:absolute; top:-40%; right:-8%; width:420px; height:420px; background:radial-gradient(circle, rgba(147,51,234,0.4), transparent 70%); }
    .hero-active  { background: var(--he-gradient-mesh, linear-gradient(135deg,#0f172a,#1e1b4b)); }
    .hero-trial   { background: linear-gradient(135deg,#4f46e5,#7c3aed); }
    .hero-due     { background: linear-gradient(135deg,#7c3aed,#b45309); }
    .hero-warn    { background: linear-gradient(135deg,#b45309,#7c2d12); }
    .hero-danger  { background: linear-gradient(135deg,#7f1d1d,#450a0a); }
    .hero-muted   { background: linear-gradient(135deg,#334155,#0f172a); }
    .sub-metric { font-variant-numeric: tabular-nums; }
    .panel-card { background:#fff; border:1px solid rgba(0,0,0,0.05); border-radius:1.1rem; transition:all .3s cubic-bezier(.25,1,.5,1); }
    .panel-card:hover { box-shadow:0 12px 30px rgba(0,0,0,0.04); }
    .plan-pick { border:1.5px solid rgba(0,0,0,0.08); border-radius:1rem; padding:1rem 1.1rem; cursor:pointer; transition:all .2s var(--ease-out-expo,cubic-bezier(.16,1,.3,1)); }
    .plan-pick.on { border-color:var(--bs-primary); background:rgba(79,70,229,.05); box-shadow:0 6px 18px rgba(79,70,229,.12); }
    .custom-overlay-backdrop { position:fixed; inset:0; background:rgba(15,23,42,0.6); backdrop-filter:blur(8px); z-index:9999; display:flex; align-items:center; justify-content:center; padding:1rem; }
    .custom-overlay-modal { width:100%; background:#fff; border-radius:1.25rem; box-shadow:0 25px 50px -12px rgba(0,0,0,.25); display:flex; flex-direction:column; max-height:92vh; transform:scale(.95); opacity:0; transition:all .3s cubic-bezier(.16,1,.3,1); overflow:hidden; }
    .custom-overlay-modal.is-open { transform:scale(1); opacity:1; }
    .custom-overlay-header { padding:1.25rem 1.5rem; border-bottom:1px solid rgba(0,0,0,.05); display:flex; justify-content:space-between; align-items:center; }
    .custom-overlay-body { padding:1.5rem; overflow-y:auto; background:#fafafa; }
    .custom-overlay-footer { padding:1.1rem 1.5rem; border-top:1px solid rgba(0,0,0,.05); display:flex; gap:.75rem; justify-content:flex-end; }
    @media (max-width: 575px) { .custom-overlay-modal { max-height:100vh; border-radius:1.25rem 1.25rem 0 0; align-self:flex-end; } .custom-overlay-backdrop { padding:0; align-items:flex-end; } }
    .sub-sticky { position:fixed; left:0; right:0; bottom:0; z-index:1030; background:#fff; border-top:1px solid rgba(0,0,0,.08); padding:.7rem 1rem; box-shadow:0 -6px 20px rgba(0,0,0,.06); }
</style>
@endpush

@section('content')
<div class="page-enter" x-data="ownerSubscription()">
    <div class="mb-4">
        <h1 class="h3 fw-bold mb-1 text-dark tracking-tight">My Subscription</h1>
        <p class="text-muted mb-0 small">Renew every branch together, on one date, in one payment.</p>
    </div>

    {{-- ── Status hero ── --}}
    <div class="sub-hero hero-{{ $hero['tone'] }} p-4 p-md-4 mb-4 shadow">
        <div class="sub-hero-bg"></div>
        <div class="position-relative" style="z-index:1;">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <span class="badge bg-white bg-opacity-25 rounded-pill px-3 py-2 mb-2"><i class="fa-solid fa-{{ $hero['icon'] }} me-1"></i>{{ $hero['label'] }}</span>
                    <div class="text-white-50 small">
                        @if($status === AccountStatus::Trial)
                            Trial ends {{ $anchorFmt ?? '—' }}@if($days !== null && $days >= 0) · {{ $days }} day(s) left @endif
                        @elseif($status === AccountStatus::Grace)
                            Access ends soon — renew to keep your hostels running.
                        @elseif($status === AccountStatus::Expired)
                            Your hostels are blocked until you renew.
                        @elseif($status === AccountStatus::Suspended)
                            Your account is on hold. Please contact support.
                        @else
                            @if($days !== null && $days >= 0) Renews in {{ $days }} day(s). @else Renews on the date below. @endif
                        @endif
                    </div>
                </div>
                @if($hero['cta'] && $status !== AccountStatus::Suspended && $selfServe)
                    <div class="d-flex flex-wrap gap-2">
                        @if($razorpayEnabled)
                            <button class="btn btn-light rounded-pill px-4 fw-bold shadow-sm tactile-btn" @click="openRenew()"><i class="fa-solid fa-arrows-rotate me-2"></i>{{ $hero['cta'] }}</button>
                        @else
                            <button class="btn btn-light rounded-pill px-4 fw-bold" disabled title="Online payment unavailable">Payments unavailable</button>
                        @endif
                        <button class="btn btn-outline-light rounded-pill px-3 fw-bold" @click="openAdd()"><i class="fa-solid fa-plus me-2"></i>Add a branch</button>
                    </div>
                @elseif($status === AccountStatus::Suspended)
                    <a href="tel:" class="btn btn-light rounded-pill px-4 fw-bold shadow-sm"><i class="fa-solid fa-headset me-2"></i>Contact support</a>
                @elseif(! $selfServe)
                    {{-- Production lock: supervised billing (P4 item 15). --}}
                    <div class="d-flex align-items-center gap-2 bg-white bg-opacity-10 rounded-pill px-3 py-2" style="backdrop-filter: blur(8px);">
                        <i class="fa-solid fa-shield-halved"></i>
                        <span class="small fw-semibold">Renewals are handled by HostelEase support</span>
                    </div>
                @endif
            </div>

            <div class="row g-3 mt-2">
                <div class="col-6 col-md-3"><div class="text-white-50 small text-uppercase" style="letter-spacing:.5px;">Branches</div><div class="h4 fw-bold mb-0 sub-metric">{{ $branches->count() }}</div></div>
                <div class="col-6 col-md-3"><div class="text-white-50 small text-uppercase" style="letter-spacing:.5px;">{{ $status === AccountStatus::Trial ? 'Trial ends' : 'Renews on' }}</div><div class="h4 fw-bold mb-0 sub-metric">{{ $anchorFmt ?? '—' }}</div></div>
                <div class="col-6 col-md-3"><div class="text-white-50 small text-uppercase" style="letter-spacing:.5px;">Term</div><div class="h4 fw-bold mb-0">{{ $account->period?->label() ?? 'Yearly' }}</div></div>
                <div class="col-6 col-md-3">
                    <div class="text-white-50 small text-uppercase" style="letter-spacing:.5px;">Next total</div>
                    <div class="h4 fw-bold mb-0 sub-metric">{{ hostelease_money($q['final']) }}</div>
                    @if($q['discount'] > 0)<div class="small text-white-50">Discount applied −{{ hostelease_money($q['discount']) }}</div>@endif
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        {{-- ── Branches ── --}}
        <div class="col-lg-7">
            <div class="panel-card shadow-sm h-100">
                <div class="p-3 px-4 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-hotel text-primary me-2"></i>Your branches</h6>
                    <span class="text-muted small">{{ $branches->count() }} total</span>
                </div>
                <div class="stagger">
                    @forelse($branches as $branch)
                        @php($behind = $account->current_period_end && $account->current_period_end->isFuture() && (! $branch->subscription_end || $branch->subscription_end->lt($account->current_period_end)))
                        <div class="d-flex justify-content-between align-items-center px-4 py-3 border-bottom">
                            <div>
                                <div class="fw-bold text-dark">{{ $branch->name }}</div>
                                <div class="small text-muted">Ends {{ $branch->subscription_end ? $branch->subscription_end->format('d M Y') : '—' }}</div>
                            </div>
                            @if($branch->isActive())
                                @if($behind)
                                    <span class="badge bg-warning-subtle text-warning rounded-pill px-3 py-2" title="Renewing all will align this branch">Behind</span>
                                @else
                                    <span class="badge bg-success-subtle text-success rounded-pill px-3 py-2">Active</span>
                                @endif
                            @else
                                <span class="badge bg-danger-subtle text-danger rounded-pill px-3 py-2">Expired</span>
                            @endif
                        </div>
                    @empty
                        <div class="p-4"><x-he-empty-state icon="hotel" title="No branches yet" subtitle="Add a branch to get started." /></div>
                    @endforelse
                </div>
                @if($branches->contains(fn($b) => $account->current_period_end && $account->current_period_end->isFuture() && (! $b->subscription_end || $b->subscription_end->lt($account->current_period_end))))
                    <div class="px-4 py-3 small text-muted bg-light bg-opacity-50"><i class="fa-solid fa-circle-info text-warning me-1"></i>Renewing all brings every branch onto the same date.</div>
                @endif
            </div>
        </div>

        {{-- ── Payment history ── --}}
        <div class="col-lg-5">
            <div class="panel-card shadow-sm h-100">
                <div class="p-3 px-4 border-bottom"><h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-receipt text-primary me-2"></i>Recent payments</h6></div>
                @forelse($orders->where('payment_status', \App\Enums\PaymentStatus::Paid) as $order)
                    <div class="d-flex justify-content-between align-items-center px-4 py-3 border-bottom">
                        <div>
                            <div class="fw-bold text-dark">{{ hostelease_money($order->amount) }}</div>
                            <div class="small text-muted">{{ $order->period?->label() ?? '' }} · {{ $order->quantity }} branch(es) · {{ $order->created_at?->format('d M Y') }}</div>
                        </div>
                        <span class="badge bg-success-subtle text-success rounded-pill px-3 py-1">Paid</span>
                    </div>
                @empty
                    <div class="p-4"><x-he-empty-state icon="receipt" title="No payments yet" subtitle="Your renewals will appear here." /></div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- ── Mobile sticky renew bar ── --}}
    @if($hero['cta'] && $status !== AccountStatus::Suspended && $razorpayEnabled && $selfServe)
        <div class="d-lg-none" style="height:76px;"></div>
        <div class="sub-sticky d-lg-none">
            <div class="d-flex align-items-center justify-content-between gap-3">
                <div class="flex-shrink-0">
                    <div class="small text-muted lh-1">Next total</div>
                    <div class="fw-bold text-dark" x-text="money(q().final)"></div>
                </div>
                <button class="btn btn-primary rounded-pill px-4 fw-bold flex-grow-1 tactile-btn" @click="openRenew()"><i class="fa-solid fa-arrows-rotate me-2"></i>{{ $hero['cta'] }}</button>
            </div>
        </div>
    @endif

    @unless($selfServe)
        {{-- Production lock notice (P4 item 15): view-only billing. --}}
        <div class="d-flex align-items-start gap-3 mt-4 p-3 px-4 rounded-4 shadow-sm" style="background:var(--he-warning-soft,#fef3c7); border:1px solid rgba(245,158,11,.25);">
            <i class="fa-solid fa-shield-halved fs-5 mt-1" style="color:var(--he-warning,#f59e0b);"></i>
            <div>
                <div class="fw-bold text-dark" style="font-size:.92rem;">Billing is managed by HostelEase support</div>
                <div class="small text-muted">Your plans and coverage above are always up to date. To renew, add a branch, or change your plan, contact support and our team will set it up on your account.</div>
            </div>
        </div>
    @endunless

    @if($selfServe)
    {{-- ══ Add-branch modal ══ --}}
    <template x-teleport="body">
        <div class="custom-overlay-backdrop" x-show="addOpen" x-transition.opacity @click.self="addOpen=false" x-cloak style="display:none;">
            <div class="custom-overlay-modal" style="max-width:500px;" :class="{'is-open':addOpen}">
                <div class="custom-overlay-header"><h5 class="fw-bold mb-0">Add a branch</h5><button type="button" class="btn-close" @click="addOpen=false" :disabled="loading"></button></div>
                <div class="custom-overlay-body">
                    <label class="form-label fw-bold small text-muted">BRANCH NAME</label>
                    <input type="text" x-model="add.name" class="form-control bg-white border shadow-sm mb-3" placeholder="e.g. Sunrise Riverside" maxlength="255">
                    <label class="form-label fw-bold small text-muted">CITY <span class="fw-normal">— optional</span></label>
                    <input type="text" x-model="add.city" class="form-control bg-white border shadow-sm mb-4" placeholder="e.g. Surat" maxlength="100">

                    @if($account->current_period_end && $account->current_period_end->isFuture() && $addBranch['prorated'] > 0)
                        <div class="bg-white border rounded-4 p-3 mb-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-bold text-dark">Add to my plan now</div>
                                    <div class="small text-muted">Prorated to {{ $addBranch['anchor'] }} · {{ $addBranch['days'] }} day(s)</div>
                                </div>
                                <div class="h5 fw-bold text-primary mb-0">{{ hostelease_money($addBranch['prorated']) }}</div>
                            </div>
                        </div>
                        <div class="small text-muted mb-1">Co-terminates with your other branches, so everything renews together.</div>
                    @else
                        <div class="alert bg-info-subtle text-info border-0 rounded-3 small mb-0"><i class="fa-solid fa-circle-info me-1"></i>This branch will start on a 14-day free trial.</div>
                    @endif
                </div>
                <div class="custom-overlay-footer d-flex flex-column flex-sm-row gap-2">
                    <form method="POST" action="{{ route('admin.branches.store') }}" class="order-2 order-sm-1 me-sm-auto w-100 w-sm-auto">
                        @csrf
                        <input type="hidden" name="name" :value="add.name">
                        <input type="hidden" name="city" :value="add.city">
                        <button type="submit" class="btn btn-link text-muted fw-semibold text-decoration-none px-0" :disabled="!add.name || loading">Start a 14-day free trial instead</button>
                    </form>
                    @if($razorpayEnabled && $account->current_period_end && $account->current_period_end->isFuture() && $addBranch['prorated'] > 0)
                        <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm order-1 order-sm-2 d-flex align-items-center justify-content-center gap-2" @click="payAdd()" :disabled="!add.name || loading">
                            <span x-show="!loading"><i class="fa-solid fa-lock me-1"></i>Add &amp; pay {{ hostelease_money($addBranch['prorated']) }}</span>
                            <span x-show="loading" class="spinner-border spinner-border-sm"></span>
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </template>

    {{-- ══ Renew-all modal ══ --}}
    <template x-teleport="body">
        <div class="custom-overlay-backdrop" x-show="renewOpen" x-transition.opacity @click.self="renewOpen=false" x-cloak style="display:none;">
            <div class="custom-overlay-modal" style="max-width:520px;" :class="{'is-open':renewOpen}">
                <div class="custom-overlay-header"><h5 class="fw-bold mb-0">Renew all branches</h5><button type="button" class="btn-close" @click="renewOpen=false" :disabled="loading"></button></div>
                <div class="custom-overlay-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="text-muted small text-uppercase" style="letter-spacing:.5px;">Choose term</div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <div class="plan-pick h-100" :class="{'on':period==='yearly'}" @click="period='yearly'">
                                <div class="fw-bold text-uppercase small" :class="period==='yearly'?'text-primary':'text-muted'">Yearly</div>
                                <div class="h5 fw-bold text-dark mb-0" x-text="money(quotes.yearly.unit)"></div>
                                <div class="small text-success fw-bold">Save 16%</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="plan-pick h-100" :class="{'on':period==='monthly'}" @click="period='monthly'">
                                <div class="fw-bold text-uppercase small" :class="period==='monthly'?'text-primary':'text-muted'">Monthly</div>
                                <div class="h5 fw-bold text-dark mb-0" x-text="money(quotes.monthly.unit)"></div>
                                <div class="small text-muted">per branch</div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white border rounded-4 p-3">
                        <div class="d-flex justify-content-between mb-1"><span class="text-muted"><span x-text="q().quantity"></span> branch(es) × <span x-text="money(q().unit)"></span></span><span class="fw-semibold" x-text="money(q().subtotal)"></span></div>
                        <template x-if="q().discount > 0">
                            <div class="d-flex justify-content-between mb-1 text-success"><span>Discount applied</span><span x-text="'−'+money(q().discount)"></span></div>
                        </template>
                        <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                            <span class="fw-bold">Total payable</span>
                            <span class="h4 fw-bold mb-0 text-primary" x-text="money(q().final)"></span>
                        </div>
                        <div class="small text-muted mt-2"><i class="fa-solid fa-calendar-check me-1"></i>New renewal date: <span class="fw-semibold text-dark" x-text="q().new_anchor"></span> — all branches together.</div>
                    </div>
                </div>
                <div class="custom-overlay-footer">
                    <button type="button" class="btn btn-light rounded-pill px-4 fw-bold" @click="renewOpen=false" :disabled="loading">Cancel</button>
                    <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm d-flex align-items-center gap-2" @click="pay()" :disabled="loading">
                        <span x-show="!loading"><i class="fa-solid fa-lock me-1"></i>Pay <span x-text="money(q().final)"></span></span>
                        <span x-show="loading" class="spinner-border spinner-border-sm"></span>
                    </button>
                </div>
            </div>
        </div>
    </template>
    @endif
</div>
@endsection

@push('scripts')
@if($razorpayEnabled && $selfServe)
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
@endif
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('ownerSubscription', () => ({
        renewOpen: false,
        addOpen: false,
        loading: false,
        period: @json($displayPeriod),
        quotes: @json($quotes),
        add: { name: '', city: '' },
        prefill: @json(['name' => $account->owner?->name, 'email' => $account->owner?->email, 'contact' => $account->owner?->mobile]),
        rzpKeyName: @json(config('app.name')),
        money(v) { return '₹' + Number(v || 0).toLocaleString('en-IN', {minimumFractionDigits:0, maximumFractionDigits:2}); },
        q() { return this.quotes[this.period]; },
        openRenew() { this.renewOpen = true; },
        openAdd() { this.add = { name: '', city: '' }; this.addOpen = true; },
        async payAdd() {
            @if(!$razorpayEnabled)
                return;
            @endif
            if (!this.add.name) return;
            this.loading = true;
            try {
                const orderRes = await fetch('{{ route('admin.subscription.add-branch-order') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                    body: JSON.stringify({ name: this.add.name, city: this.add.city }),
                });
                const order = await orderRes.json();
                if (!orderRes.ok) { window.showToast ? window.showToast(order.message || 'Could not start payment', 'error') : alert(order.message); this.loading = false; return; }
                // Branch created but nothing to charge (trial), or payment couldn't start — just go back.
                if (order.trial_only || !order.order_id) { window.location = order.redirect || '{{ route('admin.subscription.index') }}'; return; }

                const rzp = new Razorpay({
                    key: order.key, order_id: order.order_id, amount: order.amount, currency: order.currency,
                    name: this.rzpKeyName, description: order.description, prefill: this.prefill,
                    theme: { color: '#4f46e5' },
                    modal: { ondismiss: () => { this.loading = false; } },
                    handler: async (response) => {
                        const verifyRes = await fetch('{{ route('admin.subscription.verify') }}', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                            body: JSON.stringify({
                                type: 'add_branch', period: order.period, branch_id: order.branch_id,
                                razorpay_order_id: response.razorpay_order_id,
                                razorpay_payment_id: response.razorpay_payment_id,
                                razorpay_signature: response.razorpay_signature,
                            }),
                        });
                        const result = await verifyRes.json();
                        if (verifyRes.ok) {
                            if (window.Swal) { await Swal.fire({ icon: 'success', title: 'Branch added!', text: result.message, confirmButtonColor: '#4f46e5' }); }
                            window.location = result.redirect || '{{ route('admin.subscription.index') }}';
                        } else {
                            window.showToast ? window.showToast(result.message || 'Verification failed', 'error') : alert(result.message);
                            this.loading = false;
                        }
                    },
                });
                rzp.on('payment.failed', () => { window.showToast ? window.showToast('Payment failed — you were not charged. The branch is on a free trial.', 'error') : null; this.loading = false; });
                rzp.open();
            } catch (e) {
                window.showToast ? window.showToast('Something went wrong. Please try again.', 'error') : alert('Something went wrong.');
                this.loading = false;
            }
        },
        async pay() {
            @if(!$razorpayEnabled)
                return;
            @endif
            this.loading = true;
            try {
                const orderRes = await fetch('{{ route('admin.subscription.renew-order') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                    body: JSON.stringify({ period: this.period }),
                });
                const order = await orderRes.json();
                if (!orderRes.ok) { window.showToast ? window.showToast(order.message || 'Could not start payment', 'error') : alert(order.message); this.loading = false; return; }

                const rzp = new Razorpay({
                    key: order.key, order_id: order.order_id, amount: order.amount, currency: order.currency,
                    name: this.rzpKeyName, description: order.description, prefill: this.prefill,
                    theme: { color: '#4f46e5' },
                    modal: { ondismiss: () => { this.loading = false; } },
                    handler: async (response) => {
                        const verifyRes = await fetch('{{ route('admin.subscription.verify') }}', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                            body: JSON.stringify({
                                type: 'renew_account', period: order.period,
                                razorpay_order_id: response.razorpay_order_id,
                                razorpay_payment_id: response.razorpay_payment_id,
                                razorpay_signature: response.razorpay_signature,
                            }),
                        });
                        const result = await verifyRes.json();
                        if (verifyRes.ok) {
                            if (window.Swal) {
                                await Swal.fire({ icon: 'success', title: "You're all set!", text: result.message, confirmButtonColor: '#4f46e5' });
                            }
                            window.location = result.redirect || '{{ route('admin.subscription.index') }}';
                        } else {
                            window.showToast ? window.showToast(result.message || 'Verification failed', 'error') : alert(result.message);
                            this.loading = false;
                        }
                    },
                });
                rzp.on('payment.failed', () => { window.showToast ? window.showToast('Payment failed — you were not charged. Please try again.', 'error') : null; this.loading = false; });
                rzp.open();
            } catch (e) {
                window.showToast ? window.showToast('Something went wrong. Please try again.', 'error') : alert('Something went wrong.');
                this.loading = false;
            }
        },
    }));
});
</script>
@endpush
