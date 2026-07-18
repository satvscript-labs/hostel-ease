@extends('layouts.app')
@section('title', __('Platform Dashboard'))

@push('styles')
<style>
    /* ══ Super Admin Dashboard — W12 rebuild on the design system. The old
       page was pre-system: bespoke .stat-card glass, Bootstrap-blue charts,
       a raw <table> for renewals. Now: mesh hero + he-stats, glowing indigo
       charts, and renewals/activity as aligned rows (§4.11). ══ */

    /* Chart shells — skeleton until Chart.js paints (same as admin dashboard). */
    .sad-chart-shell { position: relative; height: 260px; }
    .sad-chart-shell canvas { position: relative; z-index: 1; }
    .sad-chart-skeleton { position: absolute; inset: 0; border-radius: var(--he-radius-md); transition: opacity .3s ease; }
    .sad-chart-shell.is-ready .sad-chart-skeleton { opacity: 0; pointer-events: none; }

    /* ── Renewal rows — the aligned row system (§4.11): the LIST owns the
       columns; rows inherit via subgrid so every column aligns vertically. ── */
    .sad-list { display: grid; grid-template-columns: 1fr; }
    .sad-row { grid-column: 1 / -1; display: grid; grid-template-columns: subgrid; align-items: center;
        padding: .8rem 1.25rem; transition: background .18s var(--ease-out-expo); }
    .sad-row:hover { background: var(--he-bg-surface-raised); }
    .sad-row + .sad-row { border-top: 1px solid rgba(15, 23, 42, .06); }
    .sad-who { display: flex; align-items: center; gap: .8rem; min-width: 0; }
    .sad-text { flex: 1 1 auto; min-width: 0; } /* explicit shrink chain (§4.11 r5) */
    .sad-sub-extra { display: inline; }
    .sad-chip { white-space: nowrap; }
    .sad-cols { display: none; } /* desktop-only columns */
    .sad-act { display: flex; justify-content: flex-end; }
    @container (min-width: 760px) {
        .sad-list { grid-template-columns: minmax(220px, 1.3fr) auto auto auto; column-gap: 1.25rem; }
        .sad-row { grid-template-columns: subgrid; } /* re-assert over the phone tier */
        .sad-cols { display: block; white-space: nowrap; }
        .sad-sub-extra { display: none; } /* data moves into its aligned columns */
    }

    /* ── Activity feed rows ── */
    .sad-feed-row { display: flex; align-items: flex-start; gap: .75rem; padding: .7rem 1.25rem; }
    .sad-feed-row + .sad-feed-row { border-top: 1px solid rgba(15, 23, 42, .05); }
    .sad-feed-ic { width: 32px; height: 32px; border-radius: 10px; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center; font-size: .75rem;
        background: var(--he-primary-soft); color: var(--he-primary); }
</style>
@endpush

