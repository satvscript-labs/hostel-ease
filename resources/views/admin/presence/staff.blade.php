@extends('layouts.app')
@section('title', __('Staff Presence'))

@push('styles')
@include('admin.presence._board_styles')
@endpush

@section('content')
@include('admin.presence._board_page')
@endsection

@push('scripts')
@include('admin.presence._board_scripts')
@endpush
