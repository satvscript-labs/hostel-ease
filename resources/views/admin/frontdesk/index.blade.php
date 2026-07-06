@extends('layouts.app')
@section('title', __('Front Desk'))

@section('content')
<div x-data="{ tab: '{{ request('tab', 'visitors') }}', search: '' }" @tab-changed.window="tab = $event.detail" class="page-enter">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0">{{ __('Front Desk') }}</h1>
            <p class="text-secondary">{{ __('Manage visitors, walk-ins, and student complaints.') }}</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#addVisitorModal" x-show="tab === 'visitors'">
                <i class="fa-solid fa-plus me-1"></i> {{ __('Add Visitor') }}
            </button>
            <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#cmpModal" x-show="tab === 'complaints'" x-cloak>
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
        <input type="text" x-model="search" class="form-control" placeholder="Search by name...">
    </div>

    <!-- Visitors Tab -->
    <div x-show="tab === 'visitors'" x-cloak>
        <div class="card card-premium">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('Visitor') }}</th>
                                <th>{{ __('Student / Purpose') }}</th>
                                <th>{{ __('Check In') }}</th>
                                <th>{{ __('Check Out') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th class="text-end">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($visitors as $v)
                            <tr x-show="search === '' || '{{ strtolower($v->name . ' ' . optional($v->student)->name) }}'.includes(search.toLowerCase())">
                                <td>
                                    <div class="fw-semibold">{{ $v->name }}</div>
                                    <div class="text-secondary small">{{ $v->mobile ?? '—' }}</div>
                                </td>
                                <td>
                                    @if($v->student)
                                        <a href="{{ route('admin.students.show', $v->student) }}" class="fw-medium text-decoration-none">{{ $v->student->name }}</a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                    <div class="text-secondary small">{{ $v->purpose ?? 'General' }}</div>
                                </td>
                                <td>
                                    <div class="fw-medium">{{ $v->check_in->format('d M, h:i A') }}</div>
                                </td>
                                <td>
                                    <div class="text-secondary">{{ $v->check_out ? $v->check_out->format('d M, h:i A') : '—' }}</div>
                                </td>
                                <td>
                                    @if($v->isInside())
                                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">Inside</span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill">Checked Out</span>
                                    @endif
                                </td>
                                <td class="text-end text-nowrap">
                                    @if($v->isInside())
                                        <form action="{{ route('admin.visitors.checkout', $v) }}" method="POST" class="d-inline">
                                            @csrf @method('PATCH')
                                            <button class="btn btn-sm btn-outline-danger shadow-sm"><i class="fa-solid fa-person-walking-arrow-right me-1"></i> Checkout</button>
                                        </form>
                                    @endif
                                    <form action="{{ route('admin.visitors.destroy', $v) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete record?');">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-light text-danger"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <div class="empty-state">
                                        <i class="fa-solid fa-door-closed text-secondary fs-1 mb-2"></i>
                                        <div class="text-secondary">No visitors recorded.</div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Complaints Tab -->
    <div x-show="tab === 'complaints'" x-cloak>
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card bg-danger-subtle border-danger-subtle h-100">
                    <div class="card-body">
                        <div class="text-danger small fw-medium mb-1 text-uppercase tracking-wider">Open</div>
                        <div class="h3 text-danger mb-0 fw-bold">{{ $complaintCounts['open'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-warning-subtle border-warning-subtle h-100">
                    <div class="card-body">
                        <div class="text-warning-emphasis small fw-medium mb-1 text-uppercase tracking-wider">In Progress</div>
                        <div class="h3 text-warning-emphasis mb-0 fw-bold">{{ $complaintCounts['in_progress'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-success-subtle border-success-subtle h-100">
                    <div class="card-body">
                        <div class="text-success small fw-medium mb-1 text-uppercase tracking-wider">Resolved</div>
                        <div class="h3 text-success mb-0 fw-bold">{{ $complaintCounts['resolved'] }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-premium">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('Title') }}</th>
                                <th>{{ __('Student') }}</th>
                                <th>{{ __('Category') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Logged') }}</th>
                                <th class="text-end">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($complaints as $c)
                            <tr x-show="search === '' || '{{ strtolower($c->title . ' ' . optional($c->student)->name) }}'.includes(search.toLowerCase())">
                                <td>
                                    <div class="fw-semibold">{{ $c->title }}</div>
                                    @if($c->priority==='high') <span class="badge bg-danger rounded-pill mt-1" style="font-size:0.65rem;">High Priority</span> @endif
                                </td>
                                <td>
                                    @if($c->student)
                                        <a href="{{ route('admin.students.show', $c->student) }}" class="fw-medium text-decoration-none">{{ $c->student->name }}</a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>{{ config('hostelease.complaint_categories.'.$c->category, $c->category) }}</td>
                                <td>
                                    @if($c->status==='resolved'||$c->status==='closed')
                                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">{{ config('hostelease.complaint_statuses.'.$c->status) }}</span>
                                    @elseif($c->status==='in_progress')
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill">{{ config('hostelease.complaint_statuses.'.$c->status) }}</span>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill">{{ config('hostelease.complaint_statuses.'.$c->status) }}</span>
                                    @endif
                                </td>
                                <td class="text-secondary small">{{ $c->created_at->format('d M Y') }}</td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-light border shadow-sm" data-bs-toggle="modal" data-bs-target="#upd{{ $c->id }}"><i class="fa-solid fa-pen"></i></button>
                                </td>
                            </tr>
                            
                            {{-- Update status modal --}}
                            <div class="modal fade" id="upd{{ $c->id }}" tabindex="-1">
                                <div class="modal-dialog modal-dialog-centered">
                                    <form class="modal-content" method="POST" action="{{ route('admin.complaints.update', $c) }}">
                                        @csrf @method('PATCH')
                                        <div class="modal-header border-0">
                                            <h5 class="modal-title fw-bold">Update Complaint</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <div class="fw-semibold">{{ $c->title }}</div>
                                                @if($c->description)<p class="text-muted small mt-1">{{ $c->description }}</p>@endif
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Status</label>
                                                <select name="status" class="form-select">
                                                    @foreach(config('hostelease.complaint_statuses') as $k=>$l)
                                                        <option value="{{ $k }}" @selected($c->status===$k)>{{ $l }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="mb-1">
                                                <label class="form-label">Resolution / Notes</label>
                                                <textarea name="resolution" class="form-control" rows="2">{{ $c->resolution }}</textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer border-0 bg-light rounded-bottom">
                                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-dark">Save Update</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <div class="empty-state">
                                        <i class="fa-solid fa-headset text-secondary fs-1 mb-2"></i>
                                        <div class="text-secondary">No complaints logged.</div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Add Visitor Modal --}}
<div class="modal fade" id="addVisitorModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="POST" action="{{ route('admin.visitors.store') }}">
            @csrf
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Add Visitor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Name *</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Mobile</label>
                        <div class="input-group">
                            <span class="input-group-text">+91</span>
                            <input type="tel" name="mobile" class="form-control" maxlength="10">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Visiting Student</label>
                        <select name="student_id" class="form-select">
                            <option value="">— None (General) —</option>
                            @foreach($students as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Purpose</label>
                        <input type="text" name="purpose" class="form-control" placeholder="Meeting, Delivery...">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">ID Proof</label>
                        <input type="text" name="id_proof" class="form-control" placeholder="Aadhaar / DL no.">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Check-in Time</label>
                        <input type="datetime-local" name="check_in" class="form-control" value="{{ now()->format('Y-m-d\TH:i') }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <input type="text" name="notes" class="form-control">
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light rounded-bottom">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-dark">Check In</button>
            </div>
        </form>
    </div>
</div>

{{-- Log Complaint Modal --}}
<div class="modal fade" id="cmpModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="POST" action="{{ route('admin.complaints.store') }}">
            @csrf
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Log Complaint</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Title *</label>
                        <input type="text" name="title" class="form-control" required placeholder="Short summary of the issue">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Reported by Student (Optional)</label>
                        <select name="student_id" class="form-select">
                            <option value="">— Internal / Staff —</option>
                            @foreach($students as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Category</label>
                        <select name="category" class="form-select" required>
                            @foreach(config('hostelease.complaint_categories') as $k=>$l)
                                <option value="{{ $k }}">{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Priority</label>
                        <select name="priority" class="form-select" required>
                            @foreach(config('hostelease.complaint_priorities') as $k=>$l)
                                <option value="{{ $k }}" @selected($k==='medium')>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Detailed explanation..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light rounded-bottom">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-dark">Log Complaint</button>
            </div>
        </form>
    </div>
</div>
@endsection
