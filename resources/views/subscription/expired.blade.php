<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Subscription Expired · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    @vite(['resources/scss/app.scss'])
</head>
<body class="d-flex align-items-center justify-content-center" style="min-height:100vh;background:#f1f5f9;">
    <div class="card stat-card text-center" style="max-width:480px;">
        <div class="card-body p-5">
            <i class="fa-solid fa-clock-rotate-left text-danger fs-1 mb-3"></i>
            <h1 class="h4 fw-bold">Subscription Expired</h1>
            <p class="text-muted">
                Your hostel's subscription has lapsed. Please contact the administrator
                to renew and restore access to your dashboard.
            </p>
            <p class="mb-4"><strong>SatvScript Support:</strong> support@satvscript.com</p>
            <div class="d-flex gap-2 justify-content-center">
                @if(auth()->user()?->isHostelAdmin())
                    <a href="{{ route('admin.branches.index') }}" class="btn btn-primary"><i class="fa-solid fa-credit-card me-1"></i> Manage Subscriptions</a>
                @endif
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button class="btn btn-outline-secondary"><i class="fa-solid fa-right-from-bracket me-1"></i> Logout</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
