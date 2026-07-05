@extends('layouts.app')
@section('title', $student->name)

@section('content')
<div x-data="{ tab: 'overview' }" class="page-enter">
    <!-- Header -->
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <a href="{{ route('admin.students.index') }}" class="btn btn-light btn-sm" style="border-radius: var(--he-radius-sm);">
            <i class="fa-solid fa-arrow-left"></i>
        </a>
        <h1 class="h4 fw-bold mb-0 text-truncate" style="max-width:200px">{{ $student->name }}</h1>
        <span class="badge-premium bg-{{ $student->status === 'active' ? 'success' : 'secondary' }}-subtle text-{{ $student->status === 'active' ? 'success' : 'secondary' }}">
            {{ ucfirst($student->status) }}
        </span>
        <div class="ms-auto d-flex gap-2 flex-shrink-0">
            @if($student->mobile)
                <a href="https://wa.me/91{{ preg_replace('/\D+/', '', $student->mobile) }}?text={{ urlencode('Dear '.$student->name.', regarding your hostel account — outstanding balance: '.hostelease_money($paymentSummary['outstanding'] ?? 0).'. Thank you, '.optional($student->hostel)->name) }}"
                   target="_blank" rel="noopener" class="btn btn-whatsapp btn-sm">
                    <i class="fa-brands fa-whatsapp"></i><span class="d-none d-sm-inline ms-1">WhatsApp</span>
                </a>
            @endif
            <a href="{{ route('admin.students.edit', $student) }}" class="btn btn-premium btn-sm">
                <i class="fa-solid fa-pen"></i><span class="d-none d-sm-inline ms-1">Edit</span>
            </a>
        </div>
    </div>

    <!-- Profile Hero Card -->
    <div class="profile-hero mb-3">
        <div class="ph-banner"></div>
        <div class="px-3 px-md-4 pb-3">
            <div class="d-flex flex-wrap align-items-end gap-3">
                <div class="ph-avatar-wrap">
                    <img src="{{ $student->photo_url }}" class="ph-avatar" alt="">
                </div>
                <div class="flex-grow-1 pb-1">
                    <h2 class="h5 fw-bold mb-0">{{ $student->name }}</h2>
                    <p class="mb-0" style="font-size: var(--he-text-sm); color: var(--he-text-muted);">
                        {{ config('hostelease.occupation_types.'.$student->occupation_type) }}
                        · <x-mobile-link :mobile="$student->mobile" />
                    </p>
                </div>
                @if($qrSvg)
                <div class="d-none d-md-block">
                    <div class="border rounded-3 p-1 bg-white" style="line-height:0">{!! $qrSvg !!}</div>
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Financial Summary Bento -->
    <div class="bento mb-3 stagger">
        <div class="bento-card">
            <div class="d-flex align-items-center gap-3 h-100">
                <div class="bento-icon bg-primary-subtle text-primary"><i class="fa-solid fa-receipt"></i></div>
                <div>
                    <div class="bento-value" style="font-size:1.25rem">{{ hostelease_money($paymentSummary['total_billed']) }}</div>
                    <div class="bento-label">Total Billed</div>
                </div>
            </div>
        </div>
        <div class="bento-card">
            <div class="d-flex align-items-center gap-3 h-100">
                <div class="bento-icon bg-success-subtle text-success"><i class="fa-solid fa-check-circle"></i></div>
                <div>
                    <div class="bento-value text-success" style="font-size:1.25rem">{{ hostelease_money($paymentSummary['total_paid']) }}</div>
                    <div class="bento-label">Total Paid</div>
                </div>
            </div>
        </div>
        <div class="bento-card">
            <div class="d-flex align-items-center gap-3 h-100">
                <div class="bento-icon bg-{{ $paymentSummary['outstanding'] > 0 ? 'danger' : 'success' }}-subtle text-{{ $paymentSummary['outstanding'] > 0 ? 'danger' : 'success' }}">
                    <i class="fa-solid fa-{{ $paymentSummary['outstanding'] > 0 ? 'exclamation-triangle' : 'circle-check' }}"></i>
                </div>
                <div>
                    <div class="bento-value {{ $paymentSummary['outstanding'] > 0 ? 'text-danger' : 'text-success' }}" style="font-size:1.25rem">
                        {{ hostelease_money($paymentSummary['outstanding']) }}
                    </div>
                    <div class="bento-label">Outstanding</div>
                </div>
            </div>
        </div>
        <div class="bento-card">
            <div class="d-flex align-items-center gap-3 h-100">
                <div class="bento-icon bg-warning-subtle text-warning"><i class="fa-solid fa-wallet"></i></div>
                <div>
                    <div class="bento-value" style="font-size:1.25rem">{{ hostelease_money($pocketBalance) }}</div>
                    <div class="bento-label">Pocket Money</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs Navigation -->
    <div class="card-premium overflow-hidden">
        <div class="he-tabs px-2">
            <button class="he-tab" :class="{ active: tab === 'overview' }" @click="tab = 'overview'">
                <i class="fa-solid fa-user me-1"></i> Overview
            </button>
            <button class="he-tab" :class="{ active: tab === 'fees' }" @click="tab = 'fees'">
                <i class="fa-solid fa-indian-rupee-sign me-1"></i> Fees & Dues
            </button>
            <button class="he-tab" :class="{ active: tab === 'documents' }" @click="tab = 'documents'">
                <i class="fa-solid fa-file-lines me-1"></i> Documents
            </button>
            <button class="he-tab" :class="{ active: tab === 'history' }" @click="tab = 'history'">
                <i class="fa-solid fa-clock-rotate-left me-1"></i> History
            </button>
        </div>

        <div class="p-3 p-md-4">
            <!-- TAB: Overview -->
            <div class="he-tab-panel" :class="{ active: tab === 'overview' }">
                <div class="row g-4">
                    <!-- Contact Info -->
                    <div class="col-12 col-md-6">
                        <div class="section-header"><i class="fa-solid fa-phone"></i> Contact Information</div>
                        <div class="info-row">
                            <div class="ir-icon"><i class="fa-solid fa-user"></i></div>
                            <div><div class="ir-label">Student Mobile</div><div class="ir-value"><x-mobile-link :mobile="$student->mobile" /></div></div>
                        </div>
                        <div class="info-row">
                            <div class="ir-icon"><i class="fa-solid fa-person"></i></div>
                            <div><div class="ir-label">Father's Mobile</div><div class="ir-value"><x-mobile-link :mobile="$student->father_mobile" /></div></div>
                        </div>
                        <div class="info-row">
                            <div class="ir-icon"><i class="fa-solid fa-person-dress"></i></div>
                            <div><div class="ir-label">Mother's Mobile</div><div class="ir-value"><x-mobile-link :mobile="$student->mother_mobile" /></div></div>
                        </div>
                        <div class="info-row">
                            <div class="ir-icon"><i class="fa-solid fa-shield-halved"></i></div>
                            <div><div class="ir-label">Guardian's Mobile</div><div class="ir-value"><x-mobile-link :mobile="$student->guardian_mobile" /></div></div>
                        </div>
                    </div>

                    <!-- Identity & Stay -->
                    <div class="col-12 col-md-6">
                        <div class="section-header"><i class="fa-solid fa-id-card"></i> Identity & Stay</div>
                        <div class="info-row">
                            <div class="ir-icon"><i class="fa-solid fa-fingerprint"></i></div>
                            <div><div class="ir-label">Aadhaar</div><div class="ir-value">{{ $student->aadhaar ?? '—' }}</div></div>
                        </div>
                        <div class="info-row">
                            <div class="ir-icon"><i class="fa-solid fa-location-dot"></i></div>
                            <div>
                                <div class="ir-label">Address</div>
                                <div class="ir-value">{{ $student->address ?? '—' }}{{ $student->city ? ', '.$student->city : '' }}{{ $student->state ? ', '.$student->state : '' }}</div>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="ir-icon"><i class="fa-solid fa-calendar-check"></i></div>
                            <div>
                                <div class="ir-label">Join / Leave</div>
                                <div class="ir-value">{{ optional($student->join_date)->format('d M Y') ?? '—' }} → {{ optional($student->leave_date)->format('d M Y') ?? 'Present' }}</div>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="ir-icon"><i class="fa-solid fa-bed"></i></div>
                            <div>
                                <div class="ir-label">Current Bed</div>
                                <div class="ir-value">
                                    @if($student->activeAssignment)
                                        {{ $student->activeAssignment->bed->room->floor->name }} ·
                                        Room {{ $student->activeAssignment->bed->room->room_number }} ·
                                        Bed {{ $student->activeAssignment->bed->bed_number }}
                                    @else
                                        <span class="text-warning"><i class="fa-solid fa-triangle-exclamation me-1"></i>Not assigned</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="col-12">
                        <div class="d-flex flex-wrap gap-2">
                            <a href="{{ route('admin.pocket-money.show', $student) }}" class="btn btn-outline-warning btn-sm" style="border-radius: var(--he-radius-sm);">
                                <i class="fa-solid fa-wallet me-1"></i> Pocket Money
                            </a>
                            <a href="{{ route('admin.ledger.show', $student) }}" class="btn btn-outline-primary btn-sm" style="border-radius: var(--he-radius-sm);">
                                <i class="fa-solid fa-book me-1"></i> Full Ledger
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB: Fees & Dues -->
            <div class="he-tab-panel" :class="{ active: tab === 'fees' }">
                <!-- Fee Dues -->
                <div class="mb-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <h3 class="h6 fw-bold mb-0">
                            <i class="fa-solid fa-graduation-cap text-primary me-1"></i> Fees
                            @if($paymentSummary['outstanding_fees'] > 0)
                                <span class="badge-premium bg-danger-subtle text-danger ms-1">{{ hostelease_money($paymentSummary['outstanding_fees']) }} due</span>
                            @endif
                        </h3>
                        <button class="btn btn-premium btn-sm" data-bs-toggle="modal" data-bs-target="#collectModal"
                                onclick="openCollect('fees', {{ $paymentSummary['outstanding_fees'] }})">
                            <i class="fa-solid fa-indian-rupee-sign me-1"></i> Collect
                        </button>
                    </div>
                    @include('admin.students._dues_table', ['rows' => $feesDues])
                </div>

                <!-- AC Dues -->
                <div>
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <h3 class="h6 fw-bold mb-0">
                            <i class="fa-solid fa-snowflake text-info me-1"></i> AC Bills
                            @if($paymentSummary['outstanding_ac'] > 0)
                                <span class="badge-premium bg-danger-subtle text-danger ms-1">{{ hostelease_money($paymentSummary['outstanding_ac']) }} due</span>
                            @endif
                        </h3>
                        <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#collectModal"
                                onclick="openCollect('ac', {{ $paymentSummary['outstanding_ac'] }})"
                                style="border-radius: var(--he-radius-sm);">
                            <i class="fa-solid fa-snowflake me-1"></i> Collect AC
                        </button>
                    </div>
                    @include('admin.students._dues_table', ['rows' => $acDues])
                </div>
            </div>

            <!-- TAB: Documents -->
            <div class="he-tab-panel" :class="{ active: tab === 'documents' }">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="h6 fw-bold mb-0"><i class="fa-solid fa-file-lines text-primary me-1"></i> Documents</h3>
                    <button class="btn btn-premium btn-sm" data-bs-toggle="modal" data-bs-target="#docModal">
                        <i class="fa-solid fa-upload me-1"></i> Upload
                    </button>
                </div>

                @forelse($student->documents as $doc)
                    <div class="due-card">
                        <div class="d-flex justify-content-between align-items-center gap-2">
                            <div class="d-flex align-items-center gap-2 min-width-0">
                                <div class="bento-icon bg-primary-subtle text-primary" style="width:36px;height:36px;font-size:0.85rem">
                                    <i class="fa-solid fa-{{ in_array($doc->type, ['photo']) ? 'image' : 'file-pdf' }}"></i>
                                </div>
                                <div class="min-width-0">
                                    <div class="fw-semibold text-truncate" style="font-size: var(--he-text-sm);">{{ $doc->title ?: ucfirst($doc->type) }}</div>
                                    <div style="font-size: var(--he-text-xs); color: var(--he-text-muted);">
                                        <span class="badge-premium bg-primary-subtle text-primary">{{ ucfirst($doc->type) }}</span>
                                        @if($doc->expiry_date)
                                            · Exp: {{ $doc->expiry_date->format('d M Y') }}
                                        @endif
                                        @if($doc->is_signed)
                                            · <i class="fa-solid fa-check-circle text-success"></i> Signed
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex gap-1 flex-shrink-0">
                                <a href="{{ Storage::disk('public')->url($doc->file_path) }}" target="_blank" class="btn btn-sm btn-light" style="border-radius: var(--he-radius-sm);">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                                <form action="{{ route('admin.students.documents.destroy', [$student, $doc]) }}" method="POST" class="d-inline" data-confirm="Delete this document?">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-light text-danger" style="border-radius: var(--he-radius-sm);"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="empty-state py-4">
                        <i class="fa-solid fa-folder-open d-block"></i>
                        <p>No documents uploaded yet.</p>
                        <button class="btn btn-premium btn-sm mt-2" data-bs-toggle="modal" data-bs-target="#docModal">
                            <i class="fa-solid fa-upload me-1"></i> Upload Document
                        </button>
                    </div>
                @endforelse
            </div>

            <!-- TAB: History -->
            <div class="he-tab-panel" :class="{ active: tab === 'history' }">
                <h3 class="h6 fw-bold mb-3"><i class="fa-solid fa-clock-rotate-left text-primary me-1"></i> Bed Assignment History</h3>

                @forelse($student->assignments as $a)
                    <div class="due-card">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div>
                                <div class="fw-semibold" style="font-size: var(--he-text-sm);">
                                    <i class="fa-solid fa-bed me-1 text-muted"></i>
                                    Room {{ $a->bed->room->room_number }} / Bed {{ $a->bed->bed_number }}
                                </div>
                                <div style="font-size: var(--he-text-xs); color: var(--he-text-muted);" class="mt-1">
                                    {{ $a->join_date->format('d M Y') }} → {{ optional($a->leave_date)->format('d M Y') ?? 'Present' }}
                                    · {{ $a->durationInDays() }} days
                                </div>
                                <div style="font-size: var(--he-text-sm);" class="mt-1">
                                    {{ hostelease_money($a->fee_amount) }}
                                    <span class="text-muted">/ {{ $a->feeFrequencyLabel() }}</span>
                                </div>
                            </div>
                            <span class="badge-premium bg-{{ $a->is_active ? 'success' : 'secondary' }}-subtle text-{{ $a->is_active ? 'success' : 'secondary' }}">
                                {{ $a->is_active ? 'Active' : 'Past' }}
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="empty-state py-4">
                        <i class="fa-solid fa-bed d-block"></i>
                        <p>No bed assignments yet.</p>
                    </div>
                @endforelse

                @if($paymentSummary['last_payment'])
                <h3 class="h6 fw-bold mt-4 mb-3"><i class="fa-solid fa-money-bill-wave text-success me-1"></i> Last Payment</h3>
                <div class="due-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="dc-amount text-success">{{ hostelease_money($paymentSummary['last_payment']->amount) }}</div>
                            <div style="font-size: var(--he-text-xs); color: var(--he-text-muted);">
                                {{ $paymentSummary['last_payment']->paid_on->format('d M Y') }}
                            </div>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Collect (fees / AC) modal --}}
