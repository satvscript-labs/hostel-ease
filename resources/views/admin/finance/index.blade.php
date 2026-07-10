@extends('layouts.app')

@section('title', __('Finance Board'))

@section('content')
<div x-data="financeBoard()" @tab-changed.window="switchTab($event.detail, false)" class="page-enter">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 fw-bold">{{ __('Finance Board') }}</h1>
            <p class="text-secondary">{{ __('Manage invoices, due balances, and transactions in one place.') }}</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-light rounded-pill px-4 fw-bold shadow-sm text-primary border" @click="invoiceType = 'fee'; chargeModalOpen = true">
                <i class="fa-solid fa-wand-magic-sparkles me-1"></i> {{ __('Generate Fee') }}
            </button>
            <button type="button" class="btn rounded-pill px-4 fw-bold shadow-sm" style="background: var(--he-primary); color: #fff;" @click="invoiceType = 'other'; chargeModalOpen = true">
                <i class="fa-solid fa-plus me-1"></i> {{ __('New Invoice') }}
            </button>
        </div>
    </div>

    <!-- Bento Stats -->
    <div class="row g-4 mb-4">
        <!-- Total Outstanding (Hero Mesh) -->
        <div class="col-md-4">
            <div class="card h-100 border-0" style="background: var(--he-gradient-mesh); color: #fff; overflow: hidden; position: relative; border-radius: 1.25rem;">
                <div style="position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: radial-gradient(circle at 50% 50%, rgba(147, 51, 234, 0.3) 0%, transparent 50%); opacity: 0.5;"></div>
                <div class="card-body p-4 position-relative z-1 d-flex flex-column justify-content-between">
                    <div>
                        <div class="badge bg-white text-dark mb-3" style="background: rgba(255,255,255,0.1) !important; backdrop-filter: blur(4px); color: #fff !important; border: 1px solid rgba(255,255,255,0.2);">
                            <i class="fa-solid fa-triangle-exclamation text-warning me-1"></i> Total Outstanding
                        </div>
                        <h2 class="display-6 fw-bold mb-0 text-white" style="font-feature-settings: 'tnum';">{{ hostelease_money($invoices->sum('balance')) }}</h2>
                    </div>
                </div>
            </div>
        </div>
        <!-- Total Collected (Glass Tile) -->
        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm" style="border-radius: 1.25rem; background: #fff;">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="text-secondary small fw-bold text-uppercase" style="letter-spacing: 1px;">Total Collected</div>
                        <div class="tile-icon-wrapper" style="width: 40px; height: 40px; border-radius: 50%; background: var(--he-success-soft); color: var(--he-success); display: flex; align-items: center; justify-content: center; position: relative;">
                            <i class="fa-solid fa-sack-dollar"></i>
                            <div style="position: absolute; inset: 0; background: inherit; filter: blur(8px); z-index: -1;"></div>
                        </div>
                    </div>
                    <div class="h2 mb-0 fw-bold text-success" style="font-feature-settings: 'tnum';">{{ hostelease_money($invoices->sum('paid_amount')) }}</div>
                </div>
            </div>
        </div>
        <!-- Total Invoices (Glass Tile) -->
        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm" style="border-radius: 1.25rem; background: #fff;">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="text-secondary small fw-bold text-uppercase" style="letter-spacing: 1px;">Total Invoices</div>
                        <div class="tile-icon-wrapper" style="width: 40px; height: 40px; border-radius: 50%; background: var(--he-info-soft); color: var(--he-info); display: flex; align-items: center; justify-content: center; position: relative;">
                            <i class="fa-solid fa-file-invoice-dollar"></i>
                            <div style="position: absolute; inset: 0; background: inherit; filter: blur(8px); z-index: -1;"></div>
                        </div>
                    </div>
                    <div class="h2 mb-0 fw-bold text-dark" style="font-feature-settings: 'tnum';">{{ hostelease_money($invoices->sum('amount')) }}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="he-tabs mb-4 border-bottom">
        <button class="he-tab bg-transparent border-0 py-3 px-4 fw-medium text-secondary position-relative" 
                :class="{ 'text-dark fw-bold': tab === 'invoices' }" @click="switchTab('invoices')">
            <i class="fa-solid fa-file-invoice me-1"></i> {{ __('Invoices & Dues') }}
            <div x-show="tab === 'invoices'" class="position-absolute bottom-0 start-0 w-100" style="height: 3px; background: var(--he-primary); border-radius: 3px 3px 0 0;" x-transition></div>
        </button>
        <button class="he-tab bg-transparent border-0 py-3 px-4 fw-medium text-secondary position-relative" 
                :class="{ 'text-dark fw-bold': tab === 'transactions' }" @click="switchTab('transactions')">
            <i class="fa-solid fa-money-bill-transfer me-1"></i> {{ __('Transactions') }}
            <div x-show="tab === 'transactions'" class="position-absolute bottom-0 start-0 w-100" style="height: 3px; background: var(--he-primary); border-radius: 3px 3px 0 0;" x-transition></div>
        </button>
    </div>

    <!-- Client-Side Live Search & Filter Bar -->
    <div class="mb-4">
        <div class="d-flex flex-wrap gap-2 align-items-center bg-white p-2 rounded-pill shadow-sm border border-light">
            
            <div class="flex-grow-1 position-relative px-2">
                <i class="fa-solid fa-search position-absolute text-muted" style="top: 50%; transform: translateY(-50%); left: 1rem;"></i>
                <input type="text" x-model="search" class="form-control border-0 bg-transparent ps-5 shadow-none" placeholder="Search by student, receipt, or title...">
            </div>

            <div x-show="tab === 'invoices'" x-cloak class="border-start ps-3 me-2">
                <select x-model="status" class="form-select border-0 bg-transparent text-secondary shadow-none" style="cursor: pointer;">
                    <option value="">All Statuses</option>
                    <option value="paid">Paid</option>
                    <option value="pending">Pending</option>
                    <option value="partial">Partial</option>
                </select>
            </div>
            
            <div class="pe-2" x-show="search !== '' || status !== ''" x-cloak>
                <button type="button" @click="search = ''; status = ''" class="btn btn-light rounded-pill btn-sm text-secondary px-3">
                    <i class="fa-solid fa-xmark"></i> Clear
                </button>
            </div>
        </div>
    </div>

    <!-- Invoices Tab -->
    <div x-show="tab === 'invoices'" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-4"
         x-transition:enter-end="opacity-100 translate-y-0"
         style="display: none;">
        <div class="d-flex flex-column gap-3 stagger">
            @forelse($invoices as $index => $invoice)
            <div class="card border-0 shadow-sm rounded-4 invoice-item"
                 x-show="matchesSearchInvoice('{{ addslashes(strtolower($invoice->student->name)) }}', '{{ $invoice->student->mobile }}', '{{ addslashes(strtolower($invoice->title)) }}', '{{ $invoice->status }}')">
                <div class="card-body p-3 p-md-4">
                    <div class="row align-items-center m-0 w-100">
                        <div class="col-12 col-xl-3 d-flex align-items-center gap-3 mb-3 mb-xl-0 p-0">
                            <div class="avatar bg-light text-primary fw-bold rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 48px; height: 48px;">
                                {{ substr($invoice->student->name, 0, 1) }}
                            </div>
                            <div>
                                <div class="fw-bold text-dark lh-1 mb-1">{{ $invoice->student->name }}</div>
                                <div class="text-muted small letter-spacing-1 lh-1">{{ $invoice->student->mobile }}</div>
                            </div>
                        </div>
                        
                        <div class="col-12 col-md-4 col-xl-3 mb-3 mb-md-0 p-0">
                            <div class="text-dark fw-bold lh-1 mb-1">{{ $invoice->title }}</div>
                            <div class="text-muted small letter-spacing-1 text-uppercase lh-1">{{ $invoice->type }} &bull; {{ $invoice->created_at->format('d M Y') }}</div>
                        </div>
                        
                        <div class="col-12 col-md-6 col-xl-4 d-flex gap-4 align-items-center justify-content-md-end mb-3 mb-md-0 p-0" style="font-feature-settings: 'tnum';">
                            <div class="text-start text-md-end">
                                <div class="text-muted small fw-bold text-uppercase letter-spacing-1">Amount</div>
                                <div class="fw-bold">{{ hostelease_money($invoice->amount) }}</div>
                            </div>
                            <div class="text-start text-md-end">
                                <div class="text-muted small fw-bold text-uppercase letter-spacing-1">Paid</div>
                                <div class="text-success fw-bold">{{ hostelease_money($invoice->paid_amount) }}</div>
                            </div>
                            <div class="text-start text-md-end">
                                <div class="text-muted small fw-bold text-uppercase letter-spacing-1">Balance</div>
                                <div class="text-danger fw-bold">{{ hostelease_money($invoice->balance) }}</div>
                            </div>
                        </div>
                        
                        <div class="col-12 col-md-2 col-xl-2 d-flex align-items-center justify-content-md-end gap-3 p-0">
                            @if($invoice->status === 'paid')
                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-2">Paid</span>
                            @elseif($invoice->status === 'partial')
                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-3 py-2">Partial</span>
                            @else
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-3 py-2">Pending</span>
                            @endif
                            
                            <form action="{{ route('admin.invoices.destroy', $invoice) }}" method="POST" onsubmit="return confirm('Delete this invoice?');">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-light rounded-circle text-danger shadow-sm" style="width: 36px; height: 36px;">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            @empty
            <div class="text-center py-5">
                <div class="empty-state">
                    <i class="fa-solid fa-file-invoice text-secondary fs-1 mb-3 opacity-25" style="font-size: 4rem !important;"></i>
                    <h4 class="fw-bold text-dark">No invoices found</h4>
                    <div class="text-secondary">Create a new invoice to get started.</div>
                </div>
            </div>
            @endforelse

            <div class="text-center py-5" x-show="noInvoiceResults" x-cloak>
                <div class="empty-state">
                    <i class="fa-solid fa-magnifying-glass text-secondary fs-1 mb-3 opacity-25" style="font-size: 4rem !important;"></i>
                    <h4 class="fw-bold text-dark">No Matches</h4>
                    <div class="text-secondary">No invoices match your search.</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Transactions Tab -->
    <div x-show="tab === 'transactions'" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-4"
         x-transition:enter-end="opacity-100 translate-y-0"
         style="display: none;">
        <div class="d-flex flex-column gap-3 stagger">
            @forelse($payments as $index => $payment)
            <div class="card border-0 shadow-sm rounded-4 transaction-item"
                 x-show="matchesSearchPayment('{{ addslashes(strtolower($payment->student->name)) }}', '{{ strtolower($payment->receipt_number) }}')">
                <div class="card-body p-3 p-md-4">
                    <div class="row align-items-center m-0 w-100">
                        <div class="col-12 col-md-5 col-xl-3 d-flex align-items-center gap-3 mb-3 mb-md-0 p-0">
                            <div class="avatar bg-success-subtle text-success fw-bold rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 48px; height: 48px;">
                                <i class="fa-solid fa-arrow-down"></i>
                            </div>
                            <div>
                                <div class="fw-bold text-dark lh-1 mb-1">{{ $payment->student->name }}</div>
                                <div class="text-muted small letter-spacing-1 lh-1">Receipt: {{ $payment->receipt_number }}</div>
                            </div>
                        </div>
                        
                        <div class="col-6 col-md-3 col-xl-3 p-0">
                            <div class="text-dark fw-bold text-uppercase lh-1 mb-1">{{ $payment->mode }}</div>
                            <div class="text-muted small letter-spacing-1 lh-1">{{ $payment->paid_on->format('d M Y') }}</div>
                        </div>
                        
                        <div class="col-6 col-md-3 col-xl-5 text-end p-0" style="font-feature-settings: 'tnum';">
                            <div class="text-success fw-bold h4 mb-0">+{{ hostelease_money($payment->amount) }}</div>
                        </div>
                        
                        <div class="col-12 col-md-1 col-xl-1 text-end mt-2 mt-md-0 p-0">
                            <form action="{{ route('admin.payments.destroy', $payment) }}" method="POST" onsubmit="return confirm('Reverse this payment? This will restore invoice balances.');">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-light rounded-circle text-danger shadow-sm" style="width: 36px; height: 36px;" title="Reverse Transaction">
                                    <i class="fa-solid fa-rotate-left"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            @empty
            <div class="text-center py-5">
                <div class="empty-state">
                    <i class="fa-solid fa-money-bill-transfer text-secondary fs-1 mb-3 opacity-25" style="font-size: 4rem !important;"></i>
                    <h4 class="fw-bold text-dark">No transactions found</h4>
                    <div class="text-secondary">No payments have been recorded yet.</div>
                </div>
            </div>
            @endforelse

            <div class="text-center py-5" x-show="noPaymentResults" x-cloak>
                <div class="empty-state">
                    <i class="fa-solid fa-magnifying-glass text-secondary fs-1 mb-3 opacity-25" style="font-size: 4rem !important;"></i>
                    <h4 class="fw-bold text-dark">No Matches</h4>
                    <div class="text-secondary">No transactions match your search.</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Unified Charge Modal — one dynamic form covering both "Generate Fee"
         (auto-calculated from room rent) and "New Invoice" (manual amount,
         any type). Submits to whichever backend endpoint fits the selected
         type; both stay exactly as they were, this is a frontend merge. --}}
    @php
        $studentOptions = $students->mapWithKeys(fn ($s) => [$s->id => "{$s->name} ({$s->mobile})"]);
    @endphp
    <template x-teleport="body">
        <div class="custom-overlay-backdrop" x-show="chargeModalOpen" x-transition.opacity @click="chargeModalOpen = false" x-cloak style="display: none;">

            <form method="POST" :action="invoiceType === 'fee' ? '{{ route('admin.finance.generate-fee') }}' : '{{ route('admin.invoices.store') }}'" class="custom-overlay-modal" :class="{ 'is-open': chargeModalOpen }" x-show="chargeModalOpen" x-transition.opacity @click.stop style="display: none;">
                @csrf
                <input type="hidden" name="type" :value="invoiceType">

                <div class="custom-overlay-header">
                    <h5 class="fw-bold mb-0">
                        <i class="fa-solid" :class="invoiceType === 'fee' ? 'fa-wand-magic-sparkles' : 'fa-file-invoice-dollar'" style="color: var(--he-primary);"></i>
                        <span x-text="invoiceType === 'fee' ? 'Generate Fee' : 'New Invoice'" class="ms-1"></span>
                    </h5>
                    <button type="button" class="btn-close" @click="chargeModalOpen = false"></button>
                </div>

                <div class="custom-overlay-body">
                    <div class="alert alert-info border-0 rounded-3 mb-4 d-flex gap-3 align-items-start" x-show="invoiceType === 'fee'" x-cloak>
                        <i class="fa-solid fa-circle-info fs-5 mt-1"></i>
                        <div class="small">
                            <strong>Automated Fee Generation:</strong> The system will automatically calculate the fee amount based on the student's current room assignment. Specify a custom amount to override.
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1">Student <span class="text-danger">*</span></label>
                        <x-he-select name="student_id" searchable compact placeholder="Search a student..."
                            :options="$studentOptions" :submit="false" />
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1 d-block mb-2">Charge Type <span class="text-danger">*</span></label>
                        <div class="chip-group">
                            <button type="button" class="chip" :class="{ active: invoiceType === 'fee' }" @click="invoiceType = 'fee'">Hostel Fee</button>
                            <button type="button" class="chip" :class="{ active: invoiceType === 'rent' }" @click="invoiceType = 'rent'">Monthly Rent</button>
                            <button type="button" class="chip" :class="{ active: invoiceType === 'ac' }" @click="invoiceType = 'ac'">AC Bill</button>
                            <button type="button" class="chip" :class="{ active: invoiceType === 'other' }" @click="invoiceType = 'other'">Other / Fine</button>
                        </div>
                    </div>

                    <div class="row gx-3">
                        <div class="col-md-6 mb-4" x-show="invoiceType === 'fee'" x-cloak>
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">Fee Type <span class="text-danger">*</span></label>
                            <x-he-select name="fee_type" compact :submit="false" :selected="'semester'"
                                :options="['semester' => 'Semester Fee (6x Rent)', 'yearly' => 'Yearly Fee (12x Rent)', 'custom' => 'Custom Auto Fee']" />
                        </div>
                        <div :class="invoiceType === 'fee' ? 'col-md-6' : 'col-12'" class="mb-4">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">
                                <span x-text="invoiceType === 'fee' ? 'Amount Override' : 'Amount'"></span>
                                <span class="text-danger" x-show="invoiceType !== 'fee'">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted fw-bold">₹</span>
                                <input type="number" name="amount" class="form-control bg-light fw-bold text-dark fs-5"
                                    :required="invoiceType !== 'fee'" min="1" step="0.01" :placeholder="invoiceType === 'fee' ? 'Auto' : ''">
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1">Title / Description <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control bg-light" required placeholder="e.g. Fall Semester Fee">
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1">Due Date</label>
                        <input type="date" name="due_date" class="form-control bg-light">
                    </div>
                </div>

                <div class="custom-overlay-footer bg-light">
                    <button type="button" class="btn btn-white border fw-semibold rounded-pill px-4 tactile-btn" @click="chargeModalOpen = false">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-semibold rounded-pill px-4 shadow-sm tactile-btn">
                        <i class="fa-solid fa-check me-2"></i>
                        <span x-text="invoiceType === 'fee' ? 'Generate Fee' : 'Create Invoice'"></span>
                    </button>
                </div>
            </form>
        </div>
    </template>

