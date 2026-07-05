@extends('layouts.app')
@section('title', 'Edit Student')

@section('content')
<div class="d-flex align-items-center gap-2 mb-3">
    <a href="{{ route('admin.students.show', $student) }}" class="btn btn-light btn-sm"><i class="fa-solid fa-arrow-left"></i></a>
    <h1 class="h4 fw-bold mb-0">Edit {{ $student->name }}</h1>
</div>

@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<div class="card stat-card">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.students.update', $student) }}" enctype="multipart/form-data">
            @csrf @method('PUT')
            @include('admin.students._form', ['student' => $student])
            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i> Update Student</button>
                <a href="{{ route('admin.students.show', $student) }}" class="btn btn-light">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
