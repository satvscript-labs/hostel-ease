{{-- Sign-up, step 2 of 2: verify the email. The account, branch and free trial are
     created only when this code is right. --}}
<x-guest-shell :title="__('Verify your email')" :step="2"
               :heading="__('Check your email')"
               :sub="__('We sent a 6-digit code to').' <strong>'.e($maskedEmail).'</strong>. '.__('Enter it below to finish setting up.')">

    @push('head')
    <style>
        /* ONE real input over six display cells. Six separate inputs break phone
           autofill ("from Messages / Mail"), paste and screen readers; one input
           keeps all three, and the cells are only how it looks. */
        .g-otp { position: relative; margin-bottom: 0.5rem; }
        .g-otp-input {
            position: absolute; inset: 0; width: 100%; height: 100%;
            opacity: 0; border: 0; font-size: 16px; /* 16px stops iOS zooming in */
            letter-spacing: 2.5rem; color: transparent; caret-color: transparent; z-index: 2; cursor: text;
        }
        .g-otp-cells { display: grid; grid-template-columns: repeat(3, 1fr) 0.6rem repeat(3, 1fr); gap: 0.55rem; }
        .g-otp-cell {
            height: 62px; border-radius: var(--g-radius); background: var(--g-field);
            border: 1.5px solid transparent; display: flex; align-items: center; justify-content: center;
            font-size: 1.6rem; font-weight: 700; font-variant-numeric: tabular-nums; color: var(--g-ink);
            transition: border-color 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
        }
        .g-otp-cell.is-filled { background: #fff; border-color: var(--g-line); }
        .g-otp.is-focused .g-otp-cell.is-active {
            background: #fff; border-color: var(--g-brand);
            box-shadow: 0 0 0 4px color-mix(in srgb, var(--g-brand) 14%, transparent);
        }
        .g-otp.is-invalid .g-otp-cell { border-color: var(--g-bad); background: #fff; }
        .g-otp-gap { align-self: center; height: 2px; background: var(--g-line); border-radius: 2px; }
        .g-otp-caret { width: 2px; height: 26px; background: var(--g-brand); animation: g-blink 1s step-end infinite; }
        @keyframes g-blink { 50% { opacity: 0; } }
        @media (prefers-reduced-motion: reduce) { .g-otp-caret { animation: none; } }
        @media (max-width: 380px) { .g-otp-cell { height: 54px; font-size: 1.35rem; } .g-otp-cells { gap: 0.4rem; } }
        .g-dev { border: 1.5px dashed #f59e0b; background: #fffbeb; color: #92400e; }
        /* Bootstrap's .btn sets its own font size; match the link beside it. */
        .g-resend { font: inherit; font-size: 0.875rem; font-weight: 600; }
        .g-resend[disabled] { color: var(--g-muted); opacity: 1; text-decoration: none; cursor: default; }
    </style>
    @endpush

    @if(session('status'))
        <div class="g-note g-note--ok" role="status"><i class="fa-solid fa-circle-check"></i><div>{{ session('status') }}</div></div>
    @endif

    {{-- LOCAL ONLY. This machine's mail is not configured, so the code cannot be
         emailed here; the controller passes it only when APP_ENV=local. --}}
    @if($devCode)
        <div class="g-note g-dev"><i class="fa-solid fa-code"></i>
            <div>{{ __('Local development — mail is not set up on this machine, so here is the code:') }} <strong style="letter-spacing:2px;">{{ $devCode }}</strong></div>
        </div>
    @endif

    <form method="POST" action="{{ route('register.verify.attempt') }}"
          x-data="{
              code: '',
              focused: false,
              busy: false,
              set(v) {
                  this.code = (v || '').replace(/\D/g, '').slice(0, 6);
                  this.$refs.input.value = this.code;
                  // Submit by itself on the sixth digit — once.
                  if (this.code.length === 6 && !this.busy) { this.busy = true; this.$nextTick(() => this.$root.submit()); }
              },
          }" novalidate>
        @csrf

        <label for="f-code" class="g-label">{{ __('Verification code') }}</label>
        <div class="g-otp {{ $errors->has('code') ? 'is-invalid' : '' }}" :class="{ 'is-focused': focused }">
            <input id="f-code" name="code" x-ref="input" class="g-otp-input"
                   type="text" inputmode="numeric" autocomplete="one-time-code" pattern="\d{6}" maxlength="6"
                   required autofocus
                   @input="set($event.target.value)" @focus="focused = true" @blur="focused = false"
                   @if($errors->has('code')) aria-invalid="true" aria-describedby="f-code-error" @endif>

            <div class="g-otp-cells" aria-hidden="true">
                @foreach([0, 1, 2, null, 3, 4, 5] as $i)
                    @if($i === null)
                        <span class="g-otp-gap"></span>
                    @else
                        <span class="g-otp-cell"
                              :class="{ 'is-filled': code.length > {{ $i }}, 'is-active': code.length === {{ $i }} || ({{ $i }} === 5 && code.length === 6) }">
                            <span x-text="code[{{ $i }}] || ''"></span>
                            <span class="g-otp-caret" x-show="focused && code.length === {{ $i }}" x-cloak></span>
                        </span>
                    @endif
                @endforeach
            </div>
        </div>

        @error('code')
            <div id="f-code-error" class="g-error mb-2"><i class="fa-solid fa-circle-exclamation"></i><span>{{ $message }}</span></div>
        @enderror

        <p class="g-quiet small mb-4">{{ __('The code expires in :m minutes. Check your spam folder if it has not arrived.', ['m' => \App\Services\Auth\SignupVerification::CODE_TTL_MINUTES]) }}</p>

        <button type="submit" class="g-btn" :disabled="busy || code.length < 6">
            <span x-show="!busy">{{ __('Verify and create account') }}</span>
            <span x-show="busy" x-cloak class="g-spinner" aria-hidden="true"></span>
            <span x-show="busy" x-cloak>{{ __('Creating your account…') }}</span>
        </button>
    </form>

    {{-- Resend, with the cooldown shown as a countdown instead of an error after the fact. --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-4"
         x-data="{ wait: {{ (int) $resendIn }}, t: null, init() { this.t = setInterval(() => { if (this.wait > 0) this.wait--; }, 1000); } }">
        @if($sendsLeft > 0)
            <form method="POST" action="{{ route('register.resend') }}">
                @csrf
                <button type="submit" class="btn btn-link g-link g-resend p-0 border-0" :disabled="wait > 0">
                    <span x-show="wait === 0">{{ __('Send a new code') }}</span>
                    <span x-show="wait > 0" x-cloak>{{ __('Send a new code in') }} <span x-text="Math.floor(wait / 60) + ':' + String(wait % 60).padStart(2, '0')" style="font-variant-numeric: tabular-nums;"></span></span>
                </button>
            </form>
        @else
            <span class="g-quiet small">{{ __('No more codes can be sent for this sign-up.') }}</span>
        @endif

        <span class="small">
            <a href="#change-email" class="g-link" @click.prevent="$dispatch('change-email')">{{ __('Use a different email') }}</a>
        </span>
    </div>

    {{-- Wrong address? Fix it here — no need to fill the whole form in again. --}}
    <div x-data="{ open: {{ $errors->has('email') ? 'true' : 'false' }}, busy: false }"
         @change-email.window="open = true; $nextTick(() => $refs.email.focus())"
         x-show="open" x-cloak id="change-email" class="mt-4 pt-4" style="border-top: 1px solid var(--g-line);">
        <form method="POST" action="{{ route('register.email') }}" @submit="busy = true" novalidate>
            @csrf
            <x-guest.field name="email" :label="__('New email')" type="email" :value="old('email', $email)"
                           autocomplete="email" inputmode="email" maxlength="150" x-ref="email" />
            <button type="submit" class="btn btn-light border w-100 fw-semibold" style="height: 48px; border-radius: var(--g-radius);" :disabled="busy">
                {{ __('Send the code to this email') }}
            </button>
        </form>
    </div>

    <p class="g-quiet text-center mt-4 mb-0">
        <a href="{{ route('register') }}" class="g-link">{{ __('Start again') }}</a>
    </p>

</x-guest-shell>
