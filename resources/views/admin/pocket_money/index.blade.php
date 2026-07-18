@extends('layouts.app')
@section('title', __('Pocket Money'))

@push('styles')
<style>
    /* Page-local layout only — W6.4 full redesign (the old page was a
       Bootstrap-column list, active students only, no search). */

    .pw-filter-row { position: relative; z-index: 30; }
    #pw-filter-aux { display: contents; }

    /* Wallet rows — container-tiered (§4.9/4.10). */
    .pw-row {
        align-items: center;
        gap: 0.75rem 1rem;
        grid-template-columns: minmax(0, 1fr) auto;
        grid-template-areas:
            "info acts"
            "bal  acts";
    }
    .pw-c-info { grid-area: info; display: flex; align-items: center; gap: 1rem; min-width: 0; }
    .pw-c-bal { grid-area: bal; display: flex; justify-content: flex-end; }
    .pw-row-acts {
        grid-area: acts;
        display: flex; align-items: center; justify-content: flex-end; gap: 0.5rem;
        padding-left: 1rem;
        border-left: 1px solid rgba(0, 0, 0, 0.06);
        align-self: stretch;
    }
    /* Wide ≥700: balance and action take FIXED tracks so balances line up down
       the list and every Open-Wallet button starts at the same x (§4.11 r1) —
       info (1fr) absorbs the slack. */
    @container (min-width: 700px) {
        .pw-row {
            grid-template-columns: minmax(240px, 1fr) 150px 180px;
            grid-template-areas: "info bal acts";
            column-gap: 1.25rem;
        }
        .pw-c-bal { justify-content: flex-end; }
        .pw-row-acts { padding-left: 1.25rem; align-self: center; }
    }

    .pw-balance {
        display: inline-flex; align-items: center; gap: 0.4rem;
        padding: 0.4rem 0.9rem;
        border-radius: var(--he-radius-full);
        font-weight: 700; font-feature-settings: 'tnum';
        white-space: nowrap; /* figures never wrap (§4.10) */
    }
    .pw-balance.is-positive { background: var(--he-success-soft, rgba(34,197,94,0.12)); color: var(--he-success, #16a34a); }
    .pw-balance.is-negative { background: var(--he-danger-soft); color: var(--he-danger); }
    .pw-balance.is-zero { background: var(--he-bg-canvas); color: var(--he-text-muted); }

    .pw-left-chip {
        display: inline-flex; align-items: center; gap: 0.3rem;
        margin-left: 0.4rem; padding: 0.2rem 0.55rem;
        background: var(--he-warning-soft, rgba(245, 158, 11, 0.12));
        color: var(--he-warning, #b45309);
        border-radius: var(--he-radius-full);
        font-size: 0.66rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;
        vertical-align: 2px;
    }
</style>
@endpush

@section('content')
<div class="page-enter" x-data="pocketMoney()">

    <div class="he-page-head mb-4 stagger-1">
        <div>
            <h1 class="he-page-title">{{ __('Pocket Money') }}</h1>
            <p class="he-page-sub">{{ __("Students' money in your custody — deposits, withdrawals, lending.") }}</p>
        </div>
    </div>

    {{-- Whole-book custody totals — never shrunk by search/filter. --}}
    <div class="he-stats mb-4 stagger-2">
        <div class="he-stats__grid" style="--he-stats-cols: 3;">
            <div class="he-stat he-stat--hero">
                <div class="he-stat__head">
                    <div class="he-stat__icon" style="background: rgba(255, 255, 255, 0.15); color: #93c5fd;"><i class="fa-solid fa-wallet"></i></div>
                    <div class="he-stat__label">{{ __('Total in Custody') }}</div>
                </div>
                <div class="he-stat__value">{{ hostelease_money($totals['custody']) }}</div>
            </div>
            <div class="he-stat">
                <div class="he-stat__head">
                    <div class="he-stat__icon" style="background: var(--he-primary-soft); color: var(--he-primary);"><i class="fa-solid fa-users"></i></div>
                    <div class="he-stat__label">{{ __('Open Wallets') }}</div>
                </div>
                <div class="he-stat__value">{{ $totals['wallets'] }}</div>
            </div>
            <div class="he-stat">
                <div class="he-stat__head">
                    <div class="he-stat__icon" style="background: var(--he-danger-soft); color: var(--he-danger);"><i class="fa-solid fa-hand-holding-hand"></i></div>
                    <div class="he-stat__label">{{ __('Lent Out (negative)') }}</div>
                </div>
                <div class="he-stat__value {{ $totals['negative'] > 0 ? 'text-danger' : '' }}">{{ $totals['negative'] }}</div>
            </div>
        </div>
    </div>

    {{-- Filter bar — ONE row (§4.5), fragment-driven (§4.3). --}}
    <div class="mb-4 pw-filter-row stagger-3">
        <form method="GET" action="{{ route('admin.pocket-money.index') }}" x-ref="filterForm"
              data-fragment="#pw-list, #pw-filter-aux"
              class="d-flex flex-nowrap gap-2 align-items-center">
            <div class="he-search he-search--inline he-search--clearable">
                <span class="he-search__icon"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="text" name="search" x-model="searchTerm" class="he-search__input"
                       placeholder="{{ __('Search by name or mobile...') }}"
                       @input.debounce.450ms="$el.form.requestSubmit()">
                <button type="button" class="he-search__clear" x-show="searchTerm" x-cloak
                        @click="clearSearch()" title="{{ __('Clear search') }}">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <span id="pw-filter-aux">
                <x-he-select name="filter" icon="filter" icon-only-mobile :selected="$filter ?? ''"
                    :options="[
                        '' => ['label' => __('All Wallets'), 'icon' => 'filter'],
                        'negative' => ['label' => __('Lent Out'), 'icon' => 'hand-holding-hand'],
                        'departed' => ['label' => __('Departed with money'), 'icon' => 'person-walking-arrow-right'],
                    ]" />
            </span>
        </form>
    </div>

    <div id="pw-list" data-fragment-container class="he-adaptive">
        @include('admin.pocket_money._list')
    </div>

</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('pocketMoney', () => ({
        searchTerm: @json($search ?? ''),

        clearSearch() {
            this.searchTerm = '';
            this.$nextTick(() => this.$refs.filterForm?.requestSubmit());
        },
    }));
});
</script>
@endpush
@endsection
