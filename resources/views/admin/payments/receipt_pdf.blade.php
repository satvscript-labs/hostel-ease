{{--
    Payment receipt (W6.1 redesign).

    Rendered by DomPDF — which is NOT a browser. No flexbox, no grid, no CSS
    custom properties, no color-mix: layout is tables and floats, and every
    brand colour is a literal hex (the indigo below is --he-primary #4f46e5;
    the old file used #2563eb, a blue this product doesn't own). Fonts are
    pinned to DejaVu Sans because it is the only bundled face carrying the ₹
    glyph — swap it and the rupee sign turns into a box.

    One template, two consumers: the admin PDF download and the emailed
    attachment. Anything added here shows up in both. (An Api\PaymentController
    used to render it too — dead code from a never-shipped app, deleted W6.2.)
--}}
@php
    $room = $payment->student->activeAssignment?->bed?->room?->room_number;
    $words = hostelease_amount_words($payment->amount);
    $applied = $payment->invoices;
    $monogram = strtoupper(mb_substr($payment->hostel->name ?? '?', 0, 1));
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $payment->receipt_number }}</title>
    <style>
        @page { margin: 0; }
        * { font-family: "DejaVu Sans", sans-serif; }
        body { margin: 0; padding: 0; color: #111827; font-size: 11px; line-height: 1.5; }

        /* Full-bleed masthead. Needs @page margin 0 + body padding 0 to reach
           the paper edge — DomPDF won't bleed past a page margin. */
        .band { background: #4f46e5; color: #ffffff; padding: 26px 34px 22px 34px; }
        .band-rule { height: 3px; background: #818cf8; font-size: 0; }
        .hostel { font-size: 18px; font-weight: bold; color: #ffffff; }
        .band-sub { color: #c7d2fe; font-size: 10px; }
        .monogram {
            width: 42px; height: 42px; background: #ffffff; border-radius: 12px;
            text-align: center; color: #4f46e5; font-size: 20px; font-weight: bold;
            line-height: 42px;
        }
        .doctype { color: #c7d2fe; font-size: 10px; letter-spacing: 2px; font-weight: bold; }
        .receipt-no { color: #ffffff; font-size: 15px; font-weight: bold; }
        .paid-pill {
            background: #ffffff; color: #047857; border-radius: 20px;
            padding: 4px 12px; font-size: 10px; font-weight: bold; letter-spacing: 1px;
        }

        .sheet { padding: 26px 34px 0 34px; }
        .label { color: #6b7280; font-size: 9px; letter-spacing: 1px; font-weight: bold; text-transform: uppercase; }
        .muted { color: #6b7280; }
        .strong { font-weight: bold; color: #111827; }
        .right { text-align: right; }

        .card { border: 1px solid #e5e7eb; border-radius: 10px; padding: 12px 14px; }
        .card-name { font-size: 13px; font-weight: bold; color: #111827; }

        /* Line items: what this money actually settled. */
        .items { width: 100%; border-collapse: collapse; margin-top: 6px; }
        .items th {
            text-align: left; padding: 7px 10px; background: #f3f4f6; color: #4b5563;
            font-size: 9px; letter-spacing: 1px; text-transform: uppercase;
            border-bottom: 1px solid #e5e7eb;
        }
        .items td { padding: 9px 10px; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
        .items .num { text-align: right; font-weight: bold; }

        .note {
            border: 1px dashed #d1d5db; border-radius: 8px; padding: 10px 12px;
            color: #6b7280; margin-top: 6px;
        }

        .total-box { background: #eef2ff; border: 1px solid #c7d2fe; border-radius: 10px; padding: 14px 16px; }
        .total-amount { font-size: 22px; font-weight: bold; color: #3730a3; }
        .words { color: #4338ca; font-size: 10px; font-style: italic; }

        .sign-line { border-top: 1px solid #9ca3af; width: 170px; margin-left: auto; padding-top: 4px; }

        /* position: fixed prints on every page in DomPDF — the disclaimer must
           survive a receipt that spills onto a second page. */
        .footer {
            position: fixed; bottom: 0; left: 0; right: 0;
            padding: 10px 34px; border-top: 1px solid #e5e7eb;
            color: #9ca3af; font-size: 9px;
        }
    </style>
</head>
<body>

    {{-- ══ Masthead ══ --}}
    <div class="band">
        <table width="100%" cellpadding="0" cellspacing="0"><tr>
            <td width="54" valign="top"><div class="monogram">{{ $monogram }}</div></td>
            <td valign="top">
                <div class="hostel">{{ $payment->hostel->name }}</div>
                <div class="band-sub">
                    {{ $payment->hostel->address }}{{ $payment->hostel->city ? ', '.$payment->hostel->city : '' }}{{ $payment->hostel->state ? ', '.$payment->hostel->state : '' }}<br>
                    {{ hostelease_phone($payment->hostel->mobile) }}@if($payment->hostel->email) · {{ $payment->hostel->email }}@endif
                    @if($payment->hostel->gst_number)<br>GSTIN: {{ $payment->hostel->gst_number }}@endif
                </div>
            </td>
            <td valign="top" class="right" width="180">
                <div class="doctype">PAYMENT RECEIPT</div>
                <div class="receipt-no">{{ $payment->receipt_number }}</div>
                <div style="margin-top: 8px;"><span class="paid-pill">PAID</span></div>
            </td>
        </tr></table>
    </div>
    <div class="band-rule"></div>

    <div class="sheet">

        {{-- ══ Who paid / when ══ --}}
        <table width="100%" cellpadding="0" cellspacing="0"><tr>
            <td width="52%" valign="top">
                <div class="card">
                    <div class="label">Received From</div>
                    <div class="card-name" style="margin-top: 3px;">{{ $payment->student->name }}</div>
                    <div class="muted" style="margin-top: 2px;">
                        {{ hostelease_phone($payment->student->mobile) }}
                        @if($room)<br>Room {{ $room }}@endif
                    </div>
                </div>
            </td>
            <td width="16">&nbsp;</td>
            <td valign="top">
                <div class="card">
                    <div class="label">Payment Date</div>
                    <div class="card-name" style="margin-top: 3px;">{{ $payment->paid_on->format('d M Y') }}</div>
                    <div class="muted" style="margin-top: 2px;">
                        Mode: <span class="strong">{{ strtoupper(str_replace('_', ' ', $payment->mode)) }}</span>
                        @if($payment->reference_number)<br>Ref: {{ $payment->reference_number }}@endif
                    </div>
                </div>
            </td>
        </tr></table>

        {{-- ══ What it settled ══
             The old receipt never said what the money was FOR — it listed the
             mode and a number. This reads the invoice_payment pivot, so the
             student can see exactly which dues this cleared. --}}
        <div style="margin-top: 20px;">
            <div class="label">Applied To</div>
            @if($applied->isNotEmpty())
                <table class="items">
                    <tr>
                        <th>Invoice</th>
                        <th width="90">Due Date</th>
                        <th width="95" style="text-align: right;">Applied</th>
                    </tr>
                    @foreach($applied as $invoice)
                        <tr>
                            <td>
                                <span class="strong">{{ $invoice->title }}</span>
                                <span class="muted"> · #{{ $invoice->id }}</span>
                            </td>
                            <td class="muted">{{ $invoice->due_date?->format('d M Y') ?? '—' }}</td>
                            <td class="num">{{ hostelease_money($invoice->pivot->amount) }}</td>
                        </tr>
                    @endforeach
                </table>
            @else
                {{-- Advance payments and credit notes legitimately settle
                     nothing — say so rather than printing an empty table. --}}
                <div class="note">
                    Not applied to a specific invoice — held against this student's account
                    and adjusted automatically against future dues.
                </div>
            @endif
        </div>

        {{-- ══ Total ══ --}}
        <table width="100%" cellpadding="0" cellspacing="0" style="margin-top: 18px;"><tr>
            <td valign="top">
                @if($payment->remarks)
                    <div class="label">Remarks</div>
                    <div class="muted" style="margin-top: 3px;">{{ $payment->remarks }}</div>
                @endif
            </td>
            <td width="16">&nbsp;</td>
            <td width="52%" valign="top">
                <div class="total-box">
                    @if($payment->credit_used > 0)
                        <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 6px;">
                            <tr>
                                <td class="muted">Credit adjusted</td>
                                <td class="right strong">{{ hostelease_money($payment->credit_used) }}</td>
                            </tr>
                        </table>
                    @endif
                    <table width="100%" cellpadding="0" cellspacing="0">
                        <tr>
                            <td class="label" valign="bottom" style="color: #4338ca;">Amount Paid</td>
                            <td class="right total-amount">{{ hostelease_money($payment->amount) }}</td>
                        </tr>
                    </table>
                    @if($words)
                        <div class="words" style="margin-top: 6px;">{{ $words }}</div>
                    @endif
                </div>
            </td>
        </tr></table>

        {{-- ══ Sign-off ══ --}}
        <table width="100%" cellpadding="0" cellspacing="0" style="margin-top: 34px;"><tr>
            <td valign="bottom">
                <div class="label">Collected By</div>
                <div class="strong" style="margin-top: 3px;">{{ $payment->collector?->name ?? '—' }}</div>
            </td>
            <td width="200" valign="bottom">
                <div class="sign-line right muted">Authorised Signature</div>
            </td>
        </tr></table>

    </div>

    <div class="footer">
        <table width="100%" cellpadding="0" cellspacing="0"><tr>
            <td>Computer-generated receipt — valid without a physical signature.</td>
            <td class="right">{{ $payment->hostel->name }} · {{ $payment->receipt_number }}</td>
        </tr></table>
    </div>

</body>
</html>
