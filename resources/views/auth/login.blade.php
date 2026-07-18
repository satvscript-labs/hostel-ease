{{-- W10: the shell (head, split visual, locale footer, PWA) is <x-guest-shell>;
     this page is now only the login form. Was a 300-line near-duplicate of
     register. --}}
<x-guest-shell :title="__('Login')" :heading="__('Welcome back')" :sub="__('Enter your credentials to access your dashboard.')">

    @if(session('error'))
        <div class="guest-alert"><i class="fa-solid fa-circle-exclamation mt-1"></i><div>{{ session('error') }}</div></div>
    @endif
    @if($errors->any())
        <div class="guest-alert"><i class="fa-solid fa-circle-exclamation mt-1"></i><div>{{ $errors->first() }}</div></div>
    @endif

    <form method="POST" action="{{ route('login.attempt') }}">
        @csrf

        <div class="guest-field">
            <label class="guest-label">{{ __('Mobile Number') }}</label>
            <div class="d-flex gap-2">
                <div class="guest-prefix">+91</div>
                <div class="guest-input-wrap flex-grow-1">
                    <input type="tel" name="mobile" value="{{ old('mobile') }}" class="guest-input"
                           inputmode="numeric" maxlength="10" placeholder="{{ __('10-digit mobile number') }}" required autofocus>
                    <i class="fa-solid fa-mobile-screen guest-input-ic"></i>
                </div>
            </div>
        </div>

        <div class="guest-field">
            <label class="guest-label">{{ __('Password') }}</label>
            <div class="guest-input-wrap" x-data="{ show: false }">
                <input :type="show ? 'text' : 'password'" name="password" class="guest-input" placeholder="••••••••" required autocomplete="current-password">
                <button type="button" class="guest-input-ic border-0 bg-transparent" style="pointer-events:auto; cursor:pointer;" @click="show = !show" tabindex="-1" aria-label="{{ __('Show password') }}">
                    <i class="fa-solid" :class="show ? 'fa-eye-slash' : 'fa-eye'"></i>
                </button>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <label class="d-flex align-items-center gap-2 m-0" style="cursor:pointer;">
                <input class="form-check-input m-0" type="checkbox" name="remember" id="remember">
                <span class="small fw-semibold text-muted">{{ __('Remember me') }}</span>
            </label>
            <a href="{{ route('recover') }}" class="small guest-link">{{ __('Forgot password?') }}</a>
        </div>

        <button type="submit" class="guest-btn mb-4">
            {{ __('Sign in to dashboard') }} <i class="fa-solid fa-arrow-right"></i>
        </button>

        <div class="text-center">
            <span class="text-muted small">{{ __("Don't have an account?") }}</span>
            <a href="{{ route('register') }}" class="small guest-link ms-1">{{ __('Sign up for free') }}</a>
        </div>
    </form>

</x-guest-shell>
