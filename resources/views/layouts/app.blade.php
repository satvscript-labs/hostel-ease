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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
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

        <footer class="text-center text-muted small py-3 border-top">
            &copy; {{ date('Y') }} {{ config('app.name') }} — HSMS by SatvScript.
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

