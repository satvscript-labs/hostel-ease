@extends('layouts.app')
@section('title', __('Insights & Reports'))

@php
    $icons = [
        'collection' => 'fa-hand-holding-dollar', 
        'income'     => 'fa-chart-line',
        'occupancy'  => 'fa-chart-pie', 
        'pending'    => 'fa-hourglass-half', 
        'ac'         => 'fa-snowflake'
    ];
    $descriptions = [
        'collection' => __('Track daily and monthly fee collections.'),
        'income'     => __('Analyze total revenue and income streams.'),
        'occupancy'  => __('View real-time bed and room utilization.'),
        'pending'    => __('Monitor outstanding dues and pending payments.'),
        'ac'         => __('Track AC usage and related charges.')
    ];
@endphp

@section('content')
<div class="page-enter">
    <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between mb-4">
        <div>
            <h1 class="h3 fw-bold text-dark mb-1">{{ __('Insights & Reports') }}</h1>
            <p class="text-muted mb-0">{{ __('Analyze your hostel\'s performance, revenue, and occupancy.') }}</p>
        </div>
    </div>

    <style>
        .report-card {
            transition: all 0.3s cubic-bezier(0.25, 1, 0.5, 1);
            border: 1px solid rgba(0,0,0,0.05);
            border-radius: 1rem;
            background: #fff;
            overflow: hidden;
            position: relative;
        }
        .report-card::after {
            content: '';
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 3px;
            background: var(--bs-primary);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.4s cubic-bezier(0.25, 1, 0.5, 1);
        }
        .report-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.06);
            border-color: rgba(var(--bs-primary-rgb), 0.2);
        }
        .report-card:hover::after {
            transform: scaleX(1);
        }
        .report-icon-wrapper {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            background: rgba(var(--bs-primary-rgb), 0.1);
            color: var(--bs-primary);
            transition: all 0.3s ease;
        }
        .report-card:hover .report-icon-wrapper {
            background: var(--bs-primary);
            color: #fff;
            transform: scale(1.1) rotate(5deg);
        }
        .report-badge {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
    </style>

    <div class="row g-4">
        @foreach($types as $key => [$label, $needsRange])
            <div class="col-12 col-md-6 col-xl-4">
                <a href="{{ route('admin.reports.show', $key) }}" class="text-decoration-none text-dark">
                    <div class="card report-card h-100">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="report-icon-wrapper">
                                    <i class="fa-solid {{ $icons[$key] ?? 'fa-file-lines' }}"></i>
                                </div>
                                <span class="badge {{ $needsRange ? 'bg-primary-subtle text-primary' : 'bg-success-subtle text-success' }} report-badge rounded-pill px-3 py-2">
                                    {{ $needsRange ? __('Date Range') : __('Live Snapshot') }}
                                </span>
                            </div>
                            <h5 class="fw-bold mb-2">{{ $label }}</h5>
                            <p class="text-muted small mb-0">{{ $descriptions[$key] ?? __('View detailed analytics and metrics.') }}</p>
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>
</div>
@endsection
