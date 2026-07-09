@extends('layouts.app')
@section('title', __('AC Bills'))

@section('content')
<div x-data="{ modalOpen: {{ $errors->any() ? 'true' : 'false' }} }" class="page-enter">
    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 fw-bold">{{ __('AC Bills') }}</h1>
            <p class="text-secondary">{{ __('Manage and generate monthly AC meter bills for rooms.') }}</p>
        </div>
        <div>
            <button type="button" class="btn rounded-pill px-4 fw-bold shadow-sm" style="background: var(--he-primary); color: #fff;" @click="modalOpen = true">
                <i class="fa-solid fa-bolt me-1"></i> {{ __('Generate AC Bill') }}
            </button>
        </div>
    </div>

    <!-- Bento Stats -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card h-100 border-0" style="background: var(--he-gradient-mesh); color: #fff; overflow: hidden; position: relative; border-radius: 1.25rem;">
                <div style="position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: radial-gradient(circle at 50% 50%, rgba(147, 51, 234, 0.3) 0%, transparent 50%); opacity: 0.5;"></div>
                <div class="card-body p-4 position-relative z-1 d-flex flex-column justify-content-between">
                    <div>
                        <div class="badge bg-white text-dark mb-3" style="background: rgba(255,255,255,0.1) !important; backdrop-filter: blur(4px); color: #fff !important; border: 1px solid rgba(255,255,255,0.2);">
                            <i class="fa-solid fa-snowflake text-info me-1"></i> Total AC Billed
                        </div>
                        <h2 class="display-6 fw-bold mb-0 text-white" style="font-feature-settings: 'tnum';">{{ hostelease_money($summary['billed']) }}</h2>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card glass-tile h-100 border-0 shadow-sm" style="border-radius: 1.25rem; background: #fff;">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="text-secondary small fw-bold text-uppercase" style="letter-spacing: 1px;">AC Collected</div>
                        <div class="tile-icon-wrapper" style="width: 40px; height: 40px; border-radius: 50%; background: var(--he-success-soft); color: var(--he-success); display: flex; align-items: center; justify-content: center; position: relative;">
                            <i class="fa-solid fa-sack-dollar"></i>
                            <div style="position: absolute; inset: 0; background: inherit; filter: blur(8px); z-index: -1;"></div>
                        </div>
                    </div>
                    <div class="h2 mb-0 fw-bold text-success" style="font-feature-settings: 'tnum';">{{ hostelease_money($summary['collected']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card glass-tile h-100 border-0 shadow-sm" style="border-radius: 1.25rem; background: #fff;">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="text-secondary small fw-bold text-uppercase" style="letter-spacing: 1px;">AC Dues</div>
                        <div class="tile-icon-wrapper" style="width: 40px; height: 40px; border-radius: 50%; background: var(--he-danger-soft); color: var(--he-danger); display: flex; align-items: center; justify-content: center; position: relative;">
                            <i class="fa-solid fa-clock-rotate-left"></i>
                            <div style="position: absolute; inset: 0; background: inherit; filter: blur(8px); z-index: -1;"></div>
                        </div>
                    </div>
                    <div class="h2 mb-0 fw-bold text-danger" style="font-feature-settings: 'tnum';">{{ hostelease_money($summary['due']) }}</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Premium Filters -->
    <style>
        .full-clickable-date::-webkit-calendar-picker-indicator {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
            opacity: 0;
        }
    </style>
    <div class="d-flex flex-wrap gap-3 mb-4 align-items-center position-relative" style="z-index: 100;">
        <form method="GET" x-data="{ floorOpen: false }" x-ref="filterForm" class="d-flex flex-wrap bg-white rounded-4 rounded-md-pill shadow-sm border p-2 align-items-center">
            <!-- Filter Month -->
            <div class="px-3 py-1 position-relative" style="min-width: 160px; overflow: hidden;">
                <input type="month" name="month" value="{{ $filterMonth->format('Y-m') }}" class="position-absolute w-100 h-100 top-0 start-0 full-clickable-date" style="opacity: 0; cursor: pointer; z-index: 20;" onchange="this.form.submit()">
                <div class="d-flex align-items-center gap-3" style="pointer-events: none;">
                    <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 38px; height: 38px;">
                        <i class="fa-solid fa-calendar-days"></i>
                    </div>
                    <div class="d-flex flex-column">
                        <span class="text-muted text-uppercase" style="font-size: 0.65rem; font-weight: 700; letter-spacing: 0.5px;">Filter By Month</span>
                        <span class="fw-bold fs-6 text-dark" style="line-height: 1;">{{ $filterMonth->format('F Y') }}</span>
                    </div>
                </div>
            </div>

            <div class="d-none d-md-block border-end mx-2" style="height: 32px;"></div>

            <!-- Floor Custom Dropdown -->
            <div class="position-relative px-3 py-1" style="min-width: 180px;">
                <input type="hidden" name="floor" value="{{ $filterFloor }}">
                <div class="d-flex align-items-center gap-3" @click="floorOpen = !floorOpen" style="cursor: pointer; z-index: 10;">
                    <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 38px; height: 38px;">
                        <i class="fa-solid fa-layer-group"></i>
                    </div>
                    <div class="d-flex flex-column">
                        <span class="text-muted text-uppercase" style="font-size: 0.65rem; font-weight: 700; letter-spacing: 0.5px;">Floor</span>
                        <span class="fw-bold fs-6 text-dark" style="line-height: 1;">
                            {{ $filterFloor ? $floors->firstWhere('id', $filterFloor)->name ?? 'All Floors' : 'All Floors' }}
                        </span>
                    </div>
                    <i class="fa-solid fa-chevron-down text-muted small ms-2 transition-all" :class="{'fa-chevron-up': floorOpen}"></i>
                </div>
                
                <div x-show="floorOpen" @click.outside="floorOpen = false" x-transition.opacity.duration.200ms class="position-absolute bg-white border rounded-4 shadow-lg mt-3" style="min-width: 240px; left: 0; display: none; z-index: 1050;">
                    <div class="list-group list-group-flush rounded-4 py-2">
                        <a href="javascript:void(0)" class="list-group-item list-group-item-action border-0 py-2 px-4 {{ !$filterFloor ? 'active bg-primary text-white fw-bold' : 'text-dark fw-medium' }}" @click="$refs.filterForm.floor.value=''; $refs.filterForm.submit()">
                            <i class="fa-solid fa-layer-group fa-fw me-2 {{ !$filterFloor ? '' : 'text-muted' }}"></i> All Floors
                        </a>
                        @foreach($floors as $f)
                        <a href="javascript:void(0)" class="list-group-item list-group-item-action border-0 py-2 px-4 {{ $filterFloor == $f->id ? 'active bg-primary text-white fw-bold' : 'text-dark fw-medium' }}" @click="$refs.filterForm.floor.value='{{ $f->id }}'; $refs.filterForm.submit()">
                            <i class="fa-solid fa-layer-group fa-fw me-2 {{ $filterFloor == $f->id ? '' : 'opacity-50 text-muted' }}"></i> {{ $f->name }}
                        </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- List View -->
    <div class="d-flex flex-column gap-3">
        @forelse($bills as $index => $bill)
        <div class="card border-0 shadow-sm rounded-4 ac-item" style="animation-delay: {{ min($index * 50, 500) }}ms;">
            <div class="card-body p-3 p-md-4">
                <div class="row align-items-center m-0 w-100">
                    
                    <div class="col-12 col-xl-3 d-flex align-items-center gap-3 mb-3 mb-xl-0 p-0">
                        <div class="avatar bg-info-subtle text-info fw-bold rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 48px; height: 48px;">
                            <i class="fa-solid fa-snowflake"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-dark lh-1 mb-1">Room {{ $bill->room->room_number }}</div>
                            <div class="text-muted small letter-spacing-1 lh-1">{{ optional($bill->room->floor)->name }}</div>
                        </div>
                    </div>

                    <div class="col-12 col-md-3 col-xl-2 mb-3 mb-md-0 p-0">
                        <div class="text-dark fw-bold lh-1 mb-1">{{ $bill->bill_month->format('M Y') }}</div>
                        <div class="text-muted small letter-spacing-1 lh-1">Generated</div>
                    </div>

                    <div class="col-12 col-md-3 col-xl-2 mb-3 mb-md-0 p-0" style="font-feature-settings: 'tnum';">
                        <div class="text-dark fw-bold lh-1 mb-1">{{ rtrim(rtrim(number_format($bill->total_units, 2), '0'), '.') }} Units</div>
                        <div class="text-muted small letter-spacing-1 lh-1">{{ hostelease_money($bill->unit_price) }} / unit</div>
                    </div>

                    <div class="col-12 col-md-4 col-xl-4 d-flex gap-4 align-items-center justify-content-md-end mb-3 mb-md-0 p-0" style="font-feature-settings: 'tnum';">
                        <div class="text-start text-md-end">
                            <div class="text-muted small fw-bold text-uppercase letter-spacing-1">Amount</div>
                            <div class="fw-bold">{{ hostelease_money($bill->total_amount) }}</div>
                        </div>
                        <div class="text-start text-md-end">
                            <div class="text-muted small fw-bold text-uppercase letter-spacing-1">Collected</div>
                            <div class="text-success fw-bold">{{ hostelease_money($bill->collected) }}</div>
                        </div>
                        <div class="text-start text-md-end">
                            <div class="text-muted small fw-bold text-uppercase letter-spacing-1">Split</div>
                            <div class="fw-semibold text-secondary">{{ $bill->shares_count }} students</div>
                        </div>
                    </div>
                    
                    <div class="col-12 col-md-2 col-xl-1 d-flex align-items-center justify-content-md-end gap-3 p-0">
                        <form action="{{ route('admin.ac-bills.destroy', $bill) }}" method="POST" onsubmit="return confirm('Delete this AC bill and its pending invoices?');">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-light rounded-circle text-danger shadow-sm" style="width: 36px; height: 36px;" title="Delete Bill">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </form>
                    </div>

                </div>
            </div>
        </div>
        @empty
        <div class="text-center py-5">
            <div class="empty-state">
                <i class="fa-solid fa-bolt text-secondary fs-1 mb-3 opacity-25" style="font-size: 4rem !important;"></i>
                <h4 class="fw-bold text-dark">No AC Bills</h4>
                <div class="text-secondary">Generate an AC bill for a room to get started.</div>
            </div>
        </div>
        @endforelse
    </div>

    <!-- Alpine.js Modal for "Generate AC Bill" -->
    <template x-teleport="body">
        <div class="custom-overlay-backdrop" x-show="modalOpen" x-transition.opacity @click="modalOpen = false" x-cloak style="display: none;">
            
            <form method="POST" action="{{ route('admin.ac-bills.store') }}" class="custom-overlay-modal" :class="{ 'is-open': modalOpen }" x-show="modalOpen" x-transition.opacity @click.stop style="display: none;">
                @csrf
                
                <div class="custom-overlay-header">
                    <h5 class="fw-bold mb-0"><i class="fa-solid fa-bolt text-warning me-2"></i> Generate AC Bill</h5>
                    <button type="button" class="btn-close" @click="modalOpen = false"></button>
                </div>
                
                @if($errors->any())
                <div class="alert alert-danger m-3 mb-0 border-0 shadow-sm rounded-3">
                    <ul class="mb-0 small">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif
                
                    <div class="custom-overlay-body" x-data="{
                        prev: 0,
                        curr: 0,
                        rate: {{ $defaultUnitPrice }},
                        roomId: '',
                        roomDropdown: false,
                        latestReadings: {{ json_encode($latestReadings) }},
                        updateReading() {
                            this.prev = this.latestReadings[this.roomId] || 0;
                        },
                        get units() { return Math.max(0, this.curr - this.prev); },
                        get amount() { return this.units * this.rate; }
                    }">
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1">Select Room <span class="text-danger">*</span></label>
                        <input type="hidden" name="room_id" :value="roomId">
                        
                        <div class="position-relative">
                            <div class="d-flex align-items-center justify-content-between form-control bg-light" @click="roomDropdown = !roomDropdown" style="cursor: pointer; height: 3rem;">
                                <span class="fw-semibold text-dark" x-html="roomId ? document.querySelector(`[data-room='${roomId}']`).dataset.label : 'Choose a room with students...'"></span>
                                <i class="fa-solid fa-chevron-down text-muted small transition-all" :class="{'fa-chevron-up': roomDropdown}"></i>
                            </div>
                            
                            <!-- Invisible Backdrop to intercept clicks -->
                            <div x-show="roomDropdown" @click="roomDropdown = false" class="position-fixed top-0 start-0 w-100 h-100" style="z-index: 1040; display: none;"></div>
                            
                            <div x-show="roomDropdown" x-transition.opacity.duration.200ms class="position-absolute bg-white border rounded-4 shadow-lg mt-2 w-100" style="display: none; z-index: 1050; max-height: 250px; overflow-y: auto;">
                                <div class="list-group list-group-flush rounded-4 py-2">
                                    <a href="javascript:void(0)" class="list-group-item list-group-item-action border-0 py-2 px-3 fw-medium text-muted" @click="roomId = ''; updateReading(); roomDropdown = false;">
                                        <i class="fa-solid fa-times-circle me-2 opacity-50"></i> Clear Selection
                                    </a>
                                    @foreach($rooms as $room)
                                    @php $studentsCount = $room->beds->map->activeAssignment->filter()->count(); @endphp
                                    <a href="javascript:void(0)" class="list-group-item list-group-item-action border-0 py-2 px-3 fw-medium d-flex align-items-center justify-content-between" data-room="{{ $room->id }}" data-label='<i class="fa-solid fa-snowflake text-info me-2"></i> Room {{ $room->room_number }} <span class="text-muted small ms-1">({{ $studentsCount }} students)</span>' :class="roomId === '{{ $room->id }}' ? 'active bg-primary text-white fw-bold' : 'text-dark'" @click="roomId = '{{ $room->id }}'; updateReading(); roomDropdown = false;">
                                        <span><i class="fa-solid fa-snowflake me-2" :class="roomId === '{{ $room->id }}' ? 'text-white' : 'text-info'"></i> Room {{ $room->room_number }}</span>
                                        <span class="small" :class="roomId === '{{ $room->id }}' ? 'text-white opacity-75' : 'text-muted'">{{ $studentsCount }} students</span>
                                    </a>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1">Bill Month <span class="text-danger">*</span></label>
                        <input type="month" name="bill_month" class="form-control bg-light" required value="{{ date('Y-m') }}">
                    </div>

                    <hr class="border-secondary opacity-10 my-4">
                    <h6 class="fw-bold text-muted text-uppercase mb-3" style="font-size: 0.75rem; letter-spacing: 1px;">Meter Readings</h6>
                    
                    <div class="row gx-3">
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">Previous Reading <span class="text-danger">*</span></label>
                            <input type="number" name="previous_reading" x-model.number="prev" class="form-control bg-light" required min="0" step="0.01">
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">Current Reading <span class="text-danger">*</span></label>
                            <input type="number" name="current_reading" x-model.number="curr" class="form-control bg-light" required min="0" step="0.01">
                        </div>
                    </div>

                    <div class="row gx-3">
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">Unit Rate (₹) <span class="text-danger">*</span></label>
                            <input type="number" name="unit_price" x-model.number="rate" class="form-control bg-light" required min="0.01" step="0.01">
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">Total Units</label>
                            <div class="form-control bg-light text-muted border-0 fw-bold" x-text="units.toFixed(2)">0.00</div>
                        </div>
                    </div>

                    <div class="p-3 bg-primary-subtle border border-primary-subtle rounded-3 d-flex align-items-center justify-content-between mt-2">
                        <span class="fw-bold text-primary text-uppercase letter-spacing-1 small">Total Bill Amount</span>
                        <span class="h4 mb-0 fw-bold text-primary">₹<span x-text="amount.toFixed(2)"></span></span>
                    </div>

                </div>
                
                <div class="custom-overlay-footer bg-light">
                    <button type="button" class="btn btn-white border fw-semibold rounded-pill px-4 tactile-btn" @click="modalOpen = false">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-semibold rounded-pill px-4 shadow-sm tactile-btn" :disabled="amount <= 0">
                        <i class="fa-solid fa-check me-2"></i> Generate Bill
                    </button>
                </div>
            </form>
        </div>
    </template>

</div>

@push('scripts')
<style>
    .ac-item {
        animation: cascadeIn 0.5s cubic-bezier(0.4, 0, 0.2, 1) both;
    }
    @keyframes cascadeIn {
        from { opacity: 0; transform: translateY(30px) scale(0.95); }
        to { opacity: 1; transform: translateY(0) scale(1); }
    }
</style>
@endpush
@endsection
