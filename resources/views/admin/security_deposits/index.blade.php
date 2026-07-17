@extends('layouts.app')
@section('title', __('Security Deposits'))

@push('styles')
<style>
    /* Page-local layout only — W6.4 full redesign on the canonical system
       (he-page-head, he-stats, he-search, he-cq tiers, he-pager). The old
       page was pre-design-system: unbounded list, no search, two invoice
       queries per row, and a raw student <select>. */

    /* Dropdown-over-list rule (§4.2). Never inside a container-type element. */
    .sd-filter-row { position: relative; z-index: 30; }
    #sd-filter-aux { display: contents; }

    /* List rows — container-tiered (§4.9/4.10). */
    .sd-row {
        align-items: center;
        gap: 0.75rem 1rem;
        grid-template-columns: minmax(0, 1fr) auto;
        grid-template-areas:
            "info  acts"
            "money acts";
    }
    .sd-c-info { grid-area: info; display: flex; align-items: center; gap: 1rem; min-width: 0; }
    .sd-c-text { display: flex; gap: 1rem; flex: 1; min-width: 0; }
    .sd-c-block { flex: 1 1 50%; min-width: 0; }
    .sd-row-money { grid-area: money; display: flex; justify-content: flex-end; gap: 1.5rem; }
    .sd-row-acts {
        grid-area: acts;
        display: flex; align-items: center; justify-content: flex-end; gap: 0.5rem;
        padding-left: 1rem;
        border-left: 1px solid rgba(0, 0, 0, 0.06);
        align-self: stretch;
    }
    @container (min-width: 880px) {
        .sd-row {
            grid-template-columns: minmax(260px, 1fr) auto auto;
            grid-template-areas: "info money acts";
            column-gap: 1.25rem;
        }
        .sd-row-acts { padding-left: 1.25rem; align-self: center; }
    }
    .sd-row-num {
        min-width: 96px;
        text-align: right;
        font-feature-settings: 'tnum';
        font-variant-numeric: tabular-nums;
        white-space: nowrap; /* figures never wrap (§4.10) */
    }
    .sd-row-lbl {
        font-size: 0.62rem; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.06em; color: var(--he-text-muted); margin-bottom: 0.15rem;
    }

    /* Departed-with-money marker (owner decision: they stay visible, flagged). */
    .sd-left-chip {
        display: inline-flex; align-items: center; gap: 0.3rem;
        margin-left: 0.4rem; padding: 0.2rem 0.55rem;
        background: var(--he-warning-soft, rgba(245, 158, 11, 0.12));
        color: var(--he-warning, #b45309);
        border-radius: var(--he-radius-full);
        font-size: 0.66rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;
        vertical-align: 2px;
    }

    /* Settlement sheet: the live "every rupee accounted for" meter. */
    .sd-settle-meter {
        display: flex; align-items: center; justify-content: space-between; gap: 0.75rem;
        padding: 0.7rem 0.95rem;
        border-radius: var(--he-radius-md);
        font-weight: 700; font-feature-settings: 'tnum';
    }
    .sd-settle-meter.is-ok { background: var(--he-success-soft, rgba(34,197,94,0.12)); color: var(--he-success, #16a34a); }
    .sd-settle-meter.is-off { background: var(--he-danger-soft); color: var(--he-danger); }
    .sd-due-row {
        display: flex; align-items: center; gap: 0.65rem;
        padding: 0.55rem 0.75rem;
        border: 1px solid rgba(0, 0, 0, 0.07);
        border-radius: var(--he-radius-md);
        cursor: pointer;
        transition: border-color 0.15s var(--ease-out-expo), background 0.15s var(--ease-out-expo);
    }
    .sd-due-row:hover { border-color: var(--he-primary); }
    .sd-due-row.is-checked { border-color: var(--he-primary); background: var(--he-primary-soft); }

    /* Duplicate-deposit notice: states a fact, never blocks — a second
       deposit is legitimate (top-up, re-admission). */
    .sd-dupe-warn {
        display: flex; align-items: flex-start; gap: 0.5rem;
        padding: 0.6rem 0.8rem;
        background: var(--he-warning-soft, rgba(245, 158, 11, 0.12));
        color: var(--he-warning, #b45309);
        border-radius: var(--he-radius-md);
        font-size: 0.8rem; font-weight: 600;
    }

    @media (max-width: 576px) {
        #sd-list { padding-bottom: 5rem; } /* clear the FAB */
    }
</style>
@endpush

@section('content')
<div class="page-enter" x-data="securityDeposits()">

    <div class="he-page-head mb-4 stagger-1">
        <div>
            <h1 class="he-page-title">{{ __('Security Deposits') }}</h1>
            <p class="he-page-sub">{{ __('Money held in trust — every rupee returned or accounted for.') }}</p>
        </div>
        <button type="button" class="btn btn-premium rounded-pill px-4 fw-semibold shadow-sm tactile-btn d-none d-md-inline-flex align-items-center"
                @click="openRecord()">
            <i class="fa-solid fa-plus me-2"></i>{{ __('Record Deposit') }}
        </button>
    </div>

    {{-- Whole-book totals (owner decision: deposits are off-books everywhere
         else, so THIS page is where the custody truth lives). Not fragment-
         swapped: a search must never shrink these numbers. --}}
    <div class="he-stats mb-4 stagger-2">
        <div class="he-stats__grid" style="--he-stats-cols: 3;">
            <div class="he-stat he-stat--hero">
                <div class="he-stat__head">
                    <div class="he-stat__icon" style="background: rgba(255, 255, 255, 0.15); color: #6ee7b7;"><i class="fa-solid fa-shield-halved"></i></div>
                    <div class="he-stat__label">{{ __('Held in Trust') }} <span class="opacity-50">· {{ $totals['held_count'] }}</span></div>
                </div>
                <div class="he-stat__value">{{ hostelease_money($totals['held']) }}</div>
            </div>
            <div class="he-stat">
                <div class="he-stat__head">
                    <div class="he-stat__icon" style="background: var(--he-success-soft, rgba(34,197,94,0.12)); color: var(--he-success, #16a34a);"><i class="fa-solid fa-hand-holding-dollar"></i></div>
                    <div class="he-stat__label">{{ __('Refunded') }}</div>
                </div>
                <div class="he-stat__value text-success">{{ hostelease_money($totals['refunded']) }}</div>
            </div>
            <div class="he-stat">
                <div class="he-stat__head">
                    <div class="he-stat__icon" style="background: var(--he-danger-soft); color: var(--he-danger);"><i class="fa-solid fa-scissors"></i></div>
                    <div class="he-stat__label">{{ __('Deducted from Dues') }}</div>
                </div>
                <div class="he-stat__value text-danger">{{ hostelease_money($totals['deducted']) }}</div>
            </div>
        </div>
    </div>

    {{-- Filter bar — ONE row at every width (§4.5), fragment-driven (§4.3). --}}
    <div class="mb-4 sd-filter-row stagger-3">
        <form method="GET" action="{{ route('admin.security-deposits.index') }}" x-ref="filterForm"
              data-fragment="#sd-list, #sd-filter-aux"
              class="d-flex flex-nowrap gap-2 align-items-center">
            <div class="he-search he-search--inline he-search--clearable">
                <span class="he-search__icon"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="text" name="search" x-model="searchTerm" class="he-search__input"
                       placeholder="{{ __('Search by student, mobile, or receipt...') }}"
                       @input.debounce.450ms="$el.form.requestSubmit()">
                <button type="button" class="he-search__clear" x-show="searchTerm" x-cloak
                        @click="clearSearch()" title="{{ __('Clear search') }}">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <span id="sd-filter-aux">
                <x-he-select name="status" icon="filter" icon-only-mobile :selected="$status ?? ''"
                    :options="[
                        '' => ['label' => __('All Deposits'), 'icon' => 'filter'],
                        'collected' => ['label' => __('Held'), 'icon' => 'shield-halved'],
                        'refunded' => ['label' => __('Refunded'), 'icon' => 'hand-holding-dollar'],
                    ]" />
            </span>
        </form>
    </div>

    <div id="sd-list" data-fragment-container class="he-adaptive">
        @include('admin.security_deposits._list')
    </div>

    <template x-teleport="body">
        <button type="button" class="fab" @click="openRecord()" title="{{ __('Record Deposit') }}">
            <i class="fa-solid fa-plus"></i>
        </button>
    </template>

    {{-- ══ Record Deposit ══ --}}
    <template x-teleport="body">
        <div class="custom-overlay-backdrop" x-show="recordOpen" x-transition.opacity @click="close()" x-cloak style="display: none;">
            {{-- he-picker-change is the component's bridge out to this scope
                 (a hidden input's x-model fires no native event), which is how
                 the duplicate warning knows who was picked. --}}
            <form method="POST" action="{{ route('admin.security-deposits.store') }}" data-ring-required
                  class="custom-overlay-modal" :class="{ 'is-open': recordOpen }" x-show="recordOpen" x-transition.opacity @click.stop
                  @he-picker-change="if ($event.detail.name === 'student_id') pickStudent($event.detail.value)" style="display: none;">
                @csrf
                <div class="custom-overlay-header">
                    <h5 class="fw-bold mb-0"><i class="fa-solid fa-shield-halved" style="color: var(--he-primary);"></i><span class="ms-1">{{ __('Record Deposit') }}</span></h5>
                    <button type="button" class="btn-close" @click="close()"></button>
                </div>
                <div class="custom-overlay-body">
                    {{-- x-he-picker, not x-he-select: the picker is the canonical
                         control for choosing an ENTITY from a long searchable list
                         (avatar + name + a secondary line). he-select is for a short
                         list of fixed VALUES — a status, a category. --}}
                    <div class="mb-4">
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Student') }} <span class="text-danger">*</span></label>
                        <x-he-picker name="student_id" placeholder="{{ __('Pick a student…') }}"
                            search-placeholder="{{ __('Search name or mobile…') }}"
                            :options="$students->map(fn ($s) => [
                                'id' => $s->id,
                                'name' => $s->name,
                                'sub' => hostelease_phone($s->mobile),
                                // Tagged in the list itself — the commonest way to
                                // double-charge is not knowing at choosing time.
                                'tag' => $s->held_count ? __('Held :amt', ['amt' => hostelease_money($s->held_total)]) : null,
                            ])->all()" />

                        {{-- A second deposit is legitimate (a top-up, a re-admission),
                             so this states the fact and gets out of the way — the
                             owner decides. --}}
                        <div class="sd-dupe-warn mt-2" x-show="held" x-cloak>
                            <i class="fa-solid fa-triangle-exclamation"></i>
                            <span x-text="held
                                ? held.name + ' {{ __('already holds') }} ' + held.label + ' {{ __('in deposits.') }} {{ __('Record another only if this is a top-up or a fresh admission.') }}'
                                : ''"></span>
                        </div>
                    </div>
                    <div class="row gx-3">
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Amount') }} <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted fw-bold">₹</span>
                                <input type="number" name="amount" class="form-control bg-light fw-bold text-dark" required min="1" step="0.01" placeholder="0.00">
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Collected On') }} <span class="text-danger">*</span></label>
                            <input type="date" name="collected_on" class="form-control bg-light" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Payment Mode') }} <span class="text-danger">*</span></label>
                        <x-he-select name="payment_mode_id" compact :submit="false"
                            :selected="$paymentModes->first()?->id"
                            :options="$paymentModes->mapWithKeys(fn ($m) => [$m->id => $m->name])->all()" />
                    </div>
                </div>
                <div class="custom-overlay-footer bg-light">
                    <button type="button" class="btn btn-white border fw-semibold rounded-pill px-4 tactile-btn" @click="close()">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-premium fw-semibold rounded-pill px-4 shadow-sm tactile-btn"><i class="fa-solid fa-check me-2"></i>{{ __('Record Deposit') }}</button>
                </div>
            </form>
        </div>
    </template>

    {{-- ══ Settle (refund) — every rupee accounted for ══ --}}
    <template x-teleport="body">
        <div class="custom-overlay-backdrop" x-show="refundOpen" x-transition.opacity @click="close()" x-cloak style="display: none;">
            <form method="POST" :action="r.action" class="custom-overlay-modal" :class="{ 'is-open': refundOpen }" x-show="refundOpen" x-transition.opacity @click.stop style="display: none;">
                @csrf
                <div class="custom-overlay-header">
                    <h5 class="fw-bold mb-0">
                        <i class="fa-solid fa-hand-holding-dollar text-success"></i>
                        <span class="ms-1">{{ __('Settle Deposit') }}</span>
                        <div class="fs-6 fw-normal text-muted mt-1"><span x-text="r.student"></span> · <span x-text="r.receipt"></span></div>
                    </h5>
                    <button type="button" class="btn-close" @click="close()"></button>
                </div>
                <div class="custom-overlay-body">
                    <div class="bg-light rounded-4 p-3 mb-4 d-flex justify-content-between align-items-center">
                        <span class="text-muted small fw-bold text-uppercase letter-spacing-1">{{ __('Deposit held') }}</span>
                        <span class="fs-4 fw-bold text-dark" style="font-feature-settings: 'tnum';" x-text="'₹' + fmt(r.amount)"></span>
                    </div>

                    {{-- Dues checklist: ticking a due ADDS its balance to the
                         deduction (they stack with any amount typed in by
                         hand); the refund follows so the meter stays green. --}}
                    <div class="mb-4" x-show="r.invoices.length > 0">
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Deduct pending dues') }}</label>
                        <div class="form-text small mb-2 mt-0">{{ __('Ticking a due adds it to the deducted amount.') }}</div>
                        <div class="d-flex flex-column gap-2">
                            <template x-for="inv in r.invoices" :key="inv.id">
                                <label class="sd-due-row m-0" :class="{ 'is-checked': checked.includes(inv.id) }">
                                    <input type="checkbox" class="form-check-input m-0 flex-shrink-0" :value="inv.id"
                                           :checked="checked.includes(inv.id)" @change="toggleDue(inv)">
                                    <span class="flex-grow-1 text-truncate small fw-semibold" x-text="inv.title"></span>
                                    <span class="fw-bold text-danger small text-nowrap" x-text="'₹' + fmt(inv.balance)"></span>
                                </label>
                            </template>
                        </div>
                        <template x-for="id in checked" :key="'h-' + id">
                            <input type="hidden" name="deduct_invoice_ids[]" :value="id">
                        </template>
                    </div>
                    <div class="form-text small mb-4" x-show="r.invoices.length === 0" x-cloak>
                        <i class="fa-solid fa-circle-check text-success me-1"></i>{{ __('No pending dues — the full deposit goes back to the student.') }}
                    </div>

                    <div class="row gx-3">
                        <div class="col-6 mb-3">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Deducted') }}</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted fw-bold">₹</span>
                                <input type="number" name="deducted_amount" x-model.number="deducted" class="form-control bg-light fw-bold" min="0" step="0.01"
                                       @input="refunded = round2(r.amount - (Number(deducted) || 0))">
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Refunded to student') }}</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted fw-bold">₹</span>
                                <input type="number" name="refunded_amount" x-model.number="refunded" class="form-control bg-light fw-bold" min="0" step="0.01">
                            </div>
                        </div>
                    </div>

                    {{-- The settlement meter: green means Σ == deposit, exactly.
                         Same rule the server enforces — this just says it early. --}}
                    <div class="sd-settle-meter mb-4" :class="settleGap === 0 ? 'is-ok' : 'is-off'">
                        <span x-show="settleGap === 0"><i class="fa-solid fa-circle-check me-1"></i>{{ __('Fully settled — every rupee accounted for') }}</span>
                        <span x-show="settleGap !== 0" x-cloak x-text="settleGap > 0
                            ? '₹' + fmt(settleGap) + ' {{ __('unaccounted — settlement is short of the deposit') }}'
                            : '₹' + fmt(-settleGap) + ' {{ __('over — settlement exceeds the deposit') }}'"></span>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1">
                            {{ __('Note') }}
                            {{-- Required only when money is kept against no due —
                                 that's the case the record can't otherwise explain. --}}
                            <span class="text-danger" x-show="retained > 0" x-cloak>*</span>
                        </label>
                        <input type="text" name="refund_note" x-model="note" class="form-control bg-light" maxlength="255"
                               :placeholder="retained > 0 ? '{{ __('Why is this amount being kept? e.g. room damage') }}' : '{{ __('Optional note') }}'">
                        <div class="form-text small text-danger fw-semibold" x-show="needsReason" x-cloak>
                            <i class="fa-solid fa-circle-exclamation me-1"></i>
                            <span x-text="'₹' + fmt(retained) + ' {{ __('settles no due — say what it is for.') }}'"></span>
                        </div>
                    </div>
                </div>
                <div class="custom-overlay-footer bg-light">
                    <button type="button" class="btn btn-white border fw-semibold rounded-pill px-4 tactile-btn" @click="close()">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-success fw-semibold rounded-pill px-4 shadow-sm tactile-btn"
                            :disabled="settleGap !== 0 || needsReason">
                        <i class="fa-solid fa-check me-2"></i>{{ __('Settle Deposit') }}
                    </button>
                </div>
            </form>
        </div>
    </template>

    {{-- ══ Edit (held deposits only) ══ --}}
    <template x-teleport="body">
        <div class="custom-overlay-backdrop" x-show="editOpen" x-transition.opacity @click="close()" x-cloak style="display: none;">
            <form method="POST" :action="e.action" data-ring-required
                  class="custom-overlay-modal" :class="{ 'is-open': editOpen }" x-show="editOpen" x-transition.opacity @click.stop style="display: none;">
                @csrf @method('PATCH')
                <div class="custom-overlay-header">
                    <h5 class="fw-bold mb-0">
                        <i class="fa-solid fa-pen" style="color: var(--he-primary);"></i>
                        <span class="ms-1">{{ __('Edit Deposit') }}</span>
                        <div class="fs-6 fw-normal text-muted mt-1" x-text="e.student"></div>
                    </h5>
                    <button type="button" class="btn-close" @click="close()"></button>
                </div>
                <div class="custom-overlay-body">
                    <div class="row gx-3">
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Amount') }} <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted fw-bold">₹</span>
                                <input type="number" name="amount" x-model.number="e.amount" class="form-control bg-light fw-bold" required min="1" step="0.01">
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Collected On') }} <span class="text-danger">*</span></label>
                            <input type="date" name="collected_on" x-model="e.collected_on" class="form-control bg-light" max="{{ now()->toDateString() }}" required>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Payment Mode') }} <span class="text-danger">*</span></label>
                        <x-he-select name="payment_mode_id" compact :submit="false" x-model="e.mode_id"
                            :options="$paymentModes->mapWithKeys(fn ($m) => [$m->id => $m->name])->all()" />
                    </div>
                </div>
                <div class="custom-overlay-footer bg-light">
                    <button type="button" class="btn btn-white border fw-semibold rounded-pill px-4 tactile-btn" @click="close()">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-premium fw-semibold rounded-pill px-4 shadow-sm tactile-btn"><i class="fa-solid fa-check me-2"></i>{{ __('Save Changes') }}</button>
                </div>
            </form>
        </div>
    </template>

</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('securityDeposits', () => ({
        searchTerm: @json($search ?? ''),

        recordOpen: false,
        // student_id => { name, label } for students already holding a
        // deposit. Only they are in the map, so a lookup miss IS "no warning".
        heldByStudent: {{ Illuminate\Support\Js::from($students->where('held_count', '>', 0)->mapWithKeys(fn ($s) => [
            (string) $s->id => ['name' => $s->name, 'label' => hostelease_money($s->held_total)],
        ])) }},
        held: null,

        // Settle sheet
        refundOpen: false,
        r: { action: '', student: '', receipt: '', amount: 0, invoices: [] },
        checked: [],
        deducted: 0,
        refunded: 0,
        note: '',

        // Edit sheet
        editOpen: false,
        e: { action: '', student: '', amount: 0, mode_id: '', collected_on: '' },

        fmt(v) { return Number(v || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
        round2(v) { return Math.round((Number(v) || 0) * 100) / 100; },

        // 0 means refunded + deducted == deposit, exactly (server rule).
        get settleGap() {
            return this.round2(this.r.amount - (Number(this.refunded) || 0) - (Number(this.deducted) || 0));
        },

        // The slice of the deduction that settles no due — kept for damages
        // etc. Money retained with no invoice behind it needs a written
        // reason, or the record never says why (server enforces the same).
        get duesTotal() {
            return this.round2(this.r.invoices
                .filter((i) => this.checked.includes(i.id))
                .reduce((t, i) => t + (Number(i.balance) || 0), 0));
        },
        get retained() {
            return this.round2(Math.max(0, (Number(this.deducted) || 0) - this.duesTotal));
        },
        get needsReason() { return this.retained > 0 && ! this.note.trim(); },

        clearSearch() {
            this.searchTerm = '';
            this.$nextTick(() => this.$refs.filterForm?.requestSubmit());
        },

        openRecord() {
            this.held = null;
            this.recordOpen = true;
            document.body.style.overflow = 'hidden';
        },

        pickStudent(id) {
            this.held = this.heldByStudent[String(id)] ?? null;
        },

        openRefund(payload) {
            this.r = payload;
            this.checked = [];
            this.deducted = 0;
            this.refunded = this.round2(payload.amount);
            this.note = '';
            this.refundOpen = true;
            document.body.style.overflow = 'hidden';
        },

        openEdit(payload) {
            this.e = payload;
            this.editOpen = true;
            document.body.style.overflow = 'hidden';
        },

        close() {
            this.recordOpen = this.refundOpen = this.editOpen = false;
            document.body.style.overflow = '';
        },

        // Ticking a due ADDS its balance to whatever's already in Deducted;
        // unticking takes it back off. So a manual ₹1,000 (damages) plus a
        // ₹116.66 due reads ₹1,116.66 — the two stack rather than one
        // overwriting the other. Refund follows so the meter stays green.
        toggleDue(inv) {
            const bal = Number(inv.balance) || 0;
            if (this.checked.includes(inv.id)) {
                this.checked = this.checked.filter((i) => i !== inv.id);
                this.deducted = this.round2(Math.max(0, (Number(this.deducted) || 0) - bal));
            } else {
                this.checked.push(inv.id);
                this.deducted = this.round2(Math.min((Number(this.deducted) || 0) + bal, this.r.amount));
            }
            this.refunded = this.round2(this.r.amount - this.deducted);
        },
    }));
});
</script>
@endpush
@endsection
