{{-- W10: the honest target of "Forgot password?" — no self-serve reset exists
     yet (OTP deferred, pending_requirements #1), so this explains the two real
     recovery paths instead of pretending. Upgrades into an OTP form when an
     SMS provider is chosen. --}}
<x-guest-shell :title="__('Recover access')" :heading="__('Locked out?')" :sub="__('Here is how to get back into your account.')">

    <div class="d-flex flex-column gap-3 mb-4">
        {{-- Staff path --}}
        <div class="d-flex align-items-start gap-3 p-3 rounded-3" style="border:1px solid rgba(0,0,0,.07); background:var(--he-bg-canvas,#f8fafc);">
            <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width:44px; height:44px; background:var(--he-primary-soft, rgba(79,70,229,.1)); color:var(--he-primary,#4f46e5);">
                <i class="fa-solid fa-user-group"></i>
            </div>
            <div>
                <div class="fw-bold" style="color:var(--he-text-main,#0f172a);">{{ __('Staff member?') }}</div>
                <div class="small text-muted">{{ __('Your hostel owner can reset your password from their Settings → Users & Roles in seconds. Ask them for a new one.') }}</div>
            </div>
        </div>

        {{-- Owner path --}}
        <div class="d-flex align-items-start gap-3 p-3 rounded-3" style="border:1px solid rgba(0,0,0,.07); background:var(--he-bg-canvas,#f8fafc);">
            <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width:44px; height:44px; background:var(--he-accent-soft, rgba(147,51,234,.1)); color:var(--he-accent,#9333ea);">
                <i class="fa-solid fa-shield-halved"></i>
            </div>
            <div>
                <div class="fw-bold" style="color:var(--he-text-main,#0f172a);">{{ __('Hostel owner?') }}</div>
                <div class="small text-muted">{{ __('Contact HostelEase support with your registered mobile number — we will verify it is you and reset your access.') }}</div>
            </div>
        </div>
    </div>

    <a href="mailto:{{ config('mail.from.address', 'support@hostelease.app') }}?subject={{ rawurlencode(__('Account recovery request')) }}" class="guest-btn mb-4 text-decoration-none">
        <i class="fa-solid fa-headset"></i> {{ __('Contact support') }}
    </a>

    <div class="text-center">
        <a href="{{ route('login') }}" class="small guest-link"><i class="fa-solid fa-arrow-left me-1"></i>{{ __('Back to login') }}</a>
    </div>

</x-guest-shell>
