@extends('layouts.app')
@section('title', 'Vacancy')

@section('content')
<h1 class="h4 fw-bold mb-3">Vacancy</h1>

{{-- Summary cards --}}
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="card stat-card"><div class="card-body d-flex align-items-center gap-3">
            <div class="stat-icon bg-success-subtle text-success"><i class="fa-solid fa-bed-pulse"></i></div>
            <div><div class="stat-value">{{ $emptyBeds->count() }}</div><div class="stat-label">Empty Beds</div></div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card"><div class="card-body d-flex align-items-center gap-3">
            <div class="stat-icon bg-danger-subtle text-danger"><i class="fa-solid fa-person-walking-arrow-right"></i></div>
            <div><div class="stat-value">{{ $windows[7] }}</div><div class="stat-label">Leaving ≤ 7 days</div></div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card"><div class="card-body d-flex align-items-center gap-3">
            <div class="stat-icon bg-warning-subtle text-warning"><i class="fa-solid fa-calendar-week"></i></div>
            <div><div class="stat-value">{{ $windows[15] }}</div><div class="stat-label">Leaving ≤ 15 days</div></div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card"><div class="card-body d-flex align-items-center gap-3">
            <div class="stat-icon bg-info-subtle text-info"><i class="fa-solid fa-calendar-days"></i></div>
            <div><div class="stat-value">{{ $windows[30] }}</div><div class="stat-label">Leaving ≤ 30 days</div></div>
        </div></div>
    </div>
</div>

{{-- Filters --}}
<form method="GET" class="card stat-card mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-6 col-md-3">
                <label class="form-label small mb-1">Floor</label>
                <select name="floor" class="form-select form-select-sm">
                    <option value="">All floors</option>
                    @foreach($floors as $f)<option value="{{ $f->id }}" @selected($floorId == $f->id)>{{ $f->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small mb-1">Room</label>
                <select name="room" class="form-select form-select-sm">
                    <option value="">All rooms</option>
                    @foreach($rooms as $r)<option value="{{ $r->id }}" @selected($roomId == $r->id)>{{ $r->room_number }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small mb-1">Sharing</label>
                <select name="sharing" class="form-select form-select-sm">
                    <option value="">Any</option>
                    @foreach(config('hostelease.sharing_options') as $n)<option value="{{ $n }}" @selected($sharing == $n)>{{ $n }} Sharing</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-md-3 d-flex gap-2">
                <button class="btn btn-primary btn-sm flex-grow-1"><i class="fa-solid fa-filter me-1"></i> Filter</button>
                <a href="{{ route('admin.vacancy.index') }}" class="btn btn-light btn-sm">Reset</a>
            </div>
        </div>
    </div>
</form>

<div class="row g-3">
    {{-- Empty beds --}}
    <div class="col-lg-6">
        <div class="card stat-card h-100">
            <div class="card-body">
                <h2 class="h6 fw-bold mb-3">Empty / Reserved Beds <span class="badge bg-success">{{ $emptyBeds->count() }}</span></h2>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead><tr><th>Floor</th><th>Room</th><th>Bed</th><th>Type</th><th></th></tr></thead>
                        <tbody>
                        @forelse($emptyBeds as $bed)
                            <tr>
                                <td>{{ $bed->room->floor->name }}</td>
                                <td>{{ $bed->room->room_number }}</td>
                                <td>
                                    <span class="badge bg-{{ $bed->status === 'reserved' ? 'warning text-dark' : 'success' }}">{{ $bed->bed_number }}</span>
                                </td>
                                <td>{{ $bed->room->isAc() ? 'AC' : 'Non AC' }} · {{ $bed->room->sharing }}-share</td>
                                <td class="text-end">
                                    <a href="{{ route('admin.assignments.create', ['bed' => $bed->id]) }}" class="btn btn-sm btn-primary">Assign</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-3">No empty beds match the filters.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Upcoming vacancies --}}
    <div class="col-lg-6">
        <div class="card stat-card h-100">
            <div class="card-body">
                <h2 class="h6 fw-bold mb-3">Upcoming Vacancies (next 30 days) <span class="badge bg-warning text-dark">{{ $upcoming->count() }}</span></h2>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead><tr><th>Student</th><th>Bed</th><th>Leaving</th><th>In</th></tr></thead>
                        <tbody>
                        @forelse($upcoming as $a)
                            @php($days = (int) ceil(now()->startOfDay()->diffInDays($a->student->leave_date, false)))
                            <tr>
                                <td><a href="{{ route('admin.students.show', $a->student) }}" class="text-decoration-none">{{ $a->student->name }}</a></td>
                                <td>{{ $a->bed->room->room_number }} / {{ $a->bed->bed_number }}</td>
                                <td>{{ $a->student->leave_date->format('d M Y') }}</td>
                                <td>
                                    <span class="badge bg-{{ $days <= 7 ? 'danger' : ($days <= 15 ? 'warning text-dark' : 'info') }}">
                                        {{ $days }}d
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-3">No upcoming vacancies in the next 30 days.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

