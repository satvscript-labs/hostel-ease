@extends('layouts.app')
@section('title', 'Property Board')

@section('content')
<div x-data="propertyBoard()" class="page-enter">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <h1 class="h3 fw-bold mb-0">Property Board</h1>
        <div class="d-flex gap-2">
            <!-- Search -->
            <input type="text" class="form-control" placeholder="Search room or bed..." x-model="searchQuery" style="max-width: 250px;">
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown"><i class="fa-solid fa-gear"></i></button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="{{ route('admin.rooms.index') }}"><i class="fa-solid fa-door-open me-2"></i>Manage Rooms</a></li>
                    <li><a class="dropdown-item" href="{{ route('admin.floors.index') }}"><i class="fa-solid fa-layer-group me-2"></i>Manage Floors</a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Bento Dashboard Stats -->
    <div class="bento mb-4 stagger">
        <div class="bento-card hero c2">
            <div class="d-flex align-items-center gap-3 h-100">
                <div class="bento-icon bg-white text-primary bg-opacity-25"><i class="fa-solid fa-building"></i></div>
                <div>
                    <div class="bento-value">{{ $totalBeds }}</div>
                    <div class="bento-label text-white-50">Total Beds</div>
                </div>
            </div>
        </div>
        <div class="bento-card">
            <div class="d-flex align-items-center gap-3 h-100">
                <div class="bento-icon bg-success-subtle text-success"><i class="fa-solid fa-bed-pulse"></i></div>
                <div>
                    <div class="bento-value">{{ $vacant }}</div>
                    <div class="bento-label">Vacant</div>
                </div>
            </div>
        </div>
        <div class="bento-card">
            <div class="d-flex align-items-center gap-3 h-100">
                <div class="bento-icon bg-danger-subtle text-danger"><i class="fa-solid fa-user-check"></i></div>
                <div>
                    <div class="bento-value">{{ $occupied }}</div>
                    <div class="bento-label">Occupied</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Floors & Rooms Grid -->
    <div class="stagger">
        @foreach($floors as $floor)
        <div class="floor-block mb-5" x-show="floorHasMatch('{{ $floor->id }}')">
            <h2 class="h5 fw-bold mb-3 text-muted border-bottom pb-2">{{ $floor->name }}</h2>
            
            <div class="row g-3">
                @foreach($floor->rooms as $room)
                <div class="col-12 col-md-6 col-lg-4 col-xl-3 room-item" 
                     x-show="roomMatches('{{ $room->room_number }}')" 
                     data-floor="{{ $floor->id }}">
                    <div class="card card-premium h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span class="fw-bold">Room {{ $room->room_number }}</span>
                            <span class="badge bg-{{ $room->isAc() ? 'info' : 'secondary' }}-subtle text-{{ $room->isAc() ? 'info' : 'secondary' }}">{{ $room->isAc() ? 'AC' : 'Non AC' }}</span>
                        </div>
                        <div class="card-body">
                            <div class="bed-grid">
                                @foreach($room->beds as $bed)
                                    @php
                                        $statusClass = match($bed->status) {
                                            'available' => 'bed-vacant',
                                            'occupied' => 'bed-occupied',
                                            'reserved' => 'bed-reserved',
                                            'maintenance' => 'bg-secondary text-white',
                                            default => ''
                                        };
                                        $studentName = $bed->activeAssignment ? $bed->activeAssignment->student->name : '';
                                    @endphp
                                    <div class="bed-tile-premium {{ $statusClass }}"
                                         @if($bed->status === 'available')
                                            @click="openAssignModal({{ $bed->id }}, '{{ $room->room_number }}', '{{ $bed->bed_number }}')"
                                         @endif
                                         title="{{ $studentName }}">
                                        {{ $bed->bed_number }}
                                        @if($bed->status === 'occupied')
                                            <small class="text-truncate w-100 px-1">{{ strtok($studentName, ' ') }}</small>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endforeach
    </div>

    <!-- Quick Assign Modal (Alpine controlled) -->
    <template x-teleport="body">
        <div x-show="assignModalOpen" 
             style="display: none;" 
             class="modal-backdrop show hsms-backdrop" 
             x-transition.opacity>
            <div class="modal d-block" tabindex="-1" @click.self="assignModalOpen = false">
                <div class="modal-dialog modal-dialog-centered" x-show="assignModalOpen" x-transition:enter="modal-scale">
                    <form class="modal-content" method="POST" action="{{ route('admin.assignments.store') }}">
                        @csrf
                        <input type="hidden" name="bed_id" x-model="selectedBedId">
                        
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold">Assign Bed <span x-text="selectedRoom + ' / ' + selectedBed"></span></h5>
                            <button type="button" class="btn-close" @click="assignModalOpen = false"></button>
                        </div>
                        
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Student <span class="text-danger">*</span></label>
                                <select name="student_id" class="form-select" required>
                                    <option value="">Select student...</option>
                                    @foreach($unassignedStudents as $s)
                                        <option value="{{ $s->id }}">{{ $s->name }} ({{ hostelease_phone($s->mobile) }})</option>
                                    @endforeach
                                </select>
                            </div>
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Join Date <span class="text-danger">*</span></label>
                                    <input type="date" name="join_date" class="form-control" value="{{ now()->toDateString() }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Fee Amount (₹) <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" min="0" name="fee_amount" class="form-control" required placeholder="25000">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Fee Frequency <span class="text-danger">*</span></label>
                                    <select name="fee_frequency" x-model="feeFrequency" class="form-select" required>
                                        @foreach(config('hostelease.fee_frequencies') as $k => $label)
                                            <option value="{{ $k }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6" x-show="feeFrequency === 'semester'">
                                    <label class="form-label">Semester <span class="text-danger">*</span></label>
                                    <select name="semester" class="form-select">
                                        @foreach(config('hostelease.semesters') as $s)
                                            <option value="{{ $s }}">Semester {{ $s }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" @click="assignModalOpen = false">Cancel</button>
                            <button type="submit" class="btn btn-premium">Assign</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </template>

</div>

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('propertyBoard', () => ({
            searchQuery: '',
            assignModalOpen: false,
            selectedBedId: '',
            selectedRoom: '',
            selectedBed: '',
            feeFrequency: 'semester',
            
            roomMatches(roomNum) {
                if (!this.searchQuery) return true;
                return roomNum.toLowerCase().includes(this.searchQuery.toLowerCase());
            },
            
            floorHasMatch(floorId) {
                // Return true to let flexbox/grid naturally collapse if all children hidden
                return true; 
            },
            
            openAssignModal(bedId, room, bedNum) {
                this.selectedBedId = bedId;
                this.selectedRoom = room;
                this.selectedBed = bedNum;
                this.assignModalOpen = true;
            }
        }));
    });
</script>
@endpush
@endsection
