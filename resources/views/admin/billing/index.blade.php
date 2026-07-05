@extends('layouts.app')
@section('title', 'Subscription & Billing')

@section('content')
@php
    $active = $currentEnd && ! $currentEnd->isPast();
@endphp

<h1 class="h4 fw-bold mb-3">{{ __('Subscription & Billing') }}</h1>

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card stat-card h-100"><div class="card-body">
            <div class="stat-label">{{ __('Status') }}</div>
            @if($active)
                <div class="stat-value text-success">{{ __('Active') }}</div>
                <div class="small text-muted">{{ __('Valid until') }} <strong>{{ $currentEnd->format('d M Y') }}</strong>
                    ({{ (int) now()->startOfDay()->diffInDays($currentEnd->startOfDay(), false) }} {{ __('days left') }})</div>
            @else
                <div class="stat-value text-danger">{{ __('Expired') }}</div>
                <div class="small text-muted">{{ $currentEnd ? __('Lapsed on').' '.$currentEnd->format('d M Y') : __('No active subscription') }}</div>
            @endif
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card h-100"><div class="card-body">
            <div class="stat-label">{{ __('Branches') }}</div>
            <div class="stat-value">{{ $yearly['branches'] }}</div>
            <div class="small text-muted">{{ $yearly['payable'] }} {{ __('billable') }} · {{ $yearly['free'] }} {{ __('free') }}</div>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card h-100"><div class="card-body">
            <div class="stat-label">{{ __('Discount') }}</div>
            <div class="stat-value text-primary">{{ __('3-for-2') }}</div>
            <div class="small text-muted">{{ __('Every 3rd branch is free') }}</div>
        </div></div>
    </div>
</div>

@unless($razorpayEnabled)
    <div class="alert alert-warning">{{ __('Online payment is currently unavailable. Please contact support to renew.') }}</div>
@endunless

<div class="row g-3">
    {{-- Yearly plan --}}
    <div class="col-md-6">
        <div class="card stat-card h-100 border-primary"><div class="card-body">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h5 class="fw-bold mb-0">{{ __('Yearly') }}</h5>
                    <div class="text-muted small">{{ hsms_money($yearly['unit']) }} / {{ __('branch / year') }}</div>
                </div>
                <span class="badge bg-primary-subtle text-primary">{{ __('Best value') }}</span>
            </div>
            <div class="my-3">
                <span class="display-6 fw-bold">{{ hsms_money($yearly['amount']) }}</span>
                <span class="text-muted">/ {{ __('year') }}</span>
            </div>
            <ul class="list-unstyled small text-muted mb-3">
                <li><i class="fa-solid fa-check text-success me-1"></i> {{ $yearly['payable'] }} × {{ hsms_money($yearly['unit']) }} = {{ hsms_money($yearly['amount']) }}</li>
                <li><i class="fa-solid fa-check text-success me-1"></i> {{ $yearly['free'] }} {{ __('branch(es) free') }}</li>
                <li><i class="fa-solid fa-calendar-check me-1"></i> {{ __('Covers all branches until') }} {{ $yearly['end']->format('d M Y') }}</li>
            </ul>
            <button class="btn btn-primary w-100 pay-btn" data-period="yearly" @disabled(! $razorpayEnabled || $yearly['amount_paise'] < 100)>
                <i class="fa-solid fa-lock me-1"></i> {{ __('Pay') }} {{ hsms_money($yearly['amount']) }}
            </button>
        </div></div>
    </div>

    {{-- Monthly plan --}}
    <div class="col-md-6">
        <div class="card stat-card h-100"><div class="card-body">
            <h5 class="fw-bold mb-0">{{ __('Monthly') }}</h5>
            <div class="text-muted small">{{ hsms_money($monthly['unit']) }} / {{ __('branch / month') }}</div>
            <div class="my-3">
                <span class="display-6 fw-bold">{{ hsms_money($monthly['amount']) }}</span>
                <span class="text-muted">/ {{ __('month') }}</span>
            </div>
            <ul class="list-unstyled small text-muted mb-3">
                <li><i class="fa-solid fa-check text-success me-1"></i> {{ $monthly['payable'] }} × {{ hsms_money($monthly['unit']) }} = {{ hsms_money($monthly['amount']) }}</li>
                <li><i class="fa-solid fa-check text-success me-1"></i> {{ $monthly['free'] }} {{ __('branch(es) free') }}</li>
                <li><i class="fa-solid fa-calendar-check me-1"></i> {{ __('Covers all branches until') }} {{ $monthly['end']->format('d M Y') }}</li>
            </ul>
            <button class="btn btn-outline-primary w-100 pay-btn" data-period="monthly" @disabled(! $razorpayEnabled || $monthly['amount_paise'] < 100)>
                <i class="fa-solid fa-lock me-1"></i> {{ __('Pay') }} {{ hsms_money($monthly['amount']) }}
            </button>
        </div></div>
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
        if (on) btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Please wait…';
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
