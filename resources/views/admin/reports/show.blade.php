@extends('layouts.app')
@section('title', $title)

@push('styles')
<style>
    /* ══ Report page (W8 — one skeleton for every report) ══ */
    @media print {
        .he-sidebar, .he-topbar, footer, .no-print { display: none !important; }
        .he-content { margin-left: 0 !important; }
    }

    /* Filter row owns the dropdowns → never a container (§4.2/§4.9). */
    .rp-filter-row { position: relative; z-index: 30; }

    /* Preset chips — ONE row that scrolls sideways on phones (§4.5), never wraps. */
    .rp-presets {
        display: flex; gap: 0.4rem; flex-wrap: nowrap;
        overflow-x: auto; scrollbar-width: none; -webkit-overflow-scrolling: touch;
        min-width: 0;
    }
    .rp-presets::-webkit-scrollbar { display: none; }
    .rp-chip {
        flex-shrink: 0;
        padding: 0.42rem 0.9rem;
        border: 1px solid rgba(0, 0, 0, 0.08);
        border-radius: var(--he-radius-full);
        background: var(--he-bg-surface);
        color: var(--he-text-muted);
        font-size: 0.78rem; font-weight: 700; white-space: nowrap;
        transition: all 0.2s var(--ease-out-expo);
    }
    .rp-chip:hover { border-color: var(--he-primary); color: var(--he-primary); }
    .rp-chip.is-on { background: var(--he-primary); border-color: var(--he-primary); color: #fff; box-shadow: 0 3px 10px rgba(79, 70, 229, 0.3); }

    /* Chart shell — skeleton shimmer until Chart.js paints (no blank card). */
    .rp-chart-shell { position: relative; height: 260px; }
    .rp-chart-shell canvas { position: relative; z-index: 1; }
    .rp-chart-skeleton { position: absolute; inset: 0; border-radius: var(--he-radius-md); }
    .rp-chart-shell.is-ready .rp-chart-skeleton { opacity: 0; pointer-events: none; transition: opacity 0.3s var(--ease-out-expo); }
    @container (max-width: 640px) { .rp-chart-shell { height: 200px; } }

    /* Table — premium skin; wide content scrolls INSIDE the panel (§4.10). */
    .rp-table-wrap { overflow-x: auto; }
    .rp-table { width: 100%; border-collapse: collapse; }
    .rp-table th {
        padding: 0.8rem 1.1rem;
        font-size: 0.68rem; font-weight: 800; text-transform: uppercase;
        letter-spacing: 0.06em; color: var(--he-text-muted);
        background: var(--he-bg-canvas);
        border-bottom: 1px solid rgba(0, 0, 0, 0.06);
        white-space: nowrap;
    }
    .rp-table td {
        padding: 0.8rem 1.1rem;
        border-bottom: 1px solid rgba(0, 0, 0, 0.045);
        font-size: 0.9rem; color: var(--he-text-main);
        white-space: nowrap; /* figures and periods never wrap (§4.10) */
    }
    .rp-table tbody tr { transition: background 0.15s var(--ease-out-expo); }
    .rp-table tbody tr:hover { background: var(--he-bg-surface-raised); }
    .rp-table .num { text-align: right; font-variant-numeric: tabular-nums; font-weight: 600; }
    .rp-table tfoot td {
        background: var(--he-primary-soft); color: var(--he-primary);
        font-weight: 800; border-bottom: none;
    }
</style>
@endpush

@section('content')
<div class="page-enter">

    {{-- ══ Head: back + title | export actions (labels drop below md, §4.8) ══ --}}
    <div class="d-flex justify-content-between align-items-center gap-2 mb-4 no-print stagger-1">
        <div class="d-flex align-items-center gap-3" style="min-width: 0;">
            <a href="{{ route('admin.reports.index') }}" class="btn btn-sm btn-white rounded-pill px-3 shadow-sm fw-semibold flex-shrink-0">
                <i class="fa-solid fa-arrow-left me-1"></i><span class="d-none d-sm-inline">{{ __('Reports') }}</span>
            </a>
            <div style="min-width: 0;">
                <h1 class="h4 fw-bold mb-0 text-dark text-truncate"><i class="fa-solid fa-{{ $icon }} me-2" style="color: var(--he-primary);"></i>{{ $label }}</h1>
                <p class="text-muted small mb-0 text-truncate">
                    @if($needsRange){{ $from->format('d M Y') }} — {{ $to->format('d M Y') }}@else{{ __('Live snapshot') }}@endif
                </p>
            </div>
        </div>
        <div class="d-flex gap-2 flex-shrink-0">
            <button onclick="window.print()" class="he-icon-btn" title="{{ __('Print') }}" aria-label="{{ __('Print') }}"><i class="fa-solid fa-print"></i></button>
            <a href="{{ route('admin.reports.show', [$type, 'export' => 'pdf'] + request()->only(['period', 'from', 'to', 'range', 'group'])) }}"
               class="he-icon-btn" title="{{ __('Download PDF') }}" aria-label="{{ __('Download PDF') }}"><i class="fa-solid fa-file-pdf"></i></a>
            <a href="{{ route('admin.reports.show', [$type, 'export' => 'excel'] + request()->only(['period', 'from', 'to', 'range', 'group'])) }}"
               class="btn btn-success rounded-pill px-3 fw-bold shadow-sm tactile-btn text-nowrap">
                <i class="fa-solid fa-file-excel"></i><span class="d-none d-md-inline ms-1">{{ __('Excel') }}</span>
            </a>
        </div>
    </div>

    {{-- ══ Filters — ONE row (§4.5), fragment-driven (§4.3): swaps #report-data only ══ --}}
    @if($needsRange || $type === 'dues')
    <div class="rp-filter-row mb-4 no-print stagger-2">
        {{-- One Alpine scope OWNS the range state: chips set it, a datechip
             edit clears it (custom wins), and the single hidden input is
             disabled when empty — so preset and custom bounds can never both
             submit and silently fight (last-value-wins bugs). --}}
        <form method="GET" action="{{ route('admin.reports.show', $type) }}" x-ref="f" data-fragment="#report-data"
              x-data="{ range: @js($preset === 'custom' ? '' : $preset) }"
              class="d-flex flex-nowrap align-items-center gap-2">
            @if($needsRange)
                <input type="hidden" name="range" :value="range" :disabled="! range">
                <div class="rp-presets">
                    @foreach(['month' => __('This Month'), 'last_month' => __('Last Month'), 'quarter' => __('Quarter'), 'fy' => __('FY')] as $key => $chipLabel)
                        <button type="button" class="rp-chip tactile-btn" :class="{ 'is-on': range === @js($key) }"
                                @click="range = @js($key); $nextTick(() => $refs.f.requestSubmit())">{{ $chipLabel }}</button>
                    @endforeach
                    <span class="rp-chip" :class="{ 'is-on': ! range }" style="cursor: default;">{{ __('Custom') }}</span>
                </div>

                {{-- Custom bounds — .he-datechip (collapses to icon square below md). --}}
                <div class="he-datechip flex-shrink-0" title="{{ __('From') }}">
                    <span class="he-datechip__ic"><i class="fa-solid fa-calendar-day"></i></span>
                    <span class="he-datechip__txt">
                        <span class="he-datechip__lbl">{{ __('From') }}</span>
                        <span class="fw-bold small">{{ $from->format('d M y') }}</span>
                    </span>
                    <input type="date" name="from" value="{{ $from->toDateString() }}" max="{{ now()->toDateString() }}"
                           @click="$event.target.showPicker?.()" @change="range = ''; $refs.f.requestSubmit()">
                </div>
                <div class="he-datechip flex-shrink-0" title="{{ __('To') }}">
                    <span class="he-datechip__ic"><i class="fa-solid fa-calendar-check"></i></span>
                    <span class="he-datechip__txt">
                        <span class="he-datechip__lbl">{{ __('To') }}</span>
                        <span class="fw-bold small">{{ $to->format('d M y') }}</span>
                    </span>
                    <input type="date" name="to" value="{{ $to->toDateString() }}" max="{{ now()->toDateString() }}"
                           @click="$event.target.showPicker?.()" @change="range = ''; $refs.f.requestSubmit()">
                </div>
            @endif

            @if($type === 'collection')
                <x-he-select name="period" icon="chart-column" icon-only-mobile :selected="$period" :options="[
                    'daily' => ['label' => __('Daily'), 'icon' => 'calendar-day'],
                    'weekly' => ['label' => __('Weekly'), 'icon' => 'calendar-week'],
                    'monthly' => ['label' => __('Monthly'), 'icon' => 'calendar'],
                    'yearly' => ['label' => __('Yearly'), 'icon' => 'calendar-days'],
                ]" />
            @elseif($type === 'expenses')
                <x-he-select name="group" icon="layer-group" icon-only-mobile :selected="$groupBy" :options="[
                    'category' => ['label' => __('By Category'), 'icon' => 'tags'],
                    'month' => ['label' => __('By Month'), 'icon' => 'calendar'],
                ]" />
            @endif
        </form>
    </div>
    @endif

    {{-- ══ The data region — everything a filter changes, swapped in place ══ --}}
    <div id="report-data" data-fragment-container class="he-adaptive">
        @include('admin.reports._data')
    </div>
</div>

@push('scripts')
<script>
// Chart bootstrap. The dataset rides INSIDE the fragment as JSON, because a
// fragment swap replaces innerHTML and replaced <script> tags never execute —
// so the chart must be (re)drawn from OUTSIDE the fragment, on load and again
// on every he:fragment-swapped.
(function () {
    let chart = null;

    const tones = {
        primary: { line: '#4f46e5', soft: 'rgba(79, 70, 229, 0.18)' },
        success: { line: '#10b981', soft: 'rgba(16, 185, 129, 0.16)' },
        danger: { line: '#ef4444', soft: 'rgba(239, 68, 68, 0.14)' },
        warning: { line: '#f59e0b', soft: 'rgba(245, 158, 11, 0.16)' },
        muted: { line: '#94a3b8', soft: 'rgba(148, 163, 184, 0.18)' },
    };

    function draw() {
        const holder = document.getElementById('rp-chart-data');
        const shell = document.getElementById('rp-chart-shell');
        if (chart) { chart.destroy(); chart = null; }
        if (! holder || ! shell || ! window.Chart) return;

        const cfg = JSON.parse(holder.textContent);
        const canvas = shell.querySelector('canvas');
        const ctx = canvas.getContext('2d');
        const area = cfg.type === 'area';

        chart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: cfg.labels,
                datasets: cfg.series.map(([label, data, toneKey]) => {
                    const tone = tones[toneKey] ?? tones.primary;
                    if (area) {
                        // Glowing area line (guidelines §3.10): high tension,
                        // gradient fill, no x gridlines.
                        const g = ctx.createLinearGradient(0, 0, 0, shell.clientHeight);
                        g.addColorStop(0, tone.soft);
                        g.addColorStop(1, 'rgba(255, 255, 255, 0)');
                        return { type: 'line', label, data, borderColor: tone.line, backgroundColor: g,
                            fill: true, tension: 0.45, borderWidth: 2.5, pointRadius: 2, pointHoverRadius: 5 };
                    }
                    return { label, data, backgroundColor: tone.line, borderRadius: 6, maxBarThickness: 34 };
                }),
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: cfg.series.length > 1, labels: { usePointStyle: true, boxWidth: 8 } } },
                scales: {
                    x: { grid: { display: false } },
                    y: { border: { display: false }, ticks: { maxTicksLimit: 6 } },
                },
            },
        });
        shell.classList.add('is-ready');
    }

    document.addEventListener('DOMContentLoaded', draw);
    document.addEventListener('he:fragment-swapped', draw);
})();
</script>
@endpush
@endsection
