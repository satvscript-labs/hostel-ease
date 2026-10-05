{{-- The honest target of "Forgot password?" (W10): no self-serve reset exists yet, so
     this explains the two real ways back in instead of pretending. Restyled for the
     redesigned <x-guest-shell>; behaviour unchanged. --}}
<x-guest-shell :title="__('Recover access')" :heading="__('Locked out?')" :sub="__('Here is how to get back into your account.')">

    @push('head')
    <style>
        .g-path { display: flex; gap: 1rem; align-items: flex-start; padding: 1.1rem 0; border-bottom: 1px solid var(--g-line); }
        .g-path:first-child { padding-top: 0; }
        .g-path-ic { width: 42px; height: 42px; border-radius: 11px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; }
        .g-path-title { font-weight: 700; margin-bottom: 0.2rem; }
        .g-path-text { color: var(--g-muted); font-size: 0.92rem; line-height: 1.55; }
    </style>
    @endpush

    <div class="mb-4">
        <div class="g-path">
            <div class="g-path-ic" style="background: color-mix(in srgb, var(--g-brand) 10%, #fff); color: var(--g-brand);"><i class="fa-solid fa-user-group"></i></div>
            <div>
                <div class="g-path-title">{{ __('Staff member?') }}</div>
                <div class="g-path-text">{{ __('Your hostel owner can reset your password in seconds from Settings → Users & Roles. Ask them for a new one.') }}</div>
            </div>
        </div>
        <div class="g-path">
            <div class="g-path-ic" style="background: color-mix(in srgb, var(--g-brand-2) 10%, #fff); color: var(--g-brand-2);"><i class="fa-solid fa-shield-halved"></i></div>
            <div>
                <div class="g-path-title">{{ __('Hostel owner?') }}</div>
                <div class="g-path-text">{{ __('Contact HostelEase support from your registered mobile number or email. We will confirm it is you and reset your access.') }}</div>
            </div>
        </div>
    </div>

    <a href="mailto:{{ config('hostelease.company.email', config('mail.from.address')) }}?subject={{ rawurlencode(__('Account recovery request')) }}" class="g-btn">
        <i class="fa-solid fa-headset"></i> {{ __('Contact support') }}
    </a>

    <p class="g-quiet text-center mt-4 mb-0">
        <a href="{{ route('login') }}" class="g-link">{{ __('Back to sign in') }}</a>
    </p>

</x-guest-shell>
