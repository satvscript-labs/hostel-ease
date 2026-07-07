@extends('layouts.app')
@section('title', $student->name)

@push('styles')
<style>
    :root {
        --he-panel-bg: rgba(255, 255, 255, 0.85);
        --he-backdrop: blur(24px);
        --he-border: 1px solid rgba(255, 255, 255, 0.9);
        --he-shadow-premium: 0 10px 40px rgba(0, 0, 0, 0.04);
    }
    
    .profile-layout {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }
    @media (min-width: 992px) {
        .profile-layout {
            grid-template-columns: 340px 1fr;
            align-items: start;
        }
    }
    
    .premium-panel {
        background: var(--he-panel-bg);
        backdrop-filter: var(--he-backdrop);
        border: var(--he-border);
        border-radius: 1.5rem;
        box-shadow: var(--he-shadow-premium);
        overflow: hidden;
    }
    
    /* Hero section */
    .hero-banner {
        height: 120px;
        background: linear-gradient(135deg, var(--he-primary), var(--he-accent));
        position: relative;
    }
    .hero-banner::after {
        content: '';
        position: absolute;
        inset: 0;
        opacity: 0.1;
        background-image: radial-gradient(circle at 20% 50%, rgba(255,255,255,0.9) 0%, transparent 50%);
    }
    .hero-avatar-wrap {
        position: absolute;
        bottom: -40px;
        left: 24px;
        width: 100px;
        height: 100px;
        border-radius: 50%;
        padding: 4px;
        background: white;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        z-index: 2;
    }
    .hero-avatar {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        object-fit: cover;
    }

    /* Bed Visual */
    .bed-visual {
        background: linear-gradient(135deg, rgba(79, 70, 229, 0.03), rgba(79, 70, 229, 0.01));
        border: 1px solid rgba(79, 70, 229, 0.1);
        border-radius: 1rem;
    }

    /* Quick Action Hub */
    .quick-action-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 0.75rem;
    }
    .qa-btn {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        padding: 1rem 0.5rem;
        border-radius: 1rem;
        background: white;
        border: 1px solid var(--he-border-color);
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        text-decoration: none;
        color: var(--he-text);
        font-weight: 600;
        font-size: 0.85rem;
    }
    .qa-btn i { font-size: 1.25rem; transition: transform 0.2s; }
    .qa-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.06);
        color: var(--he-primary);
        border-color: rgba(79, 70, 229, 0.3);
    }
    .qa-btn:hover i { transform: scale(1.1); }
    
    /* Segmented Tabs */
    .premium-tabs {
        display: inline-flex;
        background: rgba(0,0,0,0.04);
        padding: 0.4rem;
        border-radius: 1rem;
        gap: 0.25rem;
        overflow-x: auto;
        max-width: 100%;
    }
    .premium-tabs::-webkit-scrollbar { display: none; }
    .premium-tab {
        padding: 0.6rem 1.25rem;
        border-radius: 0.75rem;
        font-weight: 600;
        font-size: 0.9rem;
        color: var(--he-text-muted);
        border: none;
        background: transparent;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        white-space: nowrap;
    }
    .premium-tab:hover { color: var(--he-text); }
    .premium-tab.active {
        background: white;
        color: var(--he-primary);
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }

    /* Content Transition Container */
    .tab-content-container {
        position: relative;
        min-height: 500px;
        overflow: hidden;
    }
    .tab-pane-transition {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        padding: 1.5rem;
    }

    /* Timeline enhancements */
    .timeline-container { border-left: 2px solid var(--he-border-color); }
    .timeline-item { position: relative; margin-bottom: 1.5rem; padding-left: 1.5rem; }
    .timeline-marker {
        position: absolute;
        left: -17px;
        top: 0;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        border: 2px solid white;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 2;
    }
    
    .transition-hover {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .transition-hover:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.05);
    }
</style>
@endpush

