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
        animation: fade-up 0.8s cubic-bezier(0.25, 1, 0.5, 1) forwards;
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
        animation: fade-up 0.8s cubic-bezier(0.25, 1, 0.5, 1) forwards;
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

    /* 3. Financial Snapshot Bento */
    .bento-card {
        background: #fff;
        border-radius: 1.5rem;
        padding: 1.5rem;
        box-shadow: 0 10px 30px rgba(0,0,0,0.03);
        border: 1px solid rgba(0,0,0,0.02);
        opacity: 0;
        transform: translateY(20px);
        animation: fade-up 0.8s cubic-bezier(0.25, 1, 0.5, 1) forwards;
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
    }

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

    @keyframes fade-up {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
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
    <div class="bento-card bento-finance">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="h5 fw-bold mb-0">Financial Snapshot</h3>
            <a href="{{ route('admin.finance.index') }}" class="btn btn-sm btn-light rounded-pill px-3">View Finances</a>
        </div>
        
        <div class="finance-metrics">
            <div class="f-metric">
                <div class="text-muted small fw-bold text-uppercase mb-1">Monthly Income</div>
                <div class="fs-4 fw-bold text-success">{{ hostelease_money($stats['monthly_income']) }}</div>
            </div>
            <div class="f-metric">
                <div class="text-muted small fw-bold text-uppercase mb-1">Pending Dues</div>
                <div class="fs-4 fw-bold text-danger">{{ hostelease_money($stats['pending_fees']) }}</div>
            </div>
            <div class="f-metric">
                <div class="text-muted small fw-bold text-uppercase mb-1">Monthly Expenses</div>
                <div class="fs-4 fw-bold text-warning">{{ hostelease_money($stats['expenses_month']) }}</div>
            </div>
        </div>
        
        <div style="height: 250px; position: relative;">
            <canvas id="revenueChart"></canvas>
        </div>
    </div>

    {{-- 4. Operations & Occupancy (Pulse) --}}
    <div class="bento-card bento-pulse">
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
    <div class="bento-card bento-feed">
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
                <div class="text-muted py-4 text-center">
                    <i class="fa-solid fa-mug-hot d-block fs-3 mb-2 opacity-50"></i>
                    No recent activity.
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('revenueChart').getContext('2d');
    
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
});
</script>
@endpush
