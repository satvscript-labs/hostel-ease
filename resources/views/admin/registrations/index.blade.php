@extends('layouts.app')
@section('title', 'Student Registrations')

@section('content')
<div class="page-enter" x-data="registrationManager()">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div class="d-flex align-items-center gap-3">
            <h1 class="h3 fw-bold mb-0 text-dark">Student Registrations</h1>
            <span class="badge bg-warning text-dark rounded-pill px-3 py-2 fw-bold shadow-sm d-flex align-items-center">
                <span class="spinner-grow spinner-grow-sm me-2" role="status" aria-hidden="true" style="width: 0.5rem; height: 0.5rem;"></span>
                <span id="pending-count">{{ $pending->count() }}</span> &nbsp;Pending
            </span>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-7">
            <div class="premium-panel p-4 h-100 d-flex flex-column justify-content-center">
                <div class="d-flex align-items-start gap-3">
                    <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 48px; height: 48px; font-size: 1.25rem;">
                        <i class="fa-solid fa-link"></i>
                    </div>
                    <div class="w-100">
                        <h6 class="fw-bold fs-5 mb-1">Self-Registration Link</h6>
                        <p class="text-muted small mb-3">Share this link with students so they can fill out their own entry form. Submissions appear below for your approval.</p>
                        <div class="input-group shadow-sm rounded-3 overflow-hidden border">
                            <input type="text" class="form-control bg-light border-0" value="{{ $url }}" id="reg-url" readonly>
                            <button class="btn btn-white border-start fw-bold text-primary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('reg-url').value)">
                                <i class="fa-regular fa-copy me-1"></i> Copy
                            </button>
                            <a class="btn btn-white border-start fw-bold text-dark" href="{{ $url }}" target="_blank">
                                <i class="fa-solid fa-arrow-up-right-from-square"></i>
                            </a>
                        </div>
                        <form action="{{ route('admin.registrations.regenerate') }}" method="POST" class="mt-3" data-confirm="Generate a new link? The current link/QR will stop working.">
                            @csrf
                            <button class="btn btn-light btn-sm rounded-pill fw-bold border"><i class="fa-solid fa-rotate me-1 text-muted"></i> Generate new link</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="premium-panel p-4 h-100 text-center d-flex flex-column align-items-center justify-content-center">
                <h6 class="fw-bold fs-5 mb-3">Scan to Register</h6>
                @if($qr)
                    <div class="bg-white p-3 rounded-4 shadow-sm border d-inline-block" style="line-height:0">
                        {!! $qr !!}
                    </div>
                @else
                    <div class="p-4 bg-light rounded-4 text-muted"><i class="fa-solid fa-qrcode fs-1 mb-2"></i><br>QR unavailable</div>
                @endif
            </div>
        </div>
    </div>

    <div class="premium-panel overflow-hidden">
        <div class="p-4 border-bottom bg-light bg-opacity-50 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0"><i class="fa-solid fa-inbox text-primary me-2"></i> Pending Applications</h6>
            <div class="small fw-bold text-muted"><i class="fa-solid fa-rotate me-1" :class="{'fa-spin text-primary': isPolling}"></i> Auto-refresh active</div>
        </div>
        
        <div class="table-responsive" id="registrations-table-container">
            <!-- Polling target -->
            <table class="table table-hover align-middle mb-0 border-0" id="registrations-table">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4 border-0 text-uppercase small fw-bold text-muted">Student</th>
                        <th class="border-0 text-uppercase small fw-bold text-muted">Mobile</th>
                        <th class="border-0 text-uppercase small fw-bold text-muted">Occupation</th>
                        <th class="border-0 text-uppercase small fw-bold text-muted">City</th>
                        <th class="border-0 text-uppercase small fw-bold text-muted">Submitted</th>
                        <th class="text-end pe-4 border-0 text-uppercase small fw-bold text-muted">Action</th>
                    </tr>
                </thead>
                <tbody class="border-top-0">
                @forelse($pending as $r)
                    <tr>
                        <td class="ps-4">
                            <div class="fw-bold text-dark fs-6">{{ $r->name }}</div>
                        </td>
                        <td>
                            <div class="fw-bold"><i class="fa-solid fa-phone small text-muted me-1"></i>{{ hostelease_phone($r->mobile) }}</div>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border">{{ config('hostelease.occupation_types.'.$r->occupation_type, $r->occupation_type) }}</span>
                        </td>
                        <td>
                            <div class="text-muted fw-bold">{{ $r->city ?? '—' }}</div>
                        </td>
                        <td>
                            <div class="small fw-bold text-muted">
                                <i class="fa-regular fa-clock me-1"></i>{{ $r->created_at->format('d M, h:i A') }}
                            </div>
                            <div class="small text-muted">{{ $r->created_at->diffForHumans() }}</div>
                        </td>
                        <td class="text-end pe-4">
                            <button class="btn btn-premium btn-sm rounded-pill px-3 shadow-sm" @click='reviewApplication(@json($r))'>
                                <i class="fa-solid fa-magnifying-glass me-1"></i> Review
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-5">
                            <div class="text-muted mb-3"><i class="fa-solid fa-box-open fs-1"></i></div>
                            <h5 class="fw-bold text-dark">No pending registrations</h5>
                            <p class="text-muted mb-0">New student applications will appear here automatically.</p>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

