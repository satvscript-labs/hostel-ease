@extends('layouts.app')
@section('title', __('Presence'))

{{-- Shown in place of the whole Presence module while config('presence.enabled') is
     off. One page, no controls: nothing here can reach the gate-device code. --}}

@push('styles')
<style>
    .ps-wrap { max-width: 640px; margin: 3rem auto; padding: 0 0.5rem; }
    .ps-icon { width: 56px; height: 56px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem;
        color: var(--he-primary); background: color-mix(in srgb, var(--he-primary) 12%, transparent); margin-bottom: 1.25rem; }
    .ps-title { font-size: 1.6rem; font-weight: 800; letter-spacing: -0.02em; margin: 0 0 0.6rem; }
    .ps-sub { color: var(--he-text-muted); font-size: 1rem; line-height: 1.6; margin: 0 0 1.5rem; }
    .ps-list { list-style: none; padding: 0; margin: 0 0 1.75rem; display: grid; gap: 0.7rem; }
    .ps-list li { display: flex; gap: 0.75rem; align-items: flex-start; line-height: 1.5; }
    .ps-list i { color: var(--he-primary); margin-top: 0.25rem; width: 1rem; text-align: center; }
</style>
@endpush

@section('content')
<div class="ps-wrap">
    <div class="ps-icon"><i class="fa-solid fa-door-open"></i></div>
    <h1 class="ps-title">
        {{ __('Presence is coming soon') }}
        <span class="badge rounded-pill align-middle ms-2" style="background: color-mix(in srgb, var(--he-primary) 14%, transparent); color: var(--he-primary); font-size: 0.7rem;">{{ __('Premium') }}</span>
    </h1>
    <p class="ps-sub">{{ __('Connect a gate device and always know who is in the hostel and who is out. It will be available as a premium add-on.') }}</p>

    <ul class="ps-list">
        <li><i class="fa-solid fa-users"></i><span>{{ __('A live in / out board for students and for staff') }}</span></li>
        <li><i class="fa-solid fa-moon"></i><span>{{ __('Curfew alerts when a student is still out after hours') }}</span></li>
        <li><i class="fa-solid fa-list-check"></i><span>{{ __('A gate log, and attendance reports built from it') }}</span></li>
    </ul>

    <a href="mailto:{{ config('hostelease.company.email') }}?subject={{ rawurlencode(__('Presence add-on')) }}" class="btn btn-primary">
        <i class="fa-solid fa-envelope me-1"></i> {{ __('Ask about Presence') }}
    </a>
</div>
@endsection
