{{-- W10: on <x-guest-shell>. Was a near-exact copy of login's 300-line shell. --}}
<x-guest-shell :title="__('Sign up')" :heading="__('Start automating today')" :sub="__('Create your free account — no credit card required.')">

    @if($errors->any())
        <div class="guest-alert"><i class="fa-solid fa-circle-exclamation mt-1"></i><div>{{ $errors->first() }}</div></div>
    @endif

    <form method="POST" action="{{ route('register.attempt') }}">
        @csrf

        <div class="guest-field">
            <label class="guest-label">{{ __('Your Name') }}</label>
            <div class="guest-input-wrap">
                <input type="text" name="name" value="{{ old('name') }}" class="guest-input" placeholder="{{ __('e.g. John Doe') }}" required autofocus>
                <i class="fa-solid fa-user guest-input-ic"></i>
            </div>
        </div>

        <div class="guest-field">
            <label class="guest-label">{{ __('Hostel / PG Name') }}</label>
            <div class="guest-input-wrap">
                <input type="text" name="hostel_name" value="{{ old('hostel_name') }}" class="guest-input" placeholder="{{ __('e.g. Skyline PG Accommodations') }}" required>
                <i class="fa-solid fa-building guest-input-ic"></i>
            </div>
        </div>

        <div class="guest-field">
            <label class="guest-label">{{ __('Mobile Number') }}</label>
            <div class="d-flex gap-2">
                <div class="guest-prefix">+91</div>
                <div class="guest-input-wrap flex-grow-1">
                    <input type="tel" name="mobile" value="{{ old('mobile') }}" class="guest-input"
                           inputmode="numeric" maxlength="10" placeholder="{{ __('10-digit mobile number') }}" required>
                    <i class="fa-solid fa-mobile-screen guest-input-ic"></i>
                </div>
            </div>
        </div>

        <div class="guest-field">
            <label class="guest-label">{{ __('Password') }}</label>
            <div class="guest-input-wrap" x-data="{ show: false }">
                <input :type="show ? 'text' : 'password'" name="password" class="guest-input" placeholder="••••••••" required autocomplete="new-password" minlength="6">
                <button type="button" class="guest-input-ic border-0 bg-transparent" style="pointer-events:auto; cursor:pointer;" @click="show = !show" tabindex="-1" aria-label="{{ __('Show password') }}">
                    <i class="fa-solid" :class="show ? 'fa-eye-slash' : 'fa-eye'"></i>
                </button>
            </div>
        </div>

        {{-- Sets the production expectation at the right moment (owner decision):
             the first branch is instant; further branches are set up with the
             team — so signup and the owner_self_serve lock stop contradicting. --}}
        <div class="d-flex align-items-start gap-2 mb-3 p-3 rounded-3" style="background: var(--he-primary-soft, rgba(79,70,229,.08));">
            <i class="fa-solid fa-circle-info mt-1" style="color: var(--he-primary);"></i>
            <div class="small" style="color: var(--he-primary-hover, #4338ca);">{{ __('Your first branch and a 14-day free trial start instantly. Need more branches? Our team sets those up with you.') }}</div>
        </div>

        <p class="small text-muted mb-4">{{ __('By signing up you agree to our') }}
            <a href="{{ route('terms') }}" target="_blank" class="guest-link">{{ __('Terms') }}</a> {{ __('and') }}
            <a href="{{ route('privacy') }}" target="_blank" class="guest-link">{{ __('Privacy Policy') }}</a>.
        </p>

        <button type="submit" class="guest-btn mb-4">
            {{ __('Create account') }} <i class="fa-solid fa-rocket"></i>
        </button>

        <div class="text-center">
            <span class="text-muted small">{{ __('Already have an account?') }}</span>
            <a href="{{ route('login') }}" class="small guest-link ms-1">{{ __('Log in here') }}</a>
        </div>
    </form>

</x-guest-shell>
