{{-- Login, on the redesigned <x-guest-shell>. Mobile number is the login across the
     whole product (staff included, many without an email), so it stays the one
     identifier here. --}}
<x-guest-shell :title="__('Sign in')" :heading="__('Welcome back')" :sub="__('Sign in with the mobile number you registered with.')">

    @if(session('status'))
        <div class="g-note g-note--ok" role="status"><i class="fa-solid fa-circle-check"></i><div>{{ session('status') }}</div></div>
    @endif
    @if(session('error'))
        <div class="g-note g-note--bad" role="alert"><i class="fa-solid fa-circle-exclamation"></i><div>{{ session('error') }}</div></div>
    @endif
    {{-- A failed sign-in is about the PAIR, not the mobile field — so it sits above
         the form instead of under one input. --}}
    @error('credentials')
        <div class="g-note g-note--bad" role="alert"><i class="fa-solid fa-circle-exclamation"></i><div>{{ $message }}</div></div>
    @enderror

    <form method="POST" action="{{ route('login.attempt') }}" x-data="{ busy: false }" @submit="busy = true" novalidate>
        @csrf

        <x-guest.field name="mobile" :label="__('Mobile number')" type="tel" prefix="+91"
                       :value="old('mobile')" inputmode="numeric" maxlength="14" autocomplete="tel-national"
                       :placeholder="__('98765 43210')" autofocus />

        <x-guest.field name="password" :label="__('Password')" type="password" autocomplete="current-password" />

        <div class="d-flex justify-content-between align-items-center mb-4 mt-n1">
            <label class="d-flex align-items-center gap-2 m-0 g-quiet" style="cursor:pointer;">
                <input class="form-check-input m-0" type="checkbox" name="remember" value="1" @checked(old('remember'))>
                {{ __('Keep me signed in') }}
            </label>
            <a href="{{ route('recover') }}" class="g-link small">{{ __('Forgot password?') }}</a>
        </div>

        <button type="submit" class="g-btn" :disabled="busy">
            <span x-show="!busy">{{ __('Sign in') }}</span>
            <span x-show="busy" x-cloak class="g-spinner" aria-hidden="true"></span>
            <span x-show="busy" x-cloak>{{ __('Signing in…') }}</span>
        </button>
    </form>

    <p class="g-quiet text-center mt-4 mb-0">
        {{ __('New to :app?', ['app' => config('app.name', 'HostelEase')]) }}
        <a href="{{ route('register') }}" class="g-link">{{ __('Create an account') }}</a>
    </p>

</x-guest-shell>
