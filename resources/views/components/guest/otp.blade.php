@props(['name' => 'code', 'label', 'error' => null, 'autosubmit' => true])

{{-- Six display cells over ONE real input (the sign-up verify pattern): phone autofill
     ("from Messages / Mail"), paste and screen readers keep working. --}}
@once
@push('head')
<style>
    .g-otp { position: relative; margin-bottom: 0.5rem; }
    .g-otp-input { position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0; border: 0; font-size: 16px; letter-spacing: 2.5rem; color: transparent; caret-color: transparent; z-index: 2; cursor: text; }
    .g-otp-cells { display: grid; grid-template-columns: repeat(3, 1fr) 0.6rem repeat(3, 1fr); gap: 0.55rem; }
    .g-otp-cell { height: 62px; border-radius: var(--g-radius); background: var(--g-field); border: 1.5px solid transparent; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; font-weight: 700; font-variant-numeric: tabular-nums; color: var(--g-ink); transition: border-color 0.15s ease, box-shadow 0.15s ease, background 0.15s ease; }
    .g-otp-cell.is-filled { background: #fff; border-color: var(--g-line); }
    .g-otp.is-focused .g-otp-cell.is-active { background: #fff; border-color: var(--g-brand); box-shadow: 0 0 0 4px color-mix(in srgb, var(--g-brand) 14%, transparent); }
    .g-otp.is-invalid .g-otp-cell { border-color: var(--g-bad); background: #fff; }
    .g-otp-gap { align-self: center; height: 2px; background: var(--g-line); border-radius: 2px; }
    .g-otp-caret { width: 2px; height: 26px; background: var(--g-brand); animation: g-blink 1s step-end infinite; }
    @keyframes g-blink { 50% { opacity: 0; } }
    @media (prefers-reduced-motion: reduce) { .g-otp-caret { animation: none; } }
    @media (max-width: 380px) { .g-otp-cell { height: 54px; font-size: 1.35rem; } .g-otp-cells { gap: 0.4rem; } }
</style>
@endpush
@endonce

<div x-data="{
        code: '', focused: false,
        set(v) {
            this.code = (v || '').replace(/\D/g, '').slice(0, 6);
            this.$refs.input.value = this.code;
            @if($autosubmit) if (this.code.length === 6) { this.$dispatch('otp-complete'); } @endif
        },
     }">
    <label for="f-{{ $name }}" class="g-label">{{ $label }}</label>
    <div class="g-otp {{ $error ? 'is-invalid' : '' }}" :class="{ 'is-focused': focused }">
        <input id="f-{{ $name }}" name="{{ $name }}" x-ref="input" class="g-otp-input"
               type="text" inputmode="numeric" autocomplete="one-time-code" pattern="\d{6}" maxlength="6" required autofocus
               @input="set($event.target.value)" @focus="focused = true" @blur="focused = false"
               @if($error) aria-invalid="true" aria-describedby="f-{{ $name }}-error" @endif>
        <div class="g-otp-cells" aria-hidden="true">
            @foreach([0, 1, 2, null, 3, 4, 5] as $i)
                @if($i === null)
                    <span class="g-otp-gap"></span>
                @else
                    <span class="g-otp-cell" :class="{ 'is-filled': code.length > {{ $i }}, 'is-active': code.length === {{ $i }} || ({{ $i }} === 5 && code.length === 6) }">
                        <span x-text="code[{{ $i }}] || ''"></span>
                        <span class="g-otp-caret" x-show="focused && code.length === {{ $i }}" x-cloak></span>
                    </span>
                @endif
            @endforeach
        </div>
    </div>
    @if($error)
        <div id="f-{{ $name }}-error" class="g-error mb-2"><i class="fa-solid fa-circle-exclamation"></i><span>{{ $error }}</span></div>
    @endif
</div>
