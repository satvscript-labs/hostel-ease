@extends('layouts.app')
@section('title', 'Students')

@section('content')
<style>
    /* Hero & Toolbar */
    .students-hero {
        background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%);
        border-radius: 1.5rem;
        padding: 3rem 2rem 5rem;
        color: white;
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
        width: 300px; height: 300px;
        background: rgba(255,255,255,0.1);
        border-radius: 50%;
        filter: blur(40px);
        pointer-events: none;
    }
    
    .filter-toolbar {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(20px);
        border: 1px solid rgba(255,255,255,0.8);
        border-radius: 1.25rem;
        box-shadow: 0 15px 35px rgba(0,0,0,0.05);
        padding: 1rem;
        position: relative;
        z-index: 10;
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        align-items: center;
        justify-content: space-between;
    }
    .search-input-wrapper {
        position: relative;
        flex-grow: 1;
        max-width: 400px;
    }
    .search-input-wrapper i {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
    }
    .search-input {
        width: 100%;
        padding: 0.75rem 1rem 0.75rem 2.5rem;
        border-radius: 2rem;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        transition: all 0.3s;
    }
    .search-input:focus {
        background: #fff;
        border-color: var(--he-primary);
        box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        outline: none;
    }
    
    /* Segmented Filter Pills */
    .filter-pills {
        display: flex;
        gap: 0.5rem;
        background: #f1f5f9;
        padding: 0.35rem;
        border-radius: 2rem;
        overflow-x: auto;
    }
    .filter-pills::-webkit-scrollbar { display: none; }
    .filter-pill {
        border: none;
        background: transparent;
        padding: 0.5rem 1.25rem;
        border-radius: 1.5rem;
        font-weight: 600;
        font-size: 0.9rem;
        color: #64748b;
        transition: all 0.3s;
        white-space: nowrap;
    }
    .filter-pill.active {
        background: #fff;
        color: var(--he-primary);
        box-shadow: 0 4px 6px rgba(0,0,0,0.05);
    }
    .filter-pill:hover:not(.active) {
        color: #1e293b;
    }

    /* ID Badge Cards */
    .id-card {
        background: #fff;
        border-radius: 1.5rem;
        border: 1px solid #e2e8f0;
        overflow: hidden;
        position: relative;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: block;
        text-decoration: none;
        box-shadow: 0 4px 6px rgba(0,0,0,0.02);
    }
    .id-card:hover {
        transform: translateY(-8px) scale(1.02);
        box-shadow: 0 20px 40px rgba(0,0,0,0.08);
    }
    .id-card-banner {
        height: 80px;
        position: relative;
    }
    .banner-active { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
    .banner-left { background: linear-gradient(135deg, #64748b 0%, #475569 100%); }
    .banner-nobed { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
    
    .id-avatar-wrapper {
        position: absolute;
        top: 40px;
        left: 50%;
        transform: translateX(-50%);
        width: 80px;
        height: 80px;
        border-radius: 50%;
        padding: 4px;
        background: #fff;
        box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        z-index: 2;
        transition: all 0.3s;
    }
    .id-card:hover .id-avatar-wrapper {
        transform: translateX(-50%) scale(1.1);
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
        color: #1e293b;
        font-weight: 800;
        font-size: 1.15rem;
        margin-bottom: 0.25rem;
    }
    .id-status-badge {
        font-size: 0.7rem;
        font-weight: 700;
        padding: 0.3rem 0.75rem;
        border-radius: 1rem;
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        margin-bottom: 1.25rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .status-active { background: #d1fae5; color: #047857; }
    .status-left { background: #f1f5f9; color: #475569; }
    
    /* Bento Info Grid */
    .id-info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.5rem;
        background: #f8fafc;
        border-radius: 1rem;
        padding: 0.75rem;
    }
    .id-info-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 0.5rem;
        border-radius: 0.75rem;
        background: #fff;
        border: 1px solid #e2e8f0;
    }
    .id-info-val {
        font-weight: 700;
        color: #334155;
        font-size: 0.85rem;
        margin-top: 0.25rem;
    }
    .id-info-icon {
        color: #94a3b8;
        font-size: 1rem;
    }

    /* Hover Overlay Action */
    .id-overlay {
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(255,255,255,0.7);
        backdrop-filter: blur(4px);
        z-index: 10;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: opacity 0.3s;
        border-radius: 1.5rem;
    }
    .id-card:hover .id-overlay {
        opacity: 1;
    }
    .overlay-btn {
        background: var(--he-primary);
        color: white;
        border: none;
        padding: 0.75rem 1.5rem;
        border-radius: 2rem;
        font-weight: 700;
        transform: translateY(20px);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 10px 20px rgba(79, 70, 229, 0.3);
    }
    .id-card:hover .overlay-btn {
        transform: translateY(0);
    }
    
    /* Cascade Animation */
    .student-item {
        animation: cascadeIn 0.5s cubic-bezier(0.4, 0, 0.2, 1) both;
    }
    @keyframes cascadeIn {
        from { opacity: 0; transform: translateY(30px) scale(0.95); }
        to { opacity: 1; transform: translateY(0) scale(1); }
    }
</style>

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
        <div class="search-input-wrapper">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" class="search-input" placeholder="Search by name, mobile, room..." x-model="query">
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
    <div class="row g-4">
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
             style="animation-delay: {{ $index * 0.05 }}s;"
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
            <div class="empty-state bg-white p-5 rounded-4 shadow-sm text-center border">
                <i class="fa-solid fa-users d-block text-muted mb-3" style="font-size: 3rem;"></i>
                <h4 class="fw-bold text-dark">No students found</h4>
                <p class="text-muted">Add your first student to get started.</p>
                <a href="{{ route('admin.students.create') }}" class="btn btn-primary rounded-pill px-4 mt-2">
                    <i class="fa-solid fa-plus me-1"></i> Add Student
                </a>
            </div>
        </div>
        @endforelse
    </div>

    <!-- No Results Message -->
    <div class="empty-state bg-white p-5 rounded-4 shadow-sm text-center border mt-4" x-show="noResults" x-cloak>
        <i class="fa-solid fa-magnifying-glass d-block text-muted mb-3" style="font-size: 3rem;"></i>
        <h4 class="fw-bold text-dark">No Matches</h4>
        <p class="text-muted">No students match your search or filter criteria.</p>
    </div>

    <!-- Mobile FAB -->
    <a href="{{ route('admin.students.create') }}" class="fab d-md-none" title="Add Student">
        <i class="fa-solid fa-plus"></i>
    </a>
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
