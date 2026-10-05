@extends('layouts.app')
@section('title', 'Customers')

@push('styles')
<style>
    .stat-card { background: rgba(255,255,255,0.85); backdrop-filter: blur(20px); border: 1px solid rgba(0,0,0,0.05); transition: all 0.3s cubic-bezier(0.25,1,0.5,1); }
    .stat-card:hover { transform: translateY(-3px); box-shadow: 0 12px 30px rgba(0,0,0,0.05) !important; }
    .stat-value { font-size: 1.75rem; font-weight: 800; letter-spacing: -0.5px; }
    .stat-label { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; }
    /* ── Aligned customer list (MF): the raw <table> became a subgrid list so
       the columns align down the page (§4.11 r1) and it reflows to a stacked
       card on phones instead of scrolling sideways. Mirrors the hostels list. */
    .sac-list { display: grid; grid-template-columns: 1fr; }
    .sac-head { display: none; }
    .sac-row {
        grid-column: 1 / -1;
        display: flex; flex-wrap: wrap; align-items: center; gap: .45rem .9rem;
        padding: .9rem 1.25rem; text-decoration: none;
        transition: background .18s var(--ease-out-expo);
    }
    .sac-row:hover { background: var(--he-bg-surface-raised); }
    .sac-row + .sac-row { border-top: 1px solid rgba(15,23,42,.06); }

    .sac-id { display: flex; align-items: center; gap: .8rem; min-width: 0; flex: 1 1 220px; }
    .sac-ic { width: 42px; height: 42px; border-radius: 12px; flex-shrink: 0; display: flex;
        align-items: center; justify-content: center; background: var(--he-primary-soft); color: var(--he-primary); font-size: 1rem; }
    .sac-text { min-width: 0; } /* explicit shrink chain (§4.11 r5) */
    .sac-name { font-weight: 700; color: var(--he-text-main); }
    .sac-sub { font-size: .76rem; color: var(--he-text-muted); }

    .sac-cell-lbl { font-size: .62rem; font-weight: 700; text-transform: uppercase;
        letter-spacing: .06em; color: var(--he-text-muted); margin-right: .4rem; }
    .sac-branches, .sac-status { min-width: 0; }
    .sac-renews { display: flex; align-items: center; flex-wrap: wrap; gap: .5rem;
        min-width: 0; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .sac-ltv { min-width: 0; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .sac-days { font-size: .72rem; }
    /* Managed by HostelEase (BillingMode): a quiet second line under the status, so
       the operator's own book of customers is visible without a column of its own. */
    .sac-status { display: flex; flex-direction: column; align-items: flex-start; gap: .3rem; }
    .sac-managed { display: inline-flex; align-items: center; gap: .3rem; font-size: .68rem; font-weight: 700;
        color: #b45309; white-space: nowrap; }
    .sac-chev { color: var(--he-text-muted); margin-left: auto; }

    /* Wide ≥880 container: one-line subgrid — the LIST owns the template, every
       row inherits it, so every column shares one x. A header row (also subgrid)
       replaces the per-cell inline labels used on the phone tier. */
    @container (min-width: 880px) {
        .sac-list { grid-template-columns: minmax(220px,1.5fr) auto auto minmax(150px,auto) auto 20px; column-gap: 1.25rem; }
        .sac-head { display: grid; grid-column: 1 / -1; grid-template-columns: subgrid; align-items: center;
            padding: .6rem 1.25rem; font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em;
            color: var(--he-text-muted); background: var(--he-bg-surface-raised); border-bottom: 1px solid rgba(15,23,42,.06); }
        .sac-row { display: grid; grid-template-columns: subgrid; }
        .sac-id { flex: none; }
        .sac-branches, .sac-status { justify-self: center; text-align: center; }
        .sac-status { align-items: center; }
        .sac-ltv { justify-self: end; text-align: right; }
        .sac-cell-lbl { display: none; } /* header row carries the labels on wide */
        .sac-chev { margin-left: 0; }
    }
</style>
@endpush

@section('content')
<div class="page-enter">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark tracking-tight">Customers</h1>
            <p class="text-muted mb-0 small">One account per owner — quantity-based billing on a single renewal date.</p>
        </div>
    </div>

    {{-- Summary --}}
    <div class="row g-3 mb-4 stagger">
        <div class="col-6 col-md-3"><div class="card stat-card shadow-sm rounded-4"><div class="card-body py-4"><div class="stat-value text-primary mb-1">{{ $summary['accounts'] }}</div><div class="stat-label">Accounts</div></div></div></div>
        <div class="col-6 col-md-3"><div class="card stat-card shadow-sm rounded-4"><div class="card-body py-4"><div class="stat-value text-success mb-1">{{ $summary['active'] }}</div><div class="stat-label">Active</div></div></div></div>
        <div class="col-6 col-md-3"><div class="card stat-card shadow-sm rounded-4"><div class="card-body py-4"><div class="stat-value text-warning mb-1">{{ $summary['due_30'] }}</div><div class="stat-label">Renewals due · 30d</div></div></div></div>
        <div class="col-6 col-md-3"><div class="card stat-card shadow-sm rounded-4"><div class="card-body py-4"><div class="stat-value text-dark mb-1">{{ hostelease_money($summary['revenue']) }}</div><div class="stat-label">Lifetime Revenue</div></div></div></div>
    </div>

    {{-- ── Awaiting payment (S1 item 14) ──
         Real receivables, available only because a pending charge now mints an order
         (S0 · F3) — before that, money you were owed existed nowhere the app could
         see. Aged, because a 40-day-old unpaid charge is a different conversation
         from a 2-day-old one. Sits beside the open removal requests, since both are
         "someone is waiting on you". --}}
    @php
        // Three "someone is waiting on you" tiles, any subset of which may be
        // present. Widths are derived rather than hard-coded so the row stays
        // balanced at 1, 2 or 3 — and receivables keeps the larger share because
        // it carries the aged breakdown inside it.
        $waitingTiles = collect([
            'receivable' => (bool) $summary['receivable_count'],
            'links' => (bool) ($summary['live_links'] ?? 0),
            'removals' => (bool) $summary['removal_requests'],
        ])->filter()->count();
        $sideCol = $waitingTiles === 1 ? 'col-12' : ($waitingTiles === 2 ? 'col-md-6' : 'col-md-6 col-lg-3');
        $mainCol = $waitingTiles === 1 ? 'col-12' : ($waitingTiles === 2 ? 'col-lg-8' : 'col-lg-6');
    @endphp
    @if($waitingTiles)
        <div class="row g-3 mb-4 stagger">
            @if($summary['receivable_count'])
                <div class="col-12 {{ $mainCol }}">
                    <div class="card stat-card shadow-sm rounded-4 h-100">
                        <div class="card-body py-3 px-4">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                                <div>
                                    <div class="stat-label">Awaiting payment</div>
                                    <div class="stat-value text-warning mb-0">{{ hostelease_money($summary['receivable']) }}</div>
                                    <div class="small text-muted">{{ $summary['receivable_count'] }} unpaid {{ \Illuminate\Support\Str::plural('charge', $summary['receivable_count']) }}</div>
                                </div>
                                <div class="d-flex flex-wrap gap-3 small">
                                    <div><div class="stat-label">0–7 days</div><div class="fw-bold text-dark" style="font-variant-numeric:tabular-nums;">{{ hostelease_money($summary['receivable_aged']['fresh']) }}</div></div>
                                    <div><div class="stat-label">8–30 days</div><div class="fw-bold text-warning" style="font-variant-numeric:tabular-nums;">{{ hostelease_money($summary['receivable_aged']['mid']) }}</div></div>
                                    <div><div class="stat-label">30+ days</div><div class="fw-bold text-danger" style="font-variant-numeric:tabular-nums;">{{ hostelease_money($summary['receivable_aged']['old']) }}</div></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- ── Payment links sent, not yet paid (S2) ──
                 A different question from "awaiting payment": a charge can be owed
                 with no link at all (an offline proforma), and a link can be dead
                 while the charge is still owed. THIS one says the customer has been
                 asked and has not acted — which is the one you chase. --}}
            @if($summary['live_links'] ?? 0)
                <div class="col-12 {{ $sideCol }}">
                    <div class="card stat-card shadow-sm rounded-4 h-100" style="border-color: rgba(14,165,233,.25);">
                        <div class="card-body py-3 px-4">
                            <div class="stat-label">Payment links sent</div>
                            <div class="stat-value mb-0" style="color:#0284c7;">{{ hostelease_money($summary['live_links_value']) }}</div>
                            <div class="small text-muted">
                                {{ $summary['live_links'] }} live {{ \Illuminate\Support\Str::plural('link', $summary['live_links']) }} awaiting payment — open the account to re-send or cancel.
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @if($summary['removal_requests'])
                <div class="col-12 {{ $sideCol }}">
                    <div class="card stat-card shadow-sm rounded-4 h-100" style="border-color: rgba(234,88,12,.25);">
                        <div class="card-body py-3 px-4">
                            <div class="stat-label">Branch removal requests</div>
                            <div class="stat-value mb-0" style="color:#ea580c;">{{ $summary['removal_requests'] }}</div>
                            <div class="small text-muted">Owners waiting on a decision — open their account to confirm or decline.</div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @endif

    {{-- Filters — ONE canonical no-search row (§4.5): two matching he-selects
         (Renewals + Status), both auto-submitting the form. Fragment-driven so
         ONLY #cust-list swaps (W12 fix): the controls live OUTSIDE the swapped
         region, so their submit listeners survive — the old status form sat
         inside a swapped target, lost its listener, and hard-reloaded. Both
         filters combine (one form → both params). --}}
    <div class="mb-3" style="position:relative; z-index:30;">
        {{-- Natural-width, grouped left (NOT .he-filters--nosearch, which
             stretches each filter to fill the row — with only two it left a
             dead gap on the right that read as "separate"). --}}
        <form method="GET" action="{{ route('superadmin.accounts.index') }}" data-fragment="#cust-list"
              class="d-flex flex-wrap gap-2 align-items-center">
            <x-he-select name="due" icon="rotate" label="Renewals" :selected="(string) request('due', '')"
                :options="[
                    '' => ['label' => __('All renewals'), 'icon' => 'rotate'],
                    '7' => ['label' => __('Due ≤ 7 days'), 'icon' => 'bolt'],
                    '30' => ['label' => __('Due ≤ 30 days'), 'icon' => 'clock'],
                ]" />
            <x-he-select name="status" icon="filter" label="Status" :selected="request('status', '')"
                :options="[
                    '' => ['label' => __('All statuses'), 'icon' => 'filter'],
                    'active' => ['label' => __('Active'), 'icon' => 'circle-check'],
                    'grace' => ['label' => __('Grace'), 'icon' => 'hourglass-half'],
                    'expired' => ['label' => __('Expired'), 'icon' => 'circle-xmark'],
                    'trial' => ['label' => __('Trial'), 'icon' => 'gift'],
                    'suspended' => ['label' => __('Suspended'), 'icon' => 'ban'],
                ]" />
            {{-- Who handles billing (BillingMode): the operator's own book of managed
                 customers is a list worth pulling up on its own. --}}
            <x-he-select name="billing" icon="shield-halved" label="Billing" :selected="request('billing', '')"
                :options="[
                    '' => ['label' => __('All billing'), 'icon' => 'shield-halved'],
                    'self_serve' => ['label' => __('Self-serve'), 'icon' => 'user-check'],
                    'managed' => ['label' => __('Managed by HostelEase'), 'icon' => 'shield-halved'],
                ]" />
        </form>
    </div>

    <div id="cust-list" data-fragment-container class="he-adaptive card stat-card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="sac-list">
            {{-- Header row — subgrid-aligned to the columns below, wide tier only.
                 On the phone tier each cell carries its own inline label instead. --}}
            <div class="sac-head">
                <span>{{ __('Owner') }}</span>
                <span class="text-center">{{ __('Branches') }}</span>
                <span class="text-center">{{ __('Status') }}</span>
                <span>{{ __('Renews on') }}</span>
                <span class="text-end">{{ __('Lifetime value') }}</span>
                <span></span>
            </div>

            @forelse($accounts as $account)
                <a href="{{ route('superadmin.accounts.show', $account) }}" class="sac-row">
                    <div class="sac-id">
                        <div class="sac-ic"><i class="fa-solid fa-user-tie"></i></div>
                        <div class="sac-text">
                            <div class="sac-name text-truncate">{{ $account->owner?->name ?? __('Unknown owner') }}</div>
                            <div class="sac-sub text-truncate"><i class="fa-solid fa-mobile-screen text-primary me-1"></i>{{ $account->owner?->mobile ?? '—' }}</div>
                        </div>
                    </div>

                    <div class="sac-branches">
                        <span class="sac-cell-lbl">{{ __('Branches') }}</span>
                        <span class="fw-bold text-dark">{{ $account->branch_count }}</span>
                    </div>

                    <div class="sac-status">
                        <span class="badge bg-{{ $account->status->color() }}-subtle text-{{ $account->status->color() }} border border-{{ $account->status->color() }}-subtle rounded-pill px-3 py-2">{{ $account->status->label() }}</span>
                        @if($account->isManaged())
                            <span class="sac-managed" title="{{ __('Managed by HostelEase — you renew and add branches for them') }}"><i class="fa-solid fa-shield-halved"></i>{{ __('Managed') }}</span>
                        @endif
                    </div>

                    <div class="sac-renews">
                        <span class="sac-cell-lbl">{{ __('Renews on') }}</span>
                        <span class="fw-semibold text-dark">{{ $account->current_period_end ? $account->current_period_end->format('d M Y') : '—' }}</span>
                        @if($account->days_until !== null && $account->status->value !== 'suspended')
                            @php($du = $account->days_until)
                            @if($du < 0)
                                <span class="sac-days text-danger fw-semibold"><i class="fa-solid fa-circle-exclamation me-1"></i>{{ abs($du) }}d {{ __('overdue') }}</span>
                            @elseif($du === 0)
                                <span class="sac-days text-danger fw-semibold"><i class="fa-solid fa-bolt me-1"></i>{{ __('Due today') }}</span>
                            @elseif($du <= 7)
                                <span class="sac-days text-warning fw-semibold"><i class="fa-solid fa-clock me-1"></i>{{ __('in :n days', ['n' => $du]) }}</span>
                            @elseif($du <= 30)
                                <span class="sac-days text-muted"><i class="fa-regular fa-clock me-1"></i>{{ __('in :n days', ['n' => $du]) }}</span>
                            @endif
                        @endif
                    </div>

                    <div class="sac-ltv">
                        <span class="sac-cell-lbl">{{ __('Lifetime value') }}</span>
                        <span class="fw-bold text-dark">{{ hostelease_money($account->ltv) }}</span>
                    </div>

                    <div class="sac-chev"><i class="fa-solid fa-chevron-right"></i></div>
                </a>
            @empty
                <div style="grid-column: 1 / -1;">
                    <x-he-empty-state icon="users-gear" title="{{ __('No customer accounts yet') }}"
                        subtitle="{{ __('Accounts are created automatically when an owner\'s first branch is billed.') }}" />
                </div>
            @endforelse
        </div>
        @if($accounts->hasPages())
            <div class="p-3 border-top">{{ $accounts->withQueryString()->links() }}</div>
        @endif
    </div>
</div>
@endsection
