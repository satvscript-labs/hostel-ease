@extends('layouts.app')
@section('title', __('Staff & Payroll'))

@push('styles')
<style>
    /* Page-local layout only — W7.1 full rebuild on the canonical system
       (he-page-head, he-stats, he-search, he-cq tiers, he-money-list, he-pager).
       The old page was pre-design-system: gradient-mesh hero tiles that crushed
       on phones, an unbounded unpaginated grid, client-side search, and a Pay
       Salary modal offering a payment mode the server rejects. */

    /* Dropdown-over-list rule (§4.2). Never inside a container-type element. */
    .st-filter-row { position: relative; z-index: 30; }
    #st-filter-aux { display: contents; }

    /* List rows — container-tiered (§4.9/4.10). */
    .st-row {
        align-items: center;
        gap: 0.75rem 1rem;
        grid-template-columns: minmax(0, 1fr) auto;
        grid-template-areas:
            "info  acts"
            "money acts";
    }
    .st-c-info { grid-area: info; display: flex; align-items: center; gap: 1rem; min-width: 0; }
    .st-c-text { display: flex; gap: 1rem; flex: 1; min-width: 0; }
    .st-c-block { flex: 1 1 50%; min-width: 0; }
    .st-row-money { grid-area: money; display: flex; justify-content: flex-end; gap: 1.5rem; }
    .st-row-acts {
        grid-area: acts;
        display: flex; align-items: center; justify-content: flex-end; gap: 0.5rem;
        padding-left: 1rem;
        border-left: 1px solid rgba(0, 0, 0, 0.06);
        align-self: stretch;
    }
    /* Wide ≥880: the money group DISSOLVES (display:contents) and each figure
       takes a FIXED track, so Salary/Present/Paid line up down the whole list
       (§4.11 r1 — alignment is structural, not per-row luck). acts is fixed too
       so info (1fr) absorbs all slack and every trailing column shares one x —
       the acts border-left becomes a clean vertical rule down the list. */
    @container (min-width: 880px) {
        .st-row {
            grid-template-columns: minmax(240px, 1fr) 116px 92px 116px 148px;
            grid-template-areas: "info salary present paid acts";
            column-gap: 1.25rem;
        }
        .st-row-money { display: contents; }
        .st-cell-salary  { grid-area: salary; }
        .st-cell-present { grid-area: present; }
        .st-cell-paid    { grid-area: paid; }
        .st-row-num { min-width: 0; } /* the fixed track governs width now */
        .st-row-acts { padding-left: 1.25rem; align-self: center; }
    }
    .st-row-num {
        min-width: 84px;
        text-align: right;
        font-feature-settings: 'tnum';
        font-variant-numeric: tabular-nums;
        white-space: nowrap; /* figures never wrap (§4.10) */
    }
    .st-row-lbl {
        font-size: 0.62rem; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.06em; color: var(--he-text-muted); margin-bottom: 0.15rem;
    }

    /* Directory state markers. */
    .st-chip {
        display: inline-flex; align-items: center; gap: 0.3rem;
        margin-left: 0.4rem; padding: 0.2rem 0.55rem;
        border-radius: var(--he-radius-full);
        font-size: 0.66rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;
        vertical-align: 2px;
    }
    .st-chip--inactive { background: var(--he-bg-surface-raised); color: var(--he-text-muted); }
    .st-chip--removed { background: var(--he-danger-soft); color: var(--he-danger); }

    /* Two tabs, one phone row. .he-tabs answers overflow with a horizontal
       scroll, which is right for many tabs and wrong for exactly two: the
       second one ends up hanging off the edge instead of sitting beside the
       first. Here they split the row evenly and align with the cards below.
       (Page-local by §0.1 — this is the only page that needs it so far; it
       moves to _premium.scss the day a second one does.) */
    @media (max-width: 575.98px) {
        .he-tabs--split > .he-tab { flex: 1 1 0; min-width: 0; }
    }

    /* ══ Attendance (W7.3 — old design scrapped) ══
       Page-local: only this page renders the attendance partial (§0.1 — a class
       goes to _premium.scss when a SECOND page needs it, not before). The
       chips/sheet it reuses (.sal-chip, .custom-overlay-*) are already shared. */

    /* ── Day strip ── */
    .att-strip-wrap {
        display: flex; align-items: center; gap: 0.4rem;
        padding: 0.7rem 0.85rem;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    }
    .att-nav {
        width: 30px; height: 30px; flex-shrink: 0;
        display: inline-flex; align-items: center; justify-content: center;
        border: 1px solid rgba(0, 0, 0, 0.07); border-radius: var(--he-radius-sm);
        background: var(--he-bg-surface); color: var(--he-text-muted);
        font-size: 0.7rem;
        transition: all 0.18s var(--ease-out-expo);
    }
    .att-nav:hover:not(:disabled) { color: var(--he-primary); border-color: var(--he-primary); }
    .att-nav:disabled { opacity: 0.3; cursor: not-allowed; }

    /* Seven equal columns that never crush: the strip owns the row's spare
       width and each day is a min-0 grid cell (§4.10). */
    .att-strip {
        display: grid; grid-template-columns: repeat(7, minmax(0, 1fr));
        gap: 0.2rem; flex: 1 1 auto; min-width: 0;
    }
    .att-day {
        display: flex; flex-direction: column; align-items: center; gap: 0.05rem;
        padding: 0.35rem 0.1rem 0.3rem;
        border: 1px solid transparent; border-radius: var(--he-radius-md);
        background: transparent; cursor: pointer; min-width: 0;
        transition: background 0.18s var(--ease-out-expo), border-color 0.18s var(--ease-out-expo);
    }
    .att-day:hover { background: var(--he-bg-surface-raised); }
    .att-day__dow {
        font-size: 0.56rem; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.03em; color: var(--he-text-muted);
    }
    .att-day__num {
        font-size: 0.92rem; font-weight: 700; line-height: 1.15;
        color: var(--he-text-main); font-variant-numeric: tabular-nums;
    }
    /* The dot is the whole point of the strip: it says which days were missed
       WITHOUT opening them one by one. */
    .att-day__dot { width: 5px; height: 5px; border-radius: 50%; margin-top: 0.2rem; }
    .att-day__dot.is-all { background: var(--he-success); }
    .att-day__dot.is-partial { background: var(--he-warning); }
    .att-day__dot.is-none { background: rgba(0, 0, 0, 0.13); }
    .att-day.is-today .att-day__num { color: var(--he-primary); }
    .att-day.is-selected { background: var(--he-primary-soft); border-color: var(--he-primary); }
    .att-day.is-selected .att-day__num,
    .att-day.is-selected .att-day__dow { color: var(--he-primary); }

    .att-jump__chip { padding: 0.3rem 0.7rem 0.3rem 0.4rem; }
    .att-jump__chip .he-datechip__ic { width: 24px; height: 24px; font-size: 0.65rem; }
    @media (max-width: 767.98px) {
        .att-jump__chip { width: 34px; height: 34px; }
    }

    /* ── Day header ── */
    .att-head {
        display: flex; align-items: flex-start; justify-content: space-between; gap: 0.75rem;
        padding: 0.85rem 1rem;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        flex-wrap: wrap;
    }
    .att-head__date { font-weight: 800; color: var(--he-text-main); }
    .att-head__sum { font-size: 0.8rem; margin-top: 0.3rem; }
    .att-save { font-size: 0.75rem; font-weight: 700; white-space: nowrap; color: var(--he-text-muted); }
    .att-save.is-failed { color: var(--he-danger); }

    /* ── Shortcuts ── */
    .att-tools { padding: 0.75rem 1rem; background: var(--he-bg-canvas); border-bottom: 1px solid rgba(0, 0, 0, 0.05); }
    .att-tools__row { display: flex; flex-wrap: wrap; gap: 0.5rem; }
    .att-tools__done { font-size: 0.8rem; font-weight: 700; color: var(--he-success); }

    /* Desktop shows the full label; the phone drops it (§4.8 — fit or drop,
       never wrap and never half-clip). */
    .att-tool__short { display: none; }
    @media (max-width: 575.98px) {
        /* Two equal halves on ONE row. Content-sized, they were 230px + 120px
           on a 360px screen — so they wrapped into a ragged stack of two
           differently-sized slabs, which is what read as "too big and wide".
           (Padding comes from px-2 px-sm-3 on the buttons: Bootstrap's spacing
           utilities are !important, so a rule here would lose to them.) */
        .att-tools__row > .btn { flex: 1 1 0; min-width: 0; }
        .att-tool__full { display: none; }
        .att-tool__short { display: inline; }
    }

    /* ── Roster rows ── */
    .att-row {
        display: flex; align-items: center; gap: 0.75rem;
        padding: 0.7rem 1rem;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        transition: background 0.18s var(--ease-out-expo);
        flex-wrap: wrap;
    }
    .att-row:last-child { border-bottom: none; }
    .att-row:hover { background: var(--he-bg-surface-raised); }
    /* Unmarked reads as genuinely unanswered — it must never look like a mark. */
    .att-row.is-unmarked { background: rgba(245, 158, 11, 0.04); }
    .att-row__who { display: flex; align-items: center; gap: 0.7rem; flex: 1 1 180px; min-width: 0; }
    .att-row__sub { font-size: 0.72rem; margin-top: 0.1rem; }
    .att-row__act { display: flex; align-items: center; gap: 0.5rem; flex-shrink: 0; }

    .att-tag {
        display: inline-block; padding: 0.05rem 0.45rem;
        border-radius: var(--he-radius-full);
        font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em;
    }
    .att-tag--present { background: var(--he-success-soft); color: var(--he-success); }
    .att-tag--absent { background: var(--he-danger-soft); color: var(--he-danger); }
    .att-tag--half-day { background: var(--he-warning-soft); color: #b45309; }
    .att-tag--leave { background: var(--he-bg-surface-raised); color: var(--he-text-muted); }

    /* The two-way toggle: the ~95% case, at a size a thumb can actually hit. */
    .att-toggle {
        display: flex; gap: 2px; padding: 3px;
        background: var(--he-bg-canvas);
        border: 1px solid rgba(0, 0, 0, 0.06);
        border-radius: var(--he-radius-full);
    }
    .att-seg {
        display: inline-flex; align-items: center; justify-content: center; gap: 0.35rem;
        min-height: 34px; padding: 0 0.85rem;
        border: none; background: transparent; cursor: pointer;
        border-radius: var(--he-radius-full);
        font-size: 0.78rem; font-weight: 700; white-space: nowrap;
        color: var(--he-text-muted);
        transition: background 0.2s var(--ease-out-expo), color 0.2s var(--ease-out-expo);
    }
    .att-seg:active { transform: scale(0.96); }
    .att-seg--present:hover { color: var(--he-success); }
    .att-seg--absent:hover { color: var(--he-danger); }
    .att-seg--present.is-on { background: var(--he-success); color: #fff; box-shadow: 0 2px 8px rgba(16, 185, 129, 0.35); }
    .att-seg--absent.is-on { background: var(--he-danger); color: #fff; box-shadow: 0 2px 8px rgba(239, 68, 68, 0.35); }

    .att-more {
        min-width: 34px; height: 34px; padding: 0 0.5rem; flex-shrink: 0;
        display: inline-flex; align-items: center; justify-content: center;
        border: 1px solid rgba(0, 0, 0, 0.07); border-radius: var(--he-radius-md);
        background: var(--he-bg-surface); color: var(--he-text-muted);
        font-size: 0.75rem; font-weight: 700; white-space: nowrap;
        transition: all 0.18s var(--ease-out-expo);
    }
    .att-more:hover { color: var(--he-primary); border-color: var(--he-primary); }
    /* When it holds a status, it SHOWS it — the row never hides the truth
       behind three dots. */
    .att-more.is-set {
        background: var(--he-warning-soft); color: #b45309; border-color: rgba(245, 158, 11, 0.3);
        text-transform: uppercase; font-size: 0.65rem; letter-spacing: 0.03em;
    }

    /* Phone: the toggle takes the full width rather than shrinking its targets. */
    @container (max-width: 520px) {
        .att-row__act { flex: 1 1 100%; }
        .att-toggle { flex: 1; }
        .att-seg { flex: 1; min-height: 40px; }
    }

    /* ── Status sheet ── */
    .att-sheet { max-width: 420px; }
    .att-opt {
        display: flex; align-items: center; gap: 0.7rem;
        width: 100%; min-height: 48px; padding: 0 1rem;
        border: 1px solid rgba(0, 0, 0, 0.07); border-radius: var(--he-radius-md);
        background: var(--he-bg-surface); color: var(--he-text-main);
        transition: all 0.18s var(--ease-out-expo);
    }
    .att-opt:hover { border-color: var(--he-primary); background: var(--he-primary-soft); }
    .att-opt.is-on { border-color: var(--he-primary); background: var(--he-primary-soft); color: var(--he-primary); }
    .att-opt--present i:first-child { color: var(--he-success); }
    .att-opt--absent i:first-child { color: var(--he-danger); }
    .att-opt--half-day i:first-child { color: var(--he-warning); }
    .att-opt--leave i:first-child { color: var(--he-text-muted); }
    .att-opt--clear { color: var(--he-danger); }
    .att-opt--clear:hover { border-color: var(--he-danger); background: var(--he-danger-soft); }

    @media (max-width: 576px) {
        #staff-list { padding-bottom: 5rem; } /* clear the FAB */
    }
</style>
@endpush

@section('content')
<div class="page-enter" x-data="staffBoard()" @tab-changed.window="switchTab($event.detail, false)">

    <div class="he-page-head mb-4 stagger-1">
        <div>
            <h1 class="he-page-title">{{ __('Staff & Payroll') }}</h1>
            <p class="he-page-sub">{{ __('Your team, their pay, and who showed up.') }}</p>
        </div>
        <button type="button" class="btn btn-premium rounded-pill px-4 fw-semibold shadow-sm tactile-btn d-none d-md-inline-flex align-items-center"
                @click="openAdd()">
            <i class="fa-solid fa-user-plus me-2"></i>{{ __('Add Staff') }}
        </button>
    </div>

    {{-- Whole-book totals — never fragment-swapped: a search must not shrink
         the payroll. "Payroll" is the monthly COMMITMENT (active staff), "Paid"
         is what actually left this month; the gap is what's still owed. --}}
    <div class="he-stats mb-4 stagger-2">
        <div class="he-stats__grid" style="--he-stats-cols: 3;">
            <div class="he-stat he-stat--hero">
                <div class="he-stat__head">
                    <div class="he-stat__icon" style="background: rgba(255, 255, 255, 0.15); color: #93c5fd;"><i class="fa-solid fa-users"></i></div>
                    <div class="he-stat__label">{{ __('Active Staff') }}</div>
                </div>
                <div class="he-stat__value">{{ $summary['active'] }} <span class="opacity-50 fs-5">/ {{ $summary['total'] }}</span></div>
            </div>
            <div class="he-stat">
                <div class="he-stat__head">
                    <div class="he-stat__icon" style="background: var(--he-primary-soft); color: var(--he-primary);"><i class="fa-solid fa-file-invoice-dollar"></i></div>
                    <div class="he-stat__label">{{ __('Monthly Payroll') }}</div>
                </div>
                <div class="he-stat__value">{{ hostelease_money($summary['payroll']) }}</div>
            </div>
            <div class="he-stat">
                <div class="he-stat__head">
                    <div class="he-stat__icon" style="background: var(--he-success-soft); color: var(--he-success);"><i class="fa-solid fa-money-bill-wave"></i></div>
                    <div class="he-stat__label">{{ __('Paid This Month') }}</div>
                </div>
                <div class="he-stat__value text-success">{{ hostelease_money($summary['paid_this_month']) }}</div>
            </div>
        </div>
    </div>

    {{-- Identical markup to Finance and Front Desk — the app's established tab
         pattern: dark active label plus a 3px indicator that FADES in
         (x-transition). Using .he-tab.active instead gave an indigo label and a
         2px border that just appeared, so this page's tabs animated and hovered
         differently from every other tabbed page. --}}
    <div class="he-tabs he-tabs--split mb-4 border-bottom stagger-3">
        <button class="he-tab bg-transparent border-0 py-3 px-2 px-sm-4 fw-medium text-secondary position-relative tactile-btn"
                :class="{ 'text-dark fw-bold': tab === 'directory' }" @click="switchTab('directory')">
            {{-- "& Payroll" is the droppable part (§4.8): the two labels at full
                 length overflow a phone row, and .he-tabs answers overflow with
                 a horizontal scroll — so the second tab sat half off the edge
                 instead of beside the first. --}}
            <i class="fa-solid fa-address-book me-1"></i> {{ __('Directory') }}<span class="d-none d-sm-inline"> &amp; {{ __('Payroll') }}</span>
            <div x-show="tab === 'directory'" class="position-absolute bottom-0 start-0 w-100" style="height: 3px; background: var(--he-primary); border-radius: 3px 3px 0 0;" x-transition></div>
        </button>
        <button class="he-tab bg-transparent border-0 py-3 px-2 px-sm-4 fw-medium text-secondary position-relative tactile-btn"
                :class="{ 'text-dark fw-bold': tab === 'attendance' }" @click="switchTab('attendance')">
            <i class="fa-solid fa-clipboard-user me-1"></i> {{ __('Attendance') }}
            <div x-show="tab === 'attendance'" class="position-absolute bottom-0 start-0 w-100" style="height: 3px; background: var(--he-primary); border-radius: 3px 3px 0 0;" x-transition></div>
        </button>
    </div>

    {{-- ══ Directory ══ --}}
    <div x-show="tab === 'directory'" x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" style="display: none;">

        {{-- ONE row at every width (§4.5), fragment-driven (§4.3). --}}
        <div class="mb-4 st-filter-row">
            <form method="GET" action="{{ route('admin.staff.index') }}" x-ref="filterForm"
                  data-fragment="#staff-list, #st-filter-aux"
                  class="d-flex flex-nowrap gap-2 align-items-center">
                <input type="hidden" name="tab" value="directory">
                <div class="he-search he-search--inline he-search--clearable">
                    <span class="he-search__icon"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" name="search" x-model="searchTerm" class="he-search__input"
                           placeholder="{{ __('Search by name, role, or mobile...') }}"
                           @input.debounce.450ms="$el.form.requestSubmit()">
                    <button type="button" class="he-search__clear" x-show="searchTerm" x-cloak
                            @click="clearSearch()" title="{{ __('Clear search') }}">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <span id="st-filter-aux">
                    <x-he-select name="status" icon="filter" icon-only-mobile :selected="$status ?? ''"
                        :options="[
                            '' => ['label' => __('All Staff'), 'icon' => 'filter'],
                            'active' => ['label' => __('Active'), 'icon' => 'circle-check'],
                            'inactive' => ['label' => __('Inactive'), 'icon' => 'circle-pause'],
                            'removed' => ['label' => __('Removed'), 'icon' => 'user-slash'],
                        ]" />
                </span>
            </form>
        </div>

        <div id="staff-list" data-fragment-container class="he-adaptive">
            @include('admin.staff._list')
        </div>
    </div>

    {{-- ══ Attendance ══ --}}
    <div x-show="tab === 'attendance'" x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" style="display: none;">
        @include('admin.staff._attendance')
    </div>

    <template x-teleport="body">
        <button type="button" class="fab" @click="openAdd()" title="{{ __('Add Staff') }}">
            <i class="fa-solid fa-user-plus"></i>
        </button>
    </template>

    {{-- ══ Add Staff ══ --}}
    <template x-teleport="body">
        <div class="custom-overlay-backdrop" x-show="addOpen" x-transition.opacity @click="close()" x-cloak style="display: none;">
            <form method="POST" action="{{ route('admin.staff.store') }}" enctype="multipart/form-data" data-ring-required
                  class="custom-overlay-modal" :class="{ 'is-open': addOpen }" x-show="addOpen" x-transition.opacity @click.stop style="display: none;">
                @csrf
                <div class="custom-overlay-header">
                    <h5 class="fw-bold mb-0"><i class="fa-solid fa-user-plus" style="color: var(--he-primary);"></i><span class="ms-1">{{ __('Add Staff') }}</span></h5>
                    <button type="button" class="btn-close" @click="close()"></button>
                </div>
                <div class="custom-overlay-body">
                    <div class="mb-4">
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Name') }} <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control bg-light" required maxlength="150" placeholder="{{ __('e.g. Govind Sharma') }}">
                    </div>
                    <div class="row gx-3">
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Designation') }}</label>
                            <input type="text" name="designation" class="form-control bg-light" maxlength="100" placeholder="{{ __('Cook, Guard…') }}">
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Mobile') }} <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted fw-bold">+91</span>
                                <input type="tel" name="mobile" class="form-control bg-light" required inputmode="numeric" maxlength="10" pattern="\d{10}" placeholder="{{ __('10-digit number') }}">
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Monthly Salary') }} <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted fw-bold">₹</span>
                                <input type="number" name="monthly_salary" class="form-control bg-light fw-bold" required min="0" step="0.01" placeholder="0.00">
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Join Date') }}</label>
                            <input type="date" name="join_date" class="form-control bg-light" max="{{ now()->toDateString() }}">
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Aadhaar Number') }} <span class="text-danger">*</span></label>
                            <input type="text" name="aadhaar_number" class="form-control bg-light" required inputmode="numeric" maxlength="12" pattern="\d{12}" placeholder="{{ __('12-digit number') }}">
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Aadhaar Card') }} <span class="text-danger">*</span></label>
                            <input type="file" name="aadhaar_file" class="form-control bg-light" required accept="image/*">
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Photo') }}</label>
                            <input type="file" name="photo" class="form-control bg-light" accept="image/*">
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Address') }}</label>
                            <input type="text" name="address" class="form-control bg-light" maxlength="255" placeholder="{{ __('Residential address') }}">
                        </div>
                    </div>
                    <label class="d-flex align-items-center justify-content-between gap-3 bg-light rounded-4 p-3 m-0">
                        <span>
                            <span class="fw-bold d-block">{{ __('Currently working') }}</span>
                            <span class="small text-muted">{{ __('Inactive staff stay on file but leave the attendance roster.') }}</span>
                        </span>
                        <span class="form-check form-switch fs-4 m-0">
                            <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" checked>
                        </span>
                    </label>
                </div>
                <div class="custom-overlay-footer bg-light">
                    <button type="button" class="btn btn-white border fw-semibold rounded-pill px-4 tactile-btn" @click="close()">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-premium fw-semibold rounded-pill px-4 shadow-sm tactile-btn"><i class="fa-solid fa-check me-2"></i>{{ __('Add Staff') }}</button>
                </div>
            </form>
        </div>
    </template>

    {{-- ══ Pay Salary ══ --}}
    @include('admin.staff._pay_sheet', ['paymentModes' => $paymentModes])

</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('staffBoard', () => ({
        tab: @js(request('tab', 'directory')),
        searchTerm: @json($search ?? ''),

        addOpen: false,

        switchTab(newTab, updateUrl = true) {
            if (newTab === this.tab) return;
            this.tab = '';
            setTimeout(() => {
                this.tab = newTab;
                if (updateUrl) {
                    const url = new URL(window.location);
                    url.searchParams.set('tab', newTab);
                    window.history.replaceState({}, '', url);
                    // Keeps the sidebar's Directory/Attendance sublink in sync.
                    window.dispatchEvent(new CustomEvent('sync-sidebar-tab', { detail: newTab }));
                }
            }, 300);
        },

        clearSearch() {
            this.searchTerm = '';
            this.$nextTick(() => this.$refs.filterForm?.requestSubmit());
        },

        openAdd() {
            this.addOpen = true;
            document.body.style.overflow = 'hidden';
        },

        // The Pay Salary sheet owns itself — rows just $dispatch('pay-salary').
        close() {
            this.addOpen = false;
            document.body.style.overflow = '';
        },
    }));
});
</script>
@endpush
@endsection
