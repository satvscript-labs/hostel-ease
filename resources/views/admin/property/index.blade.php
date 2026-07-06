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
    
    .pb-search-wrapper {
        position: relative;
        width: 300px;
    }
    .pb-search-wrapper .fa-search {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--he-text-muted);
    }
    .pb-search {
        width: 100%;
        padding: 0.75rem 1rem 0.75rem 2.5rem;
        border-radius: 50px;
        border: 1px solid rgba(0,0,0,0.05);
        background: rgba(255,255,255,0.9);
        backdrop-filter: blur(10px);
        box-shadow: 0 4px 15px rgba(0,0,0,0.02);
        transition: all 0.3s ease;
    }
    .pb-search:focus {
        outline: none;
        box-shadow: 0 4px 20px rgba(79, 70, 229, 0.15);
        border-color: rgba(79, 70, 229, 0.3);
    }

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
    .pb-stat-success { background: rgba(16, 185, 129, 0.1); color: #10b981; }
    .pb-stat-danger { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
    .pb-stat-warning { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }

    /* The Blueprint Layout */
    .floor-section {
        margin-bottom: 4rem;
        animation: fade-up 0.8s cubic-bezier(0.25, 1, 0.5, 1) forwards;
    }
    .floor-title {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    .floor-title h2 { margin: 0; font-size: 1.5rem; font-weight: 800; color: var(--he-text-main); }
    .floor-title::after {
        content: ''; flex: 1; height: 1px;
        background: linear-gradient(90deg, rgba(79, 70, 229, 0.2), transparent);
    }
    
    .room-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1.5rem;
    }

    .room-card {
        background: rgba(255, 255, 255, 0.6);
        backdrop-filter: blur(24px);
        border: 1px solid rgba(255, 255, 255, 0.8);
        border-radius: 1.5rem;
        padding: 1.25rem;
        box-shadow: 0 8px 32px rgba(31, 38, 135, 0.05);
        transition: all 0.4s cubic-bezier(0.25, 1, 0.5, 1);
        position: relative;
        overflow: hidden;
    }
    .room-card:hover {
        transform: translateY(-4px) scale(1.01);
        box-shadow: 0 15px 40px rgba(31, 38, 135, 0.1);
        background: rgba(255, 255, 255, 0.9);
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
        margin-bottom: 1rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px dashed rgba(0,0,0,0.05);
    }
    .room-number { font-size: 1.25rem; font-weight: 800; color: var(--he-text-main); }
    .room-type { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; padding: 0.25rem 0.75rem; border-radius: 50px; }
    .room-type.ac { background: rgba(14, 165, 233, 0.1); color: #0ea5e9; }
    .room-type.non-ac { background: rgba(100, 116, 139, 0.1); color: #64748b; }

    .bed-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 0.75rem;
    }

    /* Bed Tiles */
    .bed-tile {
        position: relative;
        padding: 0.75rem;
        border-radius: 1rem;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.25, 1, 0.5, 1);
        border: 2px solid transparent;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-height: 80px;
    }
    .bed-tile::before {
        content: '';
        position: absolute;
        inset: 0;
        border-radius: inherit;
        opacity: 0;
        transition: opacity 0.3s ease;
        box-shadow: 0 0 15px currentColor;
        z-index: -1;
    }
    .bed-tile:hover { transform: translateY(-3px) scale(1.05); z-index: 10; }
    .bed-tile:hover::before { opacity: 0.3; }

    /* Empty Bed */
    .bed-empty {
        background: rgba(16, 185, 129, 0.05);
        border-color: rgba(16, 185, 129, 0.2);
        color: #10b981;
    }
    .bed-empty:hover {
        background: rgba(16, 185, 129, 0.1);
        border-color: #10b981;
    }
    
    /* Occupied Bed */
    .bed-occupied {
        background: linear-gradient(135deg, rgba(239, 68, 68, 0.05), rgba(220, 38, 38, 0.1));
        border-color: rgba(239, 68, 68, 0.3);
        color: #ef4444;
    }
    .bed-occupied:hover {
        border-color: #ef4444;
    }
    .occupant-name {
        font-size: 0.75rem;
        font-weight: 700;
        margin-top: 0.25rem;
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        color: var(--he-text-main);
    }
    .occupant-avatar {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        margin-bottom: 0.25rem;
        object-fit: cover;
        border: 2px solid white;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }

    /* Maintenance */
    .bed-maintenance { background: rgba(100, 116, 139, 0.1); color: #64748b; border-color: rgba(100, 116, 139, 0.3); cursor: not-allowed; }

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
</style>
@endpush

@section('content')
<div x-data="propertyBoard()" class="page-enter pb-5">
    
    <!-- Header -->
    <div class="pb-header flex-wrap gap-3">
        <div>
            <h1 class="display-6 fw-bold mb-1">Property Board</h1>
            <p class="text-muted mb-0">Visualize and manage your entire hostel layout instantly.</p>
        </div>
        <div class="d-flex gap-3 align-items-center">
            <div class="pb-search-wrapper">
                <i class="fa-solid fa-search"></i>
                <input type="text" class="pb-search" placeholder="Find student, room, or bed..." x-model="searchQuery">
            </div>
            <a href="{{ route('admin.floors.index') }}" class="btn btn-light rounded-pill px-4 shadow-sm fw-bold">
                <i class="fa-solid fa-hammer me-2"></i>Layout Builder
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
    <div class="stagger">
        @foreach($floors as $floor)
        <div class="floor-section" x-show="floorHasVisibleRooms('{{ $floor->id }}')">
            <div class="floor-title">
                <h2>{{ $floor->name }}</h2>
            </div>
            
            <div class="room-grid">
                @foreach($floor->rooms as $room)
                <div class="room-card" 
                     data-floor="{{ $floor->id }}"
                     :class="{ 'dimmed': !roomMatchesSearch('{{ $room->room_number }}', {{ json_encode($room->beds->map(fn($b) => $b->activeAssignment?->student->name)->filter()->values()) }}) }">
                    <div class="room-header">
                        <div class="room-number">Room {{ $room->room_number }}</div>
                        <div class="room-type {{ $room->isAc() ? 'ac' : 'non-ac' }}">{{ $room->isAc() ? 'AC' : 'Non AC' }}</div>
                    </div>
                    
                    <div class="bed-grid">
                        @foreach($room->beds as $bed)
                            @if($bed->status === 'available' || $bed->status === 'empty')
                                <div class="bed-tile bed-empty" 
                                     @click="openAssign('{{ $bed->id }}', '{{ $room->room_number }}', '{{ $bed->bed_number }}')"
                                     title="Available">
                                    <i class="fa-solid fa-plus mb-1 fs-5"></i>
                                    <div class="fw-bold">{{ $bed->bed_number }}</div>
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
                                    <img src="{{ $student->photo_url }}" class="occupant-avatar">
                                    <div class="fw-bold">{{ $bed->bed_number }}</div>
                                    <div class="occupant-name">{{ strtok($student->name, ' ') }}</div>
                                </div>
                            @else
                                <div class="bed-tile bed-maintenance" title="Maintenance">
                                    <i class="fa-solid fa-wrench mb-1 fs-5"></i>
                                    <div class="fw-bold">{{ $bed->bed_number }}</div>
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

    <!-- Quick Assign Slide-over -->
    <template x-teleport="body">
        <div x-show="panels.assign.open" class="slide-over-backdrop" x-cloak x-transition.opacity></div>
    </template>
    <template x-teleport="body">
        <div x-show="panels.assign.open" class="slide-over-panel" x-cloak
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="transform translate-x-full"
             x-transition:enter-end="transform translate-x-0"
             x-transition:leave="transition ease-in duration-300"
             x-transition:leave-start="transform translate-x-0"
             x-transition:leave-end="transform translate-x-full"
             @click.away="panels.assign.open = false">
            
            <form action="{{ route('admin.property.assign') }}" method="POST" class="d-flex flex-column h-100">
                @csrf
                <input type="hidden" name="bed_id" x-model="panels.assign.bedId">
                
                <div class="slide-header">
                    <div>
                        <h4 class="fw-bold mb-0">Assign Bed</h4>
                        <div class="text-primary fw-bold mt-1">Room <span x-text="panels.assign.room"></span> &bull; Bed <span x-text="panels.assign.bed"></span></div>
                    </div>
                    <button type="button" class="btn-close" @click="panels.assign.open = false"></button>
                </div>
                
                <div class="slide-body">
                    <div class="mb-4">
                        <label class="form-label fw-bold">Select Student</label>
                        <select name="student_id" class="glass-input" required>
                            <option value="">Choose an active student...</option>
                            @foreach($unassignedStudents as $s)
                                <option value="{{ $s->id }}">{{ $s->name }} ({{ hostelease_phone($s->mobile) }})</option>
                            @endforeach
                        </select>
                        <div class="form-text mt-2"><i class="fa-solid fa-circle-info text-primary me-1"></i>Only showing students without an active bed assignment.</div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold">Join Date</label>
                        <input type="date" name="join_date" class="glass-input" value="{{ now()->toDateString() }}" required>
                        <div class="form-text mt-2">The financial billing cycles will start from this date.</div>
                    </div>
                </div>
                
                <div class="slide-footer">
                    <button type="button" class="btn btn-light rounded-pill px-4" @click="panels.assign.open = false">Cancel</button>
                    <button type="submit" class="btn btn-premium rounded-pill px-5 ms-auto">Confirm Assignment</button>
                </div>
            </form>
        </div>
    </template>

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
                        <h4 class="fw-bold mb-0">Bed Details</h4>
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

                    <div class="d-grid gap-3">
                        <button type="button" class="btn btn-light text-start p-3 rounded-4 fw-bold d-flex align-items-center" @click="openTransfer()">
                            <div class="bg-primary text-white rounded-3 p-2 me-3"><i class="fa-solid fa-right-left"></i></div>
                            <div>
                                <div class="fs-6">Transfer Bed</div>
                                <div class="small text-muted fw-normal">Move student to a different bed</div>
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

    <!-- Modals for Transfer and Release -->
    <div class="modal fade" id="transferModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" style="border-radius: 1.5rem; border: none; box-shadow: 0 20px 40px rgba(0,0,0,0.2);" :action="'/admin/property/assignments/' + panels.details.data.assignment_id + '/transfer'" method="POST">
                @csrf @method('PATCH')
                <div class="modal-header border-0 pb-0 mt-2 ms-2">
                    <h5 class="modal-title fw-bold fs-4">Transfer Student</h5>
                    <button type="button" class="btn-close me-2" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-muted">Move <strong x-text="panels.details.data.student_name"></strong> to a new bed.</p>
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold">Select New Bed</label>
                        <select name="bed_id" class="glass-input bg-light" required>
                            <option value="">Choose an available bed...</option>
                            @foreach($allFloors as $f)
                                <optgroup label="{{ $f->name }}">
                                    @foreach($f->rooms as $r)
                                        @foreach($r->beds as $b)
                                            <option value="{{ $b->id }}">Room {{ $r->room_number }} - Bed {{ $b->bed_number }}</option>
                                        @endforeach
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>
                    
                    <div class="mb-2">
                        <label class="form-label fw-bold">Transfer Date</label>
                        <input type="date" name="join_date" class="glass-input bg-light" value="{{ now()->toDateString() }}" required>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 mb-2 me-2">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-premium rounded-pill px-4">Transfer Now</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="releaseModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" style="border-radius: 1.5rem; border: none; box-shadow: 0 20px 40px rgba(0,0,0,0.2);" :action="'/admin/property/assignments/' + panels.details.data.assignment_id + '/release'" method="POST">
                @csrf @method('PATCH')
                <div class="modal-header border-0 pb-0 mt-2 ms-2">
                    <h5 class="modal-title fw-bold fs-4 text-danger">Release Student</h5>
                    <button type="button" class="btn-close me-2" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-muted mb-4">Release <strong x-text="panels.details.data.student_name"></strong> from bed <strong x-text="panels.details.bed"></strong>? The bed will become available immediately.</p>
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold">Leave Date</label>
                        <input type="date" name="leave_date" class="glass-input bg-light" value="{{ now()->toDateString() }}" required :min="panels.details.data.join_date_raw">
                    </div>
                    
                    <div class="form-check p-3 bg-danger-subtle rounded-3">
                        <input class="form-check-input ms-1" type="checkbox" name="mark_student_left" value="1" id="markLeft" checked>
                        <label class="form-check-label ms-2 text-danger fw-bold" for="markLeft">
                            Also mark student as "Left" (Vacating Hostel entirely)
                        </label>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 mb-2 me-2">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger rounded-pill px-4 shadow-sm">Release Student</button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('propertyBoard', () => ({
        searchQuery: '',
        panels: {
            assign: { open: false, bedId: '', room: '', bed: '' },
            details: { 
                open: false, bedId: '', room: '', bed: '', 
                data: { assignment_id: '', student_id: '', student_name: '', student_mobile: '', student_photo: '', join_date: '', join_date_raw: '', duration: '' } 
            }
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
        
        floorHasVisibleRooms(floorId) {
            // CSS flexbox handles it visually, but this can be enhanced if needed
            return true;
        },
        
        openAssign(bedId, room, bed) {
            this.panels.assign.bedId = bedId;
            this.panels.assign.room = room;
            this.panels.assign.bed = bed;
            this.panels.assign.open = true;
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
            const m = new bootstrap.Modal(document.getElementById('transferModal'));
            m.show();
        },

        openRelease() {
            this.closeDetails();
            const m = new bootstrap.Modal(document.getElementById('releaseModal'));
            m.show();
        }
    }));
});
</script>
@endpush
