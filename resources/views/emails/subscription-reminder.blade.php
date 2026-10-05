@component('mail::message')
@if($kind === 'upcoming')
# {{ $isTrial ? 'Your free trial is ending soon' : 'Your subscription renews soon' }}

Dear {{ $account->owner?->name }},

@if($daysUntil <= 0)
Your {{ $isTrial ? 'trial' : 'subscription' }} renews **today** ({{ $account->current_period_end->format('d M Y') }}).
@else
Your {{ $isTrial ? 'trial' : 'subscription' }} renews in **{{ $daysUntil }} day(s)**, on **{{ $account->current_period_end->format('d M Y') }}**.
@endif

@component('mail::panel')
**Branches on this account:** {{ $account->owner?->accessibleHostelIds() ? count($account->owner->accessibleHostelIds()) : '—' }}
**Renewal date:** {{ $account->current_period_end->format('d M Y') }}
@endcomponent

@if($selfServe)
Renew from your Subscription page — every branch renews together, in one payment.

@component('mail::button', ['url' => $renewUrl])
Renew now
@endcomponent
@else
Our team will be in touch to renew it for you. If you would like to talk to us first, write to {{ $supportEmail }}.
@endif

@elseif($kind === 'grace')
# Your {{ $isTrial ? 'trial' : 'subscription' }} has expired

Dear {{ $account->owner?->name }},

Your {{ $isTrial ? 'trial' : 'subscription' }} ended on **{{ $account->current_period_end->format('d M Y') }}**. You're currently in a short grace period and your hostel(s) are still accessible, but access will be blocked soon if the account isn't renewed.

@if($selfServe)
Renew now to keep every branch running.

@component('mail::button', ['url' => $renewUrl])
Renew now
@endcomponent
@else
Please contact us at {{ $supportEmail }} as soon as possible so we can renew it and avoid any interruption.
@endif

@else
# Your {{ $isTrial ? 'trial' : 'subscription' }} has expired

Dear {{ $account->owner?->name }},

Your {{ $isTrial ? 'trial' : 'subscription' }} ended on **{{ $account->current_period_end->format('d M Y') }}** and access to your hostel(s) is now blocked.

@if($selfServe)
Renew to restore access straight away.

@component('mail::button', ['url' => $renewUrl])
Renew now
@endcomponent
@else
Please contact us at {{ $supportEmail }} to renew and restore access.
@endif
@endif

Thank you,<br>
**Hostel Ease**
@endcomponent
