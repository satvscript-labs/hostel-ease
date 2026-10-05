@props([
    'title' => null,
    'heading' => null,
    'sub' => null,
    // Sign-up is a real two-step sequence, so it gets a step indicator; nothing else does.
    'step' => null,
])

{{-- The one guest chrome (W10): login, sign-up, verify-email and recover all pass their
     form into this, so the head, the panel, the footer and the field language live in
     one place.

     Redesigned for the premium pass (2026-10-04). The brand is unchanged on purpose —
     the owner lands in the app one click later, and a login that looks like a
     different product reads as less trustworthy, not more. What changed is the one
     thing worth being bold about: the panel is no longer a generic glass "stat card
     with bars", it is the product's own signature — a bed matrix, rooms filling up for
     the term. Everything else is kept quiet: no card around the form, sentence-case
     labels, one orchestrated motion (the beds filling), and none of it if the visitor
     asks for reduced motion. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#1e1b4b">
    <link rel="icon" href="{{ asset('hostel-ease-icon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('hostel-ease-icon.svg') }}">

    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name', 'HostelEase') }}</title>
    <meta name="description" content="{{ __('Run your hostel or PG from one place — beds, rent, students and staff.') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])

    <style>
        /* ── Tokens: the brand's own, plus two the panel needs ── */
        :root {
            --g-ink: var(--he-text-main, #0f172a);
            --g-muted: var(--he-text-muted, #64748b);
            --g-line: rgba(15, 23, 42, 0.09);
            --g-field: #f6f7fb;
            --g-brand: var(--he-primary, #4f46e5);
            --g-brand-2: var(--he-accent, #9333ea);
            --g-night: #1e1b4b;
            --g-night-2: #312e81;
            --g-ok: var(--he-success, #10b981);
            --g-bad: #dc2626;
            --g-radius: 12px;
            --g-ease: cubic-bezier(0.16, 1, 0.3, 1);
        }

        html, body { height: 100%; margin: 0; }
        body {
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
            color: var(--g-ink); background: #fff;
            -webkit-font-smoothing: antialiased;
        }

        .g-layout { display: grid; min-height: 100vh; grid-template-columns: 1fr; }
        @media (min-width: 992px) { .g-layout { grid-template-columns: minmax(440px, 520px) 1fr; } }

        /* ── Form column ── */
        .g-form {
            display: flex; flex-direction: column;
            padding: 1.75rem 1.25rem 1.5rem;
            min-width: 0;
        }
        @media (min-width: 576px) { .g-form { padding: 2.5rem 2.75rem 2rem; } }
        @media (min-width: 992px) { .g-form { padding: 2.75rem 3.5rem 2rem; } }

        .g-brand { display: inline-flex; align-items: center; gap: 0.6rem; text-decoration: none; color: var(--g-ink); }
        .g-brand img { height: 32px; width: 32px; }
        .g-brand span { font-size: 1.2rem; font-weight: 800; letter-spacing: -0.03em; }

        .g-body { width: 100%; max-width: 400px; margin: auto 0; padding: 2.5rem 0; }
        @media (min-width: 576px) { .g-body { margin: auto; } }
        @media (min-width: 992px) { .g-body { margin: auto 0; } }

        .g-heading {
            font-size: clamp(1.85rem, 4.2vw, 2.25rem); line-height: 1.12;
            font-weight: 800; letter-spacing: -0.035em; margin: 0 0 0.6rem;
        }
        .g-sub { color: var(--g-muted); font-size: 1rem; line-height: 1.55; margin: 0; }
        .g-sub strong { color: var(--g-ink); font-weight: 700; overflow-wrap: anywhere; }

        /* Step indicator — sign-up only, because it really is a sequence. */
        .g-steps { display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; margin-bottom: 1.75rem; }
        .g-step { font-size: 0.78rem; font-weight: 600; color: var(--g-muted); }
        .g-step::before {
            content: ''; display: block; height: 4px; border-radius: 4px;
            background: var(--g-line); margin-bottom: 0.5rem;
            transition: background 0.4s var(--g-ease);
        }
        .g-step.is-done, .g-step.is-on { color: var(--g-ink); }
        .g-step.is-done::before, .g-step.is-on::before { background: linear-gradient(90deg, var(--g-brand), var(--g-brand-2)); }

        /* ── Fields ── */
        .g-field { margin-bottom: 1.15rem; }
        .g-label { display: block; font-size: 0.86rem; font-weight: 600; color: var(--g-ink); margin-bottom: 0.45rem; }
        .g-control {
            position: relative; display: flex; align-items: stretch;
            background: var(--g-field); border: 1.5px solid transparent; border-radius: var(--g-radius);
            transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        }
        .g-control:hover { border-color: var(--g-line); }
        .g-control:focus-within {
            background: #fff; border-color: var(--g-brand);
            box-shadow: 0 0 0 4px color-mix(in srgb, var(--g-brand) 14%, transparent);
        }
        .g-control.is-invalid { border-color: var(--g-bad); background: #fff; }
        .g-control.is-invalid:focus-within { box-shadow: 0 0 0 4px color-mix(in srgb, var(--g-bad) 14%, transparent); }
        .g-prefix {
            display: flex; align-items: center; padding: 0 0 0 1rem;
            font-weight: 600; color: var(--g-muted); font-variant-numeric: tabular-nums; white-space: nowrap;
        }
        .g-input {
            flex: 1; min-width: 0; border: 0; outline: 0; background: transparent;
            height: 50px; padding: 0 1rem; font: inherit; font-size: 1rem; color: var(--g-ink);
        }
        .g-prefix + .g-input { padding-left: 0.55rem; }
        .g-input::placeholder { color: #a3acbd; }
        .g-reveal {
            border: 0; background: transparent; color: var(--g-muted);
            padding: 0 1rem; cursor: pointer; border-radius: var(--g-radius);
        }
        .g-reveal:hover { color: var(--g-ink); }
        .g-reveal:focus-visible { outline: 2px solid var(--g-brand); outline-offset: -4px; }
        .g-hint { font-size: 0.8rem; color: var(--g-muted); margin-top: 0.4rem; }
        .g-error { font-size: 0.82rem; color: var(--g-bad); margin-top: 0.4rem; display: flex; gap: 0.4rem; align-items: flex-start; }
        .g-error i { margin-top: 0.15rem; }

        /* ── Actions ── */
        .g-btn {
            position: relative; display: flex; align-items: center; justify-content: center; gap: 0.55rem;
            width: 100%; height: 52px; border: 0; border-radius: var(--g-radius);
            background: linear-gradient(135deg, var(--g-brand), var(--g-brand-2));
            color: #fff; font: inherit; font-weight: 700; font-size: 1rem; text-decoration: none;
            box-shadow: 0 10px 24px -8px color-mix(in srgb, var(--g-brand) 70%, transparent);
            cursor: pointer; transition: transform 0.2s var(--g-ease), box-shadow 0.2s ease, opacity 0.2s ease;
        }
        .g-btn:hover { color: #fff; transform: translateY(-1px); box-shadow: 0 14px 28px -8px color-mix(in srgb, var(--g-brand) 80%, transparent); }
        .g-btn:active { transform: translateY(0); }
        .g-btn:focus-visible { outline: 3px solid color-mix(in srgb, var(--g-brand) 45%, transparent); outline-offset: 3px; }
        .g-btn[disabled] { opacity: 0.7; cursor: progress; transform: none; }
        .g-spinner { width: 18px; height: 18px; border-radius: 50%; border: 2.5px solid rgba(255,255,255,.45); border-top-color: #fff; animation: g-spin 0.7s linear infinite; }
        @keyframes g-spin { to { transform: rotate(360deg); } }

        .g-link { color: var(--g-brand); font-weight: 600; text-decoration: none; }
        .g-link:hover { text-decoration: underline; }
        .g-link:focus-visible { outline: 2px solid var(--g-brand); outline-offset: 2px; border-radius: 4px; }
        .g-quiet { font-size: 0.92rem; color: var(--g-muted); }

        .g-note { display: flex; gap: 0.7rem; align-items: flex-start; padding: 0.85rem 1rem; border-radius: var(--g-radius); font-size: 0.88rem; line-height: 1.5; margin-bottom: 1.25rem; }
        .g-note i { margin-top: 0.2rem; }
        .g-note--bad { background: #fef2f2; color: #991b1b; }
        .g-note--ok { background: #ecfdf5; color: #065f46; }
        .g-note--info { background: color-mix(in srgb, var(--g-brand) 7%, #fff); color: #3730a3; }

        .g-foot { display: flex; flex-wrap: wrap; gap: 0.5rem 1.25rem; justify-content: space-between; align-items: center; font-size: 0.82rem; color: var(--g-muted); }
        .g-foot a { color: var(--g-muted); text-decoration: none; }
        .g-foot a:hover { color: var(--g-ink); }
        .g-foot a.is-on { color: var(--g-ink); font-weight: 700; }
        .g-locales { display: flex; gap: 0.85rem; }

        /* ── The panel: a hostel filling up for the term ── */
        .g-panel {
            display: none; position: relative; overflow: hidden;
            background: radial-gradient(120% 90% at 85% 10%, var(--g-night-2) 0%, var(--g-night) 55%, #15123a 100%);
            color: #fff;
        }
        /* Sticky, so on the taller sign-up form the plan stays in view while the form scrolls. */
        @media (min-width: 992px) { .g-panel { display: flex; align-items: center; justify-content: center; position: sticky; top: 0; height: 100vh; padding: 4rem clamp(3rem, 6vw, 6rem); } }
        .g-panel-inner { width: 100%; max-width: 560px; }

        .g-plan { width: 100%; max-width: 560px; }
        .g-floor { display: grid; grid-template-columns: 4.5rem 1fr; align-items: center; gap: 1rem; margin-bottom: 1.1rem; }
        .g-floor-name { font-size: 0.8rem; font-weight: 600; color: rgba(255,255,255,.5); }
        .g-rooms { display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.7rem; }
        .g-room {
            display: flex; flex-wrap: wrap; gap: 6px; padding: 9px;
            border-radius: 10px; background: rgba(255,255,255,.04);
            border: 1px solid rgba(255,255,255,.08);
        }
        .g-bed {
            width: 22px; height: 30px; border-radius: 6px;
            border: 1.5px solid rgba(255,255,255,.22); background: transparent;
        }
        .g-bed.is-taken {
            border-color: transparent;
            background: linear-gradient(160deg, #818cf8, #a78bfa);
            box-shadow: 0 0 0 1px rgba(255,255,255,.08) inset;
            animation: g-fill 0.5s var(--g-ease) both; animation-delay: var(--d, 0ms);
        }
        .g-bed.is-new {
            background: var(--g-ok); border-color: transparent;
            animation: g-fill 0.5s var(--g-ease) both, g-glow 2.4s ease-in-out 1.6s 2;
            animation-delay: var(--d, 0ms), 1.6s;
        }
        @keyframes g-fill { from { opacity: 0; transform: scale(0.6); } to { opacity: 1; transform: scale(1); } }
        @keyframes g-glow { 50% { box-shadow: 0 0 0 6px rgba(16,185,129,.18), 0 0 18px rgba(16,185,129,.5); } }

        .g-panel-copy { margin-top: 2.75rem; max-width: 30rem; }
        .g-panel-line { font-size: clamp(1.6rem, 2.4vw, 2.1rem); line-height: 1.2; font-weight: 700; letter-spacing: -0.03em; margin: 0 0 0.85rem; }
        .g-panel-sub { color: rgba(255,255,255,.62); font-size: 1rem; line-height: 1.6; margin: 0; }
        .g-legend { display: flex; gap: 1.4rem; margin-top: 1.75rem; font-size: 0.82rem; color: rgba(255,255,255,.62); }
        .g-legend span { display: inline-flex; align-items: center; gap: 0.5rem; }
        .g-legend i { display: inline-block; width: 12px; height: 16px; border-radius: 3px; }

        @media (prefers-reduced-motion: reduce) {
            .g-bed.is-taken, .g-bed.is-new { animation: none; }
            .g-btn, .g-control, .g-step::before { transition: none; }
        }
    </style>
    @stack('head')
</head>
<body>
    @php
        // A fixed floor plan, so the panel looks the same on every visit (no layout
        // jump, no random "stats"). Rooms are listed by beds; `x` = taken, `n` = the
        // one that has just filled, `.` = free. 32 of 38 beds — a hostel near full.
        $floors = [
            __('Floor 3') => ['xx.', 'xxxx', 'x.', 'xxx'],
            __('Floor 2') => ['xxx', 'xn', 'xxx.', 'xxx'],
            __('Floor 1') => ['xxxx', 'xx', 'x.x', 'xx.'],
        ];
        $order = 0;
    @endphp

    <div class="g-layout">
        <main class="g-form">
            <a href="{{ url('/') }}" class="g-brand">
                <img src="{{ asset('hostel-ease-icon.svg') }}" alt="">
                <span>{{ config('app.name', 'HostelEase') }}</span>
            </a>

            <div class="g-body">
                @if($step)
                    <div class="g-steps" aria-label="{{ __('Sign-up progress') }}">
                        <div class="g-step {{ $step > 1 ? 'is-done' : 'is-on' }}" @if($step === 1) aria-current="step" @endif>{{ __('Your details') }}</div>
                        <div class="g-step {{ $step === 2 ? 'is-on' : '' }}" @if($step === 2) aria-current="step" @endif>{{ __('Verify email') }}</div>
                    </div>
                @endif

                @if($heading)
                    <div class="mb-4 pb-1">
                        <h1 class="g-heading">{{ $heading }}</h1>
                        @if($sub)<p class="g-sub">{!! $sub !!}</p>@endif
                    </div>
                @endif

                {{ $slot }}
            </div>

            <footer class="g-foot">
                <div class="g-locales">
                    @foreach(config('app.available_locales') as $code => $label)
                        <a href="{{ route('locale.switch', $code) }}" class="{{ app()->getLocale() === $code ? 'is-on' : '' }}" lang="{{ $code }}">{{ $label }}</a>
                    @endforeach
                </div>
                <div class="d-flex gap-3">
                    <a href="{{ route('terms') }}">{{ __('Terms') }}</a>
                    <a href="{{ route('privacy') }}">{{ __('Privacy') }}</a>
                </div>
            </footer>
        </main>

        <aside class="g-panel" aria-hidden="true">
            <div class="g-panel-inner">
                <div class="g-plan">
                    @foreach($floors as $floor => $rooms)
                        <div class="g-floor">
                            <div class="g-floor-name">{{ $floor }}</div>
                            <div class="g-rooms">
                                @foreach($rooms as $room)
                                    <div class="g-room">
                                        @foreach(str_split($room) as $bed)
                                            @php($cls = $bed === 'x' ? 'is-taken' : ($bed === 'n' ? 'is-new' : ''))
                                            <span class="g-bed {{ $cls }}" @if($cls) style="--d: {{ 120 + ($order++) * 38 }}ms" @endif></span>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="g-panel-copy">
                    <p class="g-panel-line">{{ __('Know which bed is free before the next enquiry calls.') }}</p>
                    <p class="g-panel-sub">{{ __('Rooms, rent, students and staff for every branch — kept in one place, and up to date on your phone.') }}</p>
                    <div class="g-legend">
                        <span><i style="background: linear-gradient(160deg, #818cf8, #a78bfa);"></i>{{ __('Occupied') }}</span>
                        <span><i style="background: var(--g-ok);"></i>{{ __('Just booked') }}</span>
                        <span><i style="border: 1.5px solid rgba(255,255,255,.3);"></i>{{ __('Free') }}</span>
                    </div>
                </div>
            </div>
        </aside>
    </div>

    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
        }
    </script>
    @stack('scripts')
</body>
</html>
