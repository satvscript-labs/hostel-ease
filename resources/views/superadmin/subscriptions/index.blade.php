@extends('layouts.app')
@section('title', 'Subscriptions & Billing')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1 text-dark tracking-tight">Subscriptions &amp; Billing</h1>
        <p class="text-muted mb-0 small">Manage SaaS renewals, track revenue, and issue payment bypasses.</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-primary shadow-sm rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#subModal">
            <i class="fa-solid fa-plus me-2"></i> Record / Renew
        </button>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="card stat-card"><div class="card-body py-3"><div class="stat-value text-success">{{ hostelease_money($summary['total']) }}</div><div class="stat-label">Collected (paid)</div></div></div></div>
    <div class="col-md-3"><div class="card stat-card"><div class="card-body py-3"><div class="stat-value text-warning">{{ hostelease_money($summary['pending']) }}</div><div class="stat-label">Pending</div></div></div></div>
    <div class="col-md-3"><div class="card stat-card"><div class="card-body py-3"><div class="stat-value text-primary">{{ $summary['active_accounts'] }}</div><div class="stat-label">Active accounts</div></div></div></div>
    <div class="col-md-3"><div class="card stat-card"><div class="card-body py-3"><div class="stat-value text-danger">{{ $summary['expired_accounts'] }}</div><div class="stat-label">Expired accounts</div></div></div></div>
</div>

{{-- Per-account billing --}}
<div class="card stat-card border-0 shadow-sm rounded-4 mb-4 overflow-hidden"><div class="card-body p-0">
    <div class="p-4 border-bottom bg-light bg-opacity-50">
        <h5 class="fw-bold mb-1 text-dark">Tenant Accounts</h5>
        <div class="text-muted small">One payment covers all of an owner's branches (every 3rd branch free).</div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.5px;"><tr>
                <th class="py-3 px-4 border-0">Owner / Account</th>
                <th class="py-3 px-4 border-0 text-center">Branches</th>
                <th class="py-3 px-4 border-0 text-center">Billable</th>
                <th class="py-3 px-4 border-0 text-end">Yearly Rate</th>
                <th class="py-3 px-4 border-0">Valid until</th>
                <th class="py-3 px-4 border-0">Status</th>
                <th class="py-3 px-4 border-0"></th>
            </tr></thead>
            <tbody class="border-top-0">
            @forelse($accounts as $a)
                <tr>
                    <td class="px-4 py-3">
                        <div class="fw-bold text-dark fs-6">{{ $a['owner']->name }}</div>
                        <div class="small text-muted"><i class="fa-solid fa-mobile-screen text-primary me-1"></i> {{ $a['owner']->mobile }}</div>
                    </td>
                    <td class="px-4 py-3 text-center fw-semibold fs-6">{{ $a['branches'] }}</td>
                    <td class="px-4 py-3 text-center">
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2">{{ $a['payable'] }} billable</span>
                        @if($a['free'] > 0)<span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 ms-1">{{ $a['free'] }} free</span>@endif
                    </td>
                    <td class="px-4 py-3 text-end fw-bold text-dark">{{ hostelease_money($a['yearly']) }}</td>
                    <td class="px-4 py-3 fw-medium">{{ $a['end'] ? $a['end']->format('d M Y') : '—' }}</td>
                    <td class="px-4 py-3">
                        @if($a['active'])
                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3"><i class="fa-solid fa-circle me-1" style="font-size: 0.5rem; vertical-align: middle;"></i> Active</span>
                        @else
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-3">Expired</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-end">
                        <button class="btn btn-sm btn-light text-primary rounded-pill fw-semibold px-3 renew-btn shadow-sm"
                                data-owner="{{ $a['owner']->id }}"
                                data-bs-toggle="modal" data-bs-target="#subModal">
                            Renew
                        </button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-5">No hostel accounts yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div></div>

