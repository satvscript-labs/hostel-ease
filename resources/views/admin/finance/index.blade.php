@extends('layouts.app')

@section('title', __('Finance Board'))

@section('content')
<style>
    /* Page-local layout only — the shell (hero mesh + tiles) is kept as the
       owner approved it; W6.1 adds the standard search/filter, the fragment
       containers, the surfaced flows' sheets, and a bespoke phone layout. */

    .fin-page-title { font-size: 1.6rem; letter-spacing: -0.01em; }

    /* Dropdown-over-list rule (design law §4.2): the filter row owns a
       dropdown and sits above both lists — lift the whole row. */
    .fin-filter-row { position: relative; z-index: 30; }

    /* Transparent fragment-swap boundary (§4.3): the status select re-renders
       from server truth on every filter change. The search input is outside it
       on purpose — swapping it would drop typing focus mid-keystroke. */
    #fin-filter-aux { display: contents; }

    /* ── Desktop list row ──────────────────────────────────────────────────
       A real grid, not Bootstrap columns. The old row sized every money block
       from its own content, so ₹1,000.00 and ₹27,000.00 started at different
       x-positions on adjacent rows and the eye had nothing to follow — that's
       what read as "cluttered". Fixed tracks + tabular numerals mean the three
       figures line up down the whole list, and the actions get one settled
       column instead of elbowing the numbers. */
    .fin-row {
        display: grid;
        grid-template-columns:
            minmax(0, 1.6fr)                /* student */
            minmax(0, 1.3fr)                /* title */
            repeat(3, minmax(96px, 116px))  /* amount · paid · balance */
            auto;                           /* status + actions */
        align-items: center;
        column-gap: 1.25rem;
    }
    .fin-row-num {
        text-align: right;
        font-feature-settings: 'tnum';
        font-variant-numeric: tabular-nums;
    }
    .fin-row-lbl {
        font-size: 0.62rem; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.06em; color: var(--he-text-muted);
        margin-bottom: 0.15rem;
    }
    /* One quiet container for the actions — separated from the numbers by a
       hairline so the row reads "data | what you can do about it".
       (The buttons themselves are the canonical .he-icon-btn / .he-act-row
       family — promoted to _premium.scss in W6.2 when Expenses needed them.) */
    .fin-row-acts {
        display: flex; align-items: center; justify-content: flex-end;
        gap: 0.5rem;
        padding-left: 1.25rem;
        border-left: 1px solid rgba(0, 0, 0, 0.06);
    }
    @media (max-width: 1299.98px) {
        .fin-row { column-gap: 0.85rem; }
        .fin-row-acts { padding-left: 0.85rem; }
    }

    /* Phone: Collect left (capped — a full-bleed green bar is a shouty way
       to say "one of three things you can do"), edit/delete right. */
    .fin-collect {
        flex: 1 1 auto;
        max-width: 220px;
        min-height: 44px;
        display: inline-flex; align-items: center; justify-content: center;
        gap: 0.4rem;
        /* Both required for the fit-or-drop measurement (§4.8) to mean
           anything: scrollWidth only exceeds clientWidth while the content is
           forbidden to wrap and allowed to overflow. */
        white-space: nowrap;
        overflow: hidden;
    }
    /* Set by initFitLabels() when the full label can't fit on one line: the
       amount goes, "Collect" stays. Never wraps, never half-clips. */
    .fin-collect.is-fit-short .fin-collect-amt { display: none; }

    /* Phone money block: one value per row (mobile rule 3). */
    .fin-money-list {
        background: var(--he-bg-canvas);
        border-radius: var(--he-radius-md);
        padding: 0.35rem 0.85rem;
        font-feature-settings: 'tnum';
    }
    .fin-money-row {
        display: flex; align-items: center; justify-content: space-between;
        padding: 0.35rem 0;
    }
    .fin-money-row + .fin-money-row { border-top: 1px solid rgba(0, 0, 0, 0.05); }
    .fin-money-lbl {
        font-size: 0.68rem; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.5px; color: var(--he-text-muted);
    }

    /* ── New Invoice: multi-select pills + live summary (W6.1 redesign) ── */

    /* Selected students as soft rounded-rectangle pills (the .he-search
       radius language, not full circles). Desktop-only — on phones the
       summary rows carry the remove affordance instead, so the same people
       aren't listed twice on one small screen. */
    .fin-sel-pills { display: flex; flex-wrap: wrap; gap: 0.45rem; margin-top: 0.65rem; }
    .fin-sel-pill {
        display: inline-flex; align-items: center; gap: 0.45rem;
        padding: 0.3rem 0.4rem 0.3rem 0.3rem;
        background: var(--he-bg-surface-raised);
        border: 1px solid rgba(0, 0, 0, 0.06);
        border-radius: 12px;
        font-size: 0.83rem; font-weight: 600; color: var(--he-text-main);
    }
    .fin-sel-pill .fin-pill-avatar {
        width: 24px; height: 24px; border-radius: 8px; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        background: var(--he-primary-soft); color: var(--he-primary);
        font-size: 0.7rem; font-weight: 700;
    }
    .fin-sel-pill .fin-pill-x {
        width: 20px; height: 20px; border: none; border-radius: 7px;
        display: flex; align-items: center; justify-content: center;
        background: transparent; color: var(--he-text-muted);
        font-size: 0.7rem; cursor: pointer;
        transition: background 0.15s var(--ease-out-expo), color 0.15s var(--ease-out-expo);
    }
    .fin-sel-pill .fin-pill-x:hover { background: var(--he-danger-soft); color: var(--he-danger); }
    @media (max-width: 991.98px) { .fin-sel-pills { display: none; } }

    /* Room number beside a student's name — same-name students are common,
       shared beds are not. Deliberately quiet: it disambiguates, it doesn't
       compete with the name. */
    .fin-room-tag {
        font-weight: 400;
        font-size: 0.82em;
        color: var(--he-text-muted);
        margin-left: 0.3rem;
        white-space: nowrap;
    }

    /* Divider before Monthly: Semester/Yearly are the owner-driven runs this
       form exists for; Monthly is the nightly generator's job and rarely
       belongs here, so it sits apart rather than leading. */
    .chip-sep {
        width: 1px; align-self: stretch; min-height: 22px;
        background: rgba(0, 0, 0, 0.12);
        margin: 0 0.15rem;
    }

    /* The attention ring itself is canonical now (.he-ring in _premium.scss,
       driven by window.heRing) — this page only marks the locked trigger. */
    .he-picker-trigger.is-locked { color: var(--he-text-muted); }

    /* Live summary: one row per selected student, resolved amount right,
       last-invoiced warning inline, total footer. */
    .fin-summary {
        border: 1px solid rgba(0, 0, 0, 0.07);
        border-radius: var(--he-radius-md);
        overflow: hidden;
    }
    .fin-summary-row {
        display: flex; align-items: center; gap: 0.65rem;
        padding: 0.6rem 0.85rem;
        font-feature-settings: 'tnum';
    }
    .fin-summary-row + .fin-summary-row { border-top: 1px solid rgba(0, 0, 0, 0.05); }
    .fin-summary-row .fin-pill-avatar {
        width: 30px; height: 30px; border-radius: 10px; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        background: var(--he-primary-soft); color: var(--he-primary);
        font-size: 0.78rem; font-weight: 700;
    }
    .fin-summary-warn {
        display: inline-flex; align-items: center; gap: 0.3rem;
        font-size: 0.72rem; font-weight: 600;
        color: var(--he-warning); margin-top: 1px;
    }
    .fin-summary-row.is-unresolvable { background: var(--he-danger-soft); }
    .fin-summary-total {
        display: flex; align-items: center; justify-content: space-between;
        padding: 0.7rem 0.85rem;
        background: var(--he-bg-canvas);
        border-top: 1px solid rgba(0, 0, 0, 0.07);
        font-weight: 800; font-feature-settings: 'tnum';
    }
    .fin-summary-x {
        width: 26px; height: 26px; border: none; border-radius: 8px; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        background: transparent; color: var(--he-text-muted); font-size: 0.72rem; cursor: pointer;
    }
    .fin-summary-x:hover { background: var(--he-danger-soft); color: var(--he-danger); }

    /* The filter row stays ONE row at every width — the search flexes, the
       status filter collapses to its icon below md (icon-only-mobile). Nothing
       here stretches the select any more: it must stay square on a phone. */

    @media (max-width: 576px) {
        .fin-page-title { font-size: 2.2rem; line-height: 1.5; }
        .fin-page-sub { font-size: 1rem; line-height: 1.5; }
        .fin-list { padding-bottom: 5rem; } /* clear the FAB */

        /* Stat tiles: density pass (mobile rule 4) — the desktop shell is
           roomy; on phones each tile compacts into one [icon+label | value]
           row so all three fit one screen with the tabs. */
        .fin-tile .card-body {
            display: flex; align-items: center; justify-content: space-between;
            gap: 0.75rem; padding: 0.85rem 1rem;
        }
        .fin-tile .card-body > div:first-child {
            display: flex; flex-direction: row-reverse; align-items: center;
            justify-content: flex-end; gap: 0.6rem; margin-bottom: 0 !important;
        }
        .fin-tile .tile-icon-wrapper { width: 34px !important; height: 34px !important; font-size: 0.8rem; }
        .fin-tile .h2 { font-size: 1.3rem; margin-bottom: 0 !important; }
        .fin-tile-hero .card-body { padding: 1rem 1.1rem; }
        .fin-tile-hero .display-6 { font-size: 1.6rem; }
    }
