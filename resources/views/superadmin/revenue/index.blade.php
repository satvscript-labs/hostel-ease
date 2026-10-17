@extends('layouts.app')
@section('title', __('Revenue'))

{{-- S5 · _artifact/saas_billing_autopay/28_S5_DESIGN.md §6. Every figure is
     RevenueMetrics'; definitions sit next to the numbers so nobody has to guess what
     "revenue" means here. --}}
@php
    $money = fn ($v) => hostelease_money($v);
    $r = $tiles['receivables'];
    // In rupees, not percent: against a quiet month a percentage is noise ("+7100%").
    $cashDelta = round($tiles['cash_this'] - $tiles['cash_last'], 2);
    $hasHistory = collect($months)->contains(fn ($m) => $m['cash'] > 0 || $m['earned'] > 0);
@endphp

@push('styles')
<style>
    .rv-root { --rv-cash: #4f46e5; --rv-earned: #1baf7a; }
    .rv-help { display: inline-flex; width: 16px; height: 16px; border-radius: 50%; align-items: center; justify-content: center; font-size: .6rem; background: rgba(15,23,42,.06); color: var(--he-text-muted); cursor: help; margin-left: .3rem; vertical-align: middle; }
    .he-stat--hero .rv-help { background: rgba(255,255,255,.18); color: rgba(255,255,255,.85); }
    .rv-sub { font-size: .76rem; color: var(--he-text-muted); margin-top: .2rem; font-variant-numeric: tabular-nums; }
    .he-stat--hero .rv-sub { color: rgba(255,255,255,.72); }
    .rv-aged { display: flex; height: 6px; border-radius: 3px; overflow: hidden; gap: 2px; margin-top: .55rem; background: rgba(15,23,42,.05); }
    .rv-aged span { display: block; height: 100%; }
    .rv-aged-key { display: flex; gap: .7rem; font-size: .68rem; color: var(--he-text-muted); margin-top: .35rem; flex-wrap: wrap; }
    .rv-aged-key i { display: inline-block; width: 8px; height: 8px; border-radius: 2px; margin-right: .25rem; vertical-align: middle; }
    .rv-shell { position: relative; height: 280px; }
    .rv-shell--sm { height: 200px; }
    .rv-table { width: 100%; font-size: .82rem; font-variant-numeric: tabular-nums; }
    .rv-table th { font-size: .68rem; font-weight: 700; color: var(--he-text-muted); text-align: right; padding: .5rem .6rem; border-bottom: 1px solid rgba(15,23,42,.08); white-space: nowrap; }
    .rv-table th:first-child, .rv-table td:first-child { text-align: left; }
    .rv-table td { text-align: right; padding: .55rem .6rem; border-bottom: 1px solid rgba(15,23,42,.05); white-space: nowrap; color: var(--he-text-main); }
    .rv-table tr:last-child td { border-bottom: 0; }
    .rv-table .rv-muted { color: var(--he-text-muted); }
    .rv-table .rv-strong { font-weight: 800; }
    .rv-scroll { overflow-x: auto; }
    .rv-rate { display: inline-flex; align-items: center; gap: .45rem; justify-content: flex-end; }
    .rv-rate-bar { width: 54px; height: 6px; border-radius: 3px; background: rgba(15,23,42,.06); overflow: hidden; }
    .rv-rate-bar span { display: block; height: 100%; background: var(--rv-earned); border-radius: 3px; }
    .rv-lost { display: flex; align-items: center; gap: .8rem; padding: .7rem 1.1rem; text-decoration: none; color: inherit; border-bottom: 1px solid rgba(15,23,42,.05); }
    .rv-lost:last-child { border-bottom: 0; }
    .rv-lost:hover { background: var(--he-bg-surface-raised); }
    .rv-lost-ic { width: 34px; height: 34px; border-radius: 10px; background: var(--he-danger-soft); color: var(--he-danger); display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: .8rem; }
    .rv-defs { font-size: .76rem; color: var(--he-text-muted); line-height: 1.55; }
    .rv-defs b { color: var(--he-text-main); }
    .rv-toggle { border: 1px solid rgba(15,23,42,.1); background: #fff; border-radius: 999px; padding: .25rem .8rem; font-size: .74rem; font-weight: 700; color: var(--he-text-main); }
</style>
@endpush

@section('content')
<div class="page-enter rv-root" x-data="{ table: false }">

    <div class="he-page-head mb-4 stagger-1">
        <div>
            <h1 class="he-page-title">{{ __('Revenue') }}</h1>
            <p class="he-page-sub">{{ __('What customers pay each month, what came in, and what is still owed.') }}</p>
        </div>
    </div>

    {{-- ══ The five numbers ══ --}}
    <div class="he-stats mb-4 stagger-2">
        <div class="he-stats__grid" style="--he-stats-cols: 5;">
            <div class="he-stat he-stat--hero">
                <div class="he-stat__head">
                    <div class="he-stat__icon" style="background: rgba(255,255,255,.15); color: #c7d2fe;"><i class="fa-solid fa-arrows-rotate"></i></div>
                    <div class="he-stat__label">{{ __('MRR') }}<span class="rv-help" tabindex="0" title="{{ __('Monthly recurring revenue: what every paid plan running today is worth per month — a yearly plan counts one twelfth. Not cash.') }}">?</span></div>
                </div>
                <div class="he-stat__value">{{ $money($tiles['mrr']) }}</div>
                <div class="rv-sub">{{ __('ARR') }} {{ $money($tiles['arr']) }}</div>
            </div>
            <div class="he-stat">
                <div class="he-stat__head">
                    <div class="he-stat__icon" style="background: var(--he-primary-soft); color: var(--he-primary);"><i class="fa-solid fa-user-check"></i></div>
                    <div class="he-stat__label">{{ __('Paying customers') }}<span class="rv-help" tabindex="0" title="{{ __('Customers with a paid plan running today. Customers on a free renewal are counted under "customers" but pay nothing this term.') }}">?</span></div>
                </div>
                <div class="he-stat__value">{{ $tiles['paying'] }} <span class="fs-6 opacity-50 fw-normal">{{ __('of') }} {{ $tiles['customers'] }}</span></div>
                <div class="rv-sub">{{ __('Average') }} {{ $money($tiles['arpa']) }} {{ __('a month') }}</div>
            </div>
            <div class="he-stat">
                <div class="he-stat__head">
                    <div class="he-stat__icon" style="background: var(--he-success-soft, #d1fae5); color: var(--he-success, #10b981);"><i class="fa-solid fa-indian-rupee-sign"></i></div>
                    <div class="he-stat__label">{{ __('Cash') }} · {{ now()->format('M') }}<span class="rv-help" tabindex="0" title="{{ __('Money received this month, dated by when it was paid. Voided payments are not counted.') }}">?</span></div>
                </div>
                <div class="he-stat__value">{{ $money($tiles['cash_this']) }}</div>
                <div class="rv-sub">
                    {{ __('Last month') }} {{ $money($tiles['cash_last']) }}
                    @if($cashDelta != 0)· <span class="{{ $cashDelta > 0 ? 'text-success' : 'text-danger' }} fw-semibold">{{ $cashDelta > 0 ? '+' : '−' }}{{ $money(abs($cashDelta)) }}</span>@endif
                </div>
            </div>
            <div class="he-stat">
                <div class="he-stat__head">
                    <div class="he-stat__icon" style="background: var(--he-info-soft); color: var(--he-info);"><i class="fa-solid fa-hourglass-half"></i></div>
                    <div class="he-stat__label">{{ __('Deferred') }}<span class="rv-help" tabindex="0" title="{{ __('Cash received for service not delivered yet — what would be owed back if every customer left today. Advance renewals sit here until they start.') }}">?</span></div>
                </div>
                <div class="he-stat__value">{{ $money($tiles['deferred']) }}</div>
                <div class="rv-sub">{{ __('paid, not yet earned') }}</div>
            </div>
            <div class="he-stat">
                <div class="he-stat__head">
                    <div class="he-stat__icon" style="background: var(--he-warning-soft); color: var(--he-warning);"><i class="fa-solid fa-file-invoice"></i></div>
                    <div class="he-stat__label">{{ __('Owed to us') }}<span class="rv-help" tabindex="0" title="{{ __('Charges raised and not paid yet (payment links, proformas, unfinished checkouts), aged by when they were raised.') }}">?</span></div>
                </div>
                <div class="he-stat__value {{ $r['old'] > 0 ? 'text-warning' : '' }}">{{ $money($r['total']) }}</div>
                @if($r['total'] > 0)
                    <div class="rv-aged" role="img" aria-label="{{ __('0–7 days') }} {{ $money($r['fresh']) }}, {{ __('8–30 days') }} {{ $money($r['mid']) }}, {{ __('over 30 days') }} {{ $money($r['old']) }}">
                        @foreach(['fresh' => '#94a3b8', 'mid' => '#f59e0b', 'old' => '#dc2626'] as $k => $c)
                            @if($r[$k] > 0)<span style="width: {{ $r[$k] / $r['total'] * 100 }}%; background: {{ $c }};" title="{{ $money($r[$k]) }}"></span>@endif
                        @endforeach
                    </div>
                    <div class="rv-aged-key"><span><i style="background:#94a3b8"></i>0–7d</span><span><i style="background:#f59e0b"></i>8–30d</span><span><i style="background:#dc2626"></i>30d+</span></div>
                @else
                    <div class="rv-sub">{{ __('nothing outstanding') }}</div>
                @endif
                @if($r['stale_count'])
                    <div class="rv-sub text-danger" title="{{ __('Charges a later payment already covered. Nobody owes them — void them on the customer\'s Account 360.') }}">
                        <i class="fa-solid fa-circle-exclamation me-1"></i>{{ trans_choice(':n charge already covered — void it|:n charges already covered — void them', $r['stale_count'], ['n' => $r['stale_count']]) }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ══ Cash vs earned · MRR ══ --}}
    <div class="row g-4 mb-4 stagger-3">
        <div class="col-xl-8">
            <div class="panel-card h-100">
                <div class="panel-head">
                    <h6><i class="fa-solid fa-chart-column me-2" style="color: var(--he-primary);"></i>{{ __('Cash collected vs revenue earned') }} <span class="text-muted fw-normal">· {{ __('12 months') }}</span></h6>
                    <button type="button" class="rv-toggle" @click="table = !table" x-text="table ? @js(__('Show chart')) : @js(__('Show as table'))"></button>
                </div>
                <div class="panel-body">
                    @if(! $hasHistory)
                        <x-he-empty-state icon="chart-column" title="{{ __('No payments yet') }}" subtitle="{{ __('Cash and revenue appear here as soon as the first plan is paid.') }}" />
                    @else
                        <div x-show="!table" class="rv-shell"><canvas id="rvCash" aria-label="{{ __('Cash collected and revenue earned per month') }}"></canvas></div>
                        <div x-show="table" x-cloak class="rv-scroll">
                            <table class="rv-table">
                                <thead><tr><th>{{ __('Month') }}</th><th>{{ __('Cash collected') }}</th><th>{{ __('Revenue earned') }}</th><th>{{ __('MRR at month end') }}</th><th>{{ __('Churn') }}</th></tr></thead>
                                <tbody>
                                    @foreach(array_reverse($months) as $m)
                                        <tr>
                                            <td>{{ $m['label'] }} @if($m['current'])<span class="rv-muted">· {{ __('so far') }}</span>@endif</td>
                                            <td>{{ $money($m['cash']) }}</td>
                                            <td>{{ $money($m['earned']) }}</td>
                                            <td>{{ $money($m['mrr']) }}</td>
                                            <td class="rv-muted">{{ $m['churn'] === null ? '—' : $m['churn'].'%' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <p class="rv-defs mb-0 mt-3"><b>{{ __('Cash') }}</b> {{ __('is money received that month.') }} <b>{{ __('Earned') }}</b> {{ __('is the share of paid plans delivered that month — a two-year renewal paid today is all cash now, but earned a little each month.') }}</p>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="panel-card h-100">
                <div class="panel-head"><h6><i class="fa-solid fa-arrows-rotate me-2" style="color: var(--he-primary);"></i>{{ __('MRR at each month end') }}</h6></div>
                <div class="panel-body">
                    @if(! $hasHistory)
                        <x-he-empty-state icon="arrows-rotate" title="{{ __('No paid plans yet') }}" subtitle="" />
                    @else
                        <div class="rv-shell rv-shell--sm"><canvas id="rvMrr" aria-label="{{ __('Monthly recurring revenue at each month end') }}"></canvas></div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ══ MRR movement ══ --}}
    <div class="panel-card mb-4 stagger-3">
        <div class="panel-head"><h6><i class="fa-solid fa-right-left me-2" style="color: var(--he-primary);"></i>{{ __('How MRR moved') }}</h6></div>
        <div class="panel-body rv-scroll">
            <table class="rv-table">
                <thead><tr>
                    <th>{{ __('Month') }}</th><th>{{ __('Start') }}</th>
                    <th title="{{ __('Customers paying for the first time') }}">+ {{ __('New') }}</th>
                    <th title="{{ __('Existing customers paying more — a branch added, or a bigger plan') }}">+ {{ __('Expansion') }}</th>
                    <th title="{{ __('Customers who had left and came back') }}">+ {{ __('Came back') }}</th>
                    <th title="{{ __('Existing customers paying less — a branch removed') }}">− {{ __('Contraction') }}</th>
                    <th title="{{ __('Customers whose paid plan ran out') }}">− {{ __('Churned') }}</th>
                    <th>{{ __('End') }}</th>
                </tr></thead>
                <tbody>
                    @foreach(array_reverse($movement) as $mv)
                        <tr>
                            <td>{{ $mv['label'] }} @if($mv['current'])<span class="rv-muted">· {{ __('so far') }}</span>@endif</td>
                            <td class="rv-muted">{{ $money($mv['start']) }}</td>
                            <td>{{ $mv['new'] > 0 ? '+'.$money($mv['new']) : '—' }}</td>
                            <td>{{ $mv['expansion'] > 0 ? '+'.$money($mv['expansion']) : '—' }}</td>
                            <td>{{ $mv['reactivation'] > 0 ? '+'.$money($mv['reactivation']) : '—' }}</td>
                            <td>{{ $mv['contraction'] > 0 ? '−'.$money($mv['contraction']) : '—' }}</td>
                            <td>{{ $mv['churned'] > 0 ? '−'.$money($mv['churned']) : '—' }}</td>
                            <td class="rv-strong">{{ $money($mv['end']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="rv-defs mb-0 mt-3">{{ __('Measured from the 1st of each month to the 1st of the next. A renewal paid late, inside the grace days, can show as churned one month and back the next.') }}</p>
        </div>
    </div>

    <div class="row g-4 mb-5 stagger-4">
        {{-- ══ Trials ══ --}}
        <div class="col-xl-7">
            <div class="panel-card h-100">
                <div class="panel-head"><h6><i class="fa-solid fa-flask me-2" style="color: var(--he-accent);"></i>{{ __('Free trial → paid') }} <span class="text-muted fw-normal">· {{ __('by month the trial started') }}</span></h6></div>
                <div class="panel-body rv-scroll">
                    @if($cohorts === [])
                        <x-he-empty-state icon="flask" title="{{ __('No trials yet') }}" subtitle="{{ __('Each new sign-up starts a 14-day trial; it shows here.') }}" />
                    @else
                        <table class="rv-table">
                            <thead><tr><th>{{ __('Started') }}</th><th>{{ __('Trials') }}</th><th>{{ __('Subscribed') }}</th><th>{{ __('Still on trial') }}</th><th>{{ __('Let it lapse') }}</th><th>{{ __('Conversion') }}</th></tr></thead>
                            <tbody>
                                @foreach($cohorts as $key => $c)
                                    <tr>
                                        <td>{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $key)->format('M Y') }}</td>
                                        <td>{{ $c['started'] }}</td>
                                        <td class="rv-strong">{{ $c['converted'] }}</td>
                                        <td class="rv-muted">{{ $c['running'] ?: '—' }}</td>
                                        <td class="rv-muted">{{ $c['lapsed'] ?: '—' }}</td>
                                        <td>
                                            @if($c['rate'] === null)
                                                <span class="rv-muted">{{ __('too early') }}</span>
                                            @else
                                                <span class="rv-rate"><span class="rv-rate-bar"><span style="width: {{ $c['rate'] }}%"></span></span>{{ $c['rate'] }}% <span class="rv-muted small">· {{ $c['converted'] }} {{ __('of') }} {{ $c['converted'] + $c['lapsed'] }} {{ __('finished') }}</span></span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <p class="rv-defs mb-0 mt-3">{{ __('Conversion counts only trials that have finished — subscribed ÷ (subscribed + lapsed).') }}</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- ══ Lost customers ══ --}}
        <div class="col-xl-5">
            <div class="panel-card h-100">
                <div class="panel-head"><h6><i class="fa-solid fa-user-minus me-2" style="color: var(--he-danger);"></i>{{ __('Recently lost customers') }}</h6></div>
                @forelse($lost as $l)
                    <a class="rv-lost" href="{{ $l['public_id'] ? route('superadmin.accounts.show', $l['public_id']) : '#' }}">
                        <span class="rv-lost-ic"><i class="fa-solid fa-user-minus"></i></span>
                        <span class="flex-grow-1 min-w-0">
                            <span class="d-block fw-bold text-truncate">{{ $l['name'] }}</span>
                            <span class="d-block small text-muted">{{ __('Plan ended') }} {{ $l['ended']->format('d M Y') }}</span>
                        </span>
                        <span class="text-end small">
                            <span class="d-block fw-bold">{{ $money($l['mrr']) }}</span>
                            <span class="d-block text-muted">{{ __('a month') }}</span>
                        </span>
                    </a>
                @empty
                    <div class="panel-body"><x-he-empty-state icon="user-check" title="{{ __('Nobody has left') }}" subtitle="{{ __('Customers whose plan ran out and was not renewed appear here.') }}" /></div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection

