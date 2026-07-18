@props([
    'title' => 'Legal',
    'updated' => null,
])

{{-- Shared chrome for the legal pages (W10). Public, on canonical tokens. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · {{ config('app.name', 'HostelEase') }}</title>
    <meta name="robots" content="index, follow">
    <link rel="icon" href="{{ asset('hostel-ease-icon.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    @vite(['resources/scss/app.scss'])
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--he-bg-canvas, #f8fafc); color: var(--he-text-main, #0f172a); }
        .lg-top { background: var(--he-gradient-mesh, linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%)); color: #fff; padding: 1.1rem 0; position: sticky; top: 0; z-index: 10; }
        .lg-brand { display: flex; align-items: center; gap: 0.55rem; color: #fff; text-decoration: none; font-weight: 800; font-size: 1.1rem; letter-spacing: -0.5px; }
        .lg-back { color: rgba(255, 255, 255, 0.8); text-decoration: none; font-weight: 600; font-size: 0.9rem; }
        .lg-back:hover { color: #fff; }

        .lg-hero { background: var(--he-gradient-mesh, linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%)); color: #fff; padding: 2.5rem 0 3rem; margin-top: -1px; }
        .lg-card { max-width: 860px; margin: -2rem auto 3rem; background: var(--he-bg-surface, #fff); border: 1px solid rgba(0, 0, 0, 0.05); border-radius: var(--he-radius-lg, 16px); box-shadow: var(--he-shadow-md); padding: clamp(1.5rem, 4vw, 3rem); }

        /* Prose */
        .lg-prose { color: var(--he-text-main, #0f172a); }
        .lg-prose h2 { font-size: 1.3rem; font-weight: 800; letter-spacing: -0.4px; margin: 2rem 0 0.75rem; padding-top: 0.5rem; }
        .lg-prose h2:first-child { margin-top: 0; }
        .lg-prose h3 { font-size: 1.02rem; font-weight: 700; margin: 1.4rem 0 0.5rem; }
        .lg-prose p, .lg-prose li { color: var(--he-text-muted, #475569); line-height: 1.75; font-size: 0.95rem; }
        .lg-prose ul { padding-left: 1.2rem; margin-bottom: 1rem; }
        .lg-prose li { margin-bottom: 0.4rem; }
        .lg-prose strong { color: var(--he-text-main, #0f172a); font-weight: 700; }
        .lg-prose a { color: var(--he-primary, #4f46e5); font-weight: 600; text-decoration: none; }
        .lg-prose a:hover { text-decoration: underline; }
        .lg-note { background: var(--he-warning-soft, #fef3c7); border: 1px solid rgba(245, 158, 11, 0.35); border-radius: var(--he-radius-md, 10px); padding: 0.9rem 1.1rem; font-size: 0.85rem; color: #92400e; display: flex; gap: 0.6rem; margin-bottom: 2rem; }

        .lg-toc { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 2rem; }
        .lg-toc a { font-size: 0.82rem; font-weight: 600; color: var(--he-text-muted, #64748b); background: var(--he-bg-surface-raised, #f1f5f9); border-radius: 100px; padding: 0.4rem 0.9rem; text-decoration: none; transition: all 0.2s; }
        .lg-toc a:hover { background: var(--he-primary-soft, rgba(79, 70, 229, 0.1)); color: var(--he-primary, #4f46e5); }

        .lg-footer { text-align: center; padding: 2rem 0 3rem; color: var(--he-text-muted, #94a3b8); font-size: 0.85rem; }
        .lg-footer a { color: var(--he-text-muted, #64748b); text-decoration: none; margin: 0 0.6rem; }
        .lg-footer a:hover { color: var(--he-primary, #4f46e5); }
    </style>
</head>
<body>
    <div class="lg-top">
        <div class="container d-flex align-items-center justify-content-between">
            <a href="{{ url('/') }}" class="lg-brand"><img src="{{ asset('hostel-ease-icon.svg') }}" alt="" style="height: 28px;">{{ config('app.name', 'HostelEase') }}</a>
            <a href="{{ url('/') }}" class="lg-back"><i class="fa-solid fa-arrow-left me-1"></i>{{ __('Back to home') }}</a>
        </div>
    </div>

    <div class="lg-hero">
        <div class="container">
            <div style="max-width: 860px; margin: 0 auto;">
                <h1 class="fw-bold mb-1" style="letter-spacing: -1px;">{{ $title }}</h1>
                @if($updated)<p class="text-white-50 mb-0 small">{{ __('Last updated: :date', ['date' => $updated]) }}</p>@endif
            </div>
        </div>
    </div>

    <div class="container">
        <div class="lg-card">
            <div class="lg-prose">
                {{ $slot }}
            </div>
        </div>
    </div>

    <div class="lg-footer">
        <div class="container">
            <div class="mb-2">
                <a href="{{ route('terms') }}">{{ __('Terms') }}</a>·
                <a href="{{ route('privacy') }}">{{ __('Privacy') }}</a>·
                <a href="{{ route('refund') }}">{{ __('Refunds') }}</a>
            </div>
            &copy; {{ date('Y') }} {{ config('app.name') }}. {{ __('All rights reserved.') }}
        </div>
    </div>
</body>
</html>
