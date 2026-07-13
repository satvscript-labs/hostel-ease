@extends('layouts.app')
@section('title', 'Superadmin Dashboard')

@push('styles')
<style>
    /* Premium Dashboard Aesthetic */
    .stat-card {
        background: rgba(255,255,255,0.85);
        backdrop-filter: blur(20px);
        border: 1px solid rgba(0,0,0,0.05);
        transition: all 0.3s cubic-bezier(0.25, 1, 0.5, 1);
    }
    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 30px rgba(0,0,0,0.04) !important;
    }
    .stat-value {
        font-size: 1.75rem;
        font-weight: 800;
        letter-spacing: -0.5px;
    }
    .stat-label {
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #64748b;
    }
</style>
@endpush

@php
    $cards = [
        ['label' => 'Total Hostels', 'value' => $stats['total_hostels'], 'icon' => 'fa-hotel', 'bg' => 'primary', 'desc' => 'Total branches managed'],
        ['label' => 'Active Hostels', 'value' => $stats['active_hostels'], 'icon' => 'fa-circle-check', 'bg' => 'success', 'desc' => 'Paying subscriptions'],
        ['label' => 'Due Renewals', 'value' => $stats['due_renewals'], 'icon' => 'fa-bell', 'bg' => 'warning', 'desc' => 'Expiring within 30 days'],
        ['label' => 'Total Students', 'value' => $stats['total_students'], 'icon' => 'fa-users', 'bg' => 'info', 'desc' => 'Across all hostels'],
        ['label' => 'Total Revenue', 'value' => hostelease_money($stats['total_income']), 'icon' => 'fa-sack-dollar', 'bg' => 'success', 'desc' => 'Lifetime earnings'],
        ['label' => 'Monthly Revenue', 'value' => hostelease_money($stats['monthly_revenue']), 'icon' => 'fa-chart-line', 'bg' => 'primary', 'desc' => 'Earnings this month'],
    ];
@endphp

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1 text-dark tracking-tight">Superadmin Dashboard</h1>
        <p class="text-muted mb-0 small">Platform overview, revenue, and active subscriptions.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('superadmin.accounts.index') }}" class="btn btn-primary shadow-sm rounded-pill px-4">
            <i class="fa-solid fa-users-gear me-2"></i> Customers
        </a>
    </div>
</div>

