{{--
    Subscription invoice (W12) — the PLATFORM (HostelEase / SatvScript) billing
    a CUSTOMER (an account owner) for their branches. Distinct from the student
    payment receipt: seller/buyer blocks, per-branch coverage lines, GST-ready.

    DomPDF, not a browser: tables + floats only, literal hex (indigo #4f46e5 =
    --he-primary), DejaVu Sans for the ₹ glyph. Status drives the doctype —
    paid = TAX INVOICE, else PROFORMA.
--}}
@php
    $statusValue = $order->payment_status->value;
    $paid = $statusValue === 'paid';
    $doctype = $paid ? 'TAX INVOICE' : 'PROFORMA INVOICE';
    $owner = $account->owner;
    $words = hostelease_amount_words($order->amount);
    $monogram = strtoupper(mb_substr($company['name'] ?? 'H', 0, 1));
    $statusHex = $paid ? ['#065f46', '#d1fae5'] : ($statusValue === 'pending' ? ['#92400e', '#fef3c7'] : ['#7f1d1d', '#fee2e2']);
    // Seller address as one clean string — avoids brittle nested inline @if/@endif.
    $sellerAddr = implode(', ', array_filter([$company['address'], $company['city'], $company['state']]));
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $order->invoiceNumber() }}</title>
    <style>
        @page { margin: 0; }
        * { font-family: "DejaVu Sans", sans-serif; }
        body { margin: 0; padding: 0; color: #111827; font-size: 11px; line-height: 1.5; }

        .band { background: #4f46e5; color: #ffffff; padding: 26px 34px 22px 34px; }
        .band-rule { height: 3px; background: #818cf8; font-size: 0; }
        .co-name { font-size: 18px; font-weight: bold; color: #ffffff; }
        .band-sub { color: #c7d2fe; font-size: 10px; }
        .monogram {
            width: 42px; height: 42px; background: #ffffff; border-radius: 12px;
            text-align: center; color: #4f46e5; font-size: 20px; font-weight: bold; line-height: 42px;
        }
        .doctype { color: #c7d2fe; font-size: 10px; letter-spacing: 2px; font-weight: bold; }
        .inv-no { color: #ffffff; font-size: 15px; font-weight: bold; }
        .status-pill {
            background: #ffffff; color: {{ $statusHex[0] }}; border-radius: 20px;
            padding: 4px 12px; font-size: 10px; font-weight: bold; letter-spacing: 1px;
        }

        .sheet { padding: 24px 34px 0 34px; }
        .label { color: #6b7280; font-size: 9px; letter-spacing: 1px; font-weight: bold; text-transform: uppercase; }
        .muted { color: #6b7280; }
        .strong { font-weight: bold; color: #111827; }
        .right { text-align: right; }

        .card { border: 1px solid #e5e7eb; border-radius: 10px; padding: 12px 14px; }
        .card-name { font-size: 13px; font-weight: bold; color: #111827; }

        .items { width: 100%; border-collapse: collapse; margin-top: 6px; }
        .items th {
            text-align: left; padding: 7px 10px; background: #f3f4f6; color: #4b5563;
            font-size: 9px; letter-spacing: 1px; text-transform: uppercase; border-bottom: 1px solid #e5e7eb;
        }
        .items td { padding: 9px 10px; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
        .items .num { text-align: right; font-weight: bold; }

        .meta-tbl td { padding: 3px 0; }
        .meta-k { color: #6b7280; font-size: 9px; letter-spacing: 1px; text-transform: uppercase; }
        .meta-v { font-weight: bold; color: #111827; text-align: right; }

        .total-box { background: #eef2ff; border: 1px solid #c7d2fe; border-radius: 10px; padding: 14px 16px; }
        .total-amount { font-size: 22px; font-weight: bold; color: #3730a3; }
        .words { color: #4338ca; font-size: 10px; font-style: italic; }

        .footer {
            position: fixed; bottom: 0; left: 0; right: 0;
            padding: 10px 34px; border-top: 1px solid #e5e7eb; color: #9ca3af; font-size: 9px;
        }
    </style>
</head>
<body>

    {{-- ══ Masthead: the SELLER (platform) ══ --}}
    <div class="band">
        <table width="100%" cellpadding="0" cellspacing="0"><tr>
            <td width="54" valign="top"><div class="monogram">{{ $monogram }}</div></td>
            <td valign="top">
                <div class="co-name">{{ $company['name'] }}</div>
                <div class="band-sub">
                    {{ $company['legal_name'] ? $company['legal_name'].' · ' : '' }}{{ $company['tagline'] }}<br>
                    @if($sellerAddr){{ $sellerAddr }}<br>@endif
                    {{ $company['email'] }}@if($company['website']) · {{ $company['website'] }}@endif
                    @if($company['gstin'])<br>GSTIN: {{ $company['gstin'] }}@endif
                </div>
            </td>
            <td valign="top" class="right" width="180">
                <div class="doctype">{{ $doctype }}</div>
                <div class="inv-no">{{ $order->invoiceNumber() }}</div>
                <div style="margin-top: 8px;"><span class="status-pill">{{ strtoupper($order->payment_status->label()) }}</span></div>
            </td>
        </tr></table>
    </div>
    <div class="band-rule"></div>

    <div class="sheet">

        {{-- ══ Billed-to (customer) + invoice meta ══ --}}
        <table width="100%" cellpadding="0" cellspacing="0"><tr>
            <td width="52%" valign="top">
                <div class="card">
                    <div class="label">Billed To</div>
                    <div class="card-name" style="margin-top: 3px;">{{ $owner?->name ?? 'Account #'.$account->id }}</div>
                    <div class="muted" style="margin-top: 2px;">
                        @if($owner?->mobile){{ hostelease_phone($owner->mobile) }}@endif
                        @if($owner?->email)<br>{{ $owner->email }}@endif
                    </div>
                </div>
            </td>
            <td width="16">&nbsp;</td>
            <td valign="top">
                <div class="card">
                    <table width="100%" class="meta-tbl" cellpadding="0" cellspacing="0">
                        <tr><td class="meta-k">Invoice Date</td><td class="meta-v">{{ $order->created_at?->format('d M Y') ?? '—' }}</td></tr>
                        <tr><td class="meta-k">Billing Term</td><td class="meta-v">{{ $order->period?->label() ?? '—' }}</td></tr>
                        <tr><td class="meta-k">Branches</td><td class="meta-v">{{ $order->quantity }}</td></tr>
                        <tr><td class="meta-k">Method</td><td class="meta-v">{{ $order->payment_method?->label() ?? '—' }}</td></tr>
                        @if($order->transaction_number)<tr><td class="meta-k">Txn / Ref</td><td class="meta-v">{{ $order->transaction_number }}</td></tr>@endif
                    </table>
                </div>
            </td>
        </tr></table>

        {{-- ══ Line items: per-branch coverage (the unique part) ══ --}}
        <div style="margin-top: 20px;">
            <div class="label">Subscription Coverage</div>
            <table class="items">
                <tr>
                    <th>Branch</th>
                    <th width="170">Coverage Period</th>
                    <th width="95" style="text-align: right;">Amount</th>
                </tr>
                @forelse($order->lines as $line)
                    <tr>
                        <td><span class="strong">{{ $line->branch?->name ?? 'Branch #'.$line->branch_id }}</span></td>
                        <td class="muted">{{ optional($line->start_date)->format('d M Y') ?? '—' }} — {{ optional($line->end_date)->format('d M Y') ?? '—' }}</td>
                        <td class="num">{{ hostelease_money($line->amount) }}</td>
                    </tr>
                @empty
                    {{-- Legacy / migrated orders may carry no lines — bill the whole
                         order as one row rather than printing an empty table. --}}
                    <tr>
                        <td><span class="strong">{{ $order->quantity }} branch subscription</span> <span class="muted">· {{ $order->period?->label() }}</span></td>
                        <td class="muted">—</td>
                        <td class="num">{{ hostelease_money($order->subtotal) }}</td>
                    </tr>
                @endforelse
            </table>
        </div>

        {{-- ══ Totals ══ --}}
        <table width="100%" cellpadding="0" cellspacing="0" style="margin-top: 18px;"><tr>
            <td valign="top">
                @if($order->remarks)
                    <div class="label">Notes</div>
                    <div class="muted" style="margin-top: 3px;">{{ $order->remarks }}</div>
                @endif
            </td>
            <td width="16">&nbsp;</td>
            <td width="52%" valign="top">
                <div class="total-box">
                    <table width="100%" cellpadding="0" cellspacing="0">
                        <tr><td class="muted">Subtotal</td><td class="right strong">{{ hostelease_money($order->subtotal) }}</td></tr>
                        @if((float) $order->discount_total > 0)
                            <tr><td style="color:#047857;">Discount</td><td class="right strong" style="color:#047857;">− {{ hostelease_money($order->discount_total) }}</td></tr>
                        @endif
                        <tr><td colspan="2" style="border-top:1px solid #c7d2fe; font-size:0; line-height:0; padding-top:6px;">&nbsp;</td></tr>
                        <tr>
                            <td class="label" valign="bottom" style="color: #4338ca;">{{ (float) $order->amount == 0 ? 'Complimentary' : 'Total' }}</td>
                            <td class="right total-amount">{{ hostelease_money($order->amount) }}</td>
                        </tr>
                    </table>
                    @if($words)<div class="words" style="margin-top: 6px;">{{ $words }}</div>@endif
                </div>
            </td>
        </tr></table>

        @unless($paid)
            <div style="margin-top: 16px; border: 1px dashed #d1d5db; border-radius: 8px; padding: 10px 12px; color: #6b7280;">
                This is a proforma invoice — <span class="strong">not a proof of payment</span>. A tax invoice is issued once payment is received.
            </div>
        @endunless

    </div>

    <div class="footer">
        <table width="100%" cellpadding="0" cellspacing="0"><tr>
            <td>Computer-generated invoice — valid without a physical signature.</td>
            <td class="right">{{ $company['name'] }} · {{ $order->invoiceNumber() }}</td>
        </tr></table>
    </div>

</body>
</html>
