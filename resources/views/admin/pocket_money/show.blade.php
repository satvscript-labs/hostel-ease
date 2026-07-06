@extends('layouts.app')
@section('title', 'Pocket Money — '.$student->name)

@section('content')
<style>
    /* Digital Wallet Card */
    .wallet-card {
        background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
        border-radius: 1.5rem;
        padding: 2.5rem;
        color: white;
        position: relative;
        overflow: hidden;
        box-shadow: 0 20px 40px rgba(30, 58, 138, 0.2);
    }
    .wallet-card::before {
        content: '';
        position: absolute;
        top: -50px; right: -50px;
        width: 150px; height: 150px;
        border-radius: 50%;
        background: rgba(255,255,255,0.1);
        pointer-events: none;
    }
    .wallet-card::after {
        content: '';
        position: absolute;
        bottom: -30px; right: 40px;
        width: 100px; height: 100px;
        border-radius: 50%;
        background: rgba(255,255,255,0.05);
        pointer-events: none;
    }
    .wallet-label {
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        opacity: 0.8;
        font-size: 0.85rem;
        margin-bottom: 0.5rem;
    }
    .wallet-balance {
        font-size: 3.5rem;
        font-weight: 800;
        line-height: 1;
        margin-bottom: 1.5rem;
    }
    .wallet-owner {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding-top: 1.5rem;
        border-top: 1px solid rgba(255,255,255,0.2);
    }
    .wallet-avatar {
        width: 40px; height: 40px;
        border-radius: 50%;
        border: 2px solid rgba(255,255,255,0.5);
    }

    /* Action Widget */
    .action-widget {
        background: #fff;
        border-radius: 1.5rem;
        border: 1px solid #e2e8f0;
        box-shadow: 0 10px 25px rgba(0,0,0,0.02);
        overflow: hidden;
    }
    .action-tabs {
        display: flex;
        background: #f8fafc;
        padding: 0.5rem;
        gap: 0.5rem;
        border-bottom: 1px solid #e2e8f0;
    }
    .action-tab {
        flex: 1;
        padding: 0.75rem;
        text-align: center;
        font-weight: 700;
        border-radius: 1rem;
        cursor: pointer;
        transition: all 0.2s;
        color: #64748b;
    }
    .action-tab.active-deposit { background: #d1fae5; color: #047857; }
    .action-tab.active-withdraw { background: #fef08a; color: #b45309; }
    .action-body { padding: 1.5rem; }

    /* Timeline */
    .timeline {
        position: relative;
        padding-left: 2rem;
    }
    .timeline::before {
        content: '';
        position: absolute;
        top: 0; bottom: 0; left: 0.75rem;
        width: 2px;
        background: #e2e8f0;
    }
    .timeline-item {
        position: relative;
        margin-bottom: 1.5rem;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        padding: 1rem 1.25rem;
        box-shadow: 0 4px 6px rgba(0,0,0,0.01);
    }
    .timeline-icon {
        position: absolute;
        left: -2.35rem;
        top: 1rem;
        width: 32px; height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 0.85rem;
        border: 3px solid #fff;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    .bg-deposit { background: #10b981; }
    .bg-withdraw { background: #f59e0b; }
</style>

<div class="page-enter" x-data="{ tab: 'deposit' }">
    
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <h1 class="h4 fw-bold mb-0">Pocket Money Manager</h1>
        <a href="{{ route('admin.pocket-money.index') }}" class="btn btn-light rounded-pill px-3 fw-bold shadow-sm border">
            <i class="fa-solid fa-arrow-left me-1"></i> Directory
        </a>
    </div>

    <div class="row g-4">
        <!-- LEFT COLUMN: Wallet & Actions -->
        <div class="col-lg-5 col-xl-4">
            
            <!-- Digital Wallet Card -->
            <div class="wallet-card mb-4">
                <div class="wallet-label">Current Balance</div>
                <div class="wallet-balance">{{ hostelease_money($balance) }}</div>
                <div class="wallet-owner">
                    <img src="{{ $student->photo_url }}" class="wallet-avatar" alt="Avatar">
                    <div>
                        <div class="fw-bold" style="line-height:1.2">{{ $student->name }}</div>
                        <div class="small opacity-75">Student ID: {{ $student->id }}</div>
                    </div>
                </div>
            </div>

            <!-- Segmented Action Widget -->
            <div class="action-widget">
                <div class="action-tabs">
                    <div class="action-tab" :class="{ 'active-deposit': tab === 'deposit' }" @click="tab = 'deposit'">
                        <i class="fa-solid fa-arrow-down me-1"></i> Deposit
                    </div>
                    <div class="action-tab" :class="{ 'active-withdraw': tab === 'withdraw' }" @click="tab = 'withdraw'">
                        <i class="fa-solid fa-arrow-up me-1"></i> Withdraw
                    </div>
                </div>
                
                <div class="action-body">
                    <form method="POST" action="{{ route('admin.pocket-money.store', $student) }}">
                        @csrf
                        <input type="hidden" name="type" :value="tab">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">Amount <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light fw-bold border-end-0">{{ config('hostelease.currency') }}</span>
                                <input type="number" step="0.01" name="amount" class="form-control border-start-0 ps-0 fw-bold fs-5" placeholder="0.00" required>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold small text-muted">Note (Optional)</label>
                            <input type="text" name="note" class="form-control" placeholder="e.g. Monthly allowance, Medical expense...">
                        </div>
                        
                        <button type="submit" class="btn w-100 rounded-pill py-2 fw-bold text-white fs-6 shadow-sm" :class="tab === 'deposit' ? 'bg-deposit' : 'bg-withdraw'">
                            <span x-show="tab === 'deposit'"><i class="fa-solid fa-plus me-1"></i> Add Funds</span>
                            <span x-show="tab === 'withdraw'"><i class="fa-solid fa-minus me-1"></i> Deduct Funds</span>
                        </button>
                    </form>
                </div>
            </div>
            
        </div>

        <!-- RIGHT COLUMN: Timeline -->
        <div class="col-lg-7 col-xl-8">
            <div class="card stat-card border-0 shadow-sm h-100">
                <div class="card-body p-4 p-xl-5">
                    <h5 class="fw-bold mb-4 d-flex align-items-center">
                        <i class="fa-solid fa-clock-rotate-left text-primary me-2"></i> Transaction History
                    </h5>
                    
                    @if(count($transactions) > 0)
                        <div class="timeline">
                            @foreach($transactions as $t)
                                <div class="timeline-item d-flex justify-content-between align-items-center">
                                    <div class="timeline-icon {{ $t->type === 'deposit' ? 'bg-deposit' : 'bg-withdraw' }}">
                                        <i class="fa-solid {{ $t->type === 'deposit' ? 'fa-arrow-down' : 'fa-arrow-up' }}"></i>
                                    </div>
                                    
                                    <div>
                                        <div class="fw-bold fs-5 text-dark {{ $t->type === 'deposit' ? 'text-success' : 'text-danger' }}">
                                            {{ $t->type === 'deposit' ? '+' : '-' }}{{ hostelease_money($t->amount) }}
                                        </div>
                                        <div class="small text-muted fw-semibold mt-1">
                                            {{ $t->note ?? 'No note provided' }}
                                        </div>
                                    </div>
                                    
                                    <div class="text-end">
                                        <div class="small fw-bold text-muted">{{ $t->created_at->format('M d, Y') }}</div>
                                        <div class="small text-muted mb-2">{{ $t->created_at->format('h:i A') }}</div>
                                        
                                        <form action="{{ route('admin.pocket-money.destroy', [$student, $t]) }}" method="POST" data-confirm="Delete this transaction?">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-light text-danger rounded-pill px-3 shadow-sm border" title="Delete Transaction">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-5">
                            <i class="fa-solid fa-receipt d-block mb-3 text-muted opacity-50" style="font-size: 4rem;"></i>
                            <h5 class="fw-bold text-dark">No Transactions Yet</h5>
                            <p class="text-muted">Use the widget on the left to add or deduct funds.</p>
                        </div>
                    @endif
                    
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
