{{-- Student card grid — everything inside #student-list so the search box and
     filter chips swap it without a reload (§4.3). Server-paginated (H2b): the
     old page loaded every student and filtered client-side. --}}
@php $isFiltered = $search !== '' || $filter !== ''; @endphp

<div class="row g-4 stagger">
    @forelse($students as $s)
        @php
            $asg = $s->activeAssignment;
            if ($s->status === 'active') {
                $bannerClass = $asg ? 'banner-active' : 'banner-nobed';
                $statusClass = 'status-active';
                $statusIcon = 'fa-check';
            } else {
                $bannerClass = 'banner-left';
                $statusClass = 'status-left';
                $statusIcon = 'fa-door-open';
            }
        @endphp
        <div class="col-12 col-md-6 col-lg-4 col-xl-3">
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

                        <div class="id-info-item" title="Mobile Number">
                            <i class="fa-solid fa-phone id-info-icon text-success"></i>
                            <div class="id-info-val text-truncate" style="max-width: 100%;">
                                {{ substr($s->mobile, 0, 5) }}...
                            </div>
                        </div>

                        <div class="id-info-item" title="Occupation" style="grid-column: span 2;">
                            <i class="fa-solid fa-briefcase id-info-icon text-info"></i>
                            <div class="id-info-val">
                                {{ config('hostelease.occupation_types.'.$s->occupation_type, ucfirst($s->occupation_type)) }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="id-overlay">
                    <div class="overlay-btn"><i class="fa-regular fa-eye me-1"></i> View Profile</div>
                </div>
            </a>
        </div>
    @empty
        <div class="col-12">
            <div class="card-premium">
                @if($isFiltered)
                    <x-he-empty-state icon="magnifying-glass" title="No matches"
                        subtitle="No students match your search or filter." />
                @else
                    <x-he-empty-state icon="users" title="No students yet"
                        subtitle="Add your first student to get started.">
                        <a href="{{ route('admin.students.create') }}" class="btn btn-primary rounded-pill px-4 mt-3 tactile-btn">
                            <i class="fa-solid fa-plus me-1"></i> Add Student
                        </a>
                    </x-he-empty-state>
                @endif
            </div>
        </div>
    @endforelse
</div>

@if($students->hasPages())
    <div class="mt-4">{{ $students->links() }}</div>
@endif
