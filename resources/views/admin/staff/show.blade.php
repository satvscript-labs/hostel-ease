@extends('layouts.app')
@section('title', $staff->name)

@push('styles')
<style>
    /* Page-local layout only — W7.1 full rebuild on the canonical system. The
       old page was pre-design-system: a gradient-mesh hero, four screen-filling
       stacked count cards on phones, and a salary timeline whose delete had a
       raw confirm() on it. */

    .st-id-card { display: flex; align-items: center; gap: 1rem; min-width: 0; }

    /* Attendance counts — compact row-panel when the container is narrow,
       four across when there's room (§4.9). Never four stacked hero cards. */
    .st-counts { display: grid; gap: 0.75rem; grid-template-columns: repeat(2, minmax(0, 1fr)); }
    @container (min-width: 520px) { .st-counts { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
    .st-count {
        background: var(--he-bg-surface);
        border: 1px solid rgba(0, 0, 0, 0.05);
        border-radius: var(--he-radius-lg);
        padding: 0.85rem;
        text-align: center;
    }
    .st-count__value {
        font-size: clamp(1.4rem, 7cqi, 1.9rem);
        font-weight: 800;
        line-height: 1.1;
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
    }
    .st-count__label {
        font-size: 0.62rem; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.06em; margin-top: 0.15rem;
    }

    /* Salary history rows — same container tiering as every other list. */
    .st-pay-row {
        align-items: center;
        gap: 0.75rem 1rem;
        grid-template-columns: minmax(0, 1fr) auto;
        grid-template-areas:
            "info  acts"
            "money acts";
    }
    .st-pay-info { grid-area: info; display: flex; align-items: center; gap: 0.85rem; min-width: 0; }
    .st-pay-money { grid-area: money; display: flex; justify-content: flex-end; align-items: center; gap: 1rem; }
    .st-pay-acts {
        grid-area: acts;
        display: flex; align-items: center; justify-content: flex-end;
        padding-left: 1rem;
        border-left: 1px solid rgba(0, 0, 0, 0.06);
        align-self: stretch;
    }
    @container (min-width: 560px) {
        .st-pay-row {
            grid-template-columns: minmax(180px, 1fr) auto auto;
            grid-template-areas: "info money acts";
        }
        .st-pay-acts { align-self: center; }
    }
    .st-pay-amt { font-weight: 700; white-space: nowrap; font-variant-numeric: tabular-nums; }

    .st-icon-dot {
        width: 34px; height: 34px; flex-shrink: 0;
        display: inline-flex; align-items: center; justify-content: center;
        border-radius: 10px;
        background: var(--he-success-soft); color: var(--he-success);
        font-size: 0.8rem;
    }

    .st-fact { padding: 0.7rem 0; border-bottom: 1px solid rgba(0, 0, 0, 0.05); }
    .st-fact:last-child { border-bottom: 0; }
    .st-fact__label {
        font-size: 0.62rem; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.06em; color: var(--he-text-muted); margin-bottom: 0.1rem;
    }

    /* Removed staff: the record stays readable, but every write is gone. */
    .st-removed-banner {
        display: flex; align-items: flex-start; gap: 0.6rem;
        padding: 0.8rem 1rem;
        background: var(--he-danger-soft); color: var(--he-danger);
        border-radius: var(--he-radius-md);
        font-size: 0.85rem; font-weight: 600;
    }
</style>
@endpush

@section('content')
<div class="page-enter" x-data="staffProfile()">

    <div class="he-page-head mb-4 stagger-1">
        <div>
            <h1 class="he-page-title">{{ $staff->name }}</h1>
            <p class="he-page-sub">{{ $staff->designation ?: __('Staff Member') }}</p>
        </div>
        <a href="{{ route('admin.staff.index') }}" class="btn btn-white border rounded-pill px-4 fw-semibold shadow-sm tactile-btn d-none d-md-inline-flex align-items-center">
            <i class="fa-solid fa-arrow-left me-2"></i>{{ __('Directory') }}
        </a>
    </div>

    @if($staff->trashed())
        {{-- Owner decision (W7.1): removing a staff member keeps their salary
             history and its expense mirrors — money that left is money that
             left. The record stays readable here (and this is the only place a
             mirrored expense can still be deleted from). --}}
        <div class="st-removed-banner mb-4 stagger-2">
            <i class="fa-solid fa-user-slash mt-1"></i>
            <div>
                <div>{{ __('This staff member has been removed from the directory.') }}</div>
                <div class="fw-normal">{{ __('Their salary history below stays on the books. Restore them to make changes.') }}</div>
            </div>
            <form method="POST" action="{{ route('admin.staff.restore', $staff->id) }}" class="ms-auto">
                @csrf
                <button class="btn btn-sm btn-white border rounded-pill fw-bold px-3 text-nowrap tactile-btn">
                    <i class="fa-solid fa-rotate-left me-1"></i>{{ __('Restore') }}
                </button>
            </form>
        </div>
    @endif

    <div class="row g-4">
        {{-- ══ Left: identity ══ --}}
        <div class="col-lg-4 stagger-2">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <div class="st-id-card mb-4">
                        <x-staff-avatar :staff="$staff" size="64" />
                        <div style="min-width: 0;">
                            <div class="fw-bold text-dark fs-5 text-truncate">{{ $staff->name }}</div>
                            <div>
                                @if($staff->trashed())
                                    <span class="badge bg-danger-subtle text-danger rounded-pill px-2 py-1">{{ __('Removed') }}</span>
                                @elseif($staff->is_active)
                                    <span class="badge bg-success-subtle text-success rounded-pill px-2 py-1">{{ __('Active') }}</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary rounded-pill px-2 py-1">{{ __('Inactive') }}</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="st-fact">
                        <div class="st-fact__label">{{ __('Mobile') }}</div>
                        <div class="fw-semibold text-dark">
                            @if($staff->mobile)<x-mobile-link :mobile="$staff->mobile" />@else<span class="text-muted">—</span>@endif
                        </div>
                    </div>
                    <div class="st-fact">
                        <div class="st-fact__label">{{ __('Monthly Salary') }}</div>
                        <div class="fw-bold text-dark" style="font-variant-numeric: tabular-nums;">{{ hostelease_money($staff->monthly_salary) }}</div>
                    </div>
                    <div class="st-fact">
                        <div class="st-fact__label">{{ __('Join Date') }}</div>
                        <div class="fw-semibold text-dark">{{ $staff->join_date ? $staff->join_date->format('d M Y') : __('Not specified') }}</div>
                    </div>
                    <div class="st-fact">
                        <div class="st-fact__label">{{ __('Address') }}</div>
                        <div class="text-dark">{{ $staff->address ?: __('Not specified') }}</div>
                    </div>
                    <div class="st-fact">
                        <div class="st-fact__label">{{ __('Aadhaar') }}</div>
                        <div class="fw-semibold text-dark d-flex align-items-center gap-2 flex-wrap">
                            <span style="font-variant-numeric: tabular-nums;">{{ $staff->aadhaar_number ?: '—' }}</span>
                            @if($staff->aadhaar_file)
                                <a href="{{ Storage::disk('public')->url($staff->aadhaar_file) }}" target="_blank" rel="noopener"
                                   class="badge bg-primary-subtle text-primary rounded-pill px-2 py-1 text-decoration-none">
                                    <i class="fa-solid fa-file-image me-1"></i>{{ __('View card') }}
                                </a>
                            @endif
                        </div>
                    </div>

                    @unless($staff->trashed())
                        <div class="d-flex gap-2 mt-4">
                            <button type="button" class="btn btn-premium rounded-pill fw-semibold flex-grow-1 shadow-sm tactile-btn" @click="editOpen = true; document.body.style.overflow = 'hidden'">
                                <i class="fa-solid fa-user-pen me-2"></i>{{ __('Edit') }}
                            </button>
                            <form method="POST" action="{{ route('admin.staff.destroy', $staff) }}" class="m-0"
                                  data-confirm="{{ __('Remove :name from the directory? Their salary history and its expense entries stay on the books.', ['name' => $staff->name]) }}">
                                @csrf @method('DELETE')
                                <button class="he-icon-btn he-icon-btn--lg is-danger" title="{{ __('Remove staff') }}" aria-label="{{ __('Remove staff') }}">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    @endunless
                </div>
            </div>
        </div>

        {{-- ══ Right: attendance + payroll ══ --}}
        <div class="col-lg-8 stagger-3 he-adaptive">
            <div class="d-flex align-items-center gap-2 mb-3">
                <div class="st-icon-dot" style="background: var(--he-primary-soft); color: var(--he-primary);"><i class="fa-solid fa-chart-pie"></i></div>
                <h5 class="fw-bold text-dark mb-0">{{ __('Attendance') }} <span class="text-muted fw-normal fs-6">· {{ now()->format('F Y') }}</span></h5>
            </div>

            <div class="st-counts mb-4">
                @foreach([
                    ['present', __('Present'), 'success'],
                    ['absent', __('Absent'), 'danger'],
                    ['half_day', __('Half Day'), 'warning'],
                    ['leave', __('Leave'), 'secondary'],
                ] as [$key, $label, $color])
                    <div class="st-count">
                        <div class="st-count__value text-{{ $color }}">{{ $counts[$key] }}</div>
                        <div class="st-count__label text-{{ $color }}">{{ $label }}</div>
                    </div>
                @endforeach
            </div>

            <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
                <div class="d-flex align-items-center gap-2" style="min-width: 0;">
                    <div class="st-icon-dot"><i class="fa-solid fa-money-check-dollar"></i></div>
                    <h5 class="fw-bold text-dark mb-0 text-truncate">{{ __('Salary History') }}</h5>
                </div>
                @unless($staff->trashed())
                    <button type="button" class="btn btn-sm btn-success rounded-pill fw-bold px-3 text-nowrap shadow-sm tactile-btn"
                            @click="openPay({{ \Illuminate\Support\Js::from([
                                'action' => route('admin.staff.salary', $staff),
                                'name' => $staff->name,
                                'salary' => (float) $staff->monthly_salary,
                            ]) }})">
                        <i class="fa-solid fa-plus me-1"></i>{{ __('Pay Salary') }}
                    </button>
                @endunless
            </div>

            <div class="d-flex flex-column gap-2">
                @forelse($payments as $p)
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-body p-3">
                            <div class="st-pay-row d-grid">
                                <div class="st-pay-info">
                                    <div class="st-icon-dot"><i class="fa-solid fa-money-bill-wave"></i></div>
                                    <div style="min-width: 0;">
                                        <div class="fw-bold text-dark text-truncate">{{ $p->salary_month->format('F Y') }}</div>
                                        <div class="text-muted small text-truncate">
                                            {{ __('Paid') }} {{ $p->paid_on->format('d M Y') }} · {{ $modeNames[$p->mode] ?? ucfirst($p->mode) }}
                                            @if($p->reference_number) · {{ $p->reference_number }} @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="st-pay-money">
                                    <span class="st-pay-amt text-success fs-5">{{ hostelease_money($p->amount) }}</span>
                                </div>

                                <div class="st-pay-acts">
                                    {{-- Deleting the salary takes its expense mirror
                                         with it (W6.2). Reachable even for a removed
                                         staff member — otherwise the mirror would be
                                         un-deletable from both sides. --}}
                                    <form method="POST" action="{{ route('admin.staff.salary.destroy', [$staff->id, $p->id]) }}" class="m-0"
                                          data-confirm="{{ __('Delete this salary entry? Its matching expense entry is removed too.') }}">
                                        @csrf @method('DELETE')
                                        <button class="he-icon-btn is-danger" title="{{ __('Delete salary entry') }}" aria-label="{{ __('Delete salary entry') }}">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                            @if($p->notes)
                                <div class="text-muted small mt-2 ps-1 text-truncate">{{ $p->notes }}</div>
                            @endif
                        </div>
                    </div>
                @empty
                    <x-he-empty-state icon="file-invoice-dollar" title="{{ __('No salary paid yet') }}"
                        subtitle="{{ __('Recorded salary payments appear here and in Expenses.') }}" />
                @endforelse
            </div>
        </div>
    </div>

    {{-- ══ Edit Staff ══ --}}
    @unless($staff->trashed())
    <template x-teleport="body">
        <div class="custom-overlay-backdrop" x-show="editOpen" x-transition.opacity @click="close()" x-cloak style="display: none;">
            <form method="POST" action="{{ route('admin.staff.update', $staff) }}" enctype="multipart/form-data" data-ring-required
                  class="custom-overlay-modal" :class="{ 'is-open': editOpen }" x-show="editOpen" x-transition.opacity @click.stop style="display: none;">
                @csrf @method('PUT')
                <div class="custom-overlay-header">
                    <h5 class="fw-bold mb-0"><i class="fa-solid fa-user-pen" style="color: var(--he-primary);"></i><span class="ms-1">{{ __('Edit Staff') }}</span></h5>
                    <button type="button" class="btn-close" @click="close()"></button>
                </div>
                <div class="custom-overlay-body">
                    <div class="mb-4">
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Name') }} <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control bg-light" required maxlength="150" value="{{ old('name', $staff->name) }}">
                    </div>
                    <div class="row gx-3">
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Designation') }}</label>
                            <input type="text" name="designation" class="form-control bg-light" maxlength="100" value="{{ old('designation', $staff->designation) }}">
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Mobile') }} <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted fw-bold">+91</span>
                                <input type="tel" name="mobile" class="form-control bg-light" required inputmode="numeric" maxlength="10" pattern="\d{10}"
                                       value="{{ old('mobile', substr(preg_replace('/\D+/', '', (string) $staff->mobile), -10)) }}">
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Monthly Salary') }} <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted fw-bold">₹</span>
                                <input type="number" name="monthly_salary" class="form-control bg-light fw-bold" required min="0" step="0.01" value="{{ old('monthly_salary', $staff->monthly_salary) }}">
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Join Date') }}</label>
                            <input type="date" name="join_date" class="form-control bg-light" max="{{ now()->toDateString() }}" value="{{ old('join_date', optional($staff->join_date)->format('Y-m-d')) }}">
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Aadhaar Number') }} <span class="text-danger">*</span></label>
                            <input type="text" name="aadhaar_number" class="form-control bg-light" required inputmode="numeric" maxlength="12" pattern="\d{12}" value="{{ old('aadhaar_number', $staff->aadhaar_number) }}">
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Aadhaar Card') }}</label>
                            <input type="file" name="aadhaar_file" class="form-control bg-light" accept="image/*">
                            @if($staff->aadhaar_file)
                                <div class="form-text small">{{ __('Leave empty to keep the current file.') }}</div>
                            @endif
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Photo') }}</label>
                            <input type="file" name="photo" class="form-control bg-light" accept="image/*">
                            @if($staff->photo)
                                <div class="form-text small">{{ __('Leave empty to keep the current photo.') }}</div>
                            @endif
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Address') }}</label>
                            <input type="text" name="address" class="form-control bg-light" maxlength="255" value="{{ old('address', $staff->address) }}">
                        </div>
                    </div>
                    <label class="d-flex align-items-center justify-content-between gap-3 bg-light rounded-4 p-3 m-0">
                        <span>
                            <span class="fw-bold d-block">{{ __('Currently working') }}</span>
                            <span class="small text-muted">{{ __('Inactive staff stay on file but leave the attendance roster.') }}</span>
                        </span>
                        <span class="form-check form-switch fs-4 m-0">
                            <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" @checked(old('is_active', $staff->is_active))>
                        </span>
                    </label>
                </div>
                <div class="custom-overlay-footer bg-light">
                    <button type="button" class="btn btn-white border fw-semibold rounded-pill px-4 tactile-btn" @click="close()">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-premium fw-semibold rounded-pill px-4 shadow-sm tactile-btn"><i class="fa-solid fa-check me-2"></i>{{ __('Save Changes') }}</button>
                </div>
            </form>
        </div>
    </template>

    {{-- ══ Pay Salary — the SAME sheet the Board uses ══ --}}
    @include('admin.staff._pay_sheet', ['paymentModes' => $paymentModes])
    @endunless

</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('staffProfile', () => ({
        editOpen: false,
        payOpen: false,
        p: { action: '', name: '', salary: 0, amount: 0 },

        openPay(payload) {
            this.p = { ...payload, amount: payload.salary };
            this.payOpen = true;
            document.body.style.overflow = 'hidden';
        },

        close() {
            this.editOpen = this.payOpen = false;
            document.body.style.overflow = '';
        },
    }));
});
</script>
@endpush
@endsection
