@extends('layouts.app')
@section('title', 'Layout Builder')

@push('styles')
<style>
    .builder-container {
        display: flex;
        gap: 2rem;
        height: calc(100vh - 120px);
        min-height: 600px;
    }
    
    /* Sidebar */
    .builder-sidebar {
        width: 320px;
        background: rgba(255,255,255,0.7);
        backdrop-filter: blur(20px);
        border: 1px solid rgba(255,255,255,0.5);
        border-radius: 1.5rem;
        box-shadow: 0 10px 30px rgba(0,0,0,0.03);
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    .sidebar-header {
        padding: 1.5rem;
        border-bottom: 1px solid rgba(0,0,0,0.05);
        background: linear-gradient(135deg, var(--he-primary), var(--he-accent));
        color: white;
    }
    .floor-list {
        flex: 1;
        overflow-y: auto;
        padding: 1rem;
    }
    .floor-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem 1.25rem;
        border-radius: 1rem;
        cursor: pointer;
        transition: all 0.2s;
        border: 1px solid transparent;
        margin-bottom: 0.5rem;
        background: white;
    }
    .floor-item:hover {
        background: rgba(79, 70, 229, 0.05);
    }
    .floor-item.active {
        background: var(--he-primary);
        color: white;
        box-shadow: 0 5px 15px rgba(79, 70, 229, 0.2);
    }
    .floor-item.active .text-muted { color: rgba(255,255,255,0.7) !important; }
    
    .editable-input {
        background: transparent;
        border: none;
        color: inherit;
        font-weight: inherit;
        font-size: inherit;
        width: 100%;
        outline: none;
        border-bottom: 1px dashed rgba(255,255,255,0.5);
    }
    
    /* Main Canvas */
    .builder-canvas {
        flex: 1;
        background: rgba(255,255,255,0.5);
        backdrop-filter: blur(10px);
        border-radius: 1.5rem;
        border: 1px dashed rgba(0,0,0,0.1);
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    .canvas-header {
        padding: 1.5rem 2rem;
        border-bottom: 1px solid rgba(0,0,0,0.05);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: white;
    }
    .rooms-grid {
        padding: 2rem;
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 1.5rem;
        overflow-y: auto;
        flex: 1;
    }
    
    /* Room Card */
    .room-card {
        background: white;
        border-radius: 1.25rem;
        padding: 1.5rem;
        box-shadow: 0 10px 25px rgba(0,0,0,0.02);
        border: 1px solid rgba(0,0,0,0.05);
        transition: transform 0.2s, box-shadow 0.2s;
        position: relative;
    }
    .room-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 15px 35px rgba(0,0,0,0.05);
    }
    
    .room-number-input {
        font-size: 1.5rem;
        font-weight: 800;
        color: var(--he-text-main);
        border: none;
        background: transparent;
        width: 100px;
        outline: none;
        border-bottom: 2px dashed rgba(0,0,0,0.1);
    }
    .room-number-input:focus { border-bottom-color: var(--he-primary); }
    
    .smart-toggle {
        display: inline-flex;
        background: rgba(0,0,0,0.05);
        border-radius: 50px;
        padding: 0.25rem;
    }
    .smart-toggle-btn {
        padding: 0.35rem 1rem;
        border-radius: 50px;
        font-size: 0.75rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s;
        color: var(--he-text-muted);
    }
    .smart-toggle-btn.active {
        background: white;
        color: var(--he-primary);
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .sharing-control {
        display: flex;
        align-items: center;
        gap: 1rem;
        background: rgba(0,0,0,0.02);
        padding: 0.5rem 1rem;
        border-radius: 1rem;
        border: 1px solid rgba(0,0,0,0.05);
    }
    .sharing-btn {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: white;
        border: 1px solid rgba(0,0,0,0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s;
    }
    .sharing-btn:hover { background: var(--he-primary); color: white; border-color: var(--he-primary); }
    
    .add-room-card {
        border: 2px dashed rgba(79, 70, 229, 0.3);
        background: rgba(79, 70, 229, 0.02);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        min-height: 220px;
        transition: all 0.2s;
        color: var(--he-primary);
    }
    .add-room-card:hover {
        background: rgba(79, 70, 229, 0.05);
        transform: translateY(-2px);
    }

    [x-cloak] { display: none !important; }
    
    /* Loading overlay */
    .loading-overlay {
        position: absolute;
        inset: 0;
        background: rgba(255,255,255,0.7);
        backdrop-filter: blur(2px);
        z-index: 10;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: inherit;
    }
</style>
@endpush

@section('content')
<div x-data="layoutBuilder(@js($floors))" class="page-enter" @keydown.window.escape="addingFloor = false">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1">Smart Layout Builder</h1>
            <p class="text-muted mb-0">Design your hostel hierarchy instantly without page reloads.</p>
        </div>
        <a href="{{ route('admin.property.index') }}" class="btn btn-light rounded-pill px-4 shadow-sm fw-bold">
            <i class="fa-solid fa-arrow-left me-2"></i>Back to Property Board
        </a>
    </div>

    <div class="builder-container">
        
        <!-- Sidebar: Floors -->
        <div class="builder-sidebar">
            <div class="sidebar-header">
                <h4 class="fw-bold mb-0">Floors</h4>
                <div class="small opacity-75">Select a floor to manage rooms</div>
            </div>
            
            <div class="floor-list">
                <template x-for="floor in floors" :key="floor.id">
                    <div class="floor-item" :class="{'active': activeFloor && activeFloor.id === floor.id}" @click="selectFloor(floor)">
                        <div>
                            <div class="fw-bold fs-5" x-text="floor.name"></div>
                            <div class="small text-muted"><span x-text="floor.rooms ? floor.rooms.length : 0"></span> Rooms</div>
                        </div>
                        <button class="btn btn-sm btn-link text-danger p-0" @click.stop="deleteFloor(floor.id)" title="Delete Floor">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </template>
                
                <!-- Add Floor Input -->
                <div x-show="addingFloor" class="mt-3 p-3 bg-light rounded-3 border" x-cloak>
                    <input type="text" x-model="newFloorName" class="form-control mb-2" placeholder="Floor Name (e.g. Ground Floor)" @keydown.enter="saveNewFloor" x-ref="newFloorInput">
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary btn-sm flex-fill" @click="saveNewFloor" :disabled="isLoading">Save</button>
                        <button class="btn btn-light btn-sm flex-fill" @click="addingFloor = false">Cancel</button>
                    </div>
                </div>
                
                <button x-show="!addingFloor" class="btn btn-outline-primary w-100 rounded-pill mt-3 fw-bold border-dashed" @click="startAddingFloor">
                    <i class="fa-solid fa-plus me-1"></i> Add Floor
                </button>
            </div>
        </div>

        <!-- Main Canvas: Rooms -->
        <div class="builder-canvas">
            <template x-if="!activeFloor">
                <div class="h-100 d-flex flex-column align-items-center justify-content-center text-muted">
                    <div class="bg-light rounded-circle p-4 mb-3 d-inline-block">
                        <i class="fa-solid fa-layer-group fs-1 opacity-50"></i>
                    </div>
                    <h4 class="fw-bold">Select a Floor</h4>
                    <p>Choose a floor from the sidebar to manage its layout.</p>
                </div>
            </template>
            
            <template x-if="activeFloor">
                <div class="h-100 d-flex flex-column">
                    <div class="canvas-header">
                        <div>
                            <h3 class="fw-bold mb-1 d-flex align-items-center">
                                <i class="fa-solid fa-layer-group text-primary me-2"></i>
                                <span x-text="activeFloor.name"></span>
                            </h3>
                            <div class="text-muted small">Auto-saving changes instantly.</div>
                        </div>
                        <div class="d-flex gap-2">
                            <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-2 fs-6">
                                <span x-text="activeFloor.rooms ? activeFloor.rooms.length : 0"></span> Rooms
                            </span>
                        </div>
                    </div>
                    
                    <div class="rooms-grid position-relative">
                        <div x-show="isLoading" class="loading-overlay" x-cloak>
                            <div class="spinner-border text-primary" role="status"></div>
                        </div>

                        <template x-for="room in activeFloor.rooms" :key="room.id">
                            <div class="room-card">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <div class="text-muted small fw-bold text-uppercase mb-1">Room No.</div>
                                        <input type="text" x-model="room.room_number" class="room-number-input" @change="updateRoom(room)">
                                    </div>
                                    <button class="btn btn-sm btn-light text-danger rounded-circle" @click="deleteRoom(room.id)" title="Delete Room">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </div>
                                
                                <div class="mb-4">
                                    <div class="text-muted small fw-bold text-uppercase mb-2">Room Type</div>
                                    <div class="smart-toggle">
                                        <div class="smart-toggle-btn" :class="{'active': room.room_type === 'ac'}" @click="room.room_type = 'ac'; updateRoomDebounced(room)">AC</div>
                                        <div class="smart-toggle-btn" :class="{'active': room.room_type === 'non_ac'}" @click="room.room_type = 'non_ac'; updateRoomDebounced(room)">Non-AC</div>
                                    </div>
                                </div>
                                
                                <div>
                                    <div class="text-muted small fw-bold text-uppercase mb-2">Sharing Capacity</div>
                                    <div class="sharing-control">
                                        <button class="sharing-btn" @click="if(room.sharing > 1) { room.sharing--; updateRoomDebounced(room); }"><i class="fa-solid fa-minus"></i></button>
                                        <div class="fw-bold fs-5 mx-2" style="width: 20px; text-align: center;" x-text="room.sharing"></div>
                                        <button class="sharing-btn" @click="if(room.sharing < 6) { room.sharing++; updateRoomDebounced(room); }"><i class="fa-solid fa-plus"></i></button>
                                    </div>
                                </div>
                                
                                <div class="mt-3 pt-3 border-top">
                                    <div class="d-flex justify-content-between text-muted small">
                                        <span>Beds Status</span>
                                        <span class="fw-bold"><span x-text="room.occupied_beds_count || 0"></span> / <span x-text="room.beds_count || room.sharing"></span> Occupied</span>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <!-- Add Room Button -->
                        <div class="room-card add-room-card" @click="addNewRoom">
                            <div class="bg-white rounded-circle p-3 shadow-sm mb-2 text-primary">
                                <i class="fa-solid fa-plus fs-4"></i>
                            </div>
                            <h5 class="fw-bold mb-0">Add New Room</h5>
                            <div class="small opacity-75">Click to add instantly</div>
                        </div>
                        
                    </div>
                </div>
            </template>
        </div>

    </div>

    <!-- Custom Alert Modal -->
    <div :class="{'d-block show': alertModal.show}" class="modal fade" tabindex="-1" style="background: rgba(15,23,42,0.4); backdrop-filter: blur(4px);" x-cloak>
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content" style="border-radius: 1.5rem; border: none; box-shadow: 0 20px 40px rgba(0,0,0,0.2);">
                <div class="modal-body p-4 text-center">
                    <div class="mb-3">
                        <template x-if="alertModal.type === 'error'">
                            <div class="d-inline-flex bg-danger-subtle text-danger rounded-circle p-3">
                                <i class="fa-solid fa-triangle-exclamation fs-2"></i>
                            </div>
                        </template>
                        <template x-if="alertModal.type === 'warning'">
                            <div class="d-inline-flex bg-warning-subtle text-warning rounded-circle p-3">
                                <i class="fa-solid fa-circle-exclamation fs-2"></i>
                            </div>
                        </template>
                        <template x-if="alertModal.type === 'info'">
                            <div class="d-inline-flex bg-primary-subtle text-primary rounded-circle p-3">
                                <i class="fa-solid fa-circle-info fs-2"></i>
                            </div>
                        </template>
                    </div>
                    <h5 class="fw-bold mb-2" x-text="alertModal.title"></h5>
                    <p class="text-muted mb-4" x-text="alertModal.message"></p>
                    
                    <div class="d-flex justify-content-center gap-2">
                        <template x-if="alertModal.isConfirm">
                            <button type="button" class="btn btn-light rounded-pill px-4" @click="alertModal.show = false">Cancel</button>
                        </template>
                        <button type="button" class="btn rounded-pill px-4" 
                                :class="{
                                    'btn-primary': alertModal.type === 'info', 
                                    'btn-danger': alertModal.type === 'error',
                                    'btn-warning': alertModal.type === 'warning'
                                }" 
                                @click="if(alertModal.onConfirm) alertModal.onConfirm(); alertModal.show = false">
                            <span x-text="alertModal.isConfirm ? 'Confirm' : 'OK'"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('layoutBuilder', (initialFloors) => ({
        floors: initialFloors,
        activeFloor: initialFloors.length > 0 ? initialFloors[0] : null,
        addingFloor: false,
        newFloorName: '',
        isLoading: false,

        alertModal: {
            show: false,
            title: '',
            message: '',
            type: 'info',
            isConfirm: false,
            onConfirm: null
        },

        showAlert(message, title = 'Notice', type = 'info') {
            this.alertModal.message = message;
            this.alertModal.title = title;
            this.alertModal.type = type;
            this.alertModal.isConfirm = false;
            this.alertModal.onConfirm = null;
            this.alertModal.show = true;
        },

        showConfirm(message, title = 'Confirm', type = 'warning', callback) {
            this.alertModal.message = message;
            this.alertModal.title = title;
            this.alertModal.type = type;
            this.alertModal.isConfirm = true;
            this.alertModal.onConfirm = callback;
            this.alertModal.show = true;
        },

        selectFloor(floor) {
            this.activeFloor = floor;
            if (!this.activeFloor.rooms) {
                this.activeFloor.rooms = [];
            }
        },

        startAddingFloor() {
            this.addingFloor = true;
            this.newFloorName = '';
            setTimeout(() => this.$refs.newFloorInput.focus(), 50);
        },

        async saveNewFloor() {
            if (!this.newFloorName.trim()) return;
            this.isLoading = true;
            
            try {
                const res = await fetch('{{ route('admin.floors.store') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ name: this.newFloorName })
                });
                
                const data = await res.json();
                if (data.success) {
                    data.floor.rooms = [];
                    this.floors.push(data.floor);
                    this.activeFloor = data.floor;
                    this.addingFloor = false;
                } else {
                    this.showAlert(data.message || 'Error adding floor.', 'Error', 'error');
                }
            } catch (e) {
                console.error(e);
                this.showAlert('Connection error.', 'Error', 'error');
            }
            this.isLoading = false;
        },

        deleteFloor(id) {
            this.showConfirm('Delete this floor? Make sure it has no rooms.', 'Delete Floor', 'error', async () => {
                this.isLoading = true;
                try {
                    const res = await fetch(`/admin/floors/${id}`, {
                        method: 'DELETE',
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                    });
                    const data = await res.json();
                    if (data.success) {
                        this.floors = this.floors.filter(f => f.id !== id);
                        if (this.activeFloor && this.activeFloor.id === id) {
                            this.activeFloor = this.floors.length > 0 ? this.floors[0] : null;
                        }
                    } else {
                        this.showAlert(data.error || 'Cannot delete floor.', 'Error', 'error');
                    }
                } catch (e) { console.error(e); this.showAlert('Error', 'Error', 'error'); }
                this.isLoading = false;
            });
        },

        async addNewRoom() {
            if (!this.activeFloor) return;
            this.isLoading = true;
            
            // Smart numbering logic
            let maxOnFloor = 0;
            if (this.activeFloor.rooms && this.activeFloor.rooms.length > 0) {
                this.activeFloor.rooms.forEach(r => {
                    let n = parseInt(r.room_number);
                    if (!isNaN(n) && n > maxOnFloor) maxOnFloor = n;
                });
            }
            
            let candidateNum = 101;
            if (maxOnFloor > 0) {
                candidateNum = maxOnFloor + 1;
            } else {
                let maxOverall = 0;
                this.floors.forEach(f => {
                    if (f.rooms) {
                        f.rooms.forEach(r => {
                            let n = parseInt(r.room_number);
                            if (!isNaN(n) && n > maxOverall) maxOverall = n;
                        });
                    }
                });
                if (maxOverall > 0) {
                    candidateNum = Math.floor(maxOverall / 100) * 100 + 101;
                }
            }

            // Ensure candidate is truly unique across the hostel
            let allRoomNumbers = new Set();
            this.floors.forEach(f => {
                if (f.rooms) {
                    f.rooms.forEach(r => allRoomNumbers.add(r.room_number.toString()));
                }
            });

            while (allRoomNumbers.has(candidateNum.toString())) {
                candidateNum++;
            }
            
            let nextNum = candidateNum.toString();
            
            try {
                const res = await fetch('{{ route('admin.rooms.store') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({
                        floor_id: this.activeFloor.id,
                        room_number: nextNum,
                        room_type: 'non_ac',
                        sharing: 1
                    })
                });
                const data = await res.json();
                if (data.success) {
                    this.activeFloor.rooms.push(data.room);
                } else {
                    this.showAlert(data.message || 'Error adding room.', 'Error', 'error');
                }
            } catch (e) { console.error(e); }
            this.isLoading = false;
        },

        updateRoomDebounceTimers: {},
        
        updateRoomDebounced(room) {
            if (this.updateRoomDebounceTimers[room.id]) {
                clearTimeout(this.updateRoomDebounceTimers[room.id]);
            }
            this.updateRoomDebounceTimers[room.id] = setTimeout(() => {
                this.updateRoom(room);
            }, 600);
        },

        async updateRoom(room) {
            this.isLoading = true;
            try {
                const res = await fetch(`/admin/rooms/${room.id}`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({
                        floor_id: room.floor_id,
                        room_number: room.room_number,
                        room_type: room.room_type,
                        sharing: room.sharing
                    })
                });
                const data = await res.json();
                if (data.success) {
                    // Update room state from server
                    Object.assign(room, data.room);
                } else {
                    this.showAlert(data.message || 'Validation error', 'Warning', 'warning');
                }
            } catch (e) { console.error(e); }
            this.isLoading = false;
        },

        deleteRoom(id) {
            this.showConfirm('Delete this room and all its beds?', 'Delete Room', 'error', async () => {
                this.isLoading = true;
                try {
                    const res = await fetch(`/admin/rooms/${id}`, {
                        method: 'DELETE',
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                    });
                    const data = await res.json();
                    if (data.success) {
                        this.activeFloor.rooms = this.activeFloor.rooms.filter(r => r.id !== id);
                    } else {
                        this.showAlert(data.error || 'Cannot delete room.', 'Error', 'error');
                    }
                } catch (e) { console.error(e); this.showAlert('Error', 'Error', 'error'); }
                this.isLoading = false;
            });
        }
    }));
});
</script>
@endpush
