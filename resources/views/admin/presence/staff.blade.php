@extends('layouts.app')
@section('title', __('Staff Presence'))

@push('styles')
@include('admin.presence._board_styles')
@include('admin.presence._history_styles')
<style>
    .pb-name { text-align: left; }
    .pb-name:hover { color: var(--he-primary) !important; text-decoration: underline; }
    .pb-ios { cursor: pointer; }
</style>
@endpush

@section('content')
@include('admin.presence._board_page')
@endsection

@push('scripts')
@include('admin.presence._board_scripts')
@endpush
