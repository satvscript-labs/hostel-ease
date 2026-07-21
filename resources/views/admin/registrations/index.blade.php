@extends('layouts.app')
@section('title', 'Student Registrations')

@push('styles')
<style>
    /* Registrations — premium card language (was unstyled: .premium-panel
       was never defined for this page). */
    /* .panel-card / .panel-head / .panel-body are canonical in _premium.scss. */

    .reg-page-title { font-size: 1.6rem; letter-spacing: -0.01em; }

    /* Pending application cards (replaces the 6-column table). */
    .reg-list { display: flex; flex-direction: column; }
    .reg-card {
        display: flex; align-items: center; gap: 1rem;
        padding: 1rem 1.25rem;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        transition: background 0.2s var(--ease-out-expo);
    }
    .reg-card:last-child { border-bottom: none; }
    .reg-card:hover { background: var(--he-bg-surface-raised); }
    .reg-avatar {
        width: 48px; height: 48px; border-radius: 50%; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        font-weight: 800; color: #fff; font-size: 1rem;
        background: linear-gradient(135deg, var(--he-primary), var(--he-accent));
    }
    .reg-info { flex: 1; min-width: 0; }
    .reg-name { font-weight: 700; color: var(--he-text-main); }
    .reg-meta {
        display: flex; flex-wrap: wrap; gap: 0.1rem 1rem;
        color: var(--he-text-muted); font-size: 0.82rem; font-weight: 600; margin-top: 0.15rem;
    }
    .reg-time { color: var(--he-text-muted); font-size: 0.72rem; margin-top: 0.2rem; }

    /* Review fields */
    .reg-field {
        background: var(--he-bg-surface-raised);
        border-radius: var(--he-radius-md);
        padding: 0.75rem 1rem; height: 100%;
    }
    .reg-field-lbl { color: var(--he-text-muted); font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px; margin-bottom: 0.15rem; }
    .reg-field-val { font-weight: 700; color: var(--he-text-main); word-break: break-word; }

    /* Self-registration link — a URL field + Copy/Open actions. Desktop: one
       row (as before). The link URL can be arbitrarily long, so on mobile a
       single cramped row truncates it to a handful of characters and squishes
       the buttons to sub-tap-target size — the exact defect being fixed. */
    .reg-link-group { display: flex; align-items: stretch; }
    .reg-link-input { flex: 1; min-width: 0; }
    .reg-link-btns { display: flex; flex-shrink: 0; border-left: 1px solid rgba(0, 0, 0, 0.08); }
    .reg-link-btn { border-radius: 0 !important; border-left: 1px solid rgba(0, 0, 0, 0.08); }
    .reg-link-btn:first-child { border-left: none; }

    @media (max-width: 576px) {
        .reg-page-title { font-size: 2.2rem; line-height: 1.5; } /* mobile heading standard */
        .reg-card { flex-wrap: wrap; padding: 0.9rem 1rem; }
        .reg-review-btn { width: 100%; margin-top: 0.35rem; }

        /* Stack: full-width URL row on top, two evenly-split full-width
           (≥44px tall) action buttons below — never a cramped single row. */
        .reg-link-group { flex-direction: column; }
        .reg-link-input { width: 100%; font-size: 0.82rem; padding: 0.75rem 0.9rem; }
        .reg-link-btns { border-left: none; border-top: 1px solid rgba(0, 0, 0, 0.08); width: 100%; }
        .reg-link-btn { flex: 1; justify-content: center; padding: 0.7rem 0.5rem; min-height: 44px; }
    }
</style>
@endpush

