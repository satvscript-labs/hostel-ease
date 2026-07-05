<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { color: #1f2937; font-size: 12px; margin: 0; padding: 24px; }
        .head { border-bottom: 2px solid #2563eb; padding-bottom: 10px; margin-bottom: 16px; }
        .hostel { font-size: 20px; font-weight: bold; color: #2563eb; }
        .muted { color: #6b7280; font-size: 11px; }
        .paid { background: #22c55e; color: #fff; padding: 2px 8px; border-radius: 4px; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin: 12px 0; }
        td, th { padding: 6px 4px; text-align: left; }
        .row td { border-bottom: 1px solid #eee; }
        .amount { background: #f1f5f9; padding: 12px; border-radius: 6px; font-size: 16px; font-weight: bold; }
        .right { text-align: right; }
        .sign { margin-top: 40px; }
        .line { border-top: 1px solid #999; width: 160px; display: inline-block; }
    </style>
</head>
<body>
    <table class="head"><tr>
        <td>
            <div class="hostel">{{ $payment->hostel->name }}</div>
            <div class="muted">
                {{ $payment->hostel->address }}{{ $payment->hostel->city ? ', '.$payment->hostel->city : '' }}<br>
                {{ hsms_phone($payment->hostel->mobile) }}
                @if($payment->hostel->gst_number) · GST: {{ $payment->hostel->gst_number }}@endif
            </div>
        </td>
        <td class="right">
            <span class="paid">PAID</span><br>
            <span class="muted">Receipt No.</span><br>
            <strong>{{ $payment->receipt_number }}</strong>
        </td>
    </tr></table>

    <table><tr>
        <td>
            <span class="muted">Received From</span><br>
            <strong>{{ $payment->student->name }}</strong><br>
            {{ hsms_phone($payment->student->mobile) }}
        </td>
        <td class="right">
            <span class="muted">Date</span><br>
            <strong>{{ $payment->paid_on->format('d M Y') }}</strong>
        </td>
    </tr></table>

    <table>
        <tr class="row"><th>Payment Type</th><td class="right">{{ config('hsms.payment_types.'.$payment->payment_type) }}</td></tr>
        <tr class="row"><th>Mode</th><td class="right">{{ strtoupper($payment->mode) }}</td></tr>
        @if($payment->reference_number)
            <tr class="row"><th>Reference No.</th><td class="right">{{ $payment->reference_number }}</td></tr>
        @endif
        @if($payment->remarks)
            <tr class="row"><th>Remarks</th><td class="right">{{ $payment->remarks }}</td></tr>
        @endif
    </table>

    <table class="amount"><tr>
        <td>Amount Paid</td>
        <td class="right">{{ hsms_money($payment->amount) }}</td>
    </tr></table>

    <table class="sign"><tr>
        <td class="muted">Collected by: {{ $payment->collector?->name ?? '—' }}</td>
        <td class="right"><span class="line"></span><br><span class="muted">Authorised Signature</span></td>
    </tr></table>

    <p class="muted" style="text-align:center;margin-top:20px;">This is a computer-generated receipt.</p>
</body>
</html>
