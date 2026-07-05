@extends('layouts.app')
@section('title', 'Reports')

@php
    $icons = ['collection' => 'fa-hand-holding-dollar', 'income' => 'fa-chart-line',
        'occupancy' => 'fa-chart-pie', 'pending' => 'fa-hourglass-half', 'ac' => 'fa-snowflake'];
@endphp

@section('content')
<h1 class="h4 fw-bold mb-3">Reports</h1>

<div class="row g-3">
    @foreach($types as $key => [$label, $needsRange])
        <div class="col-6 col-md-4 col-xl-3">
            <a href="{{ route('admin.reports.show', $key) }}" class="text-decoration-none">
                <div class="card stat-card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="stat-icon bg-primary-subtle text-primary"><i class="fa-solid {{ $icons[$key] ?? 'fa-file' }}"></i></div>
                        <div>
                            <div class="fw-semibold">{{ $label }}</div>
                            <div class="stat-label">{{ $needsRange ? 'Date range' : 'Snapshot' }}</div>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    @endforeach
</div>
@endsection
