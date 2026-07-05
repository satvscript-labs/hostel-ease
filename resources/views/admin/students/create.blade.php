@extends('layouts.app')
@section('title', 'Add Student')

@section('content')
<div class="page-enter">
    <!-- Header -->
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('admin.students.index') }}" class="btn btn-light btn-sm" style="border-radius: var(--he-radius-sm);">
            <i class="fa-solid fa-arrow-left"></i>
        </a>
        <h1 class="h4 fw-bold mb-0">Add Student</h1>
    </div>

    @if($errors->any())
        <div class="alert alert-danger" style="border-radius: var(--he-radius-md);">
            <ul class="mb-0 small">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="card-premium p-3 p-md-4">
        <form method="POST" action="{{ route('admin.students.store') }}" enctype="multipart/form-data">
            @csrf
            @include('admin.students._form', ['student' => null])

            <div class="mt-4 pt-3 d-flex flex-wrap gap-2 border-top" style="border-color: rgba(0,0,0,0.06) !important;">
                <button type="submit" class="btn btn-premium">
                    <i class="fa-solid fa-floppy-disk me-1"></i> Save Student
                </button>
                <a href="{{ route('admin.students.index') }}" class="btn btn-light" style="border-radius: var(--he-radius-sm);">Cancel</a>
            </div>
            <p class="text-muted mt-2 mb-0" style="font-size: var(--he-text-xs);">
                <i class="fa-solid fa-info-circle me-1"></i>
                You can upload documents and assign a bed from the student's profile after saving.
            </p>
        </form>
    </div>
</div>
@endsection
