<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Subscription Expired · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    @vite(['resources/scss/app.scss'])
</head>
<body class="d-flex align-items-center justify-content-center p-3" style="min-height:100vh; background: var(--he-bg-canvas);">
    @php
        $activeHostel = \App\Support\Tenant::id() ? \App\Models\Hostel::find(\App\Support\Tenant::id()) : auth()->user()?->hostel;
    @endphp
    <div class="page-enter" style="max-width: 460px; width: 100%;">
        <div class="card-premium text-center overflow-hidden">
            <div class="expired-hero">
                <div class="expired-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
                @if($activeHostel)
                    <div class="expired-branch">
                        <i class="fa-solid fa-hotel me-1"></i>{{ $activeHostel->name }}
                        @if($activeHostel->subscription_end)
                            <span class="opacity-75">· ended {{ $activeHostel->subscription_end->format('d M Y') }}</span>
                        @endif
                    </div>
                @endif
            </div>
            <div class="p-4 p-md-5 pt-4">
                <h1 class="h4 fw-bold mb-2">Subscription expired</h1>
                <p class="text-muted mb-4">
                    Access to this hostel has paused because the subscription lapsed.
                    @if(auth()->user()?->isHostelAdmin())
                        Renew now to restore your dashboard.
                    @else
                        Your access is paused until the hostel owner renews — please reach out to them, or contact support.
                    @endif
                </p>

                <div class="expired-support mb-4">
                    <i class="fa-solid fa-headset text-primary me-2"></i>
                    <span>SatvScript Support · <a href="mailto:support@satvscript.com">support@satvscript.com</a></span>
                </div>

                <div class="d-grid gap-2">
                    @if(auth()->user()?->isHostelAdmin())
                        <a href="{{ route('admin.subscription.index') }}" class="btn btn-primary fw-semibold rounded-pill py-2 tactile-btn">
                            <i class="fa-solid fa-credit-card me-1"></i> Renew subscription
                        </a>
                    @endif
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button class="btn btn-light border fw-semibold rounded-pill py-2 w-100 tactile-btn">
                            <i class="fa-solid fa-right-from-bracket me-1"></i> Logout
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <style>
        .expired-hero {
            background: var(--he-gradient-mesh);
            padding: 2.5rem 1rem 2rem;
            position: relative;
            overflow: hidden;
        }
        .expired-hero::after {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 30% 20%, rgba(147, 51, 234, 0.35), transparent 60%);
        }
        .expired-branch {
            position: relative;
            z-index: 1;
            margin: 1rem auto 0;
            display: inline-flex;
            align-items: center;
            font-size: var(--he-text-xs);
            font-weight: 600;
            color: #fff;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: var(--he-radius-full);
            padding: 0.35rem 0.9rem;
            backdrop-filter: blur(6px);
        }
        .expired-icon {
            width: 72px;
            height: 72px;
            border-radius: 20px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #fca5a5;
            font-size: 1.8rem;
            backdrop-filter: blur(6px);
        }
        .expired-support {
            font-size: var(--he-text-sm);
            color: var(--he-text-muted);
            background: var(--he-bg-surface-raised);
            border-radius: var(--he-radius-md);
            padding: 0.65rem 0.75rem;
        }
        .expired-support a {
            color: var(--he-primary);
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</body>
</html>