{{-- Payment history (Stripe-like UI) --}}
<div class="card stat-card border-0 shadow-sm rounded-4 overflow-hidden mb-4"><div class="card-body p-0">
    <div class="p-4 border-bottom bg-light bg-opacity-50">
        <h5 class="fw-bold mb-0 text-dark">Payment History</h5>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.5px;"><tr>
                <th class="py-3 px-4 border-0">Amount</th>
                <th class="py-3 px-4 border-0">Status</th>
                <th class="py-3 px-4 border-0">Branch / Plan</th>
                <th class="py-3 px-4 border-0">Period</th>
                <th class="py-3 px-4 border-0">Method & Txn</th>
                <th class="py-3 px-4 border-0 text-end"></th>
            </tr></thead>
            <tbody class="border-top-0">
            @foreach($subscriptions as $s)
                <tr>
                    <td class="px-4 py-3">
                        <div class="fw-bold fs-5 {{ $s->amount == 0 ? 'text-secondary' : 'text-dark' }}">{{ hostelease_money($s->amount) }}</div>
                        <div class="small text-muted">{{ $s->created_at?->format('d M Y') }}</div>
                    </td>
                    <td class="px-4 py-3">
                        @if($s->payment_status === 'paid')
                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3">Paid</span>
                        @elseif($s->payment_status === 'pending')
                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-3">Pending</span>
                        @else
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-3">{{ ucfirst($s->payment_status) }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <div class="fw-bold text-dark">{{ $s->hostel->name ?? '—' }}</div>
                        <div class="small text-muted">{{ ucfirst($s->plan) }} Plan</div>
                    </td>
                    <td class="px-4 py-3 fw-medium">
                        {{ $s->start_date->format('d M Y') }} <i class="fa-solid fa-arrow-right mx-1 text-muted small"></i> {{ $s->end_date->format('d M Y') }}
                    </td>
                    <td class="px-4 py-3">
                        <div class="fw-semibold text-dark">{{ $s->payment_method ? ucfirst($s->payment_method) : '—' }}</div>
                        <div class="small text-muted text-truncate" style="max-width: 150px;">{{ $s->transaction_number ?? '—' }}</div>
                    </td>
                    <td class="px-4 py-3 text-end text-nowrap">
                        @if($s->payment_status !== 'paid')
                            <form action="{{ route('superadmin.subscriptions.accept', $s) }}" method="POST" class="d-inline" data-confirm="Accept this payment and extend all branches to {{ $s->end_date->format('d M Y') }}?">
                                @csrf @method('PATCH')<button class="btn btn-sm btn-success rounded-circle shadow-sm" style="width: 32px; height: 32px;" title="Mark Paid"><i class="fa-solid fa-check"></i></button>
                            </form>
                        @endif
                        <button class="btn btn-sm btn-light rounded-circle shadow-sm mx-1" style="width: 32px; height: 32px;" title="Edit"
                                data-bs-toggle="modal" data-bs-target="#editSubModal"
                                onclick="editSub({{ $s->id }})">
                            <i class="fa-solid fa-pen text-primary"></i>
                        </button>
                        <form action="{{ route('superadmin.subscriptions.destroy', $s) }}" method="POST" class="d-inline" data-confirm="Delete this subscription record?">
                            @csrf @method('DELETE')<button class="btn btn-sm btn-light rounded-circle shadow-sm" style="width: 32px; height: 32px;" title="Delete"><i class="fa-solid fa-trash text-danger"></i></button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @if($subscriptions->hasPages())
        <div class="p-3 border-top">
            {{ $subscriptions->links() }}
        </div>
    @endif
</div></div>

