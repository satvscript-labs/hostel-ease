@extends('layouts.app')
@section('title', 'Students')

@section('content')
<div x-data="studentList()" class="page-enter">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h4 fw-bold mb-0">Students</h1>
        <a href="{{ route('admin.students.create') }}" class="btn btn-premium d-none d-md-inline-flex">
            <i class="fa-solid fa-user-plus me-1"></i> Add Student
        </a>
    </div>

    <!-- Search + Filter Chips -->
    <div class="mb-3">
        <input type="text" class="form-control mb-2" placeholder="Search by name, mobile, room..." x-model="query"
               style="border-radius: var(--he-radius-md);">
        <div class="chip-group">
            <button class="chip" :class="{ active: filter === '' }" @click="filter = ''">All</button>
            <button class="chip" :class="{ active: filter === 'active' }" @click="filter = 'active'">
                <i class="fa-solid fa-circle text-success me-1" style="font-size:0.5rem"></i> Active
            </button>
            <button class="chip" :class="{ active: filter === 'left' }" @click="filter = 'left'">
                <i class="fa-solid fa-circle text-secondary me-1" style="font-size:0.5rem"></i> Left
            </button>
            @foreach(config('hostelease.occupation_types') as $k => $label)
                <button class="chip" :class="{ active: filter === '{{ $k }}' }" @click="filter = '{{ $k }}'">{{ $label }}</button>
            @endforeach
        </div>
    </div>

    <!-- Student Cards Grid -->
    <div class="row g-2 stagger">
        @forelse($students as $s)
            @php($asg = $s->activeAssignment)
        <div class="col-12 col-sm-6 col-lg-4 col-xl-3 student-item"
             x-show="matchesSearch('{{ strtolower(addslashes($s->name)) }}', '{{ $s->mobile }}', '{{ $asg ? strtolower($asg->bed->room->room_number) : '' }}', '{{ $s->status }}', '{{ $s->occupation_type }}')"
             x-transition.opacity.duration.200ms>
            <a href="{{ route('admin.students.show', $s) }}" class="student-card h-100">
                <div class="d-flex align-items-start gap-3">
                    <img src="{{ $s->photo_url }}" class="sc-avatar" alt="">
                    <div class="flex-grow-1 min-width-0">
                        <div class="d-flex justify-content-between align-items-start gap-1">
                            <div class="sc-name text-truncate">{{ $s->name }}</div>
                            <span class="badge-premium bg-{{ $s->status === 'active' ? 'success' : 'secondary' }}-subtle text-{{ $s->status === 'active' ? 'success' : 'secondary' }}">
                                {{ ucfirst($s->status) }}
                            </span>
                        </div>
                        <div class="sc-meta mt-1">
                            <i class="fa-solid fa-phone" style="font-size:0.65rem"></i> {{ hostelease_phone($s->mobile) }}
                        </div>
                        @if($asg)
                        <div class="sc-meta">
                            <i class="fa-solid fa-bed" style="font-size:0.65rem"></i>
                            Room {{ $asg->bed->room->room_number }} / {{ $asg->bed->bed_number }}
                        </div>
                        @else
                        <div class="sc-meta text-warning">
                            <i class="fa-solid fa-triangle-exclamation" style="font-size:0.65rem"></i> No bed
                        </div>
                        @endif
                        <div class="sc-meta">
                            <i class="fa-solid fa-briefcase" style="font-size:0.65rem"></i>
                            {{ config('hostelease.occupation_types.'.$s->occupation_type) }}
                        </div>
                    </div>
                </div>
            </a>
        </div>
        @empty
        <div class="col-12">
            <div class="empty-state">
                <i class="fa-solid fa-users d-block"></i>
                <p>No students found. Add your first student to get started.</p>
                <a href="{{ route('admin.students.create') }}" class="btn btn-premium mt-3">
                    <i class="fa-solid fa-user-plus me-1"></i> Add Student
                </a>
            </div>
        </div>
        @endforelse
    </div>

    <!-- No Results Message -->
    <div class="empty-state" x-show="noResults" x-cloak>
        <i class="fa-solid fa-magnifying-glass d-block"></i>
        <p>No students match your search.</p>
    </div>

    <!-- Mobile FAB -->
    <a href="{{ route('admin.students.create') }}" class="fab" title="Add Student">
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
