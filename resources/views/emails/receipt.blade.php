@component('mail::message')
# Payment Receipt

Dear {{ $payment->student->name }},

We have received your payment. The details are below, and a PDF copy is attached.

@component('mail::panel')
**Receipt No:** {{ $payment->receipt_number }}
**Amount:** {{ hostelease_money($payment->amount) }}
**Mode:** {{ strtoupper($payment->mode) }}
**Date:** {{ $payment->paid_on->format('d M Y') }}
@endcomponent

Thank you,<br>
**{{ $payment->hostel->name }}**
@endcomponent

