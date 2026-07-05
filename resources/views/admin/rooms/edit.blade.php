@extends('layouts.app')
@section('title', 'Edit Room')

@section('content')
<div class="d-flex align-items-center gap-2 mb-3">
    <a href="{{ route('admin.rooms.index') }}" class="btn btn-light btn-sm"><i class="fa-solid fa-arrow-left"></i></a>
    <h1 class="h4 fw-bold mb-0">Edit Room {{ $room->room_number }}</h1>
</div>

@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

@if($room->occupied_beds_count > 0)
    <div class="alert alert-info py-2">
        <i class="fa-solid fa-circle-info me-1"></i>
        {{ $room->occupied_beds_count }} of {{ $room->beds_count }} beds are occupied.
        Reducing sharing only removes empty beds.
    </div>
@endif

<div class="card stat-card">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.rooms.update', $room) }}">
            @csrf @method('PUT')
            @include('admin.rooms._form')
            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i> Update Room</button>
                <a href="{{ route('admin.rooms.index') }}" class="btn btn-light">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
