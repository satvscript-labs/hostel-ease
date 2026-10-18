{{-- Autopay notices (S6). Markdown mail: keep lines un-indented. --}}
@component('mail::message')
@if($kind === 'debiting')
# {{ __(':amount will be debited automatically', ['amount' => $amount]) }}

{{ __('Hi :name,', ['name' => $name]) }}

{{ __('Your monthly renewal is due on :date. We have asked your bank to take :amount by UPI Autopay — your UPI app will show a notice about 24 hours before the money is taken.', ['date' => $renewsOn, 'amount' => $amount]) }}

{{ __('Nothing to do. If you would rather not pay this way, turn autopay off on your Subscription page.') }}
@elseif($kind === 'over_limit')
# {{ __('This month is above your autopay limit') }}

{{ __('Hi :name,', ['name' => $name]) }}

{{ __('Your renewal of :amount (due :date) is above the limit your autopay was set up with, so nothing was debited. Please pay it on your Subscription page.', ['amount' => $amount, 'date' => $renewsOn]) }}

@component('mail::button', ['url' => $payUrl])
{{ __('Pay :amount', ['amount' => $amount]) }}
@endcomponent
@else
# {{ __('Autopay could not take your payment') }}

{{ __('Hi :name,', ['name' => $name]) }}

{{ __('We tried to take :amount for your renewal due :date, but your bank did not approve it. Please pay it on your Subscription page to keep every branch running.', ['amount' => $amount, 'date' => $renewsOn]) }}

@component('mail::button', ['url' => $payUrl])
{{ __('Pay :amount', ['amount' => $amount]) }}
@endcomponent
@endif

{{ __('Thank you,') }}<br>
**{{ config('app.name', 'HostelEase') }}**
@endcomponent
