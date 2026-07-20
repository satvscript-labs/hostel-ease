@extends('layouts.app')
@section('title', 'Property Board')

@push('styles')
<style>
    /* Ultra-Premium Property Board Styles */
    .pb-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
    }
    
    /* Search uses the canonical `.he-search` (see _premium.scss); only the
       page-level width lives here. */
    .pb-search-field { width: 300px; }

    /* Bento Stats Row */
    .pb-stats-row {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1.5rem;
        margin-bottom: 3rem;
    }
    .pb-stat-card {
        background: rgba(255,255,255,0.85);
        backdrop-filter: blur(20px);
        border: 1px solid rgba(255,255,255,0.5);
        border-radius: 1.25rem;
        padding: 1.5rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        box-shadow: 0 10px 30px rgba(0,0,0,0.03);
        transition: transform 0.3s ease;
    }
    .pb-stat-card:hover { transform: translateY(-5px); }
    .pb-stat-icon {
        width: 54px;
        height: 54px;
        border-radius: 1rem;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }
    .pb-stat-primary { background: linear-gradient(135deg, var(--he-primary), var(--he-accent)); color: white; box-shadow: 0 10px 20px rgba(79, 70, 229, 0.3); }
    .pb-stat-success { background: rgba(16, 185, 129, 0.1); color: var(--he-success); }
    .pb-stat-danger { background: rgba(239, 68, 68, 0.1); color: var(--he-danger); }
    .pb-stat-warning { background: rgba(245, 158, 11, 0.1); color: var(--he-warning); }

    /* The Blueprint Layout */
    .floor-section {
        margin-bottom: 3rem;
    }
    
    .floor-header {
        display: flex;
        align-items: center;
        background: rgba(252, 253, 255, 0.95);
        backdrop-filter: blur(10px);
        padding: 1rem 1.5rem;
        border-radius: 1rem;
        margin-bottom: 2rem;
        box-shadow: 0 4px 15px rgba(0,0,0,0.02);
        border: 1px solid rgba(0,0,0,0.03);
    }
    .floor-header-bar {
        width: 6px;
        height: 32px;
        background: var(--he-primary);
        border-radius: 10px;
        margin-right: 1.25rem;
    }
    .floor-header h2 { 
        margin: 0; 
        font-size: 1.5rem; 
        font-weight: 700; 
        color: var(--he-text-main); 
    }
    
    .room-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 1.5rem;
    }

    .room-card {
        background: #ffffff;
        border: 1px solid rgba(0,0,0,0.03);
        border-radius: 1.25rem;
        padding: 1.25rem;
        box-shadow: 0 4px 20px rgba(0,0,0,0.02);
        position: relative;
        overflow: hidden;
    }
    .room-card.dimmed {
        opacity: 0.3;
        filter: grayscale(100%);
        transform: scale(0.98);
        pointer-events: none;
    }

    .room-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.25rem;
    }
    .room-number { font-size: 1.25rem; font-weight: 700; color: var(--he-text-main); }
    .room-type { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; padding: 0.35rem 0.85rem; border-radius: 50px; }
    .room-type.ac { background: var(--he-primary); color: #ffffff; }
    .room-type.non-ac { background: rgba(14, 165, 233, 0.1); color: var(--he-primary); }

    .bed-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }

    /* Bed Tiles */
    .bed-tile {
        position: relative;
        padding: 0.75rem;
        border-radius: 0.85rem;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        height: 60px;
    }
    .bed-tile:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(0,0,0,0.05); z-index: 10; }

    /* Empty Bed */
    .bed-tile.bed-empty {
        background: rgba(16, 185, 129, 0.06);
        border: 1px dashed rgba(16, 185, 129, 0.3);
        color: var(--he-success);
        justify-content: center;
    }
    .bed-tile.bed-empty:hover {
        background: rgba(16, 185, 129, 0.12);
        border-color: var(--he-success);
    }
    .bed-tile.bed-empty .bed-status-text {
        font-weight: 700;
        font-size: 0.75rem;
        letter-spacing: 0.5px;
    }
    
    /* Occupied Bed */
    .bed-tile.bed-occupied {
        background: rgba(239, 68, 68, 0.06);
        border: 1px solid rgba(239, 68, 68, 0.15);
        color: var(--he-danger);
        box-shadow: 0 2px 8px rgba(0,0,0,0.01);
    }
    .bed-tile.bed-occupied:hover {
        background: rgba(239, 68, 68, 0.12);
        border-color: var(--he-danger);
    }
    .occupant-details {
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: flex-start;
        flex: 1;
        min-width: 0;
        text-align: left;
    }
    .occupant-name {
        font-weight: 700;
        font-size: 0.85rem;
        color: #b91c1c;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        line-height: 1.2;
        width: 100%;
    }
    .bed-badge-row {
        display: flex;
        align-items: center;
        gap: 0.35rem;
        margin-top: 0.2rem;
    }
    .bed-badge {
        font-size: 0.6rem;
        font-weight: 700;
        background: rgba(239, 68, 68, 0.15);
        color: var(--he-danger);
        padding: 0.1rem 0.35rem;
        border-radius: 4px;
    }
    .occupant-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        object-fit: cover;
        flex-shrink: 0;
        border: 1.5px solid rgba(239, 68, 68, 0.25);
    }

    /* Maintenance */
    .bed-tile.bed-maintenance {
        background: rgba(245, 158, 11, 0.06);
        color: #d97706;
        border: 1px dashed rgba(245, 158, 11, 0.3);
        cursor: not-allowed;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        gap: 0.25rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.01);
    }
    .bed-tile.bed-maintenance:hover {
        background: rgba(245, 158, 11, 0.12);
        border-color: var(--he-warning);
    }
    .bed-maintenance .maintenance-text {
        font-size: 0.65rem;
        font-weight: 700;
        letter-spacing: 0.5px;
    }
    .bed-maintenance .maintenance-bed-label {
        font-size: 0.75rem;
        font-weight: 600;
        color: #b45309;
    }

    /* ── Move sheets (W6.4) ─────────────────────────────────────────────────
       The old bespoke "spotlight" panel is gone — assign/transfer/release now
       share the canonical .custom-overlay anatomy (section 5), so they're
       bottom sheets on phones for free. Only the move-specific bits live here. */

    /* Scrollable option list (assign's student search). The picker-based
       transfer uses .he-picker instead — it needs placement (4.7). */
    .mv-list {
        max-height: 46vh;
        overflow-y: auto;
        border: 1px solid rgba(0, 0, 0, 0.07);
        border-radius: var(--he-radius-md);
        padding: 0.3rem;
    }
    .mv-option {
        display: flex; align-items: center; gap: 0.75rem; width: 100%;
        padding: 0.6rem 0.7rem; border: none; background: none; text-align: left;
        border-radius: var(--he-radius-sm); cursor: pointer;
        transition: background 0.15s var(--ease-out-expo);
    }
    .mv-option:hover { background: var(--he-primary-soft); }
    .mv-avatar { width: 38px; height: 38px; border-radius: 12px; object-fit: cover; flex-shrink: 0; }
    .mv-plan-tag {
        font-size: 0.72rem; font-weight: 700; white-space: nowrap;
        padding: 0.2rem 0.55rem; border-radius: var(--he-radius-full);
        background: var(--he-bg-canvas); color: var(--he-text-muted);
        font-feature-settings: 'tnum';
    }
    .mv-empty { padding: 2rem 1rem; text-align: center; color: var(--he-text-muted); font-size: 0.85rem; }

    /* The chosen student — the search collapses into this. */
    .mv-picked {
        display: flex; align-items: center; gap: 0.75rem;
        padding: 0.6rem 0.7rem;
        background: var(--he-bg-canvas);
        border: 1px solid rgba(0, 0, 0, 0.06);
        border-radius: var(--he-radius-md);
    }

    /* From → To route strip on transfer. */
    .mv-route {
        display: flex; align-items: center; gap: 0.85rem;
        padding: 0.7rem 0.9rem;
        background: var(--he-bg-canvas);
        border-radius: var(--he-radius-md);
    }
    .mv-route-cell { flex: 1 1 0; min-width: 0; display: flex; flex-direction: column; gap: 0.1rem; }
    .mv-route-lbl {
        font-size: 0.6rem; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.06em; color: var(--he-text-muted);
    }
    .mv-ac-chip {
        display: inline-flex; align-items: center; gap: 0.25rem;
        padding: 0.1rem 0.45rem; border-radius: var(--he-radius-full);
        background: var(--he-info-soft); color: var(--he-info);
        font-size: 0.65rem; font-weight: 700; text-transform: uppercase;
    }

    /* Old plan → new plan, stated plainly. */
    .mv-delta {
        display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap;
        padding: 0.55rem 0.85rem;
        background: var(--he-warning-soft, rgba(245, 158, 11, 0.12));
        border-radius: var(--he-radius-md);
        font-size: 0.85rem; font-weight: 700;
        font-feature-settings: 'tnum';
    }
    .mv-delta i { color: var(--he-text-muted); }
    .mv-delta-old { color: var(--he-text-muted); text-decoration: line-through; }
    .mv-delta-new { color: var(--he-warning, #b45309); }

    .mv-note {
        background: var(--he-info-soft); color: var(--he-info);
        border-radius: var(--he-radius-md);
        padding: 0.55rem 0.85rem; font-size: 0.8rem; font-weight: 600;
    }
    .mv-warn {
        background: var(--he-warning-soft, rgba(245, 158, 11, 0.12));
        color: var(--he-warning, #b45309);
        border-radius: var(--he-radius-md);
        padding: 0.55rem 0.85rem; font-size: 0.8rem; font-weight: 600;
    }

    /* Slide-over Panel (Alpine) */
    .slide-over-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.4);
        backdrop-filter: blur(4px);
        z-index: 1040;
    }
    .slide-over-panel {
        position: fixed;
        inset: 0 0 0 auto;
        width: 100%;
        max-width: 450px;
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(30px);
        box-shadow: -10px 0 40px rgba(0,0,0,0.1);
        z-index: 1050;
        display: flex;
        flex-direction: column;
        border-left: 1px solid rgba(255,255,255,0.5);
    }
    .slide-header {
        padding: 1.5rem 2rem;
        border-bottom: 1px solid rgba(0,0,0,0.05);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: rgba(255,255,255,0.5);
    }
    .slide-body {
        padding: 2rem;
        flex: 1;
        overflow-y: auto;
    }
    .slide-footer {
        padding: 1.5rem 2rem;
        background: rgba(248, 250, 252, 0.8);
        border-top: 1px solid rgba(0,0,0,0.05);
        display: flex;
        gap: 1rem;
    }

    .glass-input {
        background: rgba(255,255,255,0.5);
        border: 1px solid rgba(0,0,0,0.1);
        border-radius: 0.75rem;
        padding: 0.75rem 1rem;
        width: 100%;
        transition: all 0.3s;
    }
    .glass-input:focus {
        outline: none;
        border-color: var(--he-primary);
        box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        background: #fff;
    }

    .student-card-preview {
        background: white;
        border-radius: 1.25rem;
        padding: 1.5rem;
        box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        border: 1px solid rgba(0,0,0,0.02);
        text-align: center;
        margin-bottom: 2rem;
    }
    .student-card-preview img {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        object-fit: cover;
        margin-bottom: 1rem;
        box-shadow: 0 8px 16px rgba(0,0,0,0.1);
    }

    [x-cloak] { display: none !important; }

    /* ============================================================
       MOBILE — Property Board reimagined for a phone (≤576px).
       Goal: feel like a native app, not a shrunk website. No
       horizontal scroll anywhere; elements align vertically because
       width is the scarce axis; density over roominess.
       ============================================================ */
    @media (max-width: 576px) {
        /* Header: a real title (bigger, tighter) + subtitle, then ONE
           toolbar row combining search + the builder action — not three
           stacked full-width bars (global search, local search, button)
           reading as a repetitive column of pills. The builder action
           collapses to an icon-only circle, same pattern as the dashboard's
           "View Finances" link. */
        .pb-header {
            flex-direction: column;
            align-items: stretch;
            gap: 0.7rem;
            margin-bottom: 1.25rem;
        }
        /* Mobile page-heading standard (see 01/02 audit docs): heading
           2.2rem/1.5, subheading 1rem/1.5 — big enough to read as a header. */
        .pb-header h1 { font-size: 2.2rem; line-height: 1.5; letter-spacing: -0.01em; margin-bottom: 0.1rem; }
        .pb-header > div:first-child p, .pb-header .text-muted { font-size: 1rem; line-height: 1.5; }
        .pb-toolbar { flex-direction: row; width: 100%; gap: 0.6rem !important; }
        .pb-search-field { width: auto; flex: 1; min-width: 0; }

        .pb-builder-btn {
            width: 46px;
            height: 46px;
            padding: 0 !important;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .pb-builder-ic { margin: 0 !important; font-size: 1.05rem; }
        .pb-builder-text { display: none; }

        /* Stats: a tidy 2×2 widget grid (iOS-widget feel), not 4-across
           and never a horizontal scroll strip. */
        .pb-stats-row {
            grid-template-columns: repeat(2, 1fr);
            gap: 0.7rem;
            margin-bottom: 1.5rem;
        }
        .pb-stat-card {
            padding: 0.85rem;
            gap: 0.7rem;
            border-radius: 1rem;
        }
        .pb-stat-card:hover { transform: none; } /* no hover lift on touch */
        .pb-stat-icon { width: 42px; height: 42px; font-size: 1.15rem; border-radius: 0.8rem; }
        .pb-stat-card .fs-4 { font-size: 1.35rem !important; }
        .pb-stat-card .small { font-size: 0.6rem !important; }

        /* Floors */
        .floor-section { margin-bottom: 1.75rem; }
        .floor-header {
            padding: 0.7rem 0.9rem;
            margin-bottom: 1rem;
            border-radius: 0.85rem;
        }
        .floor-header h2 { font-size: 1.15rem; }
        .floor-header-bar { height: 24px; margin-right: 0.85rem; }
        .floor-header .badge { font-size: 0.58rem !important; padding: 0.35rem 0.6rem !important; }

        /* One room per row (full width) — a 320px min-width grid would
           otherwise overflow a 360px phone. */
        .room-grid { grid-template-columns: 1fr; gap: 1rem; }
        .room-card { padding: 1rem; border-radius: 1rem; }
        .room-header { margin-bottom: 0.85rem; }
        .room-number { font-size: 1.05rem; }
        .room-type { font-size: 0.62rem; padding: 0.28rem 0.7rem; }

        /* Beds go vertical: full-width list rows, one per line. Horizontal
           space is scarce, so we never sit two tiles side-by-side and force
           names/labels to clip. Each bed reads like a clean native list row. */
        .bed-grid { grid-template-columns: 1fr; gap: 0.55rem; }
        .bed-tile {
            height: auto;
            min-height: 52px;
            width: 100%;
            padding: 0.65rem 0.85rem;
            border-radius: 0.8rem;
            justify-content: flex-start;
        }
        .bed-tile:hover { transform: none; box-shadow: none; } /* no hover on touch */
        .bed-tile.bed-empty { justify-content: flex-start; }
        .bed-empty .bed-status-text { font-size: 0.8rem; letter-spacing: 0.3px; }
        .occupant-avatar { width: 38px; height: 38px; }
        .occupant-name { font-size: 0.9rem; }
        .bed-badge { font-size: 0.62rem; }
        /* Maintenance row: lay out horizontally like the others (desktop
           stacks it inside a small square tile — a full row wants a line). */
        .bed-tile.bed-maintenance {
            flex-direction: row;
            justify-content: flex-start;
            align-items: center;
            gap: 0.6rem;
        }
        .bed-maintenance .maintenance-text { font-size: 0.75rem; }
        .bed-maintenance .maintenance-bed-label { margin-left: auto; font-size: 0.72rem; }

        /* The move sheets are canonical .custom-overlay modals, so they're
           already bottom sheets on phones (section 5) — only the student list
           needs reining in so the confirm button stays reachable. */
        .mv-list { max-height: 40vh; }
        .mv-route { flex-wrap: wrap; }

        /* Details slide-over is already full-width; tighten its padding so it
           reads dense like an app screen, not a roomy desktop panel. */
        .slide-header { padding: 1.1rem 1.25rem; }
        .slide-body { padding: 1.25rem; }
        .slide-footer { padding: 1.1rem 1.25rem; }
        .student-card-preview { padding: 1.25rem; margin-bottom: 1.5rem; }

        /* Any native input in this page's modals: 16px to prevent iOS zoom. */
        .glass-input { font-size: 16px; }
    }
</style>
@endpush

@section('content')
<div x-data="propertyBoard(@js($floors->first()?->id), @js($unassignedStudents), @js($vacantBeds), @js(config('hostelease.fee_frequencies')))" class="page-enter pb-5" @keydown.window.escape="closeAssign(); closeTransfer(); releaseOpen = false; bedStatus.open = false; closeDetails()">
    
    <!-- Header -->
    <div class="pb-header flex-wrap gap-3">
        <div>
            <h1 class="h3 fw-bold mb-1">Property Board</h1>
            <p class="text-muted mb-0">Visualize and manage your entire hostel layout instantly.</p>
        </div>
        <div class="pb-toolbar d-flex gap-3 align-items-center">
            <div class="he-search pb-search-field">
                <span class="he-search__icon"><i class="fa-solid fa-search"></i></span>
                <input type="text" class="he-search__input" placeholder="Find room or occupant..." x-model="searchQuery">
            </div>
            <a href="{{ route('admin.floors.index') }}" class="btn btn-light rounded-pill px-4 shadow-sm fw-bold pb-builder-btn">
                <i class="fa-solid fa-hammer me-2 pb-builder-ic"></i><span class="pb-builder-text">Layout Builder</span>
            </a>
        </div>
    </div>

    <!-- Stats -->
    <div class="pb-stats-row stagger">
        <div class="pb-stat-card">
            <div class="pb-stat-icon pb-stat-primary"><i class="fa-solid fa-building"></i></div>
            <div>
                <div class="fs-4 fw-bold">{{ $totalBeds }}</div>
                <div class="text-muted small fw-bold text-uppercase">Total Beds</div>
            </div>
        </div>
        <div class="pb-stat-card">
            <div class="pb-stat-icon pb-stat-success"><i class="fa-solid fa-bed"></i></div>
            <div>
                <div class="fs-4 fw-bold">{{ $vacant }}</div>
                <div class="text-muted small fw-bold text-uppercase">Available</div>
            </div>
        </div>
        <div class="pb-stat-card">
            <div class="pb-stat-icon pb-stat-danger"><i class="fa-solid fa-user-check"></i></div>
            <div>
                <div class="fs-4 fw-bold">{{ $occupied }}</div>
                <div class="text-muted small fw-bold text-uppercase">Occupied</div>
            </div>
        </div>
        <div class="pb-stat-card">
            <div class="pb-stat-icon pb-stat-warning"><i class="fa-solid fa-wrench"></i></div>
            <div>
                <div class="fs-4 fw-bold">{{ $maintenance }}</div>
                <div class="text-muted small fw-bold text-uppercase">Maintenance</div>
            </div>
        </div>
    </div>

    <!-- The Blueprint -->
    <div class="stagger relative">
        @foreach($floors as $floor)
        <div class="floor-section">
            <div class="floor-header">
                <div class="floor-header-bar"></div>
                <h2>{{ $floor->name }}</h2>
                <div class="ms-auto badge bg-light text-muted border border-light-subtle rounded-pill px-3 py-2 fw-bold text-uppercase" style="letter-spacing: 0.5px;">
                    {{ $floor->rooms->count() }} ROOMS TOTAL
                </div>
            </div>
            
            <div class="room-grid">
                @foreach($floor->rooms as $room)
                <div class="room-card" 
                     :class="{ 'dimmed': !roomMatchesSearch('{{ $room->room_number }}', {{ json_encode($room->beds->map(fn($b) => $b->activeAssignment?->student->name)->filter()->values()) }}) }">
                    <div class="room-header">
                        <div class="room-number">Room {{ $room->room_number }}</div>
                        <div class="room-type {{ $room->isAc() ? 'ac' : 'non-ac' }}">{{ $room->isAc() ? 'AC ROOM' : 'NON AC' }}</div>
                    </div>
                    
                    <div class="bed-grid">
                        @foreach($room->beds as $bed)
                            @if(in_array($bed->status, ['available', 'empty', 'reserved']))
                                <div class="bed-tile bed-empty"
                                     @click="openAssign({{ Illuminate\Support\Js::from([
                                         'bedId' => $bed->id,
                                         'room' => (string) $room->room_number,
                                         'bed' => (string) $bed->bed_number,
                                         'isAc' => $room->isAc(),
                                         'lastReading' => $roomFloors[$room->id] ?? null,
                                     ]) }})"
                                     title="Assign a student">
                                     <i class="fa-solid fa-circle-plus"></i>
                                     <span class="bed-status-text">{{ $bed->bed_number }} AVAILABLE</span>
                                </div>
                            @elseif($bed->status === 'maintenance')
                                <div class="bed-tile bed-maintenance"
                                     @click="openBedStatus('{{ $bed->public_id }}', '{{ $room->room_number }}', '{{ $bed->bed_number }}', '{{ $bed->status }}')"
                                     title="Manage Bed">
                                     <div class="d-flex align-items-center gap-2">
                                        <i class="fa-solid fa-wrench"></i>
                                        <span class="maintenance-text">MAINTENANCE</span>
                                     </div>
                                     <span class="maintenance-bed-label">Bed {{ $bed->bed_number }}</span>
                                </div>
                            @elseif($bed->status === 'occupied' && $bed->activeAssignment)
                                @php 
                                    $student = $bed->activeAssignment->student;
                                    $assignment = $bed->activeAssignment;
                                @endphp
                                <div class="bed-tile bed-occupied"
                                     @click="openDetails('{{ $bed->public_id }}', '{{ $room->room_number }}', '{{ $bed->bed_number }}', {{ json_encode([
                                         'assignment_public_id' => $assignment->public_id,
                                         'student_id' => $student->id,
                                         'student_public_id' => $student->public_id,
                                         'student_name' => $student->name,
                                         'student_mobile' => $student->mobile,
                                         'student_photo' => $student->photo_url,
                                         'join_date' => $assignment->join_date->format('d M Y'),
                                         'join_date_raw' => $assignment->join_date->toDateString(),
                                         'duration' => $assignment->durationInDays(),
                                         'room_is_ac' => $room->isAc(),
                                         'room_last_reading' => $roomFloors[$room->id] ?? null,
                                         // The student's CURRENT plan, so a transfer opens
                                         // pre-filled from their profile (W6.4 fix).
                                         'fee_amount' => (float) ($student->fee_amount ?? 0),
                                         'fee_frequency' => $student->fee_frequency ?? '',
                                     ]) }})"
                                     title="{{ $student->name }}">
                                    <img src="{{ $student->photo_url ?: 'https://ui-avatars.com/api/?name='.urlencode($student->name).'&background=fee2e2&color=ef4444' }}" class="occupant-avatar">
                                    <div class="occupant-details">
                                        <div class="occupant-name">{{ strtok($student->name, ' ') }}</div>
                                        <div class="bed-badge-row">
                                            <span class="bed-badge">{{ $bed->bed_number }}</span>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endforeach
    </div>

    {{-- ═══════════════ Assign — the Move sheet (W6.4 rebuild) ═══════════════
         Scrapped: the old "spotlight" (a bespoke panel that existed nowhere
         else) plus the separate Fee-Plan gate that PUT the plan by fetch and
         THEN submitted a hidden form — two round-trips, and a plan could save
         while the assignment failed.

         Now: ONE canonical sheet, ONE atomic POST, progressive disclosure.
         Pick a student and the search collapses into a pill, revealing the
         stay: date, AC meter (required for AC rooms), and the plan — because
         every room has its own cost, so a move is always a re-pricing.

         The plan is PREFILLED from the room's own rent × the frequency
         multiplier, so the usual path is: pick → glance → Confirm. Room and
         sharing PREFERENCES are deliberately absent — they're hints for
         FINDING a room, meaningless once you're pointing at this bed. --}}
    <template x-teleport="body">
        <div class="custom-overlay-backdrop" x-show="assign.open" x-transition.opacity @click="closeAssign()" x-cloak style="display: none;">
            <form method="POST" action="{{ route('admin.property.assign') }}" data-ring-required
                  class="custom-overlay-modal" :class="{ 'is-open': assign.open }" x-show="assign.open" x-transition.opacity @click.stop
                  style="display: none;" @submit="planChipsGuard($event, 'assignFreqChips', assign.frequency) && (assign.submitting = true)">
                @csrf
                <input type="hidden" name="bed_id" :value="assign.bedId">
                <input type="hidden" name="student_id" :value="assign.studentId">

                <div class="custom-overlay-header">
                    <h5 class="fw-bold mb-0">
                        <i class="fa-solid fa-user-plus" style="color: var(--he-primary);"></i>
                        <span class="ms-1">{{ __('Assign Bed') }}</span>
                        <div class="fs-6 fw-normal text-muted mt-1">
                            {{ __('Room') }} <span x-text="assign.room"></span> · {{ __('Bed') }} <span x-text="assign.bed"></span>
                            <span class="mv-ac-chip ms-1" x-show="assign.isAc"><i class="fa-solid fa-snowflake"></i>{{ __('AC') }}</span>
                        </div>
                    </h5>
                    <button type="button" class="btn-close" @click="closeAssign()"></button>
                </div>

                <div class="custom-overlay-body">

                    {{-- ── Step 1: who ── --}}
                    <div class="mb-4" x-show="!assign.student" x-cloak>
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Student') }} <span class="text-danger">*</span></label>
                        <div class="he-search he-search--clearable mb-3">
                            <span class="he-search__icon"><i class="fa-solid fa-magnifying-glass"></i></span>
                            <input type="text" x-model="assign.query" x-ref="assignSearch" class="he-search__input"
                                   placeholder="{{ __('Search an unassigned student…') }}" @keydown.escape="closeAssign()">
                            <button type="button" class="he-search__clear" x-show="assign.query" x-cloak @click="assign.query = ''">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>
                        <div class="mv-list">
                            <template x-for="s in assignCandidates" :key="s.id">
                                <button type="button" class="mv-option" @click="pickStudent(s)">
                                    <img class="mv-avatar" :src="s.photo_url || 'https://ui-avatars.com/api/?name=' + encodeURIComponent(s.name) + '&background=eef2ff&color=4f46e5'" alt="">
                                    <span class="flex-grow-1" style="min-width: 0;">
                                        <span class="d-block fw-bold text-dark text-truncate" x-text="s.name"></span>
                                        <span class="d-block small text-muted text-truncate" x-text="s.mobile"></span>
                                    </span>
                                    <span class="mv-plan-tag flex-shrink-0"
                                          x-text="s.fee_amount > 0 ? '₹' + fmt(s.fee_amount) + ' · ' + s.fee_frequency : '{{ __('no plan yet') }}'"></span>
                                </button>
                            </template>
                            <div class="mv-empty" x-show="assignCandidates.length === 0">
                                <i class="fa-solid fa-user-slash opacity-25 mb-2 d-block fs-3"></i>
                                {{ __('No unassigned students match.') }}
                            </div>
                        </div>
                    </div>

                    {{-- ── Step 2: the stay (revealed once a student is picked) ── --}}
                    <div x-show="assign.student" x-cloak>
                        {{-- Selected student, one click to change --}}
                        <div class="mv-picked mb-4">
                            <img class="mv-avatar" :src="assign.student?.photo_url || 'https://ui-avatars.com/api/?name=' + encodeURIComponent(assign.student?.name ?? '') + '&background=eef2ff&color=4f46e5'" alt="">
                            <span class="flex-grow-1" style="min-width: 0;">
                                <span class="d-block fw-bold text-dark text-truncate" x-text="assign.student?.name"></span>
                                <span class="d-block small text-muted text-truncate" x-text="assign.student?.mobile"></span>
                            </span>
                            <button type="button" class="btn btn-sm btn-white border rounded-pill px-3 fw-semibold flex-shrink-0" @click="clearStudent()">
                                {{ __('Change') }}
                            </button>
                        </div>

                        <div class="row gx-3">
                            <div class="col-md-6 mb-4">
                                {{-- Bed MOVE-IN date (occupancy history + AC-meter anchor), not the
                                     student's registration join date — the first invoice still bills
                                     from the registered join date. Matches the profile Assign sheet. --}}
                                <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Move-in Date') }} <span class="text-danger">*</span></label>
                                <input type="date" name="join_date" x-model="assign.joinDate" class="form-control bg-light" max="{{ now()->toDateString() }}" required>
                            </div>
                            {{-- Required for AC rooms (W6.3): this reading anchors every
                                 future AC bill to real consumption. --}}
                            <div class="col-md-6 mb-4" x-show="assign.isAc">
                                <label class="form-label fw-bold small text-uppercase letter-spacing-1">
                                    <i class="fa-solid fa-bolt text-warning me-1"></i>{{ __('AC Meter Now') }} <span class="text-danger">*</span>
                                </label>
                                <input type="number" name="meter_reading" x-model.number="assign.meterReading" class="form-control bg-light fw-bold"
                                       min="0" step="0.01" :required="assign.isAc" :disabled="!assign.isAc" placeholder="{{ __('Read the room meter') }}">
                                <div class="form-text small" x-show="assign.lastReading !== null && assign.lastReading !== undefined" x-cloak>
                                    {{ __('Last recorded:') }} <span class="fw-bold" x-text="fmt(assign.lastReading)"></span>
                                </div>
                            </div>
                        </div>

                        {{-- Below-floor: warn, then offer the reset override — never
                             a popup, nothing shows on a normal reading (meter-floor). --}}
                        <div class="mb-3" x-show="assignBelowFloor" x-cloak
                             style="background: var(--he-warning-soft, rgba(245,158,11,.12)); color: #b45309; border-radius: var(--he-radius-md); padding: .65rem .8rem; font-size: .82rem; font-weight: 600;">
                            <div><i class="fa-solid fa-triangle-exclamation me-1"></i>{{ __('This reading is below the room’s last recorded meter') }} (<span x-text="fmt(assign.lastReading)"></span>) — {{ __('a meter only counts up.') }}</div>
                            <label class="d-flex align-items-center gap-2 mt-2 mb-0" style="cursor: pointer;">
                                <input type="checkbox" name="meter_reset" value="1" x-model="assign.meterReset" x-ref="assignResetBox" class="form-check-input m-0">
                                <span>{{ __('The meter was reset / replaced — accept this lower reading (this is logged)') }}</span>
                            </label>
                        </div>

                        <hr class="opacity-10 my-4">
                        <h6 class="fw-bold text-muted text-uppercase mb-1" style="font-size: 0.75rem; letter-spacing: 1px;">{{ __('Fee Plan') }}</h6>
                        <p class="text-muted small mb-3" x-text="assign.student?.fee_amount > 0
                            ? '{{ __('Their current plan — keep it or set what they\'ll pay from here.') }}'
                            : '{{ __('What will this student pay, and how often?') }}'"></p>

                        <div class="mb-3">
                            <div class="chip-group" x-ref="assignFreqChips">
                                <template x-for="(label, key) in frequencies" :key="'af-' + key">
                                    <button type="button" class="chip" :class="{ active: assign.frequency === key }"
                                            @click="assign.frequency = key" x-text="label"></button>
                                </template>
                            </div>
                            <input type="hidden" name="fee_frequency" :value="assign.frequency">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Amount') }} <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted fw-bold">₹</span>
                                <input type="number" name="fee_amount" x-model.number="assign.amount" class="form-control bg-light fw-bold text-dark"
                                       min="0" step="0.01" required placeholder="0.00">
                                <span class="input-group-text bg-light text-muted small" x-show="assign.frequency" x-text="perLabel(assign.frequency)"></span>
                            </div>
                        </div>

                        <div class="mv-note" x-show="assign.student && !assign.student.fee_amount" x-cloak>
                            <i class="fa-solid fa-receipt me-1"></i>{{ __('Their first invoice is raised on this plan when you confirm.') }}
                        </div>
                    </div>
                </div>

                <div class="custom-overlay-footer bg-light">
                    <button type="button" class="btn btn-white border fw-semibold rounded-pill px-4 tactile-btn" @click="closeAssign()">{{ __('Cancel') }}</button>
                    {{-- §4.4: a below-floor reading without the reset confirmation
                         RINGS the confirmation instead of failing silently. --}}
                    <button type="submit" class="btn btn-premium fw-semibold rounded-pill px-4 shadow-sm tactile-btn"
                            :disabled="!assign.student || assign.submitting"
                            @click="if (assignBelowFloor && !assign.meterReset) { $event.preventDefault(); window.heRing?.([$refs.assignResetBox], 'primary'); }">
                        <i class="fa-solid fa-check me-2"></i>{{ __('Confirm Assignment') }}
                    </button>
                </div>
            </form>
        </div>
    </template>

    {{-- Bed status (maintenance / available) — its own small sheet now that
         the assign flow no longer carries a footer full of unrelated verbs. --}}
    <template x-teleport="body">
        <div class="custom-overlay-backdrop" x-show="bedStatus.open" x-transition.opacity @click="bedStatus.open = false" x-cloak style="display: none;">
            <div class="custom-overlay-modal" :class="{ 'is-open': bedStatus.open }" x-show="bedStatus.open" x-transition.opacity @click.stop style="display: none; max-width: 420px;">
                <div class="custom-overlay-header">
                    <h5 class="fw-bold mb-0">
                        <i class="fa-solid fa-bed" style="color: var(--he-primary);"></i>
                        <span class="ms-1">{{ __('Bed') }} <span x-text="bedStatus.room"></span>/<span x-text="bedStatus.bed"></span></span>
                    </h5>
                    <button type="button" class="btn-close" @click="bedStatus.open = false"></button>
                </div>
                <div class="custom-overlay-body">
                    <p class="text-muted mb-4">
                        {{ __('This bed is') }} <span class="fw-bold text-warning text-uppercase" x-text="bedStatus.status"></span>.
                        {{ __('Mark it available to assign a student.') }}
                    </p>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-success rounded-pill px-4 fw-bold flex-grow-1"
                                x-show="bedStatus.status === 'maintenance' || bedStatus.status === 'reserved'"
                                @click="markBedStatus('empty')">
                            <i class="fa-solid fa-check me-1"></i> {{ __('Mark as Available') }}
                        </button>
                        <button type="button" class="btn btn-outline-warning rounded-pill px-4 fw-bold flex-grow-1"
                                x-show="bedStatus.status === 'empty' || bedStatus.status === 'available'"
                                @click="markBedStatus('maintenance')">
                            <i class="fa-solid fa-wrench me-1"></i> {{ __('Mark as Maintenance') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </template>

    <!-- Hidden Form for Status Update -->
    <form id="statusForm" :action="'/admin/beds/' + bedStatus.bedId + '/status'" method="POST" class="d-none">
        @csrf @method('PATCH')
        <input type="hidden" name="status" id="statusInput">
    </form>

    <!-- Bed & Occupant Details Slide-over -->
    <template x-teleport="body">
        <div x-show="panels.details.open" class="slide-over-backdrop" x-cloak x-transition.opacity></div>
    </template>
    <template x-teleport="body">
        <div x-show="panels.details.open" class="slide-over-panel" x-cloak
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="transform translate-x-full"
             x-transition:enter-end="transform translate-x-0"
             x-transition:leave="transition ease-in duration-300"
             x-transition:leave-start="transform translate-x-0"
             x-transition:leave-end="transform translate-x-full"
             @click.away="closeDetails()">
            
            <div class="d-flex flex-column h-100">
                <div class="slide-header">
                    <div>
                        <h4 class="fw-bold mb-0">Occupant Details</h4>
                        <div class="text-danger fw-bold mt-1">Room <span x-text="panels.details.room"></span> &bull; Bed <span x-text="panels.details.bed"></span></div>
                    </div>
                    <button type="button" class="btn-close" @click="closeDetails()"></button>
                </div>
                
                <div class="slide-body">
                    <div class="student-card-preview">
                        <img :src="panels.details.data.student_photo" alt="Photo">
                        <h4 class="fw-bold mb-1" x-text="panels.details.data.student_name"></h4>
                        <div class="text-muted small mb-3" x-text="panels.details.data.student_mobile"></div>
                        
                        <div class="d-flex justify-content-center gap-3 text-start bg-light rounded-3 p-3 mt-3">
                            <div>
                                <div class="small text-muted text-uppercase fw-bold">Joined On</div>
                                <div class="fw-bold" x-text="panels.details.data.join_date"></div>
                            </div>
                            <div class="border-start ps-3">
                                <div class="small text-muted text-uppercase fw-bold">Stay Duration</div>
                                <div class="fw-bold"><span x-text="panels.details.data.duration"></span> days</div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <a :href="'/admin/students/' + panels.details.data.student_public_id" class="btn btn-outline-primary rounded-pill w-100 fw-bold">
                                View Full Profile <i class="fa-solid fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>

                    <div class="d-flex flex-column gap-2 mt-auto">
                        <h6 class="text-uppercase text-muted fw-bold small mb-2">Quick Actions</h6>
                        
                        <button type="button" class="btn btn-light text-start p-3 rounded-4 fw-bold d-flex align-items-center" @click="openTransfer()">
                            <div class="bg-primary text-white rounded-3 p-2 me-3"><i class="fa-solid fa-right-left"></i></div>
                            <div>
                                <div class="fs-6 text-primary">Transfer Bed</div>
                                <div class="small text-muted fw-normal">Move to a different room</div>
                            </div>
                        </button>
                        
                        <button type="button" class="btn btn-light text-start p-3 rounded-4 fw-bold d-flex align-items-center" @click="openRelease()">
                            <div class="bg-danger text-white rounded-3 p-2 me-3"><i class="fa-solid fa-right-from-bracket"></i></div>
                            <div>
                                <div class="fs-6 text-danger">Release Student</div>
                                <div class="small text-muted fw-normal">Vacate bed and keep history</div>
                            </div>
                        </button>
                        
                        <a :href="'/admin/beds/' + panels.details.bedId + '/history'" class="btn btn-light text-start p-3 rounded-4 fw-bold d-flex align-items-center">
                            <div class="bg-secondary text-white rounded-3 p-2 me-3"><i class="fa-solid fa-clock-rotate-left"></i></div>
                            <div>
                                <div class="fs-6">View Bed History</div>
                                <div class="small text-muted fw-normal">See everyone who stayed here</div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </template>

    {{-- ═══════════════ Transfer — the Move sheet (W6.4 rebuild) ═══════════
         Scrapped: the raw <select> of every vacant bed and a form that never
         asked what the move actually costs — which is why a student could be
         moved from a ₹5,000 Non-AC room into an ₹8,000 AC room and keep being
         billed ₹5,000 forever.

         Now: a real picker (room, floor, AC, rent, sharing), both AC meters
         when they apply, and the plan RE-PRICED from the destination room —
         with the old→new delta stated plainly. Progressive disclosure: the
         stay only appears once a destination is chosen. --}}
    <template x-teleport="body">
        <div class="custom-overlay-backdrop" x-show="transfer.open" x-transition.opacity @click="closeTransfer()" x-cloak style="display: none;">
            <form class="custom-overlay-modal" :class="{ 'is-open': transfer.open }" x-show="transfer.open" x-transition.opacity @click.stop data-ring-required
                  :action="'/admin/property/assignments/' + panels.details.data.assignment_public_id + '/transfer'" method="POST"
                  style="display: none;" @submit="planChipsGuard($event, 'transferFreqChips', transfer.frequency) && (transfer.submitting = true)">
                @csrf @method('PATCH')
                <input type="hidden" name="bed_id" :value="transfer.bedId">

                <div class="custom-overlay-header">
                    <h5 class="fw-bold mb-0">
                        <i class="fa-solid fa-right-left" style="color: var(--he-primary);"></i>
                        <span class="ms-1">{{ __('Transfer Student') }}</span>
                        <div class="fs-6 fw-normal text-muted mt-1" x-text="panels.details.data.student_name"></div>
                    </h5>
                    <button type="button" class="btn-close" @click="closeTransfer()"></button>
                </div>

                <div class="custom-overlay-body">
                    {{-- From → To --}}
                    <div class="mv-route mb-4">
                        <div class="mv-route-cell">
                            <span class="mv-route-lbl">{{ __('From') }}</span>
                            <span class="fw-bold text-dark">
                                {{ __('Room') }} <span x-text="panels.details.room"></span>/<span x-text="panels.details.bed"></span>
                                <span class="mv-ac-chip" x-show="panels.details.data.room_is_ac"><i class="fa-solid fa-snowflake"></i>{{ __('AC') }}</span>
                            </span>
                        </div>
                        <i class="fa-solid fa-arrow-right-long text-muted flex-shrink-0"></i>
                        <div class="mv-route-cell">
                            <span class="mv-route-lbl">{{ __('To') }}</span>
                            <span class="fw-bold" :class="transfer.target ? 'text-dark' : 'text-muted'">
                                <template x-if="transfer.target">
                                    <span>
                                        {{ __('Room') }} <span x-text="transfer.target.room"></span>/<span x-text="transfer.target.bed"></span>
                                        <span class="mv-ac-chip" x-show="transfer.target.is_ac"><i class="fa-solid fa-snowflake"></i>{{ __('AC') }}</span>
                                    </span>
                                </template>
                                <span x-show="!transfer.target">{{ __('choose below') }}</span>
                            </span>
                        </div>
                    </div>

                    {{-- Destination picker --}}
                    <div class="mb-4">
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('New Bed') }} <span class="text-danger">*</span></label>
                        <div class="he-picker" :class="{ 'is-open': transfer.pickerOpen }" @click.outside.capture="transfer.pickerOpen = false">
                            <button type="button" class="he-picker-trigger" x-ref="transferTrigger" @click="toggleTransferPicker()">
                                <span class="d-flex align-items-center gap-2 text-truncate">
                                    <span class="he-picker-avatar" style="width: 28px; height: 28px; font-size: 0.75rem;" x-show="transfer.target"><i class="fa-solid fa-bed"></i></span>
                                    <span :class="transfer.target ? 'fw-semibold text-dark' : 'text-muted'" x-text="transferLabel"></span>
                                </span>
                                <i class="fa-solid fa-chevron-down chevron"></i>
                            </button>
                            <div class="he-picker-panel" x-ref="transferPanel" x-show="transfer.pickerOpen" x-transition.opacity x-cloak style="display: none;">
                                <div class="he-picker-search">
                                    <input type="text" x-model="transfer.query" x-ref="transferSearch"
                                           class="form-control form-control-sm bg-light border-0" placeholder="{{ __('Search room, bed or floor…') }}">
                                </div>
                                <div class="he-picker-list">
                                    <template x-for="b in transferCandidates" :key="b.id">
                                        <button type="button" class="he-picker-option" @click="pickTargetBed(b)">
                                            <span class="he-picker-avatar" :style="b.is_ac ? 'background: var(--he-info-soft); color: var(--he-info);' : ''">
                                                <i class="fa-solid" :class="b.is_ac ? 'fa-snowflake' : 'fa-bed'"></i>
                                            </span>
                                            {{-- No price here: a room's rent is NOT the
                                                 student's fee (owner — the fee is asked, not
                                                 derived). The picker shows only what helps
                                                 choose a bed: where it is, how full, AC or not. --}}
                                            <span class="flex-grow-1" style="min-width: 0;">
                                                <span class="d-block fw-bold text-dark text-truncate">
                                                    {{ __('Room') }} <span x-text="b.room"></span> · <span x-text="b.bed"></span>
                                                </span>
                                                <span class="d-block small text-muted text-truncate"
                                                      x-text="(b.floor ?? '') + (b.sharing ? ' · ' + b.sharing + '-{{ __('sharing') }}' : '')"></span>
                                            </span>
                                            <span class="text-end flex-shrink-0 small" style="line-height: 1.25;">
                                                <span class="d-block fw-bold" :class="b.is_ac ? 'text-info' : 'text-muted'" x-text="b.is_ac ? '{{ __('AC') }}' : '{{ __('Non-AC') }}'"></span>
                                            </span>
                                        </button>
                                    </template>
                                    <div class="he-picker-empty" x-show="transferCandidates.length === 0">{{ __('No vacant beds match') }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- The stay — revealed once a destination exists --}}
                    <div x-show="transfer.target" x-cloak>
                        <div class="row gx-3">
                            <div class="col-md-6 mb-4">
                                <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Move Date') }} <span class="text-danger">*</span></label>
                                <input type="date" name="join_date" x-model="transfer.date" class="form-control bg-light" required>
                            </div>
                        </div>

                        {{-- Two meters can be involved (W6.3): the room being left caps
                             this student's AC share there; the room being entered
                             starts them at the real number. --}}
                        <div class="row gx-3" x-show="panels.details.data.room_is_ac || transfer.target?.is_ac">
                            <div class="col-md-6 mb-4" x-show="panels.details.data.room_is_ac">
                                <label class="form-label fw-bold small text-uppercase letter-spacing-1">
                                    <i class="fa-solid fa-bolt text-warning me-1"></i>{{ __('Old Room Meter') }} <span class="text-danger">*</span>
                                </label>
                                <input type="number" name="old_meter_reading" x-model.number="transfer.oldMeter" min="0" step="0.01" class="form-control bg-light fw-bold"
                                       :required="panels.details.data.room_is_ac" :disabled="!panels.details.data.room_is_ac"
                                       placeholder="{{ __('Meter of the room being left') }}">
                                <div class="form-text small" x-show="transferOldFloor !== null" x-cloak>
                                    {{ __('Last recorded:') }} <span class="fw-bold" x-text="fmt(transferOldFloor)"></span>
                                </div>
                            </div>
                            <div class="col-md-6 mb-4" x-show="transfer.target?.is_ac">
                                <label class="form-label fw-bold small text-uppercase letter-spacing-1">
                                    <i class="fa-solid fa-bolt text-warning me-1"></i>{{ __('New Room Meter') }} <span class="text-danger">*</span>
                                </label>
                                <input type="number" name="meter_reading" x-model.number="transfer.newMeter" min="0" step="0.01" class="form-control bg-light fw-bold"
                                       :required="transfer.target?.is_ac" :disabled="!transfer.target?.is_ac"
                                       placeholder="{{ __('Meter of the new room') }}">
                                <div class="form-text small" x-show="transferNewFloor !== null" x-cloak>
                                    {{ __('Last recorded:') }} <span class="fw-bold" x-text="fmt(transferNewFloor)"></span>
                                </div>
                            </div>
                        </div>

                        {{-- Below-floor warnings — one per meter, each with its own
                             reset confirmation (a transfer touches TWO meters). --}}
                        <div class="mb-3" x-show="transferBelowNewFloor" x-cloak
                             style="background: var(--he-warning-soft, rgba(245,158,11,.12)); color: #b45309; border-radius: var(--he-radius-md); padding: .65rem .8rem; font-size: .82rem; font-weight: 600;">
                            <div><i class="fa-solid fa-triangle-exclamation me-1"></i>{{ __('New room reading is below its last recorded meter') }} (<span x-text="fmt(transferNewFloor)"></span>) — {{ __('a meter only counts up.') }}</div>
                            <label class="d-flex align-items-center gap-2 mt-2 mb-0" style="cursor: pointer;">
                                <input type="checkbox" name="meter_reset" value="1" x-model="transfer.meterReset" x-ref="transferResetBox" class="form-check-input m-0">
                                <span>{{ __('That meter was reset / replaced — accept this lower reading (this is logged)') }}</span>
                            </label>
                        </div>
                        <div class="mb-3" x-show="transferBelowOldFloor" x-cloak
                             style="background: var(--he-warning-soft, rgba(245,158,11,.12)); color: #b45309; border-radius: var(--he-radius-md); padding: .65rem .8rem; font-size: .82rem; font-weight: 600;">
                            <div><i class="fa-solid fa-triangle-exclamation me-1"></i>{{ __('Old room reading is below its last recorded meter') }} (<span x-text="fmt(transferOldFloor)"></span>) — {{ __('a meter only counts up.') }}</div>
                            <label class="d-flex align-items-center gap-2 mt-2 mb-0" style="cursor: pointer;">
                                <input type="checkbox" name="old_meter_reset" value="1" x-model="transfer.oldMeterReset" x-ref="transferOldResetBox" class="form-check-input m-0">
                                <span>{{ __('That meter was reset / replaced — accept this lower reading (this is logged)') }}</span>
                            </label>
                        </div>

                        <hr class="opacity-10 my-4">
                        <h6 class="fw-bold text-muted text-uppercase mb-1" style="font-size: 0.75rem; letter-spacing: 1px;">{{ __('Fee Plan') }}</h6>
                        <p class="text-muted small mb-3">{{ __('Their current plan — what will they pay from here on?') }}</p>

                        {{-- The change, stated plainly. --}}
                        <div class="mv-delta mb-3" x-show="planChanged" x-cloak>
                            <span class="mv-delta-old" x-text="'₹' + fmt(transfer.oldAmount) + ' ' + perLabel(transfer.oldFrequency)"></span>
                            <i class="fa-solid fa-arrow-right-long"></i>
                            <span class="mv-delta-new" x-text="'₹' + fmt(transfer.amount || 0) + ' ' + perLabel(transfer.frequency)"></span>
                        </div>

                        <div class="mb-3">
                            <div class="chip-group" x-ref="transferFreqChips">
                                <template x-for="(label, key) in frequencies" :key="'tf-' + key">
                                    <button type="button" class="chip" :class="{ active: transfer.frequency === key }"
                                            @click="transfer.frequency = key" x-text="label"></button>
                                </template>
                            </div>
                            <input type="hidden" name="fee_frequency" :value="transfer.frequency">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">{{ __('Amount') }} <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted fw-bold">₹</span>
                                <input type="number" name="fee_amount" x-model.number="transfer.amount" class="form-control bg-light fw-bold text-dark"
                                       min="0" step="0.01" required placeholder="0.00">
                                <span class="input-group-text bg-light text-muted small" x-show="transfer.frequency" x-text="perLabel(transfer.frequency)"></span>
                            </div>
                        </div>

                        {{-- Unchanged plan: a nudge, not a blocker — plenty of moves
                             are same-price, but this step exists precisely because
                             rooms usually aren't. --}}
                        <div class="mv-warn" x-show="planUnchangedWarning" x-cloak>
                            <i class="fa-solid fa-triangle-exclamation me-1"></i>{{ __('The plan is unchanged. If this room costs differently, update it now — billing follows this number.') }}
                        </div>

                        {{-- Owner decision (W6.4): plan-forward-only. Say so, so the
                             money behaviour is never a surprise. --}}
                        <div class="mv-note" x-show="planChanged" x-cloak>
                            <i class="fa-solid fa-circle-info me-1"></i>{{ __('The new rate applies from their next billing cycle. Existing invoices are left exactly as issued — no refund, no new bill.') }}
                        </div>
                    </div>
                </div>

                <div class="custom-overlay-footer bg-light">
                    <button type="button" class="btn btn-white border fw-semibold rounded-pill px-4 tactile-btn" @click="closeTransfer()">{{ __('Cancel') }}</button>
                    {{-- §4.4: ring the unanswered reset confirmation(s), never fail silently. --}}
                    <button type="submit" class="btn btn-premium fw-semibold rounded-pill px-4 shadow-sm tactile-btn"
                            :disabled="!transfer.target || transfer.submitting"
                            @click="const boxes = []; if (transferBelowNewFloor && !transfer.meterReset) boxes.push($refs.transferResetBox); if (transferBelowOldFloor && !transfer.oldMeterReset) boxes.push($refs.transferOldResetBox); if (boxes.length) { $event.preventDefault(); window.heRing?.(boxes, 'primary'); }">
                        <i class="fa-solid fa-right-left me-2"></i>{{ __('Transfer Now') }}
                    </button>
                </div>
            </form>
        </div>
    </template>

    <template x-teleport="body">
        <div class="custom-overlay-backdrop" x-show="releaseOpen" x-transition.opacity @click="releaseOpen = false" x-cloak style="display: none;">
            <form class="custom-overlay-modal" :class="{ 'is-open': releaseOpen }" x-show="releaseOpen" @click.stop data-ring-required
                  :action="'/admin/property/assignments/' + panels.details.data.assignment_public_id + '/release'" method="POST" style="display: none; max-width: 480px;">
                @csrf @method('PATCH')
                <div class="custom-overlay-header">
                    <h5 class="fw-bold mb-0 text-danger">
                        <i class="fa-solid fa-right-from-bracket"></i>
                        <span class="ms-1">Release Student</span>
                    </h5>
                    <button type="button" class="btn-close" @click="releaseOpen = false"></button>
                </div>
                <div class="custom-overlay-body">
                    <p class="text-muted mb-4">Release <strong x-text="panels.details.data.student_name"></strong> from bed <strong x-text="panels.details.bed"></strong>? The bed will become available immediately.</p>

                    <div class="mb-4">
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1 d-block mb-2">Leave Date</label>
                        <input type="date" name="leave_date" class="form-control bg-light" value="{{ now()->toDateString() }}" required :min="panels.details.data.join_date_raw">
                    </div>

                    {{-- W6.3: the meter at move-out caps this student's AC share
                         at what they were actually present for. --}}
                    <div class="mb-4" x-show="panels.details.data.room_is_ac" x-cloak>
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1 d-block mb-2">
                            <i class="fa-solid fa-bolt text-warning me-1"></i>AC Meter Reading Now <span class="text-danger">*</span>
                        </label>
                        <input type="number" name="meter_reading" x-model.number="releaseMeter" min="0" step="0.01" class="form-control bg-light"
                               :required="panels.details.data.room_is_ac" :disabled="!panels.details.data.room_is_ac"
                               placeholder="Read the room's meter before releasing">
                        <div class="form-text small" x-show="releaseFloor !== null" x-cloak>
                            {{ __('Last recorded:') }} <span class="fw-bold" x-text="fmt(releaseFloor)"></span>
                        </div>
                    </div>

                    {{-- Below-floor: warn, then offer the reset override (meter-floor). --}}
                    <div class="mb-4" x-show="releaseBelowFloor" x-cloak
                         style="background: var(--he-warning-soft, rgba(245,158,11,.12)); color: #b45309; border-radius: var(--he-radius-md); padding: .65rem .8rem; font-size: .82rem; font-weight: 600;">
                        <div><i class="fa-solid fa-triangle-exclamation me-1"></i>{{ __('This reading is below the room’s last recorded meter') }} (<span x-text="fmt(releaseFloor)"></span>) — {{ __('a meter only counts up.') }}</div>
                        <label class="d-flex align-items-center gap-2 mt-2 mb-0" style="cursor: pointer;">
                            <input type="checkbox" name="meter_reset" value="1" x-model="releaseReset" x-ref="releaseResetBox" class="form-check-input m-0">
                            <span>{{ __('The meter was reset / replaced — accept this lower reading (this is logged)') }}</span>
                        </label>
                    </div>

                    <label class="form-check p-3 bg-danger-subtle rounded-3 d-flex align-items-center m-0" for="markLeft" style="cursor: pointer;">
                        <input class="form-check-input m-0 flex-shrink-0" type="checkbox" name="mark_student_left" value="1" id="markLeft" checked style="width: 1.25rem; height: 1.25rem;">
                        <span class="ms-3 text-danger fw-bold lh-sm">Also mark student as "Left" (Vacating Hostel entirely)</span>
                    </label>
                </div>
                <div class="custom-overlay-footer">
                    <button type="button" class="btn btn-white border fw-semibold rounded-pill px-4 tactile-btn" @click="releaseOpen = false">Cancel</button>
                    {{-- §4.4: ring the unanswered reset confirmation, never fail silently. --}}
                    <button type="submit" class="btn btn-danger fw-semibold rounded-pill px-4 shadow-sm tactile-btn"
                            @click="if (releaseBelowFloor && !releaseReset) { $event.preventDefault(); window.heRing?.([$refs.releaseResetBox], 'primary'); }">
                        <i class="fa-solid fa-right-from-bracket me-2"></i>Release Student
                    </button>
                </div>
            </form>
        </div>
    </template>

</div>
@endsection

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    /**
     * Property Board (W6.4 rebuild).
     *
     * Assign, transfer and release are the same event — an occupancy change —
     * so they share one grammar here: pick a destination/student, then
     * confirm the stay (date · AC meter · plan). Each move is ALSO a
     * re-pricing, because each room has its own cost: the plan is prefilled
     * from the room's own rent and posted with the move, atomically.
     *
     * Gone with the old flow: the bespoke "spotlight" panel, the fee-gate's
     * fetch-then-submit double round-trip, and room/sharing PREFERENCES —
     * which are hints for FINDING a room and mean nothing once you're
     * pointing at a specific bed. They live on the student profile.
     */
    Alpine.data('propertyBoard', (firstFloorId, studentsList, vacantBeds, frequencies) => ({
        activeFloorId: firstFloorId,
        searchQuery: '',
        students: studentsList,
        vacantBeds,
        frequencies,

        // --- Assign sheet ---
        assign: {
            open: false, submitting: false,
            bedId: '', room: '', bed: '', isAc: false,
            query: '', student: null, studentId: '',
            joinDate: @json(now()->toDateString()),
            meterReading: '',
            frequency: 'monthly', amount: '',
        },

        // --- Transfer sheet ---
        transfer: {
            open: false, submitting: false,
            pickerOpen: false, query: '', target: null, bedId: '',
            date: @json(now()->toDateString()),
            frequency: 'monthly', amount: '',
            oldAmount: 0, oldFrequency: 'monthly',
        },

        // --- Bed status sheet ---
        bedStatus: { open: false, bedId: '', room: '', bed: '', status: '' },

        panels: {
            details: {
                open: false, bedId: '', room: '', bed: '',
                data: { assignment_public_id: '', student_id: '', student_public_id: '', student_name: '', student_mobile: '', student_photo: '', join_date: '', join_date_raw: '', duration: '', room_is_ac: false, room_last_reading: null, fee_amount: 0, fee_frequency: '' }
            }
        },

        releaseOpen: false,
        releaseMeter: '',
        releaseReset: false,

        // ── Shared plan helpers ──
        // There is ONE plan per student: what they pay, and how often. It is
        // ASKED, never derived — a fee is not room rent × a multiplier, and
        // the system has no business inventing an amount nobody agreed to.
        // Existing values are shown so they can be kept or changed.
        perLabel(freq) {
            return freq === 'yearly' ? @json(__('/ year')) : (freq === 'semester' ? @json(__('/ semester')) : @json(__('/ month')));
        },
        fmt(v) { return Number(v || 0).toLocaleString('en-IN', { maximumFractionDigits: 2 }); },

        // ── Meter floors (meter-floor, 2026-07-18): a meter only counts up.
        // Floors ride the bed/room payloads; typing below one reveals the
        // warn + reset confirmation. Nothing shows on a normal reading.
        belowFloor(value, floor) {
            return floor !== null && floor !== undefined && value !== null && value !== '' && value !== undefined
                && Number(value) < Number(floor) - 0.005;
        },
        get assignBelowFloor() {
            return this.assign.isAc && this.belowFloor(this.assign.meterReading, this.assign.lastReading);
        },
        get transferNewFloor() { return this.transfer.target?.is_ac ? (this.transfer.target.last_reading ?? null) : null; },
        get transferOldFloor() { return this.panels.details.data.room_is_ac ? (this.panels.details.data.room_last_reading ?? null) : null; },
        get transferBelowNewFloor() { return this.belowFloor(this.transfer.newMeter, this.transferNewFloor); },
        get transferBelowOldFloor() { return this.belowFloor(this.transfer.oldMeter, this.transferOldFloor); },
        get releaseFloor() { return this.panels.details.data.room_is_ac ? (this.panels.details.data.room_last_reading ?? null) : null; },
        get releaseBelowFloor() { return this.belowFloor(this.releaseMeter, this.releaseFloor); },

        // The frequency chips aren't a validatable input (the value posts via
        // a hidden field), so an empty one can't ring itself — §4.4's
        // dependency case: ring the chips rather than fail silently.
        planChipsGuard(event, chipsRef, freq) {
            if (freq) return true;
            event.preventDefault();
            window.heRing?.(this.$refs[chipsRef]?.querySelectorAll('.chip') ?? [], 'danger');
            return false;
        },

        roomMatchesSearch(roomNum, occupants) {
            if (!this.searchQuery) return true;
            const q = this.searchQuery.toLowerCase().trim();
            if (roomNum.toLowerCase().includes(q)) return true;
            return occupants.some(name => name.toLowerCase().includes(q));
        },

        // ── Assign ──
        get assignCandidates() {
            const q = this.assign.query.trim().toLowerCase();
            if (!q) return this.students;
            return this.students.filter(s => s.name.toLowerCase().includes(q) || (s.mobile && s.mobile.includes(q)));
        },

        openAssign(bed) {
            this.assign = {
                ...this.assign,
                open: true, submitting: false,
                bedId: bed.bedId, room: bed.room, bed: bed.bed, isAc: bed.isAc,
                lastReading: bed.lastReading ?? null, meterReset: false,
                query: '', student: null, studentId: '',
                joinDate: @json(now()->toDateString()),
                meterReading: '',
                frequency: '', amount: '',
            };
            document.body.style.overflow = 'hidden';
            this.$nextTick(() => this.$refs.assignSearch?.focus());
        },
        closeAssign() {
            this.assign.open = false;
            document.body.style.overflow = '';
        },
        pickStudent(s) {
            this.assign.student = s;
            this.assign.studentId = s.id;
            // Show what they already have, if anything — otherwise it stays
            // blank and gets asked. Nothing is computed on their behalf.
            this.assign.frequency = s.fee_frequency || '';
            this.assign.amount = s.fee_amount > 0 ? Number(s.fee_amount) : '';
        },
        clearStudent() {
            this.assign.student = null;
            this.assign.studentId = '';
            this.$nextTick(() => this.$refs.assignSearch?.focus());
        },

        // ── Transfer ──
        get transferCandidates() {
            const q = this.transfer.query.trim().toLowerCase();
            if (!q) return this.vacantBeds;
            return this.vacantBeds.filter(b =>
                b.room.toLowerCase().includes(q)
                || b.bed.toLowerCase().includes(q)
                || (b.floor ?? '').toLowerCase().includes(q));
        },
        get transferLabel() {
            if (!this.transfer.target) return @json(__('Choose a vacant bed…'));
            const t = this.transfer.target;
            // Room · bed · AC-ness — never rent (a room's rent isn't the fee).
            return @json(__('Room')) + ' ' + t.room + ' · ' + t.bed + ' · ' + (t.is_ac ? @json(__('AC')) : @json(__('Non-AC')));
        },
        get planChanged() {
            return this.transfer.target
                && (Number(this.transfer.amount) !== Number(this.transfer.oldAmount)
                    || this.transfer.frequency !== this.transfer.oldFrequency);
        },
        // Not a blocker — plenty of moves are same-price. Just don't let an
        // unchanged plan slip past unnoticed, since the whole reason this
        // step exists is that rooms cost different amounts.
        get planUnchangedWarning() {
            return this.transfer.target && this.transfer.amount !== '' && !this.planChanged;
        },

        openTransfer() {
            const d = this.panels.details.data;
            this.closeDetails();
            this.transfer = {
                ...this.transfer,
                open: true, submitting: false,
                pickerOpen: false, query: '', target: null, bedId: '',
                oldMeter: '', newMeter: '', meterReset: false, oldMeterReset: false,
                date: @json(now()->toDateString()),
                // Their current plan, shown as-is to keep or change. Not a
                // suggestion, not derived from anything — just what's on file.
                oldAmount: Number(d.fee_amount) || 0,
                oldFrequency: d.fee_frequency || '',
                frequency: d.fee_frequency || '',
                amount: Number(d.fee_amount) > 0 ? Number(d.fee_amount) : '',
            };
            document.body.style.overflow = 'hidden';
        },
        closeTransfer() {
            this.transfer.open = false;
            document.body.style.overflow = '';
        },
        toggleTransferPicker() {
            this.transfer.pickerOpen = !this.transfer.pickerOpen;
            // Measure once visible (law 4.7): open into the space that
            // exists — never grow the page a scrollbar.
            if (this.transfer.pickerOpen) this.$nextTick(() => {
                window.hePlaceMenu?.(this.$refs.transferTrigger, this.$refs.transferPanel);
                this.$refs.transferSearch?.focus();
            });
        },
        pickTargetBed(b) {
            this.transfer.target = b;
            this.transfer.bedId = b.id;
            this.transfer.pickerOpen = false;
        },

        // ── Bed status ──
        openBedStatus(bedId, room, bed, status) {
            this.bedStatus = { open: true, bedId, room, bed, status };
        },
        markBedStatus(status) {
            document.getElementById('statusInput').value = status;
            this.$nextTick(() => document.getElementById('statusForm').submit());
        },

        // ── Occupant details ──
        openDetails(bedId, room, bed, data) {
            this.panels.details.bedId = bedId;
            this.panels.details.room = room;
            this.panels.details.bed = bed;
            this.panels.details.data = data;
            this.panels.details.open = true;
        },
        closeDetails() {
            this.panels.details.open = false;
        },
        openRelease() {
            this.closeDetails();
            this.releaseMeter = '';
            this.releaseReset = false;
            this.releaseOpen = true;
        }
    }));
});
</script>
@endpush
