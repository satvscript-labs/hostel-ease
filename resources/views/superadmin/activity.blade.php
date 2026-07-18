@extends('layouts.app')
@section('title', __('Activity Logs'))

@push('styles')
<style>
    /* ══ Activity Logs — W12 rebuild. Raw 6-column table (horizontal scroll on
       phones) → aligned rows (§4.11): subgrid ≥880, designed reflow 640–880,
       iOS rows below. Filters fragment-swap the list (§4.3). ══ */
    .act-filter-row { position:relative; z-index:30; }

    .act-list { display:grid; grid-template-columns:1fr; }
    .act-row { grid-column:1/-1; display:flex; align-items:flex-start; gap:.75rem; padding:.8rem 1.25rem; transition:background .18s var(--ease-out-expo); }
    .act-row:hover { background:var(--he-bg-surface-raised); }
    .act-row + .act-row { border-top:1px solid rgba(15,23,42,.06); }
    .act-ic { width:36px; height:36px; border-radius:11px; flex-shrink:0; display:flex; align-items:center; justify-content:center;
        background:var(--he-primary-soft); color:var(--he-primary); font-size:.8rem; }
    .act-text { flex:1 1 auto; min-width:0; } /* shrink chain (§4.11 r5) */
    .act-desc { font-weight:600; color:var(--he-text-main); font-size:.88rem; }
    .act-sub { font-size:.74rem; color:var(--he-text-muted); }
    .act-chip { white-space:nowrap; font-size:.66rem; }
    .act-when, .act-ip { display:none; }

    /* Tablet 640–880 — designed reflow: chip + timestamp join the meta line. */
    @container (min-width: 640px) {
        .act-when { display:block; font-size:.76rem; color:var(--he-text-muted); white-space:nowrap; font-variant-numeric:tabular-nums; }
        .act-row { align-items:center; }
    }

    /* Wide ≥880 — subgrid: every column aligned across rows. */
    @container (min-width: 880px) {
        .act-list { grid-template-columns:minmax(280px,1fr) auto auto auto; column-gap:1.25rem; }
        .act-row { display:grid; grid-template-columns:subgrid; }
        .act-main { display:flex; align-items:center; gap:.75rem; min-width:0; }
        .act-ip { display:block; font-size:.74rem; color:var(--he-text-muted); white-space:nowrap; font-variant-numeric:tabular-nums; }
    }
    .act-main { display:flex; align-items:flex-start; gap:.75rem; min-width:0; flex:1 1 auto; }
</style>
@endpush

@section('content')
<div class="page-enter" x-data="{ actionTerm: @json(request('action', '')) }">
    <div class="he-page-head mb-4 stagger-1">
        <div>
            <h1 class="he-page-title">{{ __('Activity Logs') }}</h1>
            <p class="he-page-sub">{{ __('Every recorded action across the platform, newest first.') }}</p>
        </div>
    </div>

    {{-- One-row fragment filter bar (§4.3/§4.5). --}}
    <div class="mb-3 act-filter-row stagger-2">
        <form method="GET" action="{{ route('superadmin.activity') }}" x-ref="filterForm"
              data-fragment="#act-list" class="d-flex flex-nowrap gap-2 align-items-center">
            <div class="he-search he-search--inline he-search--clearable">
                <span class="he-search__icon"><i class="fa-solid fa-bolt"></i></span>
                <input type="text" name="action" x-model="actionTerm" class="he-search__input"
                       placeholder="{{ __('Filter by action (payment, login…)') }}"
                       @input.debounce.450ms="$el.form.requestSubmit()">
                <button type="button" class="he-search__clear" x-show="actionTerm" x-cloak
                        @click="actionTerm = ''; $nextTick(() => $refs.filterForm.requestSubmit())" title="{{ __('Clear') }}">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <x-he-select name="hostel" icon="building" icon-only-mobile :selected="request('hostel', '')"
                :options="['' => ['label' => __('All hostels'), 'icon' => 'building']] + $hostels->mapWithKeys(fn ($h) => [$h->id => $h->name])->all()" />
        </form>
    </div>

    <div id="act-list" data-fragment-container class="he-adaptive stagger-3">
        <div class="panel-card shadow-sm">
            <div class="act-list">
                @forelse($logs as $log)
                    <div class="act-row">
                        <div class="act-main">
                            <div class="act-ic"><i class="fa-solid fa-bolt"></i></div>
                            <div class="act-text">
                                <div class="act-desc text-truncate">{{ $log->description ?: $log->action }}</div>
                                <div class="act-sub text-truncate">{{ $log->user?->name ?? __('System') }} · {{ $log->hostel?->name ?? '—' }}<span class="d-inline d-sm-none"> · {{ $log->created_at->diffForHumans(short: true) }}</span></div>
                            </div>
                        </div>
                        <span class="badge bg-primary-subtle text-primary rounded-pill px-2 py-1 act-chip">{{ $log->action }}</span>
                        <div class="act-when">{{ $log->created_at->format('d M Y H:i') }}</div>
                        <div class="act-ip">{{ $log->ip_address ?? '—' }}</div>
                    </div>
                @empty
                    <div class="p-3" style="grid-column:1/-1;">
                        <x-he-empty-state icon="wave-square" title="{{ __('No activity logged') }}"
                            subtitle="{{ (request('action') || request('hostel')) ? __('Try clearing the filters.') : __('Platform actions will appear here.') }}" />
                    </div>
                @endforelse
            </div>
            @if($logs->hasPages())
                <div class="p-3 border-top">{{ $logs->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
