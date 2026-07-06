@extends('layouts.app')
@section('title', 'Add Student')

@section('content')
<div class="page-enter">
    <!-- Header -->
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="{{ route('admin.students.index') }}" class="btn btn-white rounded-circle shadow-sm d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
            <i class="fa-solid fa-arrow-left text-muted"></i>
        </a>
        <h1 class="h3 fw-bold mb-0">Add New Student</h1>
    </div>

    @if($errors->any())
        <div class="alert alert-danger rounded-4 border-danger-subtle mb-4">
            <ul class="mb-0 small fw-bold">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.students.store') }}" enctype="multipart/form-data">
        @csrf
        @include('admin.students._form', ['student' => null])
    </form>
</div>
@endsection
