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
    @container (min-width: 880px) {
        .st-row {
            grid-template-columns: minmax(260px, 1fr) auto auto;
            grid-template-areas: "info money acts";
            column-gap: 1.25rem;
        }
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

    /* ── Attendance tab (extracted as-is; rebuilt in W7.3) ── */
    .attendance-pill-group {
        background: var(--he-bg-canvas);
        border: 1px solid rgba(0, 0, 0, 0.05);
        padding: 4px;
        border-radius: var(--he-radius-full);
    }
    .attendance-pill {
        border-radius: var(--he-radius-full);
        transition: all 0.3s var(--ease-out-expo);
        color: var(--he-text-muted);
        font-weight: 600;
        border: none;
        background: transparent;
    }
    .attendance-pill:active { transform: scale(0.95); }
    .btn-check:checked + .att-success { background: var(--he-success); color: #fff; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3); }
    .btn-check:checked + .att-danger { background: var(--he-danger); color: #fff; box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3); }
    .btn-check:checked + .att-warning { background: var(--he-warning); color: #000; box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3); }
    .btn-check:checked + .att-secondary { background: var(--he-text-muted); color: #fff; box-shadow: 0 4px 15px rgba(100, 116, 139, 0.3); }

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
    <div class="he-tabs mb-4 border-bottom stagger-3">
        <button class="he-tab bg-transparent border-0 py-3 px-4 fw-medium text-secondary position-relative tactile-btn"
                :class="{ 'text-dark fw-bold': tab === 'directory' }" @click="switchTab('directory')">
            <i class="fa-solid fa-address-book me-1"></i> {{ __('Directory & Payroll') }}
            <div x-show="tab === 'directory'" class="position-absolute bottom-0 start-0 w-100" style="height: 3px; background: var(--he-primary); border-radius: 3px 3px 0 0;" x-transition></div>
        </button>
        <button class="he-tab bg-transparent border-0 py-3 px-4 fw-medium text-secondary position-relative tactile-btn"
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
