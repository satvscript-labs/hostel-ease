<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#2563eb">
    <link rel="icon" href="{{ asset('hsms-icon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('hsms-icon.svg') }}">
    <link rel="manifest" href="/manifest.webmanifest">
    <title>@yield('title', 'Dashboard') · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    <!-- Alpine.js for Liquid UI interactivty -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        /* Ultra Premium CSS Variables */
        :root {
            --he-primary: #4f46e5;
            --he-primary-hover: #4338ca;
            --he-primary-soft: rgba(79, 70, 229, 0.1);

            --he-accent: #9333ea;
            --he-accent-hover: #7e22ce;
            --he-accent-soft: rgba(147, 51, 234, 0.1);

            --he-gradient-mesh: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
            --he-gradient-pop: linear-gradient(135deg, #4f46e5, #9333ea);

            --he-success: #10b981;
            --he-success-soft: #d1fae5;
            --he-warning: #f59e0b;
            --he-warning-soft: #fef3c7;
            --he-danger: #ef4444;
            --he-danger-soft: #fee2e2;
            --he-info: #0ea5e9;
            --he-info-soft: #e0f2fe;

            --he-bg-canvas: #f8fafc;
            --he-bg-surface: #ffffff;

            --he-text-main: #0f172a;
            --he-text-muted: #64748b;
            --he-text-inverse: #ffffff;
        }

        /* Custom Overlay Modal Backdrop */
        .custom-overlay-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(8px);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        /* Custom Overlay Modal Window (Form itself) */
        .custom-overlay-modal {
            width: 100%;
            max-width: 550px;
            background: #fff;
            border-radius: 1.25rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            display: flex;
            flex-direction: column;
            max-height: 85vh;
            transform: scale(0.95);
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            overflow: hidden;
        }

        .custom-overlay-modal.is-open {
            transform: scale(1);
            opacity: 1;
        }

        .custom-overlay-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #fff;
        }

        .custom-overlay-body {
            padding: 1.5rem;
            overflow-y: auto;
            flex-grow: 1;
            background: #fafafa;
        }

        .custom-overlay-footer {
            padding: 1.25rem 1.5rem;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
            background: #fff;
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

        /* Ultra Premium Topbar & Footer Styles */
        .hsms-topbar {
            position: sticky;
            top: 0;
            z-index: 1020;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
            padding: 0.75rem 1.5rem;
            transition: all 0.3s ease;
        }

        .premium-footer {
            background: #ffffff;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
            padding: 1.5rem 0;
            margin-top: auto;
        }

        .premium-footer p {
            margin: 0;
            font-size: 0.8rem;
            letter-spacing: 0.03em;
            color: #64748b;
        }

        .premium-footer .signature {
            font-weight: 600;
            color: #0f172a;
        }

        /* Global Entry Animations */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .stagger-1 { animation: fadeUp 0.6s cubic-bezier(0.25, 1, 0.5, 1) 0.1s both; }
        .stagger-2 { animation: fadeUp 0.6s cubic-bezier(0.25, 1, 0.5, 1) 0.15s both; }
        .stagger-3 { animation: fadeUp 0.6s cubic-bezier(0.25, 1, 0.5, 1) 0.2s both; }
        .stagger-4 { animation: fadeUp 0.6s cubic-bezier(0.25, 1, 0.5, 1) 0.25s both; }
        .stagger-5 { animation: fadeUp 0.6s cubic-bezier(0.25, 1, 0.5, 1) 0.3s both; }
    </style>
    @stack('styles')
</head>

<body>
    @include('partials.sidebar')
    <div class="hsms-backdrop" data-sidebar-backdrop></div>

    <div class="hsms-content d-flex flex-column">
        @include('partials.topbar')

        <main class="flex-grow-1 p-3 p-lg-4">
            @yield('content')
        </main>

        <footer class="premium-footer text-center">
            <p>&copy; {{ date('Y') }} <span class="fw-bold">{{ config('app.name') }}</span>. All rights reserved.</p>
            <p class="mt-1">Powered by <span class="signature">SatvScript</span>.</p>
        </footer>
    </div>

    {{-- Hidden logout form (used by topbar + idle timeout) --}}
    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">@csrf</form>

    <script>
        window.hostelease_SESSION_TIMEOUT = {{ (int) config('hostelease.session_timeout') }};
        window.hostelease_FLASH = @json([
            'type' => session('success') ? 'success' : (session('warning') ? 'warning' : (session('error') ? 'error' : null)),
            'message' => session('success') ?? session('warning') ?? session('error'),
        ]);
    </script>
    @stack('scripts')
</body>

</html>