@section('content')
<div class="page-enter" x-data="{ tab: 'overview' }">
    
    <!-- Top Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('admin.students.index') }}" class="btn btn-white rounded-circle shadow-sm d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                <i class="fa-solid fa-arrow-left text-muted"></i>
            </a>
            <h1 class="h3 fw-bold mb-0">Student Profile</h1>
        </div>
        <div class="d-flex gap-2">
            @if($qrSvg ?? false)
            <button class="btn btn-white rounded-pill px-3 shadow-sm d-none d-sm-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#qrModal">
                <i class="fa-solid fa-qrcode text-primary"></i> <span class="fw-bold">ID QR</span>
            </button>
            @endif
            <a href="{{ route('admin.students.edit', $student) }}" class="btn btn-premium rounded-pill px-4 shadow-sm fw-bold">
                <i class="fa-solid fa-pen me-2"></i>Edit Profile
            </a>
        </div>
    </div>

    <div class="profile-layout">
        
        <!-- LEFT SIDEBAR -->
        <div class="d-flex flex-column gap-4">
            
            <!-- Hero Card -->
            <div class="premium-panel">
                <div class="hero-banner">
                    <div class="hero-avatar-wrap">
                        <img src="{{ $student->photo_url }}" class="hero-avatar" alt="{{ $student->name }}">
                    </div>
                </div>
                <div class="px-4 pt-5 pb-4">
                    <div class="d-flex justify-content-between align-items-start mt-2">
                        <div>
                            <h2 class="h4 fw-bold mb-1 text-truncate" style="max-width: 180px;">{{ $student->name }}</h2>
                            <p class="text-muted mb-0 small fw-bold text-uppercase"><i class="fa-solid fa-id-badge me-1"></i> {{ config('hostelease.occupation_types.'.$student->occupation_type) }}</p>
                        </div>
                        <span class="badge bg-{{ $student->status === 'active' ? 'success' : 'secondary' }}-subtle text-{{ $student->status === 'active' ? 'success' : 'secondary' }} rounded-pill px-3 py-2 fw-bold text-uppercase shadow-sm">
                            {{ $student->status }}
                        </span>
                    </div>
                    
                    <div class="mt-4 pt-4 border-top">
                        <div class="d-flex flex-column gap-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-light rounded-circle d-flex align-items-center justify-content-center text-primary shadow-sm" style="width: 36px; height: 36px"><i class="fa-solid fa-phone"></i></div>
                                <div class="fw-bold"><x-mobile-link :mobile="$student->mobile" /></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Current Accommodation -->
            <div class="premium-panel p-4">
                <h3 class="h6 fw-bold mb-3 text-uppercase text-muted lh-1">Accommodation</h3>
                @if($student->activeAssignment)
                <div class="bed-visual p-3 text-center mb-3">
                    <div class="d-flex justify-content-center gap-4 align-items-center">
                        <div>
                            <div class="text-muted small fw-bold text-uppercase">Room</div>
                            <div class="fs-3 fw-bold text-dark lh-1 mt-1">{{ $student->activeAssignment->bed->room->room_number }}</div>
                        </div>
                        <div style="width: 2px; height: 36px; background: rgba(0,0,0,0.05); border-radius: 2px;"></div>
                        <div>
                            <div class="text-muted small fw-bold text-uppercase">Bed</div>
                            <div class="fs-3 fw-bold text-primary lh-1 mt-1">{{ $student->activeAssignment->bed->bed_number }}</div>
                        </div>
                    </div>
                    <div class="badge bg-white text-dark shadow-sm rounded-pill mt-3 px-4 py-2 border fw-bold">
                        <i class="fa-solid fa-building me-1 text-muted"></i> {{ $student->activeAssignment->bed->room->floor->name }}
                    </div>
                </div>
                <div class="d-grid">
                    <a href="{{ route('admin.property.index') }}" class="btn btn-light fw-bold text-primary rounded-pill border py-2">
                        <i class="fa-solid fa-right-left me-1"></i> Transfer Bed
                    </a>
                </div>
                @else
                <div class="text-center py-4 bg-light rounded-4 border border-dashed">
                    <i class="fa-solid fa-bed-pulse text-muted fs-1 mb-2"></i>
                    <p class="text-muted fw-bold mb-0">Not Assigned</p>
                    <a href="{{ route('admin.property.index') }}" class="btn btn-primary btn-sm rounded-pill mt-3 fw-bold px-4 shadow-sm">Assign Now</a>
                </div>
                @endif
            </div>

            <!-- Quick Actions Hub -->
            <div class="premium-panel p-4">
                <h3 class="h6 fw-bold mb-3 text-uppercase text-muted lh-1">Quick Actions</h3>
                <div class="quick-action-grid">
                    <a href="{{ route('admin.students.edit', $student) }}" class="qa-btn">
                        <i class="fa-solid fa-pen text-success"></i>
                        <span>Edit Profile</span>
                    </a>
                    <button type="button" class="qa-btn" @click="tab = 'fees'" data-bs-toggle="modal" data-bs-target="#collectModal" onclick="openCollect({{ $paymentSummary['outstanding'] ?? 0 }})">
                        <i class="fa-solid fa-indian-rupee-sign text-primary"></i>
                        <span>Collect Fee</span>
                    </button>
                    <a href="{{ route('admin.pocket-money.show', $student) }}" class="qa-btn">
                        <i class="fa-solid fa-wallet text-warning"></i>
                        <span>Add Funds</span>
                    </a>
                    <button type="button" class="qa-btn" @click="tab = 'documents'" data-bs-toggle="modal" data-bs-target="#docModal">
                        <i class="fa-solid fa-upload text-info"></i>
                        <span>Document</span>
                    </button>
                </div>
            </div>

        </div>

        <!-- RIGHT MAIN CONTENT -->
        <div class="d-flex flex-column gap-4">
            
            <!-- Financial Bento -->
            <div class="premium-panel p-4">
                <h3 class="h6 fw-bold mb-3 text-uppercase text-muted lh-1">Financial Overview</h3>
                <div class="row g-3">
                    <div class="col-6 col-md-3">
                        <div class="{{ ($paymentSummary['outstanding'] ?? 0) > 0 ? 'bg-danger-subtle border-danger-subtle' : 'bg-light border-light' }} rounded-4 p-3 h-100 border">
                            <div class="{{ ($paymentSummary['outstanding'] ?? 0) > 0 ? 'text-danger' : 'text-muted' }} small fw-bold text-uppercase mb-1">Outstanding</div>
                            <div class="fs-4 fw-bold lh-1 mt-2 {{ ($paymentSummary['outstanding'] ?? 0) > 0 ? 'text-danger' : 'text-dark' }}">{{ hostelease_money($paymentSummary['outstanding'] ?? 0) }}</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="bg-info-subtle bg-opacity-50 rounded-4 p-3 h-100 border border-info-subtle">
                            <div class="text-info-emphasis small fw-bold text-uppercase mb-1">Credits</div>
                            <div class="fs-4 fw-bold lh-1 text-info-emphasis mt-2">{{ hostelease_money($student->credit_balance ?? 0) }}</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="bg-success-subtle bg-opacity-50 rounded-4 p-3 h-100 border border-success-subtle">
                            <div class="text-success small fw-bold text-uppercase mb-1">Total Paid</div>
                            <div class="fs-4 fw-bold lh-1 text-success mt-2">{{ hostelease_money($paymentSummary['total_paid'] ?? 0) }}</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="bg-warning-subtle bg-opacity-50 rounded-4 p-3 h-100 border border-warning-subtle">
                            <div class="text-warning-emphasis small fw-bold text-uppercase mb-1">Pocket Money</div>
                            <div class="fs-4 fw-bold lh-1 text-warning-emphasis mt-2">{{ hostelease_money($pocketBalance ?? 0) }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content Tabs Container -->
            <div class="premium-panel flex-grow-1 d-flex flex-column">
                <!-- Tab Navigation -->
                <div class="border-bottom p-3 bg-light bg-opacity-50">
                    <div class="premium-tabs w-100 d-flex flex-nowrap">
                        <button type="button" class="premium-tab flex-fill" :class="{ active: tab === 'overview' }" @click="tab = 'overview'">Overview</button>
                        <button type="button" class="premium-tab flex-fill" :class="{ active: tab === 'fees' }" @click="tab = 'fees'">Invoices</button>
                        <button type="button" class="premium-tab flex-fill" :class="{ active: tab === 'documents' }" @click="tab = 'documents'">Documents</button>
                        <button type="button" class="premium-tab flex-fill" :class="{ active: tab === 'history' }" @click="tab = 'history'">Timeline</button>
                        <button type="button" class="premium-tab flex-fill" :class="{ active: tab === 'settings' }" @click="tab = 'settings'">Settings</button>
                    </div>
                </div>

                <!-- Tab Panes -->
                <div class="tab-content-container">
                    
                    <!-- OVERVIEW -->
                    <div x-show="tab === 'overview'" 
                         x-transition:enter="transition ease-out duration-300" 
                         x-transition:enter-start="opacity-0 transform translate-y-4" 
                         x-transition:enter-end="opacity-100 transform translate-y-0" 
                         x-cloak class="tab-pane-transition">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <h4 class="h5 fw-bold mb-3 d-flex align-items-center text-dark"><i class="fa-solid fa-id-card text-primary me-2"></i> Identity Details</h4>
                                <div class="bg-white bg-opacity-75 rounded-4 p-4 border border-white shadow-sm position-relative overflow-hidden" style="backdrop-filter: blur(10px);">
                                    <div class="position-relative z-1">
                                        <div class="d-flex justify-content-between align-items-center border-bottom border-light pb-3 mb-3">
                                            <span class="text-muted fw-bold small text-uppercase"><i class="fa-solid fa-hashtag me-1"></i> Aadhaar No.</span>
                                            <span class="fw-bold fs-6 text-dark font-monospace bg-light border px-2 py-1 rounded-2">{{ $student->aadhaar ? substr($student->aadhaar, 0, 4) . ' ' . substr($student->aadhaar, 4, 4) . ' ' . substr($student->aadhaar, 8, 4) : 'Not provided' }}</span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center border-bottom border-light pb-3 mb-3">
                                            <span class="text-muted fw-bold small text-uppercase"><i class="fa-solid fa-calendar-plus me-1"></i> Join Date</span>
                                            <span class="fw-bold fs-6 text-dark">
                                                @if($student->join_date)
                                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill fs-6">{{ $student->join_date->format('d M Y') }}</span>
                                                @else
                                                    —
                                                @endif
                                            </span>
                                        </div>
                                        <div class="mt-2">
                                            <div class="text-muted fw-bold small text-uppercase mb-2"><i class="fa-solid fa-location-dot me-1"></i> Permanent Address</div>
                                            <div class="bg-light bg-opacity-50 rounded-3 p-3 text-dark fw-bold border border-light">
                                                {{ $student->address ?? 'Not provided' }}<br>
                                                @if($student->city || $student->state)
                                                    <span class="text-muted">{{ $student->city }}, {{ $student->state }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h4 class="h5 fw-bold mb-3 d-flex align-items-center text-dark"><i class="fa-solid fa-users text-primary me-2"></i> Family Contacts</h4>
                                <div class="bg-white bg-opacity-75 rounded-4 p-4 border border-white shadow-sm" style="backdrop-filter: blur(10px);">
                                    <div class="d-flex flex-column gap-3">
                                        <!-- Father -->
                                        <div class="d-flex align-items-center gap-3 p-3 rounded-4 transition-hover border border-light bg-light bg-opacity-25">
                                            <div class="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center text-primary shadow-sm" style="width: 48px; height: 48px; font-size: 1.25rem;"><i class="fa-solid fa-person"></i></div>
                                            <div class="flex-grow-1">
                                                <div class="text-muted small fw-bold text-uppercase">Father</div>
                                                <div class="fw-bold fs-6"><x-mobile-link :mobile="$student->father_mobile" /></div>
                                            </div>
                                        </div>
                                        <!-- Mother -->
                                        <div class="d-flex align-items-center gap-3 p-3 rounded-4 transition-hover border border-light bg-light bg-opacity-25">
                                            <div class="bg-danger bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center text-danger shadow-sm" style="width: 48px; height: 48px; font-size: 1.25rem;"><i class="fa-solid fa-person-dress"></i></div>
                                            <div class="flex-grow-1">
                                                <div class="text-muted small fw-bold text-uppercase">Mother</div>
                                                <div class="fw-bold fs-6">
                                                    @if($student->mother_mobile)
                                                        <x-mobile-link :mobile="$student->mother_mobile" />
                                                    @else
                                                        <span class="text-muted fw-normal">Not provided</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Guardian -->
                                        <div class="d-flex align-items-center gap-3 p-3 rounded-4 transition-hover border border-light bg-light bg-opacity-25">
                                            <div class="bg-warning bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center text-warning shadow-sm" style="width: 48px; height: 48px; font-size: 1.25rem;"><i class="fa-solid fa-shield-halved"></i></div>
                                            <div class="flex-grow-1">
                                                <div class="text-muted small fw-bold text-uppercase">Guardian</div>
                                                <div class="fw-bold fs-6">
                                                    @if($student->guardian_mobile)
                                                        <x-mobile-link :mobile="$student->guardian_mobile" />
                                                    @else
                                                        <span class="text-muted fw-normal">Not provided</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- FEES & DUES -->
                    <div x-show="tab === 'fees'" 
                         x-transition:enter="transition ease-out duration-300" 
                         x-transition:enter-start="opacity-0 transform translate-y-4" 
                         x-transition:enter-end="opacity-100 transform translate-y-0" 
                         x-cloak class="tab-pane-transition">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
                            <h4 class="h5 fw-bold mb-0 d-flex align-items-center">
                                <i class="fa-solid fa-file-invoice-dollar text-primary me-2"></i> Invoices History
                            </h4>
                            <button type="button" class="btn btn-premium btn-sm rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#collectModal" onclick="openCollect({{ $paymentSummary['outstanding'] ?? 0 }})">
                                <i class="fa-solid fa-indian-rupee-sign me-1"></i> Collect Payment
                            </button>
                        </div>
                        
                        <div class="d-flex flex-column gap-2">
                        @forelse($invoices as $invoice)
                            <div class="bg-light bg-opacity-50 border rounded-4 p-3 transition-hover">
                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                                    <div class="d-flex gap-3 align-items-center">
                                        <div class="bg-{{ $invoice->status === 'paid' ? 'success' : ($invoice->status === 'partial' ? 'warning' : 'danger') }}-subtle text-{{ $invoice->status === 'paid' ? 'success' : ($invoice->status === 'partial' ? 'warning' : 'danger') }} rounded-circle d-flex align-items-center justify-content-center" style="width: 44px; height: 44px; font-size: 1.1rem;">
                                            <i class="fa-solid fa-{{ $invoice->type === 'fee' ? 'graduation-cap' : ($invoice->type === 'rent' ? 'home' : ($invoice->type === 'ac' ? 'snowflake' : 'receipt')) }}"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold fs-6">{{ $invoice->title }}</div>
                                            <div class="text-secondary small fw-bold text-uppercase">{{ $invoice->type }}</div>
                                            @if($invoice->status !== 'paid' && $invoice->due_date)
                                                <div class="small text-danger fw-bold mt-1"><i class="fa-regular fa-clock me-1"></i> Due: {{ $invoice->due_date->format('d M Y') }}</div>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-bold fs-5">{{ hostelease_money($invoice->amount) }}</div>
                                        <div class="small fw-bold {{ $invoice->status === 'paid' ? 'text-success' : 'text-danger' }} text-uppercase mt-1">
                                            @if($invoice->status === 'paid')
                                                <i class="fa-solid fa-check-circle me-1"></i> Fully Paid
                                            @else
                                                Bal: {{ hostelease_money($invoice->balance) }}
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-5 bg-light rounded-4 border border-dashed">
                                <i class="fa-solid fa-check-circle text-success fs-1 mb-2"></i>
                                <h5 class="fw-bold text-dark">No invoices found</h5>
                                <p class="text-muted mb-0">Student is all cleared up!</p>
                            </div>
                        @endforelse
                        </div>
                    </div>

                    <!-- DOCUMENTS -->
                    <div x-show="tab === 'documents'" 
                         x-transition:enter="transition ease-out duration-300" 
                         x-transition:enter-start="opacity-0 transform translate-y-4" 
                         x-transition:enter-end="opacity-100 transform translate-y-0" 
                         x-cloak class="tab-pane-transition">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h4 class="h5 fw-bold mb-0 d-flex align-items-center"><i class="fa-solid fa-file-lines text-primary me-2"></i> Uploaded Documents</h4>
                            <button type="button" class="btn btn-premium btn-sm rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#docModal">
                                <i class="fa-solid fa-upload me-1"></i> Upload
                            </button>
                        </div>

                        <div class="d-flex flex-column gap-2">
                        @forelse($student->documents as $doc)
                            <div class="bg-light bg-opacity-50 border rounded-4 p-3 transition-hover">
                                <div class="d-flex justify-content-between align-items-center gap-2">
                                    <div class="d-flex align-items-center gap-3 min-width-0">
                                        <div class="bg-primary-subtle text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px; font-size: 1.2rem;">
                                            <i class="fa-solid fa-{{ in_array($doc->type, ['photo']) ? 'image' : 'file-pdf' }}"></i>
                                        </div>
                                        <div class="min-width-0">
                                            <div class="fw-bold fs-6 text-truncate text-dark">{{ $doc->title ?: ucfirst($doc->type) }}</div>
                                            <div class="mt-1 d-flex gap-2 flex-wrap align-items-center">
                                                <span class="badge bg-primary-subtle text-primary fw-bold text-uppercase">{{ $doc->type }}</span>
                                                @if($doc->expiry_date)
                                                    <span class="small fw-bold text-muted"><i class="fa-regular fa-calendar-xmark"></i> Exp: {{ $doc->expiry_date->format('d M Y') }}</span>
                                                @endif
                                                @if($doc->is_signed)
                                                    <span class="small fw-bold text-success"><i class="fa-solid fa-check-circle"></i> Signed</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex gap-2 flex-shrink-0">
                                        <a href="{{ Storage::disk('public')->url($doc->file_path) }}" target="_blank" class="btn btn-white text-primary rounded-circle shadow-sm d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                            <i class="fa-solid fa-eye"></i>
                                        </a>
                                        <form action="{{ route('admin.students.documents.destroy', [$student, $doc]) }}" method="POST" class="d-inline" data-confirm="Delete this document?">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-white text-danger rounded-circle shadow-sm d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-5 bg-light rounded-4 border border-dashed">
                                <i class="fa-solid fa-folder-open text-muted fs-1 mb-2 d-block"></i>
                                <h5 class="fw-bold text-dark">No documents found</h5>
                                <p class="text-muted mb-3">Keep important student files secure here.</p>
                                <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#docModal">
                                    <i class="fa-solid fa-upload me-1"></i> Upload Document
                                </button>
                            </div>
                        @endforelse
                        </div>
                    </div>

                    <!-- TIMELINE -->
                    <div x-show="tab === 'history'" 
                         x-transition:enter="transition ease-out duration-300" 
                         x-transition:enter-start="opacity-0 transform translate-y-4" 
                         x-transition:enter-end="opacity-100 transform translate-y-0" 
                         x-cloak class="tab-pane-transition">
                        <h4 class="h5 fw-bold mb-4 d-flex align-items-center"><i class="fa-solid fa-clock-rotate-left text-primary me-2"></i> Activity Timeline</h4>
                        
                        <div class="timeline-container ps-2 ms-3 mt-4">
                            @forelse($timeline as $event)
                                <div class="timeline-item ps-4">
                                    <div class="timeline-marker bg-{{ $event->color }}-subtle text-{{ $event->color }} shadow-sm">
                                        <i class="fa-solid fa-{{ $event->icon }} small"></i>
                                    </div>
                                    <div class="bg-light bg-opacity-50 border rounded-4 py-3 px-4 m-0 transition-hover">
                                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                                            <div>
                                                <div class="fw-bold text-dark fs-6">{{ $event->title }}</div>
                                                <div class="mt-1 small fw-bold text-muted">
                                                    {{ \Carbon\Carbon::parse($event->date)->format('d M Y, h:i A') }}
                                                    @if(isset($event->desc)) · {{ $event->desc }} @endif
                                                </div>
                                            </div>
                                            @if(isset($event->amount))
                                                <div class="text-end">
                                                    <div class="fw-bold fs-5 text-{{ $event->color }}">{{ hostelease_money($event->amount) }}</div>
                                                    <div class="small fw-bold text-muted text-uppercase mt-1">{{ $event->status }}</div>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center py-5 border border-dashed rounded-4 bg-light ms-3">
                                    <i class="fa-solid fa-clock text-muted fs-1 mb-2"></i>
                                    <h5 class="fw-bold text-dark">No History</h5>
                                    <p class="text-muted mb-0">No historical events recorded yet.</p>
                                </div>
                            @endforelse
                        </div>
                    </div>

                    <!-- SETTINGS -->
                    <div x-show="tab === 'settings'" 
                         x-transition:enter="transition ease-out duration-300" 
                         x-transition:enter-start="opacity-0 transform translate-y-4" 
                         x-transition:enter-end="opacity-100 transform translate-y-0" 
                         x-cloak class="tab-pane-transition">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h4 class="h5 fw-bold mb-0 d-flex align-items-center"><i class="fa-solid fa-sliders text-primary me-2"></i> Fee & Accommodation Settings</h4>
                            <button type="button" class="btn btn-premium btn-sm rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#feeSettingsModal">
                                <i class="fa-solid fa-pen me-1"></i> Edit Plan
                            </button>
                        </div>
                        
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="d-flex align-items-center p-3 bg-light bg-opacity-50 border rounded-4 transition-hover">
                                    <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 48px; height: 48px; font-size: 1.25rem;">
                                        <i class="fa-solid fa-door-open"></i>
                                    </div>
                                    <div>
                                        <div class="text-muted small fw-bold text-uppercase mb-1">Room Preference</div>
                                        <div class="fw-bold fs-5 text-dark">{{ $student->room_preference ?? 'Not Set' }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex align-items-center p-3 bg-light bg-opacity-50 border rounded-4 transition-hover">
                                    <div class="bg-info-subtle text-info-emphasis rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 48px; height: 48px; font-size: 1.25rem;">
                                        <i class="fa-solid fa-users"></i>
                                    </div>
                                    <div>
                                        <div class="text-muted small fw-bold text-uppercase mb-1">Sharing Preference</div>
                                        <div class="fw-bold fs-5 text-dark">{{ $student->sharing_preference ?? 'Not Set' }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex align-items-center p-3 bg-light bg-opacity-50 border rounded-4 transition-hover">
                                    <div class="bg-success-subtle text-success rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 48px; height: 48px; font-size: 1.25rem;">
                                        <i class="fa-solid fa-calendar-alt"></i>
                                    </div>
                                    <div>
                                        <div class="text-muted small fw-bold text-uppercase mb-1">Fee Structure</div>
                                        <div class="fw-bold fs-5 text-dark text-capitalize">{{ $student->fee_frequency ?? 'Not Set' }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex align-items-center p-3 bg-light bg-opacity-50 border rounded-4 transition-hover">
                                    <div class="bg-warning-subtle text-warning-emphasis rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 48px; height: 48px; font-size: 1.25rem;">
                                        <i class="fa-solid fa-indian-rupee-sign"></i>
                                    </div>
                                    <div>
                                        <div class="text-muted small fw-bold text-uppercase mb-1">Fee Amount</div>
                                        <div class="fw-bold fs-5 text-dark">{{ $student->fee_amount ? hostelease_money($student->fee_amount) : 'Not Set' }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

<!-- TELEPORTED MODALS -->
<template x-teleport="body">
    {{-- QR Modal --}}
    @if($qrSvg ?? false)
    <div class="modal fade" id="qrModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content" style="border-radius: var(--he-radius-lg); overflow: hidden; border: none; box-shadow: 0 20px 40px rgba(0,0,0,0.1);">
                <div class="modal-header border-0 pb-0 justify-content-end">
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center pt-0 pb-4">
                    <h5 class="fw-bold mb-3">Student ID QR</h5>
                    <div class="border rounded-4 p-3 bg-white d-inline-block shadow-sm" style="line-height:0">{!! $qrSvg !!}</div>
                    <p class="text-muted small fw-bold mt-3 mb-0 text-uppercase">Scan for verification</p>
                </div>
            </div>
        </div>
    </div>
    @endif
</template>

<template x-teleport="body">
    {{-- Collect Modal --}}
    <div class="modal fade" id="collectModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" id="collectForm" method="POST"
                  x-data="studentProfileCollectModal({{ $paymentSummary['outstanding'] ?? 0 }}, {{ $student->credit_balance ?? 0 }})"
                  action="{{ route('admin.students.collect', $student) }}"
                  data-collect-action="{{ route('admin.students.collect', $student) }}"
                  data-promise-action="{{ route('admin.students.promise', $student) }}"
                  style="border-radius: var(--he-radius-lg); border: none; box-shadow: 0 20px 40px rgba(0,0,0,0.1);">
                @csrf
                <div class="modal-header border-0 pb-0 mt-2 ms-2">
                    <h5 class="modal-title fw-bold fs-4" id="collectTitle">Collect Payment</h5>
                    <button type="button" class="btn-close me-2" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    @if(empty($paymentModes) || $paymentModes->isEmpty())
                        <div class="alert alert-warning fw-bold mb-0 rounded-4">
                            <i class="fa-solid fa-triangle-exclamation me-1"></i> No payment modes setup. Please add one under <a href="{{ route('admin.payment-modes.index') }}">Settings > Payment Modes</a>.
                        </div>
                    @else
                        {{-- Pay now / Promise toggle --}}
                        <div class="btn-group w-100 mb-4 shadow-sm rounded-pill overflow-hidden border" role="group">
                            <input type="radio" class="btn-check" name="collect_mode" id="modePay" value="pay" checked onchange="setCollectMode('pay')">
                            <label class="btn btn-outline-primary border-0 fw-bold py-2" for="modePay"><i class="fa-solid fa-indian-rupee-sign me-1"></i> Pay Now</label>
                            
                            <input type="radio" class="btn-check" name="collect_mode" id="modePromise" value="promise" onchange="setCollectMode('promise')">
                            <label class="btn btn-outline-primary border-0 fw-bold py-2" for="modePromise"><i class="fa-regular fa-calendar-check me-1"></i> Promise</label>
                        </div>

                        {{-- Pay fields --}}
                        <div id="payFields">
                            <div class="bg-light rounded-4 p-3 mb-4 d-flex justify-content-between align-items-center border border-primary-subtle border-opacity-25">
                                <div>
                                    <div class="text-muted small fw-bold text-uppercase letter-spacing-1 mb-1">Outstanding Balance</div>
                                    <div class="fs-4 fw-bold text-dark">₹{{ number_format($paymentSummary['outstanding'] ?? 0, 2) }}</div>
                                </div>
                                @if(($student->credit_balance ?? 0) > 0)
                                <div class="text-end">
                                    <div class="text-muted small fw-bold text-uppercase letter-spacing-1 mb-1">Available Credit</div>
                                    <div class="fs-5 fw-bold text-success">₹{{ number_format($student->credit_balance ?? 0, 2) }}</div>
                                </div>
                                @endif
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold text-dark mb-2">Total Amount to Pay (₹)</label>
                                <div class="input-group input-group-lg border rounded-3 overflow-hidden bg-white" :class="{'border-primary shadow-sm': isFocused}">
                                    <span class="input-group-text bg-white border-0 text-muted px-3"><i class="fa-solid fa-indian-rupee-sign"></i></span>
                                    <input type="number" step="0.01" min="0.01" class="form-control border-0 ps-1 fw-bold fs-5 bg-white" 
                                           x-model.number="totalPayment" id="collectAmount"
                                           @focus="isFocused = true" @blur="isFocused = false" required>
                                </div>
                                <div class="form-text text-muted small mt-2"><i class="fa-solid fa-circle-info me-1"></i> Enter the total amount you want to settle.</div>
                            </div>

                            <!-- Payment Breakdown Section -->
                            <div class="bg-primary-subtle bg-opacity-10 border border-primary-subtle rounded-4 p-3 mb-4 position-relative">
                                
                                <h6 class="fw-bold mb-3 text-primary d-flex align-items-center">
                                    <div class="bg-primary bg-opacity-10 rounded-circle p-2 me-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                        <i class="fa-solid fa-chart-pie"></i>
                                    </div>
                                    Payment Breakdown
                                </h6>
                                
                                <div class="row g-3 position-relative z-1">
                                    @if(($student->credit_balance ?? 0) > 0)
                                    <div class="col-12">
                                        <label class="form-label fw-semibold small text-muted">Pay from Credit Balance (₹)</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-white text-success border-success-subtle"><i class="fa-solid fa-wallet"></i></span>
                                            <input type="number" step="0.01" min="0" :max="maxCreditAllowed" 
                                                   class="form-control fw-bold text-success border-success-subtle" 
                                                   x-model.number="creditUsed" 
                                                   @input="validateCredit">
                                            <button type="button" class="btn btn-outline-success text-uppercase fw-bold" style="font-size: 0.75rem;" @click="useMaxCredit">Max</button>
                                        </div>
                                        <input type="hidden" name="credit_used" :value="creditUsed">
                                    </div>
                                    @endif

                                    <div class="col-12">
                                        <label class="form-label fw-semibold small text-muted">Pay via Cash/Online (₹)</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-white text-dark"><i class="fa-solid fa-money-bill-wave"></i></span>
                                            <input type="text" class="form-control fw-bold bg-white" :value="cashAmount.toFixed(2)" readonly>
                                        </div>
                                        <input type="hidden" name="amount" :value="cashAmount">
                                    </div>
                                </div>
                            </div>

                            <div x-show="cashAmount > 0" x-transition.duration.300ms class="row g-3 mb-4">
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-bold small">Payment Mode</label>
                                    <select name="mode" class="form-select bg-light" x-model="selectedMode" @change="checkReference">
                                        @foreach($paymentModes as $m)
                                            <option value="{{ $m->code }}" data-req="{{ $m->requires_reference ? 1 : 0 }}">{{ $m->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-bold small">Payment Date</label>
                                    <input type="date" name="paid_on" class="form-control bg-light" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required>
                                </div>
                                <div class="col-12" x-show="requiresReference" x-transition>
                                    <label class="form-label fw-bold small">Reference No. <span class="text-danger">*</span></label>
                                    <input type="text" name="reference_number" x-ref="refInput" class="form-control bg-light" placeholder="e.g. UPI Txn ID, Check No.">
                                </div>
                            </div>
                            
                            <!-- Fallback mode when 100% credit -->
                            <template x-if="cashAmount <= 0">
                                <input type="hidden" name="mode" value="{{ $paymentModes->first()?->code ?? 'cash' }}">
                            </template>
                            <template x-if="cashAmount <= 0">
                                <input type="hidden" name="paid_on" value="{{ now()->toDateString() }}">
                            </template>

                            <div class="col-12">
                                <label class="form-label fw-bold small">Remarks (Optional)</label>
                                <input type="text" name="remarks" class="form-control bg-light" placeholder="Optional note">
                            </div>
                        </div>

                        {{-- Promise fields --}}
                        <div id="promiseFields" class="d-none">
                            <div class="alert alert-info py-3 small mb-4 rounded-4 border-info-subtle fw-bold">
                                <i class="fa-solid fa-circle-info me-2 fs-5 float-start"></i> 
                                No money is collected now.<br>This records the promised date to clear the outstanding balance.
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Promise Date</label>
                                <input type="date" name="promise_date" id="promiseDate" class="form-control form-control-lg bg-light"
                                       min="{{ now()->toDateString() }}" value="{{ now()->addDays(7)->toDateString() }}" required disabled>
                            </div>
                            <div class="mb-1">
                                <label class="form-label fw-bold small">Note (optional)</label>
                                <input type="text" name="promise_note" id="promiseNote" class="form-control bg-light" maxlength="255" placeholder="e.g. Will pay after salary on 5th" disabled>
                            </div>
                        </div>
                    @endif
                </div>
                <div class="modal-footer border-0 pt-0 mb-2 me-2">
                    <button type="button" class="btn btn-light rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Cancel</button>
                    @if(!empty($paymentModes) && $paymentModes->isNotEmpty())
                        <button type="submit" class="btn btn-premium rounded-pill px-4 fw-bold shadow-sm" id="collectSubmit">Collect Payment</button>
                    @endif
                </div>
            </form>
        </div>
    </div>
</template>

<template x-teleport="body">
    {{-- Document Modal --}}
    <div class="modal fade" id="docModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="POST" action="{{ route('admin.students.documents.store', $student) }}" enctype="multipart/form-data"
                  style="border-radius: var(--he-radius-lg); border: none; box-shadow: 0 20px 40px rgba(0,0,0,0.1);">
                @csrf
                <div class="modal-header border-0 pb-0 mt-2 ms-2">
                    <h5 class="modal-title fw-bold fs-4">Upload Document</h5>
                    <button type="button" class="btn-close me-2" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Document Type</label>
                        <select name="type" class="form-select bg-light" required>
                            <option value="aadhaar">Aadhaar</option>
                            <option value="photo">Photo / ID</option>
                            <option value="agreement">Rental Agreement</option>
                            <option value="other">Other Document</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Title (optional)</label>
                        <input type="text" name="title" class="form-control bg-light" placeholder="e.g. Front side of Aadhaar">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">File <span class="text-muted fw-normal">(jpg, png, pdf · max 5MB)</span></label>
                        <input type="file" name="file" class="form-control bg-light" accept=".jpg,.jpeg,.png,.webp,.pdf" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold small">Expiry Date (optional)</label>
                        <input type="date" name="expiry_date" class="form-control bg-light">
                    </div>
                    <div class="form-check bg-light rounded-4 p-3 d-flex align-items-center">
                        <input class="form-check-input m-0 flex-shrink-0" type="checkbox" name="is_signed" value="1" id="isSigned" style="width: 1.25rem; height: 1.25rem;">
                        <label class="form-check-label ms-3 fw-bold" for="isSigned">This document is physically signed</label>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 mb-2 me-2">
                    <button type="button" class="btn btn-light rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-premium rounded-pill px-4 fw-bold shadow-sm">Upload File</button>
                </div>
            </form>
        </div>
    </div>
</template>

<template x-teleport="body">
    {{-- Fee Settings Modal --}}
    <div class="modal fade" id="feeSettingsModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <form class="modal-content" method="POST" action="{{ route('admin.students.fee-settings.update', $student) }}" 
                  style="border-radius: var(--he-radius-lg); border: none; box-shadow: 0 20px 40px rgba(0,0,0,0.1);"
                  x-data="prorationPreview()">
                @csrf
                @method('PUT')
                <div class="modal-header border-0 pb-0 mt-2 ms-2">
                    <h5 class="modal-title fw-bold fs-4">Change Fee & Room Plan</h5>
                    <button type="button" class="btn-close me-2" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-4 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Room Preference</label>
                            <select name="room_preference" class="form-select bg-light">
                                <option value="">Select preference</option>
                                <option value="AC" @selected($student->room_preference === 'AC')>AC Room</option>
                                <option value="Non-AC" @selected($student->room_preference === 'Non-AC')>Non-AC Room</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Sharing Preference</label>
                            <select name="sharing_preference" class="form-select bg-light">
                                <option value="">Select sharing</option>
                                <option value="Single" @selected($student->sharing_preference === 'Single')>Single Occupancy</option>
                                <option value="Double" @selected($student->sharing_preference === 'Double')>Double Sharing</option>
                                <option value="Triple" @selected($student->sharing_preference === 'Triple')>Triple Sharing</option>
                                <option value="Quad" @selected($student->sharing_preference === 'Quad')>Quad Sharing</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Fee Structure</label>
                            <select name="fee_frequency" class="form-select bg-light" x-model="frequency" @change="fetchPreview" required>
                                <option value="">Select structure</option>
                                <option value="monthly">Monthly</option>
                                <option value="semester">Semester-wise</option>
                                <option value="yearly">Yearly</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">New Fee Amount (₹)</label>
                            <input type="number" name="fee_amount" class="form-control bg-light" x-model="amount" @change="fetchPreview" min="0" step="0.01" required>
                        </div>
                    </div>

                    {{-- Proration Preview Area --}}
                    <div x-show="preview" x-transition class="bg-primary-subtle bg-opacity-25 p-4 rounded-4 border border-primary-subtle" style="display:none;">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-white rounded-circle d-flex align-items-center justify-content-center text-primary shadow-sm me-3" style="width: 32px; height: 32px;"><i class="fa-solid fa-calculator"></i></div>
                            <span class="fw-bold fs-5 text-primary">Proration Calculation</span>
                            <div x-show="loading" class="spinner-border spinner-border-sm text-primary ms-3" role="status"></div>
                        </div>
                        <template x-if="preview && preview.has_active_cycle">
                            <div class="fw-bold">
                                <div class="d-flex justify-content-between text-muted mb-2">
                                    <span>Unused days from current plan (<span x-text="preview.days_unused"></span>/<span x-text="preview.days_total"></span>)</span>
                                    <span class="text-success">+₹<span x-text="preview.refund_credit"></span></span>
                                </div>
                                <div class="d-flex justify-content-between text-muted mb-2">
                                    <span>Current credit balance</span>
                                    <span class="text-success">+₹<span x-text="preview.current_credit_balance"></span></span>
                                </div>
                                <div class="d-flex justify-content-between text-muted mb-3 pb-3 border-bottom border-secondary-subtle">
                                    <span>Cost of new <span x-text="preview.new_frequency"></span> plan (starts today)</span>
                                    <span class="text-danger">-₹<span x-text="preview.new_invoice_amount"></span></span>
                                </div>
                                <div class="d-flex justify-content-between fs-5">
                                    <span class="text-dark">Action on Save:</span>
                                    <span>
                                        <template x-if="preview.net_due > 0">
                                            <span class="text-danger">Invoice generated for ₹<span x-text="preview.net_due"></span></span>
                                        </template>
                                        <template x-if="preview.net_due == 0">
                                            <span class="text-success">Paid by credit (Bal: ₹<span x-text="preview.projected_credit_balance"></span>)</span>
                                        </template>
                                    </span>
                                </div>
                            </div>
                        </template>
                        <template x-if="preview && !preview.has_active_cycle">
                            <div class="fw-bold text-muted">
                                <i class="fa-solid fa-circle-info me-1"></i> No active cycle to prorate. A new invoice for ₹<span x-text="preview.new_invoice_amount"></span> will be generated.
                            </div>
                        </template>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 mb-2 me-2">
                    <button type="button" class="btn btn-light rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-premium rounded-pill px-4 fw-bold shadow-sm" :disabled="loading"><i class="fa-solid fa-save me-2"></i>Confirm & Save</button>
                </div>
            </form>
        </div>
    </div>
</template>

</div> <!-- end page-enter -->

@push('scripts')
<script>
function prorationPreview() {
    return {
        frequency: '{{ $student->fee_frequency }}',
        amount: '{{ $student->fee_amount }}',
        preview: null,
        loading: false,
        
        init() {
            this.fetchPreview();
        },
        
        async fetchPreview() {
            if (!this.frequency || !this.amount) return;
            this.loading = true;
            try {
                const url = '{{ route('admin.students.prorate-preview', $student) }}' + `?fee_frequency=${this.frequency}&fee_amount=${this.amount}`;
                const res = await fetch(url);
                this.preview = await res.json();
            } catch (e) {
                console.error(e);
            } finally {
                this.loading = false;
            }
        }
    }
}

function openCollect(amount) {
    document.getElementById('collectTitle').textContent = 'Collect Payment';
    // The Alpine state handles the amount initialization automatically
    const payRadio = document.getElementById('modePay');
    if (payRadio) { payRadio.checked = true; setCollectMode('pay'); }
}

function setCollectMode(mode) {
    const form = document.getElementById('collectForm');
    if (!form) return;
    const promising = mode === 'promise';
    const pay = document.getElementById('payFields');
    const promise = document.getElementById('promiseFields');
    const submit = document.getElementById('collectSubmit');

    pay.classList.toggle('d-none', promising);
    promise.classList.toggle('d-none', !promising);
    form.action = promising ? form.dataset.promiseAction : form.dataset.collectAction;

    pay.querySelectorAll('input,select').forEach(el => el.disabled = promising);
    promise.querySelectorAll('input').forEach(el => el.disabled = !promising);

    submit.innerHTML = promising ? '<i class="fa-solid fa-calendar-check me-2"></i>Save Promise' : '<i class="fa-solid fa-indian-rupee-sign me-2"></i>Collect Payment';
    submit.classList.toggle('btn-premium', !promising);
    submit.classList.toggle('btn-warning', promising);
    
    // For alpine component, if promising we should not enforce totalPayment > 0
    // so we temporarily ignore it by just toggling disabled on the submit button directly
    if (promising) {
        submit.removeAttribute('x-bind:disabled');
        submit.disabled = false;
    } else {
        submit.setAttribute('x-bind:disabled', 'totalPayment <= 0');
    }
}

document.addEventListener('alpine:init', () => {
    Alpine.data('studentProfileCollectModal', (initialBalance, creditBal) => ({
        isFocused: false,
        creditBalance: Number(creditBal) || 0,
        outstandingBalance: Number(initialBalance) || 0,
        
        totalPayment: Number(initialBalance) > 0 ? Number(initialBalance) : 0,
        creditUsed: 0,
        
        selectedMode: '{{ $paymentModes->first()?->code ?? 'cash' }}',
        requiresReference: false,

        init() {
            this.checkReference();
            if (this.creditBalance > 0 && this.totalPayment > 0) {
                this.useMaxCredit();
            }
            this.$watch('totalPayment', value => {
                let val = Number(value) || 0;
                if (this.creditUsed > val) {
                    this.creditUsed = val;
                }
            });
        },

        get maxCreditAllowed() {
            return Math.min(this.creditBalance, Number(this.totalPayment) || 0);
        },

        get cashAmount() {
            let cash = (Number(this.totalPayment) || 0) - (Number(this.creditUsed) || 0);
            return Math.max(0, cash);
        },

        useMaxCredit() {
            this.creditUsed = this.maxCreditAllowed;
        },
        
        validateCredit() {
            if (this.creditUsed > this.maxCreditAllowed) {
                this.creditUsed = this.maxCreditAllowed;
            }
            if (this.creditUsed < 0 || isNaN(this.creditUsed)) {
                this.creditUsed = 0;
            }
        },

        checkReference() {
            this.$nextTick(() => {
                const select = this.$root.querySelector('select[name="mode"]');
                if(select && select.options.length > 0) {
                    const opt = select.options[select.selectedIndex];
                    this.requiresReference = opt ? opt.dataset.req === '1' : false;
                    
                    const refInput = this.$refs.refInput;
                    if(refInput) {
                        if (this.requiresReference && this.cashAmount > 0) {
                            refInput.setAttribute('required', 'required');
                        } else {
                            refInput.removeAttribute('required');
                        }
                    }
                }
            });
        }
    }));
});

</script>
@endpush
@endsection