{{-- Record / Renew modal (Premium & Flexible) --}}
<div class="modal fade" id="subModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form class="modal-content border-0 shadow-lg rounded-4 overflow-hidden" method="POST" action="{{ route('superadmin.subscriptions.store') }}">
            @csrf
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold text-dark">Record / Renew Subscription</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-3">
                <div class="card border-0 bg-light rounded-4 mb-4">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label fw-bold text-dark mb-0">Select Tenant Account</label>
                            <span class="badge bg-primary-subtle text-primary rounded-pill px-3" id="tenantInfoBadge">Select an account</span>
                        </div>
                        <select name="owner_id" id="ownerSelect" class="form-select form-select-lg border-0 shadow-sm" data-select2 required>
                            <option value="">Search or select account…</option>
                            @foreach($accounts as $a)
                                <option value="{{ $a['owner']->id }}">{{ $a['owner']->name }} — {{ $a['branches'] }} branch(es), {{ $a['payable'] }} billable</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="row g-4 mb-2">
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-dark">Subscription Plan</label>
                        <select name="period" id="periodSelect" class="form-select bg-light">
                            <option value="yearly">Yearly (₹{{ number_format(config('hostelease.subscription_pricing.yearly')) }}/branch)</option>
                            <option value="monthly">Monthly (₹{{ number_format(config('hostelease.subscription_pricing.monthly')) }}/branch)</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-dark d-flex justify-content-between">
                            Amount (₹)
                            <span class="text-primary fw-medium small" id="autoCalcLabel" style="cursor: pointer;"><i class="fa-solid fa-rotate-left"></i> Auto-calc</span>
                        </label>
                        <input type="number" step="0.01" name="amount" id="amountInput" class="form-control fw-bold text-dark fs-5 bg-light" required>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-dark">Payment Status</label>
                        <select name="payment_status" id="statusSelect" class="form-select bg-light">
                            <option value="paid">Paid</option>
                            <option value="pending">Pending</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-dark">Payment Method</label>
                        <select name="payment_method" id="methodSelect" class="form-select bg-light">
                            <option value="">— Select Method —</option>
                            @foreach(config('hostelease.payment_modes') as $k=>$l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                            <option value="online">Online</option>
                            <option value="comp" class="text-primary fw-bold">Comp / Bypassed (Free)</option>
                        </select>
                    </div>
                    
                    <div class="col-12">
                        <label class="form-label fw-bold text-dark">Remarks / Txn No.</label>
                        <div class="row g-2">
                            <div class="col-md-5"><input type="text" name="transaction_number" class="form-control bg-light" placeholder="Transaction Ref"></div>
                            <div class="col-md-7"><input type="text" name="remarks" id="remarksInput" class="form-control bg-light" placeholder="Optional remarks"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top-0 pt-0">
                <button type="button" class="btn btn-light rounded-pill px-4 fw-medium" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary rounded-pill px-4 fw-medium shadow-sm">Save Record</button>
            </div>
        </form>
    </div>
</div>

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
                    <select name="payment_method" id="e_method" class="form-select"><option value="">—</option>@foreach(config('hostelease.payment_modes') as $k=>$l)<option value="{{ $k }}">{{ $l }}</option>@endforeach<option value="online">Online</option></select></div>
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
    const statusSelect = document.getElementById('statusSelect');
    const methodSelect = document.getElementById('methodSelect');
    const remarksInput = document.getElementById('remarksInput');

    const tenantInfoBadge = document.getElementById('tenantInfoBadge');
    const autoCalcLabel = document.getElementById('autoCalcLabel');

    function recalc() {
        const acc = accounts[ownerSelect.value];
        if (!acc) {
            tenantInfoBadge.innerHTML = 'Select an account';
            tenantInfoBadge.className = 'badge bg-primary-subtle text-primary rounded-pill px-3';
            return;
        }
        
        tenantInfoBadge.innerHTML = `<i class="fa-solid fa-building me-1"></i> ${acc.branches} Branch(es) — ${acc.payable} Billable`;
        tenantInfoBadge.className = 'badge bg-success text-white rounded-pill px-3 shadow-sm';
        
        const amt = periodSelect.value === 'monthly' ? acc.monthly : acc.yearly;
        amountInput.value = amt;
    }

    ownerSelect.addEventListener('change', recalc);
    periodSelect.addEventListener('change', recalc);
    autoCalcLabel.addEventListener('click', recalc);

    // Watch method select for "Comp"
    methodSelect.addEventListener('change', function() {
        if (this.value === 'comp') {
            amountInput.value = 0;
            statusSelect.value = 'paid';
            if(!remarksInput.value) remarksInput.value = 'Comp / Payment bypassed by Superadmin';
        }
    });

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