<!-- Review Modal (Teleported to avoid layout issues) -->
<template x-teleport="body">
    <div class="modal fade" id="reviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content" style="border-radius: var(--he-radius-lg); border: none; box-shadow: 0 20px 40px rgba(0,0,0,0.1);">
                <div class="modal-header border-0 pb-0 mt-2 ms-2">
                    <h5 class="modal-title fw-bold fs-4 d-flex align-items-center">
                        <i class="fa-solid fa-user-check text-primary me-2"></i> Review Application
                    </h5>
                    <button type="button" class="btn-close me-2" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4" id="reviewModalBody">
                    <!-- Injected by JS -->
                </div>
                <div class="modal-footer border-top bg-light rounded-bottom-4 p-3 d-flex justify-content-between">
                    <form method="POST" id="rejectForm">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger rounded-pill px-4 fw-bold" data-confirm="Reject this application?">
                            <i class="fa-solid fa-xmark me-1"></i> Reject
                        </button>
                    </form>
                    <form method="POST" id="approveForm">
                        @csrf
                        <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm" data-confirm="Approve this application and create the student?">
                            <i class="fa-solid fa-check me-1"></i> Approve & Create Student
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>
</div> <!-- end page-enter -->

@push('scripts')
<script>
function registrationManager() {
    return {
        isPolling: false,
        pollInterval: null,
        
        init() {
            // Poll every 15 seconds
            this.pollInterval = setInterval(() => this.fetchUpdates(), 15000);
        },
        
        async fetchUpdates() {
            this.isPolling = true;
            try {
                const response = await fetch(window.location.href, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const html = await response.text();
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                
                // Update the table
                const newTable = doc.getElementById('registrations-table');
                if (newTable) {
                    document.getElementById('registrations-table').innerHTML = newTable.innerHTML;
                }
                
                // Update counter
                const newCount = doc.getElementById('pending-count');
                if (newCount) {
                    document.getElementById('pending-count').textContent = newCount.textContent;
                }
                
            } catch (error) {
                console.error("Polling failed", error);
            } finally {
                setTimeout(() => this.isPolling = false, 500); // Visual delay for spinner
            }
        },
        
        reviewApplication(reg) {
            // Set form actions
            document.getElementById('approveForm').action = `{{ url('admin/registrations') }}/${reg.id}/approve`;
            document.getElementById('rejectForm').action = `{{ url('admin/registrations') }}/${reg.id}/reject`;
            
            // Build the UI
            const body = document.getElementById('reviewModalBody');
            
            // Format occupation (capitalizing first letter as fallback)
            const occ = reg.occupation_type ? reg.occupation_type.charAt(0).toUpperCase() + reg.occupation_type.slice(1) : '—';
            
            body.innerHTML = `
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded-4 border">
                            <div class="text-muted small fw-bold text-uppercase mb-1">Full Name</div>
                            <div class="fw-bold fs-5 text-dark">${reg.name}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded-4 border">
                            <div class="text-muted small fw-bold text-uppercase mb-1">Mobile</div>
                            <div class="fw-bold fs-5 text-dark">+91 ${reg.mobile}</div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="text-muted small fw-bold text-uppercase mb-1"><i class="fa-solid fa-calendar text-primary me-1"></i> Joining Date</div>
                        <div class="fw-bold text-dark">${reg.joining_date || '—'}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small fw-bold text-uppercase mb-1"><i class="fa-solid fa-briefcase text-primary me-1"></i> Occupation</div>
                        <div class="fw-bold text-dark">${occ}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small fw-bold text-uppercase mb-1"><i class="fa-solid fa-id-card text-primary me-1"></i> Aadhaar</div>
                        <div class="fw-bold text-dark">${reg.aadhaar || '—'}</div>
                    </div>

                    <div class="col-12"><hr class="text-muted my-1"></div>

                    <div class="col-md-6">
                        <div class="text-muted small fw-bold text-uppercase mb-1">Father's Mobile</div>
                        <div class="fw-bold text-dark">${reg.father_mobile ? '+91 '+reg.father_mobile : '—'}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small fw-bold text-uppercase mb-1">Mother's Mobile</div>
                        <div class="fw-bold text-dark">${reg.mother_mobile ? '+91 '+reg.mother_mobile : '—'}</div>
                    </div>

                    <div class="col-12"><hr class="text-muted my-1"></div>

                    <div class="col-md-12">
                        <div class="text-muted small fw-bold text-uppercase mb-1">Address</div>
                        <div class="fw-bold text-dark">${reg.address || '—'}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small fw-bold text-uppercase mb-1">City</div>
                        <div class="fw-bold text-dark">${reg.city || '—'}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small fw-bold text-uppercase mb-1">State</div>
                        <div class="fw-bold text-dark">${reg.state || '—'}</div>
                    </div>
                </div>
            `;
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('reviewModal'));
            modal.show();
        }
    }
}
</script>
@endpush
@endsection
