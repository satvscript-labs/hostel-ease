@extends('layouts.app')
@section('title', 'Edit Student')

@section('content')
<div class="page-enter">
    <!-- Header -->
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('admin.students.show', $student) }}" class="btn btn-light btn-sm" style="border-radius: var(--he-radius-sm);">
            <i class="fa-solid fa-arrow-left"></i>
        </a>
        <h1 class="h4 fw-bold mb-0">Edit {{ $student->name }}</h1>
        <span class="badge-premium bg-{{ $student->status === 'active' ? 'success' : 'secondary' }}-subtle text-{{ $student->status === 'active' ? 'success' : 'secondary' }}">
            {{ ucfirst($student->status) }}
        </span>
    </div>

    @if($errors->any())
        <div class="alert alert-danger" style="border-radius: var(--he-radius-md);">
            <ul class="mb-0 small">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="card-premium p-3 p-md-4">
        <form method="POST" action="{{ route('admin.students.update', $student) }}" enctype="multipart/form-data">
            @csrf @method('PUT')
            @include('admin.students._form', ['student' => $student])

            <div class="mt-4 pt-3 d-flex flex-wrap gap-2 border-top" style="border-color: rgba(0,0,0,0.06) !important;">
                <button type="submit" class="btn btn-premium">
                    <i class="fa-solid fa-floppy-disk me-1"></i> Update Student
                </button>
                <a href="{{ route('admin.students.show', $student) }}" class="btn btn-light" style="border-radius: var(--he-radius-sm);">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
