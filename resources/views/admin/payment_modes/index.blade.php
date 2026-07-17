@extends('layouts.app')
@section('title', __('Payment Modes'))

@push('styles')
<style>
    /* Page-local layout only — W6.4 full redesign. Modes are LOAD-BEARING:
       collections, expenses, salaries and deposits all validate against the
       active ones, so this page explains itself and the guards guard. */

    /* Card grid: auto-fill, floored — cards never crush (§4.10), the grid
       just reflows. */
    .pmx-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(min(100%, 300px), 1fr));
        gap: 1rem;
    }
    .pmx-card {
        background: var(--he-bg-surface);
        border: 1px solid rgba(0, 0, 0, 0.06);
        border-radius: 1.25rem;
        box-shadow: var(--he-shadow-sm);
        padding: 1.25rem;
        display: flex;
        flex-direction: column;
        gap: 0.85rem;
    }
    .pmx-card.is-inactive { opacity: 0.65; }
    .pmx-ic {
        width: 42px; height: 42px; border-radius: 12px; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        background: var(--he-primary-soft); color: var(--he-primary);
        font-size: 1rem;
    }
    .pmx-meta {
        display: flex; align-items: center; gap: 0.4rem; flex-wrap: wrap;
    }
    .pmx-usage {
        display: inline-flex; align-items: center; gap: 0.35rem;
        padding: 0.25rem 0.65rem;
        background: var(--he-bg-canvas);
        border-radius: var(--he-radius-full);
        font-size: 0.72rem; font-weight: 700; color: var(--he-text-muted);
        font-feature-settings: 'tnum';
    }
</style>
@endpush