</style>

<div x-data="financeBoard()" @tab-changed.window="switchTab($event.detail, false)" class="page-enter">

    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-3">
        <div>
            <h1 class="fin-page-title fw-bold mb-1">{{ __('Finance Board') }}</h1>
            <p class="fin-page-sub text-secondary mb-0">{{ __('Manage invoices, due balances, and transactions in one place.') }}</p>
        </div>
        {{-- Desktop action; phones get the FAB. --}}
        <div class="d-none d-sm-flex gap-2">
            <button type="button" class="btn btn-premium rounded-pill px-4 fw-bold shadow-sm" @click="openModal()">
                <i class="fa-solid fa-plus me-1"></i> {{ __('New Invoice') }}
            </button>
        </div>
    </div>

    {{-- Stat tiles — whole-book totals (server-computed, never affected by
         search/pagination). Shell design kept per owner sign-off. --}}
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card h-100 border-0 fin-tile fin-tile-hero" style="background: var(--he-gradient-mesh); color: #fff; overflow: hidden; position: relative; border-radius: 1.25rem;">
                <div style="position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: radial-gradient(circle at 50% 50%, rgba(147, 51, 234, 0.3) 0%, transparent 50%); opacity: 0.5;"></div>
                <div class="card-body p-4 position-relative z-1 d-flex flex-column justify-content-between">
                    <div>
                        <div class="badge bg-white text-dark mb-3" style="background: rgba(255,255,255,0.1) !important; backdrop-filter: blur(4px); color: #fff !important; border: 1px solid rgba(255,255,255,0.2);">
                            <i class="fa-solid fa-triangle-exclamation text-warning me-1"></i> {{ __('Total Outstanding') }}
                        </div>
                        <h2 class="display-6 fw-bold mb-0 text-white" style="font-feature-settings: 'tnum';">{{ hostelease_money($totals['outstanding']) }}</h2>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm fin-tile" style="border-radius: 1.25rem; background: #fff;">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="text-secondary small fw-bold text-uppercase" style="letter-spacing: 1px;">{{ __('Total Collected') }}</div>
                        <div class="tile-icon-wrapper" style="width: 40px; height: 40px; border-radius: 50%; background: var(--he-success-soft); color: var(--he-success); display: flex; align-items: center; justify-content: center; position: relative;">
                            <i class="fa-solid fa-sack-dollar"></i>
                            <div style="position: absolute; inset: 0; background: inherit; filter: blur(8px); z-index: -1;"></div>
                        </div>
                    </div>
                    <div class="h2 mb-0 fw-bold text-success" style="font-feature-settings: 'tnum';">{{ hostelease_money($totals['collected']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm fin-tile" style="border-radius: 1.25rem; background: #fff;">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="text-secondary small fw-bold text-uppercase" style="letter-spacing: 1px;">{{ __('Total Invoiced') }}</div>
                        <div class="tile-icon-wrapper" style="width: 40px; height: 40px; border-radius: 50%; background: var(--he-info-soft); color: var(--he-info); display: flex; align-items: center; justify-content: center; position: relative;">
                            <i class="fa-solid fa-file-invoice-dollar"></i>
                            <div style="position: absolute; inset: 0; background: inherit; filter: blur(8px); z-index: -1;"></div>
                        </div>
                    </div>
                    <div class="h2 mb-0 fw-bold text-dark" style="font-feature-settings: 'tnum';">{{ hostelease_money($totals['invoiced']) }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="he-tabs mb-4 border-bottom">
        <button class="he-tab bg-transparent border-0 py-3 px-4 fw-medium text-secondary position-relative tactile-btn"
                :class="{ 'text-dark fw-bold': tab === 'invoices' }" @click="switchTab('invoices')">
            <i class="fa-solid fa-file-invoice me-1"></i> {{ __('Invoices & Dues') }}
            <div x-show="tab === 'invoices'" class="position-absolute bottom-0 start-0 w-100" style="height: 3px; background: var(--he-primary); border-radius: 3px 3px 0 0;" x-transition></div>
        </button>
        <button class="he-tab bg-transparent border-0 py-3 px-4 fw-medium text-secondary position-relative tactile-btn"
                :class="{ 'text-dark fw-bold': tab === 'transactions' }" @click="switchTab('transactions')">
            <i class="fa-solid fa-money-bill-transfer me-1"></i> {{ __('Transactions') }}
            <div x-show="tab === 'transactions'" class="position-absolute bottom-0 start-0 w-100" style="height: 3px; background: var(--he-primary); border-radius: 3px 3px 0 0;" x-transition></div>
        </button>
    </div>

    {{-- Filter bar — the canonical .he-search + <x-he-select> standard,
         driving BOTH lists server-side via data-fragment (§4.3).

         ONE row at every width, never wrapping: .he-search--inline sizes the
         search from `flex` (its base `width: 100%` claimed the whole line and
         pushed the status filter onto a second row), and below md the filter
         collapses to an icon-only square via icon-only-mobile — each option
         carries its own icon, so the icon IS the readout and no text is
         needed on a phone.

         The clear X lives INSIDE the search field and clears the search only
         — status clears via its own "All Statuses" option. It's Alpine-driven
         off searchTerm rather than server-rendered, because .he-search sits
         outside the swapped fragment (deliberately: swapping the input would
         kill typing focus mid-keystroke). --}}
    <div class="mb-4 fin-filter-row">
        <form method="GET" action="{{ route('admin.finance.index') }}" x-ref="filterForm"
              data-fragment="#invoice-list, #transaction-list, #fin-filter-aux"
              class="d-flex flex-nowrap gap-2 align-items-center">
            <input type="hidden" name="tab" value="{{ request('tab', 'invoices') }}" :value="tab">
            <div class="he-search he-search--inline he-search--clearable">
                <span class="he-search__icon"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="text" name="search" x-model="searchTerm" class="he-search__input"
                       placeholder="{{ __('Search by student, receipt, or title...') }}"
                       @input.debounce.450ms="$el.form.requestSubmit()">
                <button type="button" class="he-search__clear" x-show="searchTerm" x-cloak
                        @click="clearSearch()" title="{{ __('Clear search') }}">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <span id="fin-filter-aux">
                <span x-show="tab === 'invoices'" x-cloak>
                    <x-he-select name="status" icon="filter" icon-only-mobile :selected="$status ?? ''"
                        :options="[
                            '' => ['label' => __('All Statuses'), 'icon' => 'filter'],
                            'paid' => ['label' => __('Paid'), 'icon' => 'circle-check'],
                            'partial' => ['label' => __('Partial'), 'icon' => 'circle-half-stroke'],
                            'pending' => ['label' => __('Pending'), 'icon' => 'clock'],
                        ]" />
                </span>
            </span>
        </form>
    </div>

    {{-- Invoices tab --}}
    <div x-show="tab === 'invoices'"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-4"
         x-transition:enter-end="opacity-100 translate-y-0"
         style="display: none;" class="fin-list">
        <div id="invoice-list" data-fragment-container>
            @include('admin.finance._invoices')
        </div>
    </div>

    {{-- Transactions tab --}}
    <div x-show="tab === 'transactions'"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-4"
         x-transition:enter-end="opacity-100 translate-y-0"
         style="display: none;" class="fin-list">
        <div id="transaction-list" data-fragment-container>
            @include('admin.finance._transactions')
        </div>
    </div>

    {{-- Mobile FAB — New Invoice, always thumb-reachable (rule 5). Teleported
         out of .page-enter (rule 10); scope survives teleport. --}}
    <template x-teleport="body">
        <button type="button" class="fab" @click="openModal()" title="{{ __('New Invoice') }}">
            <i class="fa-solid fa-plus"></i>
        </button>
    </template>

    {{-- ═══════════════ New Invoice (W6.1 full redesign, owner-approved) ═══
         Two charge types: Hostel Fee (fee types = config fee_frequencies —
         monthly/semester/yearly; "custom" dropped, no student can have that
         frequency) and Other/Fine. Both are MULTI-select: fees generate one
         invoice per student at their own resolved amount (fee plan → room
         rent × multiplier); fines split ONE total equally. A live summary
         shows every selected student, their amount, and a last-invoiced
         warning (the owner's memory aid now that semester/yearly billing is
         owner-driven — the nightly generator is monthly-only). --}}
    <template x-teleport="body">
        <div class="custom-overlay-backdrop" x-show="chargeModalOpen" x-transition.opacity @click="closeCharge()" x-cloak style="display: none;">

            {{-- data-ring-required: empty mandatory fields ring red instead of
                 firing the browser's one-at-a-time validation bubble (§4.4). --}}
            <form method="POST" :action="formAction" data-ring-required class="custom-overlay-modal" :class="{ 'is-open': chargeModalOpen }" x-show="chargeModalOpen" x-transition.opacity @click.stop @submit="onSubmit" style="display: none;">
                @csrf
                <input type="hidden" name="fee_type" :value="feeType" :disabled="invoiceType !== 'fee'">
                {{-- Only billable students post — a red "no plan/room" row in
                     the summary is excluded here, matching what the user sees. --}}
                <template x-for="id in submitIds" :key="'sid-' + id">
                    <input type="hidden" name="student_ids[]" :value="id">
                </template>

                <div class="custom-overlay-header">
                    <h5 class="fw-bold mb-0">
                        <i class="fa-solid fa-file-invoice-dollar" style="color: var(--he-primary);"></i>
                        <span class="ms-1">{{ __('New Invoice') }}</span>
                    </h5>
                    <button type="button" class="btn-close" @click="closeCharge()"></button>
                </div>

                <div class="custom-overlay-body">
                    {{-- Charge Type --}}
                    <div class="mb-4">
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1 d-block mb-2">{{ __('Charge Type') }} <span class="text-danger">*</span></label>
                        <div class="chip-group">
                            <button type="button" class="chip" :class="{ active: invoiceType === 'fee' }" @click="setChargeType('fee')">{{ __('Hostel Fee') }}</button>
                            <button type="button" class="chip" :class="{ active: invoiceType === 'other' }" @click="setChargeType('other')">{{ __('Other / Fine') }} <span class="opacity-75">· {{ __('split') }}</span></button>
                        </div>
                        <div class="form-text small mt-2" x-show="invoiceType === 'other'" x-cloak>
                            {{ __('The total is split equally across everyone selected. AC bills are generated room-wise on the AC Bills page.') }}
                        </div>
                    </div>

                    {{-- Fee Type — mirrors fee_frequencies; drives the picker filter
                         and the rent multiplier. Ordered by real-world use: the
                         owner-driven Semester/Yearly runs lead, Monthly sits past
                         a divider because the nightly generator already covers it
                         (manual monthly is the rare plan-less/off-cycle case).
                         These chips are the DEPENDENCY case of the attention ring
                         (§4.4): reaching for students before choosing a type rings
                         them, because the picker cannot filter or price anything
                         until it knows the type. --}}
                    <div class="mb-4" x-show="invoiceType === 'fee'" x-cloak>
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1 d-block mb-2">{{ __('Fee Type') }} <span class="text-danger">*</span></label>
                        <div class="chip-group" x-ref="feeChips">
                            <button type="button" class="chip" :class="{ active: feeType === 'semester' }"
                                    @click="setFeeType('semester')">{{ __('Semester') }}</button>
                            <button type="button" class="chip" :class="{ active: feeType === 'yearly' }"
                                    @click="setFeeType('yearly')">{{ __('Yearly') }}</button>
                            <span class="chip-sep" aria-hidden="true"></span>
                            <button type="button" class="chip" :class="{ active: feeType === 'monthly' }"
                                    @click="setFeeType('monthly')">{{ __('Monthly') }}</button>
                        </div>
                        <div class="form-text small mt-2" x-show="feeType === 'monthly'" x-cloak>
                            {{ __('Monthly rent is generated automatically every night for students with a monthly fee plan — charge manually only for plan-less or off-cycle cases.') }}
                        </div>
                    </div>

                    {{-- Multi-select student picker --}}
                    <div class="mb-4">
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1 d-flex align-items-center gap-2 mb-2">
                            <span>{{ __('Students') }} <span class="text-danger">*</span></span>
                            <span class="badge rounded-pill" style="background: var(--he-primary-soft); color: var(--he-primary);"
                                  x-show="invoiceType === 'fee' && feeType" x-cloak x-text="feeTypeLabel + ' {{ __('students only') }}'"></span>
                        </label>

                        <div class="he-picker" :class="{ 'is-open': pickerOpen }" @click.outside.capture="pickerOpen = false">
                            {{-- NOT disabled when the fee type is missing: a disabled
                                 button fires no click, and the click is what rings
                                 the Fee Type chips. It reads locked, acts as a
                                 pointer to what's missing. --}}
                            <button type="button" class="he-picker-trigger" x-ref="pickerTrigger" :class="{ 'is-locked': feeLocked }" @click="togglePicker()">
                                <span class="d-flex align-items-center gap-2 text-truncate">
                                    <span class="he-picker-avatar" style="width: 28px; height: 28px; font-size: 0.8rem;" x-show="selected.length > 0" x-text="selected.length"></span>
                                    <span :class="selected.length ? 'fw-semibold text-dark' : 'text-muted'"
                                          x-text="pickerLabel"></span>
                                </span>
                                <i class="fa-solid fa-chevron-down chevron"></i>
                            </button>

                            <div class="he-picker-panel" x-ref="pickerPanel" x-show="pickerOpen" x-transition.opacity x-cloak style="display: none;">
                                <div class="he-picker-search">
                                    <input type="text" x-model="studentSearch" x-ref="studentSearch"
                                           class="form-control form-control-sm bg-light border-0" placeholder="{{ __('Search name or mobile…') }}">
                                </div>
                                <div class="he-picker-list">
                                    {{-- Rows TOGGLE selection and the panel stays
                                         open — that's the multi-select gesture. --}}
                                    <template x-for="s in eligibleStudents" :key="s.id">
                                        <button type="button" class="he-picker-option" @click="toggleStudent(s)">
                                            <span class="he-picker-avatar" x-text="s.name.charAt(0).toUpperCase()"></span>
                                            {{-- Room number rides along everywhere a student
                                                 is named (row, pill, summary) — two students
                                                 can share a name; they can't share a bed. --}}
                                            <span class="flex-grow-1" style="min-width: 0;">
                                                <span class="d-block text-truncate">
                                                    <span class="fw-bold text-dark" x-text="s.name"></span>
                                                    <span class="fin-room-tag" x-show="s.room" x-text="'({{ __('Room') }} ' + s.room + ')'"></span>
                                                </span>
                                                <span class="d-block small text-muted text-truncate" x-text="s.mobile"></span>
                                            </span>
                                            <span class="badge rounded-pill flex-shrink-0" x-show="invoiceType === 'fee'"
                                                  :class="s.fee_frequency ? 'text-bg-light border' : 'bg-secondary-subtle text-secondary'"
                                                  x-text="s.fee_frequency || '{{ __('no plan') }}'"></span>
                                            <i class="fa-solid fa-circle-check text-primary flex-shrink-0" x-show="isSelected(s.id)"></i>
                                        </button>
                                    </template>
                                    <div class="he-picker-empty" x-show="eligibleStudents.length === 0">
                                        <span x-show="invoiceType === 'fee' && !feeType">{{ __('Select a fee type first') }}</span>
                                        <span x-show="!(invoiceType === 'fee' && !feeType)">{{ __('No students match') }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Selected pills (desktop; phones use the summary rows) --}}
                        <div class="fin-sel-pills" x-show="selected.length > 0">
                            <template x-for="s in selected" :key="'pill-' + s.id">
                                {{-- No room tag here: the pills are a compact
                                     "who's selected" strip, and the summary
                                     right below already names the room. --}}
                                <span class="fin-sel-pill">
                                    <span class="fin-pill-avatar" x-text="s.name.charAt(0).toUpperCase()"></span>
                                    <span class="text-truncate" style="max-width: 140px;" x-text="s.name"></span>
                                    <button type="button" class="fin-pill-x" @click="removeStudent(s.id)" :title="'{{ __('Remove') }} ' + s.name">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </span>
                            </template>
                        </div>
                    </div>

                    {{-- Fine: ONE total, split equally. template x-if so the input
                         leaves the DOM on the fee flow and never posts there. --}}
                    <template x-if="invoiceType === 'other'">
                        <div class="mb-4">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Total Amount') }} <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted fw-bold">₹</span>
                                <input type="number" name="amount" x-model.number="fineTotal"
                                       class="form-control bg-light fw-bold text-dark"
                                       required min="1" step="0.01" placeholder="0.00">
                            </div>
                            <div class="form-text small" x-show="selected.length > 1" x-cloak>
                                {{ __('Split equally') }} — <span x-text="'₹' + fmt((Number(fineTotal) || 0) / Math.max(selected.length, 1)) + ' {{ __('each (approx.)') }}'"></span>
                            </div>
                        </div>
                    </template>

                    {{-- Live summary: who gets charged what, before anything posts. --}}
                    <div class="mb-4" x-show="selected.length > 0" x-cloak>
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Summary') }}</label>
                        <div class="fin-summary">
                            <template x-for="row in summaryRows" :key="'sum-' + row.s.id">
                                <div class="fin-summary-row" :class="{ 'is-unresolvable': row.unresolvable }">
                                    <span class="fin-pill-avatar" x-text="row.s.name.charAt(0).toUpperCase()"></span>
                                    <span class="flex-grow-1" style="min-width: 0;">
                                        <span class="d-block text-truncate">
                                            <span class="fw-semibold text-dark" x-text="row.s.name"></span>
                                            <span class="fin-room-tag" x-show="row.s.room" x-text="'({{ __('Room') }} ' + row.s.room + ')'"></span>
                                        </span>
                                        {{-- States a recorded fact only. No projected
                                             end date — the system doesn't know one. --}}
                                        <span class="fin-summary-warn" x-show="row.recentlyInvoiced" :title="row.s.last_title || ''">
                                            <i class="fa-solid fa-triangle-exclamation"></i>
                                            <span x-text="'{{ __('already invoiced · last invoiced on') }} ' + (row.s.last_invoiced_label || '')"></span>
                                        </span>
                                        <span class="fin-summary-warn" style="color: var(--he-danger);" x-show="row.unresolvable">
                                            <i class="fa-solid fa-circle-exclamation"></i>
                                            {{ __('No fee plan or room rent — excluded') }}
                                        </span>
                                    </span>
                                    <span class="fw-bold" :class="row.unresolvable ? 'text-muted' : 'text-dark'"
                                          x-text="row.unresolvable ? '—' : '₹' + fmt(row.amount)"></span>
                                    <button type="button" class="fin-summary-x" @click="removeStudent(row.s.id)" title="{{ __('Remove') }}">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </div>
                            </template>
                            <div class="fin-summary-total">
                                <span class="text-muted small text-uppercase" style="letter-spacing: 0.5px;" x-text="countLabel"></span>
                                <span x-text="'₹' + fmt(totalPreview)"></span>
                            </div>
                        </div>
                    </div>

                    {{-- Title + Due Date (shared by every generated invoice) --}}
                    <div class="row gx-3">
                        <div class="col-md-7 mb-3">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Title / Description') }} <span class="text-danger">*</span></label>
                            <input type="text" name="title" x-model="title" class="form-control bg-light" required
                                   :placeholder="invoiceType === 'fee' ? '{{ __('e.g. Fall Semester Fee') }}' : '{{ __('e.g. Broken window — Room 102') }}'">
                        </div>
                        <div class="col-md-5 mb-3">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Due Date') }}</label>
                            <input type="date" name="due_date" class="form-control bg-light">
                        </div>
                    </div>
                </div>

                <div class="custom-overlay-footer bg-light">
                    <button type="button" class="btn btn-white border fw-semibold rounded-pill px-4 tactile-btn" @click="closeCharge()">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-premium fw-semibold rounded-pill px-4 shadow-sm tactile-btn" :disabled="submitDisabled">
                        <i class="fa-solid fa-check me-2"></i>
                        <span x-text="submitLabel"></span>
                    </button>
                </div>
            </form>
        </div>
    </template>

    {{-- ═══════════════ Collect Payment (surfaced W6.1) ═══════════════
         The hub can finally take money. Posts to the same students.collect
         flow the profile uses (PaymentService: credit split, FIFO allocation
         to oldest dues, overpay becomes credit). --}}
    <template x-teleport="body">
        <div class="custom-overlay-backdrop" x-show="collectOpen" x-transition.opacity @click="closeCollect()" x-cloak style="display: none;">
            <form method="POST" :action="c.action" data-ring-required class="custom-overlay-modal" :class="{ 'is-open': collectOpen }" x-show="collectOpen" x-transition.opacity @click.stop
                  @he-select-change="if ($event.detail.name === 'mode') payMode = $event.detail.value" style="display: none;">
                @csrf
                <div class="custom-overlay-header">
                    <h5 class="fw-bold mb-0">
                        <i class="fa-solid fa-indian-rupee-sign text-success me-1"></i>
                        {{ __('Collect Payment') }}
                        <div class="fs-6 fw-normal text-muted mt-1" x-text="c.student"></div>
                    </h5>
                    <button type="button" class="btn-close" @click="closeCollect()"></button>
                </div>

                <div class="custom-overlay-body">
                    <div class="bg-light rounded-4 p-3 mb-4 d-flex justify-content-between align-items-center border border-success-subtle border-opacity-25">
                        <div>
                            <div class="text-muted small fw-bold text-uppercase letter-spacing-1 mb-1" x-text="c.invoice"></div>
                            <div class="fs-4 fw-bold text-dark" x-text="'₹' + Number(c.balance).toLocaleString('en-IN', { minimumFractionDigits: 2 })"></div>
                        </div>
                        <div class="text-end" x-show="c.credit > 0" x-cloak>
                            <div class="text-muted small fw-bold text-uppercase letter-spacing-1 mb-1">{{ __('Credit') }}</div>
                            <div class="fs-5 fw-bold text-success" x-text="'₹' + Number(c.credit).toLocaleString('en-IN', { minimumFractionDigits: 2 })"></div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold text-dark mb-2">{{ __('Total Amount to Pay (₹)') }}</label>
                        <input type="number" step="0.01" min="0.01" class="form-control form-control-lg bg-light fw-bold"
                               x-model.number="totalPayment" required>
                        <div class="form-text text-muted small mt-2">
                            <i class="fa-solid fa-circle-info me-1"></i>{{ __("Auto-settles this student's oldest dues first; any extra becomes credit.") }}
                        </div>
                    </div>

                    <div class="mb-4" x-show="c.credit > 0" x-cloak>
                        <label class="form-label fw-semibold small text-muted">{{ __('Pay from Credit Balance (₹)') }}</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white text-success border-success-subtle"><i class="fa-solid fa-wallet"></i></span>
                            <input type="number" step="0.01" min="0" :max="maxCreditAllowed"
                                   class="form-control fw-bold text-success border-success-subtle"
                                   x-model.number="creditUsed" @input="validateCredit">
                            <button type="button" class="btn btn-outline-success text-uppercase fw-bold" style="font-size: 0.75rem;" @click="creditUsed = maxCreditAllowed">{{ __('Max') }}</button>
                        </div>
                    </div>
                    <input type="hidden" name="credit_used" :value="creditUsed">
                    <input type="hidden" name="amount" :value="cashAmount">

                    {{-- Cash details only exist while there IS a cash portion.
                         template x-if (not x-show) so the inputs leave the DOM
                         and can't post duplicate/conflicting fields. --}}
                    <template x-if="cashAmount > 0">
                        <div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold small text-muted">{{ __('Pay via Cash / Online (₹)') }}</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white text-dark"><i class="fa-solid fa-money-bill-wave"></i></span>
                                    <input type="text" class="form-control fw-bold bg-white" :value="cashAmount.toFixed(2)" readonly>
                                </div>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-bold small">{{ __('Payment Mode') }}</label>
                                    <x-he-select name="mode" compact :submit="false"
                                        :selected="$paymentModes->first()?->code ?? 'cash'"
                                        :options="$paymentModes->mapWithKeys(fn ($m) => [$m->code => $m->name])->all()" />
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-bold small">{{ __('Payment Date') }}</label>
                                    <input type="date" name="paid_on" class="form-control bg-light" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required>
                                </div>
                            </div>
                            <div class="mb-3" x-show="requiresReference" x-cloak>
                                <label class="form-label fw-bold small">{{ __('Reference / Transaction ID') }} <span class="text-danger">*</span></label>
                                <input type="text" name="reference_number" class="form-control bg-light"
                                       :required="requiresReference" placeholder="{{ __('e.g. UPI Txn ID, Cheque No.') }}">
                            </div>
                        </div>
                    </template>
                    <template x-if="cashAmount <= 0">
                        <div>
                            <input type="hidden" name="mode" value="{{ $paymentModes->first()?->code ?? 'cash' }}">
                            <input type="hidden" name="paid_on" value="{{ now()->toDateString() }}">
                        </div>
                    </template>

                    <div class="mb-2">
                        <label class="form-label fw-bold small">{{ __('Remarks (Optional)') }}</label>
                        <input type="text" name="remarks" class="form-control bg-light" placeholder="{{ __('Any note about this payment') }}">
                    </div>
                </div>

                <div class="custom-overlay-footer">
                    <button type="button" class="btn btn-light rounded-pill px-4 fw-bold" @click="closeCollect()">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold" :disabled="totalPayment <= 0">
                        <i class="fa-solid fa-check-circle me-2"></i>{{ __('Confirm Payment') }}
                    </button>
                </div>
            </form>
        </div>
    </template>

    {{-- ═══════════════ Edit Invoice (surfaced W6.1) ═══════════════ --}}
    <template x-teleport="body">
        <div class="custom-overlay-backdrop" x-show="editOpen" x-transition.opacity @click="closeEdit()" x-cloak style="display: none;">
            <form method="POST" :action="e.action" data-ring-required class="custom-overlay-modal" :class="{ 'is-open': editOpen }" x-show="editOpen" x-transition.opacity @click.stop style="display: none;">
                @csrf @method('PATCH')
                <div class="custom-overlay-header">
                    <h5 class="fw-bold mb-0">
                        <i class="fa-solid fa-pen text-primary me-1"></i>
                        {{ __('Edit Invoice') }}
                        <div class="fs-6 fw-normal text-muted mt-1" x-text="e.student"></div>
                    </h5>
                    <button type="button" class="btn-close" @click="closeEdit()"></button>
                </div>

                <div class="custom-overlay-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Title / Description') }} <span class="text-danger">*</span></label>
                        <input type="text" name="title" x-model="e.title" class="form-control bg-light" required maxlength="255">
                    </div>
                    <div class="row gx-3">
                        <div class="col-md-7 mb-3">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Amount') }} <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted fw-bold">₹</span>
                                <input type="number" name="amount" x-model.number="e.amount" class="form-control bg-light fw-bold"
                                       required :min="e.paid > 0 ? e.paid : 0.01" step="0.01">
                            </div>
                            <div class="form-text small text-warning-emphasis" x-show="e.paid > 0" x-cloak>
                                <i class="fa-solid fa-circle-info me-1"></i>{{ __('Already collected') }} <span x-text="'₹' + Number(e.paid).toLocaleString('en-IN')"></span> — {{ __("the amount can't go below that.") }}
                            </div>
                        </div>
                        <div class="col-md-5 mb-3">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Due Date') }}</label>
                            <input type="date" name="due_date" x-model="e.due" class="form-control bg-light">
                        </div>
                    </div>
                </div>

                <div class="custom-overlay-footer">
                    <button type="button" class="btn btn-light rounded-pill px-4 fw-bold" @click="closeEdit()">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-premium rounded-pill px-4 fw-bold">
                        <i class="fa-solid fa-check me-2"></i>{{ __('Save Changes') }}
                    </button>
                </div>
            </form>
        </div>
    </template>

    {{-- ═══════════════ Email Receipt (surfaced W6.1) ═══════════════ --}}
    <template x-teleport="body">
        <div class="custom-overlay-backdrop" x-show="emailOpen" x-transition.opacity @click="closeEmail()" x-cloak style="display: none;">
            <form method="POST" :action="em.action" data-ring-required class="custom-overlay-modal" :class="{ 'is-open': emailOpen }" x-show="emailOpen" x-transition.opacity @click.stop style="display: none;">
                @csrf
                <div class="custom-overlay-header">
                    <h5 class="fw-bold mb-0">
                        <i class="fa-solid fa-envelope text-primary me-1"></i>
                        {{ __('Email Receipt') }}
                        <div class="fs-6 fw-normal text-muted mt-1"><span x-text="em.receipt"></span> · <span x-text="em.student"></span></div>
                    </h5>
                    <button type="button" class="btn-close" @click="closeEmail()"></button>
                </div>

                <div class="custom-overlay-body">
                    <div class="mb-2">
                        <label class="form-label fw-bold small">{{ __('Send to') }} <span class="text-danger">*</span></label>
                        <input type="email" name="email" x-model="emailTo" class="form-control form-control-lg bg-light"
                               required placeholder="{{ __('name@example.com') }}" x-ref="emailInput">
                        <div class="form-text text-muted small mt-2">
                            <i class="fa-solid fa-circle-info me-1"></i>{{ __('The PDF receipt will be attached.') }}
                        </div>
                    </div>
                </div>

                <div class="custom-overlay-footer">
                    <button type="button" class="btn btn-light rounded-pill px-4 fw-bold" @click="closeEmail()">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-premium rounded-pill px-4 fw-bold">
                        <i class="fa-solid fa-paper-plane me-2"></i>{{ __('Send') }}
                    </button>
                </div>
            </form>
        </div>
    </template>

</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {

    Alpine.data('financeBoard', () => ({
        tab: @json(request('tab', 'invoices')),

        // Drives the in-field clear X. Seeded from the server so the X is
        // already there on a page loaded with ?search= — the search input
        // never gets fragment-swapped, so this state is its only source.
        searchTerm: @json($search ?? ''),

        // --- New Invoice modal (W6.1 redesign: multi-select) ---
        chargeModalOpen: false,
        invoiceType: 'fee',          // fee | other
        feeType: '',                 // '' | semester | yearly | monthly (fee charge only)
        students: {{ Illuminate\Support\Js::from($pickerStudents) }},
        selectedIds: [],
        studentSearch: '',
        pickerOpen: false,
        fineTotal: '',
        title: '',
        today: @json(now()->toDateString()),

        // --- Collect sheet (W6.1) ---
        collectOpen: false,
        c: { action: '', student: '', invoice: '', balance: 0, credit: 0 },
        totalPayment: 0,
        creditUsed: 0,
        payMode: @json($paymentModes->first()?->code ?? 'cash'),
        modeReq: {{ Illuminate\Support\Js::from($paymentModes->mapWithKeys(fn ($m) => [$m->code => (bool) $m->requires_reference])) }},

        // --- Edit sheet (W6.1) ---
        editOpen: false,
        e: { action: '', title: '', amount: 0, due: '', paid: 0, student: '' },

        // --- Email receipt sheet (W6.1) ---
        emailOpen: false,
        em: { action: '', receipt: '', student: '' },
        emailTo: '',

        get feeTypeLabel() {
            return this.feeType ? this.feeType.charAt(0).toUpperCase() + this.feeType.slice(1) : '';
        },

        // $nextTick so the cleared x-model has reached the real input before the
        // form serialises — otherwise it re-submits the stale search term.
        // requestSubmit() (never submit()) keeps the fragment interception alive.
        clearSearch() {
            this.searchTerm = '';
            this.$nextTick(() => this.$refs.filterForm?.requestSubmit());
        },

        get feeMultiplier() {
            return this.feeType === 'yearly' ? 12 : (this.feeType === 'semester' ? 6 : 1);
        },

        // The picker can't filter by frequency or price anything until it knows
        // the fee type — so it stays shut, but clickable (it rings the chips).
        get feeLocked() { return this.invoiceType === 'fee' && !this.feeType; },

        // Cutoff for the "already invoiced" hint: one fee-type period back from
        // today. This only decides WHEN to speak up — nothing about it is shown
        // or claimed. The window comes from the type the owner just picked, not
        // from a guessed end date, and the hint states only the recorded date.
        get recencyCutoff() {
            const [y, m, d] = this.today.split('-').map(Number);
            const dt = new Date(Date.UTC(y, m - 1, d));
            dt.setUTCMonth(dt.getUTCMonth() - this.feeMultiplier);
            return dt.toISOString().slice(0, 10);
        },

        get formAction() {
            return this.invoiceType === 'fee'
                ? @json(route('admin.finance.generate-fee'))
                : @json(route('admin.invoices.store'));
        },

        // Picker list: the fee flow filters by matching frequency; plan-less
        // students show everywhere (their amount resolves from room rent).
        get eligibleStudents() {
            if (this.invoiceType === 'fee' && !this.feeType) return [];
            let list = this.students;
            if (this.invoiceType === 'fee') {
                list = list.filter(s => s.fee_frequency === this.feeType || s.fee_frequency === null);
            }
            const q = this.studentSearch.trim().toLowerCase();
            if (q) {
                list = list.filter(s => s.name.toLowerCase().includes(q) || s.mobile.includes(q));
            }
            return list;
        },

        get selected() {
            return this.selectedIds.map(id => this.students.find(s => s.id === id)).filter(Boolean);
        },

        isSelected(id) { return this.selectedIds.includes(id); },

        toggleStudent(s) {
            this.isSelected(s.id) ? this.removeStudent(s.id) : this.selectedIds.push(s.id);
        },

        removeStudent(id) {
            this.selectedIds = this.selectedIds.filter(i => i !== id);
        },

        // Mirrors the server's resolution exactly (preview only — the server
        // re-resolves on submit and never trusts these numbers).
        resolveFeeAmount(s) {
            if (s.fee_amount > 0) return s.fee_amount;
            if (s.room_rent > 0) return Math.round(s.room_rent * this.feeMultiplier * 100) / 100;
            return null;
        },

        // Mirrors InvoiceController::store's remainder-correct equal split.
        fineShares(n) {
            const t = Number(this.fineTotal) || 0;
            if (n < 1 || t <= 0) return [];
            const base = Math.floor((t / n) * 100) / 100;
            const first = Math.round((t - base * (n - 1)) * 100) / 100;
            return Array.from({ length: n }, (_, i) => (i === 0 ? first : base));
        },

        get summaryRows() {
            if (this.invoiceType === 'fee') {
                const cutoff = this.recencyCutoff;
                return this.selected.map(s => {
                    const amount = this.resolveFeeAmount(s);
                    return {
                        s, amount,
                        unresolvable: amount === null,
                        // Y-m-d strings compare correctly as strings.
                        recentlyInvoiced: !!(s.last_invoiced_on && s.last_invoiced_on > cutoff),
                    };
                });
            }
            const shares = this.fineShares(this.selected.length);
            return this.selected.map((s, i) => ({ s, amount: shares[i] ?? 0, unresolvable: false, recentlyInvoiced: false }));
        },

        get billable() { return this.summaryRows.filter(r => !r.unresolvable); },
        get submitIds() { return this.billable.map(r => r.s.id); },
        get totalPreview() { return this.billable.reduce((t, r) => t + (Number(r.amount) || 0), 0); },

        fmt(v) {
            return Number(v || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        get pickerLabel() {
            if (this.invoiceType === 'fee' && !this.feeType) return @json(__('Select fee type first'));
            const n = this.selected.length;
            if (n === 0) return @json(__('Add students…'));
            return n === 1 ? this.selected[0].name : n + ' ' + @json(__('students selected'));
        },

        get countLabel() {
            const n = this.billable.length;
            return this.invoiceType === 'fee'
                ? n + ' ' + (n === 1 ? @json(__('invoice')) : @json(__('invoices')))
                : n + ' ' + (n === 1 ? @json(__('student')) : @json(__('students'))) + ' · ' + @json(__('equal split'));
        },

        get submitLabel() {
            const n = this.billable.length;
            if (this.invoiceType === 'fee') {
                return n > 1 ? @json(__('Generate')) + ' ' + n + ' ' + @json(__('Invoices')) : @json(__('Generate Invoice'));
            }
            return n > 1 ? @json(__('Charge')) + ' ' + n + ' ' + @json(__('Students')) : @json(__('Charge Student'));
        },

        get submitDisabled() {
            if (this.billable.length === 0) return true;
            if (this.invoiceType === 'fee' && !this.feeType) return true;
            if (this.invoiceType === 'other' && !(Number(this.fineTotal) > 0)) return true;
            return false;
        },

        openModal() {
            this.invoiceType = 'fee';
            this.feeType = '';
            this.selectedIds = [];
            this.studentSearch = '';
            this.pickerOpen = false;
            this.fineTotal = '';
            this.title = '';
            this.chargeModalOpen = true;
            document.body.style.overflow = 'hidden';
        },

        closeCharge() {
            this.chargeModalOpen = false;
            document.body.style.overflow = '';
        },

        setChargeType(type) {
            if (this.invoiceType === type) return;
            this.invoiceType = type;
            this.feeType = '';
            this.selectedIds = [];
            this.fineTotal = '';
            this.pickerOpen = false;
        },

        // Changing fee type changes both the filter AND every resolved amount —
        // a kept selection would silently re-price, so it resets instead.
        setFeeType(type) {
            if (this.feeType === type) return;
            this.feeType = type;
            this.selectedIds = [];
            this.pickerOpen = false;
        },

        // Reaching for students with no fee type chosen: ring the chips instead
        // of failing silently. The DEPENDENCY tone (primary), not danger —
        // nothing is wrong yet, the order is just backwards. window.heRing
        // handles retriggering and cleanup.
        flashFeeType() {
            window.heRing?.(this.$refs.feeChips?.querySelectorAll('.chip') ?? [], 'primary');
        },

        togglePicker() {
            if (this.feeLocked) { this.flashFeeType(); return; }
            this.pickerOpen = !this.pickerOpen;
            // Same law as x-he-select (§4.7 — no angle brackets in this comment:
            // Blade compiles component tags even inside JS comments): measure
            // once visible, then open into whatever space actually exists. This
            // panel is tall, and in a modal near the bottom of the viewport it
            // must flip upward.
            if (this.pickerOpen) this.$nextTick(() => {
                window.hePlaceMenu?.(this.$refs.pickerTrigger, this.$refs.pickerPanel);
                this.$refs.studentSearch?.focus();
            });
        },

        onSubmit(e) {
            if (this.submitDisabled) {
                e.preventDefault();
                if (this.selected.length === 0) this.pickerOpen = true;
            }
        },

        // --- Collect (mirrors the profile's collect mechanics: total → credit
        //     split → cash; PaymentService allocates FIFO server-side) ---
        get maxCreditAllowed() {
            return Math.min(Number(this.c.credit) || 0, Number(this.totalPayment) || 0);
        },
        get cashAmount() {
            return Math.max(0, (Number(this.totalPayment) || 0) - (Number(this.creditUsed) || 0));
        },
        get requiresReference() {
            return !!this.modeReq[this.payMode];
        },
        openCollect(payload) {
            this.c = payload;
            this.totalPayment = payload.balance > 0 ? payload.balance : 0;
            this.creditUsed = Math.min(Number(payload.credit) || 0, this.totalPayment);
            this.collectOpen = true;
            document.body.style.overflow = 'hidden';
        },
        closeCollect() {
            this.collectOpen = false;
            document.body.style.overflow = '';
        },
        validateCredit() {
            if (this.creditUsed > this.maxCreditAllowed) this.creditUsed = this.maxCreditAllowed;
            if (this.creditUsed < 0 || isNaN(this.creditUsed)) this.creditUsed = 0;
        },

        openEdit(payload) {
            this.e = payload;
            if (!this.e.due) this.e.due = '';
            this.editOpen = true;
            document.body.style.overflow = 'hidden';
        },
        closeEdit() {
            this.editOpen = false;
            document.body.style.overflow = '';
        },

        openEmail(payload) {
            this.em = payload;
            this.emailTo = '';
            this.emailOpen = true;
            document.body.style.overflow = 'hidden';
            this.$nextTick(() => this.$refs.emailInput?.focus());
        },
        closeEmail() {
            this.emailOpen = false;
            document.body.style.overflow = '';
        },

        switchTab(newTab, updateUrl = true) {
            this.tab = '';
            setTimeout(() => {
                this.tab = newTab;
                if (updateUrl) {
                    const url = new URL(window.location);
                    url.searchParams.set('tab', newTab);
                    window.history.replaceState({}, '', url);
                    window.dispatchEvent(new CustomEvent('sync-sidebar-tab', { detail: newTab }));
                }
            }, 300);
        },

        init() {
            // Total shrank below the credit split → clamp the split.
            this.$watch('totalPayment', () => this.validateCredit());
        },
    }));
});
</script>
@endpush

@endsection
