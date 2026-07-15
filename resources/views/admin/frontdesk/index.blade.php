@extends('layouts.app')
@section('title', __('Front Desk'))

@section('content')
<style>
    /* Page-local layout only. The controls this page used to hand-roll are now
       canonical: the student pickers are x-he-picker (was .search-select-*),
       the priority selector is .chip/.chip--* (was .priority-*), the cards are
       .panel-card (was .fd-list-item), and the search is .he-search. */

    .fd-page-title { font-size: 1.6rem; letter-spacing: -0.01em; }

    /* Visitor / complaint rows. .panel-card owns the surface; this owns spacing.
       overflow: visible — the canonical panel clips (overflow: hidden) so
       .panel-head's divider meets the rounded corner, but these cards have no
       .panel-head and DO have an in-card dropdown (complaint status) that must
       escape the card's bounds. */
    .fd-list .panel-card { overflow: visible; }
    .fd-row { padding: 1.1rem 1.25rem; }

    /* Dropdown-over-list rule (design law — see ui_design_guidelines "Dropdown
       stacking checklist"): any row that OWNS a dropdown and sits ABOVE a list
       gets an explicit raised stacking order. The menu's own z-index can't
       cross whatever stacking context an animated/opacity'd ancestor creates,
       so we lift the whole row instead — deterministic, like the complaint
       cards' open-state z-index raise. */
    .fd-filter-row { position: relative; z-index: 30; }

    /* Visitor filter bar: from md up (tablet + PC) the pill hugs its content
       on the right instead of stretching across the column — the date field
       only needs date width. */
    .fd-visitor-filter { width: 100%; max-width: 100%; }
    /* Transparent fragment-swap boundary (see markup comment) — a pass-through
       so its children participate directly in the pill's flex row. */
    #fd-date-filter { display: contents; }
    @media (min-width: 768px) {
        .fd-visitor-filter { width: auto; }
        .fd-visitor-filter .fd-date input[type="date"] { width: 170px; flex: 0 0 auto; }
        /* Desktop/tablet: the label wrapper dissolves — the native date input
           participates in the pill's flex row directly, styled as before. */
        .fd-date { display: contents; }
        .fd-date-clear { display: none; }
    }

    /* Mobile (< md): a bespoke filter row — the dd-mm-yyyy text field wastes
       half the pill, so the date control collapses to a calendar chip (icon +
       short date once picked) and the status select gets the remaining width.
       The real <input type="date"> stays in the DOM, invisible, stretched over
       the chip — tapping it opens the native picker, so no JS date widget. */
    @media (max-width: 767.98px) {
        .fd-visitor-filter .he-select-wrap { flex: 1 1 auto; }
        .fd-visitor-filter .he-select-wrap .he-select-trigger { width: 100%; }
        .fd-date {
            position: relative; flex: 0 0 auto; align-self: stretch;
            display: flex; align-items: center; gap: 0.4rem;
            min-width: 44px; padding: 0 0.9rem; margin: 0;
            background: var(--he-bg-surface-raised);
            border-radius: var(--he-radius-full);
            color: var(--he-primary);
        }
        .fd-date input[type="date"] {
            position: absolute; inset: 0; width: 100%; height: 100%;
            opacity: 0; /* native picker on tap, zero visual footprint */
        }
        .fd-date-val { font-size: 0.85rem; font-weight: 700; color: var(--he-text-main); white-space: nowrap; }
        .fd-date-clear {
            flex: 0 0 auto; align-self: center;
            width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;
            border-radius: 50%; background: var(--he-bg-surface-raised);
            color: var(--he-text-muted); text-decoration: none; font-size: 0.8rem;
        }
    }
    .fd-label {
        font-size: 0.66rem; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.5px; color: var(--he-text-muted); margin-bottom: 0.15rem;
    }

    /* "Currently inside" live dot. */
    .fd-pulse {
        width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0;
        background-color: var(--he-success);
        box-shadow: 0 0 0 rgba(16, 185, 129, 0.4);
        animation: fdPulse 2s infinite;
    }
    @keyframes fdPulse {
        0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4); }
        70% { box-shadow: 0 0 0 9px rgba(16, 185, 129, 0); }
        100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
    }

    /* Resolution note: revealed inline only when resolving (B4 — keep it short). */
    .fd-resolve-note { border-top: 1px solid rgba(0, 0, 0, 0.06); padding-top: 0.6rem; margin-top: 0.4rem; }

    @media (max-width: 991.98px) {
        /* Desktop's 4-column visitor row becomes a stacked card: identity on top,
           the two meta blocks side-by-side beneath, actions full-width at the
           bottom (thumb-reachable). Re-arranged, not shrunk. */
        .fd-row { padding: 0.9rem 1rem; }
    }

    @media (max-width: 576px) {
        .fd-page-title { font-size: 2.2rem; line-height: 1.5; }
        .fd-page-sub { font-size: 1rem; line-height: 1.5; }
        /* Density (mobile rule 4) — the glass tiles are roomy at desktop scale. */
        .stat-card-glass { padding: 0.9rem 1rem; }
        .stat-card-glass .display-5 { font-size: 2rem; }
        .fd-list { padding-bottom: 5rem; } /* clear the FAB */
    }
