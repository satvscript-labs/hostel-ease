{{-- Sign-up, step 1 of 2: your details. Nothing is created yet — the next step emails
     a 6-digit code, and the account exists only once it is entered
     (App\Services\Auth\SignupVerification). --}}
<x-guest-shell :title="__('Create your account')" :step="1"
               :heading="__('Set up your hostel')"
               :sub="__('Your first branch starts with a 14-day free trial. No card needed.')">

    <form method="POST" action="{{ route('register.attempt') }}" x-data="{ busy: false }" @submit="busy = true" novalidate>
        @csrf

        <x-guest.field name="name" :label="__('Your name')" :value="old('name')"
                       autocomplete="name" :placeholder="__('Ramesh Patel')" maxlength="120" autofocus />

        <x-guest.field name="hostel_name" :label="__('Hostel or PG name')" :value="old('hostel_name')"
                       autocomplete="organization" :placeholder="__('Sunrise Boys Hostel')" maxlength="120" />

        <x-guest.field name="mobile" :label="__('Mobile number')" type="tel" prefix="+91" :value="old('mobile')"
                       inputmode="numeric" maxlength="10" autocomplete="tel-national" :placeholder="__('98765 43210')"
                       :hint="__('You will sign in with this number.')" />

        <x-guest.field name="email" :label="__('Email')" type="email" :value="old('email')"
                       autocomplete="email" inputmode="email" :placeholder="__('you@example.com')" maxlength="150"
                       :hint="__('We will send a code here to confirm it is yours.')" />

        <x-guest.field name="password" :label="__('Password')" type="password" autocomplete="new-password"
                       minlength="8" :hint="__('At least 8 characters.')" />

        <button type="submit" class="g-btn mt-2" :disabled="busy">
            <span x-show="!busy">{{ __('Continue') }}</span>
            <span x-show="busy" x-cloak class="g-spinner" aria-hidden="true"></span>
            <span x-show="busy" x-cloak>{{ __('Sending your code…') }}</span>
        </button>

        <p class="g-quiet small mt-3 mb-0">
            {{ __('By continuing you agree to the') }}
            <a href="{{ route('terms') }}" target="_blank" rel="noopener" class="g-link">{{ __('Terms') }}</a>
            {{ __('and') }}
            <a href="{{ route('privacy') }}" target="_blank" rel="noopener" class="g-link">{{ __('Privacy Policy') }}</a>.
        </p>
    </form>

    <p class="g-quiet text-center mt-4 mb-0">
        {{ __('Already have an account?') }}
        <a href="{{ route('login') }}" class="g-link">{{ __('Sign in') }}</a>
    </p>

</x-guest-shell>
