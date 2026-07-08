@extends('layouts.app')
@section('title', 'Pocket Money')

@section('content')
<style>
    /* Total Balance Widget */
    .pm-total-widget {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        border-radius: 1.5rem;
        padding: 2.5rem 2rem;
        color: white;
        margin-bottom: 2rem;
        position: relative;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: space-between;
        box-shadow: 0 20px 40px rgba(16, 185, 129, 0.2);
    }
    .pm-total-widget::after {
        content: '\f555';
        font-family: 'Font Awesome 6 Free';
        font-weight: 900;
        position: absolute;
        right: -20px;
        bottom: -40px;
        font-size: 10rem;
        opacity: 0.1;
        transform: rotate(-15deg);
        pointer-events: none;
    }
    .pm-total-label {
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
        opacity: 0.9;
        margin-bottom: 0.5rem;
        font-size: 0.85rem;
    }
    .pm-total-value {
        font-size: 3rem;
        font-weight: 800;
        line-height: 1;
        text-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }

    /* Student List */
    .pm-list-card {
        background: #fff;
        border-radius: 1.25rem;
        border: 1px solid #e2e8f0;
        overflow: hidden;
        box-shadow: 0 4px 6px rgba(0,0,0,0.02);
    }
    .pm-list-header {
        background: #f8fafc;
        padding: 1rem 1.5rem;
        border-bottom: 1px solid #e2e8f0;
        font-weight: 700;
        color: #64748b;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .pm-list-item {
        display: flex;
        align-items: center;
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid #f1f5f9;
        transition: all 0.2s;
    }
    .pm-list-item:hover {
        background: #f8fafc;
    }
    .pm-list-item:last-child {
        border-bottom: none;
    }
    
    .pm-avatar {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        object-fit: cover;
        margin-right: 1.25rem;
        border: 2px solid #fff;
        box-shadow: 0 4px 10px rgba(0,0,0,0.05);
    }
    .pm-student-name {
        font-weight: 700;
        color: #1e293b;
        font-size: 1.05rem;
        margin-bottom: 0.1rem;
    }
    
    .pm-balance-badge {
        font-weight: 800;
        padding: 0.4rem 1rem;
        border-radius: 2rem;
        font-size: 0.9rem;
    }
    .pm-balance-positive {
        background: #d1fae5;
        color: #047857;
        border: 1px solid #a7f3d0;
    }
    .pm-balance-zero {
        background: #f1f5f9;
        color: #64748b;
        border: 1px solid #e2e8f0;
    }
    .pm-balance-negative {
        background: #fee2e2;
        color: #b91c1c;
        border: 1px solid #fecaca;
    }
</style>

<div class="page-enter">
    
    <!-- Total Balance Widget -->
    <div class="pm-total-widget stagger-1">
        <div>
            <div class="pm-total-label">Total Pocket Money Held</div>
            <div class="pm-total-value">{{ hostelease_money($total) }}</div>
        </div>
        <div class="d-none d-md-block text-end">
            <h5 class="fw-bold mb-1">{{ count($students) }} Students</h5>
            <div class="opacity-75 small">Active Wallets</div>
        </div>
    </div>

    <!-- Student List -->
    <div class="pm-list-card stagger-2">
        <div class="pm-list-header d-none d-md-flex row m-0">
            <div class="col-5">Student</div>
            <div class="col-3">Mobile</div>
            <div class="col-2 text-center">Balance</div>
            <div class="col-2 text-end">Actions</div>
        </div>
        
        <div class="pm-list-body">
            @forelse($students as $s)
            <div class="pm-list-item row m-0 align-items-center" style="animation: fadeUp 0.6s cubic-bezier(0.25, 1, 0.5, 1) {{ min($loop->index * 0.05, 0.5) }}s both;">
                
                <!-- Student Info -->
                <div class="col-12 col-md-5 d-flex align-items-center mb-3 mb-md-0 px-0">
                    <img src="{{ $s->photo_url }}" class="pm-avatar" alt="{{ $s->name }}">
                    <div>
                        <div class="pm-student-name">{{ $s->name }}</div>
                        @if($s->activeAssignment)
                            <div class="small text-muted fw-semibold"><i class="fa-solid fa-bed me-1"></i> R:{{ $s->activeAssignment->bed->room->room_number }} / B:{{ $s->activeAssignment->bed->bed_number }}</div>
                        @else
                            <div class="small text-warning fw-semibold"><i class="fa-solid fa-triangle-exclamation me-1"></i> No Bed</div>
                        @endif
                    </div>
                </div>
                
                <!-- Mobile -->
                <div class="col-6 col-md-3 px-0">
                    <div class="d-md-none small text-muted fw-bold mb-1">Mobile</div>
                    @if($s->mobile)
                        <x-mobile-link :mobile="$s->mobile" class="fw-semibold text-decoration-none" />
                    @else 
                        <span class="text-muted">—</span> 
                    @endif
                </div>
                
                <!-- Balance -->
                <div class="col-6 col-md-2 text-md-center px-0 text-end text-md-start">
                    <div class="d-md-none small text-muted fw-bold mb-1">Balance</div>
                    <span class="pm-balance-badge {{ $s->pocket_balance > 0 ? 'pm-balance-positive' : ($s->pocket_balance < 0 ? 'pm-balance-negative' : 'pm-balance-zero') }}">
                        {{ hostelease_money($s->pocket_balance) }}
                    </span>
                </div>
                
                <!-- Action -->
                <div class="col-12 col-md-2 text-md-end px-0 mt-3 mt-md-0">
                    <a href="{{ route('admin.pocket-money.show', $s) }}" class="btn btn-primary rounded-pill fw-bold shadow-sm w-100 w-md-auto">
                        <i class="fa-solid fa-wallet me-1"></i> Manage
                    </a>
                </div>
            </div>
            @empty
            <div class="p-5 text-center text-muted">
                <i class="fa-solid fa-users d-block mb-3" style="font-size: 3rem; opacity: 0.5;"></i>
                <h5 class="fw-bold text-dark">No Students Found</h5>
                <p>Add active students to manage their pocket money.</p>
            </div>
            @endforelse
        </div>
    </div>
</div>
@endsection
