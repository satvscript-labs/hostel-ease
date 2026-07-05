@extends('layouts.app')
@section('title', 'Assign Student to Bed')

@section('content')
<div class="d-flex align-items-center gap-2 mb-3">
    <a href="{{ route('admin.assignments.index') }}" class="btn btn-light btn-sm"><i class="fa-solid fa-arrow-left"></i></a>
    <h1 class="h4 fw-bold mb-0">Assign Student to Bed</h1>
</div>

@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

@php
    $hasFreeBeds = $floors->flatMap->rooms->flatMap->beds->isNotEmpty();
@endphp

@if($students->isEmpty())
    <div class="alert alert-warning">No unassigned active students. <a href="{{ route('admin.students.create') }}">Add a student</a> first.</div>
@elseif(! $hasFreeBeds)
    <div class="alert alert-warning">No empty beds available. Free a bed or <a href="{{ route('admin.rooms.create') }}">add rooms</a>.</div>
@else
<div class="card stat-card">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.assignments.store') }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Student <span class="text-danger">*</span></label>
                    <select name="student_id" class="form-select" data-select2 required>
                        <option value="">Select student…</option>
                        @foreach($students as $s)
                            <option value="{{ $s->id }}" @selected(old('student_id') == $s->id)>
                                {{ $s->name }} ({{ hostelease_phone($s->mobile) }}) · {{ config('hostelease.occupation_types.'.$s->occupation_type) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Bed <span class="text-danger">*</span></label>
                    <select name="bed_id" id="bedSelect" class="form-select" data-select2 required>
                        <option value="">Select an available bed…</option>
                        @foreach($floors as $floor)
                            @foreach($floor->rooms as $room)
                                @if($room->beds->isNotEmpty())
                                    <optgroup label="{{ $floor->name }} — Room {{ $room->room_number }} ({{ $room->isAc() ? 'AC' : 'Non AC' }}, {{ $room->sharing }}-share)">
                                        @foreach($room->beds as $bed)
                                            <option value="{{ $bed->id }}"
                                                @selected(old('bed_id', $selectedBed?->id) == $bed->id)>
                                                Bed {{ $bed->bed_number }} @if($bed->status==='reserved')(Reserved)@endif
                                            </option>
                                        @endforeach
                                    </optgroup>
                                @endif
                            @endforeach
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Join Date <span class="text-danger">*</span></label>
                    <input type="date" name="join_date" class="form-control" value="{{ old('join_date', now()->toDateString()) }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Fee Amount (₹) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0" name="fee_amount" class="form-control"
                           value="{{ old('fee_amount') }}" placeholder="e.g. 25000" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Fee Frequency <span class="text-danger">*</span></label>
                    <select name="fee_frequency" id="feeFrequency" class="form-select" required>
                        @foreach(config('hostelease.fee_frequencies') as $k => $label)
                            <option value="{{ $k }}" @selected(old('fee_frequency', 'semester') === $k)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3" id="semesterWrap">
                    <label class="form-label">Semester <span class="text-danger">*</span></label>
                    <select name="semester" class="form-select">
                        @foreach(config('hostelease.semesters') as $s)
                            <option value="{{ $s }}" @selected(old('semester', '1') == $s)>Semester {{ $s }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Remarks</label>
                    <input type="text" name="remarks" class="form-control" value="{{ old('remarks') }}">
                </div>
            </div>
            <p class="text-muted small mt-2 mb-0">
                <i class="fa-solid fa-circle-info me-1"></i>
                The fee is recorded as a due on the student's profile the moment you assign them.
                <strong>Monthly</strong> creates this month's rent (and auto-generates each following month under <strong>Monthly Rent</strong>);
                <strong>Semester</strong> creates the chosen semester's fee under <strong>Semester Fees</strong>. Default is Semester.
            </p>
            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i> Assign</button>
                <a href="{{ route('admin.assignments.index') }}" class="btn btn-light">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
    (function () {
        var freq = document.getElementById('feeFrequency');
        var wrap = document.getElementById('semesterWrap');
        if (!freq || !wrap) return;
        var sem = wrap.querySelector('select');
        function sync() {
            var isSemester = freq.value === 'semester';
            wrap.style.display = isSemester ? '' : 'none';
            if (sem) sem.disabled = !isSemester;
        }
        freq.addEventListener('change', sync);
        sync();
    })();
</script>
@endpush

