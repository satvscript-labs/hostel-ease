@extends('layouts.app')
@section('title', $student->name)

@push('styles')
<style>
    /* ══ Student Profile — premium redesign (Account-360 design language) ══
       Dark mesh hero with inline metrics, clean white panel-cards, bordered
       section heads, simple list rows. No glassmorphism, no bright banner. */

    /* Hero band — NOTE: the hero itself must NOT clip overflow, or the ⋯
       dropdown gets cut off at the hero's bottom edge. The decorative glow
       lives in its own clipped layer (.sp-hero-bg) instead. */
    .sp-hero {
        background: var(--he-gradient-mesh);
        color: #fff;
        border-radius: var(--he-radius-lg);
        position: relative;
    }
    .sp-hero-bg { position: absolute; inset: 0; z-index: 0; border-radius: inherit; overflow: hidden; pointer-events: none; }
    .sp-hero-bg::after {
        content: '';
        position: absolute;
        top: -40%; right: -8%;
        width: 380px; height: 380px;
        background: radial-gradient(circle, rgba(147, 51, 234, 0.35), transparent 70%);
    }
    .sp-hero .dropdown-menu { z-index: 1080; }
    .sp-hero-avatar {
        width: 76px; height: 76px; border-radius: 50%; object-fit: cover; flex-shrink: 0;
        border: 3px solid rgba(255, 255, 255, 0.25);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.25);
    }
    .sp-hero-meta { color: rgba(255, 255, 255, 0.6); font-size: 0.85rem; }
    .sp-metric { font-variant-numeric: tabular-nums; }
    .sp-metric-label { color: rgba(255, 255, 255, 0.55); font-size: 0.66rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
    .sp-metric-val { font-size: 1.3rem; font-weight: 800; line-height: 1.15; }
    .sp-metric-val.is-due { color: #fca5a5; }

    /* Panels */
    .panel-card {
        background: var(--he-bg-surface);
        border: 1px solid rgba(0, 0, 0, 0.05);
        border-radius: var(--he-radius-lg);
        box-shadow: var(--he-shadow-sm);
        transition: box-shadow 0.3s var(--ease-out-expo);
        overflow: hidden;
    }
    .panel-card:hover { box-shadow: var(--he-shadow-md); }
    .panel-head {
        padding: 0.9rem 1.25rem;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        display: flex; align-items: center; justify-content: space-between; gap: 0.5rem;
    }
    .panel-head h6 { margin: 0; font-weight: 700; color: var(--he-text-main); }
    .panel-body { padding: 1.25rem; }

    /* List rows */
    .sp-row {
        display: flex; align-items: center; justify-content: space-between; gap: 1rem;
        padding: 0.85rem 1.25rem;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        transition: background 0.2s var(--ease-out-expo);
    }
    .sp-row:last-child { border-bottom: none; }
    .sp-row:hover { background: var(--he-bg-surface-raised); }
    .sp-ic {
        width: 44px; height: 44px; border-radius: var(--he-radius-md);
        display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        font-size: 1.1rem;
    }

    /* Info rows (identity / plan) */
    .info-row {
        display: flex; align-items: center; justify-content: space-between; gap: 1rem;
        padding: 0.7rem 0;
        border-bottom: 1px dashed rgba(0, 0, 0, 0.08);
    }
    .info-row:last-child { border-bottom: none; }
    .info-row .lbl { color: var(--he-text-muted); font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px; }
    .info-row .val { font-weight: 700; color: var(--he-text-main); text-align: right; }

    /* Accommodation bed visual */
    .sp-bed {
        background: linear-gradient(135deg, rgba(79, 70, 229, 0.06), rgba(147, 51, 234, 0.03));
        border: 1px solid rgba(79, 70, 229, 0.12);
        border-radius: var(--he-radius-md);
    }

    /* Tabs — four equal icon-only segments; the ACTIVE one expands to reveal
       its label via animation (flex-grow + label max-width both transition). */
    .sp-tabs {
        display: flex; width: 100%; gap: 0.3rem;
        background: rgba(0, 0, 0, 0.04);
        padding: 0.3rem; border-radius: var(--he-radius-md);
    }
    .sp-tab {
        flex: 1 1 0; min-width: 0;
        display: flex; align-items: center; justify-content: center;
        padding: 0.6rem 0.5rem; border: none; background: transparent;
        border-radius: var(--he-radius-sm); font-weight: 600; font-size: 0.88rem;
        color: var(--he-text-muted); cursor: pointer; overflow: hidden;
        transition: flex-grow 0.35s var(--ease-out-expo), background 0.2s var(--ease-out-expo), color 0.2s var(--ease-out-expo);
    }
    .sp-tab i { font-size: 1rem; flex-shrink: 0; }
    .sp-tab-label {
        max-width: 0; opacity: 0; overflow: hidden; white-space: nowrap;
        transition: max-width 0.35s var(--ease-out-expo), opacity 0.25s var(--ease-out-expo), margin 0.35s var(--ease-out-expo);
    }
    .sp-tab.active {
        flex-grow: 2.4;
        background: var(--he-bg-surface); color: var(--he-primary); font-weight: 700;
        box-shadow: var(--he-shadow-sm);
    }
    .sp-tab.active .sp-tab-label { max-width: 130px; opacity: 1; margin-left: 0.5rem; }
    .sp-tab:hover:not(.active) { color: var(--he-text-main); }
    .sp-pane { padding: 1.25rem; }

    /* Timeline */
    .sp-timeline { border-left: 2px solid rgba(0, 0, 0, 0.08); }
    .sp-tl-item { position: relative; padding-left: 1.5rem; margin-bottom: 1.25rem; }
    .sp-tl-item:last-child { margin-bottom: 0; }
    .sp-tl-marker {
        position: absolute; left: -17px; top: 0;
        width: 32px; height: 32px; border-radius: 50%;
        border: 2px solid var(--he-bg-surface);
        display: flex; align-items: center; justify-content: center; z-index: 2;
    }

    /* ── Mobile: rearrange for a phone ─────────────────────────── */
    @media (max-width: 576px) {
        .sp-hero { border-radius: var(--he-radius-md); }
        .sp-hero-top { flex-direction: column; align-items: stretch !important; }
        .sp-hero-avatar { width: 64px; height: 64px; }
        .sp-hero h1 { font-size: 1.5rem; }
        .sp-hero-actions { width: 100%; }
        .sp-hero-actions .btn:not(.sp-more-btn) { flex: 1; }
        .sp-metric-val { font-size: 1.1rem; }
        .panel-head, .sp-row, .sp-pane { padding-left: 1rem; padding-right: 1rem; }
    }
</style>
@endpush

@section('content')
<div class="page-enter" x-data="{ tab: 'overview', qrOpen: false, collectOpen: false, docOpen: false, feeOpen: false }"
     @keydown.window.escape="qrOpen = false; collectOpen = false; docOpen = false; feeOpen = false">

    {{-- Back --}}
    <a href="{{ route('admin.students.index') }}" class="btn btn-sm btn-white rounded-pill px-3 mb-3 shadow-sm fw-semibold"><i class="fa-solid fa-arrow-left me-1"></i> Students</a>

    {{-- ══ Hero band ══ --}}
    <div class="sp-hero p-4 mb-4 shadow">
        <div class="sp-hero-bg"></div>
        <div class="position-relative" style="z-index: 2;">
            <div class="sp-hero-top d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div class="d-flex align-items-center gap-3">
                    <img src="{{ $student->photo_url }}" class="sp-hero-avatar" alt="{{ $student->name }}">
                    <div>
                        <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                            <h1 class="h3 fw-bold mb-0">{{ $student->name }}</h1>
                            <span class="badge bg-{{ $student->status === 'active' ? 'success' : 'secondary' }}-subtle text-{{ $student->status === 'active' ? 'success' : 'secondary' }} rounded-pill px-3 py-1 text-capitalize">{{ $student->status }}</span>
                        </div>
                        <div class="sp-hero-meta d-flex flex-wrap align-items-center">
                            <span class="me-3"><i class="fa-solid fa-briefcase me-1"></i>{{ config('hostelease.occupation_types.'.$student->occupation_type) }}</span>
                            <span class="me-3"><i class="fa-solid fa-phone me-1"></i>{{ hostelease_phone($student->mobile) }}</span>
                            @if($student->join_date)
                                <span><i class="fa-solid fa-calendar me-1"></i>Joined {{ $student->join_date->format('d M Y') }}</span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="sp-hero-actions d-flex flex-wrap gap-2">
                    <a href="{{ route('admin.students.edit', $student) }}" class="btn btn-light rounded-pill px-4 fw-bold shadow-sm"><i class="fa-solid fa-pen me-2"></i>Edit</a>
                    <button type="button" class="btn btn-light text-primary rounded-pill px-4 fw-bold shadow-sm" @click="collectOpen = true; $nextTick(() => openCollect({{ $paymentSummary['outstanding'] ?? 0 }}))"><i class="fa-solid fa-indian-rupee-sign me-2"></i>Collect</button>
                    <div class="dropdown">
                        <button type="button" class="btn btn-outline-light rounded-pill px-3 fw-bold sp-more-btn" data-bs-toggle="dropdown" aria-expanded="false"><i class="fa-solid fa-ellipsis"></i></button>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-4 p-2">
                            @if($qrSvg ?? false)
                            <li><button type="button" class="dropdown-item rounded-3 py-2" @click="qrOpen = true"><i class="fa-solid fa-qrcode text-primary me-2"></i>Show ID QR</button></li>
                            @endif
                            <li><a class="dropdown-item rounded-3 py-2" href="{{ route('admin.pocket-money.show', $student) }}"><i class="fa-solid fa-wallet text-primary me-2"></i>Add pocket money</a></li>
                            <li><button type="button" class="dropdown-item rounded-3 py-2" @click="docOpen = true"><i class="fa-solid fa-upload text-primary me-2"></i>Upload document</button></li>
                            <li><button type="button" class="dropdown-item rounded-3 py-2" @click="feeOpen = true"><i class="fa-solid fa-sliders text-primary me-2"></i>Edit fee plan</button></li>
                        </ul>
                    </div>
                </div>
            </div>

            {{-- At-a-glance metrics --}}
            <div class="row g-3 mt-2 position-relative" style="z-index: 1;">
                <div class="col-6 col-md sp-metric">
                    <div class="sp-metric-label">Outstanding</div>
                    <div class="sp-metric-val {{ ($paymentSummary['outstanding'] ?? 0) > 0 ? 'is-due' : '' }}">{{ hostelease_money($paymentSummary['outstanding'] ?? 0) }}</div>
                </div>
                <div class="col-6 col-md sp-metric">
                    <div class="sp-metric-label">Total Paid</div>
                    <div class="sp-metric-val">{{ hostelease_money($paymentSummary['total_paid'] ?? 0) }}</div>
                </div>
                <div class="col-6 col-md sp-metric">
                    <div class="sp-metric-label">Credit</div>
                    <div class="sp-metric-val">{{ hostelease_money($student->credit_balance ?? 0) }}</div>
                </div>
                <div class="col-6 col-md sp-metric">
                    <div class="sp-metric-label">Pocket Money</div>
                    <div class="sp-metric-val">{{ hostelease_money($pocketBalance ?? 0) }}</div>
                </div>
                <div class="col-6 col-md sp-metric">
                    <div class="sp-metric-label">Security Deposit</div>
                    <div class="sp-metric-val">{{ hostelease_money($student->securityDeposits()->where('status', 'collected')->sum('amount')) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        {{-- ══ Left column ══ --}}
        <div class="col-lg-4">
            <div class="d-flex flex-column gap-4">

                {{-- Accommodation --}}
                <div class="panel-card">
                    <div class="panel-head"><h6><i class="fa-solid fa-bed text-primary me-2"></i>Accommodation</h6></div>
                    <div class="panel-body">
                        @if($student->activeAssignment)
                        <div class="sp-bed p-3 text-center mb-3">
                            <div class="d-flex justify-content-center gap-4 align-items-center">
                                <div>
                                    <div class="text-muted small fw-bold text-uppercase">Room</div>
                                    <div class="fs-3 fw-bold text-dark lh-1 mt-1">{{ $student->activeAssignment->bed->room->room_number }}</div>
                                </div>
                                <div style="width: 2px; height: 36px; background: rgba(0,0,0,0.06); border-radius: 2px;"></div>
                                <div>
                                    <div class="text-muted small fw-bold text-uppercase">Bed</div>
                                    <div class="fs-3 fw-bold text-primary lh-1 mt-1">{{ $student->activeAssignment->bed->bed_number }}</div>
                                </div>
                            </div>
                            <div class="badge bg-white text-dark shadow-sm rounded-pill mt-3 px-4 py-2 border fw-bold">
                                <i class="fa-solid fa-building me-1 text-muted"></i> {{ $student->activeAssignment->bed->room->floor->name }}
                            </div>
                        </div>
                        <a href="{{ route('admin.property.index') }}" class="btn btn-white border w-100 fw-bold text-primary rounded-pill py-2">
                            <i class="fa-solid fa-right-left me-1"></i> Transfer Bed
                        </a>
                        @else
                        <div class="text-center py-4">
                            <i class="fa-solid fa-bed-pulse text-muted fs-1 mb-2 opacity-50"></i>
                            <p class="text-muted fw-bold mb-3">Not assigned to a bed</p>
                            <a href="{{ route('admin.property.index') }}" class="btn btn-primary btn-sm rounded-pill fw-bold px-4 shadow-sm">Assign Now</a>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Plan & preferences --}}
                <div class="panel-card">
                    <div class="panel-head">
                        <h6><i class="fa-solid fa-sliders text-primary me-2"></i>Plan &amp; Preferences</h6>
                        <button type="button" class="btn btn-sm btn-white border text-primary rounded-pill px-3 fw-semibold" @click="feeOpen = true"><i class="fa-solid fa-pen me-1"></i>Edit</button>
                    </div>
                    <div class="panel-body py-2">
                        <div class="info-row">
                            <span class="lbl">Fee Amount</span>
                            <span class="val">{{ $student->fee_amount ? hostelease_money($student->fee_amount) : 'Not set' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="lbl">Fee Structure</span>
                            <span class="val text-capitalize">{{ $student->fee_frequency ?? 'Not set' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="lbl">Room Preference</span>
                            <span class="val">{{ $student->room_preference ?? 'Any' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="lbl">Sharing Preference</span>
                            <span class="val">{{ $student->sharing_preference ?? 'Any' }}</span>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        {{-- ══ Right column — tabs ══ --}}
        <div class="col-lg-8">
            <div class="panel-card">
                <div class="panel-head">
                    <div class="sp-tabs">
                        <button type="button" class="sp-tab" :class="{ active: tab === 'overview' }" @click="tab = 'overview'" title="Overview">
                            <i class="fa-solid fa-user"></i><span class="sp-tab-label">Overview</span>
                        </button>
                        <button type="button" class="sp-tab" :class="{ active: tab === 'fees' }" @click="tab = 'fees'" title="Invoices">
                            <i class="fa-solid fa-file-invoice-dollar"></i><span class="sp-tab-label">Invoices</span>
                        </button>
                        <button type="button" class="sp-tab" :class="{ active: tab === 'documents' }" @click="tab = 'documents'" title="Documents">
                            <i class="fa-solid fa-folder-open"></i><span class="sp-tab-label">Documents</span>
                        </button>
                        <button type="button" class="sp-tab" :class="{ active: tab === 'history' }" @click="tab = 'history'" title="Timeline">
                            <i class="fa-solid fa-clock-rotate-left"></i><span class="sp-tab-label">Timeline</span>
                        </button>
                    </div>
                </div>

                {{-- OVERVIEW --}}
                <div x-show="tab === 'overview'" x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 transform translate-y-2" x-transition:enter-end="opacity-100 transform translate-y-0"
                     x-cloak class="sp-pane">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="text-muted fw-bold small text-uppercase mb-2"><i class="fa-solid fa-id-card text-primary me-2"></i>Identity</div>
                            <div class="info-row">
                                <span class="lbl">Aadhaar No.</span>
                                <span class="val font-monospace">{{ $student->aadhaar ? substr($student->aadhaar, 0, 4) . ' ' . substr($student->aadhaar, 4, 4) . ' ' . substr($student->aadhaar, 8, 4) : 'Not provided' }}</span>
                            </div>
                            <div class="info-row">
                                <span class="lbl">Join Date</span>
                                <span class="val">{{ $student->join_date ? $student->join_date->format('d M Y') : '—' }}</span>
                            </div>
                            <div class="pt-3">
                                <div class="text-muted fw-bold small text-uppercase mb-2"><i class="fa-solid fa-location-dot me-1"></i> Permanent Address</div>
                                <div class="bg-light rounded-3 p-3 fw-semibold border">
                                    {{ $student->address ?? 'Not provided' }}
                                    @if($student->city || $student->state)
                                        <div class="text-muted fw-normal mt-1">{{ $student->city }}{{ $student->city && $student->state ? ', ' : '' }}{{ $student->state }}</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted fw-bold small text-uppercase mb-2"><i class="fa-solid fa-users text-primary me-2"></i>Family Contacts</div>
                            <div class="d-flex flex-column">
                                <div class="d-flex align-items-center gap-3 py-2">
                                    <div class="sp-ic bg-primary-subtle text-primary"><i class="fa-solid fa-person"></i></div>
                                    <div class="flex-grow-1">
                                        <div class="text-muted small fw-bold text-uppercase">Father</div>
                                        <div class="fw-bold"><x-mobile-link :mobile="$student->father_mobile" /></div>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-3 py-2 border-top">
                                    <div class="sp-ic bg-danger-subtle text-danger"><i class="fa-solid fa-person-dress"></i></div>
                                    <div class="flex-grow-1">
                                        <div class="text-muted small fw-bold text-uppercase">Mother</div>
                                        <div class="fw-bold">
                                            @if($student->mother_mobile)<x-mobile-link :mobile="$student->mother_mobile" />@else<span class="text-muted fw-normal">Not provided</span>@endif
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-3 py-2 border-top">
                                    <div class="sp-ic bg-warning-subtle text-warning"><i class="fa-solid fa-shield-halved"></i></div>
                                    <div class="flex-grow-1">
                                        <div class="text-muted small fw-bold text-uppercase">Guardian</div>
                                        <div class="fw-bold">
                                            @if($student->guardian_mobile)<x-mobile-link :mobile="$student->guardian_mobile" />@else<span class="text-muted fw-normal">Not provided</span>@endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- INVOICES --}}
                <div x-show="tab === 'fees'" x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 transform translate-y-2" x-transition:enter-end="opacity-100 transform translate-y-0"
                     x-cloak class="sp-pane pt-0">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
                        <h6 class="fw-bold mb-0"><i class="fa-solid fa-file-invoice-dollar text-primary me-2"></i>Invoice History</h6>
                        <button type="button" class="btn btn-premium btn-sm rounded-pill px-3 shadow-sm fw-bold" @click="collectOpen = true; $nextTick(() => openCollect({{ $paymentSummary['outstanding'] ?? 0 }}))">
                            <i class="fa-solid fa-indian-rupee-sign me-1"></i> Collect
                        </button>
                    </div>
                    <div class="d-flex flex-column gap-2">
                        @forelse($invoices as $invoice)
                            @php($ic = $invoice->status === 'paid' ? 'success' : ($invoice->status === 'partial' ? 'warning' : 'danger'))
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 p-3 border rounded-4">
                                <div class="d-flex gap-3 align-items-center">
                                    <div class="sp-ic bg-{{ $ic }}-subtle text-{{ $ic }}">
                                        <i class="fa-solid fa-{{ $invoice->type === 'fee' ? 'graduation-cap' : ($invoice->type === 'rent' ? 'home' : ($invoice->type === 'ac' ? 'snowflake' : 'receipt')) }}"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold">{{ $invoice->title }}</div>
                                        <div class="text-secondary small fw-bold text-uppercase">{{ $invoice->type }}</div>
                                        @if($invoice->status !== 'paid' && $invoice->due_date)
                                            <div class="small text-danger fw-bold mt-1"><i class="fa-regular fa-clock me-1"></i> Due {{ $invoice->due_date->format('d M Y') }}</div>
                                        @endif
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold fs-5">{{ hostelease_money($invoice->amount) }}</div>
                                    <div class="small fw-bold {{ $invoice->status === 'paid' ? 'text-success' : 'text-danger' }} text-uppercase mt-1">
                                        @if($invoice->status === 'paid')
                                            <i class="fa-solid fa-check-circle me-1"></i> Paid
                                        @else
                                            Bal: {{ hostelease_money($invoice->balance) }}
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <x-he-empty-state icon="circle-check" title="All cleared up" subtitle="No invoices outstanding for this student." />
                        @endforelse
                    </div>
                </div>

                {{-- DOCUMENTS --}}
                <div x-show="tab === 'documents'" x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 transform translate-y-2" x-transition:enter-end="opacity-100 transform translate-y-0"
                     x-cloak class="sp-pane pt-0">
                    <div class="d-flex justify-content-between align-items-center py-3">
                        <h6 class="fw-bold mb-0"><i class="fa-solid fa-file-lines text-primary me-2"></i>Documents</h6>
                        <button type="button" class="btn btn-premium btn-sm rounded-pill px-3 shadow-sm fw-bold" @click="docOpen = true">
                            <i class="fa-solid fa-upload me-1"></i> Upload
                        </button>
                    </div>
                    <div class="d-flex flex-column gap-2">
                        @forelse($student->documents as $doc)
                            <div class="d-flex justify-content-between align-items-center gap-2 p-3 border rounded-4">
                                <div class="d-flex align-items-center gap-3 min-width-0">
                                    <div class="sp-ic bg-primary-subtle text-primary">
                                        <i class="fa-solid fa-{{ in_array($doc->type, ['photo']) ? 'image' : 'file-pdf' }}"></i>
                                    </div>
                                    <div class="min-width-0">
                                        <div class="fw-bold text-truncate">{{ $doc->title ?: ucfirst($doc->type) }}</div>
                                        <div class="mt-1 d-flex gap-2 flex-wrap align-items-center">
                                            <span class="badge bg-primary-subtle text-primary fw-bold text-uppercase">{{ $doc->type }}</span>
                                            @if($doc->expiry_date)
                                                <span class="small fw-bold text-muted"><i class="fa-regular fa-calendar-xmark"></i> Exp {{ $doc->expiry_date->format('d M Y') }}</span>
                                            @endif
                                            @if($doc->is_signed)
                                                <span class="small fw-bold text-success"><i class="fa-solid fa-check-circle"></i> Signed</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex gap-2 flex-shrink-0">
                                    <a href="{{ Storage::disk('public')->url($doc->file_path) }}" target="_blank" class="btn btn-white border text-primary rounded-circle shadow-sm d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                    <form action="{{ route('admin.students.documents.destroy', [$student, $doc]) }}" method="POST" class="d-inline" data-confirm="Delete this document?">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-white border text-danger rounded-circle shadow-sm d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <x-he-empty-state icon="folder-open" title="No documents yet" subtitle="Keep important student files secure here.">
                                <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm mt-3" @click="docOpen = true">
                                    <i class="fa-solid fa-upload me-1"></i> Upload Document
                                </button>
                            </x-he-empty-state>
                        @endforelse
                    </div>
                </div>

                {{-- TIMELINE --}}
                <div x-show="tab === 'history'" x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 transform translate-y-2" x-transition:enter-end="opacity-100 transform translate-y-0"
                     x-cloak class="sp-pane">
                    <h6 class="fw-bold mb-4"><i class="fa-solid fa-clock-rotate-left text-primary me-2"></i>Activity Timeline</h6>
                    <div class="sp-timeline ps-2 ms-3">
                        @forelse($timeline as $event)
                            <div class="sp-tl-item">
                                <div class="sp-tl-marker bg-{{ $event->color }}-subtle text-{{ $event->color }} shadow-sm">
                                    <i class="fa-solid fa-{{ $event->icon }} small"></i>
                                </div>
                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 p-3 border rounded-4">
                                    <div>
                                        <div class="fw-bold text-dark">{{ $event->title }}</div>
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
                        @empty
                            <div class="ms-2"><x-he-empty-state icon="clock" title="No history yet" subtitle="Activity for this student will appear here." /></div>
                        @endforelse
                    </div>
                </div>

            </div>
        </div>
    </div>

<!-- TELEPORTED MODALS -->
<template x-teleport="body">
    {{-- QR Modal --}}
    @if($qrSvg ?? false)
    <div class="custom-overlay-backdrop" x-show="qrOpen" x-transition.opacity @click="qrOpen = false" x-cloak style="display: none;">
        <div class="custom-overlay-modal" :class="{ 'is-open': qrOpen }" x-show="qrOpen" @click.stop x-cloak style="display: none; max-width: 360px;">
            <div class="custom-overlay-header">
                <h5 class="fw-bold mb-0"><i class="fa-solid fa-qrcode" style="color: var(--he-primary);"></i><span class="ms-1">Student ID QR</span></h5>
                <button type="button" class="btn-close" @click="qrOpen = false"></button>
            </div>
            <div class="custom-overlay-body text-center">
                <div class="border rounded-4 p-3 bg-white d-inline-block shadow-sm" style="line-height:0">{!! $qrSvg !!}</div>
                <p class="text-muted small fw-bold mt-3 mb-0 text-uppercase">Scan for verification</p>
            </div>
        </div>
    </div>
    @endif
</template>

<template x-teleport="body">
    {{-- Collect Modal --}}
    <div class="custom-overlay-backdrop" x-show="collectOpen" x-transition.opacity @click="collectOpen = false" x-cloak style="display: none;">
        <form class="custom-overlay-modal" id="collectForm" method="POST"
              x-show="collectOpen" :class="{ 'is-open': collectOpen }" @click.stop
              x-data="studentProfileCollectModal({{ $paymentSummary['outstanding'] ?? 0 }}, {{ $student->credit_balance ?? 0 }})"
              action="{{ route('admin.students.collect', $student) }}"
              data-collect-action="{{ route('admin.students.collect', $student) }}"
              data-promise-action="{{ route('admin.students.promise', $student) }}"
              style="display: none; max-width: 600px;">
            @csrf
            <div class="custom-overlay-header">
                <h5 class="fw-bold mb-0" id="collectTitle">Collect Payment</h5>
                <button type="button" class="btn-close" @click="collectOpen = false"></button>
            </div>
            <div class="custom-overlay-body">
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
            <div class="custom-overlay-footer">
                <button type="button" class="btn btn-white border rounded-pill px-4 fw-bold" @click="collectOpen = false">Cancel</button>
                @if(!empty($paymentModes) && $paymentModes->isNotEmpty())
                    <button type="submit" class="btn btn-premium rounded-pill px-4 fw-bold shadow-sm" id="collectSubmit">Collect Payment</button>
                @endif
            </div>
        </form>
    </div>
</template>

<template x-teleport="body">
    {{-- Document Modal --}}
    <div class="custom-overlay-backdrop" x-show="docOpen" x-transition.opacity @click="docOpen = false" x-cloak style="display: none;">
        <form class="custom-overlay-modal" method="POST" action="{{ route('admin.students.documents.store', $student) }}" enctype="multipart/form-data"
              x-show="docOpen" :class="{ 'is-open': docOpen }" @click.stop style="display: none; max-width: 480px;">
            @csrf
            <div class="custom-overlay-header">
                <h5 class="fw-bold mb-0"><i class="fa-solid fa-upload" style="color: var(--he-primary);"></i><span class="ms-1">Upload Document</span></h5>
                <button type="button" class="btn-close" @click="docOpen = false"></button>
            </div>
            <div class="custom-overlay-body">
                <div class="mb-3">
                    <label class="form-label fw-bold small">Document Type</label>
                    <x-he-select name="type" icon="file-lines" :submit="false" :selected="'aadhaar'"
                        :options="['aadhaar' => 'Aadhaar', 'photo' => 'Photo / ID', 'agreement' => 'Rental Agreement', 'other' => 'Other Document']" />
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
                <label class="form-check bg-light rounded-4 p-3 d-flex align-items-center m-0" for="isSigned" style="cursor: pointer;">
                    <input class="form-check-input m-0 flex-shrink-0" type="checkbox" name="is_signed" value="1" id="isSigned" style="width: 1.25rem; height: 1.25rem;">
                    <span class="ms-3 fw-bold">This document is physically signed</span>
                </label>
            </div>
            <div class="custom-overlay-footer">
                <button type="button" class="btn btn-white border rounded-pill px-4 fw-bold" @click="docOpen = false">Cancel</button>
                <button type="submit" class="btn btn-premium rounded-pill px-4 fw-bold shadow-sm">Upload File</button>
            </div>
        </form>
    </div>
</template>

<template x-teleport="body">
    {{-- Fee Settings Modal --}}
    <div class="custom-overlay-backdrop" x-show="feeOpen" x-transition.opacity @click="feeOpen = false" x-cloak style="display: none;">
        <form class="custom-overlay-modal" method="POST" action="{{ route('admin.students.fee-settings.update', $student) }}"
              x-show="feeOpen" :class="{ 'is-open': feeOpen }" @click.stop
              x-data="prorationPreview()" style="display: none; max-width: 680px;">
            @csrf
            @method('PUT')
            <div class="custom-overlay-header">
                <h5 class="fw-bold mb-0"><i class="fa-solid fa-sliders" style="color: var(--he-primary);"></i><span class="ms-1">Change Fee &amp; Room Plan</span></h5>
                <button type="button" class="btn-close" @click="feeOpen = false"></button>
            </div>
            <div class="custom-overlay-body">
                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Room Preference</label>
                        <x-he-select name="room_preference" icon="door-closed" :submit="false"
                            :selected="$student->room_preference"
                            :options="['' => 'Select preference', 'AC' => 'AC Room', 'Non-AC' => 'Non-AC Room']" />
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Sharing Preference</label>
                        <x-he-select name="sharing_preference" icon="users" :submit="false"
                            :selected="$student->sharing_preference"
                            :options="['' => 'Select sharing'] + collect(hostelease_sharing_labels())->mapWithKeys(fn ($l) => [$l => $l])->all()" />
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
            <div class="custom-overlay-footer">
                <button type="button" class="btn btn-white border rounded-pill px-4 fw-bold" @click="feeOpen = false">Cancel</button>
                <button type="submit" class="btn btn-premium rounded-pill px-4 fw-bold shadow-sm" :disabled="loading"><i class="fa-solid fa-save me-2"></i>Confirm &amp; Save</button>
            </div>
        </form>
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
