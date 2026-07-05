@extends('layouts.app')
@section('title', 'Super Admin Dashboard')

@php
    $cards = [
        ['label' => 'Total Hostels', 'value' => $stats['total_hostels'], 'icon' => 'fa-hotel', 'bg' => 'primary'],
        ['label' => 'Active Hostels', 'value' => $stats['active_hostels'], 'icon' => 'fa-circle-check', 'bg' => 'success'],
        ['label' => 'Expired Hostels', 'value' => $stats['expired_hostels'], 'icon' => 'fa-circle-xmark', 'bg' => 'danger'],
        ['label' => 'Due Renewals (30d)', 'value' => $stats['due_renewals'], 'icon' => 'fa-bell', 'bg' => 'warning'],
        ['label' => 'Total Students', 'value' => $stats['total_students'], 'icon' => 'fa-users', 'bg' => 'info'],
        ['label' => 'Total Income', 'value' => hsms_money($stats['total_income']), 'icon' => 'fa-sack-dollar', 'bg' => 'success'],
        ['label' => 'Monthly Revenue', 'value' => hsms_money($stats['monthly_revenue']), 'icon' => 'fa-chart-line', 'bg' => 'primary'],
    ];
@endphp

@section('content')
<h1 class="h4 fw-bold mb-3">Super Admin Dashboard</h1>

<div class="row g-3 mb-4">
    @foreach($cards as $c)
        <div class="col-6 col-md-4 col-xl-3">
            <div class="card stat-card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon bg-{{ $c['bg'] }}-subtle text-{{ $c['bg'] }}">
                        <i class="fa-solid {{ $c['icon'] }}"></i>
                    </div>
                    <div>
                        <div class="stat-value">{{ $c['value'] }}</div>
                        <div class="stat-label">{{ $c['label'] }}</div>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card stat-card h-100">
            <div class="card-body">
                <h2 class="h6 fw-bold mb-3">Revenue (Last 12 Months)</h2>
                <canvas id="revenueChart" height="120"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card stat-card h-100">
            <div class="card-body">
                <h2 class="h6 fw-bold mb-3">New Hostel Registrations</h2>
                <canvas id="regChart" height="160"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="card stat-card mt-3">
    <div class="card-body">
        <h2 class="h6 fw-bold mb-3">Upcoming Renewals</h2>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Hostel</th><th>Owner</th><th>End Date</th><th>Days Left</th><th>Status</th></tr></thead>
                <tbody>
                @forelse($upcomingRenewals as $h)
                    <tr>
                        <td class="fw-semibold">{{ $h->name }}</td>
                        <td>{{ $h->owner_name }}</td>
                        <td>{{ optional($h->subscription_end)->format('d M Y') }}</td>
                        <td>{{ $h->daysUntilExpiry() }}</td>
                        <td><span class="badge bg-{{ $h->daysUntilExpiry() <= 7 ? 'danger' : 'warning' }}">{{ ucfirst($h->status) }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-3">No renewals due in the next 30 days.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Wait for the deferred Vite module (which defines window.Chart) to load.
    document.addEventListener('DOMContentLoaded', function () {
        const labels = @json($charts['labels']);
        new Chart(document.getElementById('revenueChart'), {
            type: 'line',
            data: { labels, datasets: [{ label: 'Revenue (₹)', data: @json($charts['revenue']),
                borderColor: '#2563eb', backgroundColor: 'rgba(37,99,235,.12)', fill: true, tension: .35 }] },
            options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
        });
        new Chart(document.getElementById('regChart'), {
            type: 'bar',
            data: { labels, datasets: [{ label: 'Registrations', data: @json($charts['registrations']),
                backgroundColor: '#22c55e', borderRadius: 6 }] },
            options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
        });
    });
</script>
@endpush
