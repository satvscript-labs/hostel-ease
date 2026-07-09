<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#2563eb">
    <link rel="icon" href="{{ asset('hostel-ease-icon.svg') }}" type="image/svg+xml">
    
    <!-- SEO -->
    <title>Login · {{ config('app.name', 'HostelEase') }} SaaS</title>
    <meta name="description" content="Sign in to your HostelEase dashboard to manage your hostel, track payments, and view real-time occupancy insights.">
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    
    <style>
        /* Ultra Premium Split Screen Login */
        body, html { height: 100%; margin: 0; font-family: 'Plus Jakarta Sans', sans-serif; background: #ffffff; }
        
        .split-layout {
            display: flex; min-height: 100vh; flex-direction: column;
        }
        
        .form-side {
            width: 100%; display: flex; flex-direction: column; justify-content: center;
            padding: 2rem 1.5rem; position: relative; z-index: 2;
            background: #ffffff; flex-grow: 1;
        }
        
        .image-side {
            display: none; position: relative; overflow: hidden;
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
            align-items: center; justify-content: center;
        }
        @media(min-width: 992px) {
            .split-layout { flex-direction: row; }
            .image-side { display: flex; flex-grow: 1; }
            .form-side { width: 45%; max-width: 550px; padding: 3rem 4rem; flex-grow: 0; }
        }
        
        /* Abstract Glassmorphism Overlay */
        .glass-overlay {
            position: absolute; inset: 0;
            background: radial-gradient(circle at top right, rgba(147, 51, 234, 0.4) 0%, transparent 60%),
                        radial-gradient(circle at bottom left, rgba(79, 70, 229, 0.4) 0%, transparent 60%);
            z-index: 1; filter: blur(40px);
        }
        
        .quote-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 1.5rem; color: #ffffff;
            position: relative; z-index: 2;
            box-shadow: 0 25px 50px rgba(0,0,0,0.2);
            transform: translateY(20px); opacity: 0;
            animation: slideUp 0.8s cubic-bezier(0.25, 1, 0.5, 1) forwards 0.2s;
        }
        
        @keyframes slideUp {
            to { transform: translateY(0); opacity: 1; }
        }
        
        /* Modern Floating Input */
        .premium-input-group { position: relative; margin-bottom: 1.5rem; }
        .premium-input {
            width: 100%; background: #f8fafc; border: 1px solid rgba(0,0,0,0.05);
            border-radius: 0.75rem; padding: 1rem 1.25rem; font-size: 0.95rem;
            color: #0f172a; transition: all 0.3s ease;
        }
        .premium-input:focus {
            background: #ffffff; border-color: #4f46e5; outline: none;
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        }
        .input-icon {
            position: absolute; right: 1.25rem; top: 50%; transform: translateY(-50%);
            color: #94a3b8; transition: color 0.3s ease;
        }
        .premium-input:focus ~ .input-icon { color: #4f46e5; }
        
        .btn-neon {
            background: linear-gradient(135deg, #4f46e5, #9333ea);
            color: white; border: none; font-weight: 600; font-size: 1rem;
            padding: 1rem; border-radius: 0.75rem;
            box-shadow: 0 10px 20px rgba(79, 70, 229, 0.25);
            transition: all 0.3s ease; width: 100%;
        }
        .btn-neon:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px rgba(79, 70, 229, 0.4); color: white;
        }

        .abstract-chart-bar {
            flex-grow: 1; border-radius: 6px 6px 0 0;
            transition: height 1s ease;
        }
    </style>
</head>
<body>
    @php
    $features = [
        [
            'title' => 'Visual Bed Matrix',
            'desc' => 'Say goodbye to confusing spreadsheets. Monitor live occupancy across all your rooms in real-time.',
            'icon' => 'fa-solid fa-bed',
            'color' => 'primary',
            'stat_label' => 'Live Occupancy',
            'stat_value' => '92%',
            'stat_sub' => 'Filled',
            'badge_icon' => 'fa-bed',
            'badge_text' => '138/150',
            'bars' => [40, 60, 45, 80, 70, 100],
        ],
        [
            'title' => 'Automated Rent Collection',
            'desc' => 'System automatically generates invoices and tracks pending dues so you get paid faster.',
            'icon' => 'fa-solid fa-bolt',
            'color' => 'success',
            'stat_label' => 'Monthly Revenue',
            'stat_value' => '₹2,45,000',
            'stat_sub' => 'This Month',
            'badge_icon' => 'fa-arrow-trend-up',
            'badge_text' => '+14.5%',
            'bars' => [30, 50, 40, 70, 85, 100],
        ],
        [
            'title' => 'Deep Financial Insights',
            'desc' => 'Make data-driven decisions with real-time revenue analytics and collection charts.',
            'icon' => 'fa-solid fa-chart-line',
            'color' => 'info',
            'stat_label' => 'Collection Rate',
            'stat_value' => '98.5%',
            'stat_sub' => 'Avg',
            'badge_icon' => 'fa-check-double',
            'badge_text' => 'Excellent',
            'bars' => [80, 85, 90, 88, 95, 98],
        ],
        [
            'title' => 'Effortless Student Management',
            'desc' => 'Maintain digital profiles, manage documents, and track individual payment ledgers easily.',
            'icon' => 'fa-solid fa-users',
            'color' => 'warning',
            'stat_label' => 'Active Students',
            'stat_value' => '142',
            'stat_sub' => 'Enrolled',
            'badge_icon' => 'fa-user-plus',
            'badge_text' => 'New +12',
            'bars' => [50, 60, 75, 90, 110, 142],
        ],
        [
            'title' => 'Comprehensive Reports',
            'desc' => 'Export one-click financial, occupancy, and defaulter reports for complete visibility.',
            'icon' => 'fa-solid fa-file-invoice',
            'color' => 'danger',
            'stat_label' => 'Pending Dues',
            'stat_value' => '₹12,500',
            'stat_sub' => 'Total',
            'badge_icon' => 'fa-clock',
            'badge_text' => 'Follow up',
            'bars' => [100, 80, 60, 45, 30, 15], // Going down is good for dues
        ],
    ];
    $feature = collect($features)->random();
    @endphp

    <div class="split-layout">
        
        <!-- Left Side: Login Form -->
        <div class="form-side">
            <div class="d-flex align-items-center gap-2 mb-5 pb-3">
                <img src="{{ asset('hostel-ease-icon.svg') }}" alt="Logo" style="height: 36px;">
                <span class="fs-4 fw-bold text-dark tracking-tight">{{ config('app.name', 'HostelEase') }}</span>
            </div>
            
            <div class="mb-5">
                <h1 class="fw-bold text-dark mb-2" style="letter-spacing: -1px; font-size: 2.25rem;">Welcome back</h1>
                <p class="text-muted">Enter your credentials to access your dashboard.</p>
            </div>
            
            @if(session('error'))
                <div class="alert alert-danger bg-danger-subtle text-danger-emphasis border-0 rounded-3 mb-4 d-flex align-items-center gap-2 shadow-sm">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <div>{{ session('error') }}</div>
                </div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger bg-danger-subtle text-danger-emphasis border-0 rounded-3 mb-4 d-flex align-items-center gap-2 shadow-sm">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <div>{{ $errors->first() }}</div>
                </div>
            @endif

            <form method="POST" action="{{ route('login.attempt') }}">
                @csrf
                <div class="premium-input-group">
                    <label class="form-label small fw-bold text-muted text-uppercase mb-2" style="letter-spacing: 0.5px;">{{ __('Mobile Number') }}</label>
                    <div class="d-flex gap-2">
                        <div class="d-flex align-items-center justify-content-center bg-light border rounded-3 px-3 fw-bold text-secondary" style="border-radius: 0.75rem !important;">
                            +91
                        </div>
                        <div class="position-relative flex-grow-1">
                            <input type="tel" name="mobile" value="{{ old('mobile') }}"
                                   class="premium-input" inputmode="numeric" maxlength="10"
                                   placeholder="10-digit mobile number" required autofocus>
                            <i class="fa-solid fa-mobile-screen input-icon"></i>
                        </div>
                    </div>
                </div>
                
                <div class="premium-input-group">
                    <label class="form-label small fw-bold text-muted text-uppercase mb-2" style="letter-spacing: 0.5px;">{{ __('Password') }}</label>
                    <input type="password" name="password" class="premium-input" placeholder="••••••••" required>
                    <i class="fa-solid fa-lock input-icon"></i>
                </div>
                
                <div class="d-flex justify-content-between align-items-center mb-5">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember">
                        <label class="form-check-label small fw-semibold text-muted" for="remember">{{ __('Remember me') }}</label>
                    </div>
                    <a href="#" class="small fw-bold text-primary text-decoration-none">{{ __('Forgot Password?') }}</a>
                </div>
                
                <button type="submit" class="btn btn-neon mb-4">
                    {{ __('Sign In to Dashboard') }} <i class="fa-solid fa-arrow-right ms-2"></i>
                </button>
                
                <div class="text-center">
                    <span class="text-muted small">Don't have an account?</span>
                    <a href="{{ route('register') }}" class="small fw-bold text-primary text-decoration-none ms-1">Sign up for free</a>
                </div>
            </form>
            
            <div class="mt-auto pt-5">
                <div class="d-flex gap-3 justify-content-center">
                    @foreach(config('app.available_locales') as $code => $label)
                        <a href="{{ route('locale.switch', $code) }}" class="small text-decoration-none {{ app()->getLocale() === $code ? 'fw-bold text-dark' : 'text-muted' }}">{{ $label }}</a>
                        @if(! $loop->last)<span class="text-muted opacity-25">|</span>@endif
                    @endforeach
                </div>
            </div>
        </div>
        
        <!-- Right Side: Visuals (Hidden on mobile) -->
        <div class="image-side">
            <div class="glass-overlay"></div>
            
            <div class="quote-card p-0 overflow-hidden" style="width: 450px;">
                <div class="p-4 border-bottom border-white border-opacity-10 d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-white opacity-75 small fw-semibold text-uppercase tracking-wider mb-1">{{ $feature['stat_label'] }}</div>
                        <div class="fs-2 fw-bold text-white">{{ $feature['stat_value'] }} <span class="fs-5 text-white opacity-50 fw-normal">{{ $feature['stat_sub'] }}</span></div>
                    </div>
                    <div class="badge bg-{{ $feature['color'] }} bg-opacity-25 text-white rounded-pill px-3 py-2 border border-{{ $feature['color'] }} border-opacity-50">
                        <i class="{{ $feature['badge_icon'] }} me-1"></i> {{ $feature['badge_text'] }}
                    </div>
                </div>
                
                <div class="p-4" style="height: 180px; display: flex; align-items: flex-end; gap: 8px;">
                    <!-- Abstract Dynamic Chart -->
                    @foreach($feature['bars'] as $height)
                        <div class="abstract-chart-bar {{ $loop->last ? 'bg-'.$feature['color'].' shadow' : 'bg-white bg-opacity-10' }} position-relative" style="height: {{ $height }}%;">
                            @if($loop->last)
                                <div class="position-absolute top-0 start-50 translate-middle badge bg-white text-{{ $feature['color'] }} shadow-sm rounded-pill px-2 py-1" style="font-size: 0.7rem; margin-top: -15px;">Latest</div>
                            @endif
                        </div>
                    @endforeach
                </div>
                
                <div class="p-4 bg-white bg-opacity-10 border-top border-white border-opacity-10">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-white bg-opacity-25 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                            <i class="{{ $feature['icon'] }} text-white"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-white fs-6">{{ $feature['title'] }}</div>
                            <div class="text-white opacity-75 small">{{ $feature['desc'] }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
    
    <!-- PWA Service Worker Registration -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js').catch(err => {
                    console.log('ServiceWorker registration failed: ', err);
                });
            });
        }
    </script>
</body>
</html>
