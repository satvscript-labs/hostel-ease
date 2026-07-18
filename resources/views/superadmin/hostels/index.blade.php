@extends('layouts.app')
@section('title', __('Hostels'))

@push('styles')
<style>
    /* ══ Hostels directory — W12 rebuild. The table (all text-nowrap, no phone
       tier) becomes the aligned row system (§4.11): the LIST owns the columns
       on the wide tier via subgrid; 640–880 is a DESIGNED two-line reflow (the
       tablet band is never "the wide grid, squeezed"); phones get an iOS row
       that opens the branch. Filters are fragment-driven (§4.3). ══ */

    .h13-tile { display:flex; align-items:center; gap:.9rem; padding:1rem 1.15rem; }
    .h13-tile .h13-ic { width:44px; height:44px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.05rem; flex-shrink:0; }
    .h13-tile .h13-val { font-size:1.35rem; font-weight:800; line-height:1; color:var(--he-text-main,#0f172a); font-variant-numeric:tabular-nums; }
    .h13-tile .h13-lbl { font-size:.68rem; font-weight:700; letter-spacing:.5px; text-transform:uppercase; color:var(--he-text-muted,#64748b); }

    /* Dropdown-over-list (§4.2): the filter row must not sit inside the container. */
    .sah-filter-row { position:relative; z-index:30; }

    /* ── The aligned list (rewritten flat after owner review — no wrapper /
       display:contents interplay; cells are DIRECT children of the row). ── */
    .sah-list { display:grid; grid-template-columns:1fr; }
    .sah-row { grid-column:1 / -1; display:flex; flex-wrap:wrap; align-items:center; gap:.5rem .9rem;
        padding:.85rem 1.25rem; transition:background .18s var(--ease-out-expo); }
    .sah-row:hover { background:var(--he-bg-surface-raised); }
    .sah-row + .sah-row { border-top:1px solid rgba(15,23,42,.06); }

    .sah-id { display:flex; align-items:center; gap:.8rem; min-width:0; flex:1 1 240px; }
    .sah-ic { width:42px; height:42px; border-radius:12px; flex-shrink:0; display:flex; align-items:center; justify-content:center;
        background:var(--he-primary-soft); color:var(--he-primary); font-size:1rem; }
    .sah-text { flex:1 1 auto; min-width:0; } /* explicit shrink chain (§4.11 r5) */
    .sah-name { font-weight:700; color:var(--he-text-main); }
    .sah-sub { font-size:.76rem; color:var(--he-text-muted); }

    .sah-owner { display:flex; align-items:center; gap:.5rem; min-width:0; }
    .sah-cov { display:flex; align-items:center; gap:.45rem; font-size:.8rem; white-space:nowrap; font-variant-numeric:tabular-nums; }
    .sah-status { white-space:nowrap; }
    .sah-acts { display:flex; align-items:center; gap:.45rem; justify-content:flex-end; margin-left:auto; }

    /* Wide ≥880 container: one-line subgrid — every cell starts at the same x
       in every row (§4.11). Below 880: the flex row above wraps naturally into
       a compact stacked card (SA mobile is explicitly out of scope for now). */
    @container (min-width: 880px) {
        .sah-list { grid-template-columns:minmax(220px,1.3fr) minmax(170px,1fr) auto auto auto; column-gap:1.25rem; }
        .sah-row { display:grid; grid-template-columns:subgrid; }
        .sah-id { flex:none; }
        .sah-owner .sah-text { max-width:190px; }
        .sah-acts { margin-left:0; }
    }
</style>
@endpush

@section('content')
<div class="page-enter" x-data="hostelsManager()">
    <div class="he-page-head mb-4 stagger-1">
        <div>
            <h1 class="he-page-title">{{ __('Hostels') }}</h1>
            <p class="he-page-sub">{{ __('Every tenant branch across the platform — coverage, owners and admins.') }}</p>
        </div>
        <button type="button" @click="createModalOpen = true" class="btn btn-premium shadow-sm rounded-pill px-4 fw-semibold tactile-btn d-none d-md-inline-flex align-items-center">
            <i class="fa-solid fa-plus me-2"></i>{{ __('Add Hostel') }}
        </button>
    </div>

    <template x-teleport="body">
        <button type="button" class="fab" @click="createModalOpen = true" title="{{ __('Add Hostel') }}"><i class="fa-solid fa-plus"></i></button>
    </template>

    {{-- ── Fleet health ── --}}
    <div class="row g-3 mb-4 stagger-2">
        @foreach([
            ['primary', 'hotel', $stats['total'], __('Branches')],
            ['success', 'circle-check', $stats['active'], __('Active')],
            ['warning', 'hourglass-half', $stats['expiring'], __('Expiring ≤ 30d')],
            ['danger', 'circle-xmark', $stats['expired'], __('Expired')],
        ] as [$color, $icon, $val, $lbl])
            <div class="col-6 col-lg-3">
                <div class="panel-card shadow-sm h13-tile">
                    <div class="h13-ic bg-{{ $color }}-subtle text-{{ $color }}"><i class="fa-solid fa-{{ $icon }}"></i></div>
                    <div><div class="h13-val">{{ $val }}</div><div class="h13-lbl">{{ $lbl }}</div></div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- ── Filter bar: ONE row (§4.5), fragment-driven (§4.3) ── --}}
    <div class="mb-3 sah-filter-row stagger-3">
        <form method="GET" action="{{ route('superadmin.hostels.index') }}" x-ref="filterForm"
              data-fragment="#hostel-list" class="d-flex flex-nowrap gap-2 align-items-center">
            <div class="he-search he-search--inline he-search--clearable">
                <span class="he-search__icon"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="text" name="q" x-model="searchTerm" class="he-search__input"
                       placeholder="{{ __('Search name, owner, mobile, city…') }}"
                       @input.debounce.450ms="$el.form.requestSubmit()">
                <button type="button" class="he-search__clear" x-show="searchTerm" x-cloak @click="clearSearch()" title="{{ __('Clear search') }}">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <x-he-select name="status" icon="filter" icon-only-mobile :selected="request('status', '')"
                :options="[
                    '' => ['label' => __('All statuses'), 'icon' => 'filter'],
                    'active' => ['label' => __('Active'), 'icon' => 'circle-check'],
                    'expired' => ['label' => __('Expired'), 'icon' => 'circle-xmark'],
                    'suspended' => ['label' => __('Suspended'), 'icon' => 'ban'],
                ]" />
        </form>
    </div>

    <div id="hostel-list" data-fragment-container class="he-adaptive stagger-4">
        @include('superadmin.hostels._list')
    </div>

    @include('superadmin.hostels.modals')
</div>
@endsection
