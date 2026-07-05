@extends('layouts.app')
@section('title', 'Subscriptions')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 fw-bold mb-0">Subscriptions</h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#subModal"><i class="fa-solid fa-plus me-1"></i> Record / Renew</button>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="card stat-card"><div class="card-body py-3"><div class="stat-value text-success">{{ hsms_money($summary['total']) }}</div><div class="stat-label">Collected (paid)</div></div></div></div>
    <div class="col-md-3"><div class="card stat-card"><div class="card-body py-3"><div class="stat-value text-warning">{{ hsms_money($summary['pending']) }}</div><div class="stat-label">Pending</div></div></div></div>
    <div class="col-md-3"><div class="card stat-card"><div class="card-body py-3"><div class="stat-value text-primary">{{ $summary['active_accounts'] }}</div><div class="stat-label">Active accounts</div></div></div></div>
    <div class="col-md-3"><div class="card stat-card"><div class="card-body py-3"><div class="stat-value text-danger">{{ $summary['expired_accounts'] }}</div><div class="stat-label">Expired accounts</div></div></div></div>
</div>

{{-- Per-account billing --}}
<div class="card stat-card mb-4"><div class="card-body">
    <h5 class="fw-bold mb-3">Accounts <span class="text-muted small fw-normal">— one payment covers all of an owner's branches (every 3rd branch free)</span></h5>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr>
                <th>Owner</th><th>Mobile</th><th class="text-center">Branches</th><th class="text-center">Billable</th>
                <th class="text-end">Yearly</th><th class="text-end">Monthly</th><th>Valid until</th><th>Status</th><th></th>
            </tr></thead>
            <tbody>
            @forelse($accounts as $a)
                <tr>
                    <td class="fw-semibold">{{ $a['owner']->name }}</td>
                    <td>{{ $a['owner']->mobile }}</td>
                    <td class="text-center">{{ $a['branches'] }}</td>
                    <td class="text-center">
                        <span class="badge bg-primary-subtle text-primary">{{ $a['payable'] }} billable</span>
                        @if($a['free'] > 0)<span class="badge bg-success-subtle text-success">{{ $a['free'] }} free</span>@endif
                    </td>
                    <td class="text-end">{{ hsms_money($a['yearly']) }}</td>
                    <td class="text-end">{{ hsms_money($a['monthly']) }}</td>
                    <td>{{ $a['end'] ? $a['end']->format('d M Y') : '—' }}</td>
                    <td>
                        @if($a['active'])
                            <span class="badge bg-success">Active</span>
                        @else
                            <span class="badge bg-danger">Expired</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-primary renew-btn"
                                data-owner="{{ $a['owner']->id }}"
                                data-bs-toggle="modal" data-bs-target="#subModal">
                            <i class="fa-solid fa-rotate me-1"></i> Renew
                        </button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center text-muted py-4">No hostel accounts yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div></div>

{{-- Payment history --}}
<div class="card stat-card"><div class="card-body">
    <h5 class="fw-bold mb-3">Payment history</h5>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" data-datatable>
            <thead><tr><th>Date</th><th>Branch</th><th>Plan</th><th>Start</th><th>End</th><th class="text-end">Amount</th><th>Method</th><th>Status</th><th>Txn</th><th></th></tr></thead>
            <tbody>
            @foreach($subscriptions as $s)
                <tr>
                    <td>{{ $s->created_at?->format('d M Y') }}</td>
                    <td class="fw-semibold">{{ $s->hostel->name ?? '—' }}</td>
                    <td>{{ ucfirst($s->plan) }}</td>
                    <td>{{ $s->start_date->format('d M Y') }}</td>
                    <td>{{ $s->end_date->format('d M Y') }}</td>
                    <td class="text-end">{{ hsms_money($s->amount) }}</td>
                    <td>{{ $s->payment_method ? ucfirst($s->payment_method) : '—' }}</td>
                    <td><span class="badge bg-{{ $s->payment_status==='paid'?'success':($s->payment_status==='pending'?'warning text-dark':'danger') }}">{{ ucfirst($s->payment_status) }}</span></td>
                    <td>{{ $s->transaction_number ?? '—' }}</td>
                    <td class="text-end text-nowrap">
                        @if($s->payment_status !== 'paid')
                            <form action="{{ route('superadmin.subscriptions.accept', $s) }}" method="POST" class="d-inline" data-confirm="Accept this payment and extend all branches to {{ $s->end_date->format('d M Y') }}?">
                                @csrf @method('PATCH')<button class="btn btn-sm btn-success" title="Accept payment"><i class="fa-solid fa-check"></i></button>
                            </form>
                        @endif
                        <button class="btn btn-sm btn-light" title="Edit"
                                data-bs-toggle="modal" data-bs-target="#editSubModal"
                                onclick="editSub({{ $s->id }})">
                            <i class="fa-solid fa-pen"></i>
                        </button>
                        <form action="{{ route('superadmin.subscriptions.destroy', $s) }}" method="POST" class="d-inline" data-confirm="Delete this subscription record?">
                            @csrf @method('DELETE')<button class="btn btn-sm btn-light text-danger" title="Delete"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div></div>

