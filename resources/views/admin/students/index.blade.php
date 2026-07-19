@extends('layouts.app')
@section('title', 'Students')

@push('styles')
<style>
    /* Hero — standard indigo→purple gradient (was an off-palette indigo→blue). */
    .students-hero {
        background: linear-gradient(135deg, var(--he-primary) 0%, var(--he-accent) 100%);
        border-radius: var(--he-radius-lg);
        padding: 2.5rem 2rem 4.5rem;
        color: #fff;
        margin-bottom: -3rem;
        position: relative;
        overflow: hidden;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
    }
    .students-hero::after {
        content: '';
        position: absolute;
        top: -50%; right: -20%;
        width: 320px; height: 320px;
        background: rgba(255,255,255,0.12);
        border-radius: 50%;
        filter: blur(45px);
        pointer-events: none;
    }
    .students-hero h1 { letter-spacing: -0.02em; }

    /* Toolbar — card standard (was blur + heavy shadow). */
    .filter-toolbar {
        background: var(--he-bg-surface);
        border: 1px solid rgba(0,0,0,0.05);
        border-radius: var(--he-radius-lg);
        box-shadow: var(--he-shadow-lg);
        padding: 1rem;
        position: relative;
        z-index: 10;
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        align-items: center;
        justify-content: space-between;
    }
    /* Search uses the canonical `.he-search` (see _premium.scss); only the
       page-level width lives here. */
    .students-search {
        flex-grow: 1;
        max-width: 400px;
    }

    /* Filter chips — outlined pills that fill with the brand gradient when
       active; soft primary tint on hover. Wrap (never a scroll strip). */
    .filter-pills {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    .filter-pill {
        border: 1.5px solid rgba(0, 0, 0, 0.08);
        background: var(--he-bg-surface);
        padding: 0.5rem 1.15rem;
        border-radius: var(--he-radius-full);
        font-weight: 600;
        font-size: 0.85rem;
        color: var(--he-text-muted);
        transition: all 0.2s var(--ease-out-expo);
        white-space: nowrap;
        cursor: pointer;
    }
    .filter-pill:hover:not(.active) {
        border-color: var(--he-primary);
        color: var(--he-primary);
        background: var(--he-primary-soft);
    }
    .filter-pill.active {
        background: linear-gradient(135deg, var(--he-primary), var(--he-accent));
        border-color: transparent;
        color: #fff;
        box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
    }

    /* ID Badge Cards */
    .id-card {
        background: var(--he-bg-surface);
        border-radius: var(--he-radius-lg);
        border: 1px solid rgba(0,0,0,0.05);
        overflow: hidden;
        position: relative;
        transition: transform 0.3s var(--ease-out-expo), box-shadow 0.3s var(--ease-out-expo);
        display: block;
        text-decoration: none;
        box-shadow: var(--he-shadow-sm);
    }
    .id-card:hover {
        transform: translateY(-6px);
        box-shadow: var(--he-shadow-float);
    }
    .id-card-banner {
        height: 80px;
        position: relative;
    }
    .banner-active { background: linear-gradient(135deg, var(--he-success) 0%, #059669 100%); }
    .banner-left { background: linear-gradient(135deg, #64748b 0%, #475569 100%); }
    .banner-nobed { background: linear-gradient(135deg, var(--he-warning) 0%, #d97706 100%); }

    .id-avatar-wrapper {
        position: absolute;
        top: 40px;
        left: 50%;
        transform: translateX(-50%);
        width: 80px;
        height: 80px;
        border-radius: 50%;
        padding: 4px;
        background: var(--he-bg-surface);
        box-shadow: var(--he-shadow-md);
        z-index: 2;
        transition: transform 0.3s var(--ease-out-expo);
    }
    .id-card:hover .id-avatar-wrapper {
        transform: translateX(-50%) scale(1.08);
    }
    .id-avatar {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        object-fit: cover;
    }
    .id-card-body {
        padding: 3.5rem 1.5rem 1.5rem;
        text-align: center;
    }
    .id-name {
        color: var(--he-text-main);
        font-weight: 800;
        font-size: 1.15rem;
        margin-bottom: 0.35rem;
    }
    .id-status-badge {
        font-size: 0.68rem;
        font-weight: 700;
        padding: 0.3rem 0.75rem;
        border-radius: var(--he-radius-full);
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        margin-bottom: 1.25rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .status-active { background: var(--he-success-soft); color: #047857; }
    .status-left { background: var(--he-bg-surface-raised); color: var(--he-text-muted); }

    /* Bento Info Grid */
    .id-info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.5rem;
        background: var(--he-bg-canvas);
        border-radius: var(--he-radius-md);
        padding: 0.75rem;
    }
    .id-info-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 0.6rem 0.5rem;
        border-radius: var(--he-radius-sm);
        background: var(--he-bg-surface);
        border: 1px solid rgba(0,0,0,0.04);
    }
    .id-info-val {
        font-weight: 700;
        color: var(--he-text-main);
        font-size: 0.82rem;
        margin-top: 0.3rem;
    }
    .id-info-icon {
        color: var(--he-text-muted);
        font-size: 1rem;
    }

    /* Hover Overlay Action */
    .id-overlay {
        position: absolute;
        inset: 0;
        background: rgba(255,255,255,0.7);
        backdrop-filter: blur(4px);
        z-index: 10;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: opacity 0.3s var(--ease-out-expo);
        border-radius: var(--he-radius-lg);
    }
    .id-card:hover .id-overlay {
        opacity: 1;
    }
    .overlay-btn {
        background: var(--he-primary);
        color: #fff;
        border: none;
        padding: 0.7rem 1.4rem;
        border-radius: var(--he-radius-full);
        font-weight: 700;
        transform: translateY(16px);
        transition: transform 0.3s var(--ease-out-expo);
        box-shadow: 0 10px 20px rgba(79, 70, 229, 0.3);
    }
    .id-card:hover .overlay-btn {
        transform: translateY(0);
    }

    /* Mobile — native, dense; heading uses the standard 2.2rem/1.5 scale. */
    @media (max-width: 576px) {
        .students-hero {
            flex-direction: column;
            gap: 0.75rem;
            padding: 1.75rem 1.35rem 4rem;
        }
        .students-hero h1 { font-size: 2.2rem; line-height: 1.5; margin-bottom: 0.1rem; }
        .students-hero p { font-size: 1rem; line-height: 1.5; }
        .students-hero .btn { display: none; } /* the FAB is the mobile add action */
        .filter-toolbar { padding: 0.85rem; gap: 0.75rem; }
        .students-search { max-width: none; width: 100%; }
        /* Chips fill the row: one row if they fit, else they wrap and each
           row stretches to full width (no orphan chip on its own). */
        .filter-pills { width: 100%; }
        .filter-pills .filter-pill { flex: 1 1 auto; text-align: center; }
        .id-card-body { padding: 3rem 1.1rem 1.1rem; }
    }
</style>
@endpush

@section('content')
<div class="page-enter">

    <!-- Hero Banner -->
    <div class="students-hero">
        <div>
            <h1 class="h2 fw-bold mb-1">Student Directory</h1>
            <p class="mb-0 opacity-75">Manage all active and past residents</p>
        </div>
        <a href="{{ route('admin.students.create') }}" class="btn btn-light fw-bold rounded-pill px-4 shadow-sm text-primary">
            <i class="fa-solid fa-plus me-1"></i> Add Student
        </a>
    </div>

    <!-- Toolbar — one fragment-driven row (§4.5): search + a filter select, both
         swap #student-list without a reload (§4.3). Server-side now (H2b). -->
    <div class="filter-toolbar mb-4">
        <form method="GET" action="{{ route('admin.students.index') }}" data-fragment="#student-list"
              class="d-flex flex-wrap align-items-center gap-2 w-100">
            <div class="he-search he-search--inline he-search--clearable students-search" x-data="{ q: @js($search) }">
                <span class="he-search__icon"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="text" name="q" class="he-search__input" x-model="q" value="{{ $search }}"
                       placeholder="Search by name, mobile, room…"
                       @input.debounce.450ms="$el.form.requestSubmit()">
                <button type="button" class="he-search__clear" x-show="q.length" x-cloak
                        @click="q = ''; $root.closest('form').requestSubmit()" aria-label="Clear search">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <x-he-select name="filter" icon="filter" icon-only-mobile :selected="$filter"
                :options="[
                    '' => ['label' => __('All Students'), 'icon' => 'users'],
                    'active' => ['label' => __('Active'), 'icon' => 'circle-check'],
                    'left' => ['label' => __('Left'), 'icon' => 'door-open'],
                ] + collect(config('hostelease.occupation_types'))->mapWithKeys(fn ($l, $k) => [$k => ['label' => $l, 'icon' => 'briefcase']])->all()" />
        </form>
    </div>

    <!-- Student Cards Grid — the swap target -->
    <div id="student-list" data-fragment-container>
        @include('admin.students._list')
    </div>

    {{-- Mobile FAB — teleported to <body> so its position:fixed anchors to
         the viewport. Inside .page-enter the entrance animation leaves a
         lingering transform, which would trap the fixed FAB to the page
         (it'd sit at the end of the content instead of the screen corner). --}}
    <template x-teleport="body">
        <a href="{{ route('admin.students.create') }}" class="fab" title="Add Student">
            <i class="fa-solid fa-plus"></i>
        </a>
    </template>
</div>
@endsection
