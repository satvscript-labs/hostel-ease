<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>HostelEase — {{ __('The Ultimate Hostel Management Platform') }}</title>
    <meta name="title" content="HostelEase — The Ultimate Hostel Management SaaS Platform">
    <meta name="description" content="Manage your hostel, dorm, or PG effortlessly with HostelEase. Automate billing, track live bed occupancy, and manage students from one stunning dashboard.">
    <meta name="keywords" content="hostel management software, pg management system, dorm management saas, bed occupancy tracking, hostel billing automation, hostelease">
    <meta name="author" content="SatvScript">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="{{ url()->current() }}">

    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:title" content="HostelEase — The Ultimate Hostel Management SaaS Platform">
    <meta property="og:description" content="Automate billing, track live bed occupancy, and manage students from one stunning dashboard.">
    <meta property="og:image" content="{{ asset('hostel-ease-icon.svg') }}">
    <meta property="twitter:card" content="summary_large_image">

    <meta name="theme-color" content="#0f172a">
    <link rel="icon" href="{{ asset('hostel-ease-icon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('hostel-ease-icon.svg') }}">
    <link rel="manifest" href="/manifest.webmanifest">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @vite(['resources/scss/app.scss'])

    <style>
        /* W10 landing rebuild — canonical --he-* tokens only, no hardcoded hex.
           Kept the strong dark-mesh concept; rebuilt execution + added a working
           mobile drawer, a how-it-works band, a CTA band, and real config
           pricing (was hardcoded at half the real price). */
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--he-bg-canvas, #f8fafc); color: var(--he-text-main, #0f172a); overflow-x: hidden; }
        section { position: relative; }
        .lp-dark { background: var(--he-gradient-mesh, linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%)); }

        [x-cloak] { display: none !important; }

        /* ── Floating glass navbar ── */
        .lp-nav { position: fixed; top: 16px; left: 50%; transform: translateX(-50%); width: calc(100% - 32px); max-width: 1160px; z-index: 1030;
            background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 100px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.18);
            padding: 0.6rem 0.7rem 0.6rem 1.4rem; display: flex; align-items: center; justify-content: space-between; }
        .lp-nav-links { display: none; gap: 2rem; }
        .lp-nav-links a { color: rgba(255, 255, 255, 0.7); text-decoration: none; font-weight: 600; font-size: 0.9rem; transition: color 0.2s; }
        .lp-nav-links a:hover { color: #fff; }
        .lp-nav-actions { display: flex; align-items: center; gap: 0.5rem; }
        .lp-brand { display: flex; align-items: center; gap: 0.55rem; color: #fff; text-decoration: none; font-weight: 800; font-size: 1.15rem; letter-spacing: -0.5px; }
        .lp-burger { width: 42px; height: 42px; border: 0; border-radius: 50%; background: rgba(255, 255, 255, 0.1); color: #fff; display: flex; align-items: center; justify-content: center; transition: background 0.2s; }
        .lp-burger:hover { background: rgba(255, 255, 255, 0.18); }
        @media (min-width: 900px) {
            /* Grid 1fr | auto | 1fr so the links are centered to the NAVBAR, not
               to the gap between the logo and the buttons (owner catch). */
            .lp-nav { display: grid; grid-template-columns: 1fr auto 1fr; }
            .lp-nav-links { display: flex; justify-self: center; }
            .lp-nav-actions { justify-self: end; }
            .lp-burger { display: none; }
        }

        /* ── Mobile drawer — animated both ways (owner). The scrim fades via
           Alpine x-transition; the panel always lives in the DOM and animates
           on the .is-open class (spring in, ease out), so CLOSING is as smooth
           as opening. ── */
        .lp-drawer-scrim { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.55); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); z-index: 1040; }
        .lp-drawer { position: fixed; top: 12px; right: 12px; left: 12px; z-index: 1050; background: rgba(15, 23, 42, 0.97);
            border: 1px solid rgba(255, 255, 255, 0.12); border-radius: 26px; padding: 1.75rem 1.5rem 1.9rem;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.5);
            opacity: 0; visibility: hidden; pointer-events: none;
            transform: translateY(-24px) scale(0.96); transform-origin: top center;
            transition: opacity 0.28s ease, transform 0.42s var(--ease-spring, cubic-bezier(0.175, 0.885, 0.32, 1.275)), visibility 0.42s; }
        .lp-drawer.is-open { opacity: 1; visibility: visible; pointer-events: auto; transform: none; }
        .lp-drawer a { display: block; color: #fff; text-decoration: none; font-weight: 700; padding: 0.95rem 1.1rem; border-radius: 14px; transition: background 0.2s, transform 0.2s; }
        .lp-drawer a:hover { background: rgba(255, 255, 255, 0.09); }
        .lp-drawer a:active { transform: scale(0.98); }
        /* Links rise in with a gentle stagger once the panel is open. */
        .lp-drawer.is-open .lp-drawer-item { animation: lpDrawerItem 0.45s var(--ease-out-expo, cubic-bezier(0.16, 1, 0.3, 1)) both; }
        .lp-drawer.is-open .lp-drawer-item:nth-child(1) { animation-delay: 0.06s; }
        .lp-drawer.is-open .lp-drawer-item:nth-child(2) { animation-delay: 0.12s; }
        .lp-drawer.is-open .lp-drawer-item:nth-child(3) { animation-delay: 0.18s; }
        @keyframes lpDrawerItem { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }
        .lp-drawer-cta { padding: 0.9rem 1.1rem !important; }

        /* ── Buttons ── */
        .lp-btn { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.85rem 1.6rem; border-radius: 100px; font-weight: 700; font-size: 0.95rem; text-decoration: none; border: none; transition: transform 0.2s var(--ease-out-expo, cubic-bezier(0.16, 1, 0.3, 1)), box-shadow 0.2s; }
        .lp-btn-primary { background: var(--he-gradient-pop, linear-gradient(135deg, #4f46e5, #9333ea)); color: #fff; box-shadow: 0 10px 22px rgba(79, 70, 229, 0.3); }
        .lp-btn-primary:hover { transform: translateY(-2px); box-shadow: 0 16px 32px rgba(79, 70, 229, 0.42); color: #fff; }
        .lp-btn-glass { background: rgba(255, 255, 255, 0.1); color: #fff; border: 1px solid rgba(255, 255, 255, 0.2); }
        .lp-btn-glass:hover { background: rgba(255, 255, 255, 0.18); color: #fff; }
        .lp-btn-light { background: #fff; color: var(--he-text-main, #0f172a); box-shadow: var(--he-shadow-md); }
        .lp-btn-light:hover { transform: translateY(-2px); color: var(--he-text-main, #0f172a); }

        /* ── Hero ── */
        .lp-hero { min-height: 100vh; display: flex; align-items: center; padding: 130px 0 60px; overflow: hidden; }
        .lp-hero-mesh { position: absolute; inset: 0; opacity: 0.6; z-index: 0;
            background-image: radial-gradient(at 80% 0%, hsla(253, 16%, 7%, 1) 0, transparent 50%), radial-gradient(at 0% 50%, hsla(253, 16%, 7%, 1) 0, transparent 50%), radial-gradient(at 80% 100%, hsla(242, 100%, 70%, 0.3) 0, transparent 50%), radial-gradient(at 0% 0%, hsla(343, 100%, 76%, 0.2) 0, transparent 50%); }
        .lp-hero-inner { position: relative; z-index: 2; }
        .lp-pill { display: inline-flex; align-items: center; gap: 0.5rem; background: rgba(255, 255, 255, 0.1); color: #fff; border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 100px; padding: 0.5rem 1.15rem; font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; backdrop-filter: blur(10px); }
        .lp-hero-title { font-size: clamp(2.6rem, 5.5vw, 4.75rem); font-weight: 800; letter-spacing: -2px; line-height: 1.08; margin: 1.4rem 0; background: linear-gradient(to right, #fff, #a5b4fc); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; }
        .lp-hero-sub { color: rgba(255, 255, 255, 0.75); font-size: clamp(1rem, 2vw, 1.2rem); max-width: 640px; margin: 0 auto 2rem; }

        /* Dashboard mockup */
        .lp-mock { border-radius: 20px; border: 1px solid rgba(255, 255, 255, 0.15); overflow: hidden; background: var(--he-bg-canvas, #f8fafc);
            transform: perspective(1400px) rotateX(6deg); box-shadow: 0 50px 100px -20px rgba(0, 0, 0, 0.7); }
        .lp-mock-bar { background: #0b1120; height: 34px; display: flex; align-items: center; padding: 0 1rem; gap: 0.5rem; }
        .lp-mock-dot { width: 11px; height: 11px; border-radius: 50%; }

        /* ── Section headings ── */
        .lp-eyebrow { color: var(--he-primary, #4f46e5); font-weight: 800; text-transform: uppercase; letter-spacing: 1.5px; font-size: 0.8rem; }
        .lp-h2 { font-weight: 800; font-size: clamp(1.9rem, 4vw, 2.7rem); letter-spacing: -1px; color: var(--he-text-main, #0f172a); }
        .lp-lead { color: var(--he-text-muted, #64748b); font-size: 1.05rem; max-width: 620px; margin: 0 auto; }

        /* ── Feature bento ── */
        .lp-feat { background: var(--he-bg-surface, #fff); border: 1px solid rgba(0, 0, 0, 0.05); border-radius: var(--he-radius-lg, 16px); padding: 2rem; height: 100%; box-shadow: var(--he-shadow-sm); transition: transform 0.3s var(--ease-out-expo, cubic-bezier(0.16, 1, 0.3, 1)), box-shadow 0.3s; }
        .lp-feat:hover { transform: translateY(-6px); box-shadow: var(--he-shadow-lg); }
        .lp-feat-ic { width: 56px; height: 56px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; color: #fff; margin-bottom: 1.25rem; background: var(--he-gradient-pop, linear-gradient(135deg, #4f46e5, #9333ea)); box-shadow: 0 8px 20px rgba(79, 70, 229, 0.3); }
        .lp-feat h4 { font-weight: 700; margin-bottom: 0.6rem; }
        .lp-feat p { color: var(--he-text-muted, #64748b); margin: 0; }

        /* ── How it works ── */
        .lp-step { text-align: center; padding: 1rem; }
        .lp-step-n { width: 60px; height: 60px; margin: 0 auto 1rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; font-weight: 800; color: var(--he-primary, #4f46e5); background: var(--he-primary-soft, rgba(79, 70, 229, 0.1)); border: 2px solid var(--he-primary, #4f46e5); }

        /* ── Pricing ── */
        .lp-price { background: var(--he-bg-surface, #fff); border: 1px solid rgba(0, 0, 0, 0.06); border-radius: var(--he-radius-lg, 16px); padding: 2.25rem; height: 100%; position: relative; }
        .lp-price.pop { border: 2px solid var(--he-primary, #4f46e5); box-shadow: 0 20px 45px rgba(79, 70, 229, 0.18); }
        .lp-price-badge { position: absolute; top: -14px; left: 50%; transform: translateX(-50%); background: var(--he-gradient-pop, linear-gradient(135deg, #4f46e5, #9333ea)); color: #fff; font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; padding: 0.35rem 1rem; border-radius: 100px; white-space: nowrap; }
        .lp-price ul { list-style: none; padding: 0; margin: 1.5rem 0 2rem; }
        .lp-price li { padding: 0.5rem 0; color: var(--he-text-main, #0f172a); }
        .lp-price li.off { color: var(--he-text-muted, #94a3b8); }

        /* ── CTA band ── */
        .lp-cta { border-radius: var(--he-radius-lg, 24px); padding: clamp(2.5rem, 6vw, 4.5rem); text-align: center; overflow: hidden; }

        /* ── Footer ── */
        .lp-footer { padding: 3.5rem 0 2.5rem; color: rgba(255, 255, 255, 0.6); }
        .lp-footer a { color: rgba(255, 255, 255, 0.6); text-decoration: none; }
        .lp-footer a:hover { color: #fff; }
    </style>
</head>

<body x-data="{ menu: false }">

    {{-- ── Nav ── --}}
    <nav class="lp-nav">
        <a href="{{ url('/') }}" class="lp-brand">
            <img src="{{ asset('hostel-ease-icon.svg') }}" alt="" style="height: 30px;">
            {{ config('app.name', 'HostelEase') }}
        </a>
        <div class="lp-nav-links">
            <a href="#features">{{ __('Features') }}</a>
            <a href="#how">{{ __('How it works') }}</a>
            <a href="#pricing">{{ __('Pricing') }}</a>
        </div>
        <div class="lp-nav-actions">
            <a href="{{ route('login') }}" class="lp-btn lp-btn-glass d-none d-sm-inline-flex">{{ __('Log in') }}</a>
            <a href="{{ route('register') }}" class="lp-btn lp-btn-primary d-none d-sm-inline-flex">{{ __('Start free') }}</a>
            <button class="lp-burger" @click="menu = true" aria-label="{{ __('Open menu') }}"><i class="fa-solid fa-bars"></i></button>
        </div>
    </nav>

    {{-- Mobile drawer. Scrim fades via Alpine; the panel is always in the DOM
         and animates on .is-open, so both open AND close are smooth. Esc closes. --}}
    <div @keydown.escape.window="menu = false">
        <div class="lp-drawer-scrim" x-show="menu" x-transition.opacity.duration.300ms @click="menu = false" x-cloak></div>
        <div class="lp-drawer" :class="{ 'is-open': menu }" x-cloak>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="lp-brand"><img src="{{ asset('hostel-ease-icon.svg') }}" alt="" style="height: 26px;">{{ config('app.name') }}</span>
                <button class="lp-burger" @click="menu = false" aria-label="{{ __('Close menu') }}"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <a href="#features" class="lp-drawer-item" @click="menu = false">{{ __('Features') }}</a>
            <a href="#how" class="lp-drawer-item" @click="menu = false">{{ __('How it works') }}</a>
            <a href="#pricing" class="lp-drawer-item" @click="menu = false">{{ __('Pricing') }}</a>
            <hr style="border-color: rgba(255,255,255,0.1); margin: 0.75rem 0;">
            <a href="{{ route('login') }}">{{ __('Log in') }}</a>
            <a href="{{ route('register') }}" class="lp-btn lp-btn-primary lp-drawer-cta w-100 justify-content-center mt-2">{{ __('Start free trial') }}</a>
        </div>
    </div>

    {{-- ── Hero ── --}}
    <section class="lp-hero lp-dark">
        <div class="lp-hero-mesh"></div>
        <div class="container lp-hero-inner text-center">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <span class="lp-pill">{{ __('The Future of Property Management') }}</span>
                    <h1 class="lp-hero-title">{{ __('Automate Your Hostel.') }}<br>{{ __('Elevate Your Experience.') }}</h1>
                    <p class="lp-hero-sub">{{ __('The premium platform to streamline bed assignments, automate rent collection, and see powerful real-time insights — built for hostel and PG owners.') }}</p>
                    <div class="d-flex flex-wrap justify-content-center gap-3">
                        <a href="{{ route('register') }}" class="lp-btn lp-btn-primary"><i class="fa-solid fa-rocket"></i>{{ __('Start Your Free Trial') }}</a>
                        <a href="#features" class="lp-btn lp-btn-glass">{{ __('Explore Features') }}</a>
                    </div>
                </div>
            </div>

            {{-- Dashboard mockup --}}
            <div class="row justify-content-center mt-5 pt-3">
                <div class="col-lg-11 col-xl-10 px-2 px-md-4">
                    <div class="lp-mock mx-auto">
                        <div class="lp-mock-bar">
                            <span class="lp-mock-dot" style="background:#ff5f57;"></span>
                            <span class="lp-mock-dot" style="background:#febc2e;"></span>
                            <span class="lp-mock-dot" style="background:#28c840;"></span>
                            <span class="mx-auto text-white opacity-50" style="font-size:0.7rem;">app.hostelease.com</span>
                        </div>
                        <div class="d-flex text-start" style="height: 460px; max-height: 56vh;">
                            <div class="bg-white border-end d-none d-md-flex flex-column p-3" style="width: 210px;">
                                <div class="d-flex align-items-center gap-2 mb-4">
                                    <div class="rounded text-white d-flex align-items-center justify-content-center fw-bold" style="width:28px; height:28px; font-size:0.8rem; background: var(--he-primary);">H</div>
                                    <span class="fw-bold text-dark">HostelEase</span>
                                </div>
                                @foreach([['chart-pie','Dashboard',true],['bed','Bed Matrix',false],['users','Students',false],['file-invoice-dollar','Billing',false]] as [$ic,$lbl,$on])
                                    <div class="p-2 rounded-3 fw-semibold small {{ $on ? 'text-primary' : 'text-secondary' }}" @style(['background: var(--he-primary-soft)' => $on])><i class="fa-solid fa-{{ $ic }} me-2"></i>{{ $lbl }}</div>
                                @endforeach
                            </div>
                            <div class="flex-grow-1 p-4 d-flex flex-column" style="background: var(--he-bg-canvas);">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <div class="fw-bold fs-5 text-dark">{{ __('Overview') }}</div>
                                    <div class="rounded-circle" style="width:32px; height:32px; background: var(--he-bg-surface-raised);"></div>
                                </div>
                                <div class="row g-3 mb-4">
                                    @foreach([['Total Revenue','₹4,52,000','success','↑ 12%'],['Occupancy','94%','primary','141/150'],['Pending Dues','₹18,500','danger','12 students']] as [$l,$v,$c,$s])
                                        <div class="col-4">
                                            <div class="bg-white p-3 rounded-4 shadow-sm border h-100">
                                                <div class="text-muted small text-uppercase fw-bold mb-1" style="font-size:0.6rem;">{{ $l }}</div>
                                                <div class="fw-bold text-dark" style="font-size:1.05rem;">{{ $v }}</div>
                                                <div class="text-{{ $c }} fw-semibold" style="font-size:0.68rem;">{{ $s }}</div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="bg-white rounded-4 shadow-sm border flex-grow-1 p-3 d-flex flex-column">
                                    <div class="fw-bold text-dark mb-3 small">{{ __('Revenue Trend') }}</div>
                                    <div class="flex-grow-1 d-flex align-items-end gap-2 px-1 pb-1">
                                        @foreach([30,50,40,70,55,90] as $h)
                                            <div class="rounded-top flex-grow-1" style="height: {{ $h }}%; background: var(--he-primary); opacity: {{ $loop->last ? 1 : 0.15 + $loop->index * 0.13 }}; {{ $loop->last ? 'box-shadow:0 -5px 15px rgba(79,70,229,.4);' : '' }}"></div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ── Features ── --}}
    <section id="features" class="py-5 my-md-5">
        <div class="container py-4">
            <div class="text-center mb-5">
                <div class="lp-eyebrow mb-2">{{ __('Everything in one place') }}</div>
                <h2 class="lp-h2 mb-3">{{ __('Everything You Need to Scale') }}</h2>
                <p class="lp-lead">{{ __('Stop juggling spreadsheets. Purpose-built tools for modern hostel and PG operators.') }}</p>
            </div>
            <div class="row g-4">
                @foreach([
                    ['bed', __('Visual Bed Matrix'), __('Live occupancy across every room and floor in one interactive board. Never double-book a bed again.')],
                    ['file-invoice-dollar', __('Automated Billing'), __('Invoices generate themselves, dues track automatically, and every payment lands in a clean ledger.')],
                    ['chart-line', __('Deep Insights'), __('Real-time revenue analytics, collection and aging reports — a 360° view of your business.')],
                    ['users', __('Student Management'), __('Digital profiles, documents, and a per-student ledger. Everything about a resident in one place.')],
                    ['shield-halved', __('Deposits & Custody'), __('Security deposits held, settled and refunded to the rupee — every rupee accounted for.')],
                    ['building', __('Multi-Branch'), __('Run several branches from one account, switch in a tap, and renew them together on one date.')],
                ] as [$ic,$t,$d])
                    <div class="col-md-6 col-lg-4">
                        <div class="lp-feat">
                            <div class="lp-feat-ic"><i class="fa-solid fa-{{ $ic }}"></i></div>
                            <h4>{{ $t }}</h4>
                            <p>{{ $d }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ── How it works ── --}}
    <section id="how" class="py-5" style="background: var(--he-bg-surface, #fff);">
        <div class="container py-4">
            <div class="text-center mb-5">
                <div class="lp-eyebrow mb-2">{{ __('Up and running in minutes') }}</div>
                <h2 class="lp-h2">{{ __('How It Works') }}</h2>
            </div>
            <div class="row g-4 justify-content-center">
                @foreach([
                    ['1', __('Create your account'), __('Sign up with your mobile number. Your first branch and a 14-day free trial start instantly.')],
                    ['2', __('Set up your property'), __('Add floors, rooms and beds, then assign students. The visual matrix keeps it all in view.')],
                    ['3', __('Collect & track'), __('Invoices auto-generate, you collect in a tap, and reports show exactly where you stand.')],
                ] as [$n,$t,$d])
                    <div class="col-md-4">
                        <div class="lp-step">
                            <div class="lp-step-n">{{ $n }}</div>
                            <h5 class="fw-bold">{{ $t }}</h5>
                            <p class="text-muted mb-0">{{ $d }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ── Pricing (real config prices) ── --}}
    @php
        $monthly = (float) config('hostelease.subscription_pricing.monthly', 1000);
        $yearly = (float) config('hostelease.subscription_pricing.yearly', 10000);
        $trial = (int) config('hostelease.trial_days', 14);
    @endphp
    <section id="pricing" class="py-5 my-md-4">
        <div class="container py-4">
            <div class="text-center mb-5">
                <div class="lp-eyebrow mb-2">{{ __('Simple & transparent') }}</div>
                <h2 class="lp-h2 mb-3">{{ __('Pricing That Grows With You') }}</h2>
                <p class="lp-lead">{{ __('Billed per branch. Start free for :days days — no credit card required.', ['days' => $trial]) }}</p>
            </div>
            <div class="row justify-content-center g-4">
                <div class="col-lg-4 col-md-6">
                    <div class="lp-price">
                        <h5 class="fw-bold mb-1">{{ __('Monthly') }}</h5>
                        <p class="small text-muted mb-3">{{ __('Flexible, pay as you go') }}</p>
                        <div><span class="display-5 fw-bold">{{ hostelease_money($monthly) }}</span><span class="text-muted"> / {{ __('branch / mo') }}</span></div>
                        <ul>
                            <li><i class="fa-solid fa-check text-success me-2"></i>{{ __('Unlimited students') }}</li>
                            <li><i class="fa-solid fa-check text-success me-2"></i>{{ __('Automated invoicing') }}</li>
                            <li><i class="fa-solid fa-check text-success me-2"></i>{{ __('Financial reports') }}</li>
                            <li class="off"><i class="fa-solid fa-xmark me-2"></i>{{ __('Volume discounts') }}</li>
                        </ul>
                        <a href="{{ route('register') }}" class="lp-btn lp-btn-light w-100 justify-content-center">{{ __('Get Started') }}</a>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="lp-price pop">
                        <span class="lp-price-badge">{{ __('Best Value') }}</span>
                        <h5 class="fw-bold mb-1" style="color: var(--he-primary);">{{ __('Yearly') }}</h5>
                        <p class="small text-muted mb-3">{{ __('For serious operators') }}</p>
                        <div><span class="display-5 fw-bold">{{ hostelease_money($yearly) }}</span><span class="text-muted"> / {{ __('branch / yr') }}</span></div>
                        <ul>
                            <li><i class="fa-solid fa-check text-success me-2"></i>{{ __('Everything in Monthly') }}</li>
                            <li><i class="fa-solid fa-check text-success me-2"></i>{{ __('Two months free vs monthly') }}</li>
                            <li><i class="fa-solid fa-check text-success me-2"></i>{{ __('Volume discounts on more branches') }}</li>
                            <li><i class="fa-solid fa-check text-success me-2"></i>{{ __('Priority support') }}</li>
                        </ul>
                        <a href="{{ route('register') }}" class="lp-btn lp-btn-primary w-100 justify-content-center">{{ __('Start Free Trial') }}</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ── CTA band ── --}}
    <section class="py-5">
        <div class="container">
            <div class="lp-cta lp-dark">
                <div class="lp-hero-mesh" style="opacity:0.5;"></div>
                <div class="position-relative" style="z-index:2;">
                    <h2 class="fw-bold text-white mb-3" style="font-size: clamp(1.8rem, 4vw, 2.6rem); letter-spacing:-1px;">{{ __('Ready to automate your hostel?') }}</h2>
                    <p class="text-white-50 mb-4 mx-auto" style="max-width:560px;">{{ __('Join owners who run their properties from one dashboard. Set up in minutes.') }}</p>
                    <a href="{{ route('register') }}" class="lp-btn lp-btn-light"><i class="fa-solid fa-rocket"></i>{{ __('Start Your Free Trial') }}</a>
                </div>
            </div>
        </div>
    </section>

    {{-- ── Footer ── --}}
    <footer class="lp-footer lp-dark">
        <div class="container text-center">
            <div class="d-flex justify-content-center align-items-center gap-2 mb-4">
                <img src="{{ asset('hostel-ease-icon.svg') }}" alt="" style="height: 30px; filter: grayscale(1) brightness(2);">
                <span class="fs-5 fw-bold text-white">{{ config('app.name', 'HostelEase') }}</span>
            </div>
            <div class="d-flex justify-content-center flex-wrap gap-4 mb-3">
                <a href="#features">{{ __('Features') }}</a>
                <a href="#how">{{ __('How it works') }}</a>
                <a href="#pricing">{{ __('Pricing') }}</a>
                <a href="{{ route('login') }}">{{ __('Log in') }}</a>
            </div>
            <div class="d-flex justify-content-center flex-wrap gap-4 mb-4">
                <a href="{{ route('terms') }}">{{ __('Terms of Service') }}</a>
                <a href="{{ route('privacy') }}">{{ __('Privacy Policy') }}</a>
                <a href="{{ route('refund') }}">{{ __('Refund Policy') }}</a>
            </div>
            <p class="small mb-1">&copy; {{ date('Y') }} {{ config('app.name') }}. {{ __('All rights reserved.') }}</p>
            <p class="small mb-0">{{ __('Powered by') }} <span class="text-white fw-bold">SatvScript</span>.</p>
        </div>
    </footer>

    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
        }
    </script>
</body>
</html>
