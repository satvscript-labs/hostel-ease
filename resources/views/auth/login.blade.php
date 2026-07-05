<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#2563eb">
    <link rel="icon" href="{{ asset('hsms-icon.svg') }}" type="image/svg+xml">
    <title>Login · {{ config('app.name') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body class="d-flex align-items-center justify-content-center" style="min-height:100vh;background:linear-gradient(135deg,#1e3a8a,#2563eb);">
    <div class="card stat-card border-0 shadow-lg" style="width:100%;max-width:400px;">
        <div class="card-body p-4 p-sm-5">
            <div class="text-center mb-4">
                <img src="{{ asset('hsms-icon.svg') }}" alt="HSMS" style="width:72px;height:72px;border-radius:18px;box-shadow:0 8px 22px rgba(37,99,235,.3)">
                <h1 class="h4 fw-bold mt-3 mb-0">{{ config('app.name') }}</h1>
                <p class="text-muted small">Hostel Management System</p>
            </div>

            @if($errors->any())
                <div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('login.attempt') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label small fw-semibold">{{ __('Mobile Number') }}</label>
                    <div class="input-group">
                        <span class="input-group-text">+91</span>
                        <input type="tel" name="mobile" value="{{ old('mobile') }}"
                               class="form-control" inputmode="numeric" maxlength="10"
                               placeholder="10-digit mobile" required autofocus>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">{{ __('Password') }}</label>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="remember" id="remember">
                    <label class="form-check-label small" for="remember">{{ __('Remember me') }}</label>
                </div>
                <button type="submit" class="btn btn-primary w-100 fw-semibold">
                    <i class="fa-solid fa-right-to-bracket me-1"></i> {{ __('Sign In') }}
                </button>
            </form>

            <div class="text-center mt-3">
                @foreach(config('app.available_locales') as $code => $label)
                    <a href="{{ route('locale.switch', $code) }}" class="small text-decoration-none {{ app()->getLocale() === $code ? 'fw-bold' : 'text-muted' }}">{{ $label }}</a>
                    @if(! $loop->last)<span class="text-muted">·</span>@endif
                @endforeach
            </div>
        </div>
    </div>
</body>
</html>
