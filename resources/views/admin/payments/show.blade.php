@extends('layouts.app')
@section('title', 'Receipt '.$payment->receipt_number)

@push('styles')
<style>
    @media print {
        .he-sidebar, .he-topbar, footer, .no-print { display: none !important; }
        .he-content { margin-left: 0 !important; }
        .receipt-card { box-shadow: none !important; border: 1px solid #ddd !important; }
    }
</style>
@endpush

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 no-print">
    <div class="d-flex align-items-center gap-2">
        <a href="{{ route('admin.payments.index') }}" class="btn btn-light btn-sm"><i class="fa-solid fa-arrow-left"></i></a>
        <h1 class="h4 fw-bold mb-0">Receipt</h1>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <button onclick="window.print()" class="btn btn-light"><i class="fa-solid fa-print me-1"></i> Print</button>
        <a href="{{ route('admin.payments.pdf', $payment) }}" class="btn btn-light"><i class="fa-solid fa-file-pdf me-1"></i> PDF</a>
        <form method="POST" action="{{ route('admin.payments.whatsapp', $payment) }}" target="_blank">@csrf
            <button class="btn btn-success"><i class="fa-brands fa-whatsapp me-1"></i> WhatsApp</button>
        </form>
        <button class="btn btn-primary" onclick="emailReceipt()"><i class="fa-solid fa-envelope me-1"></i> Email</button>
    </div>
</div>

<div class="card stat-card receipt-card mx-auto" style="max-width:680px;">
    <div class="card-body p-4 p-md-5">
        <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3">
            <div>
                <h2 class="h4 fw-bold text-primary mb-0">{{ $payment->hostel->name }}</h2>
                <p class="text-muted small mb-0">
                    {{ $payment->hostel->address }}{{ $payment->hostel->city ? ', '.$payment->hostel->city : '' }}<br>
                    {{ hostelease_phone($payment->hostel->mobile) }}
                </p>
            </div>
            <div class="text-end">
                <span class="badge bg-success">PAID</span>
                <div class="small text-muted mt-1">Receipt</div>
                <div class="fw-bold">{{ $payment->receipt_number }}</div>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-6">
                <div class="text-muted small">Received From</div>
                <div class="fw-semibold">{{ $payment->student->name }}</div>
                <div class="small">{{ hostelease_phone($payment->student->mobile) }}</div>
            </div>
            <div class="col-6 text-end">
                <div class="text-muted small">Date</div>
                <div class="fw-semibold">{{ $payment->paid_on->format('d M Y') }}</div>
            </div>
        </div>

        <table class="table table-sm mb-3">
            <tbody>
                <tr><th class="text-muted">Credit Used</th><td class="text-end">{{ $payment->credit_used > 0 ? '₹'.number_format($payment->credit_used, 2) : '-' }}</td></tr>
                <tr><th class="text-muted">Mode</th><td class="text-end text-uppercase">{{ $payment->mode }}</td></tr>
                @if($payment->reference_number)
                    <tr><th class="text-muted">Reference No.</th><td class="text-end">{{ $payment->reference_number }}</td></tr>
                @endif
                @if($payment->remarks)
                    <tr><th class="text-muted">Remarks</th><td class="text-end">{{ $payment->remarks }}</td></tr>
                @endif
            </tbody>
        </table>

        <div class="d-flex justify-content-between align-items-center bg-light rounded p-3">
            <span class="fw-semibold">Amount Paid</span>
            <span class="h4 fw-bold text-success mb-0">{{ hostelease_money($payment->amount) }}</span>
        </div>

        <div class="d-flex justify-content-between mt-4 pt-4">
            <div class="small text-muted">Collected by: {{ $payment->collector?->name ?? '—' }}</div>
            <div class="text-center"><div style="border-top:1px solid #999;width:140px;"></div><small class="text-muted">Authorised Signature</small></div>
        </div>
        <p class="text-center text-muted small mt-3 mb-0">This is a computer-generated receipt.</p>
    </div>
</div>

<form id="emailForm" method="POST" action="{{ route('admin.payments.email', $payment) }}" class="d-none">@csrf<input type="hidden" name="email" id="emailField"></form>
@endsection

@push('scripts')
<script>
    function emailReceipt() {
        Swal.fire({
            title: 'Email Receipt',
            input: 'email',
            inputPlaceholder: 'recipient@example.com',
            inputValue: @json($payment->hostel->email ?? ''),
            showCancelButton: true,
            confirmButtonText: 'Send',
        }).then((r) => {
            if (r.isConfirmed && r.value) {
                document.getElementById('emailField').value = r.value;
                document.getElementById('emailForm').submit();
            }
        });
    }
</script>
@endpush