</style>

<div x-data="frontdesk()" @tab-changed.window="switchTab($event.detail, false)" class="page-enter">

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3 stagger-1">
        <div>
            <h1 class="fd-page-title fw-bold mb-1">{{ __('Front Desk') }}</h1>
            <p class="fd-page-sub text-secondary mb-0">{{ __('Manage visitors, walk-ins, and student complaints.') }}</p>
        </div>
        {{-- Desktop add buttons. On phones these collapse to the FAB (below). --}}
        <div class="d-none d-sm-flex gap-2">
            <button class="btn btn-premium rounded-pill fw-bold shadow-sm px-4" @click="openVisitorPanel()" x-show="tab === 'visitors'">
                <i class="fa-solid fa-plus me-1"></i> {{ __('Add Visitor') }}
            </button>
            <button class="btn btn-premium rounded-pill fw-bold shadow-sm px-4" @click="openComplaintPanel()" x-show="tab === 'complaints'" x-cloak>
                <i class="fa-solid fa-plus me-1"></i> {{ __('Log Complaint') }}
            </button>
        </div>
    </div>

    {{-- Tabs. Only two, and both labels fit at 360px, so the plain segmented
         .he-tabs is right here — the icon-expand pattern (mobile rule 8) is for
         3+ tabs that can't fit their labels. --}}
    <div class="he-tabs mb-4 border-bottom stagger-2">
        <button class="he-tab bg-transparent border-0 py-3 px-4 fw-medium text-secondary position-relative tactile-btn"
                :class="{ 'text-dark fw-bold': tab === 'visitors' }" @click="switchTab('visitors')">
            <i class="fa-solid fa-door-open me-1"></i> {{ __('Visitors') }}
            @if($insideCount > 0)
                <span class="badge bg-danger rounded-pill ms-1">{{ $insideCount }}</span>
            @endif
            <div x-show="tab === 'visitors'" class="position-absolute bottom-0 start-0 w-100" style="height: 3px; background: var(--he-primary); border-radius: 3px 3px 0 0;" x-transition></div>
        </button>
        <button class="he-tab bg-transparent border-0 py-3 px-4 fw-medium text-secondary position-relative tactile-btn"
                :class="{ 'text-dark fw-bold': tab === 'complaints' }" @click="switchTab('complaints')">
            <i class="fa-solid fa-headset me-1"></i> {{ __('Complaints') }}
            @if($complaintCounts['open'] > 0)
                <span class="badge bg-danger rounded-pill ms-1">{{ $complaintCounts['open'] }}</span>
            @endif
            <div x-show="tab === 'complaints'" class="position-absolute bottom-0 start-0 w-100" style="height: 3px; background: var(--he-primary); border-radius: 3px 3px 0 0;" x-transition></div>
        </button>
    </div>

    {{-- Canonical page search (mobile rule 7) — was a hand-rolled .input-group. --}}
    <div class="mb-4 stagger-3">
        <div class="he-search">
            <span class="he-search__icon"><i class="fa-solid fa-magnifying-glass"></i></span>
            <input type="text" x-model="search" class="he-search__input" placeholder="{{ __('Search records...') }}">
        </div>
    </div>

    {{-- ================= VISITORS TAB ================= --}}
    <div x-show="tab === 'visitors'"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-4"
         x-transition:enter-end="opacity-100 translate-y-0"
         style="display: none;">

        @php
            // Short label for the mobile date chip. rescue(): request('date') is
            // user input — an unparseable value must degrade to "no date", not 500.
            $fdDateLabel = request('date')
                ? rescue(fn () => \Illuminate\Support\Carbon::parse(request('date'))->format('d M'), null, false)
                : null;
        @endphp
        <div class="row g-3 mb-4 align-items-stretch stagger-4 fd-filter-row">
            <div class="col-12 col-md-4">
                <div class="stat-card-glass stat-card-visitors h-100">
                    <div class="opacity-75 text-uppercase fw-bold small tracking-wider mb-1">{{ __('Currently Inside') }}</div>
                    <div class="display-5 fw-bold">{{ $insideCount }}</div>
                </div>
            </div>
            <div class="col-12 col-md-8 d-flex justify-content-md-end align-items-end">
                {{-- data-fragment: re-render only the visitor list AND this
                     filter's own derived display (see #fd-date-filter below) on
                     submit, never the page (design law §4.3). Plain GET form
                     underneath, so it still works with JS disabled. --}}
                <form method="GET" action="{{ route('admin.frontdesk.index') }}"
                      data-fragment="#visitor-list, #fd-date-filter"
                      class="fd-visitor-filter d-flex gap-2 bg-white p-2 rounded-pill shadow-sm border border-light">
                    <input type="hidden" name="tab" value="visitors">
                    <x-he-select name="filter" icon="filter" :selected="request('filter', '')"
                        :options="['' => __('All Visitors'), 'inside' => __('Currently Inside')]" />
                    {{-- The short "16 Jul" chip label and the clear (X) button
                         are both DERIVED server-side from request('date') — they
                         don't live inside #visitor-list, so a fragment swap that
                         only targets the list would leave them stuck showing
                         whatever they showed on the last full load. This wrapper
                         is a second, always-present fragment target so they stay
                         in sync with the list on every filter change.
                         display: contents (unconditional): a transparent
                         pass-through so its children still participate directly
                         in .fd-visitor-filter's flex row — this wrapper adds a
                         swap target, not a layout box. --}}
                    <span id="fd-date-filter">
                        {{-- On phones this label renders as a calendar chip (icon +
                             short date) with the invisible native input stretched
                             over it; from md up it dissolves (display: contents)
                             and the input shows as a normal 170px date field. --}}
                        <label class="fd-date" title="{{ __('Filter by date') }}">
                            <i class="fa-solid fa-calendar-days d-md-none"></i>
                            @if($fdDateLabel)
                                <span class="fd-date-val d-md-none">{{ $fdDateLabel }}</span>
                            @endif
                            {{-- requestSubmit(), not submit() — see §4.3 / he-select. --}}
                            <input type="date" name="date" class="form-control border-0 bg-light rounded-pill fw-medium" value="{{ request('date') }}" onchange="this.form.requestSubmit()">
                        </label>
                        @if(request('date'))
                            <a href="{{ route('admin.frontdesk.index', array_filter(['tab' => 'visitors', 'filter' => request('filter')])) }}"
                               class="fd-date-clear d-md-none" title="{{ __('Clear date') }}"><i class="fa-solid fa-xmark"></i></a>
                        @endif
                    </span>
                </form>
            </div>
        </div>

        {{-- Fragment target: the filter form swaps ONLY this element's contents.
             Everything outside it (tab, search text, scroll) survives. --}}
        <div class="fd-list" id="visitor-list">
            @forelse($visitors as $v)
                @php
                    // Js::from() gives a properly-escaped JS literal. Interpolating the
                    // name raw into '...' (as this did) breaks on an apostrophe —
                    // O'Brien closes the string and the Alpine expression dies.
                    $vHaystack = \Illuminate\Support\Js::from(
                        mb_strtolower(trim($v->name.' '.optional($v->student)->name.' '.$v->mobile))
                    );
                @endphp
                <div x-show="search === '' || {{ $vHaystack }}.includes(search.toLowerCase())"
                     style="animation: fadeUp 0.6s cubic-bezier(0.25, 1, 0.5, 1) {{ min($loop->index * 0.05, 0.5) }}s both;">
                    <div class="panel-card mb-3 {{ !$v->isInside() ? 'opacity-75' : '' }}">
                        <div class="fd-row row g-3 align-items-center">

                            {{-- Identity --}}
                            <div class="col-12 col-lg-3">
                                <div class="d-flex align-items-center gap-2">
                                    @if($v->isInside()) <span class="fd-pulse"></span> @endif
                                    <span class="fw-bold fs-5 text-dark text-truncate">{{ $v->name }}</span>
                                </div>
                                <div class="small text-muted fw-semibold mt-1">
                                    <i class="fa-solid fa-phone me-1"></i>{{ $v->mobile ? hostelease_phone($v->mobile) : __('No mobile') }}
                                </div>
                            </div>

                            {{-- Visiting --}}
                            <div class="col-6 col-lg-3">
                                <div class="fd-label">{{ __('Visiting') }}</div>
                                @if($v->student)
                                    <a href="{{ route('admin.students.show', $v->student) }}" class="fw-bold text-decoration-none text-primary">{{ $v->student->name }}</a>
                                @else
                                    <span class="fw-bold text-secondary">{{ __('General Visit') }}</span>
                                @endif
                                <div class="small text-muted mt-1">{{ $v->purpose ?: __('No specific purpose') }}</div>
                            </div>

                            {{-- Timing --}}
                            <div class="col-6 col-lg-3">
                                <div class="fd-label">{{ __('Timing') }}</div>
                                <div class="fw-semibold text-dark small"><i class="fa-solid fa-arrow-right-to-bracket text-success me-1"></i>{{ $v->check_in->format('h:i A, d M') }}</div>
                                @if($v->check_out)
                                    <div class="fw-semibold text-secondary small mt-1"><i class="fa-solid fa-arrow-right-from-bracket text-danger me-1"></i>{{ $v->check_out->format('h:i A, d M') }}</div>
                                @endif
                            </div>

                            {{-- Actions --}}
                            <div class="col-12 col-lg-3 d-flex justify-content-lg-end gap-2">
                                @if($v->isInside())
                                    <form action="{{ route('admin.visitors.checkout', $v) }}" method="POST" class="m-0 flex-grow-1 flex-lg-grow-0">
                                        @csrf @method('PATCH')
                                        <button class="btn btn-warning rounded-pill shadow-sm fw-bold px-3 text-dark w-100"><i class="fa-solid fa-person-walking-arrow-right me-1"></i>{{ __('Checkout') }}</button>
                                    </form>
                                @else
                                    <span class="badge bg-light text-secondary border rounded-pill px-3 py-2 align-self-center"><i class="fa-solid fa-check me-1"></i>{{ __('Left') }}</span>
                                @endif

                                {{-- data-confirm → reskinned SweetAlert2 (mobile rule 6a). Was a native confirm(). --}}
                                <form action="{{ route('admin.visitors.destroy', $v) }}" method="POST" class="m-0"
                                      data-confirm="{{ __('Delete this visitor record? This cannot be undone.') }}">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-light rounded-pill border shadow-sm text-danger px-3" title="{{ __('Delete') }}"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <x-he-empty-state icon="door-closed" title="{{ __('No Visitors') }}" subtitle="{{ __('No visitor records found.') }}" />
            @endforelse
        </div>
    </div>

    {{-- ================= COMPLAINTS TAB ================= --}}
    <div x-show="tab === 'complaints'"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-4"
         x-transition:enter-end="opacity-100 translate-y-0"
         style="display: none;">

        <div class="row g-3 mb-4 stagger-4">
            <div class="col-12 col-md-4">
                <div class="stat-card-glass stat-card-complaints-open h-100">
                    <div class="opacity-75 text-uppercase fw-bold small tracking-wider mb-1">{{ __('Open Issues') }}</div>
                    <div class="display-5 fw-bold">{{ $complaintCounts['open'] }}</div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="stat-card-glass stat-card-complaints-prog h-100">
                    <div class="opacity-75 text-uppercase fw-bold small tracking-wider mb-1">{{ __('In Progress') }}</div>
                    <div class="display-5 fw-bold">{{ $complaintCounts['in_progress'] }}</div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="stat-card-glass stat-card-complaints-res h-100">
                    <div class="opacity-75 text-uppercase fw-bold small tracking-wider mb-1">{{ __('Resolved') }}</div>
                    <div class="display-5 fw-bold">{{ $complaintCounts['resolved'] }}</div>
                </div>
            </div>
        </div>

        <div class="row fd-list">
            @forelse($complaints as $c)
                @php
                    $cHaystack = \Illuminate\Support\Js::from(
                        mb_strtolower(trim($c->title.' '.optional($c->student)->name))
                    );
                    $prio = ['high' => 'danger', 'medium' => 'warning', 'low' => 'info'][$c->priority] ?? 'secondary';
                @endphp
                <div class="col-12 col-lg-6"
                     x-data="complaintCard({{ \Illuminate\Support\Js::from($c->status) }})"
                     x-show="search === '' || {{ $cHaystack }}.includes(search.toLowerCase())"
                     :style="`position: relative; z-index: ${open ? 50 : 1}; animation: fadeUp 0.6s cubic-bezier(0.25, 1, 0.5, 1) {{ min($loop->index * 0.05, 0.5) }}s both;`">
                    <div class="panel-card mb-3 h-100 d-flex flex-column">
                        <div class="p-3 p-lg-4 d-flex flex-column h-100">

                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                <div style="min-width: 0;">
                                    <h5 class="fw-bold text-dark mb-1">{{ $c->title }}</h5>
                                    <div class="small text-muted fw-semibold">
                                        {{ config('hostelease.complaint_categories.'.$c->category, $c->category) }} · {{ __('Logged') }} {{ $c->created_at->format('M d') }}
                                    </div>
                                </div>
                                <span class="badge bg-{{ $prio }} {{ $prio === 'warning' || $prio === 'info' ? 'text-dark' : '' }} rounded-pill px-3 py-2 shadow-sm flex-shrink-0">
                                    @if($c->priority === 'high')<i class="fa-solid fa-triangle-exclamation me-1"></i>@endif
                                    {{ config('hostelease.complaint_priorities.'.$c->priority, $c->priority) }}
                                </span>
                            </div>

                            @if($c->description)
                                <p class="text-muted small mb-2">{{ Str::limit($c->description, 100) }}</p>
                            @endif

                            @if($c->resolution)
                                <div class="small mb-2 p-2 rounded-3" style="background: var(--he-success-soft); color: var(--he-success);">
                                    <i class="fa-solid fa-circle-check me-1"></i><span class="fw-semibold">{{ __('Resolution') }}:</span> {{ Str::limit($c->resolution, 90) }}
                                </div>
                            @endif

                            <div class="mt-auto pt-3 border-top d-flex flex-wrap justify-content-between align-items-center gap-2">
                                <div class="d-flex align-items-center gap-2" style="min-width: 0;">
                                    <div class="bg-light rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 32px; height: 32px;">
                                        <i class="fa-solid fa-user text-secondary small"></i>
                                    </div>
                                    @if($c->student)
                                        <a href="{{ route('admin.students.show', $c->student) }}" class="small fw-bold text-decoration-none text-dark text-truncate">{{ $c->student->name }}</a>
                                    @else
                                        <span class="small fw-bold text-secondary">{{ __('Staff / Internal') }}</span>
                                    @endif
                                </div>

                                <div class="d-flex align-items-center gap-2">
                                    {{-- Status dropdown. Open/In-progress submit instantly (1 click).
                                         Resolved/Closed reveal a compact note box instead — the only
                                         transition where "how was it fixed?" is worth one line. --}}
                                    <div class="position-relative">
                                        <button type="button" @click="open = !open" @click.outside.capture="open = false"
                                            class="btn btn-sm rounded-pill shadow-sm fw-bold px-3 d-flex align-items-center justify-content-between border" style="min-width: 140px;"
                                            :class="{
                                                'bg-danger-subtle text-danger border-danger-subtle': status === 'open',
                                                'bg-warning-subtle text-warning-emphasis border-warning-subtle': status === 'in_progress',
                                                'bg-success-subtle text-success border-success-subtle': status === 'resolved' || status === 'closed'
                                            }">
                                            <span x-text="statusLabel" class="text-nowrap"></span>
                                            <i class="fa-solid fa-chevron-down small opacity-50 ms-2"></i>
                                        </button>

                                        <div x-show="open" x-transition.opacity style="display:none; min-width: 230px;"
                                             class="position-absolute mt-2 end-0 bg-white border rounded-4 shadow-lg p-2 z-3">
                                            <form action="{{ route('admin.complaints.update', $c) }}" method="POST" class="m-0" x-ref="statusForm">
                                                @csrf @method('PATCH')
                                                <input type="hidden" name="status" x-model="pending">

                                                @foreach(config('hostelease.complaint_statuses') as $k => $l)
                                                    <button type="button" @click="choose({{ \Illuminate\Support\Js::from($k) }})"
                                                        class="btn btn-sm w-100 text-start rounded-3 mb-1 text-nowrap d-flex align-items-center"
                                                        :class="pending === {{ \Illuminate\Support\Js::from($k) }} ? 'bg-light fw-bold text-dark' : 'text-muted'">
                                                        <span style="width: 20px;">
                                                            <i class="fa-solid fa-check text-primary" x-show="status === {{ \Illuminate\Support\Js::from($k) }}"></i>
                                                        </span>
                                                        {{ $l }}
                                                    </button>
                                                @endforeach

                                                {{-- B4: one optional line, revealed only when resolving. --}}
                                                <div class="fd-resolve-note" x-show="needsNote" x-cloak style="display: none;">
                                                    <input type="text" name="resolution" maxlength="1000"
                                                           class="form-control form-control-sm bg-light border-0 mb-2"
                                                           :placeholder="'{{ __('How was it fixed? (optional)') }}'"
                                                           x-ref="note" @keydown.enter.prevent="$refs.statusForm.submit()">
                                                    <button type="submit" class="btn btn-premium btn-sm w-100 rounded-pill fw-bold">
                                                        <i class="fa-solid fa-check me-1"></i>{{ __('Save') }}
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>

                                    {{-- B3: the destroy route existed since day one with no UI to reach it. --}}
                                    <form action="{{ route('admin.complaints.destroy', $c) }}" method="POST" class="m-0"
                                          data-confirm="{{ __('Delete this complaint? This cannot be undone.') }}">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-light rounded-pill border shadow-sm text-danger px-3" title="{{ __('Delete') }}"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <x-he-empty-state class="col-12" icon="headset" title="{{ __('No Complaints') }}" subtitle="{{ __("You're all caught up!") }}" />
            @endforelse
        </div>
    </div>

    {{-- Mobile FAB — the add action, always thumb-reachable (rule 5). Teleported
         because .page-enter's entrance animation ends on a transform, which
         establishes a containing block and would anchor a position:fixed child
         to the page instead of the viewport (rule 10). x-teleport preserves the
         Alpine scope, so it calls this component's methods directly — same as
         the modals below. --}}
    <template x-teleport="body">
        <button type="button" class="fab"
                @click="tab === 'visitors' ? openVisitorPanel() : openComplaintPanel()"
                :title="tab === 'visitors' ? '{{ __('Add Visitor') }}' : '{{ __('Log Complaint') }}'">
            <i class="fa-solid fa-plus"></i>
        </button>
    </template>

    {{-- ================= MODALS ================= --}}

    <template x-teleport="body">
        <div class="custom-overlay-backdrop" x-show="visitorPanelOpen || complaintPanelOpen" x-transition.opacity @click="closePanels()" x-cloak style="display: none;">

            {{-- Add Visitor --}}
            <form method="POST" action="{{ route('admin.visitors.store') }}" class="custom-overlay-modal" :class="{ 'is-open': visitorPanelOpen }" x-show="visitorPanelOpen" x-transition.opacity @click.stop style="display: none;">
                @csrf
                <div class="custom-overlay-header">
                    <h5 class="fw-bold mb-0"><i class="fa-solid fa-door-open text-primary me-2"></i>{{ __('Add Visitor') }}</h5>
                    <button type="button" class="btn-close" @click="closePanels()"></button>
                </div>

                <div class="custom-overlay-body">
                    <div class="section-header">{{ __('Visitor Details') }}</div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">{{ __('Full Name') }} <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control bg-light" required placeholder="{{ __('John Doe') }}">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-sm-6">
                            <label class="form-label fw-bold small">{{ __('Mobile') }}</label>
                            <input type="tel" name="mobile" class="form-control bg-light" maxlength="10" placeholder="{{ __('10-digit number') }}">
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label fw-bold small">{{ __('ID Proof') }}</label>
                            <input type="text" name="id_proof" class="form-control bg-light" placeholder="{{ __('Aadhaar / DL no.') }}">
                        </div>
                    </div>

                    <div class="section-header">{{ __('Visit Details') }}</div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">{{ __('Visiting Student') }}</label>
                        <x-he-picker name="student_id" :options="$pickerStudents"
                            :none="__('— General Visit (no student) —')"
                            :search-placeholder="__('Search name or mobile…')"
                            :empty-text="__('No students match')" />
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">{{ __('Purpose of Visit') }}</label>
                        <input type="text" name="purpose" class="form-control bg-light" placeholder="{{ __('Meeting, Delivery, Parents...') }}">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">{{ __('Check-in Time') }}</label>
                        <input type="datetime-local" name="check_in" class="form-control bg-light" value="{{ now()->format('Y-m-d\TH:i') }}">
                    </div>
                </div>

                <div class="custom-overlay-footer">
                    <button type="button" class="btn btn-light rounded-pill px-4 fw-bold" @click="closePanels()">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-premium rounded-pill px-5 fw-bold shadow-sm">{{ __('Check In') }}</button>
                </div>
            </form>

            {{-- Log Complaint --}}
            <form method="POST" action="{{ route('admin.complaints.store') }}" class="custom-overlay-modal" :class="{ 'is-open': complaintPanelOpen }" x-show="complaintPanelOpen" x-transition.opacity @click.stop style="display: none;">
                @csrf
                <div class="custom-overlay-header">
                    <h5 class="fw-bold mb-0"><i class="fa-solid fa-headset text-primary me-2"></i>{{ __('Log Complaint') }}</h5>
                    <button type="button" class="btn-close" @click="closePanels()"></button>
                </div>

                <div class="custom-overlay-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold small">{{ __('Title / Issue Summary') }} <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control bg-light" required placeholder="{{ __('e.g. Broken AC in Room 102') }}">
                    </div>

                    {{-- Segmented priority (§1b: chip-group for few-option choices).
                         Was a hand-rolled .priority-* control with hardcoded hex. --}}
                    <div class="mb-3">
                        <label class="form-label fw-bold small">{{ __('Priority Level') }}</label>
                        <div class="chip-group">
                            @php $prioColors = ['low' => 'info', 'medium' => 'warning', 'high' => 'danger']; @endphp
                            @foreach(config('hostelease.complaint_priorities') as $k => $l)
                                <label class="position-relative flex-grow-1">
                                    <input type="radio" name="priority" value="{{ $k }}" class="chip-radio"
                                           @checked($k === 'medium')>
                                    <span class="chip chip--{{ $prioColors[$k] ?? 'info' }} d-block text-center py-2">{{ $l }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">{{ __('Reported By') }}</label>
                        <x-he-picker name="student_id" :options="$pickerStudents"
                            :none="__('— Internal / Staff (not a student) —')"
                            :search-placeholder="__('Search name or mobile…')"
                            :empty-text="__('No students match')" />
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">{{ __('Category') }}</label>
                        <x-he-select name="category" icon="tags" :submit="false" compact
                            :selected="array_key_first(config('hostelease.complaint_categories'))"
                            :options="config('hostelease.complaint_categories')" />
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">{{ __('Detailed Description') }}</label>
                        <textarea name="description" class="form-control bg-light" rows="3" placeholder="{{ __('Provide any additional details about the issue...') }}"></textarea>
                    </div>
                </div>

                <div class="custom-overlay-footer">
                    <button type="button" class="btn btn-light rounded-pill px-4 fw-bold" @click="closePanels()">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-premium rounded-pill px-5 fw-bold shadow-sm">{{ __('Submit') }}</button>
                </div>
            </form>
        </div>
    </template>

</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {

    Alpine.data('frontdesk', () => ({
        tab: @json(request('tab', 'visitors')),
        search: '',
        visitorPanelOpen: false,
        complaintPanelOpen: false,

        openVisitorPanel() {
            this.visitorPanelOpen = true;
            document.body.style.overflow = 'hidden';
        },
        openComplaintPanel() {
            this.complaintPanelOpen = true;
            document.body.style.overflow = 'hidden';
        },
        closePanels() {
            this.visitorPanelOpen = false;
            this.complaintPanelOpen = false;
            document.body.style.overflow = '';
        },
        switchTab(newTab, updateUrl = true) {
            this.tab = '';
            setTimeout(() => {
                this.tab = newTab;
                this.search = ''; // reset search on tab switch
                if (updateUrl) {
                    const url = new URL(window.location);
                    url.searchParams.set('tab', newTab);
                    window.history.replaceState({}, '', url);
                    // Keeps the sidebar's Visitors/Complaints sublink in sync.
                    window.dispatchEvent(new CustomEvent('sync-sidebar-tab', { detail: newTab }));
                }
            }, 300);
        },
    }));

    // One complaint card's status control.
    Alpine.data('complaintCard', (initialStatus) => ({
        open: false,
        status: initialStatus,   // persisted state
        pending: initialStatus,  // what's highlighted in the menu

        get statusLabel() {
            return ({
                open: @json(__('Open')),
                in_progress: @json(__('In Progress')),
                resolved: @json(__('Resolved')),
                closed: @json(__('Closed')),
            })[this.status] ?? this.status;
        },
        // Only resolving asks for a note — every other transition is 1 click.
        get needsNote() {
            return this.pending === 'resolved' || this.pending === 'closed';
        },
        choose(val) {
            this.pending = val;
            if (this.needsNote) {
                this.$nextTick(() => this.$refs.note?.focus());
                return; // wait for the (optional) note + Save
            }
            this.$refs.statusForm.submit();
        },
    }));
});
</script>
@endpush
@endsection