<div class="modal fade" id="collectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="collectForm" method="POST"
              action="{{ route('admin.students.collect', $student) }}"
              data-collect-action="{{ route('admin.students.collect', $student) }}"
              data-promise-action="{{ route('admin.students.promise', $student) }}"
              style="border-radius: var(--he-radius-lg); overflow: hidden;">
            @csrf
            <input type="hidden" name="scope" id="collectScope" value="fees">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="collectTitle">Collect Fees</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                @if($paymentModes->isEmpty())
                    <div class="alert alert-warning mb-0" style="border-radius: var(--he-radius-md);">
                        No payment modes yet. Add one under <a href="{{ route('admin.payment-modes.index') }}">Payment Modes</a> first.
                    </div>
                @else
                    {{-- Pay now / Promise toggle --}}
                    <div class="btn-group w-100 mb-3" role="group">
                        <input type="radio" class="btn-check" name="collect_mode" id="modePay" value="pay" checked onchange="setCollectMode('pay')">
                        <label class="btn btn-outline-primary" for="modePay"><i class="fa-solid fa-indian-rupee-sign me-1"></i> Pay now</label>
                        <input type="radio" class="btn-check" name="collect_mode" id="modePromise" value="promise" onchange="setCollectMode('promise')">
                        <label class="btn btn-outline-primary" for="modePromise"><i class="fa-regular fa-calendar-check me-1"></i> Promise</label>
                    </div>

                    {{-- Pay fields --}}
                    <div id="payFields">
                        <div class="mb-3">
                            <label class="form-label">Amount (₹)</label>
                            <input type="number" step="0.01" min="1" name="amount" id="collectAmount" class="form-control" required>
                        </div>
                        <div class="row g-3">
                            <div class="col-6"><label class="form-label">Payment Type</label>
                                <select name="payment_type" class="form-select">@foreach(config('hostelease.payment_types') as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
                            <div class="col-6"><label class="form-label">Mode</label>
                                <select name="mode" class="form-select" required>@foreach($paymentModes as $m)<option value="{{ $m->code }}">{{ $m->name }}</option>@endforeach</select></div>
                            <div class="col-6"><label class="form-label">Date</label>
                                <input type="date" name="paid_on" class="form-control" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required></div>
                            <div class="col-6"><label class="form-label">Reference</label>
                                <input type="text" name="reference_number" class="form-control" placeholder="Optional"></div>
                            <div class="col-12"><label class="form-label">Remarks</label>
                                <input type="text" name="remarks" class="form-control" placeholder="Optional"></div>
                        </div>
                    </div>

                    {{-- Promise fields --}}
                    <div id="promiseFields" class="d-none">
                        <div class="alert alert-info py-2 small mb-3" style="border-radius: var(--he-radius-sm);">
                            <i class="fa-solid fa-circle-info me-1"></i> No money is taken now — this records the date the student promised to clear the outstanding.
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Promise Date</label>
                            <input type="date" name="promise_date" id="promiseDate" class="form-control"
                                   min="{{ now()->toDateString() }}" value="{{ now()->addDays(7)->toDateString() }}" required disabled>
                        </div>
                        <div class="mb-1">
                            <label class="form-label">Note (optional)</label>
                            <input type="text" name="promise_note" id="promiseNote" class="form-control" maxlength="255" placeholder="e.g. after salary on 5th" disabled>
                        </div>
                    </div>
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius: var(--he-radius-sm);">Cancel</button>
                @unless($paymentModes->isEmpty())
                    <button type="submit" class="btn btn-premium" id="collectSubmit">Collect</button>
                @endunless
            </div>
        </form>
    </div>
</div>

{{-- Upload document modal --}}
<div class="modal fade" id="docModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="POST" action="{{ route('admin.students.documents.store', $student) }}" enctype="multipart/form-data"
              style="border-radius: var(--he-radius-lg); overflow: hidden;">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Upload Document</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Type</label>
                    <select name="type" class="form-select" required>
                        <option value="aadhaar">Aadhaar</option>
                        <option value="photo">Photo</option>
                        <option value="agreement">Agreement</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="mb-3"><label class="form-label">Title (optional)</label><input type="text" name="title" class="form-control"></div>
                <div class="mb-3"><label class="form-label">File (jpg, png, pdf · max 5MB)</label><input type="file" name="file" class="form-control" accept=".jpg,.jpeg,.png,.webp,.pdf" required></div>
                <div class="mb-3"><label class="form-label">Expiry Date (optional)</label><input type="date" name="expiry_date" class="form-control"></div>
                <div class="form-check"><input class="form-check-input" type="checkbox" name="is_signed" value="1" id="isSigned"><label class="form-check-label" for="isSigned">Signed agreement</label></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius: var(--he-radius-sm);">Cancel</button>
                <button type="submit" class="btn btn-premium">Upload</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    function openCollect(scope, amount) {
        document.getElementById('collectScope').value = scope;
        document.getElementById('collectTitle').textContent = scope === 'ac' ? 'Collect AC Bill' : 'Collect Fees';
        const amt = document.getElementById('collectAmount');
        if (amt) amt.value = amount > 0 ? amount : '';
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

        submit.textContent = promising ? 'Save Promise' : 'Collect';
        submit.classList.toggle('btn-premium', !promising);
        submit.classList.toggle('btn-warning', promising);
    }
</script>
@endpush
@endsection
