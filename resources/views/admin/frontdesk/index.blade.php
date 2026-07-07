@extends('layouts.app')
@section('title', __('Front Desk'))

@section('content')
<style>
    /* Premium Stats Cards */
    .stat-card-glass {
        border-radius: 1.25rem;
        padding: 1.5rem;
        color: white;
        position: relative;
        overflow: hidden;
        box-shadow: 0 10px 25px rgba(0,0,0,0.05);
        display: flex;
        flex-direction: column;
        justify-content: center;
        border: none;
    }
    .stat-card-glass::after {
        content: '';
        position: absolute;
        top: -30px; right: -30px;
        width: 100px; height: 100px;
        border-radius: 50%;
        background: rgba(255,255,255,0.1);
        pointer-events: none;
    }
    .stat-card-visitors { background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%); }
    .stat-card-complaints-open { background: linear-gradient(135deg, #ef4444 0%, #f43f5e 100%); }
    .stat-card-complaints-prog { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
    .stat-card-complaints-res { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
    
    /* Searchable Select (Alpine) */
    .search-select-wrapper {
        position: relative;
    }
    .search-select-button {
        display: flex;
        justify-content: space-between;
        align-items: center;
        width: 100%;
        padding: 0.75rem 1rem;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 0.75rem;
        cursor: pointer;
        color: #1e293b;
        font-weight: 500;
    }
    .search-select-button:focus, .search-select-wrapper.is-open .search-select-button {
        background: #fff;
        border-color: var(--he-primary);
        box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        outline: none;
    }
    .search-select-dropdown {
        position: absolute;
        top: 100%; left: 0; right: 0;
        margin-top: 0.5rem;
        background: #fff;
        border-radius: 0.75rem;
        border: 1px solid #e2e8f0;
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        z-index: 1050;
        max-height: 250px;
        display: flex;
        flex-direction: column;
    }
    .search-select-input {
        padding: 0.75rem;
        border: none;
        border-bottom: 1px solid #e2e8f0;
        background: #f8fafc;
        border-radius: 0.75rem 0.75rem 0 0;
        outline: none;
        width: 100%;
    }
    .search-select-options {
        overflow-y: auto;
        padding: 0.5rem 0;
    }
    .search-select-option {
        padding: 0.5rem 1rem;
        cursor: pointer;
        transition: background 0.1s;
    }
    .search-select-option:hover { background: #f1f5f9; }
    
    /* Custom Overlay Modal */
    .custom-overlay-backdrop {
        position: fixed; inset: 0;
        background: rgba(15, 23, 42, 0.6);
        backdrop-filter: blur(8px);
        z-index: 1040;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }
    .custom-overlay-modal {
        width: 100%; max-width: 550px;
        background: #fff;
        border-radius: 1.25rem;
        box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
        display: flex;
        flex-direction: column;
        max-height: 85vh;
        transform: scale(0.95);
        opacity: 0;
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        overflow: hidden;
    }
    .custom-overlay-modal.is-open { transform: scale(1); opacity: 1; }
    .custom-overlay-header {
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid rgba(0,0,0,0.05);
        display: flex; justify-content: space-between; align-items: center;
        background: #fff;
    }
    .custom-overlay-body {
        padding: 1.5rem;
        overflow-y: auto;
        flex-grow: 1;
        background: #fafafa;
    }
    .custom-overlay-footer {
        padding: 1.25rem 1.5rem;
        border-top: 1px solid rgba(0,0,0,0.05);
        background: #fff;
        display: flex; gap: 1rem; justify-content: flex-end;
    }
    
    /* Priority Radios */
    .priority-selector { display: flex; gap: 0.5rem; }
    .priority-label {
        flex: 1; text-align: center; padding: 0.75rem;
        border: 2px solid #e2e8f0; border-radius: 0.75rem;
        cursor: pointer; font-weight: 600; color: #64748b;
        transition: all 0.2s;
    }
    .priority-radio:checked + .priority-label.p-low { border-color: #3b82f6; background: #eff6ff; color: #1d4ed8; }
    .priority-radio:checked + .priority-label.p-medium { border-color: #f59e0b; background: #fef3c7; color: #b45309; }
    .priority-radio:checked + .priority-label.p-high { border-color: #ef4444; background: #fef2f2; color: #b91c1c; }
    
    /* Lists & Cards */
    .fd-list-item {
        background: #fff;
        border-radius: 1.25rem;
        border: 1px solid #e2e8f0;
        padding: 1.25rem 1.5rem;
        margin-bottom: 1rem;
        box-shadow: 0 4px 6px rgba(0,0,0,0.02);
        transition: all 0.2s;
    }
    .fd-list-item:hover { box-shadow: 0 10px 15px rgba(0,0,0,0.05); transform: translateY(-2px); }
    
    .pulse-indicator {
        width: 10px; height: 10px;
        background-color: #10b981;
        border-radius: 50%;
        display: inline-block;
        box-shadow: 0 0 0 rgba(16, 185, 129, 0.4);
        animation: pulse 2s infinite;
    }
    @keyframes pulse {
        0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4); }
        70% { box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); }
        100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
    }
</style>

<div x-data="frontdesk()" class="page-enter">

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h1 class="h3 fw-bold mb-1">{{ __('Front Desk') }}</h1>
            <p class="text-secondary mb-0">{{ __('Manage visitors, walk-ins, and student complaints.') }}</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-primary rounded-pill fw-bold shadow-sm px-4" @click="openVisitorPanel()" x-show="tab === 'visitors'">
                <i class="fa-solid fa-plus me-1"></i> {{ __('Add Visitor') }}
            </button>
            <button class="btn btn-primary rounded-pill fw-bold shadow-sm px-4" @click="openComplaintPanel()" x-show="tab === 'complaints'" x-cloak>
                <i class="fa-solid fa-plus me-1"></i> {{ __('Log Complaint') }}
            </button>
        </div>
    </div>

    <!-- Tabs -->
    <div class="he-tabs mb-4">
        <button class="he-tab" :class="{ 'active': tab === 'visitors' }" @click="tab = 'visitors'">
            <i class="fa-solid fa-door-open me-1"></i> {{ __('Visitors') }} 
            @if($insideCount > 0)
                <span class="badge bg-danger rounded-pill ms-1">{{ $insideCount }}</span>
            @endif
        </button>
        <button class="he-tab" :class="{ 'active': tab === 'complaints' }" @click="tab = 'complaints'">
            <i class="fa-solid fa-headset me-1"></i> {{ __('Complaints') }}
            @if($complaintCounts['open'] > 0)
                <span class="badge bg-danger rounded-pill ms-1">{{ $complaintCounts['open'] }}</span>
            @endif
        </button>
    </div>

    <div class="mb-4">
        <div class="input-group input-group-lg">
            <span class="input-group-text bg-white border-end-0 text-muted px-4 rounded-start-pill"><i class="fa-solid fa-search"></i></span>
            <input type="text" x-model="search" class="form-control border-start-0 rounded-end-pill fs-6 bg-white" placeholder="Search records...">
        </div>
    </div>

    <!-- ================= VISITORS TAB ================= -->
    <div x-show="tab === 'visitors'" x-cloak>
        <div class="row mb-4 align-items-stretch">
            <div class="col-12 col-md-4 mb-3 mb-md-0">
                <div class="stat-card-glass stat-card-visitors h-100">
                    <div class="opacity-75 text-uppercase fw-bold small tracking-wider mb-1">Currently Inside</div>
                    <div class="display-5 fw-bold">{{ $insideCount }}</div>
                </div>
            </div>
            <div class="col-12 col-md-8 d-flex justify-content-md-end align-items-end">
                <form method="GET" action="{{ route('admin.frontdesk.index') }}" class="d-flex gap-2 bg-white p-2 rounded-pill shadow-sm border border-light">
                    <input type="hidden" name="tab" value="visitors">
                    <select name="filter" class="form-select border-0 bg-light rounded-pill fw-medium" onchange="this.form.submit()" style="min-width: 150px;">
                        <option value="">All Visitors</option>
                        <option value="inside" {{ request('filter') == 'inside' ? 'selected' : '' }}>Currently Inside</option>
                    </select>
                    <input type="date" name="date" class="form-control border-0 bg-light rounded-pill fw-medium" value="{{ request('date') }}" onchange="this.form.submit()">
                </form>
            </div>
        </div>

        <div class="row">
            @forelse($visitors as $v)
                <div class="col-12" x-show="search === '' || '{{ strtolower($v->name . ' ' . optional($v->student)->name) }}'.includes(search.toLowerCase())">
                    <div class="fd-list-item d-flex flex-wrap align-items-center {{ !$v->isInside() ? 'opacity-75' : '' }}">
                        <div class="col-12 col-md-3 mb-2 mb-md-0">
                            <div class="d-flex align-items-center gap-2">
                                @if($v->isInside()) <span class="pulse-indicator"></span> @endif
                                <span class="fw-bold fs-5 text-dark">{{ $v->name }}</span>
                            </div>
                            <div class="small text-muted fw-semibold mt-1"><i class="fa-solid fa-phone me-1"></i> {{ $v->mobile ?? 'No Mobile' }}</div>
                        </div>
                        
                        <div class="col-6 col-md-3">
                            <div class="small text-muted text-uppercase fw-bold mb-1" style="font-size: 0.7rem;">Visiting</div>
                            @if($v->student)
                                <a href="{{ route('admin.students.show', $v->student) }}" class="fw-bold text-decoration-none text-primary">{{ $v->student->name }}</a>
                            @else
                                <span class="fw-bold text-secondary">General Visit</span>
                            @endif
                            <div class="small text-muted mt-1">{{ $v->purpose ?? 'No specific purpose' }}</div>
                        </div>

                        <div class="col-6 col-md-3">
                            <div class="small text-muted text-uppercase fw-bold mb-1" style="font-size: 0.7rem;">Timing</div>
                            <div class="fw-semibold text-dark"><i class="fa-solid fa-arrow-right-to-bracket text-success me-1"></i> {{ $v->check_in->format('h:i A, d M') }}</div>
                            @if($v->check_out)
                                <div class="fw-semibold text-secondary mt-1"><i class="fa-solid fa-arrow-right-from-bracket text-danger me-1"></i> {{ $v->check_out->format('h:i A, d M') }}</div>
                            @endif
                        </div>

                        <div class="col-12 col-md-3 text-md-end mt-3 mt-md-0 d-flex justify-content-md-end gap-2">
                            @if($v->isInside())
                                <form action="{{ route('admin.visitors.checkout', $v) }}" method="POST" class="d-inline m-0">
                                    @csrf @method('PATCH')
                                    <button class="btn btn-warning rounded-pill shadow-sm fw-bold px-3 text-dark"><i class="fa-solid fa-person-walking-arrow-right me-1"></i> Checkout</button>
                                </form>
                            @else
                                <span class="badge bg-light text-secondary border rounded-pill px-3 py-2 align-self-center"><i class="fa-solid fa-check me-1"></i> Left</span>
                            @endif
                            
                            <form action="{{ route('admin.visitors.destroy', $v) }}" method="POST" class="d-inline m-0" onsubmit="return confirm('Delete visitor record?');">
                                @csrf @method('DELETE')
                                <button class="btn btn-light rounded-pill border shadow-sm text-danger px-3"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12 text-center py-5">
                    <i class="fa-solid fa-door-closed text-muted opacity-25 d-block mb-3" style="font-size: 4rem;"></i>
                    <h5 class="fw-bold text-dark">No Visitors</h5>
                    <p class="text-muted">No visitor records found.</p>
                </div>
            @endforelse
        </div>
    </div>

    <!-- ================= COMPLAINTS TAB ================= -->
    <div x-show="tab === 'complaints'" x-cloak>
        <div class="row g-3 mb-4">
            <div class="col-12 col-md-4">
                <div class="stat-card-glass stat-card-complaints-open h-100">
                    <div class="opacity-75 text-uppercase fw-bold small tracking-wider mb-1">Open Issues</div>
                    <div class="display-5 fw-bold">{{ $complaintCounts['open'] }}</div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="stat-card-glass stat-card-complaints-prog h-100">
                    <div class="opacity-75 text-uppercase fw-bold small tracking-wider mb-1">In Progress</div>
                    <div class="display-5 fw-bold">{{ $complaintCounts['in_progress'] }}</div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="stat-card-glass stat-card-complaints-res h-100">
                    <div class="opacity-75 text-uppercase fw-bold small tracking-wider mb-1">Resolved</div>
                    <div class="display-5 fw-bold">{{ $complaintCounts['resolved'] }}</div>
                </div>
            </div>
        </div>

        <div class="row">
            @forelse($complaints as $c)
                <div class="col-12 col-lg-6" x-show="search === '' || '{{ strtolower($c->title . ' ' . optional($c->student)->name) }}'.includes(search.toLowerCase())">
                    <div class="fd-list-item d-flex flex-column h-100" style="margin-bottom: 1.5rem;">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h5 class="fw-bold text-dark mb-1">{{ $c->title }}</h5>
                                <div class="small text-muted fw-semibold">
                                    {{ config('hostelease.complaint_categories.'.$c->category, $c->category) }} • Logged {{ $c->created_at->format('M d') }}
                                </div>
                            </div>
                            @if($c->priority === 'high') 
                                <span class="badge bg-danger rounded-pill px-3 py-2 shadow-sm"><i class="fa-solid fa-triangle-exclamation me-1"></i> High Priority</span>
                            @elseif($c->priority === 'medium')
                                <span class="badge bg-warning text-dark rounded-pill px-3 py-2 shadow-sm">Medium Priority</span>
                            @else
                                <span class="badge bg-info text-dark rounded-pill px-3 py-2 shadow-sm">Low Priority</span>
                            @endif
                        </div>
                        
                        @if($c->description)
                            <p class="text-muted small mb-3">{{ Str::limit($c->description, 100) }}</p>
                        @endif

                        <div class="mt-auto pt-3 border-top d-flex flex-wrap justify-content-between align-items-center gap-3">
                            <div class="d-flex align-items-center gap-2">
                                <div class="bg-light rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                    <i class="fa-solid fa-user text-secondary small"></i>
                                </div>
                                @if($c->student)
                                    <a href="{{ route('admin.students.show', $c->student) }}" class="small fw-bold text-decoration-none text-dark">{{ $c->student->name }}</a>
                                @else
                                    <span class="small fw-bold text-secondary">Staff / Internal</span>
                                @endif
                            </div>

                            <!-- Premium Inline Status Dropdown -->
                            <div x-data="{ open: false, currentStatus: '{{ $c->status }}' }" class="position-relative">
                                <button type="button" @click="open = !open" @click.away="open = false" 
                                    class="btn btn-sm rounded-pill shadow-sm fw-bold px-3 d-flex align-items-center justify-content-between border" style="min-width: 140px;"
                                    :class="{
                                        'bg-danger-subtle text-danger border-danger-subtle': currentStatus === 'open',
                                        'bg-warning-subtle text-warning-emphasis border-warning-subtle': currentStatus === 'in_progress',
                                        'bg-success-subtle text-success border-success-subtle': currentStatus === 'resolved' || currentStatus === 'closed'
                                    }">
                                    <span x-text="currentStatus === 'open' ? 'Open' : (currentStatus === 'in_progress' ? 'In Progress' : (currentStatus === 'resolved' ? 'Resolved' : 'Closed'))" class="text-nowrap"></span>
                                    <i class="fa-solid fa-chevron-down small opacity-50 ms-2"></i>
                                </button>
                                
                                <div x-show="open" x-transition.opacity style="display:none;" 
                                     class="position-absolute mt-2 end-0 bg-white border rounded-4 shadow-lg p-2 z-3" style="min-width: 160px;">
                                    <form action="{{ route('admin.complaints.update', $c) }}" method="POST" class="m-0">
                                        @csrf @method('PATCH')
                                        @foreach(config('hostelease.complaint_statuses') as $k=>$l)
                                            <button type="submit" name="status" value="{{ $k }}" 
                                                class="btn btn-sm w-100 text-start rounded-3 mb-1 text-nowrap {{ $c->status === $k ? 'bg-light fw-bold text-dark' : 'text-muted' }}"
                                                onmouseover="this.classList.add('bg-light')" onmouseout="if('{{$c->status}}'!=='{{$k}}') this.classList.remove('bg-light')">
                                                @if($c->status === $k) <i class="fa-solid fa-check text-primary me-2"></i> @else <span style="display:inline-block;width:20px;"></span> @endif
                                                {{ $l }}
                                            </button>
                                        @endforeach
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12 text-center py-5">
                    <i class="fa-solid fa-headset text-muted opacity-25 d-block mb-3" style="font-size: 4rem;"></i>
                    <h5 class="fw-bold text-dark">No Complaints</h5>
                    <p class="text-muted">You're all caught up!</p>
                </div>
            @endforelse
        </div>
    </div>


    <!-- ================= MODALS ================= -->
    
    <template x-teleport="body">
        <!-- Shared Backdrop -->
        <div class="custom-overlay-backdrop" x-show="visitorPanelOpen || complaintPanelOpen" x-transition.opacity @click="closePanels()" x-cloak style="display: none;">
            
            <!-- Add Visitor Modal -->
            <form method="POST" action="{{ route('admin.visitors.store') }}" class="custom-overlay-modal" :class="{ 'is-open': visitorPanelOpen }" x-show="visitorPanelOpen" x-transition.opacity @click.stop style="display: none;">
                @csrf
                <div class="custom-overlay-header">
                    <h5 class="fw-bold mb-0"><i class="fa-solid fa-door-open text-primary me-2"></i> Add Visitor</h5>
                    <button type="button" class="btn-close" @click="closePanels()"></button>
                </div>
                
                <div class="custom-overlay-body">
                    <h6 class="fw-bold text-muted text-uppercase mb-3" style="font-size: 0.75rem; letter-spacing: 1px;">Visitor Details</h6>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control bg-light" required placeholder="John Doe">
                    </div>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold small">Mobile</label>
                            <input type="tel" name="mobile" class="form-control bg-light" maxlength="10" placeholder="10-digit number">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small">ID Proof</label>
                            <input type="text" name="id_proof" class="form-control bg-light" placeholder="Aadhaar / DL no.">
                        </div>
                    </div>

                    <hr class="border-secondary opacity-10 my-4">
                    
                    <h6 class="fw-bold text-muted text-uppercase mb-3" style="font-size: 0.75rem; letter-spacing: 1px;">Visit Details</h6>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Visiting Student</label>
                        
                        <!-- Searchable Select Alpine Component -->
                        <div x-data="searchableSelect({
                            options: [
                                {value: '', label: '— General Visit (No Student) —'},
                                @foreach($students as $s)
                                {value: '{{ $s->id }}', label: '{{ addslashes($s->name) }}'},
                                @endforeach
                            ]
                        })" class="search-select-wrapper" :class="{ 'is-open': open }" @click.away="open = false">
                            <input type="hidden" name="student_id" :value="value">
                            
                            <button type="button" class="search-select-button" @click="open = !open">
                                <span x-text="selectedLabel"></span>
                                <i class="fa-solid fa-chevron-down small text-muted"></i>
                            </button>
                            
                            <div class="search-select-dropdown" x-show="open" x-transition.opacity style="display:none;">
                                <input type="text" x-model="search" class="search-select-input" placeholder="Search student name..." x-ref="searchInput">
                                <div class="search-select-options">
                                    <template x-for="opt in filteredOptions" :key="opt.value">
                                        <div class="search-select-option" @click="selectOption(opt.value)">
                                            <span x-text="opt.label" class="fw-medium text-dark"></span>
                                        </div>
                                    </template>
                                    <div x-show="filteredOptions.length === 0" class="p-3 text-muted small text-center">No students found.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Purpose of Visit</label>
                        <input type="text" name="purpose" class="form-control bg-light" placeholder="Meeting, Delivery, Parents...">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Check-in Time</label>
                        <input type="datetime-local" name="check_in" class="form-control bg-light" value="{{ now()->format('Y-m-d\TH:i') }}">
                    </div>
                </div>
                
                <div class="custom-overlay-footer">
                    <button type="button" class="btn btn-light rounded-pill px-4 fw-bold" @click="closePanels()">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">Check In</button>
                </div>
            </form>

            <!-- Log Complaint Modal -->
            <form method="POST" action="{{ route('admin.complaints.store') }}" class="custom-overlay-modal" :class="{ 'is-open': complaintPanelOpen }" x-show="complaintPanelOpen" x-transition.opacity @click.stop style="display: none;">
                @csrf
                <div class="custom-overlay-header">
                    <h5 class="fw-bold mb-0"><i class="fa-solid fa-headset text-primary me-2"></i> Log Complaint</h5>
                    <button type="button" class="btn-close" @click="closePanels()"></button>
                </div>
                
                <div class="custom-overlay-body">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Title / Issue Summary <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control bg-light" required placeholder="e.g. Broken AC in Room 102">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Priority Level</label>
                        <div class="priority-selector">
                            <input type="radio" name="priority" id="p-low" value="low" class="d-none priority-radio">
                            <label for="p-low" class="priority-label p-low py-2">Low</label>
                            
                            <input type="radio" name="priority" id="p-medium" value="medium" class="d-none priority-radio" checked>
                            <label for="p-medium" class="priority-label p-medium py-2">Medium</label>
                            
                            <input type="radio" name="priority" id="p-high" value="high" class="d-none priority-radio">
                            <label for="p-high" class="priority-label p-high py-2">High</label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Reported By</label>
                        
                        <!-- Searchable Select Alpine Component -->
                        <div x-data="searchableSelect({
                            options: [
                                {value: '', label: '— Internal / Staff (Not Student) —'},
                                @foreach($students as $s)
                                {value: '{{ $s->id }}', label: '{{ addslashes($s->name) }}'},
                                @endforeach
                            ]
                        })" class="search-select-wrapper" :class="{ 'is-open': open }" @click.away="open = false">
                            <input type="hidden" name="student_id" :value="value">
                            
                            <button type="button" class="search-select-button" @click="open = !open">
                                <span x-text="selectedLabel"></span>
                                <i class="fa-solid fa-chevron-down small text-muted"></i>
                            </button>
                            
                            <div class="search-select-dropdown" x-show="open" x-transition.opacity style="display:none;">
                                <input type="text" x-model="search" class="search-select-input" placeholder="Search student name..." x-ref="searchInput">
                                <div class="search-select-options">
                                    <template x-for="opt in filteredOptions" :key="opt.value">
                                        <div class="search-select-option" @click="selectOption(opt.value)">
                                            <span x-text="opt.label" class="fw-medium text-dark"></span>
                                        </div>
                                    </template>
                                    <div x-show="filteredOptions.length === 0" class="p-3 text-muted small text-center">No students found.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Category</label>
                        <select name="category" class="form-select bg-light" required>
                            @foreach(config('hostelease.complaint_categories') as $k=>$l)
                                <option value="{{ $k }}">{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Detailed Description</label>
                        <textarea name="description" class="form-control bg-light" rows="3" placeholder="Provide any additional details about the issue..."></textarea>
                    </div>
                </div>
                
                <div class="custom-overlay-footer">
                    <button type="button" class="btn btn-light rounded-pill px-4 fw-bold" @click="closePanels()">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">Submit</button>
                </div>
            </form>
        </div>
    </template>

</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    
    // Main Frontdesk State
    Alpine.data('frontdesk', () => ({
        tab: '{{ request('tab', 'visitors') }}',
        search: '',
        visitorPanelOpen: false,
        complaintPanelOpen: false,
        
        openVisitorPanel() {
            this.visitorPanelOpen = true;
            document.body.style.overflow = 'hidden';
        },
        openComplaintPanel() {
            this.complaintPanelOpen = true;
            document.body.style.overflow = 'hidden';
        },
        closePanels() {
            this.visitorPanelOpen = false;
            this.complaintPanelOpen = false;
            document.body.style.overflow = '';
        },
        init() {
            this.$watch('tab', (val) => {
                this.search = ''; // reset search on tab switch
                const url = new URL(window.location);
                url.searchParams.set('tab', val);
                window.history.replaceState({}, '', url);
            });
        }
    }));

    // Searchable Select Component
    Alpine.data('searchableSelect', (config) => ({
        open: false,
        search: '',
        value: '',
        options: config.options,
        
        get filteredOptions() {
            if (this.search === '') return this.options;
            return this.options.filter(opt => opt.label.toLowerCase().includes(this.search.toLowerCase()));
        },
        get selectedLabel() {
            const selected = this.options.find(opt => opt.value == this.value);
            return selected ? selected.label : '— Select —';
        },
        selectOption(val) {
            this.value = val;
            this.open = false;
            this.search = '';
        },
        init() {
            this.$watch('open', val => {
                if(val) {
                    this.$nextTick(() => { this.$refs.searchInput.focus(); });
                } else {
                    this.search = '';
                }
            });
        }
    }));
});
</script>
@endpush
@endsection
