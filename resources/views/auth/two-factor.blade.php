{{-- Sign-in, step 2 (S7.1): the person's password was right; now the code. Nobody is
     signed in until this succeeds. Three ways through: the app code, the emailed code,
     or a one-time recovery code. --}}
@php
    $sub = $recovery
        ? e(__('Enter one of the recovery codes you saved when you turned on two-factor sign-in.'))
        : ($method === 'email'
            ? e(__('We sent a 6-digit code to')).' <strong>'.e($maskedEmail).'</strong>.'
            : e(__('Open your authenticator app and enter the 6-digit code for :app.', ['app' => config('app.name', 'HostelEase')])));
@endphp
<x-guest-shell :title="__('Enter your code')" :heading="$recovery ? __('Use a recovery code') : __('Enter your code')" :sub="$sub">

    @push('head')
    <style>
        .g-dev { border: 1.5px dashed #f59e0b; background: #fffbeb; color: #92400e; }
        .g-resend { font: inherit; font-size: 0.875rem; font-weight: 600; }
        .g-resend[disabled] { color: var(--g-muted); opacity: 1; text-decoration: none; cursor: default; }
        .g-recovery-input { text-transform: uppercase; letter-spacing: 0.12em; font-variant-numeric: tabular-nums; }
    </style>
    @endpush

    @if(session('status'))
        <div class="g-note g-note--ok" role="status"><i class="fa-solid fa-circle-check"></i><div>{{ session('status') }}</div></div>
    @endif
    @if($sendError && ! $recovery)
        <div class="g-note g-note--bad" role="alert"><i class="fa-solid fa-circle-exclamation"></i><div>{{ $sendError }}</div></div>
    @endif
    @if($devCode && $method === 'email' && ! $recovery)
        <div class="g-note g-dev"><i class="fa-solid fa-code"></i>
            <div>{{ __('Local development — mail is not set up on this machine, so here is the code:') }} <strong style="letter-spacing:2px;">{{ $devCode }}</strong></div>
        </div>
    @endif

    <form method="POST" action="{{ route('two-factor.verify') }}" x-data="{ busy: false }"
          @submit="busy = true" @otp-complete="if (!busy) { busy = true; $nextTick(() => $el.submit()); }" novalidate>
        @csrf
        <input type="hidden" name="recovery" value="{{ $recovery ? 1 : 0 }}">

        @if($recovery)
            <x-guest.field name="code" :label="__('Recovery code')" :placeholder="__('ABCD-2345')" autocomplete="off" maxlength="20" autofocus />
        @else
            <x-guest.otp name="code" :label="$method === 'email' ? __('Code from your email') : __('Code from your app')" :error="$errors->first('code')" />
        @endif

        <label class="d-flex align-items-start gap-2 my-3 g-quiet" style="cursor:pointer;">
            <input class="form-check-input mt-1" type="checkbox" name="trust" value="1" @checked(old('trust'))>
            <span>{{ __('Trust this device for :n days — do not ask for a code here again', ['n' => \App\Services\Auth\TwoFactor::TRUST_DAYS]) }}</span>
        </label>

        <button type="submit" class="g-btn" :disabled="busy">
            <span x-show="!busy">{{ __('Verify and sign in') }}</span>
            <span x-show="busy" x-cloak class="g-spinner" aria-hidden="true"></span>
            <span x-show="busy" x-cloak>{{ __('Signing in…') }}</span>
        </button>
    </form>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-4">
        @if($method === 'email' && ! $recovery)
            <form method="POST" action="{{ route('two-factor.resend') }}"
                  x-data="{ wait: {{ (int) $resendIn }}, init() { setInterval(() => { if (this.wait > 0) this.wait--; }, 1000); } }">
                @csrf
                <button type="submit" class="btn btn-link g-link g-resend p-0 border-0" :disabled="wait > 0">
                    <span x-show="wait === 0">{{ __('Send a new code') }}</span>
                    <span x-show="wait > 0" x-cloak>{{ __('Send a new code in') }} <span x-text="wait + 's'" style="font-variant-numeric: tabular-nums;"></span></span>
                </button>
            </form>
        @else
            <span></span>
        @endif

        @if($recovery)
            <a href="{{ route('two-factor.challenge') }}" class="g-link small">{{ $method === 'email' ? __('Use the emailed code') : __('Use my app code') }}</a>
        @else
            <a href="{{ route('two-factor.challenge', ['recovery' => 1]) }}" class="g-link small">{{ __('Lost your phone? Use a recovery code') }}</a>
        @endif
    </div>

    <form method="POST" action="{{ route('two-factor.cancel') }}" class="text-center mt-4">
        @csrf
        <button type="submit" class="btn btn-link g-link g-resend p-0 border-0">{{ __('Back to sign in') }}</button>
    </form>

</x-guest-shell>
