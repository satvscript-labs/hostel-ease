@extends('layouts.app')
@section('title', __('System Backups'))

@push('styles')
<style>
    /* ══ Backups — W12 rework onto the design system: he-page-head + he-stats
       health band, snapshot table → aligned rows (§4.11) with a designed
       640–880 tier, he-icon-btn actions, FAB on phones. ══ */
    .bk-list { display:grid; grid-template-columns:1fr; }
    .bk-row { grid-column:1/-1; display:flex; align-items:center; gap:.75rem; padding:.85rem 1.25rem; transition:background .18s var(--ease-out-expo); }
    .bk-row:hover { background:var(--he-bg-surface-raised); }
    .bk-row + .bk-row { border-top:1px solid rgba(15,23,42,.06); }
    .bk-ic { width:40px; height:40px; border-radius:12px; flex-shrink:0; display:flex; align-items:center; justify-content:center;
        background:var(--he-primary-soft); color:var(--he-primary); }
    .bk-text { flex:1 1 auto; min-width:0; } /* shrink chain (§4.11 r5) */
    .bk-name { font-weight:600; font-size:.82rem; color:var(--he-text-main); font-family:var(--bs-font-monospace, monospace); }
    .bk-sub { font-size:.74rem; color:var(--he-text-muted); }
    .bk-size, .bk-when { display:none; white-space:nowrap; font-variant-numeric:tabular-nums; }
    .bk-acts { display:flex; align-items:center; gap:.45rem; flex-shrink:0; }

    @container (min-width: 640px) {
        .bk-size { display:block; font-weight:600; color:var(--he-text-main); font-size:.84rem; }
    }
    @container (min-width: 880px) {
        .bk-list { grid-template-columns:minmax(260px,1fr) auto auto auto; column-gap:1.25rem; }
        .bk-row { display:grid; grid-template-columns:subgrid; }
        .bk-main { display:flex; align-items:center; gap:.75rem; min-width:0; }
        .bk-when { display:block; font-size:.8rem; color:var(--he-text-muted); }
        .bk-sub-when { display:none; } /* the timestamp moves into its column */
    }
    .bk-main { display:flex; align-items:center; gap:.75rem; min-width:0; flex:1 1 auto; }
</style>
@endpush

@section('content')
@php
    $totalSize = collect($backups)->sum('size');
    $lastBackup = count($backups) > 0 ? $backups[0] : null;
    $healthy = $lastBackup && $lastBackup['created_at']->diffInHours(now()) < 24;
@endphp
<div class="page-enter">
    <div class="he-page-head mb-4 stagger-1">
        <div>
            <h1 class="he-page-title">{{ __('System Backups') }}</h1>
            <p class="he-page-sub">{{ __('Database snapshots and automated recovery points.') }}</p>
        </div>
        <button form="backupForm" class="btn btn-premium shadow-sm rounded-pill px-4 fw-semibold tactile-btn d-none d-md-inline-flex align-items-center">
            <i class="fa-solid fa-cloud-arrow-up me-2"></i>{{ __('Create Backup Now') }}
        </button>
    </div>

    <form id="backupForm" method="POST" action="{{ route('superadmin.backups.store') }}" class="d-none">@csrf</form>
    <template x-teleport="body">
        <button form="backupForm" class="fab" title="{{ __('Create Backup Now') }}"><i class="fa-solid fa-cloud-arrow-up"></i></button>
    </template>

    {{-- ── Health band ── --}}
    <div class="he-stats mb-4 stagger-2">
        <div class="he-stats__grid" style="--he-stats-cols: 3;">
            <div class="he-stat he-stat--hero">
                <div class="he-stat__head">
                    <div class="he-stat__icon" style="background: rgba(255,255,255,.15); color: {{ $healthy ? '#6ee7b7' : '#fbbf24' }};"><i class="fa-solid fa-shield-halved"></i></div>
                    <div class="he-stat__label">{{ __('Last backup') }} <span class="opacity-50">· {{ $healthy ? __('healthy') : __('needs backup') }}</span></div>
                </div>
                <div class="he-stat__value">{{ $lastBackup ? $lastBackup['created_at']->diffForHumans(short: true) : __('None yet') }}</div>
            </div>
            <div class="he-stat">
                <div class="he-stat__head">
                    <div class="he-stat__icon" style="background: var(--he-primary-soft); color: var(--he-primary);"><i class="fa-solid fa-hard-drive"></i></div>
                    <div class="he-stat__label">{{ __('Storage used') }}</div>
                </div>
                <div class="he-stat__value">{{ number_format($totalSize / 1024 / 1024, 2) }} <span class="fs-6 opacity-50 fw-normal">MB</span></div>
            </div>
            <div class="he-stat">
                <div class="he-stat__head">
                    <div class="he-stat__icon" style="background: var(--he-info-soft); color: var(--he-info);"><i class="fa-solid fa-clock-rotate-left"></i></div>
                    <div class="he-stat__label">{{ __('Snapshots kept') }}</div>
                </div>
                <div class="he-stat__value">{{ count($backups) }}</div>
            </div>
        </div>
    </div>

    <div class="d-flex align-items-start gap-2 mb-3 p-3 rounded-3 stagger-3" style="background: var(--he-info-soft); color: #0369a1; font-size:.84rem; font-weight:600;">
        <i class="fa-solid fa-circle-info mt-1"></i>
        <div>{{ __('Nightly auto-backup runs at 02:00 and keeps 30 days. Files live in') }} <code>storage/app/backups</code>.</div>
    </div>

    {{-- ── Snapshots — aligned rows ── --}}
    <div class="he-adaptive stagger-4 mb-5">
        <div class="panel-card shadow-sm">
            <div class="bk-list">
                @forelse($backups as $b)
                    <div class="bk-row">
                        <div class="bk-main">
                            <div class="bk-ic"><i class="fa-solid fa-file-zipper"></i></div>
                            <div class="bk-text">
                                <div class="bk-name text-truncate">{{ $b['name'] }}</div>
                                <div class="bk-sub text-truncate"><span class="d-sm-none">{{ number_format($b['size'] / 1024 / 1024, 2) }} MB · </span><span class="bk-sub-when">{{ $b['created_at']->format('d M Y H:i') }} · </span>{{ $b['created_at']->diffForHumans() }}</div>
                            </div>
                        </div>
                        <div class="bk-size">{{ number_format($b['size'] / 1024 / 1024, 2) }} MB</div>
                        <div class="bk-when">{{ $b['created_at']->format('d M Y H:i') }}</div>
                        <div class="bk-acts he-act-row">
                            <a href="{{ route('superadmin.backups.download', $b['name']) }}" class="he-icon-btn" title="{{ __('Download') }}" aria-label="{{ __('Download :file', ['file' => $b['name']]) }}"><i class="fa-solid fa-download"></i></a>
                            <form action="{{ route('superadmin.backups.destroy', $b['name']) }}" method="POST" class="m-0" data-confirm="{{ __('Delete this snapshot permanently?') }}">
                                @csrf @method('DELETE')
                                <button class="he-icon-btn is-danger" title="{{ __('Delete') }}" aria-label="{{ __('Delete :file', ['file' => $b['name']]) }}"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="p-3" style="grid-column:1/-1;">
                        <x-he-empty-state icon="cloud-arrow-up" title="{{ __('No backups yet') }}" subtitle="{{ __('Create your first snapshot to secure the database.') }}" />
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
