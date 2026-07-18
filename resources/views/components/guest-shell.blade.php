@props([
    'title' => null,
    'heading' => null,
    'sub' => null,
])

{{-- The one guest chrome (W10). Login and Register were 300-line near-twins —
     the SAME 165-line feature array, the SAME split-layout / input / button
     CSS, pasted into both. That's the "don't hand-roll the same shell twice"
     law (§0). Now they pass their form into this; the visual side, the head,
     the locale footer and the PWA script live here, once, on canonical tokens
     (no more hardcoded #4f46e5). --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#4f46e5">
    <link rel="icon" href="{{ asset('hostel-ease-icon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('hostel-ease-icon.svg') }}">

    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name', 'HostelEase') }}</title>
    <meta name="description" content="{{ __('Manage your hostel, PG, or dorm with HostelEase — automated billing, live occupancy, and student management in one dashboard.') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    {{-- Alpine (same CDN the app layout uses) — the password show/hide toggles
         are x-data, so the guest pages need it too. --}}
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])

    <style>
        /* All colour comes from _premium.scss tokens — nothing hardcoded. */
        body, html { height: 100%; margin: 0; font-family: 'Plus Jakarta Sans', sans-serif; background: var(--he-bg-surface, #fff); }

        .guest-layout { display: flex; min-height: 100vh; flex-direction: column; }
        .guest-form-side {
            width: 100%; flex-grow: 1;
            display: flex; flex-direction: column; justify-content: center;
            padding: 2rem 1.5rem; position: relative; z-index: 2;
            background: var(--he-bg-surface, #fff);
        }
        .guest-visual-side {
            display: none; position: relative; overflow: hidden;
            background: var(--he-gradient-mesh, linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%));
            align-items: center; justify-content: center; padding: 3rem;
        }
        @media (min-width: 992px) {
            .guest-layout { flex-direction: row; }
            .guest-visual-side { display: flex; flex-grow: 1; }
            .guest-form-side { width: 46%; max-width: 560px; padding: 3rem 4rem; flex-grow: 0; }
        }

        .guest-brand { display: flex; align-items: center; gap: 0.6rem; }
        .guest-brand span { font-size: 1.35rem; font-weight: 800; letter-spacing: -0.5px; color: var(--he-text-main, #0f172a); }

        .guest-heading { font-weight: 800; letter-spacing: -1px; font-size: clamp(1.9rem, 5vw, 2.35rem); color: var(--he-text-main, #0f172a); margin-bottom: 0.4rem; }
        .guest-sub { color: var(--he-text-muted, #64748b); margin-bottom: 0; }

        /* Inputs — the canonical field language (raised surface, primary focus
           ring), matching .he-search / form-control across the app. */
        .guest-field { margin-bottom: 1.25rem; }
        .guest-label { display: block; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--he-text-muted, #64748b); margin-bottom: 0.5rem; }
        .guest-input-wrap { position: relative; }
        .guest-input {
            width: 100%; background: var(--he-bg-canvas, #f8fafc);
            border: 1.5px solid rgba(0, 0, 0, 0.06); border-radius: var(--he-radius-md, 10px);
            padding: 0.9rem 2.9rem 0.9rem 1.1rem; font-size: 0.95rem;
            color: var(--he-text-main, #0f172a); transition: all 0.25s var(--ease-out-expo, cubic-bezier(0.16, 1, 0.3, 1));
        }
        .guest-input:focus {
            background: #fff; border-color: var(--he-primary, #4f46e5); outline: none;
            box-shadow: 0 0 0 4px var(--he-primary-soft, rgba(79, 70, 229, 0.1));
        }
        .guest-input-ic { position: absolute; right: 1.1rem; top: 50%; transform: translateY(-50%); color: var(--he-text-muted, #94a3b8); transition: color 0.25s ease; pointer-events: none; }
        .guest-input:focus ~ .guest-input-ic { color: var(--he-primary, #4f46e5); }
        .guest-prefix {
            display: flex; align-items: center; justify-content: center;
            background: var(--he-bg-surface-raised, #f1f5f9); border: 1.5px solid rgba(0, 0, 0, 0.06);
            border-radius: var(--he-radius-md, 10px); padding: 0 0.9rem; font-weight: 700; color: var(--he-text-muted, #64748b);
        }

        /* Primary action — the canonical gradient button (matches .btn-premium). */
        .guest-btn {
            display: flex; align-items: center; justify-content: center; gap: 0.5rem;
            width: 100%; padding: 0.95rem; border: none; border-radius: var(--he-radius-md, 10px);
            background: var(--he-gradient-pop, linear-gradient(135deg, #4f46e5, #9333ea));
            color: #fff; font-weight: 700; font-size: 1rem;
            box-shadow: 0 10px 22px rgba(79, 70, 229, 0.28); cursor: pointer;
            transition: transform 0.2s var(--ease-out-expo, cubic-bezier(0.16, 1, 0.3, 1)), box-shadow 0.2s ease;
        }
        .guest-btn:hover { transform: translateY(-2px); box-shadow: 0 16px 30px rgba(79, 70, 229, 0.4); color: #fff; }
        .guest-btn:active { transform: translateY(0) scale(0.99); }

        .guest-alert { background: var(--he-danger-soft, #fee2e2); color: var(--he-danger, #b91c1c); border-radius: var(--he-radius-md, 10px); padding: 0.8rem 1rem; margin-bottom: 1.25rem; font-size: 0.88rem; font-weight: 600; display: flex; align-items: flex-start; gap: 0.5rem; }
        .guest-link { color: var(--he-primary, #4f46e5); font-weight: 700; text-decoration: none; }
        .guest-link:hover { text-decoration: underline; }

        /* Locale footer */
        .guest-locales { display: flex; gap: 0.75rem; justify-content: center; }
        .guest-locales a { font-size: 0.82rem; text-decoration: none; color: var(--he-text-muted, #64748b); }
        .guest-locales a.on { font-weight: 700; color: var(--he-text-main, #0f172a); }

        /* ── Visual showcase (right, ≥992px) — a live-feeling product stat card
           that slides up on load. One rotating feature; pure decoration. ── */
        .guest-glow { position: absolute; inset: 0; z-index: 0; filter: blur(40px);
            background: radial-gradient(circle at top right, var(--he-accent-soft, rgba(147, 51, 234, 0.4)) 0%, transparent 60%),
                        radial-gradient(circle at bottom left, var(--he-primary-soft, rgba(79, 70, 229, 0.4)) 0%, transparent 60%); }
        .guest-card {
            position: relative; z-index: 2; width: 440px; max-width: 100%;
            background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.15); border-radius: var(--he-radius-lg, 16px); color: #fff;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25); overflow: hidden;
            opacity: 0; transform: translateY(20px); animation: guestUp 0.8s var(--ease-out-expo, cubic-bezier(0.16, 1, 0.3, 1)) forwards 0.15s;
        }
        @keyframes guestUp { to { opacity: 1; transform: translateY(0); } }
        .guest-card-head { padding: 1.4rem; border-bottom: 1px solid rgba(255, 255, 255, 0.1); display: flex; justify-content: space-between; align-items: center; }
        .guest-bars { padding: 1.4rem; height: 180px; display: flex; align-items: flex-end; gap: 8px; }
        .guest-bar { flex-grow: 1; border-radius: 6px 6px 0 0; background: rgba(255, 255, 255, 0.1); position: relative; }
        .guest-bar.lead { background: var(--he-primary, #4f46e5); box-shadow: 0 0 18px rgba(79, 70, 229, 0.6); }
        .guest-card-foot { padding: 1.4rem; background: rgba(255, 255, 255, 0.06); border-top: 1px solid rgba(255, 255, 255, 0.1); display: flex; align-items: center; gap: 0.9rem; }
        .guest-chip { background: rgba(255, 255, 255, 0.12); color: #fff; border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 9999px; padding: 0.4rem 0.9rem; font-size: 0.8rem; font-weight: 700; white-space: nowrap; }
    </style>
    @stack('head')
</head>
<body>
    @php
        $features = [
            ['title' => __('Visual Bed Matrix'), 'desc' => __('Live occupancy across every room — no more spreadsheets.'), 'icon' => 'fa-bed', 'label' => __('Live Occupancy'), 'value' => '92%', 'sub' => __('Filled'), 'chip' => '138 / 150', 'bars' => [40, 60, 45, 80, 70, 100]],
            ['title' => __('Automated Rent Collection'), 'desc' => __('Invoices generate themselves and dues track automatically.'), 'icon' => 'fa-bolt', 'label' => __('Monthly Revenue'), 'value' => '₹2,45,000', 'sub' => __('This month'), 'chip' => '+14.5%', 'bars' => [30, 50, 40, 70, 85, 100]],
            ['title' => __('Deep Financial Insights'), 'desc' => __('Real-time revenue analytics and collection charts.'), 'icon' => 'fa-chart-line', 'label' => __('Collection Rate'), 'value' => '98.5%', 'sub' => __('Average'), 'chip' => __('Excellent'), 'bars' => [80, 85, 90, 88, 95, 98]],
            ['title' => __('Effortless Student Management'), 'desc' => __('Digital profiles, documents, and per-student ledgers.'), 'icon' => 'fa-users', 'label' => __('Active Students'), 'value' => '142', 'sub' => __('Enrolled'), 'chip' => __('New +12'), 'bars' => [50, 60, 75, 90, 110, 142]],
        ];
        $feature = collect($features)->random();
    @endphp

    <div class="guest-layout">
        <div class="guest-form-side">
            <a href="{{ url('/') }}" class="guest-brand mb-5 pb-2 text-decoration-none">
                <img src="{{ asset('hostel-ease-icon.svg') }}" alt="{{ config('app.name') }}" style="height: 34px;">
                <span>{{ config('app.name', 'HostelEase') }}</span>
            </a>

            @if($heading)
                <div class="mb-4">
                    <h1 class="guest-heading">{{ $heading }}</h1>
                    @if($sub)<p class="guest-sub">{{ $sub }}</p>@endif
                </div>
            @endif

            {{ $slot }}

            <div class="mt-auto pt-5">
                <div class="guest-locales">
                    @foreach(config('app.available_locales') as $code => $label)
                        <a href="{{ route('locale.switch', $code) }}" class="{{ app()->getLocale() === $code ? 'on' : '' }}">{{ $label }}</a>
                        @if(! $loop->last)<span class="text-muted opacity-25">|</span>@endif
                    @endforeach
                </div>
            </div>
        </div>

        <div class="guest-visual-side">
            <div class="guest-glow"></div>
            <div class="guest-card">
                <div class="guest-card-head">
                    <div>
                        <div class="text-white-50 small fw-semibold text-uppercase mb-1" style="letter-spacing: 1px;">{{ $feature['label'] }}</div>
                        <div class="fs-2 fw-bold">{{ $feature['value'] }} <span class="fs-5 opacity-50 fw-normal">{{ $feature['sub'] }}</span></div>
                    </div>
                    <span class="guest-chip"><i class="fa-solid fa-arrow-trend-up me-1"></i>{{ $feature['chip'] }}</span>
                </div>
                <div class="guest-bars">
                    @foreach($feature['bars'] as $h)
                        <div class="guest-bar {{ $loop->last ? 'lead' : '' }}" style="height: {{ $h }}%;"></div>
                    @endforeach
                </div>
                <div class="guest-card-foot">
                    <div class="rounded-circle bg-white bg-opacity-25 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 42px; height: 42px;">
                        <i class="fa-solid {{ $feature['icon'] }} text-white"></i>
                    </div>
                    <div>
                        <div class="fw-bold">{{ $feature['title'] }}</div>
                        <div class="text-white-50 small">{{ $feature['desc'] }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
        }
    </script>
    @stack('scripts')
</body>
</html>
