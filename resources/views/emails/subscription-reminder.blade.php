{{-- Renewal reminder (S4 · 24_S4_DESIGN.md §5). Every figure comes from
     RenewalReminders::context() ($c). Markdown mail: keep lines un-indented, or
     they render as code blocks. --}}
@php
    $noun = $c['is_trial'] ? __('free trial') : __('plan');
    $renews = $c['renews_on']->translatedFormat('d M Y');
    $graceUntil = $c['grace_until']->translatedFormat('d M Y');
    $pausesOn = $c['pauses_on']->translatedFormat('d M Y');
    $waiting = $c['waiting_amount'] !== null;
    $cta = match (true) {
        $waiting && $c['pay_url'] !== null => __('Pay :amount', ['amount' => hostelease_money($c['waiting_amount'])]),
        $c['self_serve'] => $kind === 'expired' ? __('Renew and restore access') : __('Review & renew'),
        default => null,
    };
@endphp
@component('mail::message')
@if($kind === 'upcoming')
# {{ $daysUntil <= 0 ? __('Your :noun renews today', ['noun' => $noun]) : trans_choice('Your :noun renews in :n day|Your :noun renews in :n days', $daysUntil, ['noun' => $noun, 'n' => $daysUntil]) }}
@elseif($kind === 'grace')
# {{ __('Your :noun has ended', ['noun' => $noun]) }}
@else
# {{ __('Your :noun has expired', ['noun' => $noun]) }}
@endif

{{ __('Hi :name,', ['name' => $account->owner?->name ?? __('there')]) }}

@if($kind === 'upcoming')
{{ __('Every branch renews together on :date.', ['date' => $renews]) }}
@elseif($kind === 'grace')
{{ __('It ended on :date. Your branches keep working until :until — after that, access pauses until it is renewed.', ['date' => $renews, 'until' => $graceUntil]) }}
@else
{{ __('It ended on :date and access to your branches is paused. Your data is safe — renewing restores everything straight away.', ['date' => $renews]) }}
@endif

@component('mail::panel')
@if($waiting)
**{{ __('Amount due') }}:** {{ hostelease_money($c['waiting_amount']) }}
@elseif($c['free'])
**{{ __('Amount due') }}:** {{ __('Nothing — your next renewal is free.') }}
@elseif($c['is_trial'])
**{{ __('To continue') }}:** {{ hostelease_money($c['amounts']['yearly']) }} {{ __('a year') }} · {{ hostelease_money($c['amounts']['monthly']) }} {{ __('a month') }}
@else
**{{ __('Amount') }}:** {{ hostelease_money($c['amount']) }} · {{ trans_choice(':n branch|:n branches', $c['branches'], ['n' => $c['branches']]) }} · {{ __(ucfirst($c['term'])) }}
@endif

**{{ __('Renewal date') }}:** {{ $renews }}
@if($kind !== 'expired')

**{{ __('Access pauses on') }}:** {{ $pausesOn }} {{ __('if not renewed') }}
@endif
@endcomponent

@if($cta)
@component('mail::button', ['url' => $c['pay_url']])
{{ $cta }}
@endcomponent
@if($c['link_url'])
{{ __('This is a secure Razorpay payment link — UPI, cards and netbanking all work.') }}
@endif
@else
{{ __('Our team will be in touch to renew it for you. If you would like to talk to us first, write to :email.', ['email' => $supportEmail]) }}
@endif

{{ __('Thank you,') }}<br>
**{{ config('app.name', 'HostelEase') }}**
@endcomponent
