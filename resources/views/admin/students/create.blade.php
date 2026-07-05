@extends('layouts.app')
@section('title', 'Add Student')

@section('content')
<div class="d-flex align-items-center gap-2 mb-3">
    <a href="{{ route('admin.students.index') }}" class="btn btn-light btn-sm"><i class="fa-solid fa-arrow-left"></i></a>
    <h1 class="h4 fw-bold mb-0">Add Student</h1>
</div>

@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<div class="card stat-card">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.students.store') }}" enctype="multipart/form-data">
            @csrf
            @include('admin.students._form', ['student' => null])
            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i> Save Student</button>
                <a href="{{ route('admin.students.index') }}" class="btn btn-light">Cancel</a>
            </div>
            <p class="text-muted small mt-2 mb-0">You can upload documents (Aadhaar, agreement, etc.) from the student's profile after saving.</p>
        </form>
    </div>
</div>
@endsection
