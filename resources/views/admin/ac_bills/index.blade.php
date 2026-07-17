@extends('layouts.app')
@section('title', __('AC Bills'))

@push('styles')
<style>
    /* Page-local layout only — heading/stats/buttons/chips/dropdowns are the
       canonical he-* patterns (laws 4.5/4.7/4.9/4.10). W6.3 full rebuild. */

    /* Dropdown-over-list rule (4.2): the filter row owns dropdowns — lift it.
       Never inside a container-type element. */
    .ac-filter-row { position: relative; z-index: 30; }
    #ac-filter-aux { display: contents; }

    /* ── List rows — container-tiered (4.9/4.10), the Finance grammar:
         ≥880px container  one line: info | money | acts
         640–879.98px      two lines: info top, money below, acts anchored right
         <640px            phone card (.he-cq-card)                       */
    .ac-row {
        align-items: center;
        gap: 0.75rem 1rem;
        grid-template-columns: minmax(0, 1fr) auto;
        grid-template-areas:
            "info  acts"
            "money acts";
        cursor: pointer;
    }
    .ac-c-info { grid-area: info; display: flex; align-items: center; gap: 1rem; min-width: 0; }
    .ac-c-text { display: flex; gap: 1rem; flex: 1; min-width: 0; }
    .ac-c-block { flex: 1 1 50%; min-width: 0; }
    .ac-row-money { grid-area: money; display: flex; justify-content: flex-end; gap: 1.5rem; }
    .ac-row-acts {
        grid-area: acts;
        display: flex; align-items: center; justify-content: flex-end; gap: 0.5rem;
        padding-left: 1rem;
        border-left: 1px solid rgba(0, 0, 0, 0.06);
        align-self: stretch;
        cursor: default;
    }
    @container (min-width: 880px) {
        .ac-row {
            grid-template-columns: minmax(240px, 1fr) auto auto;
            grid-template-areas: "info money acts";
            column-gap: 1.25rem;
        }
        .ac-row-acts { padding-left: 1.25rem; align-self: center; }
    }
    .ac-row-num {
        min-width: 96px;
        text-align: right;
        font-feature-settings: 'tnum'; font-variant-numeric: tabular-nums;
        white-space: nowrap; /* figures never wrap mid-digit (4.10) */
    }
    .ac-row-lbl {
        font-size: 0.62rem; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.06em; color: var(--he-text-muted); margin-bottom: 0.15rem;
    }

    .ac-avatar {
        width: 44px; height: 44px; border-radius: 14px;
        display: flex; align-items: center; justify-content: center;
        background: var(--he-info-soft); color: var(--he-info);
        font-size: 1rem;
    }
    .ac-avatar--sm { width: 40px; height: 40px; font-size: 0.9rem; }

    /* Collection progress under the collected figure. */
    .ac-progress {
        height: 4px; border-radius: 4px; overflow: hidden;
        background: var(--he-bg-canvas); margin-top: 0.3rem;
    }
    .ac-progress-fill { height: 100%; border-radius: 4px; background: var(--he-success); }

    /* Expand chevron — rotation only, never a retained transform on an
       ancestor of a dropdown (4.2); this is a leaf icon. */
    .ac-chevron i { transition: transform 0.25s var(--ease-out-expo); }
    .ac-chevron.is-open i { transform: rotate(180deg); }

    /* ── Expanded detail: the bill retelling its own split ── */
    .ac-detail {
        margin-top: 1rem; padding-top: 1rem;
        border-top: 1px dashed rgba(0, 0, 0, 0.12);
        cursor: default;
    }
    .ac-meter { display: flex; flex-wrap: wrap; gap: 0.6rem; margin-bottom: 0.85rem; }
    .ac-meter-cell {
        flex: 1 1 150px;
        background: var(--he-bg-canvas); border-radius: var(--he-radius-md);
        padding: 0.55rem 0.85rem;
        display: flex; flex-direction: column; gap: 0.1rem;
        white-space: nowrap;
    }
    .ac-note {
        background: var(--he-info-soft); color: var(--he-info);
        border-radius: var(--he-radius-md);
        padding: 0.55rem 0.85rem; font-size: 0.8rem; font-weight: 600;
        margin-bottom: 0.85rem;
    }
    /* Metered segments (W6.3): the visible proof behind every share —
       which stretch of the meter each occupant set consumed. */
    .ac-segments {
        border: 1px dashed rgba(0, 0, 0, 0.12);
        border-radius: var(--he-radius-md);
        padding: 0.35rem 0.85rem;
        margin-bottom: 0.85rem;
    }
    .ac-segment {
        display: flex; flex-wrap: wrap; align-items: center;
        gap: 0.35rem 1.25rem;
        padding: 0.4rem 0;
        font-size: 0.8rem;
    }
    .ac-segment + .ac-segment { border-top: 1px solid rgba(0, 0, 0, 0.05); }
    .ac-seg-chip {
        margin-left: auto;
        padding: 0.15rem 0.6rem;
        border-radius: var(--he-radius-full);
        font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;
        background: var(--he-success-soft, rgba(34, 197, 94, 0.12)); color: var(--he-success, #16a34a);
    }
    .ac-seg-chip.is-est {
        background: var(--he-warning-soft, rgba(245, 158, 11, 0.12));
        color: var(--he-warning, #b45309);
    }

    .ac-shares { border: 1px solid rgba(0, 0, 0, 0.07); border-radius: var(--he-radius-md); overflow: hidden; }
    .ac-share-row {
        display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem 1.25rem;
        padding: 0.65rem 0.85rem;
    }
    .ac-share-row + .ac-share-row { border-top: 1px solid rgba(0, 0, 0, 0.05); }
    .ac-share-who {
        flex: 1 1 240px; min-width: 0;
        display: flex; align-items: center; gap: 0.65rem;
        text-decoration: none;
    }
    .ac-share-who:hover .fw-semibold { color: var(--he-primary) !important; }
    .ac-share-avatar {
        width: 32px; height: 32px; border-radius: 10px; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        background: var(--he-primary-soft); color: var(--he-primary);
        font-size: 0.8rem; font-weight: 700;
    }
    .ac-share-num {
        text-align: right; min-width: 84px;
        font-feature-settings: 'tnum'; white-space: nowrap;
    }
    .ac-share-num .ac-row-lbl { margin-bottom: 0; }

    /* ── Generate modal ── */
    .ac-month-chips {
        display: grid; gap: 0.5rem;
        grid-template-columns: repeat(auto-fill, minmax(96px, 1fr));
    }
    .ac-mchip {
        border: 1px solid rgba(0, 0, 0, 0.08); border-radius: var(--he-radius-full);
        background: var(--he-bg-surface); color: var(--he-text-main);
        padding: 0.45rem 0.5rem; font-size: 0.8rem; font-weight: 600;
        text-align: center; cursor: pointer; white-space: nowrap;
        transition: all 0.18s var(--ease-out-expo);
    }
    .ac-mchip:hover { border-color: var(--he-primary); color: var(--he-primary); }
    .ac-mchip.active { background: var(--he-primary); border-color: var(--he-primary); color: #fff; }
    .ac-read-row { display: flex; align-items: center; gap: 0.75rem; }
    .ac-read-row + .ac-read-row { margin-top: 0.6rem; }
    .ac-read-lbl { flex: 0 0 108px; font-size: 0.8rem; font-weight: 700; color: var(--he-text-main); }
    .ac-rate-save {
        border: 1px solid rgba(0, 0, 0, 0.08); border-radius: 10px;
        width: 44px; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        background: var(--he-bg-surface); color: var(--he-text-muted);
        transition: all 0.18s var(--ease-out-expo);
    }
    .ac-rate-save:hover { border-color: var(--he-primary); color: var(--he-primary); }
    .ac-rate-save.is-saved { border-color: var(--he-success); color: var(--he-success); background: var(--he-success-soft, rgba(34,197,94,0.12)); }
    .ac-sum-month {
        border: 1px solid rgba(0, 0, 0, 0.07); border-radius: var(--he-radius-md);
        padding: 0.75rem 0.9rem;
    }
    .ac-sum-month + .ac-sum-month { margin-top: 0.6rem; }
    .ac-sum-warn {
        display: flex; align-items: center; gap: 0.4rem;
        font-size: 0.78rem; font-weight: 600;
        border-radius: var(--he-radius-sm); padding: 0.35rem 0.6rem; margin-top: 0.45rem;
    }
    .ac-sum-warn--danger { background: var(--he-danger-soft); color: var(--he-danger); }
    .ac-sum-warn--amber { background: var(--he-warning-soft, rgba(245, 158, 11, 0.12)); color: var(--he-warning, #b45309); }
    .ac-sum-share { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; padding: 0.3rem 0; font-size: 0.85rem; }
    .ac-sum-share + .ac-sum-share { border-top: 1px solid rgba(0, 0, 0, 0.04); }

    @media (max-width: 576px) {
        #ac-list { padding-bottom: 5rem; } /* clear the FAB */
    }
</style>
@endpush

@section('content')
@php
    $monthOptions = collect(range(0, 11))->map(fn ($i) => [
        'value' => now()->subMonthsNoOverflow($i)->format('Y-m'),
        'label' => now()->subMonthsNoOverflow($i)->format('M Y'),
    ])->values();
@endphp

<div class="page-enter" x-data="acBoard()">

    {{-- ══ Header ══ --}}
    <div class="he-page-head mb-4 stagger-1">
        <div>
            <h1 class="he-page-title">{{ __('AC Bills') }}</h1>
            <p class="he-page-sub">{{ __('Meter-based room bills, split day-wise among the occupants.') }}</p>
        </div>
        {{-- Desktop action; phones get the FAB (never a wrapped header button). --}}
        <button type="button" class="btn btn-premium rounded-pill px-4 fw-semibold shadow-sm tactile-btn d-none d-md-inline-flex align-items-center"
                @click="openGenerate()">
            <i class="fa-solid fa-bolt me-2"></i>{{ __('Generate AC Bill') }}
        </button>
    </div>

    {{-- ══ Window tiles (fragment target — the month filter re-renders them) ══ --}}
    <div id="ac-summary">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <span class="text-muted small fw-bold text-uppercase" style="letter-spacing: 1px;">{{ __('AC Billing') }}</span>
            <span class="text-muted small fw-semibold">{{ $filterMonth->format('F Y') }}</span>
        </div>
        <div class="he-stats mb-4 stagger-2">
            <div class="he-stats__grid" style="--he-stats-cols: 3;">
                <div class="he-stat he-stat--hero">
                    <div class="he-stat__head">
                        <div class="he-stat__icon" style="background: rgba(255, 255, 255, 0.15); color: #7dd3fc;"><i class="fa-solid fa-snowflake"></i></div>
                        <div class="he-stat__label">{{ __('Total AC Billed') }}</div>
                    </div>
                    <div class="he-stat__value">{{ hostelease_money($summary['billed']) }}</div>
                </div>
                <div class="he-stat">
                    <div class="he-stat__head">
                        <div class="he-stat__icon" style="background: var(--he-success-soft); color: var(--he-success);"><i class="fa-solid fa-sack-dollar"></i></div>
                        <div class="he-stat__label">{{ __('AC Collected') }}</div>
                    </div>
                    <div class="he-stat__value text-success">{{ hostelease_money($summary['collected']) }}</div>
                </div>
                <div class="he-stat">
                    <div class="he-stat__head">
                        <div class="he-stat__icon" style="background: var(--he-danger-soft); color: var(--he-danger);"><i class="fa-solid fa-clock-rotate-left"></i></div>
                        <div class="he-stat__label">{{ __('AC Dues') }}</div>
                    </div>
                    <div class="he-stat__value {{ $summary['due'] > 0 ? 'text-danger' : 'text-muted' }}">{{ hostelease_money($summary['due']) }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- ══ Filter bar — ONE row (4.5), fragment-driven (4.3) ══ --}}
    <div class="mb-4 ac-filter-row stagger-3">
        {{-- he-filters--nosearch: this page has no search box, so the two
             filters keep their labels at every width and share the row
             (capped at 260px each) instead of collapsing to two lonely icons
             beside a screen of dead space. --}}
        <form method="GET" action="{{ route('admin.ac-bills.index') }}" x-ref="filterForm"
              data-fragment="#ac-summary, #ac-filter-aux, #ac-list"
              class="he-filters--nosearch d-flex flex-nowrap gap-2 align-items-center">
            <div class="he-datechip" title="{{ __('Billing month') }}">
                <input type="month" name="month" x-model="filterMonth" max="{{ now()->format('Y-m') }}"
                       @click="try { $el.showPicker() } catch (e) {}"
                       @change="$el.form.requestSubmit()" aria-label="{{ __('Billing month') }}">
                <span class="he-datechip__ic"><i class="fa-solid fa-calendar-days"></i></span>
                <span class="he-datechip__txt">
                    <span class="he-datechip__lbl">{{ __('Month') }}</span>
                    <span class="fw-bold small text-dark" style="line-height: 1.1;" x-text="fmtMonth(filterMonth)"></span>
                </span>
            </div>

            <span id="ac-filter-aux">
                <x-he-select name="floor" icon="layer-group" icon-only-mobile :selected="(string) ($filterFloor ?? '')"
                    :options="['' => ['label' => __('All Floors'), 'icon' => 'layer-group']]
                        + $floors->mapWithKeys(fn ($f) => [(string) $f->id => ['label' => $f->name, 'icon' => 'layer-group']])->all()" />
            </span>
        </form>
    </div>

    {{-- ══ The list (fragment container + measuring container, 4.3/4.9) ══ --}}
    <div id="ac-list" data-fragment-container class="he-adaptive">
        @include('admin.ac_bills._list')
    </div>

    {{-- FAB (phones) --}}
    <template x-teleport="body">
        <button type="button" class="fab" @click="openGenerate()" title="{{ __('Generate AC Bill') }}">
            <i class="fa-solid fa-bolt"></i>
        </button>
    </template>

    {{-- ═══════════════ Generate AC Bill (W6.3 rebuild) ═══════════════
         Room picker with occupant/reading context; one-or-many months with
         CHAINED readings (each month's start = the previous month's end);
         a per-hostel saveable unit rate; and a live summary computed by the
         SAME server service that store() uses — the shares the owner reads
         are the shares that get invoiced. --}}
    <template x-teleport="body">
        <div class="custom-overlay-backdrop" x-show="genOpen" x-transition.opacity @click="closeGenerate()" x-cloak style="display: none;">
            <form method="POST" action="{{ route('admin.ac-bills.store') }}" data-ring-required
                  class="custom-overlay-modal" :class="{ 'is-open': genOpen }" x-show="genOpen" x-transition.opacity @click.stop style="display: none;">
                @csrf
                <input type="hidden" name="room_id" :value="roomId ?? ''">
                <input type="hidden" name="prev_reading" :value="prevReading">
                <input type="hidden" name="unit_price" :value="rate">
                <template x-for="m in sortedMonths" :key="'m-' + m">
                    <input type="hidden" name="months[]" :value="m">
                </template>
                <template x-for="m in sortedMonths" :key="'r-' + m">
                    <input type="hidden" name="readings[]" :value="readings[m] ?? ''">
                </template>

                <div class="custom-overlay-header">
                    <h5 class="fw-bold mb-0">
                        <i class="fa-solid fa-bolt" style="color: var(--he-warning, #f59e0b);"></i>
                        <span class="ms-1">{{ __('Generate AC Bill') }}</span>
                    </h5>
                    <button type="button" class="btn-close" @click="closeGenerate()"></button>
                </div>

                <div class="custom-overlay-body">

                    {{-- Room picker — occupant + reading context on every row --}}
                    <div class="mb-4">
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Room') }} <span class="text-danger">*</span></label>
                        <div class="he-picker" :class="{ 'is-open': roomOpen }" @click.outside.capture="roomOpen = false">
                            <button type="button" class="he-picker-trigger" x-ref="roomTrigger" @click="toggleRoomPicker()">
                                <span class="d-flex align-items-center gap-2 text-truncate">
                                    <span class="he-picker-avatar" style="width: 28px; height: 28px; font-size: 0.75rem;" x-show="room"><i class="fa-solid fa-snowflake"></i></span>
                                    <span :class="room ? 'fw-semibold text-dark' : 'text-muted'" x-text="roomLabel"></span>
                                </span>
                                <i class="fa-solid fa-chevron-down chevron"></i>
                            </button>
                            <div class="he-picker-panel" x-ref="roomPanel" x-show="roomOpen" x-transition.opacity x-cloak style="display: none;">
                                <div class="he-picker-search">
                                    <input type="text" x-model="roomSearch" x-ref="roomSearchInput"
                                           class="form-control form-control-sm bg-light border-0" placeholder="{{ __('Search room number…') }}">
                                </div>
                                <div class="he-picker-list">
                                    <template x-for="r in filteredRooms" :key="r.id">
                                        <button type="button" class="he-picker-option" @click="selectRoom(r)">
                                            <span class="he-picker-avatar"><i class="fa-solid fa-snowflake"></i></span>
                                            <span class="flex-grow-1" style="min-width: 0;">
                                                <span class="d-block fw-bold text-dark text-truncate">
                                                    {{ __('Room') }} <span x-text="r.number"></span>
                                                    <span class="fw-normal text-muted small" x-show="r.floor" x-text="'· ' + r.floor"></span>
                                                </span>
                                                <span class="d-block small text-muted text-truncate"
                                                      x-text="r.occupants.length ? r.occupants.join(', ') : '{{ __('empty now (history still billable)') }}'"></span>
                                            </span>
                                            <span class="text-end flex-shrink-0 small text-muted" style="line-height: 1.25;">
                                                <span class="d-block" x-text="'{{ __('meter') }} ' + fmt(r.last_reading)"></span>
                                                <span class="d-block" x-text="r.last_billed_label ? '{{ __('billed till') }} ' + r.last_billed_label : '{{ __('never billed') }}'"></span>
                                            </span>
                                        </button>
                                    </template>
                                    <div class="he-picker-empty" x-show="filteredRooms.length === 0">{{ __('No AC rooms match') }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Months — one or many; readings chain below --}}
                    <div class="mb-4">
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1 d-flex align-items-center gap-2">
                            <span>{{ __('Bill Month(s)') }} <span class="text-danger">*</span></span>
                            <span class="badge rounded-pill" style="background: var(--he-primary-soft); color: var(--he-primary);"
                                  x-show="months.length > 1" x-cloak x-text="months.length + ' {{ __('months') }}'"></span>
                        </label>
                        <div class="ac-month-chips">
                            @foreach($monthOptions as $m)
                                <button type="button" class="ac-mchip" :class="{ active: months.includes('{{ $m['value'] }}') }"
                                        @click="toggleMonth('{{ $m['value'] }}')">{{ $m['label'] }}</button>
                            @endforeach
                        </div>
                    </div>

                    {{-- Readings — chained: each month's start is the previous end --}}
                    <div class="mb-4" x-show="months.length > 0" x-cloak>
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Meter Readings') }}</label>
                        <div class="ac-read-row">
                            <span class="ac-read-lbl">{{ __('Start') }}</span>
                            <div class="input-group input-group-sm">
                                <input type="number" class="form-control bg-light fw-bold" x-model.number="prevReading"
                                       min="0" step="0.01" required aria-label="{{ __('Start reading') }}">
                                <span class="input-group-text bg-light text-muted small">{{ __('last recorded') }}</span>
                            </div>
                        </div>
                        <template x-for="m in sortedMonths" :key="'in-' + m">
                            <div class="ac-read-row">
                                <span class="ac-read-lbl" x-text="'{{ __('End of') }} ' + monthLabels[m]"></span>
                                <input type="number" class="form-control form-control-sm bg-light fw-bold" x-model.number="readings[m]"
                                       min="0" step="0.01" required :placeholder="'{{ __('meter at end of') }} ' + monthLabels[m]"
                                       :aria-label="'{{ __('Reading at end of') }} ' + monthLabels[m]">
                            </div>
                        </template>
                    </div>

                    {{-- Rate — saveable as the hostel default --}}
                    <div class="mb-4">
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Unit Rate') }} <span class="text-danger">*</span></label>
                        <div class="d-flex gap-2">
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted fw-bold">₹</span>
                                <input type="number" class="form-control bg-light fw-bold" x-model.number="rate" required min="0.01" step="0.01">
                                <span class="input-group-text bg-light text-muted small">/ {{ __('unit') }}</span>
                            </div>
                            <button type="button" class="ac-rate-save" :class="{ 'is-saved': rateSaved }" @click="saveRate()"
                                    :title="rateSaved ? '{{ __('Saved as hostel default') }}' : '{{ __('Save as hostel default') }}'"
                                    :aria-label="rateSaved ? '{{ __('Saved as hostel default') }}' : '{{ __('Save as hostel default') }}'">
                                <i class="fa-solid" :class="rateSaved ? 'fa-check' : 'fa-floppy-disk'"></i>
                            </button>
                        </div>
                        <div class="form-text small" x-show="rateSaved" x-cloak>{{ __('Saved — this rate is now the default for every AC bill.') }}</div>
                    </div>

                    {{-- Live summary — the server's own math, previewed --}}
                    <div class="mb-2" x-show="months.length > 0 && room" x-cloak>
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1 d-flex align-items-center gap-2">
                            <span>{{ __('Summary') }}</span>
                            <i class="fa-solid fa-circle-notch fa-spin text-muted small" x-show="previewBusy" x-cloak></i>
                        </label>
                        <template x-if="preview">
                            <div>
                                <template x-for="pm in preview.months" :key="'pm-' + pm.month">
                                    <div class="ac-sum-month">
                                        <div class="d-flex align-items-center justify-content-between gap-2">
                                            <span class="fw-bold text-dark" x-text="pm.label"></span>
                                            <span class="fw-bold text-dark" style="font-feature-settings: 'tnum';"
                                                  x-text="fmt(pm.units) + ' {{ __('units') }} · ₹' + fmt(pm.amount)"></span>
                                        </div>
                                        <div class="ac-sum-warn ac-sum-warn--danger" x-show="pm.already_billed">
                                            <i class="fa-solid fa-circle-exclamation"></i>
                                            {{ __('Already billed for this month — edit that bill instead.') }}
                                        </div>
                                        <div class="ac-sum-warn ac-sum-warn--amber" x-show="pm.gap_before">
                                            <i class="fa-solid fa-triangle-exclamation"></i>
                                            {{ __("Includes an unbilled gap before it — that gap's units land in this bill. Select the missing month too to split correctly.") }}
                                        </div>
                                        <div class="ac-sum-warn ac-sum-warn--amber" x-show="pm.note" x-text="pm.note"></div>
                                        <div class="ac-sum-warn ac-sum-warn--danger" x-show="pm.students.length === 0">
                                            <i class="fa-solid fa-circle-exclamation"></i>
                                            {{ __('No one occupied the room this month — nobody to bill.') }}
                                        </div>
                                        {{-- Metered segments: how the meter's stretch maps to
                                             occupant sets (only worth showing when it splits). --}}
                                        <template x-if="pm.segments && pm.segments.length > 1">
                                            <div class="mt-2">
                                                <template x-for="(sg, si) in pm.segments" :key="pm.month + '-sg-' + si">
                                                    <div class="ac-sum-share">
                                                        <span class="text-muted small text-truncate" style="min-width: 0;">
                                                            <span class="fw-semibold text-dark" x-text="sg.from + ' – ' + sg.to"></span>
                                                            <span x-text="' · ' + (sg.units !== null ? fmt(sg.units) + ' {{ __('units') }} · ' : '') + sg.occupants + ' {{ __('occupants') }}'"></span>
                                                            <span :style="sg.estimated ? 'color: var(--he-warning, #b45309);' : 'color: var(--he-success, #16a34a);'"
                                                                  x-text="' · ' + (sg.estimated ? '{{ __('estimated by days') }}' : '{{ __('metered') }}')"></span>
                                                        </span>
                                                        <span class="fw-semibold text-dark flex-shrink-0 small" style="font-feature-settings: 'tnum';" x-text="'₹' + fmt(sg.amount)"></span>
                                                    </div>
                                                </template>
                                            </div>
                                        </template>
                                        <div class="mt-2" x-show="pm.students.length > 0">
                                            <template x-for="s in pm.students" :key="pm.month + '-' + s.student_id">
                                                <div class="ac-sum-share">
                                                    <span class="text-truncate" style="min-width: 0;">
                                                        <span class="fw-semibold text-dark" x-text="s.name"></span>
                                                        <span class="text-muted small" x-text="' · ' + s.days + ' {{ __('of') }} ' + pm.days_in_month + ' {{ __('days') }}'"></span>
                                                        <span class="text-info small" x-show="s.joined_mid"> · {{ __('joined mid-month') }}</span>
                                                        <span class="small" style="color: var(--he-warning, #b45309);" x-show="s.left"> · {{ __('left') }}</span>
                                                    </span>
                                                    <span class="fw-bold text-dark flex-shrink-0" style="font-feature-settings: 'tnum';" x-text="'₹' + fmt(s.share)"></span>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                                <div class="d-flex align-items-center justify-content-between px-1 pt-2">
                                    <span class="text-muted small text-uppercase fw-bold" style="letter-spacing: 0.5px;" x-text="grandCountLabel"></span>
                                    <span class="fw-bold fs-5 text-dark" style="font-feature-settings: 'tnum';" x-text="'₹' + fmt(grandTotal)"></span>
                                </div>
                            </div>
                        </template>
                        <div class="text-muted small" x-show="!preview && !previewBusy">{{ __('Fill the readings to see the split.') }}</div>
                    </div>
                </div>

                <div class="custom-overlay-footer bg-light">
                    <button type="button" class="btn btn-white border fw-semibold rounded-pill px-4 tactile-btn" @click="closeGenerate()">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-premium fw-semibold rounded-pill px-4 shadow-sm tactile-btn" :disabled="submitDisabled">
                        <i class="fa-solid fa-check me-2"></i><span x-text="submitLabel"></span>
                    </button>
                </div>
            </form>
        </div>
    </template>

    {{-- ═══════════════ Edit readings / rate ═══════════════ --}}
    <template x-teleport="body">
        <div class="custom-overlay-backdrop" x-show="editOpen" x-transition.opacity @click="editOpen = false" x-cloak style="display: none;">
            <form method="POST" :action="e.action" data-ring-required
                  class="custom-overlay-modal" :class="{ 'is-open': editOpen }" x-show="editOpen" x-transition.opacity @click.stop style="display: none;">
                @csrf @method('PATCH')
                <div class="custom-overlay-header">
                    <h5 class="fw-bold mb-0">
                        <i class="fa-solid fa-pen text-primary me-1"></i>
                        {{ __('Edit AC Bill') }}
                        <div class="fs-6 fw-normal text-muted mt-1" x-text="'{{ __('Room') }} ' + e.room + ' · ' + e.month"></div>
                    </h5>
                    <button type="button" class="btn-close" @click="editOpen = false"></button>
                </div>
                <div class="custom-overlay-body">
                    <div class="row gx-3">
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Previous Reading') }} <span class="text-danger">*</span></label>
                            <input type="number" name="previous_reading" x-model.number="e.prev" class="form-control bg-light fw-bold" required min="0" step="0.01">
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Current Reading') }} <span class="text-danger">*</span></label>
                            <input type="number" name="current_reading" x-model.number="e.curr" class="form-control bg-light fw-bold" required :min="e.prev" step="0.01">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Unit Rate (₹)') }} <span class="text-danger">*</span></label>
                        <input type="number" name="unit_price" x-model.number="e.rate" class="form-control bg-light fw-bold" required min="0.01" step="0.01">
                    </div>
                    <div class="form-text small">
                        <i class="fa-solid fa-circle-info me-1"></i>{{ __("Saving recomputes every student's day-wise share. If a new share would drop below what a student has already paid, the edit is refused with their name — reverse that receipt first.") }}
                    </div>
                </div>
                <div class="custom-overlay-footer bg-light">
                    <button type="button" class="btn btn-white border fw-semibold rounded-pill px-4 tactile-btn" @click="editOpen = false">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-premium fw-semibold rounded-pill px-4 shadow-sm tactile-btn">
                        <i class="fa-solid fa-check me-2"></i>{{ __('Save & Recompute') }}
                    </button>
                </div>
            </form>
        </div>
    </template>

</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('acBoard', () => ({
        // Filter readout (never fragment-swapped — Alpine is its only source).
        filterMonth: @json($filterMonth->format('Y-m')),

        // --- Generate sheet ---
        genOpen: false,
        rooms: {{ Illuminate\Support\Js::from($pickerRooms) }},
        monthLabels: {{ Illuminate\Support\Js::from($monthOptions->pluck('label', 'value')) }},
        roomId: null,
        roomSearch: '',
        roomOpen: false,
        months: [],
        readings: {},
        prevReading: 0,
        rate: @json($defaultUnitPrice),
        rateSaved: false,

        // --- Live preview (server-computed; see ac-bills.preview) ---
        preview: null,
        previewBusy: false,
        previewTimer: null,
        previewSeq: 0,

        // --- Edit sheet ---
        editOpen: false,
        e: { action: '', room: '', month: '', prev: 0, curr: 0, rate: 0 },

        get room() { return this.rooms.find(r => r.id === this.roomId) ?? null; },
        get roomLabel() {
            if (! this.room) return @json(__('Choose an AC room…'));
            const occ = this.room.occupants.length;
            return @json(__('Room')) + ' ' + this.room.number + ' · ' + occ + ' ' + (occ === 1 ? @json(__('student')) : @json(__('students')));
        },
        get filteredRooms() {
            const q = this.roomSearch.trim().toLowerCase();
            if (! q) return this.rooms;
            return this.rooms.filter(r => r.number.toLowerCase().includes(q)
                || (r.floor ?? '').toLowerCase().includes(q)
                || r.occupants.some(n => n.toLowerCase().includes(q)));
        },
        get sortedMonths() { return [...this.months].sort(); },
        get readingsComplete() {
            return this.sortedMonths.every(m => this.readings[m] !== undefined && this.readings[m] !== '' && this.readings[m] !== null);
        },
        get grandTotal() {
            return this.preview ? this.preview.months.reduce((t, m) => t + (Number(m.amount) || 0), 0) : 0;
        },
        get grandCountLabel() {
            if (! this.preview) return '';
            const bills = this.preview.months.length;
            const invoices = this.preview.months.reduce((t, m) => t + m.students.length, 0);
            return bills + ' ' + (bills === 1 ? @json(__('bill')) : @json(__('bills'))) + ' · ' + invoices + ' ' + @json(__('invoices'));
        },
        get submitDisabled() {
            if (! this.roomId || this.months.length === 0 || ! this.readingsComplete || ! (Number(this.rate) > 0)) return true;
            if (this.previewBusy || ! this.preview) return true;
            return this.preview.months.some(m => m.already_billed || m.students.length === 0);
        },
        get submitLabel() {
            const n = this.months.length;
            return n > 1 ? @json(__('Generate')) + ' ' + n + ' ' + @json(__('Bills')) : @json(__('Generate Bill'));
        },

        fmt(v) { return Number(v || 0).toLocaleString('en-IN', { maximumFractionDigits: 2 }); },
        fmtMonth(ym) {
            if (! ym) return '';
            const [y, m] = ym.split('-').map(Number);
            return new Date(Date.UTC(y, m - 1, 1)).toLocaleDateString('en-IN', { month: 'short', year: 'numeric', timeZone: 'UTC' });
        },

        openGenerate() {
            this.roomId = null; this.roomSearch = ''; this.roomOpen = false;
            this.months = []; this.readings = {}; this.prevReading = 0;
            this.preview = null; this.rateSaved = false;
            this.genOpen = true;
            document.body.style.overflow = 'hidden';
        },
        closeGenerate() { this.genOpen = false; document.body.style.overflow = ''; },

        toggleRoomPicker() {
            this.roomOpen = ! this.roomOpen;
            // Measure once visible (4.7) — this panel is tall, and near the
            // bottom of the viewport it must flip upward, never grow the page.
            if (this.roomOpen) this.$nextTick(() => {
                window.hePlaceMenu?.(this.$refs.roomTrigger, this.$refs.roomPanel);
                this.$refs.roomSearchInput?.focus();
            });
        },
        selectRoom(r) {
            this.roomId = r.id;
            this.prevReading = r.last_reading; // the chain's anchor — editable
            this.roomOpen = false;
            this.schedulePreview();
        },
        toggleMonth(m) {
            if (this.months.includes(m)) {
                this.months = this.months.filter(x => x !== m);
                delete this.readings[m];
            } else {
                this.months.push(m);
            }
            this.schedulePreview();
        },

        // Debounced; sequence-guarded so a slow early response can never
        // overwrite a fast later one (same discipline as fragment filters).
        schedulePreview() {
            clearTimeout(this.previewTimer);
            if (! this.roomId || this.months.length === 0 || ! this.readingsComplete || ! (Number(this.rate) > 0)) {
                this.preview = null;
                return;
            }
            this.previewBusy = true;
            this.previewTimer = setTimeout(() => this.fetchPreview(), 400);
        },
        async fetchPreview() {
            const seq = ++this.previewSeq;
            try {
                const res = await window.axios.post(@json(route('admin.ac-bills.preview')), {
                    room_id: this.roomId,
                    unit_price: Number(this.rate),
                    prev_reading: Number(this.prevReading) || 0,
                    months: this.sortedMonths,
                    readings: this.sortedMonths.map(m => Number(this.readings[m])),
                });
                if (seq !== this.previewSeq) return; // superseded
                this.preview = res.data;
            } catch (err) {
                if (seq !== this.previewSeq) return;
                this.preview = null;
            } finally {
                if (seq === this.previewSeq) this.previewBusy = false;
            }
        },

        async saveRate() {
            if (! (Number(this.rate) > 0)) return;
            try {
                await window.axios.patch(@json(route('admin.ac-bills.unit-rate')), { unit_price: Number(this.rate) });
                this.rateSaved = true;
                setTimeout(() => { this.rateSaved = false; }, 2500);
            } catch (err) { /* validation errors just leave the icon unsaved */ }
        },

        openEdit(payload) {
            this.e = { ...payload };
            this.editOpen = true;
        },

        init() {
            // Readings/rate/start changes re-run the preview (deep-ish watch).
            this.$watch('readings', () => this.schedulePreview());
            this.$watch('rate', () => this.schedulePreview());
            this.$watch('prevReading', () => this.schedulePreview());
        },
    }));
});
</script>
@endpush
@endsection
