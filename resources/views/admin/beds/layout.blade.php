@extends('layouts.app')
@section('title', 'Bed Layout')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 fw-bold mb-0">Bed Layout</h1>
    <form method="GET" class="d-flex gap-2">
        <select name="floor" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width:180px;">
            <option value="">All Floors</option>
            @foreach($allFloors as $f)
                <option value="{{ $f->id }}" @selected(request('floor') == $f->id)>{{ $f->name }}</option>
            @endforeach
        </select>
    </form>
</div>

{{-- Summary --}}
<div class="row g-3 mb-3">
    <div class="col-6 col-md">
        <div class="card stat-card"><div class="card-body py-3">
            <div class="stat-value">{{ $summary['occupancy_pct'] }}%</div>
            <div class="stat-label">Occupancy</div>
        </div></div>
    </div>
    <div class="col-6 col-md">
        <div class="card stat-card"><div class="card-body py-3">
            <div class="stat-value text-danger">{{ $summary['occupied'] }}</div>
            <div class="stat-label">Occupied</div>
        </div></div>
    </div>
    <div class="col-6 col-md">
        <div class="card stat-card"><div class="card-body py-3">
            <div class="stat-value text-success">{{ $summary['empty'] }}</div>
            <div class="stat-label">Empty</div>
        </div></div>
    </div>
    <div class="col-6 col-md">
        <div class="card stat-card"><div class="card-body py-3">
            <div class="stat-value text-warning">{{ $summary['reserved'] }}</div>
            <div class="stat-label">Reserved</div>
        </div></div>
    </div>
    <div class="col-6 col-md">
        <div class="card stat-card"><div class="card-body py-3">
            <div class="stat-value text-secondary">{{ $summary['maintenance'] }}</div>
            <div class="stat-label">Maintenance</div>
        </div></div>
    </div>
</div>

{{-- Legend --}}
<div class="d-flex flex-wrap gap-3 mb-3 small">
    <span><span class="legend-dot" style="background:#22c55e"></span> Empty</span>
    <span><span class="legend-dot" style="background:#ef4444"></span> Occupied</span>
    <span><span class="legend-dot" style="background:#eab308"></span> Reserved</span>
    <span><span class="legend-dot" style="background:#9ca3af"></span> Maintenance</span>
</div>

@forelse($floors as $floor)
    @php
        $floorBeds = $floor->rooms->flatMap->beds;
        $floorOcc = $floorBeds->where('status', 'occupied')->count();
        $floorTotal = $floorBeds->count();
        $floorPct = $floorTotal ? round($floorOcc / $floorTotal * 100) : 0;
    @endphp
    <div class="floor-block">
        <div class="d-flex align-items-center gap-2 mb-2">
            <h2 class="h6 fw-bold mb-0"><i class="fa-solid fa-layer-group text-primary me-1"></i>{{ $floor->name }}</h2>
            <span class="badge bg-light text-secondary">{{ $floorOcc }}/{{ $floorTotal }} beds · {{ $floorPct }}%</span>
        </div>

        @if($floor->rooms->isEmpty())
            <p class="text-muted small">No rooms on this floor.</p>
        @else
            <div class="row g-3">
                @foreach($floor->rooms as $room)
                    <div class="col-12 col-md-6 col-xl-4">
                        <div class="card stat-card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="fw-semibold">Room {{ $room->room_number }}</span>
                                    <span class="badge bg-{{ $room->isAc() ? 'info' : 'secondary' }}-subtle text-{{ $room->isAc() ? 'info' : 'secondary' }}">
                                        {{ $room->isAc() ? 'AC' : 'Non AC' }} · {{ $room->sharing }}-share
                                    </span>
                                </div>
                                <div class="bed-grid">
                                    @foreach($room->beds as $bed)
                                        @php
                                            $student = $bed->activeAssignment?->student;
                                            $pendingDues = $student ? $student->semesterFees()->where('status', '!=', 'paid')->get() : collect();
                                            $pendingAmount = $pendingDues->sum('balance');
                                            $bedData = [
                                                'id' => $bed->id,
                                                'bed_number' => $bed->bed_number,
                                                'status' => $bed->status,
                                                'room' => $room->room_number,
                                                'floor' => $floor->name,
                                                'student' => $student?->name,
                                                'mobile' => $student ? hostelease_phone($student->mobile) : null,
                                                'pending_dues' => $pendingDues->count(),
                                                'pending_amount' => (float) $pendingAmount,
                                            ];
                                        @endphp
                                        <div class="bed-tile bed-{{ $bed->status }} position-relative"
                                             role="button"
                                             onclick="showBed(@js($bedData))"
                                             title="{{ $pendingAmount > 0 ? '₹' . number_format($pendingAmount, 0) . ' pending' : '' }}">
                                            {{ $bed->bed_number }}
                                            @if($student)<small>{{ \Illuminate\Support\Str::limit($student->name, 8) }}</small>@endif
                                            @if($pendingAmount > 0)
                                                <span class="position-absolute top-0 end-0 translate-middle rounded-circle" style="width:14px; height:14px; background-color:#fbbf24; border:2px solid white;"></span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@empty
    <div class="card stat-card"><div class="card-body text-center text-muted py-5">
        No floors/rooms yet. Start by adding <a href="{{ route('admin.floors.index') }}">floors</a>
        and <a href="{{ route('admin.rooms.index') }}">rooms</a>.
    </div></div>
