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

Please contact us to renew and avoid any interruption to your hostel operations.

@elseif($kind === 'grace')
# Your {{ $isTrial ? 'trial' : 'subscription' }} has expired

Dear {{ $account->owner?->name }},

Your {{ $isTrial ? 'trial' : 'subscription' }} ended on **{{ $account->current_period_end->format('d M Y') }}**. You're currently in a short grace period and your hostel(s) are still accessible, but access will be blocked soon if the account isn't renewed.

Please contact us as soon as possible to renew and avoid any interruption.

@else
# Your {{ $isTrial ? 'trial' : 'subscription' }} has expired

Dear {{ $account->owner?->name }},

Your {{ $isTrial ? 'trial' : 'subscription' }} ended on **{{ $account->current_period_end->format('d M Y') }}** and access to your hostel(s) is now blocked.

Please contact us to renew and restore access.
@endif

Thank you,<br>
**Hostel Ease**
@endcomponent
