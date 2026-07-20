{{-- Board stat tiles. Currently Out is the hero — the number the page exists
     for. "Not enrolled" links to Devices. Swapped by the live poll. --}}
@php $isStaff = ($type ?? 'student') === 'staff'; @endphp
<div class="he-stats">
    <div class="he-stats__grid" style="--he-stats-cols: 4;">
        <div class="he-stat he-stat--hero">
            <div class="he-stat__head">
                <div class="he-stat__icon" style="background: rgba(255,255,255,0.15); color: #fbbf24;"><i class="fa-solid fa-person-walking-arrow-right"></i></div>
                <div class="he-stat__label">{{ __('Currently Out') }}</div>
            </div>
            <div class="he-stat__value">{{ $stats['out'] }}</div>
        </div>
        <div class="he-stat">
            <div class="he-stat__head">
                <div class="he-stat__icon" style="background: var(--he-success-soft); color: var(--he-success);"><i class="fa-solid fa-house-user"></i></div>
                <div class="he-stat__label">{{ $isStaff ? __('On Premises') : __('Inside') }}</div>
            </div>
            <div class="he-stat__value text-success">{{ $stats['inside'] }}</div>
        </div>
        <div class="he-stat">
            <div class="he-stat__head">
                <div class="he-stat__icon" style="background: {{ ($stats['unknown'] + $stats['stale']) ? 'var(--he-warning-soft)' : 'var(--he-bg-surface-raised)' }}; color: {{ ($stats['unknown'] + $stats['stale']) ? 'var(--he-warning)' : 'var(--he-text-muted)' }};"><i class="fa-solid fa-circle-question"></i></div>
                <div class="he-stat__label">{{ __('Unknown / Stale') }}</div>
            </div>
            <div class="he-stat__value {{ ($stats['unknown'] + $stats['stale']) ? 'text-warning' : '' }}">{{ $stats['unknown'] }}@if($stats['stale']) <span class="fs-6 opacity-75">· {{ $stats['stale'] }} ⚠</span>@endif</div>
        </div>
        <a href="{{ route('admin.presence.devices') }}" class="he-stat text-decoration-none" style="color: inherit;">
            <div class="he-stat__head">
                <div class="he-stat__icon" style="background: var(--he-bg-surface-raised); color: var(--he-text-muted);"><i class="fa-solid fa-user-plus"></i></div>
                <div class="he-stat__label">{{ __('Not Enrolled') }}</div>
            </div>
            <div class="he-stat__value">{{ $stats['not_enrolled'] }}</div>
        </a>
    </div>
</div>