<!-- Bento Box Metric Cards -->
<div class="row g-4 mb-4">
    @foreach($cards as $c)
        <div class="col-sm-6 col-lg-4 col-xl-4">
            <div class="card stat-card border-0 shadow-sm rounded-4 h-100 overflow-hidden position-relative" style="background: linear-gradient(145deg, #ffffff, #f8f9fa);">
                <div class="position-absolute opacity-10" style="bottom: -10%; right: -5%;">
                    <i class="fa-solid {{ $c['icon'] }}" style="font-size: 8rem; color: var(--bs-{{ $c['bg'] }}); transform: rotate(-15deg);"></i>
                </div>
                <div class="card-body p-4 position-relative z-1 d-flex flex-column justify-content-between h-100">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h2 class="h6 fw-bold mb-0 text-muted text-uppercase tracking-wider" style="font-size: 0.75rem; letter-spacing: 1px;">{{ $c['label'] }}</h2>
                        <div class="rounded-circle bg-{{ $c['bg'] }}-subtle text-{{ $c['bg'] }} d-flex align-items-center justify-content-center shadow-sm" style="width: 36px; height: 36px;">
                            <i class="fa-solid {{ $c['icon'] }} fs-6"></i>
                        </div>
                    </div>
                    <div>
                        <div class="fs-1 fw-bolder text-dark lh-1 mb-2">{{ $c['value'] }}</div>
                        <div class="small text-muted fw-medium d-flex align-items-center gap-1">
                            <i class="fa-solid fa-circle text-{{ $c['bg'] }}" style="font-size: 0.4rem;"></i> {{ $c['desc'] }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-7">
        <div class="card stat-card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="h5 fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                        <i class="fa-solid fa-chart-line text-primary"></i> Revenue (Last 12 Months)
                    </h2>
                </div>
                <div style="height: 280px; position: relative;">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="card stat-card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="h5 fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                        <i class="fa-solid fa-users text-success"></i> New Hostels
                    </h2>
                </div>
                <div style="height: 280px; position: relative;">
                    <canvas id="regChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card stat-card border-0 shadow-sm rounded-4 overflow-hidden mb-5">
    <div class="card-body p-0">
        <div class="p-4 border-bottom bg-light bg-opacity-50 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark">Upcoming Renewals (30 days)</h5>
            <a href="{{ route('superadmin.accounts.index') }}" class="btn btn-sm btn-light rounded-pill px-3 shadow-sm fw-medium">View All Customers</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.5px;">
                    <tr>
                        <th class="py-3 px-4 text-muted fw-semibold border-0">Owner</th>
                        <th class="py-3 px-4 text-muted fw-semibold border-0 text-center">Branches</th>
                        <th class="py-3 px-4 text-muted fw-semibold border-0">Renewal Date</th>
                        <th class="py-3 px-4 text-muted fw-semibold border-0">Days Left</th>
                        <th class="py-3 px-4 text-muted fw-semibold border-0 text-end">Action</th>
                    </tr>
                </thead>
                <tbody class="border-top-0">
                @forelse($upcomingRenewals as $account)
                    @php($days = $account->daysUntilAnchor())
                    <tr>
                        <td class="px-4 py-3 fw-bold text-dark">{{ $account->owner?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-center fw-medium text-secondary">{{ count($account->owner?->accessibleHostelIds() ?? []) }}</td>
                        <td class="px-4 py-3 text-dark fw-medium">{{ optional($account->current_period_end)->format('d M Y') }}</td>
                        <td class="px-4 py-3">
                            <span class="badge bg-{{ $days <= 7 ? 'danger' : 'warning' }}-subtle text-{{ $days <= 7 ? 'danger' : 'warning' }} border border-{{ $days <= 7 ? 'danger' : 'warning' }}-subtle rounded-pill px-3 py-1">
                                {{ $days }} days
                            </span>
                        </td>
                        <td class="px-4 py-3 text-end">
                            <a href="{{ route('superadmin.accounts.show', $account) }}" class="btn btn-sm btn-light text-primary rounded-pill fw-semibold px-3 shadow-sm" title="Open account">
                                Renew
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-5">
                        <i class="fa-solid fa-face-smile fs-1 text-light mb-3"></i>
                        <p class="mb-0">All good! No renewals due in the next 30 days.</p>
                    </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const labels = @json($charts['labels']);
        
        // Define a gradient for the line chart
        const revCtx = document.getElementById('revenueChart').getContext('2d');
        const revGradient = revCtx.createLinearGradient(0, 0, 0, 250);
        revGradient.addColorStop(0, 'rgba(37,99,235,0.4)');
        revGradient.addColorStop(1, 'rgba(37,99,235,0.0)');

        new Chart(revCtx, {
            type: 'line',
            data: { 
                labels, 
                datasets: [{ 
                    label: 'Revenue (₹)', 
                    data: @json($charts['revenue']),
                    borderColor: '#2563eb', 
                    backgroundColor: revGradient, 
                    fill: true, 
                    borderWidth: 3,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#2563eb',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    tension: .4 
                }] 
            },
            options: { 
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } }, 
                scales: { 
                    y: { 
                        beginAtZero: true,
                        grid: { borderDash: [4, 4], color: 'rgba(0,0,0,0.05)', drawBorder: false }
                    },
                    x: {
                        grid: { display: false, drawBorder: false }
                    }
                } 
            }
        });

        const regCtx = document.getElementById('regChart').getContext('2d');
        new Chart(regCtx, {
            type: 'bar',
            data: { 
                labels, 
                datasets: [{ 
                    label: 'Registrations', 
                    data: @json($charts['registrations']),
                    backgroundColor: '#10b981', 
                    borderRadius: 6,
                    barPercentage: 0.6
                }] 
            },
            options: { 
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } }, 
                scales: { 
                    y: { 
                        beginAtZero: true, 
                        ticks: { precision: 0 },
                        grid: { borderDash: [4, 4], color: 'rgba(0,0,0,0.05)', drawBorder: false }
                    },
                    x: {
                        grid: { display: false, drawBorder: false }
                    }
                } 
            }
        });
    });
</script>
@endpush

