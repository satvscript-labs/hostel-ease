{{-- Reusable "collect payment against an obligation" modal. --}}
<div class="modal fade" id="collectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow-lg" id="collectForm" method="POST" x-data="collectPaymentModal()">
            @csrf
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold d-flex align-items-center">
                    <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                        <i class="fa-solid fa-indian-rupee-sign"></i>
                    </div>
                    <div>
                        Collect Payment
                        <div class="fs-6 fw-normal text-muted mt-1" id="collectLabel"></div>
                    </div>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body pt-4">
                <div class="bg-light rounded-4 p-3 mb-4 d-flex justify-content-between align-items-center border border-primary-subtle border-opacity-25">
                    <div>
                        <div class="text-muted small fw-bold text-uppercase letter-spacing-1 mb-1">Outstanding Balance</div>
                        <div class="fs-4 fw-bold text-dark" id="collectBalanceDisplay">₹0.00</div>
                    </div>
                    <div class="text-end" x-show="creditBalance > 0" x-cloak>
                        <div class="text-muted small fw-bold text-uppercase letter-spacing-1 mb-1">Available Credit</div>
                        <div class="fs-5 fw-bold text-success" x-text="'₹' + creditBalance.toFixed(2)"></div>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold text-dark mb-2">Total Amount to Pay (₹)</label>
                    <div class="input-group input-group-lg border rounded-3 overflow-hidden bg-white" :class="{'border-primary shadow-sm': isFocused}">
                        <span class="input-group-text bg-white border-0 text-muted px-3"><i class="fa-solid fa-indian-rupee-sign"></i></span>
                        <input type="number" step="0.01" min="0.01" class="form-control border-0 ps-1 fw-bold fs-5 bg-white" 
                               x-model.number="totalPayment" 
                               @focus="isFocused = true" @blur="isFocused = false" required>
                    </div>
                    <div class="form-text text-muted small mt-2"><i class="fa-solid fa-circle-info me-1"></i> Enter the total amount you want to settle against this balance.</div>
                </div>

                <!-- Payment Breakdown Section -->
                <div class="bg-primary-subtle bg-opacity-10 border border-primary-subtle rounded-4 p-3 p-md-4 mb-4 position-relative">
                    
                    <h6 class="fw-bold mb-3 text-primary d-flex align-items-center">
                        <div class="bg-primary bg-opacity-10 rounded-circle p-2 me-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                            <i class="fa-solid fa-chart-pie"></i>
                        </div>
                        Payment Breakdown
                    </h6>
                    
                    <div class="row g-3 position-relative z-1">
                        <!-- Credit Input -->
                        <div class="col-12" x-show="creditBalance > 0">
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

                        <!-- Cash/Online Amount (Readonly UI + Hidden Input) -->
                        <div class="col-12">
                            <label class="form-label fw-semibold small text-muted">Pay via Cash/Online (₹)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white text-dark"><i class="fa-solid fa-money-bill-wave"></i></span>
                                <input type="text" class="form-control fw-bold bg-white" :value="cashAmount.toFixed(2)" readonly>
                            </div>
                            <!-- This is the actual amount sent to backend -->
                            <input type="hidden" name="amount" :value="cashAmount">
                        </div>
                    </div>
                </div>

                <!-- Payment Details (Only show if Cash Amount > 0) -->
                <div x-show="cashAmount > 0" x-transition.duration.300ms class="row g-3 mb-4">
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-bold small">Payment Mode</label>
                        <select name="mode" class="form-select bg-light" x-model="selectedMode" @change="checkReference">
                            @foreach(\App\Models\PaymentMode::active()->get() as $m)
                                <option value="{{ $m->code }}" data-req="{{ $m->requires_reference ? 1 : 0 }}">{{ $m->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-bold small">Payment Date</label>
                        <input type="date" name="paid_on" class="form-control bg-light" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required>
                    </div>
                    <div class="col-12" x-show="requiresReference" x-transition>
                        <label class="form-label fw-bold small">Reference / Transaction ID <span class="text-danger">*</span></label>
                        <input type="text" name="reference_number" x-ref="refInput" class="form-control bg-light" placeholder="e.g. UPI Txn ID, Check No.">
                    </div>
                </div>
                
                <!-- Fallback mode when 100% credit -->
                <template x-if="cashAmount <= 0">
                    <input type="hidden" name="mode" value="{{ \App\Models\PaymentMode::active()->first()?->code ?? 'cash' }}">
                </template>
                <template x-if="cashAmount <= 0">
                    <input type="hidden" name="paid_on" value="{{ now()->toDateString() }}">
                </template>

                <div class="mb-2">
                    <label class="form-label fw-bold small">Remarks (Optional)</label>
                    <input type="text" name="remarks" class="form-control bg-light" placeholder="Any note about this payment">
                </div>
            </div>
            
            <div class="modal-footer border-top-0 pt-0">
                <button type="button" class="btn btn-light rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold d-flex align-items-center" :disabled="totalPayment <= 0">
                    <i class="fa-solid fa-check-circle me-2"></i> Confirm Payment
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    // Alpine component for the Collect Payment Modal
    document.addEventListener('alpine:init', () => {
        Alpine.data('collectPaymentModal', () => ({
            isFocused: false,
            creditBalance: 0,
            outstandingBalance: 0,
            
            totalPayment: 0,
            creditUsed: 0,
            
            selectedMode: '{{ \App\Models\PaymentMode::active()->first()?->code ?? 'cash' }}',
            requiresReference: false,

            init() {
                // Determine reference requirement on load
                this.checkReference();
                
                // Watch for changes in total payment to adjust credit if it exceeds
                this.$watch('totalPayment', value => {
                    let val = Number(value) || 0;
                    if (this.creditUsed > val) {
                        this.creditUsed = val;
                    }
                });
            },

            get maxCreditAllowed() {
                // Can't use more credit than we have, and can't use more than the total payment
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
                // Find the selected option to see if it needs a reference
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

    // Global function to trigger the modal from anywhere
    window.prepCollect = function(action, label, balance, credit = 0) {
        const form = document.getElementById('collectForm');
        form.action = action;
        
        document.getElementById('collectLabel').textContent = label;
        document.getElementById('collectBalanceDisplay').textContent = '₹' + Number(balance).toFixed(2);
        
        // Find the Alpine component instance and update its state
        const alpineData = Alpine.$data(form);
        alpineData.outstandingBalance = Number(balance);
        alpineData.creditBalance = Number(credit);
        
        // Auto-fill total payment with outstanding balance
        alpineData.totalPayment = alpineData.outstandingBalance > 0 ? alpineData.outstandingBalance : 0;
        
        // Auto-fill credit if available (use max possible)
        alpineData.$nextTick(() => {
            if (alpineData.creditBalance > 0) {
                alpineData.useMaxCredit();
            } else {
                alpineData.creditUsed = 0;
            }
        });
        
        bootstrap.Modal.getOrCreateInstance(document.getElementById('collectModal')).show();
    };
</script>
@endpush

