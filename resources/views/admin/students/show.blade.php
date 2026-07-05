@extends('layouts.app')
@section('title', $student->name)

@section('content')
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <a href="{{ route('admin.students.index') }}" class="btn btn-light btn-sm"><i class="fa-solid fa-arrow-left"></i></a>
    <h1 class="h4 fw-bold mb-0">{{ $student->name }}</h1>
    <span class="badge bg-{{ $student->status === 'active' ? 'success' : 'secondary' }}">{{ ucfirst($student->status) }}</span>
    <div class="ms-auto d-flex gap-2">
        @if($student->mobile)
            <a href="https://wa.me/91{{ preg_replace('/\D+/', '', $student->mobile) }}?text={{ urlencode('Dear '.$student->name.', regarding your hostel account — outstanding balance: '.hsms_money($paymentSummary['outstanding'] ?? 0).'. Thank you, '.optional($student->hostel)->name) }}"
               target="_blank" rel="noopener" class="btn btn-whatsapp btn-sm"><i class="fa-brands fa-whatsapp me-1"></i> WhatsApp</a>
        @endif
        <a href="{{ route('admin.students.edit', $student) }}" class="btn btn-primary btn-sm"><i class="fa-solid fa-pen me-1"></i> Edit</a>
    </div>
</div>

<div class="row g-3">
    {{-- Profile card --}}
    <div class="col-lg-4">
        <div class="card stat-card">
            <div class="card-body text-center">
                <img src="{{ $student->photo_url }}" class="rounded mb-3" style="width:130px;height:130px;object-fit:cover;" alt="">
                <h2 class="h5 fw-bold mb-1">{{ $student->name }}</h2>
                <p class="text-muted mb-2">{{ config('hsms.occupation_types.'.$student->occupation_type) }}</p>
                <div class="mb-3"><x-mobile-link :mobile="$student->mobile" /></div>
                @if($qrSvg)
                    <div class="d-inline-block border rounded p-2 bg-white">{!! $qrSvg !!}</div>
                    <p class="text-muted small mt-1 mb-0">Scan for profile</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Details + contacts --}}
    <div class="col-lg-8">
        <div class="card stat-card mb-3">
            <div class="card-body">
                <h2 class="h6 fw-bold mb-3">Details</h2>
                <dl class="row mb-0 small">
                    <dt class="col-sm-4 text-muted">Father's Mobile</dt><dd class="col-sm-8"><x-mobile-link :mobile="$student->father_mobile" /></dd>
                    <dt class="col-sm-4 text-muted">Mother's Mobile</dt><dd class="col-sm-8"><x-mobile-link :mobile="$student->mother_mobile" /></dd>
                    <dt class="col-sm-4 text-muted">Guardian's Mobile</dt><dd class="col-sm-8"><x-mobile-link :mobile="$student->guardian_mobile" /></dd>
                    <dt class="col-sm-4 text-muted">Aadhaar</dt><dd class="col-sm-8">{{ $student->aadhaar ?? '—' }}</dd>
                    <dt class="col-sm-4 text-muted">Address</dt><dd class="col-sm-8">{{ $student->address ?? '—' }}{{ $student->city ? ', '.$student->city : '' }}{{ $student->state ? ', '.$student->state : '' }}</dd>
                    <dt class="col-sm-4 text-muted">Join / Leave</dt><dd class="col-sm-8">{{ optional($student->join_date)->format('d-m-Y') ?? '—' }} → {{ optional($student->leave_date)->format('d-m-Y') ?? '—' }}</dd>
                    <dt class="col-sm-4 text-muted">Current Bed</dt>
                    <dd class="col-sm-8">
                        @if($student->activeAssignment)
                            {{ $student->activeAssignment->bed->room->floor->name }} ·
                            Room {{ $student->activeAssignment->bed->room->room_number }} ·
                            Bed {{ $student->activeAssignment->bed->bed_number }}
                        @else
                            <span class="text-muted">Not assigned</span>
                        @endif
                    </dd>
                </dl>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-6 col-lg-3">
                <div class="card stat-card h-100"><div class="card-body">
                    <div class="stat-label">Total Billed</div>
                    <div class="stat-value">{{ hsms_money($paymentSummary['total_billed']) }}</div>
                </div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card stat-card h-100"><div class="card-body">
                    <div class="stat-label">Total Paid</div>
                    <div class="stat-value text-success">{{ hsms_money($paymentSummary['total_paid']) }}</div>
                </div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card stat-card h-100"><div class="card-body">
                    <div class="stat-label">Outstanding</div>
                    <div class="stat-value {{ $paymentSummary['outstanding'] > 0 ? 'text-danger' : 'text-success' }}">{{ hsms_money($paymentSummary['outstanding']) }}</div>
                </div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card stat-card h-100"><div class="card-body">
                    <div class="stat-label">Last Payment</div>
                    <div class="stat-value">{{ $paymentSummary['last_payment'] ? hsms_money($paymentSummary['last_payment']->amount) : '—' }}</div>
                    <small class="text-muted">{{ optional($paymentSummary['last_payment']?->paid_on)->format('d-m-Y') }}</small>
                </div></div>
            </div>
        </div>
    </div>