</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    
    // Main Finance Board Component
    Alpine.data('financeBoard', () => ({
        tab: '{{ request("tab", "invoices") }}',
        search: '',
        status: '',
        chargeModalOpen: false,
        invoiceType: 'fee',
        noInvoiceResults: false,
        noPaymentResults: false,
        
        matchesSearchInvoice(name, mobile, title, statusValue) {
            const q = this.search.toLowerCase().trim();
            const filterStatus = this.status;
            
            if (filterStatus && filterStatus !== '' && statusValue !== filterStatus) return false;
            
            if (!q) return true;
            return name.includes(q) || mobile.includes(q) || title.includes(q);
        },
        
        matchesSearchPayment(name, receipt) {
            const q = this.search.toLowerCase().trim();
            if (!q) return true;
            return name.includes(q) || receipt.includes(q);
        },

        switchTab(newTab, updateUrl = true) {
            this.tab = '';
            setTimeout(() => { 
                this.tab = newTab; 
                if (updateUrl) {
                    const url = new URL(window.location);
                    url.searchParams.set('tab', newTab);
                    window.history.replaceState({}, '', url);
                    window.dispatchEvent(new CustomEvent('sync-sidebar-tab', { detail: newTab }));
                }
                this.checkNoResults();
            }, 300);
        },

        init() {
            this.$watch('search', () => this.checkNoResults());
            this.$watch('status', () => this.checkNoResults());
            // Initial check
            setTimeout(() => this.checkNoResults(), 100);
        },

        checkNoResults() {
            this.$nextTick(() => {
                if(this.tab === 'invoices') {
                    const items = this.$root.querySelectorAll('.invoice-item');
                    let visible = 0;
                    items.forEach(el => { if (el.style.display !== 'none') visible++; });
                    this.noInvoiceResults = (visible === 0 && items.length > 0);
                } else {
                    const items = this.$root.querySelectorAll('.transaction-item');
                    let visible = 0;
                    items.forEach(el => { if (el.style.display !== 'none') visible++; });
                    this.noPaymentResults = (visible === 0 && items.length > 0);
                }
            });
        }
    }));
});
</script>
@endpush

@endsection
