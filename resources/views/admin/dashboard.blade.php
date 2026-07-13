@extends('layouts.app')
@section('title', __('Command Center'))

@push('styles')
<style>
    /* Ultra-Premium Dashboard Styles */
    .dash-grid {
        display: grid;
        grid-template-columns: repeat(12, 1fr);
        gap: 1.5rem;
    }
    
    /* 1. The Hero Greeting */
    .dash-hero {
        grid-column: span 12;
        border-radius: 1.5rem;
        position: relative;
        overflow: hidden;
        background: linear-gradient(135deg, var(--he-primary) 0%, var(--he-accent) 100%);
        color: white;
        padding: 3rem 2.5rem;
        box-shadow: 0 20px 40px rgba(79, 70, 229, 0.25);
        animation: fadeUp 0.8s cubic-bezier(0.25, 1, 0.5, 1) forwards;
    }
    
    .hero-mesh {
        position: absolute;
        inset: 0;
        opacity: 0.8;
        background-image: 
            radial-gradient(at 80% 0%, rgba(0,0,0,0.3) 0px, transparent 50%),
            radial-gradient(at 0% 50%, rgba(255,255,255,0.1) 0px, transparent 50%),
            radial-gradient(at 80% 100%, rgba(255,255,255,0.15) 0px, transparent 50%);
        z-index: 1;
    }
    .hero-content { position: relative; z-index: 2; }
    
    .hero-badge {
        display: inline-flex;
        align-items: center;
        background: rgba(255, 255, 255, 0.2);
        backdrop-filter: blur(8px);
        padding: 0.5rem 1rem;
        border-radius: 50px;
        font-size: 0.85rem;
        font-weight: 600;
        letter-spacing: 0.5px;
        border: 1px solid rgba(255, 255, 255, 0.3);
        margin-bottom: 1rem;
    }

    /* 2. Quick Action Arsenal */
    .quick-actions-row {
        grid-column: span 12;
        display: flex;
        gap: 1rem;
        overflow-x: auto;
        padding-bottom: 0.5rem;
        /* Hide scrollbar for sleekness */
        scrollbar-width: none; 
        -ms-overflow-style: none;
    }
    .quick-actions-row::-webkit-scrollbar { display: none; }
    
    .action-tile {
        flex: 1;
        min-width: 140px;
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(16px);
        border-radius: 1.25rem;
        padding: 1.5rem 1rem;
        text-align: center;
        text-decoration: none;
        color: var(--he-text-main);
        box-shadow: 0 10px 30px rgba(0,0,0,0.02);
        border: 1px solid rgba(0,0,0,0.02);
        transition: all 0.4s cubic-bezier(0.25, 1, 0.5, 1);
        opacity: 0;
        transform: translateY(20px);
        animation: fadeUp 0.8s cubic-bezier(0.25, 1, 0.5, 1) forwards;
    }
    .action-tile:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 35px rgba(0,0,0,0.06);
        background: #fff;
    }
    .action-icon {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        margin: 0 auto 1rem auto;
        position: relative;
        background: var(--he-bg-canvas);
        color: var(--he-primary);
        transition: transform 0.3s ease;
    }
    .action-tile:hover .action-icon {
        transform: scale(1.1);
        color: #fff;
        background: var(--he-primary);
    }
    .action-icon::after {
        content: ''; position: absolute; inset: 0;
        border-radius: inherit; filter: blur(8px);
        opacity: 0; z-index: -1;
        background: inherit;
        transition: opacity 0.3s ease;
    }
    .action-tile:hover .action-icon::after { opacity: 0.6; }

    /* Staggered Animations */
    .action-tile:nth-child(1) { animation-delay: 0.1s; }
    .action-tile:nth-child(2) { animation-delay: 0.15s; }
    .action-tile:nth-child(3) { animation-delay: 0.2s; }
    .action-tile:nth-child(4) { animation-delay: 0.25s; }
    .action-tile:nth-child(5) { animation-delay: 0.3s; }

    /* 3. Financial Snapshot Bento
       NB: named .dash-card (not .bento-card) on purpose — .bento-card is a
       canonical component in _premium.scss with different radius/shadow;
       reusing that name here would silently shadow the global one (the exact
       two-definitions drift the design law warns against). This is a
       dashboard-local rich card, so it gets its own name. */
    .dash-card {
        background: #fff;
        border-radius: 1.5rem;
        padding: 1.5rem;
        box-shadow: 0 10px 30px rgba(0,0,0,0.03);
        border: 1px solid rgba(0,0,0,0.02);
        opacity: 0;
        transform: translateY(20px);
        animation: fadeUp 0.8s cubic-bezier(0.25, 1, 0.5, 1) forwards;
    }
    .bento-finance { grid-column: span 12; animation-delay: 0.3s; }
    @media(min-width: 992px) { .bento-finance { grid-column: span 8; } }
    
    .finance-metrics {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    .f-metric {
        padding: 1rem;
        border-radius: 1rem;
        background: var(--he-bg-canvas);
        border: 1px solid rgba(0,0,0,0.02);
        min-width: 0; /* let the value shrink/wrap instead of forcing the grid track wider */
    }
    .f-metric-value {
        font-size: var(--he-text-xl);
        line-height: 1.15;
        /* Amounts can reach lakhs/crore (₹99,99,999.00) — never truncate money;
           let it wrap as a last resort instead of overflowing the tile. */
        overflow-wrap: break-word;
        word-break: break-word;
    }

    /* Card header "view all" link — a text pill on desktop, an icon-only
       circle on mobile (see mobile pass) so it can never wrap or crowd the
       title, no matter how narrow the viewport. */
    .dash-link-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        flex-shrink: 0;
        padding: 0.4rem 0.9rem;
        border-radius: var(--he-radius-full);
        background: var(--he-bg-surface-raised);
        color: var(--he-text-main);
        font-size: 0.8rem;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.2s var(--ease-out-expo);
    }
    .dash-link-btn:hover { background: var(--he-primary-soft); color: var(--he-primary); }
    .dash-link-icon { font-size: 0.7rem; opacity: 0.6; }
    .dash-card-header h3 { min-width: 0; }

    /* 4. Operations & Occupancy */
    .bento-pulse { grid-column: span 12; animation-delay: 0.4s; }
    @media(min-width: 992px) { .bento-pulse { grid-column: span 4; } }

    /* Liquid Radial Progress (Apple Watch Rings) */
    .radial-widget {
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        margin-bottom: 1.5rem;
    }
    .svg-ring {
        transform: rotate(-90deg);
        transform-origin: 50% 50%;
    }
    .ring-track { fill: transparent; stroke: #f1f5f9; stroke-width: 12; }
    .ring-progress {
        fill: transparent; stroke-width: 12; stroke-linecap: round;
        transition: stroke-dashoffset 1.5s cubic-bezier(0.25, 1, 0.5, 1);
    }
    .ring-occupied { stroke: url(#grad-occupied); }
    .radial-center {
        position: absolute;
        text-align: center;
        display: flex; flex-direction: column; align-items: center;
    }
    
    .pulse-stat-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem 0;
        border-bottom: 1px solid rgba(0,0,0,0.03);
    }
    .pulse-stat-row:last-child { border-bottom: none; }

    /* 5. Unified Live Feed */
    .bento-feed { grid-column: span 12; animation-delay: 0.5s; }
    
    .timeline-feed {
        position: relative;
        padding-left: 2rem;
    }
    .timeline-feed::before {
        content: '';
        position: absolute;
        left: 7px; top: 0; bottom: 0;
        width: 2px;
        background: var(--he-bg-canvas);
        border-radius: 2px;
    }
    .feed-item {
        position: relative;
        margin-bottom: 1.5rem;
    }
    .feed-item:last-child { margin-bottom: 0; }
    .feed-marker {
        position: absolute;
        left: -2rem; top: 2px;
        width: 16px; height: 16px;
        border-radius: 50%;
        border: 3px solid #fff;
        box-shadow: 0 0 0 1px rgba(0,0,0,0.05);
    }
    .feed-marker.bg-success { box-shadow: 0 0 10px rgba(16, 185, 129, 0.4), 0 0 0 1px rgba(0,0,0,0.05); }
    .feed-marker.bg-warning { box-shadow: 0 0 10px rgba(245, 158, 11, 0.4), 0 0 0 1px rgba(0,0,0,0.05); }
    .feed-marker.bg-info { box-shadow: 0 0 10px rgba(14, 165, 233, 0.4), 0 0 0 1px rgba(0,0,0,0.05); }

    /* Chart skeleton — shown until Chart.js has drawn, so the card never
       "pops" its content in on load (per the design law's skeleton rule). */
    .chart-shell { position: relative; height: 250px; }
    .chart-shell canvas { position: relative; z-index: 1; }
    .chart-skeleton {
        position: absolute;
        inset: 0;
        z-index: 2;
        border-radius: var(--he-radius-md);
        transition: opacity 0.4s var(--ease-out-expo);
    }
    .chart-shell.is-ready .chart-skeleton { opacity: 0; pointer-events: none; }

    /* fadeUp is defined once, canonically, in _premium.scss — not redeclared here. */

    /* ─── Mobile pass (spec 02 §1: no scroll strips, compact density) ─── */
    @media (max-width: 767.98px) {
        .dash-grid { gap: 0.85rem; }
        .dash-hero { padding: 1.75rem 1.25rem; border-radius: 1.15rem; }
        .dash-hero h1 { font-size: 1.6rem; }
        .dash-hero .hero-badge { font-size: 0.75rem; padding: 0.4rem 0.8rem; margin-bottom: 0.75rem; }
        .dash-hero .fs-5 { font-size: 0.95rem !important; }
        .dash-card { padding: 1.1rem; border-radius: 1.15rem; }

        /* Quick actions become a wrapping grid — no horizontal-scroll strip. */
        .quick-actions-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.55rem;
            overflow: visible;
            padding-bottom: 0;
        }
        .action-tile { min-width: 0; padding: 0.8rem 0.4rem; border-radius: 0.9rem; }
        .action-icon { width: 38px; height: 38px; font-size: 0.95rem; margin-bottom: 0.5rem; border-radius: 11px; }
        .action-tile .small { font-size: 0.7rem; line-height: 1.2; }

        /* Card header: title stays one line; the "view all" link collapses
           to an icon-only circle so it never wraps or crowds the title,
           regardless of viewport width. */
        .dash-card-header h3 { font-size: 0.95rem; }
        .dash-link-btn {
            width: 30px;
            height: 30px;
            padding: 0;
            justify-content: center;
        }
        .dash-link-text { display: none; }
        .dash-link-icon { font-size: 0.7rem; opacity: 1; }

        /* Financial metrics go vertical on mobile — a 3-column grid squeezes
           lakh/crore-sized values into ~75px tiles, which forces wrapping no
           matter how small the font gets. A single-column row list (label
           left, value right, matching the .pulse-stat-row pattern already
           used lower on this page) gives each value the card's full width,
           so it never needs to wrap or shrink to an unreadable size. */
        .finance-metrics {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .f-metric {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.65rem 0.85rem;
            border-radius: 0.75rem;
        }
        .f-metric > div:first-child {
            margin-bottom: 0 !important;
            flex: 1 1 auto;
            min-width: 0; /* label may wrap/shrink first — the value never does */
        }
        .f-metric-value {
            font-size: 1rem;
            flex: 0 0 auto;
            white-space: nowrap; /* the value's turn to own the row never comes second */
        }

        .radial-widget svg { width: 132px; height: 132px; }
        .pulse-stat-row { padding: 0.6rem 0; }
    }
</style>
@endpush

@section('content')
<div class="dash-grid">

    {{-- 1. Hero Greeting --}}
    <div class="dash-hero">
        <div class="hero-mesh"></div>
        <div class="hero-content">
            <div class="hero-badge">
                <i class="fa-regular fa-calendar me-2"></i>
                {{ now()->format('l, j M Y') }}
            </div>
            <h1 class="display-5 fw-bold mb-2">{{ $stats['greeting'] }}, Admin!</h1>
            <p class="fs-5 opacity-75 mb-0">{!! $stats['summary'] !!}</p>
        </div>
    </div>

    {{-- 2. Quick Action Arsenal --}}
    <div class="quick-actions-row">
        <a href="{{ route('admin.students.index') }}" class="action-tile">
            <div class="action-icon"><i class="fa-solid fa-indian-rupee-sign"></i></div>
            <div class="fw-bold small">Collect Payment</div>
        </a>
        <a href="{{ route('admin.property.index') }}" class="action-tile">
            <div class="action-icon"><i class="fa-solid fa-bed"></i></div>
            <div class="fw-bold small">Assign Bed</div>
        </a>
        <a href="{{ route('admin.registrations.index') }}" class="action-tile">
            <div class="action-icon"><i class="fa-solid fa-user-plus"></i></div>
            <div class="fw-bold small">New Student</div>
        </a>
        <a href="{{ route('admin.expenses.index') }}" class="action-tile">
            <div class="action-icon text-danger"><i class="fa-solid fa-receipt"></i></div>
            <div class="fw-bold small">Add Expense</div>
        </a>
        <a href="{{ route('admin.frontdesk.index') }}" class="action-tile">
            <div class="action-icon text-warning"><i class="fa-solid fa-triangle-exclamation"></i></div>
            <div class="fw-bold small">Log Complaint</div>
        </a>
    </div>

    {{-- 3. Financial Snapshot Bento --}}
    <div class="dash-card bento-finance">
        <div class="dash-card-header d-flex justify-content-between align-items-center mb-4">
            <h3 class="h5 fw-bold mb-0">Financial Snapshot</h3>
            <a href="{{ route('admin.finance.index') }}" class="dash-link-btn">
                <span class="dash-link-text">View Finances</span>
                <i class="fa-solid fa-chevron-right dash-link-icon"></i>
            </a>
        </div>

        <div class="finance-metrics">
            <div class="f-metric">
                <div class="text-muted small fw-bold text-uppercase mb-1">Monthly Income</div>
                <div class="f-metric-value fw-bold text-success">{{ hostelease_money($stats['monthly_income']) }}</div>
            </div>
            <div class="f-metric">
                <div class="text-muted small fw-bold text-uppercase mb-1">Pending Dues</div>
                <div class="f-metric-value fw-bold text-danger">{{ hostelease_money($stats['pending_fees']) }}</div>
            </div>
            <div class="f-metric">
                <div class="text-muted small fw-bold text-uppercase mb-1">Monthly Expenses</div>
                <div class="f-metric-value fw-bold text-warning">{{ hostelease_money($stats['expenses_month']) }}</div>
            </div>
        </div>
        
        <div class="chart-shell" id="revenueChartShell">
            <div class="chart-skeleton skeleton"></div>
            <canvas id="revenueChart"></canvas>
        </div>
    </div>

    {{-- 4. Operations & Occupancy (Pulse) --}}
    <div class="dash-card bento-pulse">
        <h3 class="h5 fw-bold mb-4">Hostel Pulse</h3>
        
        <div class="radial-widget" x-data="{ pct: 0 }" x-init="setTimeout(() => pct = {{ $stats['occupancy_pct'] }}, 300)">
            <svg width="160" height="160" viewBox="0 0 160 160" class="svg-ring">
                <defs>
                    <linearGradient id="grad-occupied" x1="0%" y1="0%" x2="100%" y2="0%">
                        <stop offset="0%" stop-color="var(--he-primary)" />
                        <stop offset="100%" stop-color="var(--he-accent)" />
                    </linearGradient>
                </defs>
                <circle class="ring-track" cx="80" cy="80" r="70"></circle>
                <!-- Circumference = 2 * pi * 70 = 439.8 -->
                <circle class="ring-progress ring-occupied" cx="80" cy="80" r="70" stroke-dasharray="439.8" :stroke-dashoffset="439.8 - (439.8 * pct) / 100"></circle>
            </svg>
            <div class="radial-center">
                <span class="fs-2 fw-bold" x-text="Math.round(pct) + '%'">0%</span>
                <span class="text-muted small text-uppercase" style="letter-spacing: 1px;">Occupied</span>
            </div>
        </div>
        
        <div class="pulse-stat-row mt-4">
            <div class="d-flex align-items-center">
                <div class="bg-primary-subtle text-primary rounded p-2 me-3"><i class="fa-solid fa-bed"></i></div>
                <div>
                    <div class="fw-bold">{{ $stats['empty_beds'] }} Beds Available</div>
                    <div class="small text-muted">Out of {{ $stats['total_beds'] }} total</div>
                </div>
            </div>
        </div>
        <div class="pulse-stat-row">
            <div class="d-flex align-items-center">
                <div class="bg-warning-subtle text-warning rounded p-2 me-3"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <div>
                    <div class="fw-bold">{{ $stats['unresolved_complaints'] }} Open Complaints</div>
                    <div class="small text-muted">Require your attention</div>
                </div>
            </div>
        </div>
        <div class="pulse-stat-row">
            <div class="d-flex align-items-center">
                <div class="bg-info-subtle text-info rounded p-2 me-3"><i class="fa-solid fa-users"></i></div>
                <div>
                    <div class="fw-bold">{{ $stats['visitors_today'] }} Expected Visitors</div>
                    <div class="small text-muted">For today</div>
                </div>
            </div>
        </div>
    </div>

    {{-- 5. Unified Live Feed --}}
    <div class="dash-card bento-feed">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="h5 fw-bold mb-0">Live Activity Feed</h3>
        </div>
        
        <div class="timeline-feed">
            @forelse($feed as $item)
                <div class="feed-item">
                    <div class="feed-marker bg-{{ $item->color }}"></div>
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="fw-bold">{{ $item->title }}</div>
                            <div class="text-muted small mt-1">{{ $item->desc }}</div>
                        </div>
                        <div class="text-muted small" style="white-space: nowrap;">
                            {{ $item->time->diffForHumans() }}
                        </div>
                    </div>
                </div>
            @empty
                <x-he-empty-state icon="mug-hot" title="No recent activity"
                    subtitle="New payments, check-ins and alerts will show up here as they happen." />
            @endforelse
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Chart.js is already bundled in app.js (window.Chart) — no CDN re-load.
    const canvas = document.getElementById('revenueChart');
    if (!canvas || !window.Chart) return;
    const Chart = window.Chart;
    const ctx = canvas.getContext('2d');

    // Create glowing gradient
    const gradient = ctx.createLinearGradient(0, 0, 0, 300);
    gradient.addColorStop(0, 'rgba(79, 70, 229, 0.3)');
    gradient.addColorStop(1, 'rgba(79, 70, 229, 0.0)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: {!! json_encode($charts['collection_labels']) !!},
            datasets: [{
                label: 'Collection (₹)',
                data: {!! json_encode($charts['collection_values']) !!},
                borderColor: '#4f46e5',
                backgroundColor: gradient,
                borderWidth: 3,
                pointBackgroundColor: '#fff',
                pointBorderColor: '#4f46e5',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6,
                fill: true,
                tension: 0.4 // Smooth curve
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(15, 23, 42, 0.9)',
                    titleFont: { family: 'Plus Jakarta Sans', size: 13 },
                    bodyFont: { family: 'Plus Jakarta Sans', size: 14, weight: 'bold' },
                    padding: 12,
                    cornerRadius: 8,
                    displayColors: false,
                    callbacks: {
                        label: function(context) {
                            return '₹ ' + context.parsed.y.toLocaleString();
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.03)', drawBorder: false },
                    ticks: {
                        font: { family: 'Plus Jakarta Sans', size: 12 },
                        color: '#64748b',
                        callback: function(value) { return '₹' + (value/1000) + 'k'; }
                    }
                },
                x: {
                    grid: { display: false, drawBorder: false },
                    ticks: { font: { family: 'Plus Jakarta Sans', size: 12 }, color: '#64748b' }
                }
            },
            animation: {
                duration: 2000,
                easing: 'easeOutQuart'
            }
        }
    });

    // Chart is drawn — fade the skeleton out.
    document.getElementById('revenueChartShell')?.classList.add('is-ready');
});
</script>
@endpush
