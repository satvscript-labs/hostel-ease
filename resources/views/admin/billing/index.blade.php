@extends('layouts.app')
@section('title', __('Subscription & Billing'))

@section('content')
@php
    $active = $currentEnd && ! $currentEnd->isPast();
    $daysLeft = $active ? (int) now()->startOfDay()->diffInDays($currentEnd->startOfDay(), false) : 0;
    $totalDays = 365; // Approximate, just for progress bar visual
    $progressPercent = $active ? min(100, max(0, ($daysLeft / $totalDays) * 100)) : 0;
    
    // Progress bar color logic
    $progressColor = 'bg-success';
    if ($daysLeft <= 30 && $daysLeft > 7) $progressColor = 'bg-warning';
    if ($daysLeft <= 7) $progressColor = 'bg-danger';
@endphp

<div class="page-enter">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold text-dark mb-1">{{ __('Subscription & Billing') }}</h1>
            <p class="text-muted mb-0">{{ __('Manage your plan, branches, and payments securely.') }}</p>
        </div>
    </div>

    <!-- Current Plan Dashboard -->
    <div class="card border-0 shadow-sm rounded-4 mb-5 overflow-hidden">
        <div class="card-body p-0">
            <div class="row g-0">
                <div class="col-lg-8 p-4 p-md-5">
                    <div class="d-flex justify-content-between align-items-start mb-4">
                        <div>
                            <p class="text-uppercase small fw-bold text-muted mb-1">{{ __('Current Status') }}</p>
                            @if($active)
                                <h2 class="display-6 fw-bold text-success mb-0 d-flex align-items-center gap-2">
                                    <i class="fa-regular fa-circle-check"></i> {{ __('Active') }}
                                </h2>
                            @else
                                <h2 class="display-6 fw-bold text-danger mb-0 d-flex align-items-center gap-2">
                                    <i class="fa-regular fa-circle-xmark"></i> {{ __('Expired') }}
                                </h2>
                            @endif
                        </div>
                        <div class="text-end">
                            <p class="text-uppercase small fw-bold text-muted mb-1">{{ __('Valid Until') }}</p>
                            <h4 class="fw-bold mb-0">{{ $currentEnd ? $currentEnd->format('d M Y') : __('N/A') }}</h4>
                        </div>
                    </div>

                    @if($active)
                    <div class="mb-2 d-flex justify-content-between small fw-semibold">
                        <span class="text-muted">{{ __('Time Remaining') }}</span>
                        <span class="{{ str_replace('bg-', 'text-', $progressColor) }}">{{ $daysLeft }} {{ __('days left') }}</span>
                    </div>
                    <div class="progress rounded-pill bg-light" style="height: 10px;">
                        <div class="progress-bar {{ $progressColor }} rounded-pill" role="progressbar" style="width: {{ $progressPercent }}%"></div>
                    </div>
                    @else
                    <div class="alert alert-danger bg-danger-subtle text-danger-emphasis border-0 rounded-3 mb-0">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i>
                        {{ $currentEnd ? __('Your subscription lapsed on').' '.$currentEnd->format('d M Y') : __('You currently have no active subscription.') }}
                    </div>
                    @endif
                </div>
                
                <div class="col-lg-4 bg-light border-start border-light-subtle p-4 p-md-5 d-flex flex-column justify-content-center">
                    <div class="mb-4">
                        <p class="text-uppercase small fw-bold text-muted mb-1">{{ __('Managed Branches') }}</p>
                        <h3 class="fw-bold mb-0 d-flex align-items-baseline gap-2">
                            {{ $yearly['branches'] }}
                            <span class="fs-6 text-muted fw-normal">{{ $yearly['payable'] }} {{ __('billable') }} / {{ $yearly['free'] }} {{ __('free') }}</span>
                        </h3>
                    </div>
                    <div>
                        <p class="text-uppercase small fw-bold text-muted mb-1">{{ __('Active Discount') }}</p>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-2 fw-semibold fs-6">
                                <i class="fa-solid fa-tags me-1"></i> {{ __('3-for-2 Offer') }}
                            </span>
                        </div>
                        <p class="small text-muted mt-2 mb-0">{{ __('Every 3rd branch is completely free.') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @unless($razorpayEnabled)
        <div class="alert alert-warning border-0 shadow-sm rounded-4 mb-5 d-flex align-items-center p-4">
            <div class="bg-warning text-dark rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px; flex-shrink: 0;">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <div>
                <h6 class="fw-bold mb-1">{{ __('Payment Gateway Unavailable') }}</h6>
                <p class="mb-0 small">{{ __('Online payment is currently disabled. Please contact support to renew your subscription.') }}</p>
            </div>
        </div>
    @endunless

    <!-- Pricing Plans -->
    <div class="text-center mb-5">
        <h2 class="fw-bold">{{ __('Choose Your Renewal Plan') }}</h2>
        <p class="text-muted">{{ __('Select a plan that best fits your business needs.') }}</p>
    </div>

    <style>
        .pricing-card {
            transition: all 0.3s cubic-bezier(0.25, 1, 0.5, 1);
            border-radius: 1.5rem;
        }
        .pricing-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.08) !important;
        }
        .pricing-best-value {
            border: 2px solid var(--bs-primary) !important;
            box-shadow: 0 0 0 4px rgba(var(--bs-primary-rgb), 0.1);
            position: relative;
        }
        .best-value-badge {
            position: absolute;
            top: -15px;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(135deg, var(--bs-primary), #6366f1);
            color: white;
            padding: 0.5rem 1.5rem;
            border-radius: 50px;
            font-weight: bold;
            font-size: 0.85rem;
            letter-spacing: 1px;
            text-transform: uppercase;
            box-shadow: 0 4px 12px rgba(var(--bs-primary-rgb), 0.4);
            white-space: nowrap;
        }
    </style>

    <div class="row g-4 justify-content-center align-items-center">
        {{-- Monthly plan --}}
        <div class="col-md-6 col-lg-5">
            <div class="card pricing-card border-0 shadow-sm h-100 p-2">
                <div class="card-body p-4 p-xl-5 d-flex flex-column">
                    <h5 class="fw-bold text-muted mb-1">{{ __('Monthly Plan') }}</h5>
                    <p class="text-muted small mb-4">{{ hostelease_money($monthly['unit']) }} / {{ __('branch / month') }}</p>
                    
                    <div class="mb-4">
                        <span class="display-4 fw-bold text-dark">{{ hostelease_money($monthly['amount']) }}</span>
                        <span class="text-muted">/ {{ __('mo') }}</span>
                    </div>
                    
                    <ul class="list-unstyled mb-5 flex-grow-1">
                        <li class="mb-3 d-flex align-items-start gap-3">
                            <div class="bg-success-subtle text-success rounded-circle d-flex align-items-center justify-content-center mt-1" style="width: 24px; height: 24px;">
                                <i class="fa-solid fa-check fs-7"></i>
                            </div>
                            <div>
                                <strong class="d-block text-dark">{{ __('Billed Monthly') }}</strong>
                                <span class="small text-muted">{{ $monthly['payable'] }} {{ __('branches') }} &times; {{ hostelease_money($monthly['unit']) }}</span>
                            </div>
                        </li>
                        <li class="mb-3 d-flex align-items-start gap-3">
                            <div class="bg-success-subtle text-success rounded-circle d-flex align-items-center justify-content-center mt-1" style="width: 24px; height: 24px;">
                                <i class="fa-solid fa-check fs-7"></i>
                            </div>
                            <div>
                                <strong class="d-block text-dark">{{ __('Free Branches') }}</strong>
                                <span class="small text-muted">{{ $monthly['free'] }} {{ __('branch(es) completely free') }}</span>
                            </div>
                        </li>
                        <li class="mb-3 d-flex align-items-start gap-3">
                            <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center mt-1" style="width: 24px; height: 24px;">
                                <i class="fa-regular fa-calendar-check fs-7"></i>
                            </div>
                            <div>
                                <strong class="d-block text-dark">{{ __('Coverage') }}</strong>
                                <span class="small text-muted">{{ __('Renews all branches until') }} <br><strong>{{ $monthly['end']->format('d M Y') }}</strong></span>
                            </div>
                        </li>
                    </ul>
                    
                    <button class="btn btn-light btn-lg rounded-pill fw-bold w-100 py-3 pay-btn transition-all" data-period="monthly" @disabled(! $razorpayEnabled || $monthly['amount_paise'] < 100)>
                        <i class="fa-solid fa-shield-halved me-2 text-muted"></i> {{ __('Pay Monthly') }}
                    </button>
                </div>
            </div>
        </div>

        {{-- Yearly plan --}}
        <div class="col-md-6 col-lg-5">
            <div class="card pricing-card pricing-best-value border-0 shadow h-100 p-2 mt-4 mt-md-0 z-index-1">
                <div class="best-value-badge">
                    <i class="fa-solid fa-star me-1"></i> {{ __('Best Value') }}
                </div>
                <div class="card-body p-4 p-xl-5 d-flex flex-column">
                    <h5 class="fw-bold text-primary mb-1">{{ __('Yearly Plan') }}</h5>
                    <p class="text-muted small mb-4">{{ hostelease_money($yearly['unit']) }} / {{ __('branch / year') }}</p>
                    
                    <div class="mb-4">
                        <span class="display-4 fw-bold text-dark">{{ hostelease_money($yearly['amount']) }}</span>
                        <span class="text-muted">/ {{ __('yr') }}</span>
                    </div>
                    
                    <ul class="list-unstyled mb-5 flex-grow-1">
                        <li class="mb-3 d-flex align-items-start gap-3">
                            <div class="bg-success-subtle text-success rounded-circle d-flex align-items-center justify-content-center mt-1" style="width: 24px; height: 24px;">
                                <i class="fa-solid fa-check fs-7"></i>
                            </div>
                            <div>
                                <strong class="d-block text-dark">{{ __('Billed Annually') }}</strong>
                                <span class="small text-muted">{{ $yearly['payable'] }} {{ __('branches') }} &times; {{ hostelease_money($yearly['unit']) }}</span>
                            </div>
                        </li>
                        <li class="mb-3 d-flex align-items-start gap-3">
                            <div class="bg-success-subtle text-success rounded-circle d-flex align-items-center justify-content-center mt-1" style="width: 24px; height: 24px;">
                                <i class="fa-solid fa-check fs-7"></i>
                            </div>
                            <div>
                                <strong class="d-block text-dark">{{ __('Free Branches') }}</strong>
                                <span class="small text-muted">{{ $yearly['free'] }} {{ __('branch(es) completely free') }}</span>
                            </div>
                        </li>
                        <li class="mb-3 d-flex align-items-start gap-3">
                            <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center mt-1" style="width: 24px; height: 24px;">
                                <i class="fa-regular fa-calendar-check fs-7"></i>
                            </div>
                            <div>
                                <strong class="d-block text-dark">{{ __('Peace of Mind') }}</strong>
                                <span class="small text-muted">{{ __('Coverage secured until') }} <br><strong>{{ $yearly['end']->format('d M Y') }}</strong></span>
                            </div>
                        </li>
                    </ul>
                    
                    <button class="btn btn-primary btn-lg rounded-pill fw-bold w-100 py-3 pay-btn transition-all shadow-sm" data-period="yearly" @disabled(! $razorpayEnabled || $yearly['amount_paise'] < 100)>
                        <i class="fa-solid fa-lock me-2"></i> {{ __('Secure Checkout') }} &mdash; {{ hostelease_money($yearly['amount']) }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const orderUrl = @json(route('admin.billing.order'));
    const verifyUrl = @json(route('admin.billing.verify'));

    async function post(url, body) {
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: JSON.stringify(body),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(data.message || 'Something went wrong. Please try again.');
        return data;
    }

    function setLoading(btn, on) {
        btn.disabled = on;
        btn.dataset.html = on ? btn.innerHTML : (btn.dataset.html || btn.innerHTML);
        if (on) btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> {{ __('Processing...') }}';
        else if (btn.dataset.html) btn.innerHTML = btn.dataset.html;
    }

    async function pay(btn) {
        const period = btn.dataset.period;
        setLoading(btn, true);
        let order;
        try {
            order = await post(orderUrl, { period });
        } catch (e) {
            setLoading(btn, false);
            window.Swal ? Swal.fire({ icon: 'error', title: 'Error', text: e.message }) : alert(e.message);
            return;
        }
        setLoading(btn, false);

        const rzp = new Razorpay({
            key: order.key,
            order_id: order.order_id,
            amount: order.amount,
            currency: order.currency,
            name: order.name,
            description: order.description,
            prefill: order.prefill,
            theme: { color: '#2563eb' },
            handler: async function (response) {
                try {
                    const result = await post(verifyUrl, {
                        razorpay_order_id: response.razorpay_order_id,
                        razorpay_payment_id: response.razorpay_payment_id,
                        razorpay_signature: response.razorpay_signature,
                        period: period,
                    });
                    if (window.Swal) {
                        await Swal.fire({ icon: 'success', title: 'Payment successful', text: result.message, timer: 2200, showConfirmButton: false });
                    }
                    window.location = result.redirect || @json(route('dashboard'));
                } catch (e) {
                    window.Swal ? Swal.fire({ icon: 'error', title: 'Verification failed', text: e.message }) : alert(e.message);
                }
            },
            modal: {
                ondismiss: function () {
                    window.Swal && Swal.fire({ icon: 'info', title: 'Payment cancelled', text: 'You closed the payment window before completing the payment.' });
                },
            },
        });

        rzp.on('payment.failed', function (resp) {
            const msg = (resp && resp.error && resp.error.description) || 'Payment failed. Please try again.';
            window.Swal ? Swal.fire({ icon: 'error', title: 'Payment failed', text: msg }) : alert(msg);
        });

        rzp.open();
    }

    document.querySelectorAll('.pay-btn').forEach((btn) => {
        btn.addEventListener('click', () => pay(btn));
    });
})();
</script>
@endpush
