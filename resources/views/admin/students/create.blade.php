@extends('layouts.app')
@section('title', 'Add Student')

@section('content')
<div class="page-enter" x-data>
    {{-- Header --}}
    <a href="{{ route('admin.students.index') }}" class="btn btn-sm btn-white rounded-pill px-3 mb-3 shadow-sm fw-semibold"><i class="fa-solid fa-arrow-left me-1"></i> Students</a>
    <h1 class="form-page-title fw-bold mb-4">Add New Student</h1>

    @if($errors->any())
        <div class="alert alert-danger rounded-4 border-danger-subtle mb-4">
            <ul class="mb-0 small fw-bold">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.students.store') }}" enctype="multipart/form-data" id="studentForm">
        @csrf
        @include('admin.students._form', ['student' => null])
    </form>
</div>
@endsection
