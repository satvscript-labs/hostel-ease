<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { color: #1f2937; font-size: 11px; margin: 0; padding: 24px; }
        h1 { color: #2563eb; font-size: 18px; margin: 0 0 2px; }
        .muted { color: #6b7280; font-size: 10px; }
        .totals { width: 100%; margin: 16px 0; }
        .totals td { padding: 10px; background: #f1f5f9; border-radius: 6px; text-align: center; }
        .totals .v { font-size: 15px; font-weight: bold; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.data th { background: #2563eb; color: #fff; padding: 6px; text-align: left; font-size: 10px; }
        table.data td { padding: 5px 6px; border-bottom: 1px solid #eee; }
        .right { text-align: right; }
        h2 { font-size: 13px; margin: 18px 0 4px; }
    </style>
</head>
<body>
    <h1>{{ $student->hostel->name }}</h1>
    <div class="muted">Student Ledger Statement · Generated {{ now()->format('d-m-Y') }}</div>

    <table style="width:100%;margin-top:12px;"><tr>
        <td><strong>{{ $student->name }}</strong><br>
            <span class="muted">{{ hostelease_phone($student->mobile) }} · {{ config('hostelease.occupation_types.'.$student->occupation_type) }}</span></td>
        <td class="right"><span class="muted">Status</span><br>{{ ucfirst($student->status) }}</td>
    </tr></table>

    <table class="totals"><tr>
        <td><div class="muted">Total Fees</div><div class="v">{{ hostelease_money($totals['billed']) }}</div></td>
        <td><div class="muted">Total Paid</div><div class="v" style="color:#16a34a;">{{ hostelease_money($totals['paid']) }}</div></td>
        <td><div class="muted">Remaining</div><div class="v" style="color:#dc2626;">{{ hostelease_money($totals['outstanding']) }}</div></td>
    </tr></table>

    <h2>Obligations</h2>
    <table class="data">
        <thead><tr><th>Date</th><th>Particular</th><th class="right">Amount</th><th class="right">Paid</th><th class="right">Balance</th><th>Status</th></tr></thead>
        <tbody>
        @forelse($obligations as $o)
            <tr>
                <td>{{ optional($o['date'])->format('d-m-Y') ?? '—' }}</td>
                <td>{{ $o['particular'] }}</td>
                <td class="right">{{ hostelease_money($o['amount']) }}</td>
                <td class="right">{{ hostelease_money($o['paid']) }}</td>
                <td class="right">{{ hostelease_money($o['balance']) }}</td>
                <td>{{ ucfirst($o['status']) }}</td>
            </tr>
        @empty
            <tr><td colspan="6">No obligations.</td></tr>
        @endforelse
        </tbody>
    </table>

    <h2>Payment History</h2>
    <table class="data">
        <thead><tr><th>Receipt</th><th>Date</th><th>Type</th><th>Mode</th><th>Reference</th><th class="right">Amount</th></tr></thead>
        <tbody>
        @forelse($payments as $p)
            <tr>
                <td>{{ $p->receipt_number }}</td>
                <td>{{ $p->paid_on->format('d-m-Y') }}</td>
                <td>{{ $p->credit_used > 0 ? '₹'.number_format($p->credit_used, 2) : '-' }}</td>
                <td>{{ strtoupper($p->mode) }}</td>
                <td>{{ $p->reference_number ?? '—' }}</td>
                <td class="right">{{ hostelease_money($p->amount) }}</td>
            </tr>
        @empty
            <tr><td colspan="6">No payments.</td></tr>
        @endforelse
        </tbody>
    </table>
</body>
</html>

