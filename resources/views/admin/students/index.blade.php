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
<div x-data="studentList()" class="page-enter">
    
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

    <!-- Toolbar -->
    <div class="filter-toolbar mb-4">
        <div class="he-search students-search">
            <span class="he-search__icon"><i class="fa-solid fa-magnifying-glass"></i></span>
            <input type="text" class="he-search__input" placeholder="Search by name, mobile, room..." x-model="query">
        </div>
        <div class="filter-pills">
            <button class="filter-pill" :class="{ active: filter === '' }" @click="filter = ''">All</button>
            <button class="filter-pill" :class="{ active: filter === 'active' }" @click="filter = 'active'">Active</button>
            <button class="filter-pill" :class="{ active: filter === 'left' }" @click="filter = 'left'">Left</button>
            @foreach(config('hostelease.occupation_types') as $k => $label)
                <button class="filter-pill" :class="{ active: filter === '{{ $k }}' }" @click="filter = '{{ $k }}'">{{ $label }}</button>
            @endforeach
        </div>
    </div>

    <!-- Student Cards Grid -->
    <div class="row g-4 stagger">
        @forelse($students as $index => $s)
            @php
                $asg = $s->activeAssignment;
                if($s->status === 'active') {
                    $bannerClass = $asg ? 'banner-active' : 'banner-nobed';
                    $statusClass = 'status-active';
                    $statusIcon = 'fa-check';
                } else {
                    $bannerClass = 'banner-left';
                    $statusClass = 'status-left';
                    $statusIcon = 'fa-door-open';
                }
            @endphp
        <div class="col-12 col-md-6 col-lg-4 col-xl-3 student-item"
             x-show="matchesSearch('{{ strtolower(addslashes($s->name)) }}', '{{ $s->mobile }}', '{{ $asg ? strtolower($asg->bed->room->room_number) : '' }}', '{{ $s->status }}', '{{ $s->occupation_type }}')">
             
            <a href="{{ route('admin.students.show', $s) }}" class="id-card">
                <div class="id-card-banner {{ $bannerClass }}"></div>
                <div class="id-avatar-wrapper">
                    <img src="{{ $s->photo_url }}" class="id-avatar" alt="{{ $s->name }}">
                </div>
                
                <div class="id-card-body">
                    <div class="id-name text-truncate">{{ $s->name }}</div>
                    <div class="id-status-badge {{ $statusClass }}">
                        <i class="fa-solid {{ $statusIcon }}"></i> {{ ucfirst($s->status) }}
                    </div>
                    
                    <div class="id-info-grid">
                        <!-- Room info -->
                        <div class="id-info-item" title="Room Allocation">
                            <i class="fa-solid fa-bed id-info-icon text-primary"></i>
                            <div class="id-info-val text-truncate" style="max-width: 100%;">
                                @if($asg)
                                    R:{{ $asg->bed->room->room_number }} / B:{{ $asg->bed->bed_number }}
                                @else
                                    <span class="text-warning">No Bed</span>
                                @endif
                            </div>
                        </div>
                        
                        <!-- Mobile info -->
                        <div class="id-info-item" title="Mobile Number">
                            <i class="fa-solid fa-phone id-info-icon text-success"></i>
                            <div class="id-info-val text-truncate" style="max-width: 100%;">
                                {{ substr($s->mobile, 0, 5) }}...
                            </div>
                        </div>
                        
                        <!-- Occupation info -->
                        <div class="id-info-item" title="Occupation" style="grid-column: span 2;">
                            <i class="fa-solid fa-briefcase id-info-icon text-info"></i>
                            <div class="id-info-val">
                                {{ config('hostelease.occupation_types.'.$s->occupation_type, ucfirst($s->occupation_type)) }}
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Hover Action -->
                <div class="id-overlay">
                    <div class="overlay-btn">
                        <i class="fa-regular fa-eye me-1"></i> View Profile
                    </div>
                </div>
            </a>
        </div>
        @empty
        <div class="col-12">
            <div class="card-premium">
                <x-he-empty-state icon="users" title="No students found"
                    subtitle="Add your first student to get started.">
                    <a href="{{ route('admin.students.create') }}" class="btn btn-primary rounded-pill px-4 mt-3 tactile-btn">
                        <i class="fa-solid fa-plus me-1"></i> Add Student
                    </a>
                </x-he-empty-state>
            </div>
        </div>
        @endforelse
    </div>

    <!-- No Results Message -->
    <div class="card-premium mt-4" x-show="noResults" x-cloak>
        <x-he-empty-state icon="magnifying-glass" title="No matches"
            subtitle="No students match your search or filter criteria." />
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

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('studentList', () => ({
        query: '',
        filter: '',
        noResults: false,

        matchesSearch(name, mobile, room, status, occupation) {
            const q = this.query.toLowerCase().trim();
            const f = this.filter;

            // Filter chips
            if (f === 'active' || f === 'left') {
                if (status !== f) return false;
            } else if (f && f !== '') {
                if (occupation !== f) return false;
            }

            // Search query
            if (!q) return true;
            return name.includes(q) || mobile.includes(q) || room.includes(q);
        },

        init() {
            this.$watch('query', () => this.checkNoResults());
            this.$watch('filter', () => this.checkNoResults());
        },

        checkNoResults() {
            this.$nextTick(() => {
                const items = this.$root.querySelectorAll('.student-item');
                let visible = 0;
                items.forEach(el => {
                    if (el.style.display !== 'none') visible++;
                });
                this.noResults = visible === 0 && items.length > 0;
            });
        }
    }));
});
</script>
@endpush
@endsection