@section('content')
<div class="page-enter" x-data="registrationManager()">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-4">
        <h1 class="reg-page-title fw-bold mb-0 text-dark">Student Registrations</h1>
        <span class="badge bg-warning-subtle text-warning rounded-pill px-3 py-2 fw-bold d-inline-flex align-items-center">
            <span class="spinner-grow spinner-grow-sm me-2" role="status" aria-hidden="true" style="width: 0.5rem; height: 0.5rem;"></span>
            <span id="pending-count">{{ $pending->count() }}</span>&nbsp;Pending
        </span>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-7">
            <div class="panel-card h-100">
                <div class="p-4">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 34px; height: 34px; font-size: 0.95rem;">
                            <i class="fa-solid fa-link"></i>
                        </div>
                        <h6 class="fw-bold fs-5 mb-0">Self-Registration Link</h6>
                    </div>
                    <div class="w-100 min-width-0">
                        <p class="text-muted small mb-3">Share this link with students so they can fill out their own entry form. Submissions appear below for your approval.</p>
                        <div class="reg-link-group shadow-sm rounded-3 overflow-hidden border">
                            <input type="text" class="form-control bg-light border-0 reg-link-input" value="{{ $url }}" id="reg-url" readonly>
                            <div class="reg-link-btns">
                                <button class="btn btn-white fw-bold text-primary reg-link-btn" type="button" onclick="navigator.clipboard.writeText(document.getElementById('reg-url').value); window.Swal && Swal.fire({toast:true,position:'top-end',icon:'success',title:'Link copied',showConfirmButton:false,timer:1800})">
                                    <i class="fa-regular fa-copy me-1"></i> Copy
                                </button>
                                <a class="btn btn-white fw-bold text-dark reg-link-btn" href="{{ $url }}" target="_blank" title="Open link">
                                    <i class="fa-solid fa-arrow-up-right-from-square me-1"></i><span class="reg-link-open-label">Open</span>
                                </a>
                            </div>
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
            <div class="panel-card h-100 text-center d-flex flex-column align-items-center justify-content-center p-4">
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

    <div class="panel-card">
        <div class="panel-head">
            <h6><i class="fa-solid fa-inbox text-primary me-2"></i>Pending Applications</h6>
            <div class="small fw-bold text-muted"><i class="fa-solid fa-rotate me-1" :class="{'fa-spin text-primary': isPolling}"></i> Auto-refresh</div>
        </div>

        {{-- Polling target — swapped wholesale by fetchUpdates(). --}}
        <div class="reg-list" id="registrations-list">
            @forelse($pending as $r)
                @php($initials = collect(explode(' ', trim($r->name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode(''))
                <div class="reg-card">
                    <div class="reg-avatar">{{ $initials ?: '?' }}</div>
                    <div class="reg-info">
                        <div class="reg-name">{{ $r->name }}</div>
                        <div class="reg-meta">
                            <span><i class="fa-solid fa-phone me-1"></i>{{ hostelease_phone($r->mobile) }}</span>
                            <span><i class="fa-solid fa-briefcase me-1"></i>{{ config('hostelease.occupation_types.'.$r->occupation_type, ucfirst($r->occupation_type)) }}</span>
                            @if($r->city)<span><i class="fa-solid fa-location-dot me-1"></i>{{ $r->city }}</span>@endif
                        </div>
                        <div class="reg-time"><i class="fa-regular fa-clock me-1"></i>{{ $r->created_at->format('d M, h:i A') }} · {{ $r->created_at->diffForHumans() }}</div>
                    </div>
                    <button class="btn btn-premium btn-sm rounded-pill px-3 shadow-sm fw-bold reg-review-btn"
                            @click="openReview({
                                id: '{{ $r->public_id }}',
                                name: '{{ addslashes($r->name) }}',
                                mobile: '{{ $r->mobile }}',
                                occupation: '{{ addslashes(config('hostelease.occupation_types.'.$r->occupation_type, ucfirst($r->occupation_type))) }}',
                                joining_date: '{{ $r->joining_date ? \Illuminate\Support\Carbon::parse($r->joining_date)->format('d M Y') : '' }}',
                                aadhaar: '{{ hostelease_mask_aadhaar($r->aadhaar) }}',
                                father_mobile: '{{ $r->father_mobile }}',
                                mother_mobile: '{{ $r->mother_mobile }}',
                                address: '{{ addslashes($r->address) }}',
                                city: '{{ $r->city }}',
                                state: '{{ $r->state }}'
                            })">
                        <i class="fa-solid fa-magnifying-glass me-1"></i> Review
                    </button>
                </div>
            @empty
                <x-he-empty-state icon="box-open" title="No pending registrations"
                    subtitle="New student applications will appear here automatically." />
            @endforelse
        </div>
    </div>

{{-- Review modal — canonical custom-overlay (Alpine-driven, x-text; no
     innerHTML injection). Becomes a bottom sheet on mobile automatically. --}}
<template x-teleport="body">
    <div class="custom-overlay-backdrop" x-show="reviewOpen" x-transition.opacity @click="reviewOpen = false" x-cloak style="display: none;">
        <div class="custom-overlay-modal" :class="{ 'is-open': reviewOpen }" x-show="reviewOpen" @click.stop x-cloak style="display: none; max-width: 640px;">
            <div class="custom-overlay-header">
                <h5 class="fw-bold mb-0"><i class="fa-solid fa-user-check" style="color: var(--he-primary);"></i><span class="ms-1">Review Application</span></h5>
                <button type="button" class="btn-close" @click="reviewOpen = false"></button>
            </div>
            <div class="custom-overlay-body">
                <div class="row g-3">
                    <div class="col-md-6"><div class="reg-field"><div class="reg-field-lbl">Full Name</div><div class="reg-field-val" x-text="current.name"></div></div></div>
                    <div class="col-md-6"><div class="reg-field"><div class="reg-field-lbl">Mobile</div><div class="reg-field-val" x-text="current.mobile ? '+91 ' + current.mobile : '—'"></div></div></div>
                    <div class="col-6 col-md-4"><div class="reg-field"><div class="reg-field-lbl">Joining Date</div><div class="reg-field-val" x-text="current.joining_date || '—'"></div></div></div>
                    <div class="col-6 col-md-4"><div class="reg-field"><div class="reg-field-lbl">Occupation</div><div class="reg-field-val" x-text="current.occupation || '—'"></div></div></div>
                    <div class="col-12 col-md-4"><div class="reg-field"><div class="reg-field-lbl">Aadhaar</div>
                        {{-- Masked by default; the eye toggle hits a LOGGED reveal endpoint (P5). --}}
                        <div class="reg-field-val d-flex align-items-center gap-2">
                            <span x-text="(aadhaarShown ? aadhaarFull : current.aadhaar) || '—'"></span>
                            <button type="button" x-show="current.aadhaar && current.aadhaar !== '—'" @click="revealAadhaar()" :disabled="aadhaarLoading"
                                    class="btn btn-sm btn-link p-0 text-muted lh-1" :title="aadhaarShown ? 'Hide' : 'Reveal — this is logged'">
                                <i class="fa-solid" :class="aadhaarLoading ? 'fa-spinner fa-spin' : (aadhaarShown ? 'fa-eye-slash' : 'fa-eye')"></i>
                            </button>
                        </div>
                    </div></div>
                    <div class="col-6"><div class="reg-field"><div class="reg-field-lbl">Father's Mobile</div><div class="reg-field-val" x-text="current.father_mobile ? '+91 ' + current.father_mobile : '—'"></div></div></div>
                    <div class="col-6"><div class="reg-field"><div class="reg-field-lbl">Mother's Mobile</div><div class="reg-field-val" x-text="current.mother_mobile ? '+91 ' + current.mother_mobile : '—'"></div></div></div>
                    <div class="col-12"><div class="reg-field"><div class="reg-field-lbl">Address</div><div class="reg-field-val" x-text="current.address || '—'"></div></div></div>
                    <div class="col-6"><div class="reg-field"><div class="reg-field-lbl">City</div><div class="reg-field-val" x-text="current.city || '—'"></div></div></div>
                    <div class="col-6"><div class="reg-field"><div class="reg-field-lbl">State</div><div class="reg-field-val" x-text="current.state || '—'"></div></div></div>
                </div>
            </div>
            <div class="custom-overlay-footer">
                <form method="POST" class="flex-fill" :action="'{{ url('admin/registrations') }}/' + current.id + '/reject'" data-confirm="Reject this application?">
                    @csrf
                    <button type="submit" class="btn btn-white border text-danger rounded-pill w-100 fw-bold tactile-btn"><i class="fa-solid fa-xmark me-1"></i> Reject</button>
                </form>
                <form method="POST" class="flex-fill" :action="'{{ url('admin/registrations') }}/' + current.id + '/approve'" data-confirm="Approve this application and create the student?">
                    @csrf
                    <button type="submit" class="btn btn-success rounded-pill w-100 fw-bold shadow-sm tactile-btn"><i class="fa-solid fa-check me-1"></i> Approve &amp; Create</button>
                </form>
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
        reviewOpen: false,
        current: {},
        aadhaarShown: false,
        aadhaarFull: null,
        aadhaarLoading: false,

        init() {
            // Poll every 15 seconds for new submissions.
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

                const newList = doc.getElementById('registrations-list');
                if (newList) {
                    document.getElementById('registrations-list').innerHTML = newList.innerHTML;
                }
                const newCount = doc.getElementById('pending-count');
                if (newCount) {
                    document.getElementById('pending-count').textContent = newCount.textContent;
                }
            } catch (error) {
                console.error('Polling failed', error);
            } finally {
                setTimeout(() => this.isPolling = false, 500); // brief spinner hold
            }
        },

        openReview(reg) {
            this.current = reg;
            this.aadhaarShown = false;
            this.aadhaarFull = null;
            this.reviewOpen = true;
        },

        // Logged Aadhaar reveal (P5) — fetches the full number for this applicant.
        async revealAadhaar() {
            if (this.aadhaarShown) { this.aadhaarShown = false; return; }
            if (this.aadhaarFull) { this.aadhaarShown = true; return; }
            this.aadhaarLoading = true;
            try {
                const r = await fetch(`{{ url('admin/registrations') }}/${this.current.id}/aadhaar`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (r.ok) { this.aadhaarFull = (await r.json()).aadhaar; this.aadhaarShown = true; }
            } catch (e) {}
            this.aadhaarLoading = false;
        }
    }
}
</script>
@endpush
@endsection
