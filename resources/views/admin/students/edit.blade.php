@extends('layouts.app')
@section('title', 'Edit Student')

@section('content')
<div class="page-enter" x-data>
    {{-- Header --}}
    <a href="{{ route('admin.students.show', $student) }}" class="btn btn-sm btn-white rounded-pill px-3 mb-3 shadow-sm fw-semibold"><i class="fa-solid fa-arrow-left me-1"></i> Back to profile</a>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
        <h1 class="form-page-title fw-bold mb-0">Edit {{ $student->name }}</h1>
        <span class="badge bg-{{ $student->status === 'active' ? 'success' : 'secondary' }}-subtle text-{{ $student->status === 'active' ? 'success' : 'secondary' }} rounded-pill px-3 py-2 fw-bold text-uppercase shadow-sm">
            {{ $student->status }}
        </span>
    </div>

    @if($errors->any())
        <div class="alert alert-danger rounded-4 border-danger-subtle mb-4">
            <ul class="mb-0 small fw-bold">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.students.update', $student) }}" enctype="multipart/form-data" id="studentForm">
        @csrf @method('PUT')
        @include('admin.students._form', ['student' => $student])
    </form>
</div>
@endsection
