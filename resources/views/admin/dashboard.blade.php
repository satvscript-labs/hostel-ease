@extends('layouts.app')
@section('title', __('Dashboard'))

@push('styles')
<style>
    /* Ultra-Premium Dashboard Styles */
    .dash-grid {
        display: grid;
        grid-template-columns: repeat(12, 1fr);
        gap: 1.5rem;
    }
    .dash-hero {
        grid-column: span 12;
        border-radius: 1.5rem;
        position: relative;
        overflow: hidden;
        background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
        color: white;
        padding: 3rem 2.5rem;
        box-shadow: 0 20px 40px rgba(30, 27, 75, 0.2);
    }
    @media(min-width: 992px) { .dash-hero { grid-column: span 8; } }
    
    .hero-mesh {
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        opacity: 0.6;
        background-image: 
            radial-gradient(at 80% 0%, hsla(253,16%,7%,1) 0px, transparent 50%),
            radial-gradient(at 0% 50%, hsla(253,16%,7%,1) 0px, transparent 50%),
            radial-gradient(at 80% 100%, hsla(242,100%,70%,0.3) 0px, transparent 50%),
            radial-gradient(at 0% 0%, hsla(343,100%,76%,0.2) 0px, transparent 50%);
        z-index: 1;
    }
    .hero-content { position: relative; z-index: 2; }
    
    /* Apple Watch Style Radial Rings */
    .radial-widget {
        grid-column: span 12;
        background: #fff;
        border-radius: 1.5rem;
        padding: 2rem;
        box-shadow: 0 10px 30px rgba(0,0,0,0.03);
        border: 1px solid rgba(0,0,0,0.02);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    @media(min-width: 992px) { .radial-widget { grid-column: span 4; } }
    
    .svg-ring {
        transform: rotate(-90deg);
        transform-origin: 50% 50%;
    }
    .ring-track {
        fill: transparent;
        stroke: #f1f5f9;
        stroke-width: 12;
    }
    .ring-progress {
        fill: transparent;
        stroke-width: 12;
        stroke-linecap: round;
        transition: stroke-dashoffset 1.5s cubic-bezier(0.25, 1, 0.5, 1);
    }
    .ring-occupied { stroke: url(#grad-occupied); }
    .ring-empty { stroke: url(#grad-empty); }
    .ring-reserved { stroke: url(#grad-reserved); }
    
    /* Floating Glass Tiles */
    .glass-tile {
        grid-column: span 6;
        background: #fff;
        border-radius: 1.25rem;
        padding: 1.5rem;
        box-shadow: 0 10px 30px rgba(0,0,0,0.02);
        border: 1px solid rgba(0,0,0,0.02);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    @media(min-width: 768px) { .glass-tile { grid-column: span 4; } }
    @media(min-width: 1200px) { .glass-tile { grid-column: span 2; } }
    
    .glass-tile:hover {
        transform: translateY(-4px);
        box-shadow: 0 15px 35px rgba(0,0,0,0.05);
    }
    
    .tile-icon-wrapper {
        width: 48px; height: 48px;
        border-radius: 14px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.25rem;
        margin-bottom: 1rem;
        position: relative;
    }
    .tile-icon-wrapper::after {
        content: ''; position: absolute; inset: 0;
        border-radius: inherit; filter: blur(8px);
        opacity: 0.5; z-index: -1;
    }
    
    /* Chart Component */
    .chart-widget {
        grid-column: span 12;
        background: #fff;
        border-radius: 1.5rem;
        padding: 2rem;
        box-shadow: 0 10px 30px rgba(0,0,0,0.03);
        border: 1px solid rgba(0,0,0,0.02);
    }
    @media(min-width: 992px) { .chart-widget { grid-column: span 8; } }
    
    /* Timeline Feed */
    .feed-widget {
        grid-column: span 12;
        background: #fff;
        border-radius: 1.5rem;
        padding: 2rem;
        box-shadow: 0 10px 30px rgba(0,0,0,0.03);
        border: 1px solid rgba(0,0,0,0.02);
    }
    @media(min-width: 992px) { .feed-widget { grid-column: span 4; } }
    
    .timeline-item {
        position: relative;
        padding-left: 2rem;
        padding-bottom: 1.5rem;
        border-left: 2px solid #f1f5f9;
    }
    .timeline-item:last-child { border-left-color: transparent; padding-bottom: 0; }
    .timeline-icon {
        position: absolute;
        left: -11px; top: 0;
        width: 20px; height: 20px;
        border-radius: 50%;
        background: #fff;
        border: 2px solid var(--bs-primary);
        display: flex; align-items: center; justify-content: center;
    }
</style>
@endpush

@section('content')
<div class="page-enter">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold text-dark mb-1">{{ __('Dashboard Overview') }}</h1>
            <p class="text-muted mb-0">{{ __('Welcome back, here\'s what is happening today.') }}</p>
        </div>
        <div class="text-end d-none d-md-block">
            <div class="fw-bold fs-5">{{ now()->format('l, jS M Y') }}</div>
        </div>
    </div>

    <div class="dash-grid">
        <!-- Hero Income Card -->
        <div class="dash-hero d-flex flex-column justify-content-center">
            <div class="hero-mesh"></div>
            <div class="hero-content">
                <div class="d-flex justify-content-between align-items-start mb-4">
                    <div class="bg-white bg-opacity-10 rounded-pill px-3 py-1 text-white small fw-semibold backdrop-blur" style="backdrop-filter: blur(8px);">
                        <i class="fa-solid fa-wallet me-2 text-warning"></i> {{ __('Total Income · ') }} {{ now()->format('F Y') }}
                    </div>
                </div>
                <div class="display-3 fw-bold mb-2 text-white" style="letter-spacing: -1px;">
                    {{ hostelease_money($stats['monthly_income']) }}
                </div>
                <div class="d-flex gap-4 text-white opacity-75">
                    <div>
                        <div class="fw-semibold">{{ $stats['students'] }}</div>
                        <div class="small text-uppercase tracking-wider" style="font-size:0.7rem;letter-spacing:1px;">{{ __('Total Students') }}</div>
                    </div>
                    <div class="border-start border-white border-opacity-25 ps-4">
                        <div class="fw-semibold">{{ $stats['occupancy_pct'] }}%</div>
                        <div class="small text-uppercase tracking-wider" style="font-size:0.7rem;letter-spacing:1px;">{{ __('Hostel Occupancy') }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Custom Radial Rings: Bed Occupancy -->
        @php
            $tot = max(1, $stats['total_beds']);
            $occPct = round(($charts['occupancy']['occupied'] / $tot) * 100);
            $empPct = round(($charts['occupancy']['empty'] / $tot) * 100);
            $resPct = round(($charts['occupancy']['reserved'] / $tot) * 100);
            
            // Circumferences (2 * pi * r)
            // Ring 1 (Occupied): r=90 -> c=565.48
            $c1 = 565.48; $o1 = $c1 - ($occPct / 100 * $c1);
            // Ring 2 (Empty): r=70 -> c=439.82
            $c2 = 439.82; $o2 = $c2 - ($empPct / 100 * $c2);
            // Ring 3 (Reserved): r=50 -> c=314.15
            $c3 = 314.15; $o3 = $c3 - ($resPct / 100 * $c3);
        @endphp
        <div class="radial-widget">
            <div>
                <h5 class="fw-bold mb-0">{{ __('Bed Capacity Matrix') }}</h5>
                <p class="text-muted small mb-0">{{ __('Real-time occupancy visualization') }}</p>
            </div>
            
            <div class="d-flex justify-content-center my-4 position-relative">
                <svg width="220" height="220" viewBox="0 0 220 220" class="svg-ring">
                    <defs>
                        <linearGradient id="grad-occupied" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#4f46e5" />
                            <stop offset="100%" stop-color="#7c3aed" />
                        </linearGradient>
                        <linearGradient id="grad-empty" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#10b981" />
                            <stop offset="100%" stop-color="#34d399" />
                        </linearGradient>
                        <linearGradient id="grad-reserved" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#f59e0b" />
                            <stop offset="100%" stop-color="#fbbf24" />
                        </linearGradient>
                    </defs>
                    
                    <!-- Tracks -->
                    <circle class="ring-track" cx="110" cy="110" r="90"></circle>
                    <circle class="ring-track" cx="110" cy="110" r="70"></circle>
                    <circle class="ring-track" cx="110" cy="110" r="50"></circle>
                    
                    <!-- Progress Rings -->
                    <circle class="ring-progress ring-occupied" cx="110" cy="110" r="90" stroke-dasharray="{{ $c1 }}" stroke-dashoffset="{{ $c1 }}" data-target="{{ $o1 }}"></circle>
                    <circle class="ring-progress ring-empty" cx="110" cy="110" r="70" stroke-dasharray="{{ $c2 }}" stroke-dashoffset="{{ $c2 }}" data-target="{{ $o2 }}"></circle>
                    <circle class="ring-progress ring-reserved" cx="110" cy="110" r="50" stroke-dasharray="{{ $c3 }}" stroke-dashoffset="{{ $c3 }}" data-target="{{ $o3 }}"></circle>
                </svg>
                
                <div class="position-absolute top-50 start-50 translate-middle text-center mt-1">
                    <span class="d-block display-6 fw-bold" style="color: #4f46e5">{{ $stats['total_beds'] }}</span>
                    <span class="d-block small text-muted text-uppercase fw-semibold" style="font-size:0.65rem;letter-spacing:1px;">{{ __('Total') }}</span>
                </div>
            </div>
            
            <div class="d-flex justify-content-between small fw-semibold">
                <div class="text-center">
                    <div style="color: #6d28d9"><i class="fa-solid fa-circle me-1" style="font-size:8px"></i>{{ $charts['occupancy']['occupied'] }}</div>
                    <div class="text-muted" style="font-size:0.7rem">{{ __('Occupied') }}</div>
                </div>
                <div class="text-center">
                    <div class="text-success"><i class="fa-solid fa-circle me-1" style="font-size:8px"></i>{{ $charts['occupancy']['empty'] }}</div>
                    <div class="text-muted" style="font-size:0.7rem">{{ __('Empty') }}</div>
                </div>
                <div class="text-center">
                    <div class="text-warning"><i class="fa-solid fa-circle me-1" style="font-size:8px"></i>{{ $charts['occupancy']['reserved'] }}</div>
                    <div class="text-muted" style="font-size:0.7rem">{{ __('Reserved') }}</div>
                </div>
            </div>
        </div>

        <!-- Floating Glass Tiles -->
        @php
            $tiles = [
                ['Total Rooms', $stats['total_rooms'], 'fa-door-open', 'indigo', 'bg-indigo-subtle', '#4f46e5'],
                ['Pending Fees', hostelease_money($stats['pending_fees']), 'fa-hourglass-half', 'danger', 'bg-danger-subtle', '#ef4444'],
                ['AC Pending', hostelease_money($stats['ac_pending']), 'fa-snowflake', 'info', 'bg-info-subtle', '#0ea5e9'],
            ];
            if(count($tiles) < 6) {
                $tiles[] = ['Students', $stats['students'], 'fa-user-graduate', 'primary', 'bg-primary-subtle', '#2563eb'];
                $tiles[] = ['Occupied Beds', $stats['occupied_beds'], 'fa-bed', 'purple', 'bg-purple-subtle', '#9333ea'];
                $tiles[] = ['Empty Beds', $stats['empty_beds'], 'fa-bed-pulse', 'success', 'bg-success-subtle', '#10b981'];
            }
        @endphp
        
        @foreach($tiles as [$label, $value, $icon, $colorName, $bgClass, $hex])
            <div class="glass-tile d-flex flex-column justify-content-between">
                <div class="tile-icon-wrapper text-white" style="background: linear-gradient(135deg, {{ $hex }}, {{ $hex }}cc);">
                    <i class="fa-solid {{ $icon }}"></i>
                    <!-- Hack for pseudo-element shadow color -->
                    <style>.tile-icon-wrapper:hover::after { background: {{ $hex }}; }</style>
                </div>
                <div>
                    <h4 class="fw-bold mb-1 text-dark">{{ $value }}</h4>
                    <span class="text-muted small fw-semibold">{{ $label }}</span>
                </div>
            </div>
        @endforeach

        <!-- Glowing Area Chart -->
        <div class="chart-widget">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h5 class="fw-bold mb-0">{{ __('Revenue Analytics') }}</h5>
                    <p class="text-muted small mb-0">{{ __('Monthly collection trends over the last 6 months') }}</p>
                </div>
            </div>
            <div style="height: 300px; position: relative;">
                <canvas id="collectionChart"></canvas>
            </div>
        </div>

        <!-- Timeline Feed (Leaving Soon) -->
        <div class="feed-widget">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold mb-0">{{ __('Action Hub') }}</h5>
                <span class="badge bg-danger-subtle text-danger rounded-pill">{{ count($alerts['leaving_soon']) }} {{ __('Alerts') }}</span>
            </div>
            
            <div class="mt-3">
                <div class="text-uppercase small fw-bold text-muted mb-3" style="letter-spacing: 1px;">{{ __('Leaving in 7 Days') }}</div>
                @forelse($alerts['leaving_soon'] as $s)
                    <div class="timeline-item">
                        <div class="timeline-icon text-warning border-warning bg-warning-subtle">
                            <i class="fa-solid fa-person-walking-arrow-right" style="font-size: 0.6rem;"></i>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-bold text-dark fs-6">{{ $s->name }}</div>
                                <div class="small text-muted">{{ optional($s->activeAssignment?->bed?->room)->room_number ?? 'Unknown Room' }}</div>
                            </div>
                            <span class="badge bg-warning text-dark rounded-pill">{{ optional($s->leave_date)->format('d M') }}</span>
                        </div>
                    </div>
                @empty
                    <div class="text-center p-4">
                        <i class="fa-solid fa-calendar-check fs-2 text-success opacity-50 mb-2"></i>
                        <div class="text-muted small fw-semibold">{{ __('No students leaving soon.') }}</div>
                    </div>
                @endforelse
                
                <hr class="my-4 border-light">
                
                <div class="text-uppercase small fw-bold text-muted mb-3" style="letter-spacing: 1px;">{{ __('Quick Alerts') }}</div>
                
                @if($alerts['empty_beds'] > 0)
                <div class="d-flex align-items-start gap-3 mb-3 p-3 bg-success-subtle rounded-3">
                    <div class="text-success mt-1"><i class="fa-solid fa-bed"></i></div>
                    <div>
                        <div class="fw-bold text-success-emphasis" style="font-size:0.9rem">{{ $alerts['empty_beds'] }} {{ __('Beds Available') }}</div>
                        <div class="small text-success">{{ __('Ready for new check-ins.') }}</div>
                    </div>
                </div>
                @endif
                
                @if($stats['pending_fees'] > 0)
                <div class="d-flex align-items-start gap-3 mb-3 p-3 bg-danger-subtle rounded-3">
                    <div class="text-danger mt-1"><i class="fa-solid fa-triangle-exclamation"></i></div>
                    <div>
                        <div class="fw-bold text-danger-emphasis" style="font-size:0.9rem">{{ hostelease_money($stats['pending_fees']) }} {{ __('Pending') }}</div>
                        <div class="small text-danger">{{ __('Outstanding fee collections.') }}</div>
                    </div>
                </div>
                @endif
                
            </div>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Trigger SVG radial animations
        setTimeout(() => {
            document.querySelectorAll('.ring-progress').forEach(el => {
                el.style.strokeDashoffset = el.getAttribute('data-target');
            });
        }, 100);

        // Chart.js Configuration
        const ctx = document.getElementById('collectionChart').getContext('2d');
        
        // Create Neon Purple/Indigo Glowing Gradient
        const gradient = ctx.createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, 'rgba(79, 70, 229, 0.5)'); // Indigo-600 with opacity
        gradient.addColorStop(1, 'rgba(79, 70, 229, 0.0)'); // Transparent
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: @json($charts['collection_labels']),
                datasets: [{
                    label: 'Collection (₹)',
                    data: @json($charts['collection_values']),
                    borderColor: '#4f46e5',
                    borderWidth: 3,
                    backgroundColor: gradient,
                    fill: true,
                    tension: 0.4, // Smooth curves
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#4f46e5',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        titleFont: { size: 13, family: 'Inter' },
                        bodyFont: { size: 14, weight: 'bold', family: 'Inter' },
                        padding: 12,
                        displayColors: false,
                        cornerRadius: 8,
                        callbacks: {
                            label: function(context) {
                                return '₹ ' + context.parsed.y.toLocaleString('en-IN');
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false, drawBorder: false },
                        ticks: { font: { family: 'Inter' }, color: '#64748b' }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0,0,0,0.04)', drawBorder: false },
                        border: { display: false },
                        ticks: {
                            font: { family: 'Inter' },
                            color: '#94a3b8',
                            callback: function(value) {
                                if (value >= 100000) return '₹' + (value / 100000).toFixed(1) + 'L';
                                if (value >= 1000) return '₹' + (value / 1000).toFixed(1) + 'k';
                                return '₹' + value;
                            }
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index',
                },
            }
        });
    });
</script>
@endpush
