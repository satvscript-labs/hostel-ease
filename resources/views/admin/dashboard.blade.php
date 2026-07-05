@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
<h1 class="h4 fw-bold mb-3">Dashboard</h1>

<div class="bento">
    {{-- Hero: monthly income --}}
    <div class="bento-card hero c2 r2">
        <div class="bento-icon" style="background:rgba(255,255,255,.18);color:#fff"><i class="fa-solid fa-money-bill-wave"></i></div>
        <div class="mt-auto">
            <div class="bento-value" style="font-size:2.4rem">{{ hsms_money($stats['monthly_income']) }}</div>
            <div class="bento-label">Income · {{ now()->format('M Y') }}</div>
            <div class="mt-2 small" style="color:rgba(255,255,255,.85)">{{ $stats['students'] }} students · {{ $stats['occupancy_pct'] }}% occupancy</div>
        </div>
    </div>

    @php
        $tiles = [
            ['Students', $stats['students'], 'fa-users', 'primary'],
            ['Occupied Beds', $stats['occupied_beds'], 'fa-bed', 'danger'],
            ['Empty Beds', $stats['empty_beds'], 'fa-bed-pulse', 'success'],
            ['Occupancy', $stats['occupancy_pct'].'%', 'fa-chart-pie', 'info'],
        ];
    @endphp
    @foreach($tiles as [$label, $value, $icon, $bg])
        <div class="bento-card">
            <div class="bento-icon bg-{{ $bg }}-subtle text-{{ $bg }}"><i class="fa-solid {{ $icon }}"></i></div>
            <div class="mt-auto">
                <div class="bento-value">{{ $value }}</div>
                <div class="bento-label">{{ $label }}</div>
            </div>
        </div>
    @endforeach

    {{-- Monthly collection chart --}}
    <div class="bento-card c2 r2">
        <div class="fw-bold mb-2">Monthly Collection</div>
        <canvas id="collectionChart"></canvas>
    </div>

    @php
        $tiles2 = [
            ['Pending Fees', hsms_money($stats['pending_fees']), 'fa-hourglass-half', 'warning'],
            ['AC Pending', hsms_money($stats['ac_pending']), 'fa-snowflake', 'info'],
            ['Total Rooms', $stats['total_rooms'], 'fa-door-open', 'primary'],
        ];
    @endphp
    @foreach($tiles2 as [$label, $value, $icon, $bg])
        <div class="bento-card">
            <div class="bento-icon bg-{{ $bg }}-subtle text-{{ $bg }}"><i class="fa-solid {{ $icon }}"></i></div>
            <div class="mt-auto">
                <div class="bento-value">{{ $value }}</div>
                <div class="bento-label">{{ $label }}</div>
            </div>
        </div>
    @endforeach

    {{-- Bed occupancy doughnut --}}
    <div class="bento-card c2 r2">
        <div class="fw-bold mb-2">Bed Occupancy</div>
        <canvas id="occupancyChart"></canvas>
    </div>

    {{-- Leaving soon --}}
    <div class="bento-card c2 r2">
        <div class="fw-bold mb-2"><i class="fa-solid fa-person-walking-arrow-right text-warning me-1"></i> Leaving in 7 Days</div>
        <ul class="list-group list-group-flush bento-list">
            @forelse($alerts['leaving_soon'] as $s)
                <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                    <span>{{ $s->name }} <small class="text-muted">· {{ optional($s->activeAssignment?->bed?->room)->room_number ?? '—' }}</small></span>
                    <span class="badge bg-warning text-dark">{{ optional($s->leave_date)->format('d-m') }}</span>
                </li>
            @empty
                <li class="list-group-item px-0 text-muted border-0">No students leaving in the next 7 days.</li>
            @endforelse
        </ul>
    </div>

    {{-- Quick alerts --}}
    <div class="bento-card c2 r2">
        <div class="fw-bold mb-2"><i class="fa-solid fa-bolt text-primary me-1"></i> Quick Alerts</div>
        <div class="d-flex flex-column gap-2">
            <div class="alert alert-success mb-0 py-2">{{ $alerts['empty_beds'] }} empty beds available.</div>
            <div class="alert alert-warning mb-0 py-2">{{ hsms_money($stats['pending_fees']) }} in pending fees.</div>
            <div class="alert alert-info mb-0 py-2">{{ hsms_money($stats['ac_pending']) }} in pending AC bills.</div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const occ = @json($charts['occupancy']);
        const noAspect = { responsive: true, maintainAspectRatio: false };
        new Chart(document.getElementById('occupancyChart'), {
            type: 'doughnut',
            data: {
                labels: ['Occupied', 'Empty', 'Reserved', 'Maintenance'],
                datasets: [{ data: [occ.occupied, occ.empty, occ.reserved, occ.maintenance],
                    backgroundColor: ['#ef4444', '#22c55e', '#eab308', '#9ca3af'] }]
            },
            options: { ...noAspect, plugins: { legend: { position: 'bottom' } }, cutout: '62%' }
        });
        new Chart(document.getElementById('collectionChart'), {
            type: 'bar',
            data: { labels: @json($charts['collection_labels']),
                datasets: [{ label: 'Collection (₹)', data: @json($charts['collection_values']),
                    backgroundColor: '#2563eb', borderRadius: 8, maxBarThickness: 38 }] },
            options: { ...noAspect, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true }, x: { grid: { display: false } } } }
        });
    });
</script>
@endpush
