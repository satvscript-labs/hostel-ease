@extends('layouts.app')
@section('title', __('Pocket Money').' — '.$student->name)

@push('styles')
<style>
    /* Page-local layout only — W6.4 full redesign of the wallet page. */

    /* The wallet hero — the one sanctioned gradient accent on this page
       (same family as .he-stat--hero). */
    .pw-wallet {
        background: var(--he-gradient-mesh, linear-gradient(135deg, #1e1b4b, #312e81));
        border-radius: 1.25rem;
        color: #fff;
        padding: 1.75rem;
        position: relative;
        overflow: hidden;
        box-shadow: var(--he-shadow-lg);
        container-type: inline-size;
    }
    .pw-wallet::after {
        content: '';
        position: absolute; top: -40px; right: -40px;
        width: 140px; height: 140px; border-radius: 50%;
        background: rgba(255, 255, 255, 0.08);
        pointer-events: none;
    }
    .pw-wallet-balance {
        font-size: clamp(1.6rem, 8cqi, 2.4rem); /* fluid, never wraps (§4.10) */
        font-weight: 800;
        white-space: nowrap;
        font-feature-settings: 'tnum';
        line-height: 1.2;
    }

    /* Transaction rows. */
    .pwt-ic {
        width: 40px; height: 40px; border-radius: 12px; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.9rem;
    }
    .pwt-ic.is-deposit { background: var(--he-success-soft, rgba(34,197,94,0.12)); color: var(--he-success, #16a34a); }
    .pwt-ic.is-withdraw { background: var(--he-danger-soft); color: var(--he-danger); }
    .pwt-amount {
        text-align: right;
        font-weight: 700;
        font-feature-settings: 'tnum';
        white-space: nowrap; /* figures never wrap (§4.10) */
    }
</style>
@endpush

@section('content')
<div class="page-enter" x-data="pocketWallet()">

    <a href="{{ route('admin.pocket-money.index') }}" class="btn btn-sm btn-white rounded-pill px-3 mb-3 shadow-sm fw-semibold">
        <i class="fa-solid fa-arrow-left me-1"></i> {{ __('Pocket Money') }}
    </a>

    {{-- ══ Wallet hero + quick stats ══ --}}
    <div class="row g-3 mb-4 stagger-1">
        <div class="col-lg-6">
            <div class="pw-wallet h-100">
                <div class="d-flex align-items-center gap-3 position-relative" style="z-index: 1;">
                    <img src="{{ $student->photo_url ?: 'https://ui-avatars.com/api/?name='.urlencode($student->name).'&background=312e81&color=fff' }}"
                         class="rounded-circle flex-shrink-0" style="width: 52px; height: 52px; object-fit: cover; border: 2px solid rgba(255,255,255,0.3);" alt="">
                    <div style="min-width: 0;">
                        <a href="{{ route('admin.students.show', $student) }}" class="fw-bold text-white text-decoration-none d-block text-truncate fs-5">{{ $student->name }}</a>
                        <div class="small text-truncate" style="color: rgba(255,255,255,0.7);">
                            {{ hostelease_phone($student->mobile) }}
                            @if($student->status !== 'active') · <span class="text-warning fw-bold">{{ __('Left the hostel') }}</span>@endif
                        </div>
                    </div>
                </div>
                <div class="mt-3 position-relative" style="z-index: 1;">
                    <div class="small text-uppercase fw-bold mb-1" style="color: rgba(255,255,255,0.65); letter-spacing: 1px;">{{ __('Wallet Balance') }}</div>
                    <div class="pw-wallet-balance {{ $balance < 0 ? 'text-warning' : '' }}">{{ hostelease_money($balance) }}</div>
                    @if($balance < 0)
                        <div class="small mt-1" style="color: rgba(255,255,255,0.75);"><i class="fa-solid fa-hand-holding-hand me-1"></i>{{ __('Lent to the student — they owe this back.') }}</div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="he-stats h-100">
                <div class="he-stats__grid h-100" style="--he-stats-cols: 2;">
                    <div class="he-stat">
                        <div class="he-stat__head">
                            <div class="he-stat__icon" style="background: var(--he-success-soft, rgba(34,197,94,0.12)); color: var(--he-success, #16a34a);"><i class="fa-solid fa-arrow-down"></i></div>
                            <div class="he-stat__label">{{ __('Total Deposited') }}</div>
                        </div>
                        <div class="he-stat__value text-success">{{ hostelease_money($stats['deposited']) }}</div>
                    </div>
                    <div class="he-stat">
                        <div class="he-stat__head">
                            <div class="he-stat__icon" style="background: var(--he-danger-soft); color: var(--he-danger);"><i class="fa-solid fa-arrow-up"></i></div>
                            <div class="he-stat__label">{{ __('Total Withdrawn') }}</div>
                        </div>
                        <div class="he-stat__value text-danger">{{ hostelease_money($stats['withdrawn']) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ══ Quick actions ══ --}}
    <div class="d-flex gap-2 mb-4 stagger-2">
        <button type="button" class="btn btn-success rounded-pill fw-bold px-4" style="min-height: 44px;" @click="open('deposit')">
            <i class="fa-solid fa-plus me-1"></i>{{ __('Deposit') }}
        </button>
        <button type="button" class="btn btn-white border rounded-pill fw-bold px-4 text-danger" style="min-height: 44px;" @click="open('withdraw')">
            <i class="fa-solid fa-minus me-1"></i>{{ __('Withdraw') }}
        </button>
    </div>

    {{-- ══ Transactions (fragment container: pagination swaps in place, §4.3) ══ --}}
    <div id="pwt-list" data-fragment-container class="he-adaptive stagger-3">
        @include('admin.pocket_money._transactions')
    </div>

    {{-- ══ Record sheet — one form, two directions ══ --}}
    <template x-teleport="body">
        <div class="custom-overlay-backdrop" x-show="sheetOpen" x-transition.opacity @click="close()" x-cloak style="display: none;">
            <form method="POST" action="{{ route('admin.pocket-money.store', $student) }}" data-ring-required
                  class="custom-overlay-modal" :class="{ 'is-open': sheetOpen }" x-show="sheetOpen" x-transition.opacity @click.stop style="display: none;">
                @csrf
                <input type="hidden" name="type" :value="type">
                <div class="custom-overlay-header">
                    <h5 class="fw-bold mb-0">
                        <i class="fa-solid" :class="type === 'deposit' ? 'fa-plus text-success' : 'fa-minus text-danger'"></i>
                        <span class="ms-1" x-text="type === 'deposit' ? @js(__('Deposit Money')) : @js(__('Withdraw Money'))"></span>
                        <div class="fs-6 fw-normal text-muted mt-1">{{ $student->name }}</div>
                    </h5>
                    <button type="button" class="btn-close" @click="close()"></button>
                </div>
                <div class="custom-overlay-body">
                    <div class="mb-4">
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1 d-block mb-2">{{ __('Direction') }}</label>
                        <div class="chip-group">
                            <button type="button" class="chip" :class="{ active: type === 'deposit' }" @click="type = 'deposit'">{{ __('Deposit') }}</button>
                            <button type="button" class="chip" :class="{ active: type === 'withdraw' }" @click="type = 'withdraw'">{{ __('Withdraw') }}</button>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Amount') }} <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted fw-bold">₹</span>
                            <input type="number" name="amount" x-model.number="amount" class="form-control bg-light fw-bold text-dark" required min="1" step="0.01" placeholder="0.00">
                        </div>
                        {{-- Lending is allowed by design — but never silently. --}}
                        <div class="form-text small mt-2 text-danger fw-semibold" x-show="goesNegative" x-cloak>
                            <i class="fa-solid fa-hand-holding-hand me-1"></i>
                            <span x-text="@js(__('This lends the student')) + ' ₹' + fmt(Math.abs(balanceAfter)) + ' ' + @js(__('beyond their balance — the wallet goes negative.'))"></span>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Note') }}</label>
                        <input type="text" name="note" class="form-control bg-light" maxlength="255" placeholder="{{ __('e.g. Canteen expenses') }}">
                    </div>
                </div>
                <div class="custom-overlay-footer bg-light">
                    <button type="button" class="btn btn-white border fw-semibold rounded-pill px-4 tactile-btn" @click="close()">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn fw-semibold rounded-pill px-4 shadow-sm tactile-btn" :class="type === 'deposit' ? 'btn-success' : 'btn-danger'">
                        <i class="fa-solid fa-check me-2"></i>
                        <span x-text="type === 'deposit' ? @js(__('Record Deposit')) : @js(__('Record Withdrawal'))"></span>
                    </button>
                </div>
            </form>
        </div>
    </template>

</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('pocketWallet', () => ({
        sheetOpen: false,
        type: 'deposit',
        amount: '',
        balance: @json(round((float) $balance, 2)),

        get balanceAfter() {
            const a = Number(this.amount) || 0;
            return Math.round((this.type === 'deposit' ? this.balance + a : this.balance - a) * 100) / 100;
        },
        get goesNegative() { return this.type === 'withdraw' && this.balanceAfter < 0; },

        fmt(v) { return Number(v || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },

        open(type) {
            this.type = type;
            this.amount = '';
            this.sheetOpen = true;
            document.body.style.overflow = 'hidden';
        },
        close() {
            this.sheetOpen = false;
            document.body.style.overflow = '';
        },
    }));
});
</script>
@endpush
@endsection
