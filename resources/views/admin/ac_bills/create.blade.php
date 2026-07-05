@extends('layouts.app')
@section('title', 'Generate AC Bill')

@section('content')
<div class="d-flex align-items-center gap-2 mb-3">
    <a href="{{ route('admin.ac-bills.index') }}" class="btn btn-light btn-sm"><i class="fa-solid fa-arrow-left"></i></a>
    <h1 class="h4 fw-bold mb-0">Generate AC Bill</h1>
</div>

@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

@if($acRooms->isEmpty())
    <div class="alert alert-warning">No AC rooms. Create a room with type <strong>AC</strong> first.</div>
@else
{{-- Step 1: pick room (reloads to load occupants) --}}
<form method="GET" class="card stat-card mb-3"><div class="card-body">
    <label class="form-label">AC Room <span class="text-danger">*</span></label>
    <div class="d-flex gap-2">
        <select name="room" class="form-select" onchange="this.form.submit()">
            <option value="">Select an AC room…</option>
            @foreach($acRooms as $r)
                <option value="{{ $r->id }}" @selected($selectedRoom && $selectedRoom->id == $r->id)>
                    {{ $r->floor->name }} · Room {{ $r->room_number }} ({{ $r->sharing }}-share)
                </option>
            @endforeach
        </select>
    </div>
</div></form>

@if($selectedRoom)
    @if($occupants->isEmpty())
        <div class="alert alert-warning">Room {{ $selectedRoom->room_number }} has no active occupants to bill.</div>
    @else
    <form method="POST" action="{{ route('admin.ac-bills.store') }}" class="card stat-card"><div class="card-body">
        @csrf
        <input type="hidden" name="room_id" value="{{ $selectedRoom->id }}">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Bill Month <span class="text-danger">*</span></label>
                <input type="month" name="bill_month" class="form-control" value="{{ old('bill_month', now()->format('Y-m')) }}" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Previous Unit <span class="text-danger">*</span></label>
                <input type="number" step="0.01" min="0" name="previous_unit" id="prev" class="form-control" value="{{ old('previous_unit', $lastReading) }}" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Current Unit <span class="text-danger">*</span></label>
                <input type="number" step="0.01" min="0" name="current_unit" id="curr" class="form-control" value="{{ old('current_unit') }}" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Unit Price (₹) <span class="text-danger">*</span></label>
                <input type="number" step="0.01" min="0" name="unit_price" id="rate" class="form-control" value="{{ old('unit_price') }}" required>
            </div>
        </div>

        <div class="alert alert-info d-flex justify-content-between mt-3 mb-3">
            <span>Units: <strong id="unitsOut">0</strong></span>
            <span>Total: <strong id="totalOut">₹0.00</strong></span>
            <span>Per student: <strong id="perOut">₹0.00</strong></span>
        </div>

        <label class="form-label">Distribution</label>
        <div class="mb-2">
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="distribution" value="equal" id="distEqual" checked onchange="toggleDist()">
                <label class="form-check-label" for="distEqual">Divide equally among all {{ $occupants->count() }} occupants</label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="distribution" value="selected" id="distSelected" onchange="toggleDist()">
                <label class="form-check-label" for="distSelected">Divide among selected students</label>
            </div>
        </div>

        <div id="studentList" class="row g-2 mb-3" style="display:none;">
            @foreach($occupants as $o)
                <div class="col-md-4">
                    <div class="form-check border rounded p-2">
                        <input class="form-check-input student-cb" type="checkbox" name="students[]" value="{{ $o->id }}" id="stu{{ $o->id }}" onchange="recalc()">
                        <label class="form-check-label" for="stu{{ $o->id }}">{{ $o->name }}</label>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-bolt me-1"></i> Generate & Split</button>
            <a href="{{ route('admin.ac-bills.index') }}" class="btn btn-light">Cancel</a>
        </div>
    </div></form>
    @endif
@endif
@endif
@endsection

@push('scripts')
<script>
    const totalOccupants = {{ $selectedRoom ? $occupants->count() : 0 }};
    function recalc() {
        const prev = parseFloat(document.getElementById('prev')?.value) || 0;
        const curr = parseFloat(document.getElementById('curr')?.value) || 0;
        const rate = parseFloat(document.getElementById('rate')?.value) || 0;
        const units = Math.max(0, curr - prev);
        const total = units * rate;

        const selected = document.getElementById('distSelected')?.checked;
        const checked = document.querySelectorAll('.student-cb:checked').length;
        const count = selected ? (checked || 0) : totalOccupants;

        document.getElementById('unitsOut').textContent = units.toFixed(2);
        document.getElementById('totalOut').textContent = '₹' + total.toFixed(2);
        document.getElementById('perOut').textContent = '₹' + (count ? (total / count) : 0).toFixed(2);
    }
    function toggleDist() {
        const selected = document.getElementById('distSelected').checked;
        document.getElementById('studentList').style.display = selected ? '' : 'none';
        recalc();
    }
    ['prev','curr','rate'].forEach(id => document.getElementById(id)?.addEventListener('input', recalc));
    recalc();
</script>
@endpush
