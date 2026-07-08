@extends('layouts.app')
@section('title', __('Security Deposits'))

@section('content')
<div x-data="securityDeposits()" class="page-enter">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="h3 fw-bold mb-0">{{ __('Security Deposits') }}</h1>
            <p class="text-secondary mb-0">{{ __('Manage and refund student security deposits.') }}</p>
        </div>
        <button class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" @click="collectModal = true">
            <i class="fa-solid fa-plus me-1"></i> Collect Deposit
        </button>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4">
                    <div class="text-muted small fw-bold text-uppercase mb-2">Total Collected</div>
                    <div class="h2 fw-bold text-dark mb-0">{{ hostelease_money($deposits->sum('amount')) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4">
                    <div class="text-muted small fw-bold text-uppercase mb-2">Total Refunded</div>
                    <div class="h2 fw-bold text-success mb-0">{{ hostelease_money($deposits->sum('refunded_amount')) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4">
                    <div class="text-muted small fw-bold text-uppercase mb-2">Total Deducted (Dues)</div>
                    <div class="h2 fw-bold text-warning mb-0">{{ hostelease_money($deposits->sum('deducted_amount')) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4 py-3 text-uppercase small text-muted letter-spacing-1 fw-bold border-0 rounded-top-start-4">Student</th>
                            <th class="py-3 text-uppercase small text-muted letter-spacing-1 fw-bold border-0">Receipt</th>
                            <th class="py-3 text-uppercase small text-muted letter-spacing-1 fw-bold border-0">Amount</th>
                            <th class="py-3 text-uppercase small text-muted letter-spacing-1 fw-bold border-0">Status</th>
                            <th class="pe-4 py-3 text-end text-uppercase small text-muted letter-spacing-1 fw-bold border-0 rounded-top-end-4">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($deposits as $deposit)
                        <tr>
                            <td class="ps-4 py-3">
                                <div class="fw-bold text-dark">{{ $deposit->student->name }}</div>
                                <div class="text-muted small">{{ $deposit->student->mobile }}</div>
                            </td>
                            <td class="py-3">
                                <div class="fw-bold">{{ $deposit->receipt_number }}</div>
                                <div class="text-muted small">{{ $deposit->collected_on->format('d M Y') }}</div>
                            </td>
                            <td class="py-3 fw-bold" style="font-feature-settings: 'tnum';">
                                {{ hostelease_money($deposit->amount) }}
                                @if($deposit->status === 'refunded')
                                <div class="small fw-normal text-muted mt-1">
                                    <span class="text-success" title="Refunded to student">R: {{ hostelease_money($deposit->refunded_amount) }}</span> &bull; 
                                    <span class="text-warning" title="Deducted for pending dues">D: {{ hostelease_money($deposit->deducted_amount) }}</span>
                                </div>
                                @endif
                            </td>
                            <td class="py-3">
                                @if($deposit->status === 'collected')
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1">Collected</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-3 py-1">Refunded</span>
                                @endif
                            </td>
                            <td class="pe-4 py-3 text-end">
                                @if($deposit->status === 'collected')
                                <button type="button" class="btn btn-sm btn-light fw-bold text-primary rounded-pill px-3 shadow-sm border" @click="openRefundModal({{ $deposit->id }}, '{{ addslashes($deposit->student->name) }}', {{ $deposit->amount }}, {{ $deposit->student->invoices()->where('status', '!=', 'paid')->sum('balance') }}, {{ $deposit->student->invoices()->where('status', '!=', 'paid')->get()->toJson() }})">
                                    <i class="fa-solid fa-arrow-right-arrow-left me-1"></i> Refund / Deduct
                                </button>
                                @else
                                <span class="text-muted small"><i class="fa-solid fa-check"></i> Processed</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <i class="fa-solid fa-vault text-secondary fs-1 mb-3 opacity-25" style="font-size: 3rem !important;"></i>
                                <h5 class="fw-bold text-dark">No Deposits Found</h5>
                                <p class="text-secondary mb-0">Collect security deposits from students to see them here.</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Collect Deposit Modal -->
    <template x-teleport="body">
        <div class="custom-overlay-backdrop" x-show="collectModal" x-transition.opacity @click="collectModal = false" x-cloak style="display: none;">
            <form method="POST" action="{{ route('admin.security-deposits.store') }}" class="custom-overlay-modal" :class="{ 'is-open': collectModal }" x-show="collectModal" x-transition.opacity @click.stop style="display: none;">
                @csrf
                <div class="custom-overlay-header">
                    <h5 class="fw-bold mb-0"><i class="fa-solid fa-plus text-primary me-2"></i> Collect Deposit</h5>
                    <button type="button" class="btn-close" @click="collectModal = false"></button>
                </div>
                <div class="custom-overlay-body">
                    <div class="mb-4">
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1">Student <span class="text-danger">*</span></label>
                        <select name="student_id" class="form-select bg-light fw-semibold" required>
                            <option value="">— Select Student —</option>
                            @foreach($students as $student)
                            <option value="{{ $student->id }}">{{ $student->name }} ({{ $student->mobile }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1">Amount <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted fw-bold">₹</span>
                            <input type="number" name="amount" class="form-control bg-light fw-bold text-dark fs-5" min="1" step="0.01" required>
                        </div>
                    </div>
                    <div class="row gx-3">
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">Payment Mode <span class="text-danger">*</span></label>
                            <select name="payment_mode_id" class="form-select bg-light fw-semibold" required>
                                @foreach($paymentModes as $mode)
                                <option value="{{ $mode->id }}">{{ $mode->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">Collection Date <span class="text-danger">*</span></label>
                            <input type="date" name="collected_on" class="form-control bg-light" value="{{ date('Y-m-d') }}" required>
                        </div>
                    </div>
                </div>
                <div class="custom-overlay-footer bg-light">
                    <button type="button" class="btn btn-white border fw-semibold rounded-pill px-4 tactile-btn" @click="collectModal = false">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-semibold rounded-pill px-4 shadow-sm tactile-btn">Save Record</button>
                </div>
            </form>
        </div>
    </template>

    <!-- Refund/Deduct Modal -->
    <template x-teleport="body">
        <div class="custom-overlay-backdrop" x-show="refundModal" x-transition.opacity @click="refundModal = false" x-cloak style="display: none;">
            <form method="POST" :action="'{{ url('admin/security-deposits') }}/' + activeDepositId + '/refund'" class="custom-overlay-modal" :class="{ 'is-open': refundModal }" x-show="refundModal" x-transition.opacity @click.stop style="display: none;">
                @csrf
                <div class="custom-overlay-header bg-warning-subtle border-bottom border-warning-subtle">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-arrow-right-arrow-left text-warning me-2"></i> Process Refund</h5>
                    <button type="button" class="btn-close" @click="refundModal = false"></button>
                </div>
                <div class="custom-overlay-body pt-4">
                    <div class="d-flex justify-content-between align-items-center mb-4 p-3 bg-light rounded-3 border">
                        <div>
                            <div class="text-muted small fw-bold text-uppercase mb-1">Student</div>
                            <div class="fw-bold text-dark" x-text="activeStudentName"></div>
                        </div>
                        <div class="text-end">
                            <div class="text-muted small fw-bold text-uppercase mb-1">Deposit Amount</div>
                            <div class="h4 fw-bold text-dark mb-0" x-text="'₹' + parseFloat(activeDepositAmount).toFixed(2)"></div>
                        </div>
                    </div>

                    <div x-show="activePendingDues > 0" class="alert alert-danger border-0 rounded-3 mb-4">
                        <div class="d-flex gap-3 align-items-start">
                            <i class="fa-solid fa-triangle-exclamation fs-5 mt-1"></i>
                            <div>
                                <h6 class="fw-bold mb-1">Pending Dues Detected!</h6>
                                <p class="small mb-0">This student has <strong>₹<span x-text="parseFloat(activePendingDues).toFixed(2)"></span></strong> in unpaid invoices. You can deduct this amount directly from their security deposit.</p>
                            </div>
                        </div>
                        
                        <div class="mt-3 bg-white bg-opacity-50 p-2 rounded">
                            <div class="small fw-bold text-uppercase mb-2 text-dark">Select invoices to deduct:</div>
                            <template x-for="invoice in activeInvoices" :key="invoice.id">
                                <label class="d-flex justify-content-between align-items-center p-2 border-bottom cursor-pointer">
                                    <div class="d-flex align-items-center gap-2">
                                        <input type="checkbox" name="deduct_invoice_ids[]" :value="invoice.id" class="form-check-input mt-0" @change="calculateRefund()">
                                        <span class="small fw-semibold text-dark" x-text="invoice.title"></span>
                                    </div>
                                    <span class="small fw-bold text-danger" x-text="'₹' + parseFloat(invoice.balance).toFixed(2)"></span>
                                </label>
                            </template>
                        </div>
                    </div>

                    <div class="row gx-3">
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">Amount to Deduct</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted fw-bold">₹</span>
                                <input type="number" name="deducted_amount" x-model="deductedAmount" class="form-control bg-light fw-bold text-danger fs-5" min="0" :max="activeDepositAmount" step="0.01" @input="calculateRefund(true)" required>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">Amount to Refund (Give back)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted fw-bold">₹</span>
                                <input type="number" name="refunded_amount" x-model="refundedAmount" class="form-control bg-light fw-bold text-success fs-5" min="0" :max="activeDepositAmount" step="0.01" readonly required>
                            </div>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1">Refund Note (Optional)</label>
                        <input type="text" name="refund_note" class="form-control bg-light" placeholder="e.g. Returned via UPI, or reasons for deductions">
                    </div>
                </div>
                <div class="custom-overlay-footer bg-light d-flex justify-content-between align-items-center">
                    <div class="small fw-bold text-muted">
                        Total must equal <span x-text="'₹' + parseFloat(activeDepositAmount).toFixed(2)"></span>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-white border fw-semibold rounded-pill px-4 tactile-btn" @click="refundModal = false">Cancel</button>
                        <button type="submit" class="btn btn-warning fw-bold text-dark rounded-pill px-4 shadow-sm tactile-btn" :disabled="parseFloat(deductedAmount) + parseFloat(refundedAmount) > parseFloat(activeDepositAmount) + 0.01 || parseFloat(deductedAmount) + parseFloat(refundedAmount) < parseFloat(activeDepositAmount) - 0.01"><i class="fa-solid fa-check-double me-2"></i> Process Refund</button>
                    </div>
                </div>
            </form>
        </div>
    </template>
</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('securityDeposits', () => ({
        collectModal: false,
        refundModal: false,
        
        activeDepositId: null,
        activeStudentName: '',
        activeDepositAmount: 0,
        activePendingDues: 0,
        activeInvoices: [],
        
        deductedAmount: 0,
        refundedAmount: 0,
        
        openRefundModal(id, studentName, amount, pendingDues, invoices) {
            this.activeDepositId = id;
            this.activeStudentName = studentName;
            this.activeDepositAmount = parseFloat(amount).toFixed(2);
            this.activePendingDues = parseFloat(pendingDues).toFixed(2);
            this.activeInvoices = invoices;
            
            this.deductedAmount = 0;
            this.refundedAmount = this.activeDepositAmount;
            
            this.refundModal = true;
        },
        
        calculateRefund(manualInput = false) {
            if (!manualInput) {
                // Calculate based on checked invoices
                let deduction = 0;
                const checkboxes = document.querySelectorAll('input[name="deduct_invoice_ids[]"]:checked');
                checkboxes.forEach(cb => {
                    const invoice = this.activeInvoices.find(i => i.id == cb.value);
                    if (invoice) deduction += parseFloat(invoice.balance);
                });
                
                // Don't deduct more than the deposit amount
                this.deductedAmount = Math.min(deduction, this.activeDepositAmount).toFixed(2);
            }
            
            // Refund is the remainder
            let refund = parseFloat(this.activeDepositAmount) - parseFloat(this.deductedAmount);
            // Ensure no negative refund due to bad input
            if (refund < 0) {
                this.deductedAmount = this.activeDepositAmount;
                refund = 0;
            }
            this.refundedAmount = refund.toFixed(2);
        }
    }));
});
</script>
@endpush
@endsection
