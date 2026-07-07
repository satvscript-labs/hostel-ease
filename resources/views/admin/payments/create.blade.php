@extends('layouts.app')
@section('title', 'Record Payment')

@section('content')
<div class="d-flex align-items-center gap-2 mb-3">
    <a href="{{ route('admin.payments.index') }}" class="btn btn-light btn-sm"><i class="fa-solid fa-arrow-left"></i></a>
    <h1 class="h4 fw-bold mb-0">Record Payment</h1>
</div>

@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

@if($students->isEmpty())
    <div class="alert alert-warning">No active students. <a href="{{ route('admin.students.create') }}">Add a student</a> first.</div>
@else
<div class="card stat-card">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.payments.store') }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Student <span class="text-danger">*</span></label>
                    <select name="student_id" id="studentSelect" class="form-select" data-select2 required>
                        <option value="">Select student…</option>
                        @foreach($students as $s)
                            <option value="{{ $s->id }}" @selected(old('student_id', $selected) == $s->id)>{{ $s->name }} ({{ hostelease_phone($s->mobile) }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Pay towards</label>
                    <select name="payable" id="payableSelect" class="form-select">
                        <option value="">General / advance (not tied to a due)</option>
                    </select>
                    <small class="text-muted">Pick a due to settle it — its balance updates automatically.</small>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Amount (₹) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="1" name="amount" id="amountInput" class="form-control" value="{{ old('amount') }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date <span class="text-danger">*</span></label>
                    <input type="date" name="paid_on" class="form-control" value="{{ old('paid_on', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Credit Used</label>
                    <input type="number" step="0.01" min="0" name="credit_used" class="form-control" value="{{ old('credit_used', 0) }}">
                    <div class="form-text">Specify amount to deduct from student's available credit balance.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Mode <span class="text-danger">*</span></label>
                    <select name="mode" id="modeSelect" class="form-select" required>
                        @foreach(\App\Models\PaymentMode::options() as $m)
                            <option value="{{ $m->code }}" data-ref="{{ $m->requires_reference ? 1 : 0 }}" @selected(old('mode') === $m->code)>{{ $m->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4" id="refWrap">
                    <label class="form-label">Reference Number</label>
                    <input type="text" name="reference_number" id="refInput" class="form-control" value="{{ old('reference_number') }}">
                </div>
                <div class="col-12">
                    <label class="form-label">Remarks</label>
                    <input type="text" name="remarks" class="form-control" value="{{ old('remarks') }}">
                </div>
            </div>
            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i> Save & Generate Receipt</button>
                <a href="{{ route('admin.payments.index') }}" class="btn btn-light">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
    // "Pay towards" — populate the due selector from the chosen student and
    // auto-fill the amount with the due's balance.
    (function () {
        const duesMap = @json($duesMap ?? []);
        const preRef = @json($preRef ?? null);
        const studentSel = document.getElementById('studentSelect');
        const payableSel = document.getElementById('payableSelect');
        const amountInput = document.getElementById('amountInput');
        if (!studentSel || !payableSel || !amountInput) return;

        function syncAmount() {
            const opt = payableSel.options[payableSel.selectedIndex];
            const bal = opt && opt.dataset.balance ? parseFloat(opt.dataset.balance) : null;
            if (bal && (!amountInput.value || amountInput.dataset.auto === '1')) {
                amountInput.value = bal;
                amountInput.dataset.auto = '1';
            }
        }

        function populateDues(keepRef) {
            const dues = duesMap[studentSel.value] || [];
            payableSel.innerHTML = '<option value="">General / advance (not tied to a due)</option>';
            dues.forEach(function (d) {
                const o = document.createElement('option');
                o.value = d.ref;
                o.textContent = d.label;
                o.dataset.balance = d.balance;
                payableSel.appendChild(o);
            });
            if (keepRef) payableSel.value = keepRef;
            syncAmount();
        }

        payableSel.addEventListener('change', function () { amountInput.dataset.auto = '1'; syncAmount(); });
        amountInput.addEventListener('input', function () { amountInput.dataset.auto = '0'; });
        studentSel.addEventListener('change', function () { populateDues(null); });
        if (window.jQuery) { window.jQuery(studentSel).on('change', function () { populateDues(null); }); }

        populateDues(preRef);
    })();

    const mode = document.getElementById('modeSelect');
    const refWrap = document.getElementById('refWrap');
    const refInput = document.getElementById('refInput');
    function syncRef() {
        const opt = mode.options[mode.selectedIndex];
        const needs = opt && opt.dataset.ref === '1';
        refWrap.style.display = needs ? '' : 'none';
        refInput.required = needs;
    }
    mode.addEventListener('change', syncRef);
    syncRef();
</script>
@endpush

