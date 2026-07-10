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
    
    /* .sharing-control / .sharing-btn now live in _premium.scss — shared
       with the Fee-Plan Gate's sharing-preference stepper. */

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
<div x-data="layoutBuilder(@js($floors), {{ (int) $maxSharing }})" class="page-enter" @keydown.window.escape="addingFloor = false">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1">Smart Layout Builder</h1>
            <p class="text-muted mb-0">Design your hostel hierarchy instantly without page reloads.</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-light rounded-pill px-4 shadow-sm fw-bold" @click="openRoomSettings()">
                <i class="fa-solid fa-sliders me-2"></i>Room Settings
            </button>
            <a href="{{ route('admin.property.index') }}" class="btn btn-light rounded-pill px-4 shadow-sm fw-bold">
                <i class="fa-solid fa-arrow-left me-2"></i>Back to Property Board
            </a>
        </div>
    </div>

    <div class="builder-container">
        
        <!-- Sidebar: Floors -->
        <div class="builder-sidebar">
            <div class="sidebar-header">
                <h4 class="fw-bold mb-0">Floors</h4>
                <div class="small opacity-75">Select a floor to manage rooms</div>
            </div>
            
            <div class="floor-list" x-sort="reorderFloors">
                <template x-for="floor in floors" :key="floor.id">
                    <div class="floor-item" :class="{'active': activeFloor && activeFloor.id === floor.id}" @click="selectFloor(floor)" x-sort:item="floor.id">
                        <div class="d-flex align-items-center">
                            <i class="fa-solid fa-grip-vertical text-muted opacity-50 me-3 fs-5" style="cursor: grab;"></i>
                            <div>
                                <div class="fw-bold fs-5" x-text="floor.name"></div>
                                <div class="small text-muted"><span x-text="floor.rooms ? floor.rooms.length : 0"></span> Rooms</div>
                            </div>
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
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="sharing-control">
                                            <button class="sharing-btn" @click="room._pendingSharing = Math.max(1, (room._pendingSharing ?? room.sharing) - 1)"><i class="fa-solid fa-minus"></i></button>
                                            <div class="fw-bold fs-5 mx-2" style="width: 20px; text-align: center;" x-text="room._pendingSharing ?? room.sharing"></div>
                                            <button class="sharing-btn" @click="room._pendingSharing = Math.min(maxSharing, (room._pendingSharing ?? room.sharing) + 1)"><i class="fa-solid fa-plus"></i></button>
                                        </div>
                                        <button type="button" class="sharing-save-btn"
                                                x-show="(room._pendingSharing ?? room.sharing) !== room.sharing || room._justSaved"
                                                x-transition.opacity
                                                :class="{ 'is-saved': room._justSaved }"
                                                :disabled="room._savingSharing"
                                                :title="room._justSaved ? 'Saved' : 'Save sharing capacity'"
                                                @click="saveSharing(room)">
                                            <i class="fa-solid" :class="room._savingSharing ? 'fa-spinner fa-spin' : (room._justSaved ? 'fa-check' : 'fa-floppy-disk')" style="font-size: 0.75rem;"></i>
                                        </button>
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

    <!-- Room Settings — the hostel's own ceiling on beds-per-room. Drives
         the sharing stepper on every room card here, room-creation validation,
         and the Fee-Plan Gate's sharing-preference stepper on the Property
         Board — all read this one value, so raising it here is the only
         thing that ever needs to happen to support bigger dorm-style rooms. -->
    <template x-teleport="body">
        <div class="custom-overlay-backdrop" x-show="roomSettings.open" x-transition.opacity @click="roomSettings.open = false" x-cloak style="display: none;">
            <div class="custom-overlay-modal" :class="{ 'is-open': roomSettings.open }" x-show="roomSettings.open" x-transition.opacity @click.stop style="display: none; max-width: 440px;">
                <div class="custom-overlay-header">
                    <h5 class="fw-bold mb-0">
                        <i class="fa-solid fa-sliders" style="color: var(--he-primary);"></i>
                        <span class="ms-1">Room Settings</span>
                    </h5>
                    <button type="button" class="btn-close" @click="roomSettings.open = false"></button>
                </div>
                <div class="custom-overlay-body">
                    <label class="form-label fw-bold small text-uppercase letter-spacing-1 d-block mb-2">Maximum Sharing Capacity</label>
                    <p class="small text-muted mb-3">The largest number of beds allowed in a single room. Raise this before adding bigger dorm-style rooms — every sharing picker across the app follows this ceiling automatically.</p>

                    <div class="sharing-control">
                        <button type="button" class="sharing-btn" @click="roomSettings.value = Math.max(1, roomSettings.value - 1)">
                            <i class="fa-solid fa-minus"></i>
                        </button>
                        <div class="sharing-readout">
                            <div class="fs-4" x-text="roomSettings.value"></div>
                            <div class="small text-muted" x-text="roomSettings.value === 1 ? 'bed per room' : 'beds per room'"></div>
                        </div>
                        <button type="button" class="sharing-btn" @click="roomSettings.value = Math.min({{ (int) config('hostelease.max_room_sharing_limit', 30) }}, roomSettings.value + 1)">
                            <i class="fa-solid fa-plus"></i>
                        </button>
                    </div>

                    <div class="text-danger small mt-2" x-show="roomSettings.error" x-text="roomSettings.error"></div>
                </div>
                <div class="custom-overlay-footer bg-light">
                    <button type="button" class="btn btn-white border fw-semibold rounded-pill px-4 tactile-btn" @click="roomSettings.open = false">Cancel</button>
                    <button type="button" class="btn btn-primary fw-semibold rounded-pill px-4 shadow-sm tactile-btn" @click="saveRoomSettings()" :disabled="roomSettings.saving">
                        <span x-show="!roomSettings.saving"><i class="fa-solid fa-check me-2"></i>Save</span>
                        <span x-show="roomSettings.saving"><i class="fa-solid fa-spinner fa-spin me-2"></i>Saving...</span>
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('layoutBuilder', (initialFloors, initialMaxSharing = 7) => ({
        floors: initialFloors,
        activeFloor: initialFloors.length > 0 ? initialFloors[0] : null,
        addingFloor: false,
        newFloorName: '',
        isLoading: false,
        maxSharing: initialMaxSharing,

        // Eagerly give every room its per-card UI state so Alpine tracks
        // these props from the first render. Adding them lazily inside an
        // async callback leaves the button's :disabled/spinner bindings
        // un-tracked, so the spinner never clears when the save resolves.
        init() {
            this.floors.forEach(f => (f.rooms || []).forEach(r => this.normalizeRoom(r)));
        },

        normalizeRoom(room) {
            if (room._pendingSharing === undefined) room._pendingSharing = room.sharing;
            if (room._savingSharing === undefined) room._savingSharing = false;
            if (room._justSaved === undefined) room._justSaved = false;
            return room;
        },

        alertModal: {
            show: false,
            title: '',
            message: '',
            type: 'info',
            isConfirm: false,
            onConfirm: null
        },

        roomSettings: {
            open: false,
            value: initialMaxSharing,
            saving: false,
            error: '',
        },

        openRoomSettings() {
            this.roomSettings.value = this.maxSharing;
            this.roomSettings.error = '';
            this.roomSettings.open = true;
        },

        async saveRoomSettings() {
            this.roomSettings.saving = true;
            this.roomSettings.error = '';

            try {
                const res = await fetch('{{ route('admin.floors.sharing-settings') }}', {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ max_room_sharing: this.roomSettings.value }),
                });

                if (res.status === 422) {
                    const data = await res.json();
                    this.roomSettings.error = data.errors?.max_room_sharing?.[0] || 'Could not save — check the value.';
                    this.roomSettings.saving = false;
                    return;
                }
                if (!res.ok) throw new Error('Save failed');

                this.maxSharing = this.roomSettings.value;
                this.roomSettings.open = false;
            } catch (e) {
                console.error(e);
                this.roomSettings.error = 'Something went wrong — please try again.';
            } finally {
                this.roomSettings.saving = false;
            }
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

        reorderFloors(item, position) {
            let draggedFloor = this.floors.find(f => f.id === item);
            this.floors = this.floors.filter(f => f.id !== item);
            this.floors.splice(position, 0, draggedFloor);
            
            let orderedIds = this.floors.map(f => f.id);
            fetch('{{ route('admin.floors.reorder') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ ordered_ids: orderedIds })
            }).catch(e => console.error(e));
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
                    this.activeFloor.rooms.push(this.normalizeRoom(data.room));
                } else {
                    this.showAlert(data.message || 'Error adding room.', 'Error', 'error');
                }
            } catch (e) { console.error(e); }
            this.isLoading = false;
        },

        updateRoomDebounceTimers: {},
        roomUpdateInFlight: {},

        updateRoomDebounced(room) {
            if (this.updateRoomDebounceTimers[room.id]) {
                clearTimeout(this.updateRoomDebounceTimers[room.id]);
            }
            this.updateRoomDebounceTimers[room.id] = setTimeout(() => {
                this.updateRoom(room);
            }, 600);
        },

        async updateRoom(room) {
            // Prevent concurrent updates to the same room (SQLite race condition).
            if (this.roomUpdateInFlight[room.id]) return;

            this.isLoading = true;
            this.roomUpdateInFlight[room.id] = true;

            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 15000);

            try {
                const res = await fetch(`/admin/rooms/${room.id}`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({
                        floor_id: room.floor_id,
                        room_number: room.room_number,
                        room_type: room.room_type,
                        sharing: room.sharing
                    }),
                    signal: controller.signal,
                });
                const data = await res.json();
                if (data.success) {
                    // Update room state from server
                    Object.assign(room, data.room);
                } else {
                    this.showAlert(data.message || 'Validation error', 'Warning', 'warning');
                }
            } catch (e) {
                console.error(e);
                if (e.name === 'AbortError') {
                    this.showAlert('Save timed out — the server took too long to respond.', 'Error', 'error');
                }
            } finally {
                clearTimeout(timeoutId);
                this.isLoading = false;
                this.roomUpdateInFlight[room.id] = false;
            }
        },

        async saveSharing(room) {
            // Explicit save — the stepper only edits room._pendingSharing, so
            // one request fires per click regardless of how many +/- taps.
            if (room._savingSharing) return;

            const target = room._pendingSharing ?? room.sharing;
            room._savingSharing = true;

            try {
                const res = await fetch(`/admin/rooms/${room.id}`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({
                        floor_id: room.floor_id,
                        room_number: room.room_number,
                        room_type: room.room_type,
                        sharing: target
                    }),
                });
                const data = await res.json();
                if (data.success) {
                    Object.assign(room, data.room);
                    room._pendingSharing = room.sharing;
                    room._justSaved = true;
                    setTimeout(() => { room._justSaved = false; }, 1200);
                } else {
                    this.showAlert(data.message || 'Could not save sharing capacity.', 'Warning', 'warning');
                }
            } catch (e) {
                console.error(e);
                this.showAlert('Could not save sharing capacity — please try again.', 'Error', 'error');
            } finally {
                room._savingSharing = false;
            }
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