{{-- Record / Renew modal (offline) --}}
<div class="modal fade" id="subModal" tabindex="-1"><div class="modal-dialog">
    <form class="modal-content" method="POST" action="{{ route('superadmin.subscriptions.store') }}">@csrf
        <div class="modal-header"><h5 class="modal-title">Record / Renew Subscription</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="mb-3">
                <label class="form-label">Account (owner)</label>
                <select name="owner_id" id="ownerSelect" class="form-select" data-select2 required>
                    <option value="">Select…</option>
                    @foreach($accounts as $a)
                        <option value="{{ $a['owner']->id }}">{{ $a['owner']->name }} — {{ $a['branches'] }} branch(es), {{ $a['payable'] }} billable</option>
                    @endforeach
                </select>
            </div>
            <div class="row g-3">
                <div class="col-6"><label class="form-label">Period</label>
                    <select name="period" id="periodSelect" class="form-select">
                        <option value="yearly">Yearly (₹{{ number_format(config('hsms.subscription_pricing.yearly')) }}/branch)</option>
                        <option value="monthly">Monthly (₹{{ number_format(config('hsms.subscription_pricing.monthly')) }}/branch)</option>
                    </select>
                </div>
                <div class="col-6"><label class="form-label">Amount (₹)</label><input type="number" step="0.01" name="amount" id="amountInput" class="form-control" required>
                    <div class="form-text" id="amountHint">Auto-calculated — you can override.</div>
                </div>
                <div class="col-6"><label class="form-label">Payment Status</label>
                    <select name="payment_status" class="form-select"><option value="paid">Paid</option><option value="pending">Pending</option></select></div>
                <div class="col-6"><label class="form-label">Method</label>
                    <select name="payment_method" class="form-select"><option value="">—</option>@foreach(config('hsms.payment_modes') as $k=>$l)<option value="{{ $k }}">{{ $l }}</option>@endforeach<option value="online">Online</option></select></div>
                <div class="col-12"><label class="form-label">Transaction No. / Remarks</label><input type="text" name="transaction_number" class="form-control mb-2" placeholder="Reference (optional)"><input type="text" name="remarks" class="form-control" placeholder="Remarks (optional)"></div>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
    </form>
</div></div>

{{-- Edit subscription record modal --}}
<div class="modal fade" id="editSubModal" tabindex="-1"><div class="modal-dialog">
    <form class="modal-content" id="editSubForm" method="POST" action="">@csrf @method('PUT')
        <div class="modal-header"><h5 class="modal-title">Edit Subscription Record</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="row g-3">
                <div class="col-6"><label class="form-label">Period</label>
                    <select name="plan" id="e_plan" class="form-select"><option value="yearly">Yearly</option><option value="monthly">Monthly</option></select></div>
                <div class="col-6"><label class="form-label">Amount (₹)</label><input type="number" step="0.01" name="amount" id="e_amount" class="form-control" required></div>
                <div class="col-6"><label class="form-label">Start</label><input type="date" name="start_date" id="e_start" class="form-control" required></div>
                <div class="col-6"><label class="form-label">End</label><input type="date" name="end_date" id="e_end" class="form-control" required></div>
                <div class="col-6"><label class="form-label">Payment Status</label>
                    <select name="payment_status" id="e_status" class="form-select"><option value="paid">Paid</option><option value="pending">Pending</option><option value="failed">Failed</option></select></div>
                <div class="col-6"><label class="form-label">Method</label>
                    <select name="payment_method" id="e_method" class="form-select"><option value="">—</option>@foreach(config('hsms.payment_modes') as $k=>$l)<option value="{{ $k }}">{{ $l }}</option>@endforeach<option value="online">Online</option></select></div>
                <div class="col-12"><label class="form-label">Transaction No.</label><input type="text" name="transaction_number" id="e_txn" class="form-control"></div>
                <div class="col-12"><label class="form-label">Remarks</label><input type="text" name="remarks" id="e_remarks" class="form-control"></div>
            </div>
            <div class="form-text mt-2"><i class="fa-solid fa-circle-info me-1"></i> Saving with status <strong>Paid</strong> extends all of the owner's branches to the End date.</div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save changes</button></div>
    </form>
</div></div>
@endsection

@push('scripts')
<script>
(function () {
    const accounts = @json($accountsJson);
    const ownerSelect = document.getElementById('ownerSelect');
    const periodSelect = document.getElementById('periodSelect');
    const amountInput = document.getElementById('amountInput');
    const amountHint = document.getElementById('amountHint');

    function recalc() {
        const acc = accounts[ownerSelect.value];
        if (!acc) { amountHint.textContent = 'Auto-calculated — you can override.'; return; }
        const amt = periodSelect.value === 'monthly' ? acc.monthly : acc.yearly;
        amountInput.value = amt;
        amountHint.textContent = acc.payable + ' billable branch(es) × unit price = ₹' + Number(amt).toLocaleString('en-IN');
    }

    ownerSelect.addEventListener('change', recalc);
    periodSelect.addEventListener('change', recalc);

    // Prefill owner when the row "Renew" button is used.
    document.querySelectorAll('.renew-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            const id = btn.dataset.owner;
            ownerSelect.value = id;
            if (window.jQuery && window.jQuery(ownerSelect).trigger) window.jQuery(ownerSelect).trigger('change');
            else recalc();
        });
    });
})();

// Populate the edit modal from a subscription row.
const SUBS = @json($subsJson);
const editBase = @json(url('superadmin/subscriptions'));
function editSub(id) {
    const s = SUBS[id];
    if (!s) return;
    document.getElementById('editSubForm').action = editBase + '/' + id;
    document.getElementById('e_plan').value = (s.plan === 'monthly' ? 'monthly' : 'yearly');
    document.getElementById('e_amount').value = s.amount;
    document.getElementById('e_start').value = (s.start_date || '').substring(0, 10);
    document.getElementById('e_end').value = (s.end_date || '').substring(0, 10);
    document.getElementById('e_status').value = s.payment_status || 'pending';
    document.getElementById('e_method').value = s.payment_method || '';
    document.getElementById('e_txn').value = s.transaction_number || '';
    document.getElementById('e_remarks').value = s.remarks || '';
}
</script>
@endpush
