@extends('layouts.app')
@section('title', 'Add Room')

@section('content')
<div class="d-flex align-items-center gap-2 mb-3">
    <a href="{{ route('admin.rooms.index') }}" class="btn btn-light btn-sm"><i class="fa-solid fa-arrow-left"></i></a>
    <h1 class="h4 fw-bold mb-0">Add Room</h1>
</div>

@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

@if($floors->isEmpty())
    <div class="alert alert-warning">
        You have no floors yet. <a href="{{ route('admin.floors.index') }}">Add a floor</a> before creating rooms.
    </div>
@else
    <div class="card stat-card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.rooms.store') }}">
                @csrf
                @include('admin.rooms._form')
                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i> Create Room</button>
                    <a href="{{ route('admin.rooms.index') }}" class="btn btn-light">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endif
@endsection
