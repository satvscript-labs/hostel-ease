@extends('layouts.app')
@section('title', 'Rooms')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 fw-bold mb-0">Rooms</h1>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.beds.layout') }}" class="btn btn-outline-primary">
            <i class="fa-solid fa-bed me-1"></i> Bed Layout
        </a>
        <a href="{{ route('admin.rooms.create') }}" class="btn btn-primary">
            <i class="fa-solid fa-plus me-1"></i> Add Room
        </a>
    </div>
</div>

<div class="card stat-card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" data-datatable>
                <thead>
                    <tr>
                        <th>Room</th><th>Floor</th><th>Type</th><th>Sharing</th>
                        <th>Beds (Occ/Total)</th><th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($rooms as $room)
                    <tr>
                        <td class="fw-semibold">{{ $room->room_number }}</td>
                        <td>{{ $room->floor->name }}</td>
                        <td>
                            <span class="badge bg-{{ $room->isAc() ? 'info' : 'secondary' }}-subtle text-{{ $room->isAc() ? 'info' : 'secondary' }}">
                                {{ $room->isAc() ? 'AC' : 'Non AC' }}
                            </span>
                        </td>
                        <td>{{ $room->sharing }} Sharing</td>
                        <td>
                            <span class="badge bg-danger-subtle text-danger">{{ $room->occupied_beds_count }}</span>
                            /
                            <span class="badge bg-primary-subtle text-primary">{{ $room->beds_count }}</span>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('admin.rooms.edit', $room) }}" class="btn btn-sm btn-light"><i class="fa-solid fa-pen"></i></a>
                            <form action="{{ route('admin.rooms.destroy', $room) }}" method="POST" class="d-inline"
                                  data-confirm="Delete room {{ $room->room_number }} and all its beds?">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-light text-danger"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