@endforelse

{{-- Bed detail modal --}}
<div class="modal fade" id="bedModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bedTitle">Bed</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <dl class="row mb-0">
                    <dt class="col-4 text-muted">Location</dt><dd class="col-8" id="bedLoc"></dd>
                    <dt class="col-4 text-muted">Status</dt><dd class="col-8" id="bedStatus"></dd>
                    <dd class="col-12" id="bedStudentWrap" hidden>
                        <hr>
                        <strong id="bedStudent"></strong><br>
                        <a id="bedCall" href="#" class="me-2"><i class="fa-solid fa-phone"></i> Call</a>
                        <a id="bedWa" href="#" target="_blank" class="text-success"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a>
                    </dd>
                </dl>
            </div>
            <div class="modal-footer" id="bedActions">
                {{-- status-change buttons injected by JS for non-occupied beds --}}
            </div>
        </div>
    </div>
</div>

<form id="statusForm" method="POST" class="d-none">@csrf @method('PATCH')<input type="hidden" name="status" id="statusValue"></form>
@endsection

@push('scripts')
<script>
    const statusUrlBase = "{{ url('admin/beds') }}";
    const assignUrl = "{{ route('admin.assignments.create') }}";
    const historyUrlBase = "{{ route('admin.beds.history', ['bed' => '__BED__']) }}";

    function showBed(b) {
        document.getElementById('bedTitle').textContent = 'Bed ' + b.bed_number;
        document.getElementById('bedLoc').textContent = b.floor + ' · Room ' + b.room;
        const labels = { empty: 'Empty', occupied: 'Occupied', reserved: 'Reserved', maintenance: 'Maintenance' };
        document.getElementById('bedStatus').textContent = labels[b.status] || b.status;

        const wrap = document.getElementById('bedStudentWrap');
        if (b.student) {
            wrap.hidden = false;
            let html = '<strong>' + b.student + '</strong>';
            if (b.pending_amount > 0) {
                html += '<br><span class="badge bg-danger">₹' + b.pending_amount.toLocaleString('en-IN') + ' pending (' + b.pending_dues + ' dues)</span>';
            }
            html += '<br><a href="tel:' + (b.mobile || '') + '" class="me-2"><i class="fa-solid fa-phone"></i> Call</a>';
            html += '<a href="https://wa.me/' + (b.mobile || '').replace('+', '') + '" target="_blank" class="text-success"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a>';
            document.getElementById('bedStudentWrap').innerHTML = '<dd class="col-12">' + html + '</dd>';
        } else {
            wrap.hidden = true;
        }

        // Status-change actions (only when not occupied)
        const actions = document.getElementById('bedActions');
        actions.innerHTML = '';

        // History link is always available.
        const hist = document.createElement('a');
        hist.className = 'btn btn-sm btn-light me-auto';
        hist.innerHTML = '<i class="fa-solid fa-clock-rotate-left me-1"></i>History';
        hist.href = historyUrlBase.replace('__BED__', b.id);
        actions.appendChild(hist);

        if (b.status !== 'occupied') {
            // Assign student to this free bed.
            const assign = document.createElement('a');
            assign.className = 'btn btn-sm btn-primary';
            assign.innerHTML = '<i class="fa-solid fa-bed-pulse me-1"></i>Assign';
            assign.href = assignUrl + '?bed=' + b.id;
            actions.appendChild(assign);

            [
                { s: 'empty', label: 'Set Empty', cls: 'btn-success' },
                { s: 'reserved', label: 'Reserve', cls: 'btn-warning' },
                { s: 'maintenance', label: 'Maintenance', cls: 'btn-secondary' },
            ].filter(o => o.s !== b.status).forEach(o => {
                const btn = document.createElement('button');
                btn.className = 'btn btn-sm ' + o.cls;
                btn.textContent = o.label;
                btn.onclick = () => changeStatus(b.id, o.s);
                actions.appendChild(btn);
            });
        } else {
            const note = document.createElement('small');
            note.className = 'text-muted';
            note.textContent = 'Manage from Bed Assignment.';
            actions.appendChild(note);
        }
        bootstrap.Modal.getOrCreateInstance(document.getElementById('bedModal')).show();
    }

    function changeStatus(bedId, status) {
        const form = document.getElementById('statusForm');
        form.action = statusUrlBase + '/' + bedId + '/status';
        document.getElementById('statusValue').value = status;
        form.submit();
    }
</script>
@endpush