</div>

{{-- Fees & Dues --}}
<div class="card stat-card mt-3">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h2 class="h6 fw-bold mb-0">Fees &amp; Dues
                <span class="badge bg-danger-subtle text-danger ms-1">{{ hsms_money($paymentSummary['outstanding_fees']) }} due</span>
            </h2>
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#collectModal"
                    onclick="openCollect('fees', {{ $paymentSummary['outstanding_fees'] }})">
                <i class="fa-solid fa-indian-rupee-sign me-1"></i> Collect Fees
            </button>
        </div>
        @include('admin.students._dues_table', ['rows' => $feesDues])
    </div>
</div>

{{-- AC Bills (kept separate from fees) --}}
<div class="card stat-card mt-3">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h2 class="h6 fw-bold mb-0">AC Bills
                <span class="badge bg-danger-subtle text-danger ms-1">{{ hsms_money($paymentSummary['outstanding_ac']) }} due</span>
            </h2>
            <button class="btn btn-sm btn-info text-white" data-bs-toggle="modal" data-bs-target="#collectModal"
                    onclick="openCollect('ac', {{ $paymentSummary['outstanding_ac'] }})">
                <i class="fa-solid fa-snowflake me-1"></i> Collect AC
            </button>
        </div>
        @include('admin.students._dues_table', ['rows' => $acDues])
    </div>
</div>

{{-- Pocket Money --}}
<div class="card stat-card mt-3">
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h2 class="h6 fw-bold mb-1"><i class="fa-solid fa-wallet text-warning me-1"></i> Pocket Money</h2>
            <div class="stat-value">{{ hsms_money($pocketBalance) }}</div>
        </div>
        <a href="{{ route('admin.pocket-money.show', $student) }}" class="btn btn-sm btn-outline-primary">Manage</a>
    </div>
</div>