@section('content')
<div class="page-enter" x-data="paymentModes()">

    <div class="he-page-head mb-4 stagger-1">
        <div>
            <h1 class="he-page-title">{{ __('Payment Modes') }}</h1>
            <p class="he-page-sub">{{ __('How money moves — these modes power collections, expenses, salaries and deposits.') }}</p>
        </div>
        <button type="button" class="btn btn-premium rounded-pill px-4 fw-semibold shadow-sm tactile-btn d-none d-md-inline-flex align-items-center"
                @click="openAdd()">
            <i class="fa-solid fa-plus me-2"></i>{{ __('Add Mode') }}
        </button>
    </div>

    <div class="pmx-grid stagger-2">
        @foreach($modes as $m)
            @php
                $used = $usage[$m->id] ?? 0;
                $editPayload = \Illuminate\Support\Js::from([
                    'action' => route('admin.payment-modes.update', $m),
                    'name' => $m->name,
                    'req' => (bool) $m->requires_reference,
                    'active' => (bool) $m->is_active,
                    'used' => $used,
                ]);
            @endphp
            <div class="pmx-card {{ $m->is_active ? '' : 'is-inactive' }}">
                <div class="d-flex align-items-center gap-3">
                    <div class="pmx-ic"><i class="fa-solid fa-money-bill-wave"></i></div>
                    <div class="flex-grow-1" style="min-width: 0;">
                        <div class="fw-bold text-dark text-truncate fs-6">{{ $m->name }}</div>
                        <div class="text-muted small text-truncate">{{ $m->code }}</div>
                    </div>
                    <span class="badge rounded-pill px-2 py-1 flex-shrink-0 {{ $m->is_active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">
                        {{ $m->is_active ? __('Active') : __('Inactive') }}
                    </span>
                </div>

                <div class="pmx-meta">
                    <span class="pmx-usage" title="{{ __('Payments, expenses, salaries and deposits recorded with this mode') }}">
                        <i class="fa-solid fa-receipt"></i>
                        {{ $used > 0 ? __(':n record(s)', ['n' => number_format($used)]) : __('Never used') }}
                    </span>
                    @if($m->requires_reference)
                        <span class="pmx-usage" style="color: var(--he-warning, #b45309); background: var(--he-warning-soft, rgba(245,158,11,0.12));">
                            <i class="fa-solid fa-hashtag"></i>{{ __('Needs reference no.') }}
                        </span>
                    @endif
                </div>

                <div class="he-act-row mt-auto">
                    <button type="button" class="btn btn-sm btn-white border rounded-pill fw-bold px-3" style="min-height: 36px;" @click="openEdit({{ $editPayload }})">
                        <i class="fa-solid fa-pen me-1"></i>{{ __('Edit') }}
                    </button>
                    <div class="he-act-right">
                        @if($used === 0)
                            <form action="{{ route('admin.payment-modes.destroy', $m) }}" method="POST" class="m-0"
                                  data-confirm="{{ __('Delete payment mode “:name”? It has never been used, so nothing references it.', ['name' => $m->name]) }}">
                                @csrf @method('DELETE')
                                <button class="he-icon-btn is-danger" title="{{ __('Delete') }}" aria-label="{{ __('Delete') }}"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        @else
                            {{-- History keeps its labels (owner decision): a used
                                 mode deactivates via Edit, it never deletes. --}}
                            <span class="he-icon-btn opacity-50" title="{{ __('In use on :n record(s) — deactivate it instead; history keeps its label.', ['n' => number_format($used)]) }}">
                                <i class="fa-solid fa-lock"></i>
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <template x-teleport="body">
        <button type="button" class="fab" @click="openAdd()" title="{{ __('Add Mode') }}">
            <i class="fa-solid fa-plus"></i>
        </button>
    </template>

    {{-- ══ Add sheet ══ --}}
    <template x-teleport="body">
        <div class="custom-overlay-backdrop" x-show="addOpen" x-transition.opacity @click="close()" x-cloak style="display: none;">
            <form method="POST" action="{{ route('admin.payment-modes.store') }}" data-ring-required
                  class="custom-overlay-modal" :class="{ 'is-open': addOpen }" x-show="addOpen" x-transition.opacity @click.stop style="display: none; max-width: 460px;">
                @csrf
                <div class="custom-overlay-header">
                    <h5 class="fw-bold mb-0"><i class="fa-solid fa-money-bill-wave" style="color: var(--he-primary);"></i><span class="ms-1">{{ __('Add Payment Mode') }}</span></h5>
                    <button type="button" class="btn-close" @click="close()"></button>
                </div>
                <div class="custom-overlay-body">
                    <div class="mb-4">
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Name') }} <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control bg-light" required maxlength="60" placeholder="{{ __('e.g. PhonePe, Bank Transfer') }}">
                        <div class="form-text small">{{ __('Available immediately in collections, expenses, salaries and deposits.') }}</div>
                    </div>
                    <label class="d-flex align-items-center gap-2 mb-2">
                        <input type="checkbox" name="requires_reference" value="1" class="form-check-input m-0">
                        <span class="fw-semibold small">{{ __('Requires a reference / transaction number') }}</span>
                    </label>
                </div>
                <div class="custom-overlay-footer bg-light">
                    <button type="button" class="btn btn-white border fw-semibold rounded-pill px-4 tactile-btn" @click="close()">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-premium fw-semibold rounded-pill px-4 shadow-sm tactile-btn"><i class="fa-solid fa-check me-2"></i>{{ __('Add Mode') }}</button>
                </div>
            </form>
        </div>
    </template>

    {{-- ══ Edit sheet ══ --}}
    <template x-teleport="body">
        <div class="custom-overlay-backdrop" x-show="editOpen" x-transition.opacity @click="close()" x-cloak style="display: none;">
            <form method="POST" :action="e.action" data-ring-required
                  class="custom-overlay-modal" :class="{ 'is-open': editOpen }" x-show="editOpen" x-transition.opacity @click.stop style="display: none; max-width: 460px;">
                @csrf @method('PUT')
                <div class="custom-overlay-header">
                    <h5 class="fw-bold mb-0"><i class="fa-solid fa-pen" style="color: var(--he-primary);"></i><span class="ms-1">{{ __('Edit Payment Mode') }}</span></h5>
                    <button type="button" class="btn-close" @click="close()"></button>
                </div>
                <div class="custom-overlay-body">
                    <div class="mb-4">
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Name') }} <span class="text-danger">*</span></label>
                        <input type="text" name="name" x-model="e.name" class="form-control bg-light" required maxlength="60">
                    </div>
                    <label class="d-flex align-items-center gap-2 mb-3">
                        <input type="checkbox" name="requires_reference" value="1" class="form-check-input m-0" :checked="e.req">
                        <span class="fw-semibold small">{{ __('Requires a reference / transaction number') }}</span>
                    </label>
                    <label class="d-flex align-items-center gap-2 mb-2">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input m-0" :checked="e.active">
                        <span class="fw-semibold small">{{ __('Active — offered on every money form') }}</span>
                    </label>
                    <div class="form-text small mt-2" x-show="e.used > 0" x-cloak>
                        <i class="fa-solid fa-circle-info me-1"></i>
                        <span x-text="e.used.toLocaleString('en-IN') + ' {{ __('recorded transaction(s) keep this label either way.') }}'"></span>
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
    Alpine.data('paymentModes', () => ({
        addOpen: false,
        editOpen: false,
        e: { action: '', name: '', req: false, active: true, used: 0 },

        openAdd() {
            this.addOpen = true;
            document.body.style.overflow = 'hidden';
        },
        openEdit(payload) {
            this.e = payload;
            this.editOpen = true;
            document.body.style.overflow = 'hidden';
        },
        close() {
            this.addOpen = this.editOpen = false;
            document.body.style.overflow = '';
        },
    }));
});
</script>
@endpush
@endsection
