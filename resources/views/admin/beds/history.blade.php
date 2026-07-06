@extends('layouts.app')
@section('title', 'Bed History')

@push('styles')
<style>
    .history-header {
        background: linear-gradient(135deg, var(--he-primary), var(--he-accent));
        color: white;
        border-radius: 1.5rem;
        padding: 3rem 2.5rem;
        position: relative;
        overflow: hidden;
        margin-bottom: 3rem;
        box-shadow: 0 20px 40px rgba(79, 70, 229, 0.25);
    }
    .header-mesh {
        position: absolute;
        inset: 0;
        opacity: 0.8;
        background-image: radial-gradient(at 80% 0%, rgba(0,0,0,0.3) 0px, transparent 50%), radial-gradient(at 0% 50%, rgba(255,255,255,0.1) 0px, transparent 50%);
        z-index: 1;
    }
    .history-header-content { position: relative; z-index: 2; }
    
    .timeline {
        position: relative;
        padding-left: 4rem; /* 64px */
        margin-top: 2rem;
    }
    .timeline::before {
        content: '';
        position: absolute;
        left: 1rem; /* 16px */
        top: 0;
        bottom: 0;
        width: 4px;
        background: rgba(0,0,0,0.05);
        border-radius: 4px;
    }

    .timeline-item {
        position: relative;
        margin-bottom: 2.5rem;
        opacity: 0;
        transform: translateY(20px);
        animation: fade-up 0.6s cubic-bezier(0.25, 1, 0.5, 1) forwards;
    }
    .timeline-item:nth-child(1) { animation-delay: 0.1s; }
    .timeline-item:nth-child(2) { animation-delay: 0.2s; }
    .timeline-item:nth-child(3) { animation-delay: 0.3s; }
    .timeline-item:nth-child(4) { animation-delay: 0.4s; }

    .timeline-marker {
        position: absolute;
        left: -3rem; /* Exactly on the line */
        top: 3.5rem; /* Center vertically with the avatar (24px padding + 32px half avatar) */
        transform: translate(-50%, -50%);
        width: 24px;
        height: 24px;
        border-radius: 50%;
        background: white;
        border: 4px solid var(--he-primary);
        box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        z-index: 2;
    }
    .timeline-item.active .timeline-marker {
        border-color: #10b981;
        box-shadow: 0 0 15px rgba(16, 185, 129, 0.4), 0 0 0 4px rgba(16, 185, 129, 0.1);
    }

    .timeline-card {
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(20px);
        border: 1px solid rgba(255,255,255,0.8);
        border-radius: 1.25rem;
        padding: 1.5rem;
        box-shadow: 0 10px 30px rgba(0,0,0,0.03);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .timeline-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 15px 40px rgba(0,0,0,0.06);
    }
    .timeline-item.active .timeline-card {
        border-color: rgba(16, 185, 129, 0.3);
        background: linear-gradient(to right, rgba(16, 185, 129, 0.02), rgba(255, 255, 255, 0.9));
    }

    @keyframes fade-up {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>
@endpush

@section('content')
<div class="page-enter">

    <div class="history-header">
        <div class="header-mesh"></div>
        <div class="history-header-content">
            <a href="{{ route('admin.property.index') }}" class="btn btn-light rounded-pill px-4 mb-4 fw-bold shadow-sm">
                <i class="fa-solid fa-arrow-left me-2"></i>Back to Property Board
            </a>
            
            <h1 class="display-5 fw-bold mb-2">Bed History</h1>
            <div class="d-flex align-items-center gap-3">
                <div class="fs-4 opacity-75">
                    {{ $bed->room->floor->name }} &bull; Room {{ $bed->room->room_number }} &bull; Bed {{ $bed->bed_number }}
                </div>
                <span class="badge bg-white text-dark rounded-pill px-3 py-2 fw-bold">
                    {{ ucfirst($bed->status) }}
                </span>
            </div>
        </div>
    </div>

    @if($assignments->isEmpty())
        <div class="text-center py-5">
            <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                <i class="fa-solid fa-clock-rotate-left fs-1 text-muted"></i>
            </div>
            <h3 class="fw-bold text-muted">No History</h3>
            <p class="text-muted">This bed has never been occupied.</p>
        </div>
    @else
        <div class="timeline">
            @foreach($assignments as $a)
                <div class="timeline-item {{ $a->is_active ? 'active' : '' }}">
                    <div class="timeline-marker"></div>
                    <div class="timeline-card d-flex flex-wrap gap-4 align-items-center">
                        <a href="{{ route('admin.students.show', $a->student) }}" class="flex-shrink-0 text-decoration-none">
                            <img src="{{ $a->student->photo_url }}" class="rounded-circle shadow-sm border border-2 border-primary" style="width: 64px; height: 64px; object-fit: cover;">
                        </a>
                        
                        <div class="flex-grow-1">
                            <h4 class="fw-bold mb-1">
                                <a href="{{ route('admin.students.show', $a->student) }}" class="text-decoration-none text-dark text-hover-primary">{{ $a->student->name }}</a>
                                @if($a->is_active)
                                    <span class="badge bg-success ms-2 rounded-pill shadow-sm">Currently Occupying</span>
                                @endif
                            </h4>
                            <div class="text-muted small">
                                <i class="fa-solid fa-phone me-1"></i> <x-mobile-link :mobile="$a->student->mobile" />
                            </div>
                            <div class="mt-2">
                                <a href="{{ route('admin.students.show', $a->student) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold">View Profile</a>
                            </div>
                        </div>
                        
                        <div class="bg-light rounded-3 p-3 text-center border">
                            <div class="small text-muted text-uppercase fw-bold mb-1">Duration</div>
                            <div class="fw-bold text-dark">{{ $a->durationInDays() }} Days</div>
                            <div class="small text-muted mt-1">
                                {{ $a->join_date->format('d M, Y') }} &mdash; 
                                {{ optional($a->leave_date)->format('d M, Y') ?? 'Present' }}
                            </div>
                        </div>

                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