{{-- Collect (fees / AC) modal — Pay now OR Promise to pay --}}
<div class="modal fade" id="collectModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" id="collectForm" method="POST"
              action="{{ route('admin.students.collect', $student) }}"
              data-collect-action="{{ route('admin.students.collect', $student) }}"
              data-promise-action="{{ route('admin.students.promise', $student) }}">
            @csrf
            <input type="hidden" name="scope" id="collectScope" value="fees">
            <div class="modal-header">
                <h5 class="modal-title" id="collectTitle">Collect Fees</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                @if($paymentModes->isEmpty())
                    <div class="alert alert-warning mb-0">No payment modes yet. Add one under <a href="{{ route('admin.payment-modes.index') }}">Payment Modes</a> first.</div>
                @else
                    {{-- Pay now / Promise to pay toggle --}}
                    <div class="btn-group w-100 mb-3" role="group">
                        <input type="radio" class="btn-check" name="collect_mode" id="modePay" value="pay" checked onchange="setCollectMode('pay')">
                        <label class="btn btn-outline-primary" for="modePay"><i class="fa-solid fa-indian-rupee-sign me-1"></i> Pay now</label>
                        <input type="radio" class="btn-check" name="collect_mode" id="modePromise" value="promise" onchange="setCollectMode('promise')">
                        <label class="btn btn-outline-primary" for="modePromise"><i class="fa-regular fa-calendar-check me-1"></i> Promise to pay</label>
                    </div>

                    {{-- Pay fields --}}
                    <div id="payFields">
                        <div class="mb-3">
                            <label class="form-label">Amount (₹)</label>
                            <input type="number" step="0.01" min="1" name="amount" id="collectAmount" class="form-control" required>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">Payment Type</label>
                                <select name="payment_type" class="form-select">@foreach(config('hsms.payment_types') as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
                            <div class="col-md-6"><label class="form-label">Mode</label>
                                <select name="mode" class="form-select" required>@foreach($paymentModes as $m)<option value="{{ $m->code }}">{{ $m->name }}</option>@endforeach</select></div>
                            <div class="col-md-6"><label class="form-label">Date</label>
                                <input type="date" name="paid_on" class="form-control" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required></div>
                            <div class="col-md-6"><label class="form-label">Reference (optional)</label>
                                <input type="text" name="reference_number" class="form-control"></div>
                            <div class="col-12"><label class="form-label">Remarks (optional)</label>
                                <input type="text" name="remarks" class="form-control"></div>
                        </div>
                    </div>

                    {{-- Promise fields --}}
                    <div id="promiseFields" class="d-none">
                        <div class="alert alert-info py-2 small mb-3"><i class="fa-solid fa-circle-info me-1"></i> No money is taken now — this records the date the student promised to clear the outstanding.</div>
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
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                @unless($paymentModes->isEmpty())<button type="submit" class="btn btn-primary" id="collectSubmit">Collect</button>@endunless
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

        // Disabled inputs are skipped by both HTML5 validation and form submit,
        // so the inactive section never blocks or pollutes the request.
        pay.querySelectorAll('input,select').forEach(el => el.disabled = promising);
        promise.querySelectorAll('input').forEach(el => el.disabled = !promising);

        submit.textContent = promising ? 'Save Promise' : 'Collect';
        submit.classList.toggle('btn-primary', !promising);
        submit.classList.toggle('btn-warning', promising);
    }
</script>
@endpush

{{-- Documents --}}
<div class="card stat-card mt-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h6 fw-bold mb-0">Documents</h2>
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#docModal">
                <i class="fa-solid fa-upload me-1"></i> Upload
            </button>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Type</th><th>Title</th><th>Expiry</th><th>Signed</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                @forelse($student->documents as $doc)
                    <tr>
                        <td><span class="badge bg-primary-subtle text-primary">{{ ucfirst($doc->type) }}</span></td>
                        <td>{{ $doc->title }}</td>
                        <td>{{ optional($doc->expiry_date)->format('d-m-Y') ?? '—' }}</td>
                        <td>{!! $doc->is_signed ? '<i class="fa-solid fa-check text-success"></i>' : '—' !!}</td>
                        <td class="text-end">
                            <a href="{{ Storage::disk('public')->url($doc->file_path) }}" target="_blank" class="btn btn-sm btn-light"><i class="fa-solid fa-eye"></i></a>
                            <form action="{{ route('admin.students.documents.destroy', [$student, $doc]) }}" method="POST" class="d-inline" data-confirm="Delete this document?">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-light text-danger"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-3">No documents uploaded.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Bed history --}}
<div class="card stat-card mt-3">
    <div class="card-body">
        <h2 class="h6 fw-bold mb-3">Bed History</h2>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Room / Bed</th><th>Join</th><th>Leave</th><th>Fee</th><th>Duration</th><th>Status</th></tr></thead>
                <tbody>
                @forelse($student->assignments as $a)
                    <tr>
                        <td>{{ $a->bed->room->room_number }} / {{ $a->bed->bed_number }}</td>
                        <td>{{ $a->join_date->format('d-m-Y') }}</td>
                        <td>{{ optional($a->leave_date)->format('d-m-Y') ?? '—' }}</td>
                        <td>{{ hsms_money($a->fee_amount) }} <small class="text-muted">/ {{ $a->feeFrequencyLabel() }}</small></td>
                        <td>{{ $a->durationInDays() }} days</td>
                        <td><span class="badge bg-{{ $a->is_active ? 'success' : 'secondary' }}-subtle text-{{ $a->is_active ? 'success' : 'secondary' }}">{{ $a->is_active ? 'Active' : 'Past' }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-3">No bed assignments yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Upload modal --}}
<div class="modal fade" id="docModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="POST" action="{{ route('admin.students.documents.store', $student) }}" enctype="multipart/form-data">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Upload Document</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
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
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Upload</button>
            </div>
        </form>
    </div>
</div>
@endsection