@section('content')
<div class="page-enter">

    <div class="he-page-head mb-4 stagger-1">
        <div>
            <h1 class="he-page-title">{{ __('Platform Dashboard') }}</h1>
            <p class="he-page-sub">{{ __('Every hostel, every rupee, at a glance.') }}</p>
        </div>
        <a href="{{ route('superadmin.accounts.index') }}" class="btn btn-premium rounded-pill px-4 fw-semibold shadow-sm tactile-btn d-none d-md-inline-flex align-items-center">
            <i class="fa-solid fa-users-gear me-2"></i>{{ __('Customers') }}
        </a>
    </div>

    {{-- ══ Platform KPIs — hero carries the money ══ --}}
    <div class="he-stats mb-4 stagger-2">
        <div class="he-stats__grid" style="--he-stats-cols: 4;">
            <div class="he-stat he-stat--hero">
                <div class="he-stat__head">
                    <div class="he-stat__icon" style="background: rgba(255, 255, 255, 0.15); color: #6ee7b7;"><i class="fa-solid fa-sack-dollar"></i></div>
                    <div class="he-stat__label">{{ __('Revenue') }} · {{ now()->format('M') }} <span class="opacity-50">· {{ __('lifetime') }} {{ hostelease_money($stats['total_income']) }}</span></div>
                </div>
                <div class="he-stat__value">{{ hostelease_money($stats['monthly_revenue']) }}</div>
            </div>
            <div class="he-stat">
                <div class="he-stat__head">
                    <div class="he-stat__icon" style="background: var(--he-primary-soft); color: var(--he-primary);"><i class="fa-solid fa-hotel"></i></div>
                    <div class="he-stat__label">{{ __('Hostels') }}</div>
                </div>
                <div class="he-stat__value">{{ $stats['active_hostels'] }} <span class="fs-6 opacity-50 fw-normal">/ {{ $stats['total_hostels'] }} {{ __('active') }}</span></div>
            </div>
            <div class="he-stat">
                <div class="he-stat__head">
                    <div class="he-stat__icon" style="background: var(--he-warning-soft); color: var(--he-warning);"><i class="fa-solid fa-bell"></i></div>
                    <div class="he-stat__label">{{ __('Due in 30 days') }}</div>
                </div>
                <div class="he-stat__value {{ $stats['due_renewals'] > 0 ? 'text-warning' : '' }}">{{ $stats['due_renewals'] }}</div>
            </div>
            <div class="he-stat">
                <div class="he-stat__head">
                    <div class="he-stat__icon" style="background: var(--he-info-soft); color: var(--he-info);"><i class="fa-solid fa-users"></i></div>
                    <div class="he-stat__label">{{ __('Students') }}</div>
                </div>
                <div class="he-stat__value">{{ number_format($stats['total_students']) }}</div>
            </div>
        </div>
    </div>

    {{-- ══ Trends ══ --}}
    <div class="row g-4 mb-4 stagger-3">
        <div class="col-xl-7">
            <div class="panel-card h-100">
                <div class="panel-head"><h6><i class="fa-solid fa-chart-line me-2" style="color: var(--he-primary);"></i>{{ __('Revenue — last 12 months') }}</h6></div>
                <div class="panel-body">
                    <div class="sad-chart-shell" id="revShell">
                        <div class="sad-chart-skeleton skeleton"></div>
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="panel-card h-100">
                <div class="panel-head"><h6><i class="fa-solid fa-building-circle-arrow-right me-2" style="color: var(--he-accent);"></i>{{ __('New hostels') }}</h6></div>
                <div class="panel-body">
                    <div class="sad-chart-shell" id="regShell">
                        <div class="sad-chart-skeleton skeleton"></div>
                        <canvas id="regChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-5 stagger-4">
        {{-- ══ Upcoming renewals — aligned rows ══ --}}
        <div class="col-xl-7">
            <div class="panel-card h-100">
                <div class="panel-head">
                    <h6><i class="fa-solid fa-rotate me-2" style="color: var(--he-warning);"></i>{{ __('Upcoming renewals') }} <span class="text-muted fw-normal">· 30 {{ __('days') }}</span></h6>
                    <a href="{{ route('superadmin.accounts.index') }}" class="btn btn-sm btn-white border rounded-pill px-3 fw-semibold">{{ __('All customers') }}</a>
                </div>
                <div class="he-adaptive">
                    <div class="sad-list">
                        @forelse($upcomingRenewals as $account)
                            @php($days = $account->daysUntilAnchor())
                            @php($branches = count($account->owner?->accessibleHostelIds() ?? []))
                            <div class="sad-row">
                                <div class="sad-who">
                                    <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold flex-shrink-0" style="width: 38px; height: 38px;">{{ strtoupper(substr($account->owner?->name ?? '?', 0, 1)) }}</div>
                                    <div class="sad-text">
                                        <div class="fw-bold text-dark text-truncate">{{ $account->owner?->name ?? '—' }}</div>
                                        <div class="small text-muted text-truncate">{{ optional($account->current_period_end)->format('d M Y') }}<span class="sad-sub-extra"> · {{ $branches }} {{ __('branch(es)') }}</span></div>
                                    </div>
                                </div>
                                <div class="sad-cols small fw-semibold text-secondary">{{ $branches }} {{ __('branch(es)') }}</div>
                                <div class="sad-cols">
                                    <span class="badge sad-chip bg-{{ $days <= 7 ? 'danger' : 'warning' }}-subtle text-{{ $days <= 7 ? 'danger' : 'warning' }} rounded-pill px-3 py-1 fw-semibold">{{ $days }} {{ __('days') }}</span>
                                </div>
                                <div class="sad-act">
                                    <a href="{{ route('superadmin.accounts.show', $account) }}" class="btn btn-sm btn-white border text-primary rounded-pill fw-semibold px-3">{{ __('Renew') }}</a>
                                </div>
                            </div>
                        @empty
                            <div class="p-3" style="grid-column: 1 / -1;">
                                <x-he-empty-state icon="face-smile" title="{{ __('Nothing due') }}" subtitle="{{ __('No renewals in the next 30 days.') }}" />
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- ══ Platform pulse ══ --}}
        <div class="col-xl-5">
            <div class="panel-card h-100">
                <div class="panel-head">
                    <h6><i class="fa-solid fa-wave-square me-2" style="color: var(--he-info);"></i>{{ __('Platform pulse') }}</h6>
                    <a href="{{ route('superadmin.activity') }}" class="btn btn-sm btn-white border rounded-pill px-3 fw-semibold">{{ __('Full log') }}</a>
                </div>
                <div>
                    @forelse($recentActivity as $log)
                        <div class="sad-feed-row">
                            <div class="sad-feed-ic"><i class="fa-solid fa-bolt"></i></div>
                            <div class="min-w-0">
                                <div class="small fw-semibold text-dark text-truncate">{{ $log->description ?? $log->action }}</div>
                                <div class="text-muted text-truncate" style="font-size: .72rem;">{{ $log->user?->name ?? __('System') }} · {{ $log->created_at->diffForHumans(short: true) }}</div>
                            </div>
                        </div>
                    @empty
                        <div class="p-3"><x-he-empty-state icon="wave-square" title="{{ __('No activity yet') }}" subtitle="{{ __('Actions across the platform appear here.') }}" /></div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const labels = @json($charts['labels']);

    // Glowing indigo area (design law: high tension, gradient fill, no x grid).
    const revCtx = document.getElementById('revenueChart').getContext('2d');
    const g = revCtx.createLinearGradient(0, 0, 0, 250);
    g.addColorStop(0, 'rgba(79, 70, 229, 0.35)');
    g.addColorStop(1, 'rgba(79, 70, 229, 0)');
    new Chart(revCtx, {
        type: 'line',
        data: { labels, datasets: [{
            data: @json($charts['revenue']),
            borderColor: '#4f46e5', backgroundColor: g, fill: true, borderWidth: 3,
            pointBackgroundColor: '#fff', pointBorderColor: '#4f46e5', pointBorderWidth: 2,
            pointRadius: 3, pointHoverRadius: 6, tension: 0.4,
        }] },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' }, border: { display: false } },
                x: { grid: { display: false }, border: { display: false } },
            },
        },
    });
    document.getElementById('revShell').classList.add('is-ready');

    const regCtx = document.getElementById('regChart').getContext('2d');
    new Chart(regCtx, {
        type: 'bar',
        data: { labels, datasets: [{
            data: @json($charts['registrations']),
            backgroundColor: 'rgba(147, 51, 234, 0.75)', borderRadius: 6, barPercentage: 0.6,
        }] },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: 'rgba(0,0,0,0.05)' }, border: { display: false } },
                x: { grid: { display: false }, border: { display: false } },
            },
        },
    });
    document.getElementById('regShell').classList.add('is-ready');
});
</script>
@endpush
