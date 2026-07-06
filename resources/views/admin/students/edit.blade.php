@extends('layouts.app')
@section('title', 'Edit Student')

@section('content')
<div class="page-enter">
    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('admin.students.show', $student) }}" class="btn btn-white rounded-circle shadow-sm d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                <i class="fa-solid fa-arrow-left text-muted"></i>
            </a>
            <div>
                <h1 class="h3 fw-bold mb-0">Edit {{ $student->name }}</h1>
            </div>
        </div>
        <span class="badge bg-{{ $student->status === 'active' ? 'success' : 'secondary' }}-subtle text-{{ $student->status === 'active' ? 'success' : 'secondary' }} rounded-pill px-3 py-2 fw-bold text-uppercase shadow-sm">
            {{ $student->status }}
        </span>
    </div>

    @if($errors->any())
        <div class="alert alert-danger rounded-4 border-danger-subtle mb-4">
            <ul class="mb-0 small fw-bold">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.students.update', $student) }}" enctype="multipart/form-data">
        @csrf @method('PUT')
        @include('admin.students._form', ['student' => $student])
    </form>
</div>
@endsection
