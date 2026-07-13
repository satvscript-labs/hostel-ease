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

    /* Spotlight Assign Modal */
    .spotlight-backdrop {
        position: fixed; inset: 0; background: rgba(15,23,42,0.6); backdrop-filter: blur(8px); z-index: 2000;
        display: flex; align-items: flex-start; justify-content: center; padding-top: 10vh;
    }
    .spotlight-panel {
        width: 100%; max-width: 600px; background: rgba(255,255,255,0.95); backdrop-filter: blur(30px);
        border-radius: 1.5rem; box-shadow: 0 25px 50px rgba(0,0,0,0.25); overflow: hidden;
        border: 1px solid rgba(255,255,255,0.5);
    }
    .spotlight-input-container { position: relative; border-bottom: 1px solid rgba(0,0,0,0.1); }
    .spotlight-input {
        width: 100%; padding: 1.5rem 1.5rem 1.5rem 4rem; font-size: 1.25rem; border: none; background: transparent; outline: none;
    }
    .spotlight-input-container i { position: absolute; left: 1.5rem; top: 50%; transform: translateY(-50%); font-size: 1.25rem; color: var(--he-primary); }
    .spotlight-results { max-height: 400px; overflow-y: auto; padding: 0.5rem; }
    .spotlight-item {
        display: flex; align-items: center; gap: 1rem; padding: 1rem; border-radius: 1rem; cursor: pointer; transition: all 0.2s;
    }
    .spotlight-item:hover { background: rgba(79, 70, 229, 0.1); }
    .spotlight-avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }

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

        /* Spotlight quick-assign: near full-width sheet from the top,
           inputs at 16px so iOS doesn't zoom. */
        .spotlight-backdrop { padding-top: 6vh; padding-left: 0.75rem; padding-right: 0.75rem; }
        .spotlight-panel { max-width: 100%; border-radius: 1.25rem; }
        .spotlight-input { font-size: 16px; padding: 1.15rem 1.15rem 1.15rem 3.25rem; }
        .spotlight-input-container i { left: 1.15rem; font-size: 1.1rem; }
        .spotlight-results { max-height: 55vh; }
        .spotlight-item { padding: 0.75rem; }

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
<div x-data="propertyBoard(@js($floors->first()?->id), @js($unassignedStudents), {{ (int) hostelease_max_room_sharing() }}, @js(hostelease_sharing_labels()))" class="page-enter pb-5" @keydown.window.escape="spotlight.open = false; transferOpen = false; releaseOpen = false; feeGate.open = false; closeDetails()">
    
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
                                     @click="openSpotlight('{{ $bed->id }}', '{{ $room->room_number }}', '{{ $bed->bed_number }}', '{{ $bed->status }}')"
                                     title="Manage Bed">
                                     <i class="fa-solid fa-circle-plus"></i>
                                     <span class="bed-status-text">{{ $bed->bed_number }} AVAILABLE</span>
                                </div>
                            @elseif($bed->status === 'maintenance')
                                <div class="bed-tile bed-maintenance" 
                                     @click="openSpotlight('{{ $bed->id }}', '{{ $room->room_number }}', '{{ $bed->bed_number }}', '{{ $bed->status }}')"
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
                                     @click="openDetails({{ $bed->id }}, '{{ $room->room_number }}', '{{ $bed->bed_number }}', {{ json_encode([
                                         'assignment_id' => $assignment->id,
                                         'student_id' => $student->id,
                                         'student_name' => $student->name,
                                         'student_mobile' => $student->mobile,
                                         'student_photo' => $student->photo_url,
                                         'join_date' => $assignment->join_date->format('d M Y'),
                                         'join_date_raw' => $assignment->join_date->toDateString(),
                                         'duration' => $assignment->durationInDays(),
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

    <!-- Spotlight Quick Assign Modal -->
    <template x-teleport="body">
        <div x-show="spotlight.open" class="spotlight-backdrop" x-cloak x-transition.opacity>
            <div class="spotlight-panel" @click.away="spotlight.open = false" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 transform scale-95" x-transition:enter-end="opacity-100 transform scale-100">
                <div class="bg-primary text-white p-3 text-center fw-bold">
                    Managing Bed <span x-text="spotlight.room"></span>/<span x-text="spotlight.bed"></span>
                </div>
                
                <template x-if="spotlight.status === 'empty' || spotlight.status === 'available'">
                    <div>
                        <div class="spotlight-input-container">
                            <i class="fa-solid fa-search"></i>
                            <input type="text" x-model="spotlight.query" class="spotlight-input" placeholder="Type a student name to assign..." x-ref="spotlightInput" @keydown.escape="spotlight.open = false">
                        </div>
                        <div class="spotlight-results">
                            <template x-for="student in filteredStudents" :key="student.id">
                                <div class="spotlight-item" @click="confirmAssignment(student)">
                                    <img :src="student.photo_url || 'https://ui-avatars.com/api/?name='+encodeURI(student.name)+'&background=2563eb&color=fff'" class="spotlight-avatar">
                                    <div>
                                        <div class="fw-bold fs-5" x-text="student.name"></div>
                                        <div class="small text-muted" x-text="student.mobile"></div>
                                    </div>
                                    <div class="ms-auto text-primary opacity-50"><i class="fa-solid fa-arrow-right"></i></div>
                                </div>
                            </template>
                            <div x-show="filteredStudents.length === 0" class="p-5 text-center text-muted">
                                <i class="fa-solid fa-user-slash fs-1 opacity-25 mb-3"></i>
                                <h5>No unassigned students found.</h5>
                                <p class="small">Try a different search or register a new student.</p>
                            </div>
                        </div>
                    </div>
                </template>

                <template x-if="spotlight.status !== 'empty' && spotlight.status !== 'available'">
                    <div class="p-5 text-center text-muted">
                        <i class="fa-solid fa-lock fs-1 opacity-25 mb-3"></i>
                        <h5>This bed is currently <span class="text-uppercase text-warning fw-bold" x-text="spotlight.status"></span>.</h5>
                        <p class="small">You must mark it as available before assigning a student.</p>
                    </div>
                </template>

                <div class="bg-light p-3 border-top d-flex justify-content-center gap-2">
                    <template x-if="spotlight.status === 'empty' || spotlight.status === 'available'">
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-warning rounded-pill px-3 fw-bold" @click="markBedStatus('maintenance')">
                                <i class="fa-solid fa-wrench me-1"></i> Mark as Maintenance
                            </button>
                        </div>
                    </template>
                    <template x-if="spotlight.status === 'maintenance' || spotlight.status === 'reserved'">
                        <button type="button" class="btn btn-sm btn-success rounded-pill px-4 fw-bold shadow-sm" @click="markBedStatus('empty')">
                            <i class="fa-solid fa-check me-1"></i> Mark as Available
                        </button>
                    </template>
                </div>
            </div>
        </div>
    </template>

    <!-- Hidden Form for Assignment -->
    <form id="assignForm" action="{{ route('admin.property.assign') }}" method="POST" class="d-none">
        @csrf
        <input type="hidden" name="bed_id" x-model="spotlight.bedId">
        <input type="hidden" name="student_id" x-model="spotlight.studentId">
    </form>

    <!-- Fee Plan Gate — shown before assignment when the picked student has
         no fee_amount/fee_frequency set yet. Saving completes straight into
         the bed assignment, no second click. -->
    <template x-teleport="body">
        <div class="custom-overlay-backdrop" x-show="feeGate.open" x-transition.opacity @click="feeGate.open = false" x-cloak style="display: none;">
            <form @submit.prevent="saveFeeGate()" class="custom-overlay-modal" :class="{ 'is-open': feeGate.open }" x-show="feeGate.open" x-transition.opacity @click.stop style="display: none; max-width: 480px;">
                <div class="custom-overlay-header">
                    <h5 class="fw-bold mb-0">
                        <i class="fa-solid fa-sliders" style="color: var(--he-primary);"></i>
                        <span class="ms-1">Set Fee Plan</span>
                    </h5>
                    <button type="button" class="btn-close" @click="feeGate.open = false"></button>
                </div>
                <div class="custom-overlay-body">
                    <div class="alert alert-info border-0 rounded-3 mb-4 d-flex gap-3 align-items-start">
                        <i class="fa-solid fa-circle-info fs-5 mt-1"></i>
                        <div class="small">
                            No fee plan set for <strong x-text="feeGate.student ? feeGate.student.name : 'this student'"></strong> yet — set it to complete the assignment.
                        </div>
                    </div>

                    {{-- Room Preference --}}
                    <div class="mb-4">
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1 d-block mb-2">Room Preference</label>
                        <div class="chip-group">
                            <button type="button" class="chip" :class="{ active: feeGate.form.room_preference === '' }" @click="feeGate.form.room_preference = ''">Any</button>
                            <button type="button" class="chip" :class="{ active: feeGate.form.room_preference === 'AC' }" @click="feeGate.form.room_preference = 'AC'">AC</button>
                            <button type="button" class="chip" :class="{ active: feeGate.form.room_preference === 'Non-AC' }" @click="feeGate.form.room_preference = 'Non-AC'">Non-AC</button>
                        </div>
                    </div>

                    {{-- Sharing — a stepper, not a chip list. Chips (or a scrolling chip
                         strip) grow wider or need scrolling as the hostel's own room-
                         sharing ceiling grows (see Layout Builder → Room Settings); a
                         stepper's footprint never changes whether the range is 1–4 or
                         1–40. Stepping below 1 lands on "Any". --}}
                    <div class="mb-4">
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1 d-block mb-2">Sharing</label>
                        <div class="sharing-control">
                            <button type="button" class="sharing-btn" @click="feeGate.sharingValue = Math.max(0, feeGate.sharingValue - 1)">
                                <i class="fa-solid fa-minus"></i>
                            </button>
                            <div class="sharing-readout">
                                <div class="fs-6 fw-bold" x-show="feeGate.sharingValue === 0" x-text="'Any'"></div>
                                <div class="fs-6 fw-bold" x-show="feeGate.sharingValue > 0" x-text="sharingLabels[feeGate.sharingValue - 1]"></div>
                            </div>
                            <button type="button" class="sharing-btn" @click="feeGate.sharingValue = Math.min(maxSharing, feeGate.sharingValue + 1)">
                                <i class="fa-solid fa-plus"></i>
                            </button>
                        </div>
                    </div>

                    {{-- Fee Structure --}}
                    <div class="mb-4">
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1 d-block mb-2">Fee Structure <span class="text-danger">*</span></label>
                        <div class="chip-group">
                            <button type="button" class="chip" :class="{ active: feeGate.form.fee_frequency === 'monthly' }" @click="feeGate.form.fee_frequency = 'monthly'">Monthly</button>
                            <button type="button" class="chip" :class="{ active: feeGate.form.fee_frequency === 'semester' }" @click="feeGate.form.fee_frequency = 'semester'">Semester</button>
                            <button type="button" class="chip" :class="{ active: feeGate.form.fee_frequency === 'yearly' }" @click="feeGate.form.fee_frequency = 'yearly'">Yearly</button>
                        </div>
                        <div class="text-danger small mt-1" x-show="feeGate.errors.fee_frequency" x-text="feeGate.errors.fee_frequency && feeGate.errors.fee_frequency[0]"></div>
                    </div>

                    {{-- Amount --}}
                    <div class="mb-2">
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1">Amount <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted fw-bold">₹</span>
                            <input type="number" x-model="feeGate.form.fee_amount" class="form-control bg-light fw-bold text-dark" min="0" step="0.01" placeholder="0.00">
                        </div>
                        <div class="text-danger small mt-1" x-show="feeGate.errors.fee_amount" x-text="feeGate.errors.fee_amount && feeGate.errors.fee_amount[0]"></div>
                    </div>
                </div>
                <div class="custom-overlay-footer bg-light">
                    <button type="button" class="btn btn-white border fw-semibold rounded-pill px-4 tactile-btn" @click="feeGate.open = false">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-semibold rounded-pill px-4 shadow-sm tactile-btn" :disabled="feeGate.saving">
                        <span x-show="!feeGate.saving"><i class="fa-solid fa-check me-2"></i>Save &amp; Assign</span>
                        <span x-show="feeGate.saving"><i class="fa-solid fa-spinner fa-spin me-2"></i>Saving...</span>
                    </button>
                </div>
            </form>
        </div>
    </template>

    <!-- Hidden Form for Status Update -->
    <form id="statusForm" :action="'/admin/beds/' + spotlight.bedId + '/status'" method="POST" class="d-none">
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
                            <a :href="'/admin/students/' + panels.details.data.student_id" class="btn btn-outline-primary rounded-pill w-100 fw-bold">
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

    <!-- Transfer & Release — canonical custom-overlay anatomy (matches the
         fee-gate modal above; Alpine-driven, no Bootstrap). Both become
         bottom sheets on mobile via the system rule in _premium.scss. -->
    <template x-teleport="body">
        <div class="custom-overlay-backdrop" x-show="transferOpen" x-transition.opacity @click="transferOpen = false" x-cloak style="display: none;">
            <form class="custom-overlay-modal" :class="{ 'is-open': transferOpen }" x-show="transferOpen" @click.stop
                  :action="'/admin/property/assignments/' + panels.details.data.assignment_id + '/transfer'" method="POST" style="display: none; max-width: 480px;">
                @csrf @method('PATCH')
                <div class="custom-overlay-header">
                    <h5 class="fw-bold mb-0">
                        <i class="fa-solid fa-right-left" style="color: var(--he-primary);"></i>
                        <span class="ms-1">Transfer Student</span>
                    </h5>
                    <button type="button" class="btn-close" @click="transferOpen = false"></button>
                </div>
                <div class="custom-overlay-body">
                    <p class="text-muted">Move <strong x-text="panels.details.data.student_name"></strong> to a new bed.</p>

                    <div class="mb-4">
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1 d-block mb-2">Select New Bed</label>
                        <select name="bed_id" class="glass-input" required>
                            <option value="">Choose an available bed...</option>
                            @foreach($allFloors as $f)
                                <optgroup label="{{ $f->name }}">
                                    @foreach($f->rooms as $r)
                                        @foreach($r->beds as $b)
                                            @if($b->status === 'available' || $b->status === 'empty')
                                                <option value="{{ $b->id }}">Room {{ $r->room_number }} - Bed {{ $b->bed_number }}</option>
                                            @endif
                                        @endforeach
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1 d-block mb-2">Transfer Date</label>
                        <input type="date" name="join_date" class="glass-input" value="{{ now()->toDateString() }}" required>
                    </div>
                </div>
                <div class="custom-overlay-footer">
                    <button type="button" class="btn btn-white border fw-semibold rounded-pill px-4 tactile-btn" @click="transferOpen = false">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-semibold rounded-pill px-4 shadow-sm tactile-btn">
                        <i class="fa-solid fa-right-left me-2"></i>Transfer Now
                    </button>
                </div>
            </form>
        </div>
    </template>

    <template x-teleport="body">
        <div class="custom-overlay-backdrop" x-show="releaseOpen" x-transition.opacity @click="releaseOpen = false" x-cloak style="display: none;">
            <form class="custom-overlay-modal" :class="{ 'is-open': releaseOpen }" x-show="releaseOpen" @click.stop
                  :action="'/admin/property/assignments/' + panels.details.data.assignment_id + '/release'" method="POST" style="display: none; max-width: 480px;">
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
                        <input type="date" name="leave_date" class="glass-input" value="{{ now()->toDateString() }}" required :min="panels.details.data.join_date_raw">
                    </div>

                    <label class="form-check p-3 bg-danger-subtle rounded-3 d-flex align-items-center m-0" for="markLeft" style="cursor: pointer;">
                        <input class="form-check-input m-0 flex-shrink-0" type="checkbox" name="mark_student_left" value="1" id="markLeft" checked style="width: 1.25rem; height: 1.25rem;">
                        <span class="ms-3 text-danger fw-bold lh-sm">Also mark student as "Left" (Vacating Hostel entirely)</span>
                    </label>
                </div>
                <div class="custom-overlay-footer">
                    <button type="button" class="btn btn-white border fw-semibold rounded-pill px-4 tactile-btn" @click="releaseOpen = false">Cancel</button>
                    <button type="submit" class="btn btn-danger fw-semibold rounded-pill px-4 shadow-sm tactile-btn">
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
    Alpine.data('propertyBoard', (firstFloorId, studentsList, maxSharing = 7, sharingLabels = []) => ({
        activeFloorId: firstFloorId,
        searchQuery: '',
        students: studentsList,
        maxSharing,
        sharingLabels,

        spotlight: {
            open: false,
            query: '',
            bedId: '',
            studentId: '',
            room: '',
            bed: ''
        },

        panels: {
            details: {
                open: false, bedId: '', room: '', bed: '',
                data: { assignment_id: '', student_id: '', student_name: '', student_mobile: '', student_photo: '', join_date: '', join_date_raw: '', duration: '' }
            }
        },

        // Transfer & Release overlays (canonical custom-overlay, Alpine-driven).
        transferOpen: false,
        releaseOpen: false,

        feeGate: {
            open: false,
            student: null,
            saving: false,
            errors: {},
            sharingValue: 0, // 0 = "Any"; 1..maxSharing indexes into sharingLabels
            form: { room_preference: '', sharing_preference: '', fee_frequency: '', fee_amount: '' },
        },
        
        get filteredStudents() {
            if (!this.spotlight.query) return this.students;
            const q = this.spotlight.query.toLowerCase().trim();
            return this.students.filter(s => 
                s.name.toLowerCase().includes(q) || 
                (s.mobile && s.mobile.includes(q))
            );
        },
        
        roomMatchesSearch(roomNum, occupants) {
            if (!this.searchQuery) return true;
            const q = this.searchQuery.toLowerCase().trim();
            if (roomNum.toLowerCase().includes(q)) return true;
            for (let name of occupants) {
                if (name.toLowerCase().includes(q)) return true;
            }
            return false;
        },
        
        openSpotlight(bedId, room, bed, bedStatus) {
            this.spotlight.bedId = bedId;
            this.spotlight.room = room;
            this.spotlight.bed = bed;
            this.spotlight.status = bedStatus;
            this.spotlight.query = '';
            this.spotlight.studentId = '';
            this.spotlight.open = true;
        },

        confirmAssignment(student) {
            if (!student.fee_amount || !student.fee_frequency) {
                this.openFeeGate(student);
                return;
            }
            this.spotlight.studentId = student.id;
            this.$nextTick(() => {
                document.getElementById('assignForm').submit();
            });
        },

        openFeeGate(student) {
            this.feeGate.student = student;
            this.feeGate.errors = {};
            this.feeGate.saving = false;
            this.feeGate.form = {
                room_preference: student.room_preference || '',
                sharing_preference: student.sharing_preference || '',
                fee_frequency: student.fee_frequency || '',
                fee_amount: student.fee_amount || '',
            };
            // Reverse-map the stored label back to the stepper's number; an
            // unrecognized/legacy value (indexOf -1) safely falls back to "Any".
            this.feeGate.sharingValue = student.sharing_preference
                ? Math.max(0, this.sharingLabels.indexOf(student.sharing_preference) + 1)
                : 0;
            this.feeGate.open = true;
        },

        async saveFeeGate() {
            this.feeGate.saving = true;
            this.feeGate.errors = {};
            this.feeGate.form.sharing_preference = this.feeGate.sharingValue > 0
                ? this.sharingLabels[this.feeGate.sharingValue - 1]
                : '';

            const payload = { ...this.feeGate.form };

            try {
                const res = await fetch(`/admin/students/${this.feeGate.student.id}/fee-settings`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify(payload),
                });

                if (res.status === 422) {
                    const data = await res.json();
                    this.feeGate.errors = data.errors || {};
                    this.feeGate.saving = false;
                    return;
                }
                if (!res.ok) throw new Error('Save failed');

                // Keep the in-memory student record in sync, then complete the assignment.
                const s = this.feeGate.student;
                s.fee_amount = payload.fee_amount;
                s.fee_frequency = payload.fee_frequency;
                s.room_preference = payload.room_preference;
                s.sharing_preference = payload.sharing_preference;

                this.feeGate.open = false;
                this.spotlight.studentId = s.id;
                this.$nextTick(() => document.getElementById('assignForm').submit());
            } catch (e) {
                console.error(e);
                this.feeGate.saving = false;
            }
        },

        markBedStatus(status) {
            document.getElementById('statusInput').value = status;
            document.getElementById('statusForm').submit();
        },
        
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

        openTransfer() {
            this.closeDetails();
            this.transferOpen = true;
        },

        openRelease() {
            this.closeDetails();
            this.releaseOpen = true;
        }
    }));
});
</script>
@endpush
