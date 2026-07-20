@extends('layouts.app')
@section('title', __('Presence · Devices & Enrollment'))

@php
    use App\Enums\Presence\DeviceStatus;
    use App\Enums\Presence\DeviceDirectionMode;
    use App\Enums\Presence\EnrollmentStatus;
@endphp

@push('styles')
<style>
    /* ══════════════════════════════════════════════════════════════════
       Presence · Devices & Enrollment  (P2)
       Built on the canonical system (he-page-head, he-stats, glass-tile,
       he-cq tiers, he-act-row, he-icon-btn, he-modal, he-picker). Page-local
       .pr-* only for what this operations surface uniquely needs: the device
       "unit" card with a live status LED, the capacity gauge, and the
       enrollment status pills. Promote to _premium.scss the day a 2nd page
       uses one (§0.1).
       ══════════════════════════════════════════════════════════════════ */

    /* ── Device "unit" cards ── */
    .pr-device-grid {
        display: grid; gap: 1rem;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    }
    .pr-device {
        position: relative;
        display: flex; flex-direction: column;
        padding: 1.15rem 1.15rem 1rem;
        overflow: hidden;
    }
    /* A faint machined sheen so a unit reads as hardware, not a table row. */
    .pr-device::before {
        content: ''; position: absolute; inset: 0;
        background: radial-gradient(120% 100% at 100% 0%, rgba(79,70,229,0.06), transparent 60%);
        pointer-events: none;
    }
    .pr-device__head { display: flex; align-items: flex-start; gap: 0.8rem; }
    .pr-device__icon {
        width: 46px; height: 46px; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        border-radius: var(--he-radius-md);
        background: var(--he-gradient-pop); color: #fff; font-size: 1.15rem;
        box-shadow: 0 6px 16px -4px rgba(79, 70, 229, 0.5);
    }
    .pr-device__title { min-width: 0; flex: 1; }
    .pr-device__name { font-weight: 800; letter-spacing: -0.01em; color: var(--he-text-main); }
    .pr-device__serial {
        font-family: ui-monospace, 'SF Mono', 'Cascadia Code', monospace;
        font-size: 0.72rem; letter-spacing: 0.02em;
        color: var(--he-text-muted);
        word-break: break-all;
    }

    /* Live status LED — the ONE ambient motion on the page (a gate's liveness
       is the whole point). Reserved for meaning: only Online breathes. */
    .pr-led {
        display: inline-flex; align-items: center; gap: 0.4rem;
        padding: 0.25rem 0.6rem 0.25rem 0.5rem;
        border-radius: var(--he-radius-full);
        font-size: 0.68rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em;
        flex-shrink: 0;
    }
    .pr-led__dot { width: 8px; height: 8px; border-radius: 50%; position: relative; }
    .pr-led--online   { background: var(--he-success-soft); color: #047857; }
    .pr-led--online .pr-led__dot { background: var(--he-success); }
    .pr-led--online .pr-led__dot::after {
        content: ''; position: absolute; inset: 0; border-radius: 50%;
        background: var(--he-success);
        animation: pr-breathe 2s var(--ease-out-expo) infinite;
    }
    .pr-led--offline  { background: var(--he-danger-soft); color: #b91c1c; }
    .pr-led--offline .pr-led__dot { background: var(--he-danger); }
    .pr-led--unknown  { background: var(--he-bg-surface-raised); color: var(--he-text-muted); }
    .pr-led--unknown .pr-led__dot { background: var(--he-text-muted); }
    @keyframes pr-breathe {
        0%   { transform: scale(1); opacity: 0.7; }
        70%  { transform: scale(2.6); opacity: 0; }
        100% { transform: scale(2.6); opacity: 0; }
    }

    .pr-device__meta {
        display: flex; flex-wrap: wrap; gap: 0.15rem 1.1rem;
        margin-top: 0.9rem;
        font-size: 0.76rem; color: var(--he-text-muted);
    }
    .pr-device__meta b { color: var(--he-text-main); font-weight: 700; }

    /* Capacity gauge — the unit "filling up" toward its 1,000-face limit. */
    .pr-gauge { margin-top: 0.85rem; }
    .pr-gauge__top {
        display: flex; justify-content: space-between; align-items: baseline;
        font-size: 0.68rem; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.05em; color: var(--he-text-muted); margin-bottom: 0.3rem;
    }
    .pr-gauge__num { font-variant-numeric: tabular-nums; color: var(--he-text-main); }
    .pr-gauge__track {
        height: 6px; border-radius: var(--he-radius-full);
        background: var(--he-bg-surface-raised); overflow: hidden;
    }
    .pr-gauge__fill {
        height: 100%; border-radius: var(--he-radius-full);
        background: var(--he-gradient-pop);
        width: 0; /* animated to target on load */
        transition: width 1s var(--ease-out-expo);
    }

    .pr-device__mode {
        display: inline-flex; align-items: center; gap: 0.35rem;
        font-size: 0.68rem; font-weight: 700;
        color: var(--he-text-muted);
        margin-top: 0.15rem;
    }

    .pr-device__acts {
        display: flex; align-items: center; gap: 0.4rem;
        margin-top: 1rem; padding-top: 0.85rem;
        border-top: 1px solid rgba(0,0,0,0.06);
    }

    /* Add-device affordance: a dashed "unit slot" that is itself a card. */
    .pr-device--add {
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        gap: 0.5rem; min-height: 190px;
        border: 2px dashed rgba(79, 70, 229, 0.28) !important;
        background: transparent !important; box-shadow: none !important;
        color: var(--he-primary); cursor: pointer;
        transition: all 0.3s var(--ease-out-expo);
    }
    .pr-device--add:hover {
        border-color: var(--he-primary) !important;
        background: var(--he-primary-soft) !important;
        transform: translateY(-2px);
    }
    .pr-device--add i { font-size: 1.5rem; }

    /* ── Quarantine attention panel ── */
    .pr-quar {
        border: 1px solid rgba(245, 158, 11, 0.35);
        background: linear-gradient(180deg, rgba(254, 243, 199, 0.5), var(--he-bg-surface));
        border-radius: var(--he-radius-lg);
        overflow: hidden;
    }
    .pr-quar__head {
        display: flex; align-items: center; gap: 0.7rem;
        padding: 0.9rem 1.1rem;
        border-bottom: 1px solid rgba(245, 158, 11, 0.2);
    }
    .pr-quar__badge {
        width: 34px; height: 34px; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        border-radius: var(--he-radius-md);
        background: var(--he-warning); color: #fff; font-size: 0.9rem;
        box-shadow: 0 4px 12px -2px rgba(245, 158, 11, 0.5);
    }
    .pr-quar__row {
        display: flex; align-items: center; gap: 1rem;
        padding: 0.75rem 1.1rem;
        border-top: 1px solid rgba(245, 158, 11, 0.14);
    }
    .pr-quar__row:first-of-type { border-top: none; }
    .pr-quar__id {
        font-family: ui-monospace, 'SF Mono', monospace; font-weight: 700;
        color: var(--he-text-main); word-break: break-all;
    }

    /* ── Enrollment status pills ── */
    .pr-pill {
        display: inline-flex; align-items: center; gap: 0.35rem;
        padding: 0.28rem 0.7rem; border-radius: var(--he-radius-full);
        font-size: 0.68rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.04em;
        white-space: nowrap;
    }
    .pr-pill__dot { width: 7px; height: 7px; border-radius: 50%; }
    .pr-pill--active  { background: var(--he-success-soft); color: #047857; }
    .pr-pill--active .pr-pill__dot { background: var(--he-success); }
    .pr-pill--pending { background: var(--he-warning-soft); color: #b45309; position: relative; overflow: hidden; }
    .pr-pill--pending .pr-pill__dot { background: var(--he-warning); }
    /* Pending shimmers — a quiet "still waiting for the face" signal. */
    .pr-pill--pending::after {
        content: ''; position: absolute; inset: 0;
        background: linear-gradient(100deg, transparent 30%, rgba(255,255,255,0.55) 50%, transparent 70%);
        transform: translateX(-100%);
        animation: pr-shimmer 2.4s ease-in-out infinite;
    }
    @keyframes pr-shimmer { to { transform: translateX(100%); } }
    .pr-pill--failed  { background: var(--he-danger-soft); color: #b91c1c; }
    .pr-pill--failed .pr-pill__dot { background: var(--he-danger); }
    .pr-pill--none    { background: var(--he-bg-surface-raised); color: var(--he-text-muted); }
    .pr-pill--none .pr-pill__dot { background: var(--he-text-muted); }

    .pr-uid {
        font-family: ui-monospace, 'SF Mono', monospace; font-size: 0.76rem; font-weight: 700;
        color: var(--he-text-muted); font-variant-numeric: tabular-nums;
    }

    /* ── Enrollment roster rows (container-tiered like Staff, §4.9/4.11) ── */
    .pr-row { align-items: center; gap: 0.6rem 1rem; grid-template-columns: minmax(0,1fr) auto; grid-template-areas: "who acts"; }
    .pr-row__who { grid-area: who; display: flex; align-items: center; gap: 0.85rem; min-width: 0; }
    .pr-row__status { display: flex; align-items: center; gap: 0.7rem; }
    .pr-row__acts { grid-area: acts; display: flex; align-items: center; justify-content: flex-end; gap: 0.5rem; }
    @container (min-width: 720px) {
        .pr-row {
            grid-template-columns: minmax(220px, 1fr) 150px 180px;
            grid-template-areas: "who status acts";
        }
        .pr-row__status { grid-area: status; justify-content: flex-start; }
    }
    .pr-avatar {
        width: 42px; height: 42px; border-radius: 50%; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        font-weight: 800; color: #fff; font-size: 0.95rem;
        background: linear-gradient(135deg, var(--he-primary), var(--he-accent));
    }
    .pr-leaver {
        display: inline-flex; align-items: center; gap: 0.3rem;
        padding: 0.15rem 0.5rem; border-radius: var(--he-radius-full);
        font-size: 0.64rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.04em;
        background: var(--he-danger-soft); color: var(--he-danger); margin-left: 0.4rem;
    }

    /* Discover results in the add-device modal. */
    .pr-disc { display: flex; flex-direction: column; gap: 0.4rem; margin-top: 0.6rem; }
    .pr-disc__item {
        display: flex; align-items: center; gap: 0.6rem; width: 100%;
        padding: 0.55rem 0.75rem; text-align: left;
        border: 1px solid rgba(0,0,0,0.08); border-radius: var(--he-radius-md);
        background: var(--he-bg-surface); color: var(--he-text-main);
        transition: all 0.18s var(--ease-out-expo);
    }
    .pr-disc__item:hover { border-color: var(--he-primary); background: var(--he-primary-soft); }

    @media (prefers-reduced-motion: reduce) {
        .pr-led--online .pr-led__dot::after,
        .pr-pill--pending::after { animation: none; }
        .pr-gauge__fill { transition: none; }
    }

    @media (max-width: 576px) { .pr-roster-wrap { padding-bottom: 5rem; } } /* clear FAB */
</style>
@endpush

@section('content')
<div class="page-enter" x-data="presenceDevices()">

    {{-- ═══ Head ═══ --}}
    <div class="he-page-head mb-4 stagger-1">
        <div>
            <h1 class="he-page-title">{{ __('Devices & Enrollment') }}</h1>
            <p class="he-page-sub">{{ __('Your gate terminals, and who is registered on them.') }}</p>
        </div>
        <button type="button" class="btn btn-premium rounded-pill px-4 fw-semibold shadow-sm tactile-btn d-none d-md-inline-flex align-items-center"
                @click="openAddDevice()">
            <i class="fa-solid fa-plus me-2"></i>{{ __('Add device') }}
        </button>
    </div>

    {{-- ═══ Stats ═══ --}}
    <div class="he-stats mb-4 stagger-2">
        <div class="he-stats__grid" style="--he-stats-cols: 4;">
            <div class="he-stat he-stat--hero">
                <div class="he-stat__head">
                    <div class="he-stat__icon" style="background: rgba(255,255,255,0.15); color: #93c5fd;"><i class="fa-solid fa-tower-broadcast"></i></div>
                    <div class="he-stat__label">{{ __('Devices Online') }}</div>
                </div>
                <div class="he-stat__value">{{ $stats['online'] }} <span class="opacity-50 fs-5">/ {{ $stats['devices'] }}</span></div>
            </div>
            <div class="he-stat">
                <div class="he-stat__head">
                    <div class="he-stat__icon" style="background: var(--he-success-soft); color: var(--he-success);"><i class="fa-solid fa-id-card"></i></div>
                    <div class="he-stat__label">{{ __('Enrolled') }}</div>
                </div>
                <div class="he-stat__value">{{ $stats['enrolled'] }}</div>
            </div>
            <div class="he-stat">
                <div class="he-stat__head">
                    <div class="he-stat__icon" style="background: var(--he-warning-soft); color: var(--he-warning);"><i class="fa-solid fa-hourglass-half"></i></div>
                    <div class="he-stat__label">{{ __('Pending') }}</div>
                </div>
                <div class="he-stat__value {{ $stats['pending'] ? 'text-warning' : '' }}">{{ $stats['pending'] }}</div>
            </div>
            <div class="he-stat">
                <div class="he-stat__head">
                    <div class="he-stat__icon" style="background: {{ $stats['unmatched'] ? 'var(--he-danger-soft)' : 'var(--he-bg-surface-raised)' }}; color: {{ $stats['unmatched'] ? 'var(--he-danger)' : 'var(--he-text-muted)' }};"><i class="fa-solid fa-circle-question"></i></div>
                    <div class="he-stat__label">{{ __('Unmatched') }}</div>
                </div>
                <div class="he-stat__value {{ $stats['unmatched'] ? 'text-danger' : '' }}">{{ $stats['unmatched'] }}</div>
            </div>
        </div>
    </div>

    {{-- ═══ Zone 1 · Devices ═══ --}}
    <div class="d-flex align-items-center gap-2 mb-3 stagger-3">
        <h2 class="h6 fw-bold text-uppercase text-muted mb-0" style="letter-spacing: 0.06em;">{{ __('Gate Terminals') }}</h2>
    </div>

    <div class="pr-device-grid mb-5 stagger">
        @foreach($devices as $device)
            @php
                $st = $device->device_status;
                $ledClass = $st === DeviceStatus::Online ? 'online' : ($st === DeviceStatus::Offline ? 'offline' : 'unknown');
                $pct = min(100, round(($device->face_count / 1000) * 100));
                $payload = \Illuminate\Support\Js::from([
                    'id' => $device->public_id,
                    'name' => $device->name,
                    'direction_mode' => $device->direction_mode->value,
                    'is_active' => (bool) $device->is_active,
                ]);
            @endphp
            <div class="glass-tile pr-device rounded-4">
                <div class="pr-device__head">
                    <div class="pr-device__icon"><i class="fa-solid fa-door-open"></i></div>
                    <div class="pr-device__title">
                        <div class="pr-device__name text-truncate">{{ $device->name }}</div>
                        <div class="pr-device__serial">{{ $device->serial_number }}</div>
                    </div>
                    <span class="pr-led pr-led--{{ $ledClass }}">
                        <span class="pr-led__dot"></span>{{ $st->label() }}
                    </span>
                </div>

                <div class="pr-device__mode">
                    <i class="fa-solid fa-arrows-left-right"></i>{{ $device->direction_mode->label() }}
                </div>

                <div class="pr-device__meta">
                    <span>{{ __('Last log') }} <b>{{ $device->last_log_at ? $device->last_log_at->diffForHumans() : __('—') }}</b></span>
                    <span>{{ __('Synced') }} <b>{{ $device->last_synced_at ? $device->last_synced_at->diffForHumans() : __('never') }}</b></span>
                </div>

                <div class="pr-gauge">
                    <div class="pr-gauge__top">
                        <span>{{ __('Faces enrolled') }}</span>
                        <span class="pr-gauge__num">{{ $device->face_count }} <span class="text-muted">/ 1,000</span></span>
                    </div>
                    <div class="pr-gauge__track">
                        <div class="pr-gauge__fill" data-pct="{{ $pct }}"></div>
                    </div>
                </div>

                <div class="pr-device__acts">
                    <form method="POST" action="{{ route('admin.presence.devices.sync-time', $device) }}" class="m-0">
                        @csrf
                        <button class="he-icon-btn" title="{{ __('Sync clock') }}" aria-label="{{ __('Sync clock') }}"><i class="fa-solid fa-clock-rotate-left"></i></button>
                    </form>
                    <form method="POST" action="{{ route('admin.presence.devices.pull-logs', $device) }}" class="m-0">
                        @csrf
                        <button class="he-icon-btn" title="{{ __('Pull logs now') }}" aria-label="{{ __('Pull logs now') }}"><i class="fa-solid fa-cloud-arrow-down"></i></button>
                    </form>
                    <div class="he-act-right" style="margin-left: auto; display: flex; gap: 0.4rem;">
                        <button type="button" class="he-icon-btn" title="{{ __('Edit') }}" aria-label="{{ __('Edit') }}" @click="openEditDevice({{ $payload }})"><i class="fa-solid fa-pen"></i></button>
                        <form method="POST" action="{{ route('admin.presence.devices.destroy', $device) }}" class="m-0"
                              data-confirm="{{ __('Remove :name? Its punch history is kept.', ['name' => $device->name]) }}">
                            @csrf @method('DELETE')
                            <button class="he-icon-btn is-danger" title="{{ __('Remove') }}" aria-label="{{ __('Remove') }}"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach

        {{-- Add affordance --}}
        <div class="glass-tile pr-device pr-device--add rounded-4" @click="openAddDevice()" role="button" tabindex="0"
             @keydown.enter="openAddDevice()" @keydown.space.prevent="openAddDevice()">
            <i class="fa-solid fa-plus"></i>
            <span class="fw-bold">{{ __('Add gate device') }}</span>
            <span class="small text-muted">{{ __('Register a terminal by serial') }}</span>
        </div>
    </div>

    {{-- ═══ Zone 2 · Quarantine (only when unmatched) ═══ --}}
    @if($unmatched->isNotEmpty())
        <div class="pr-quar mb-5 stagger-4">
            <div class="pr-quar__head">
                <div class="pr-quar__badge"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <div class="flex-grow-1" style="min-width: 0;">
                    <div class="fw-bold text-dark">{{ __('Unmatched scans') }}</div>
                    <div class="small text-muted">{{ __('These device IDs are punching but aren\'t linked to anyone yet. Match each to a person to attach their history.') }}</div>
                </div>
            </div>
            @foreach($unmatched as $u)
                <div class="pr-quar__row">
                    <div class="flex-grow-1" style="min-width: 0;">
                        <span class="pr-quar__id">{{ $u->device_user_id }}</span>
                        <div class="small text-muted">{{ $u->punch_count }} {{ __('punch(es)') }} · {{ __('last seen') }} {{ \Illuminate\Support\Carbon::parse($u->last_seen)->diffForHumans() }}</div>
                    </div>
                    <button type="button" class="btn btn-sm btn-white border rounded-pill fw-bold px-3 text-nowrap tactile-btn"
                            @click="openMatch('{{ $u->device_user_id }}')">
                        <i class="fa-solid fa-link me-1"></i>{{ __('Match…') }}
                    </button>
                </div>
            @endforeach
        </div>
    @endif

    {{-- ═══ Zone 3 · Enrollment ═══ --}}
    <div class="panel-card stagger-5">
        <div class="panel-head flex-wrap gap-2">
            <h6 class="mb-0"><i class="fa-solid fa-address-card text-primary me-2"></i>{{ __('Enrollment') }}</h6>
            <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
                {{-- Students | Staff — fragment-swapped, no reload (§4.3) --}}
                <div class="he-tabs he-tabs--split" style="border: 0;">
                    <a href="{{ route('admin.presence.devices', ['people' => 'students']) }}" data-fragment="#pr-roster"
                       class="he-tab bg-transparent border-0 py-2 px-3 fw-medium text-secondary position-relative tactile-btn {{ $tab === 'students' ? 'text-dark fw-bold' : '' }}">
                        {{ __('Students') }}
                    </a>
                    <a href="{{ route('admin.presence.devices', ['people' => 'staff']) }}" data-fragment="#pr-roster"
                       class="he-tab bg-transparent border-0 py-2 px-3 fw-medium text-secondary position-relative tactile-btn {{ $tab === 'staff' ? 'text-dark fw-bold' : '' }}">
                        {{ __('Staff') }}
                    </a>
                </div>

                @if($tab === 'students' && $floors->isNotEmpty())
                    <button type="button" class="btn btn-sm btn-light border rounded-pill fw-bold px-3 tactile-btn" @click="openBulk()">
                        <i class="fa-solid fa-layer-group me-1 text-primary"></i>{{ __('Enroll a floor') }}
                    </button>
                @endif

                <form method="POST" action="{{ route('admin.presence.reconcile') }}" class="m-0">
                    @csrf
                    <button class="btn btn-sm btn-light border rounded-pill fw-bold px-3 tactile-btn" title="{{ __('Check who has registered their face') }}">
                        <i class="fa-solid fa-rotate me-1 text-primary"></i>{{ __('Refresh status') }}
                    </button>
                </form>
            </div>
        </div>

        <div class="panel-body pr-roster-wrap he-adaptive">
            <div id="pr-roster" data-fragment-container>
                @include('admin.presence._roster')
            </div>
        </div>
    </div>

    {{-- Mobile FAB — teleported to <body> so it escapes .page-enter's transform,
         which otherwise traps a position:fixed child (it'd anchor to the page box,
         not the viewport). Same pattern as Staff/Expenses/Finance FABs. --}}
    <template x-teleport="body">
        <button type="button" class="fab" @click="openAddDevice()" aria-label="{{ __('Add device') }}">
            <i class="fa-solid fa-plus"></i>
        </button>
    </template>

    {{-- ═══════════════ Modals ═══════════════ --}}

    {{-- Add / Edit device — hand-rolled (§5.3) because the form action is dynamic
         (store vs update): <x-he-modal> renders a static server action. --}}
    <template x-teleport="body">
    <div class="custom-overlay-backdrop" x-show="deviceModalOpen" x-transition.opacity @click="deviceModalOpen = false" x-cloak style="display: none;">
        <form method="POST" :action="editing ? editAction : '{{ route('admin.presence.devices.store') }}'"
              class="custom-overlay-modal" :class="{ 'is-open': deviceModalOpen }" x-show="deviceModalOpen" x-transition.opacity @click.stop
              style="display: none; max-width: 550px;">
            @csrf
            <template x-if="editing"><input type="hidden" name="_method" value="PUT"></template>

            <div class="custom-overlay-header">
                <h5 class="fw-bold mb-0">
                    <i class="fa-solid fa-door-open text-primary me-2"></i>
                    <span x-text="editing ? '{{ __('Edit device') }}' : '{{ __('Add gate device') }}'"></span>
                </h5>
                <button type="button" class="btn-close" @click="deviceModalOpen = false"></button>
            </div>

            <div class="custom-overlay-body">
        <div class="mb-3" x-show="!editing">
            <label class="form-label fw-bold small text-uppercase">{{ __('Serial number') }}</label>
            <input type="text" name="serial_number" x-model="form.serial_number" class="form-control bg-light"
                   placeholder="TW60000324000187" autocomplete="off">
            <div class="d-flex justify-content-between align-items-center mt-2">
                <span class="small text-muted">{{ __('Printed on the device / iDMS.') }}</span>
                <button type="button" class="btn btn-sm btn-link text-decoration-none fw-bold p-0" @click="runDiscover()" :disabled="discovering">
                    <i class="fa-solid" :class="discovering ? 'fa-spinner fa-spin' : 'fa-magnifying-glass'"></i>
                    <span x-text="discovering ? '{{ __('Scanning…') }}' : '{{ __('Discover') }}'"></span>
                </button>
            </div>
            <div class="pr-disc" x-show="discovered.length" x-cloak>
                <template x-for="d in discovered" :key="d.serial">
                    <button type="button" class="pr-disc__item" @click="form.serial_number = d.serial">
                        <i class="fa-solid fa-door-open text-primary"></i>
                        <span class="flex-grow-1" style="min-width:0;">
                            <span class="d-block fw-bold text-truncate" x-text="d.name || d.serial"></span>
                            <span class="d-block small text-muted" style="font-family: monospace;" x-text="d.serial"></span>
                        </span>
                        <i class="fa-solid fa-plus small text-primary"></i>
                    </button>
                </template>
            </div>
            <div class="small text-muted mt-2" x-show="discoverDone && !discovered.length" x-cloak>{{ __('No new devices found on iDMS.') }}</div>
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold small text-uppercase">{{ __('Name') }}</label>
            <input type="text" name="name" x-model="form.name" class="form-control bg-light" placeholder="{{ __('Main Gate') }}">
        </div>

        <div class="mb-2">
            <label class="form-label fw-bold small text-uppercase">{{ __('Direction') }}</label>
            <x-he-select name="direction_mode" :submit="false" compact icon="arrows-left-right"
                x-model="form.direction_mode"
                :options="collect(DeviceDirectionMode::cases())->mapWithKeys(fn($m) => [$m->value => $m->label()])->all()" />
            <div class="small text-muted mt-2">
                {{ __('One unit for both ways = Toggle. Separate in/out units = Entry / Exit.') }}
            </div>
        </div>

        <template x-if="editing">
            <label class="d-flex align-items-center gap-2 mt-3">
                <input type="checkbox" name="is_active" value="1" x-model="form.is_active" class="form-check-input mt-0">
                <span class="fw-semibold small">{{ __('Active (polled for punches)') }}</span>
            </label>
        </template>
            </div>{{-- /custom-overlay-body --}}

            <div class="custom-overlay-footer">
                <button type="button" class="btn btn-light border fw-semibold rounded-pill px-4" @click="deviceModalOpen = false">{{ __('Cancel') }}</button>
                <button type="submit" class="btn btn-primary fw-semibold rounded-pill px-4 shadow-sm"><i class="fa-solid fa-check me-2"></i>{{ __('Save') }}</button>
            </div>
        </form>
    </div>
    </template>

    {{-- Bulk floor enroll --}}
    <x-he-modal open="bulkOpen" title="{{ __('Enroll a floor') }}" icon="layer-group"
        :action="route('admin.presence.enroll.floor')">
        <p class="text-muted small">{{ __('Enrolls every active student on the chosen floor. Do it floor-by-floor so people aren\'t queued at the terminal all at once. Already-active students are skipped.') }}</p>
        <label class="form-label fw-bold small text-uppercase">{{ __('Floor') }}</label>
        <x-he-select name="floor_id" :submit="false" compact icon="layer-group"
            :options="$floors->pluck('name', 'id')->all()" />
    </x-he-modal>

    {{-- Quarantine match --}}
    @if($unmatched->isNotEmpty())
    <x-he-modal open="matchOpen" title="{{ __('Match a device ID') }}" icon="link"
        :action="route('admin.presence.quarantine.match')">
        <p class="text-muted small mb-3">
            {{ __('Device ID') }} <span class="pr-quar__id" x-text="matchId"></span>.
            {{ __('Who is this? Their past punches under this ID will be attached.') }}
        </p>
        <input type="hidden" name="device_user_id" :value="matchId">
        <input type="hidden" name="person_type" :value="matchType">
        <input type="hidden" name="person_id" :value="matchPersonId">
        <label class="form-label fw-bold small text-uppercase">{{ __('Person') }}</label>
        <x-he-picker name="match_person" :options="$matchPeople"
            search-placeholder="{{ __('Search students & staff…') }}"
            placeholder="{{ __('Select a person') }}"
            @he-picker-change="onMatchPick($event.detail)" />
    </x-he-modal>
    @endif
</div>
@endsection

@push('scripts')
<script>
    function presenceDevices() {
        return {
            // Device add/edit
            deviceModalOpen: false,
            editing: false,
            editAction: '',
            form: { serial_number: '', name: '', direction_mode: 'toggle', is_active: true },
            // Discover
            discovering: false, discoverDone: false, discovered: [],
            // Bulk
            bulkOpen: false,
            // Match
            matchOpen: false, matchId: '', matchType: '', matchPersonId: '',

            init() {
                // Fill capacity gauges after paint so they animate from 0.
                this.$nextTick(() => this.fillGauges());
                // Re-fill after a fragment swap (roster tab) — harmless, cheap.
                document.addEventListener('he:fragment-swapped', () => this.fillGauges());
            },
            fillGauges() {
                requestAnimationFrame(() => {
                    document.querySelectorAll('.pr-gauge__fill').forEach(el => {
                        el.style.width = (el.dataset.pct || 0) + '%';
                    });
                });
            },

            openAddDevice() {
                this.editing = false;
                this.form = { serial_number: '', name: '', direction_mode: 'toggle', is_active: true };
                this.discovered = []; this.discoverDone = false;
                this.deviceModalOpen = true;
            },
            openEditDevice(d) {
                this.editing = true;
                this.editAction = '{{ url('admin/presence/devices') }}/' + d.id;
                this.form = { serial_number: '', name: d.name, direction_mode: d.direction_mode, is_active: d.is_active };
                this.deviceModalOpen = true;
            },

            async runDiscover() {
                this.discovering = true; this.discoverDone = false;
                try {
                    const res = await fetch('{{ route('admin.presence.devices.discover') }}', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    });
                    const data = await res.json();
                    this.discovered = data.devices || [];
                } catch (e) {
                    this.discovered = [];
                    window.Swal && Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: '{{ __('Could not reach iDMS') }}', showConfirmButton: false, timer: 2500 });
                } finally {
                    this.discovering = false; this.discoverDone = true;
                }
            },

            openBulk() { this.bulkOpen = true; },

            openMatch(id) {
                this.matchId = id; this.matchType = ''; this.matchPersonId = '';
                this.matchOpen = true;
            },
            onMatchPick(detail) {
                // The picker value is "type:public_id" — split into the two fields.
                const [type, ...rest] = (detail.value || '').split(':');
                this.matchType = type || '';
                this.matchPersonId = rest.join(':');
            },
        };
    }
</script>
@endpush
