@extends('layouts.app')
@section('title', __('Insights & Reports'))

@push('styles')
<style>
    /* ══ Report hub (W8 — old design scrapped) ══
       Category-sectioned cards, each carrying a LIVE micro-stat so the hub is
       a mini-dashboard, not a list of links. Grid measures its CONTAINER
       (§4.9), so a sidebar-squeezed desktop gets the tablet layout. */

    .rh-grid {
        display: grid;
        gap: 1rem;
        grid-template-columns: 1fr;
    }
    @container (min-width: 640px) { .rh-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @container (min-width: 1020px) { .rh-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }

    .rh-cat {
        display: flex; align-items: center; gap: 0.6rem;
        margin: 0 0 0.85rem;
        font-size: 0.72rem; font-weight: 800; text-transform: uppercase;
        letter-spacing: 0.09em; color: var(--he-text-muted);
    }
    .rh-cat::after { content: ''; flex: 1; height: 1px; background: rgba(0, 0, 0, 0.07); }

    .rh-card {
        display: block;
        background: var(--he-bg-surface);
        border: 1px solid rgba(0, 0, 0, 0.05);
        border-radius: var(--he-radius-lg);
        box-shadow: var(--he-shadow-sm);
        padding: 1.25rem;
        text-decoration: none;
        position: relative;
        overflow: hidden;
        transition: transform 0.3s var(--ease-out-expo), box-shadow 0.3s var(--ease-out-expo), border-color 0.3s var(--ease-out-expo);
    }
    .rh-card::after {
        content: '';
        position: absolute; left: 0; right: 0; bottom: 0; height: 3px;
        background: var(--he-gradient-pop);
        transform: scaleX(0); transform-origin: left;
        transition: transform 0.4s var(--ease-out-expo);
    }
    .rh-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--he-shadow-lg);
        border-color: rgba(79, 70, 229, 0.25);
    }
    .rh-card:hover::after { transform: scaleX(1); }

    .rh-icon {
        width: 46px; height: 46px; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        border-radius: var(--he-radius-md);
        background: var(--he-primary-soft); color: var(--he-primary);
        font-size: 1.15rem;
        transition: all 0.3s var(--ease-out-expo);
    }
    .rh-card:hover .rh-icon { background: var(--he-gradient-pop); color: #fff; transform: scale(1.06) rotate(4deg); }

    /* The live number — the reason the hub isn't just links. Never wraps. */
    .rh-stat {
        display: inline-block;
        margin-top: 0.8rem; padding: 0.3rem 0.7rem;
        background: var(--he-bg-canvas);
        border-radius: var(--he-radius-full);
        font-size: 0.76rem; font-weight: 700; color: var(--he-text-main);
        font-variant-numeric: tabular-nums; white-space: nowrap;
        max-width: 100%; overflow: hidden; text-overflow: ellipsis;
    }
</style>
@endpush

@section('content')
<div class="page-enter">
    <div class="he-page-head mb-4 stagger-1">
        <div>
            <h1 class="he-page-title">{{ __('Insights & Reports') }}</h1>
            <p class="he-page-sub">{{ __('Your hostel by the numbers — money, dues, and occupancy.') }}</p>
        </div>
    </div>

    <div class="he-adaptive">
        @foreach(['money' => __('Money'), 'property' => __('Property'), 'presence' => __('Presence')] as $cat => $catLabel)
            @php $cards = collect($types)->filter(fn ($t) => $t[3] === $cat); @endphp
            @continue($cards->isEmpty())

            <h2 class="rh-cat {{ $loop->first ? 'stagger-2' : 'mt-4' }}">{{ $catLabel }}</h2>
            <div class="rh-grid stagger">
                @foreach($cards as $key => [$label, $description, $icon, $c, $needsRange])
                    <a href="{{ route('admin.reports.show', $key) }}" class="rh-card">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="rh-icon"><i class="fa-solid fa-{{ $icon }}"></i></div>
                            <span class="badge {{ $needsRange ? 'bg-primary-subtle text-primary' : 'bg-success-subtle text-success' }} rounded-pill px-3 py-2"
                                  style="font-size: 0.66rem; letter-spacing: 0.04em;">
                                {{ $needsRange ? __('Date Range') : __('Live') }}
                            </span>
                        </div>
                        <h5 class="fw-bold text-dark mb-1">{{ $label }}</h5>
                        <p class="text-muted small mb-0">{{ $description }}</p>
                        <span class="rh-stat">{{ $stats[$key] }}</span>
                    </a>
                @endforeach
            </div>
        @endforeach
    </div>
</div>
@endsection