@if($hasHistory)
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const months = @json(array_values($months));
    const labels = months.map(m => m.label + (m.current ? ' ·' : ''));
    const inr = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 });
    const compact = v => '₹' + new Intl.NumberFormat('en-IN', { notation: 'compact', maximumFractionDigits: 1 }).format(v);
    const css = getComputedStyle(document.querySelector('.rv-root'));
    const cashC = css.getPropertyValue('--rv-cash').trim(), earnedC = css.getPropertyValue('--rv-earned').trim();
    const axes = {
        y: { beginAtZero: true, ticks: { callback: compact, color: '#64748b' }, grid: { color: 'rgba(15,23,42,0.05)' }, border: { display: false } },
        x: { grid: { display: false }, border: { display: false }, ticks: { color: '#64748b', maxRotation: 0, autoSkipPadding: 10 } },
    };
    const tooltip = { callbacks: { label: c => ' ' + c.dataset.label + ': ' + inr.format(c.parsed.y) }, padding: 10, boxPadding: 4 };

    new Chart(document.getElementById('rvCash'), {
        type: 'bar',
        data: { labels, datasets: [
            { label: @js(__('Cash collected')), data: months.map(m => m.cash), backgroundColor: cashC, borderRadius: 4, borderSkipped: 'start', borderColor: '#fff', borderWidth: 1, categoryPercentage: 0.62, barPercentage: 0.9 },
            { label: @js(__('Revenue earned')), data: months.map(m => m.earned), backgroundColor: earnedC, borderRadius: 4, borderSkipped: 'start', borderColor: '#fff', borderWidth: 1, categoryPercentage: 0.62, barPercentage: 0.9 },
        ] },
        options: {
            responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
            plugins: { legend: { position: 'top', align: 'start', labels: { usePointStyle: true, pointStyle: 'rectRounded', color: '#0f172a', boxWidth: 10 } }, tooltip },
            scales: axes,
        },
    });

    new Chart(document.getElementById('rvMrr'), {
        type: 'line',
        data: { labels, datasets: [{ label: 'MRR', data: months.map(m => m.mrr), borderColor: cashC, backgroundColor: cashC, borderWidth: 2, pointRadius: 0, pointHoverRadius: 4, pointHitRadius: 12, tension: 0.25 }] },
        options: {
            responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
            plugins: { legend: { display: false }, tooltip },
            scales: axes,
        },
    });
});
</script>
@endpush
@endif
