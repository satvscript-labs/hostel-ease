@extends('layouts.app')
@section('title', $staff->name)

@push('styles')
<style>
    /* ══ Staff Profile — Account-360 design language ══
       Matches the student profile: dark mesh hero with inline metrics, canonical
       panel-cards, dashed info rows. The W7.1 rebuild put this page on the
       design system but kept a plain two-column card layout, so it read a tier
       below the student profile it sits beside. */

    /* The hero must NOT clip: the ⋯ menu opens past its bottom edge. The glow
       lives in its own clipped layer instead (same fix as the student page). */
    .st-hero {
        background: var(--he-gradient-mesh);
        color: #fff;
        border-radius: var(--he-radius-lg);
        position: relative;
    }
    .st-hero-bg { position: absolute; inset: 0; z-index: 0; border-radius: inherit; overflow: hidden; pointer-events: none; }
    .st-hero-bg::after {
        content: '';
        position: absolute;
        top: -40%; right: -8%;
        width: 380px; height: 380px;
        background: radial-gradient(circle, rgba(147, 51, 234, 0.35), transparent 70%);
    }
    .st-hero .dropdown-menu { z-index: 1080; }
    .st-hero-avatar {
        width: 76px; height: 76px; border-radius: 50%; object-fit: cover; flex-shrink: 0;
        border: 3px solid rgba(255, 255, 255, 0.25);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.25);
    }
    .st-hero-meta { color: rgba(255, 255, 255, 0.6); font-size: 0.85rem; }
    .st-metric-label { color: rgba(255, 255, 255, 0.55); font-size: 0.66rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
    .st-metric-val {
        font-size: 1.3rem; font-weight: 800; line-height: 1.15;
        font-variant-numeric: tabular-nums; white-space: nowrap;
    }

    /* Dashed identity rows — same as the student profile's .info-row. */
    .st-info {
        display: flex; align-items: center; justify-content: space-between; gap: 1rem;
        padding: 0.7rem 0;
        border-bottom: 1px dashed rgba(0, 0, 0, 0.08);
    }
    .st-info:last-child { border-bottom: none; }
    .st-info .lbl { color: var(--he-text-muted); font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px; white-space: nowrap; }
    .st-info .val { font-weight: 700; color: var(--he-text-main); text-align: right; min-width: 0; }

    /* Attendance counts — four across when there's room, two when there isn't
       (§4.9: measured against the CONTAINER, never the viewport). */
    .st-counts { display: grid; gap: 0.6rem; grid-template-columns: repeat(2, minmax(0, 1fr)); }
    @container (min-width: 460px) { .st-counts { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
    .st-count {
        border: 1px solid rgba(0, 0, 0, 0.05);
        border-radius: var(--he-radius-md);
        padding: 0.7rem 0.5rem;
        text-align: center;
    }
    .st-count__value {
        font-size: clamp(1.25rem, 6cqi, 1.7rem);
        font-weight: 800; line-height: 1.1;
        white-space: nowrap; font-variant-numeric: tabular-nums;
    }
    .st-count__label {
        font-size: 0.6rem; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.06em; margin-top: 0.1rem;
    }

    /* Salary history rows */
    .st-pay {
        display: flex; align-items: center; gap: 0.85rem;
        padding: 0.8rem 1.25rem;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        transition: background 0.2s var(--ease-out-expo);
    }
    .st-pay:last-child { border-bottom: none; }
    .st-pay:hover { background: var(--he-bg-surface-raised); }
    .st-pay__ic {
        width: 40px; height: 40px; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        border-radius: var(--he-radius-md);
        background: var(--he-success-soft); color: var(--he-success);
    }
    .st-pay__amt { font-weight: 800; white-space: nowrap; font-variant-numeric: tabular-nums; }

    .st-removed {
        display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap;
        padding: 0.75rem 1rem;
        background: var(--he-danger-soft); color: var(--he-danger);
        border-radius: var(--he-radius-md);
        font-size: 0.85rem; font-weight: 600;
    }

    /* ── Mobile: rearrange for a phone ─────────────────────────── */
    @media (max-width: 576px) {
        .st-hero { border-radius: var(--he-radius-md); }
        .st-hero-top { flex-direction: column; align-items: stretch !important; }
        .st-hero-avatar { width: 60px; height: 60px; }
        .st-hero h1 { font-size: 1.4rem; }
        .st-hero-actions { width: 100%; }
        .st-hero-actions .btn:not(.st-more-btn) { flex: 1; }
        .st-metric-val { font-size: 1.05rem; }
        .panel-head, .panel-body, .st-pay { padding-left: 1rem; padding-right: 1rem; }
    }
</style>
@endpush

@section('content')
<div class="page-enter" x-data="staffProfile()" @keydown.window.escape="close()">

    {{-- Back. NOT d-none d-md-* — that rule is for the page-head's one primary
         ACTION (which the FAB replaces on phones). A back link is navigation:
         hiding it below md left the phone with no way out of this page but the
         browser's own button. Matches the student profile exactly. --}}
    <a href="{{ route('admin.staff.index') }}" class="btn btn-sm btn-white rounded-pill px-3 mb-3 shadow-sm fw-semibold">
        <i class="fa-solid fa-arrow-left me-1"></i> {{ __('Staff') }}
    </a>

    {{-- ══ Hero band ══ --}}
    <div class="st-hero p-4 mb-4 shadow">
        <div class="st-hero-bg"></div>
        <div class="position-relative" style="z-index: 2;">
            <div class="st-hero-top d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div class="d-flex align-items-center gap-3" style="min-width: 0;">
                    <x-staff-avatar :staff="$staff" size="76" class="st-hero-avatar" />
                    <div style="min-width: 0;">
                        <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                            <h1 class="h3 fw-bold mb-0 text-truncate">{{ $staff->name }}</h1>
                            @if($staff->trashed())
                                <span class="badge bg-danger-subtle text-danger rounded-pill px-3 py-1">{{ __('Removed') }}</span>
                            @elseif($staff->is_active)
                                <span class="badge bg-success-subtle text-success rounded-pill px-3 py-1">{{ __('Active') }}</span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary rounded-pill px-3 py-1">{{ __('Inactive') }}</span>
                            @endif
                        </div>
                        <div class="st-hero-meta d-flex flex-wrap align-items-center gap-3">
                            <span><i class="fa-solid fa-briefcase me-1"></i>{{ $staff->designation ?: __('Staff Member') }}</span>
                            @if($staff->mobile)
                                <span><i class="fa-solid fa-phone me-1"></i>{{ hostelease_phone($staff->mobile) }}</span>
                            @endif
                            @if($staff->join_date)
                                <span><i class="fa-solid fa-calendar me-1"></i>{{ __('Joined') }} {{ $staff->join_date->format('d M Y') }}</span>
                            @endif
                        </div>
                    </div>
                </div>

                @unless($staff->trashed())
                    <div class="st-hero-actions d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-light text-success rounded-pill px-4 fw-bold shadow-sm"
                                @click="$dispatch('pay-salary', {{ \Illuminate\Support\Js::from([
                                    'action' => route('admin.staff.salary', $staff),
                                    'name' => $staff->name,
                                    'salary' => (float) $staff->monthly_salary,
                                    'paid' => (object) ($payroll['paid'][$staff->id] ?? []),
                                    'attendance' => (object) ($payroll['attendance'][$staff->id] ?? []),
                                ]) }})">
                            <i class="fa-solid fa-money-bill-wave me-2"></i>{{ __('Pay') }}
                        </button>
                        <button type="button" class="btn btn-light rounded-pill px-4 fw-bold shadow-sm" @click="openEdit()">
                            <i class="fa-solid fa-pen me-2"></i>{{ __('Edit') }}
                        </button>
                        <div class="dropdown">
                            <button type="button" class="btn btn-outline-light rounded-pill px-3 fw-bold st-more-btn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="{{ __('More actions') }}">
                                <i class="fa-solid fa-ellipsis"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-4 p-2">
                                <li>
                                    <form method="POST" action="{{ route('admin.staff.destroy', $staff) }}" class="m-0"
                                          data-confirm="{{ __('Remove :name from the directory? Their salary history and its expense entries stay on the books.', ['name' => $staff->name]) }}">
                                        @csrf @method('DELETE')
                                        <button class="dropdown-item rounded-3 py-2 text-danger">
                                            <i class="fa-solid fa-trash me-2"></i>{{ __('Remove from directory') }}
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    </div>
                @endunless
            </div>

            {{-- At-a-glance. "Payroll" is the monthly commitment, "Paid" is what
                 actually went out this month — the gap is what's still owed. --}}
            <div class="row g-3 mt-2 position-relative" style="z-index: 1;">
                <div class="col-6 col-md">
                    <div class="st-metric-label">{{ __('Monthly Salary') }}</div>
                    <div class="st-metric-val">{{ hostelease_money($staff->monthly_salary) }}</div>
                </div>
                <div class="col-6 col-md">
                    <div class="st-metric-label">{{ __('Paid') }} · {{ now()->format('M') }}</div>
                    <div class="st-metric-val" style="color: #6ee7b7;">{{ hostelease_money($paidThisMonth) }}</div>
                </div>
                <div class="col-6 col-md">
                    <div class="st-metric-label">{{ __('Present') }} · {{ now()->format('M') }}</div>
                    <div class="st-metric-val">{{ $counts['present'] + $counts['half_day'] }} <span class="fs-6 opacity-50 fw-normal">{{ __('days') }}</span></div>
                </div>
                <div class="col-6 col-md">
                    <div class="st-metric-label">{{ __('Paid Lifetime') }}</div>
                    <div class="st-metric-val">{{ hostelease_money($paidLifetime) }}</div>
                </div>
            </div>
        </div>
    </div>

    @if($staff->trashed())
        <div class="st-removed mb-4">
            <i class="fa-solid fa-user-slash"></i>
            <span>{{ __('Removed from the directory. Salary history below stays on the books — restore to make changes.') }}</span>
            <form method="POST" action="{{ route('admin.staff.restore', $staff) }}" class="ms-auto m-0">
                @csrf
                <button class="btn btn-sm btn-white border rounded-pill fw-bold px-3 text-nowrap tactile-btn">
                    <i class="fa-solid fa-rotate-left me-1"></i>{{ __('Restore') }}
                </button>
            </form>
        </div>
    @endif

    <div class="row g-4">
        {{-- ══ Left: identity ══ --}}
        <div class="col-lg-4">
            <div class="panel-card">
                <div class="panel-head">
                    <h6><i class="fa-solid fa-id-card me-2" style="color: var(--he-primary);"></i>{{ __('Details') }}</h6>
                </div>
                <div class="panel-body py-2">
                    <div class="st-info">
                        <span class="lbl">{{ __('Mobile') }}</span>
                        <span class="val">
                            @if($staff->mobile)<x-mobile-link :mobile="$staff->mobile" />@else<span class="text-muted fw-normal">—</span>@endif
                        </span>
                    </div>
                    <div class="st-info">
                        <span class="lbl">{{ __('Aadhaar') }}</span>
                        <span class="val d-flex align-items-center gap-2 justify-content-end flex-wrap">
                            <x-aadhaar-field :masked="hostelease_mask_aadhaar($staff->aadhaar_number)"
                                :url="route('admin.staff.aadhaar', $staff)" />
                            @if($staff->aadhaar_file)
                                <a href="{{ route('admin.files.show', ['staff', $staff->id, 'aadhaar_file']) }}" target="_blank" rel="noopener"
                                   class="badge bg-primary-subtle text-primary rounded-pill px-2 py-1 text-decoration-none">
                                    <i class="fa-solid fa-file-image me-1"></i>{{ __('View') }}
                                </a>
                            @endif
                        </span>
                    </div>
                    <div class="st-info">
                        <span class="lbl">{{ __('Address') }}</span>
                        <span class="val text-truncate">{{ $staff->address ?: '—' }}</span>
                    </div>
                    @if($staff->notes)
                        <div class="st-info">
                            <span class="lbl">{{ __('Notes') }}</span>
                            <span class="val fw-normal text-truncate">{{ $staff->notes }}</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ══ Right: attendance + payroll ══ --}}
        <div class="col-lg-8 he-adaptive">
            <div class="panel-card mb-4">
                <div class="panel-head">
                    <h6><i class="fa-solid fa-clipboard-user me-2" style="color: var(--he-primary);"></i>{{ __('Attendance') }}</h6>
                    <span class="text-muted small fw-semibold">{{ now()->format('F Y') }}</span>
                </div>
                <div class="panel-body">
                    <div class="st-counts">
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
                </div>
            </div>

            <div class="panel-card">
                <div class="panel-head">
                    <h6><i class="fa-solid fa-money-check-dollar me-2" style="color: var(--he-success);"></i>{{ __('Salary History') }}</h6>
                    <span class="text-muted small fw-semibold">{{ $payments->count() }} {{ trans_choice('entry|entries', $payments->count()) }}</span>
                </div>
                @forelse($payments as $p)
                    <div class="st-pay">
                        <div class="st-pay__ic"><i class="fa-solid fa-money-bill-wave"></i></div>
                        <div class="flex-grow-1" style="min-width: 0;">
                            <div class="fw-bold text-dark text-truncate">{{ $p->salary_month->format('F Y') }}</div>
                            <div class="text-muted small text-truncate">
                                {{ $p->paid_on->format('d M Y') }} · {{ $modeNames[$p->mode] ?? ucfirst($p->mode) }}@if($p->reference_number) · {{ $p->reference_number }}@endif
                            </div>
                            @if($p->notes)
                                <div class="text-muted small text-truncate fst-italic">{{ $p->notes }}</div>
                            @endif
                        </div>
                        <span class="st-pay__amt text-success">{{ hostelease_money($p->amount) }}</span>
                        {{-- Deleting the salary takes its expense mirror with it
                             (W6.2). Reachable even for a removed staff member —
                             otherwise the mirror is stranded un-deletable. --}}
                        <form method="POST" action="{{ route('admin.staff.salary.destroy', [$staff, $p->id]) }}" class="m-0"
                              data-confirm="{{ __('Delete this salary entry? Its matching expense entry is removed too.') }}">
                            @csrf @method('DELETE')
                            <button class="he-icon-btn is-danger" title="{{ __('Delete salary entry') }}" aria-label="{{ __('Delete salary entry') }}">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </form>
                    </div>
                @empty
                    <div class="panel-body">
                        <x-he-empty-state icon="file-invoice-dollar" title="{{ __('No salary paid yet') }}"
                            subtitle="{{ __('Recorded payments appear here and in Expenses.') }}" />
                    </div>
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
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Aadhaar Number') }}</label>
                            {{-- Blank on edit (P5): leave empty to keep the stored number; the
                                 masked value is the placeholder. Only a fresh 12-digit entry replaces it. --}}
                            <input type="text" name="aadhaar_number" class="form-control bg-light" inputmode="numeric" maxlength="12" pattern="\d{12}"
                                   value="{{ old('aadhaar_number') }}" placeholder="{{ hostelease_mask_aadhaar($staff->aadhaar_number) }} · {{ __('leave blank to keep') }}">
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

        openEdit() {
            this.editOpen = true;
            document.body.style.overflow = 'hidden';
        },

        // The Pay Salary sheet owns itself — this page just $dispatches to it.
        close() {
            this.editOpen = false;
            document.body.style.overflow = '';
        },
    }));
});
</script>
@endpush
@endsection
