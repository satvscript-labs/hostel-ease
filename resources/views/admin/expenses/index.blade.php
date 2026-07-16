@extends('layouts.app')
@section('title', __('Expenses'))

@push('styles')
<style>
    /* Page-local layout only — buttons/action rows are the canonical
       .he-icon-btn / .he-act-row family, search is .he-search--inline,
       dropdowns are x-he-select (placement + stacking handled by law
       sections 4.2/4.7). W6.2 full redesign, replacing the pre-design-system
       page wholesale. */

    /* Page heading — the Finance Board's approved mobile type scale (title
       grows on phones, it doesn't shrink). */
    .exp-page-title { font-size: 1.6rem; letter-spacing: -0.01em; }
    @media (max-width: 576px) {
        .exp-page-title { font-size: 2.2rem; line-height: 1.5; }
        .exp-page-sub { font-size: 1rem; line-height: 1.5; }
        #expense-list { padding-bottom: 5rem; } /* clear the FAB */
    }

    /* Dropdown-over-list rule (4.2): the filter row owns dropdowns and sits
       above the list — lift the whole row. */
    .exp-filter-row { position: relative; z-index: 30; }

    /* Transparent fragment-swap boundary (4.3): the category select
       re-renders from server truth so a category-chip click below the row
       can't leave the select showing a stale value. The search input and
       date inputs stay OUTSIDE it — swapping controls mid-interaction drops
       focus. */
    #exp-filter-aux { display: contents; }

    /* Summary tiles (white premium, colour carried by the icon + number). */
    .exp-tile { border-radius: 1.25rem; background: var(--he-bg-surface); }
    .exp-tile-ic {
        width: 40px; height: 40px; border-radius: 50%; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        position: relative;
    }
    .exp-tile-ic::after {
        content: ''; position: absolute; inset: 0;
        background: inherit; filter: blur(8px); z-index: -1;
    }

    /* Date-range chips: a styled premium chip with the NATIVE date input
       stretched invisibly across it — clicks land on the input itself, so
       the picker opens without any JS. Value readout is Alpine (x-text), so
       nothing here needs fragment-swapping. */
    .exp-date {
        position: relative; overflow: hidden; flex-shrink: 0;
        display: flex; align-items: center; gap: 0.6rem;
        padding: 0.45rem 0.9rem 0.45rem 0.55rem;
        background: var(--he-bg-surface);
        border: 1px solid rgba(0, 0, 0, 0.08);
        border-radius: var(--he-radius-full);
        transition: border-color 0.2s var(--ease-out-expo);
    }
    .exp-date:hover { border-color: var(--he-primary); }
    .exp-date input[type="date"] {
        position: absolute; inset: 0; width: 100%; height: 100%;
        opacity: 0; cursor: pointer; z-index: 2;
    }
    /* Desktop browsers only open the calendar from the tiny indicator icon —
       clicking the field body just focuses it, which made the chip look dead
       on PC. Stretch the indicator across the whole input so every click IS
       an indicator click (the showPicker() call on the input is the
       belt-and-braces for non-WebKit engines). */
    .exp-date input[type="date"]::-webkit-calendar-picker-indicator {
        position: absolute; inset: 0; width: 100%; height: 100%;
        cursor: pointer; opacity: 0;
    }
    .exp-date-ic {
        width: 30px; height: 30px; border-radius: 50%; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        background: var(--he-primary-soft); color: var(--he-primary);
        font-size: 0.75rem;
    }
    .exp-date-lbl {
        display: block; font-size: 0.6rem; font-weight: 700;
        text-transform: uppercase; letter-spacing: 0.5px;
        color: var(--he-text-muted); line-height: 1.1;
    }
    /* Phones: the chip collapses to a square icon (same move as
       icon-only-mobile on x-he-select) — the active range stays readable in
       the list header, which fragment-swaps with the data. */
    @media (max-width: 767.98px) {
        .exp-date { width: 46px; height: 46px; padding: 0; justify-content: center; }
        .exp-date .exp-date-txt { display: none; }
    }

    /* Category spend strip: each chip is a real filter (data-fragment
       anchor). Scrolls sideways on phones instead of wrapping the page. */
    .exp-chip-strip {
        display: flex; gap: 0.5rem; overflow-x: auto;
        padding-bottom: 0.25rem;
        scrollbar-width: none;
    }
    .exp-chip-strip::-webkit-scrollbar { display: none; }
    .exp-cat-chip {
        display: inline-flex; align-items: center; gap: 0.45rem;
        padding: 0.4rem 0.85rem; flex-shrink: 0;
        background: var(--he-bg-surface);
        border: 1px solid rgba(0, 0, 0, 0.08);
        border-radius: var(--he-radius-full);
        font-size: 0.8rem; font-weight: 600; color: var(--he-text-main);
        text-decoration: none; white-space: nowrap;
        transition: all 0.18s var(--ease-out-expo);
    }
    .exp-cat-chip i { color: var(--he-text-muted); font-size: 0.75rem; }
    .exp-cat-chip .exp-chip-amt { color: var(--he-danger); font-feature-settings: 'tnum'; }
    .exp-cat-chip:hover { border-color: var(--he-primary); color: var(--he-primary); }
    .exp-cat-chip.active {
        background: var(--he-primary); border-color: var(--he-primary); color: #fff;
    }
    .exp-cat-chip.active i, .exp-cat-chip.active .exp-chip-amt { color: rgba(255, 255, 255, 0.85); }

    /* Desktop list grid: fixed date/mode/amount tracks so values align down
       the whole list (the W6.1 fin-row lesson — content-sized columns are
       what read as "cluttered"). */
    .exp-row {
        display: grid;
        grid-template-columns: minmax(0, 2.1fr) 110px 120px minmax(150px, 1.1fr) 130px auto;
        align-items: center;
        column-gap: 1rem;
    }
    .exp-row-num { text-align: right; font-feature-settings: 'tnum'; font-variant-numeric: tabular-nums; }
    .exp-row-lbl {
        font-size: 0.62rem; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.06em; color: var(--he-text-muted); margin-bottom: 0.15rem;
    }
    .exp-row-acts {
        display: flex; align-items: center; justify-content: flex-end; gap: 0.5rem;
        padding-left: 1rem;
        border-left: 1px solid rgba(0, 0, 0, 0.06);
    }
    @media (max-width: 1299.98px) {
        .exp-row { column-gap: 0.75rem; grid-template-columns: minmax(0, 2fr) 100px 105px minmax(130px, 1fr) 120px auto; }
    }

    .exp-cat-avatar {
        width: 44px; height: 44px; border-radius: 14px;
        display: flex; align-items: center; justify-content: center;
        background: var(--he-danger-soft); color: var(--he-danger);
        font-size: 1rem;
    }
    .exp-cat-avatar--sm { width: 40px; height: 40px; font-size: 0.9rem; }

    /* Salary mirrors: quiet amber "auto" marker, not a shout. */
    .exp-auto-chip {
        display: inline-flex; align-items: center; gap: 0.3rem;
        margin-left: 0.4rem; padding: 0.25rem 0.6rem;
        background: var(--he-warning-soft, rgba(245, 158, 11, 0.12));
        color: var(--he-warning, #b45309);
        border-radius: var(--he-radius-full);
        font-size: 0.68rem; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.04em;
    }
</style>
@endpush

@section('content')
@php
    /* One icon per category — the picker menu teaches the mapping, the list
       avatar and the spend chips repeat it. Shared with _list.blade.php via
       include scope. */
    $catIcons = [
        'electricity' => 'bolt',
        'water' => 'droplet',
        'staff_salary' => 'user-tie',
        'maintenance' => 'screwdriver-wrench',
        'groceries' => 'basket-shopping',
        'rent' => 'building',
        'other' => 'receipt',
    ];
    $categoryOptions = collect(config('hostelease.expense_categories'))
        ->map(fn ($label, $key) => ['label' => $label, 'icon' => $catIcons[$key] ?? 'receipt'])
        ->all();
    $fragmentTargets = '#exp-summary, #exp-filter-aux, #exp-cat-chips, #expense-list';
@endphp

<div class="page-enter" x-data="expenseBoard()">

    {{-- ══ Header ══ --}}
    <div class="d-flex align-items-center justify-content-between gap-3 mb-4 stagger-1">
        <div>
            <h1 class="exp-page-title fw-bold mb-1">{{ __('Expenses & Outflows') }}</h1>
            <p class="exp-page-sub text-secondary mb-0">{{ __('Every rupee out, and what\'s left of what came in.') }}</p>
        </div>
        {{-- Desktop action; phones get the FAB. --}}
        <button type="button" class="btn btn-premium rounded-pill px-4 fw-semibold shadow-sm tactile-btn d-none d-md-inline-flex align-items-center flex-shrink-0"
                @click="openCreate()">
            <i class="fa-solid fa-plus me-2"></i>{{ __('Log Expense') }}
        </button>
    </div>

    {{-- ══ P&L tiles — whole selected window, never the page or the search.
         Inside the fragment targets: changing the date window re-renders
         them with the new period's truth. Income is CASH-ONLY (owner
         decision W6.2): credit applications re-count money that was already
         income once, and credit notes are refunds. ══ --}}
    <div id="exp-summary">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <span class="text-muted small fw-bold text-uppercase" style="letter-spacing: 1px;">{{ __('Profit & Loss') }}</span>
            <span class="text-muted small fw-semibold" style="font-feature-settings: 'tnum';">
                {{ $from->format('d M Y') }} &ndash; {{ $to->format('d M Y') }}
            </span>
        </div>
        <div class="row g-3 mb-4 stagger-2">
            <div class="col-md-4">
                <div class="card exp-tile h-100 border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="text-secondary small fw-bold text-uppercase" style="letter-spacing: 1px;">{{ __('Income') }} <span class="opacity-50">· {{ __('cash in') }}</span></div>
                            <div class="exp-tile-ic" style="background: var(--he-success-soft, rgba(34,197,94,0.12)); color: var(--he-success, #16a34a);"><i class="fa-solid fa-arrow-trend-up"></i></div>
                        </div>
                        <div class="h2 mb-0 fw-bold text-success" style="font-feature-settings: 'tnum';">{{ hostelease_money($summary['income']) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card exp-tile h-100 border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="text-secondary small fw-bold text-uppercase" style="letter-spacing: 1px;">{{ __('Expenses') }}</div>
                            <div class="exp-tile-ic" style="background: var(--he-danger-soft); color: var(--he-danger);"><i class="fa-solid fa-arrow-trend-down"></i></div>
                        </div>
                        <div class="h2 mb-0 fw-bold text-danger" style="font-feature-settings: 'tnum';">{{ hostelease_money($summary['expense']) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                @php $profitable = $summary['profit'] >= 0; @endphp
                <div class="card exp-tile h-100 border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="text-secondary small fw-bold text-uppercase" style="letter-spacing: 1px;">{{ $profitable ? __('Net Profit') : __('Net Loss') }}</div>
                            <div class="exp-tile-ic" style="background: {{ $profitable ? 'var(--he-primary-soft)' : 'var(--he-warning-soft, rgba(245,158,11,0.12))' }}; color: {{ $profitable ? 'var(--he-primary)' : 'var(--he-warning, #b45309)' }};">
                                <i class="fa-solid fa-{{ $profitable ? 'scale-balanced' : 'scale-unbalanced-flip' }}"></i>
                            </div>
                        </div>
                        <div class="h2 mb-0 fw-bold {{ $profitable ? 'text-primary' : 'text-warning-emphasis' }}" style="font-feature-settings: 'tnum';">{{ hostelease_money($summary['profit']) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ══ Filter bar — ONE row at every width (4.5). Search flexes; the
         date chips and category select collapse to icon squares on phones.
         Everything drives the fragments server-side (4.3). ══ --}}
    <div class="mb-3 exp-filter-row stagger-3">
        <form method="GET" action="{{ route('admin.expenses.index') }}" x-ref="filterForm"
              data-fragment="{{ $fragmentTargets }}"
              class="d-flex flex-nowrap gap-2 align-items-center">
            <div class="he-search he-search--inline he-search--clearable">
                <span class="he-search__icon"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="text" name="search" x-model="searchTerm" class="he-search__input"
                       placeholder="{{ __('Search by title, payee, or reference...') }}"
                       @input.debounce.450ms="$el.form.requestSubmit()">
                <button type="button" class="he-search__clear" x-show="searchTerm" x-cloak
                        @click="clearSearch()" title="{{ __('Clear search') }}">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="exp-date" title="{{ __('From date') }}">
                <input type="date" name="from" x-model="fromDate" max="{{ now()->toDateString() }}"
                       @click="try { $el.showPicker() } catch (e) {}"
                       @change="$el.form.requestSubmit()" aria-label="{{ __('From date') }}">
                <span class="exp-date-ic"><i class="fa-solid fa-calendar-day"></i></span>
                <span class="exp-date-txt">
                    <span class="exp-date-lbl">{{ __('From') }}</span>
                    <span class="fw-bold small text-dark" style="line-height: 1.1;" x-text="fmtDate(fromDate)"></span>
                </span>
            </div>
            <div class="exp-date" title="{{ __('To date') }}">
                <input type="date" name="to" x-model="toDate" max="{{ now()->toDateString() }}"
                       @click="try { $el.showPicker() } catch (e) {}"
                       @change="$el.form.requestSubmit()" aria-label="{{ __('To date') }}">
                <span class="exp-date-ic"><i class="fa-solid fa-calendar-check"></i></span>
                <span class="exp-date-txt">
                    <span class="exp-date-lbl">{{ __('To') }}</span>
                    <span class="fw-bold small text-dark" style="line-height: 1.1;" x-text="fmtDate(toDate)"></span>
                </span>
            </div>

            <span id="exp-filter-aux">
                <x-he-select name="category" icon="tags" icon-only-mobile :selected="$category ?? ''"
                    :options="['' => ['label' => __('All Categories'), 'icon' => 'tags']] + $categoryOptions" />
            </span>
        </form>
    </div>

    {{-- ══ Category spend strip — where the window's money actually went,
         and each chip IS the category filter (clicking the active one clears
         it). Re-renders as a fragment so active state + totals track the
         filters. ══ --}}
    <div id="exp-cat-chips">
        @if($summary['by_category']->isNotEmpty())
            <div class="exp-chip-strip mb-4">
                @foreach($summary['by_category'] as $catKey => $catTotal)
                    @php
                        $isActive = $category === $catKey;
                        $chipParams = array_filter([
                            'from' => $from->toDateString(),
                            'to' => $to->toDateString(),
                            'search' => $search,
                            'category' => $isActive ? null : $catKey,
                        ]);
                    @endphp
                    <a href="{{ route('admin.expenses.index', $chipParams) }}"
                       data-fragment="{{ $fragmentTargets }}"
                       class="exp-cat-chip {{ $isActive ? 'active' : '' }}"
                       title="{{ $isActive ? __('Clear category filter') : __('Filter by this category') }}">
                        <i class="fa-solid fa-{{ $catIcons[$catKey] ?? 'receipt' }}"></i>
                        {{ config('hostelease.expense_categories.'.$catKey, ucfirst($catKey)) }}
                        <span class="exp-chip-amt">{{ hostelease_money($catTotal) }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ══ The list (fragment container: pagination swaps in place, 4.3) ══ --}}
    <div id="expense-list" data-fragment-container>
        @include('admin.expenses._list')
    </div>

    {{-- FAB (phones — the header button is hidden below md) --}}
    <template x-teleport="body">
        <button type="button" class="fab" @click="openCreate()" title="{{ __('Log Expense') }}">
            <i class="fa-solid fa-plus"></i>
        </button>
    </template>

    {{-- ══ Log / Edit Expense — one sheet, two modes. data-ring-required:
         empty mandatory fields ring red instead of the native bubble (4.4). ══ --}}
    <template x-teleport="body">
        <div class="custom-overlay-backdrop" x-show="modalOpen" x-transition.opacity @click="closeModal()" x-cloak style="display: none;">
            <form method="POST" :action="isEdit ? f.action : @js(route('admin.expenses.store'))" data-ring-required
                  class="custom-overlay-modal" :class="{ 'is-open': modalOpen }" x-show="modalOpen" x-transition.opacity @click.stop style="display: none;">
                @csrf
                <template x-if="isEdit"><input type="hidden" name="_method" value="PATCH"></template>

                <div class="custom-overlay-header">
                    <h5 class="fw-bold mb-0">
                        <i class="fa-solid fa-money-bill-wave" style="color: var(--he-primary);"></i>
                        <span class="ms-1" x-text="isEdit ? @js(__('Edit Expense')) : @js(__('Log Expense'))"></span>
                    </h5>
                    <button type="button" class="btn-close" @click="closeModal()"></button>
                </div>

                <div class="custom-overlay-body">
                    <div class="row gx-3">
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Category') }} <span class="text-danger">*</span></label>
                            <x-he-select name="category" compact :submit="false" x-model="f.category"
                                :selected="array_key_first(config('hostelease.expense_categories'))"
                                :options="$categoryOptions" />
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Date') }} <span class="text-danger">*</span></label>
                            <input type="date" name="expense_date" x-model="f.date" class="form-control bg-light" max="{{ now()->toDateString() }}" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Title / Description') }} <span class="text-danger">*</span></label>
                        <input type="text" name="title" x-model="f.title" class="form-control bg-light" required maxlength="150"
                               placeholder="{{ __('e.g. Plumber for Room 101') }}">
                    </div>

                    <div class="row gx-3">
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Amount') }} <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted fw-bold">₹</span>
                                <input type="number" name="amount" x-model.number="f.amount" class="form-control bg-light fw-bold text-dark"
                                       required min="1" step="0.01" placeholder="0.00">
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Payment Mode') }} <span class="text-danger">*</span></label>
                            {{-- The tenant's payment_modes table (W6.2) — the same
                                 vocabulary as collections, not a hardcoded list. --}}
                            <x-he-select name="mode" compact :submit="false" x-model="f.mode"
                                :selected="$paymentModes->first()?->code ?? 'cash'"
                                :options="$paymentModes->mapWithKeys(fn ($m) => [$m->code => $m->name])->all()" />
                        </div>
                    </div>

                    <hr class="opacity-10 my-4">
                    <h6 class="fw-bold text-muted text-uppercase mb-3" style="font-size: 0.75rem; letter-spacing: 1px;">{{ __('Additional Details (Optional)') }}</h6>

                    <div class="row gx-3">
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Paid To') }}</label>
                            <input type="text" name="paid_to" x-model="f.paid_to" class="form-control bg-light" maxlength="150" placeholder="{{ __('Person or vendor name') }}">
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Reference No.') }}</label>
                            <input type="text" name="reference_number" x-model="f.reference" class="form-control bg-light" maxlength="100" placeholder="{{ __('Invoice or bill no.') }}">
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Notes') }}</label>
                        <textarea name="notes" x-model="f.notes" class="form-control bg-light" rows="2" maxlength="500" placeholder="{{ __('Any extra information...') }}"></textarea>
                    </div>
                </div>

                <div class="custom-overlay-footer bg-light">
                    <button type="button" class="btn btn-white border fw-semibold rounded-pill px-4 tactile-btn" @click="closeModal()">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-premium fw-semibold rounded-pill px-4 shadow-sm tactile-btn">
                        <i class="fa-solid fa-check me-2"></i><span x-text="isEdit ? @js(__('Save Changes')) : @js(__('Log Expense'))"></span>
                    </button>
                </div>
            </form>
        </div>
    </template>

</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('expenseBoard', () => ({
        // Seeded from the server: the clear X and the date readouts must be
        // right on a page loaded with filters already in the URL. These
        // controls are never fragment-swapped, so this state is their only
        // source of truth.
        searchTerm: @json($search ?? ''),
        fromDate: @json($from->toDateString()),
        toDate: @json($to->toDateString()),

        // --- Log / Edit sheet (one form, two modes) ---
        modalOpen: false,
        isEdit: false,
        f: { action: '', category: '', title: '', amount: '', date: '', paid_to: '', mode: '', reference: '', notes: '' },

        fmtDate(iso) {
            if (!iso) return '';
            const [y, m, d] = iso.split('-').map(Number);
            return new Date(Date.UTC(y, m - 1, d)).toLocaleDateString('en-IN', {
                day: '2-digit', month: 'short', year: 'numeric', timeZone: 'UTC',
            });
        },

        // $nextTick so the cleared x-model reaches the real input before the
        // form serialises; requestSubmit() keeps the fragment interception.
        clearSearch() {
            this.searchTerm = '';
            this.$nextTick(() => this.$refs.filterForm?.requestSubmit());
        },

        openCreate() {
            this.isEdit = false;
            this.f = {
                action: '',
                category: @json(array_key_first(config('hostelease.expense_categories'))),
                title: '', amount: '',
                date: @json(now()->toDateString()),
                paid_to: '',
                mode: @json($paymentModes->first()?->code ?? 'cash'),
                reference: '', notes: '',
            };
            this.modalOpen = true;
            document.body.style.overflow = 'hidden';
        },

        openEdit(payload) {
            this.isEdit = true;
            this.f = { ...payload };
            this.modalOpen = true;
            document.body.style.overflow = 'hidden';
        },

        closeModal() {
            this.modalOpen = false;
            document.body.style.overflow = '';
        },
    }));
});
</script>
@endpush
@endsection
