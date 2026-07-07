@extends('layouts.app')

@section('title', __('Finance Board'))

@section('content')
<div x-data="{ tab: '{{ request('tab', 'invoices') }}', search: '{{ request('search', '') }}', invoiceModalOpen: false }" 
     @tab-changed.window="tab = $event.detail" class="page-enter"
     x-init="$watch('tab', (val) => {
         const url = new URL(window.location);
         url.searchParams.set('tab', val);
         window.history.replaceState({}, '', url);
     })">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 fw-bold">{{ __('Finance Board') }}</h1>
            <p class="text-secondary">{{ __('Manage invoices, due balances, and transactions in one place.') }}</p>
        </div>
        <div>
            <button type="button" class="btn rounded-pill px-4 fw-bold shadow-sm" style="background: var(--he-primary); color: #fff;" @click="invoiceModalOpen = true">
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
            <div class="card glass-tile h-100 border-0 shadow-sm" style="border-radius: 1.25rem; background: #fff;">
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
            <div class="card glass-tile h-100 border-0 shadow-sm" style="border-radius: 1.25rem; background: #fff;">
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
                :class="{ 'text-dark fw-bold': tab === 'invoices' }" @click="tab = 'invoices'">
            <i class="fa-solid fa-file-invoice me-1"></i> {{ __('Invoices & Dues') }}
            <div x-show="tab === 'invoices'" class="position-absolute bottom-0 start-0 w-100" style="height: 3px; background: var(--he-primary); border-radius: 3px 3px 0 0;" x-transition></div>
        </button>
        <button class="he-tab bg-transparent border-0 py-3 px-4 fw-medium text-secondary position-relative" 
                :class="{ 'text-dark fw-bold': tab === 'transactions' }" @click="tab = 'transactions'">
            <i class="fa-solid fa-money-bill-transfer me-1"></i> {{ __('Transactions') }}
            <div x-show="tab === 'transactions'" class="position-absolute bottom-0 start-0 w-100" style="height: 3px; background: var(--he-primary); border-radius: 3px 3px 0 0;" x-transition></div>
        </button>
    </div>

    <!-- Filter & Search Bar -->
    <div class="mb-4">
        <form action="{{ route('admin.finance.index') }}" method="GET" class="d-flex flex-wrap gap-2 align-items-center bg-white p-2 rounded-pill shadow-sm border border-light">
            <input type="hidden" name="tab" x-model="tab">
            <input type="hidden" name="sort" value="{{ $sort }}">
            <input type="hidden" name="direction" value="{{ $direction }}">
            
            <div class="flex-grow-1 position-relative px-2">
                <i class="fa-solid fa-search position-absolute text-muted" style="top: 50%; transform: translateY(-50%); left: 1rem;"></i>
                <input type="text" name="search" x-model="search" class="form-control border-0 bg-transparent ps-5 shadow-none" placeholder="Search by student or receipt...">
            </div>

            <div x-show="tab === 'invoices'" x-cloak class="border-start ps-3 me-2">
                <select name="status" class="form-select border-0 bg-transparent text-secondary shadow-none" x-on:change="$el.form.submit()" style="cursor: pointer;">
                    <option value="">All Statuses</option>
                    <option value="paid" {{ $status === 'paid' ? 'selected' : '' }}>Paid</option>
                    <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="partial" {{ $status === 'partial' ? 'selected' : '' }}>Partial</option>
                </select>
            </div>
            
            @if($search || $status || $sort !== 'id')
                <div class="pe-2">
                    <a href="{{ route('admin.finance.index', ['tab' => request('tab', 'invoices')]) }}" class="btn btn-light rounded-pill btn-sm text-secondary px-3"><i class="fa-solid fa-xmark"></i> Clear</a>
                </div>
            @endif
        </form>
    </div>

    <!-- Invoices Tab -->
    <div x-show="tab === 'invoices'" x-cloak>
        <div class="d-flex flex-column gap-3">
            @forelse($invoices as $index => $invoice)
            <div class="card border-0 shadow-sm rounded-4 animate-fade-up" style="animation-delay: {{ $index * 50 }}ms;">
                <div class="card-body p-4 d-flex align-items-center justify-content-between flex-wrap gap-3">
                    <div class="d-flex align-items-center gap-3" style="min-width: 250px;">
                        <div class="avatar bg-light text-primary fw-bold rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                            {{ substr($invoice->student->name, 0, 1) }}
                        </div>
                        <div>
                            <div class="fw-bold text-dark">{{ $invoice->student->name }}</div>
                            <div class="text-muted small letter-spacing-1">{{ $invoice->student->mobile }}</div>
                        </div>
                    </div>
                    
                    <div style="min-width: 150px;">
                        <div class="text-dark fw-medium">{{ $invoice->title }}</div>
                        <div class="text-muted small letter-spacing-1 text-uppercase">{{ $invoice->type }} &bull; {{ $invoice->created_at->format('d M Y') }}</div>
                    </div>
                    
                    <div class="d-flex gap-4 align-items-center flex-grow-1 justify-content-end" style="font-feature-settings: 'tnum';">
                        <div class="text-end">
                            <div class="text-muted small fw-bold text-uppercase letter-spacing-1">Amount</div>
                            <div class="fw-bold">{{ hostelease_money($invoice->amount) }}</div>
                        </div>
                        <div class="text-end">
                            <div class="text-muted small fw-bold text-uppercase letter-spacing-1">Paid</div>
                            <div class="text-success fw-bold">{{ hostelease_money($invoice->paid_amount) }}</div>
                        </div>
                        <div class="text-end">
                            <div class="text-muted small fw-bold text-uppercase letter-spacing-1">Balance</div>
                            <div class="text-danger fw-bold">{{ hostelease_money($invoice->balance) }}</div>
                        </div>
                    </div>
                    
                    <div class="d-flex align-items-center gap-3 ms-md-4">
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
            @empty
            <div class="text-center py-5">
                <div class="empty-state">
                    <i class="fa-solid fa-file-invoice text-secondary fs-1 mb-3 opacity-25" style="font-size: 4rem !important;"></i>
                    <h4 class="fw-bold text-dark">No invoices found</h4>
                    <div class="text-secondary">Try adjusting your search or filters.</div>
                </div>
            </div>
            @endforelse
        </div>
        
        @if($invoices->hasPages())
        <div class="mt-4">
            {{ $invoices->appends(['tab' => 'invoices', 'search' => request('search')])->links() }}
        </div>
        @endif
    </div>

    <!-- Transactions Tab -->
    <div x-show="tab === 'transactions'" x-cloak>
        <div class="d-flex flex-column gap-3">
            @forelse($payments as $index => $payment)
            <div class="card border-0 shadow-sm rounded-4 animate-fade-up" style="animation-delay: {{ $index * 50 }}ms;">
                <div class="card-body p-4 d-flex align-items-center justify-content-between flex-wrap gap-3">
                    <div class="d-flex align-items-center gap-3" style="min-width: 250px;">
                        <div class="avatar bg-success-subtle text-success fw-bold rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                            <i class="fa-solid fa-arrow-down"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-dark">{{ $payment->student->name }}</div>
                            <div class="text-muted small letter-spacing-1">Receipt: {{ $payment->receipt_number }}</div>
                        </div>
                    </div>
                    
                    <div style="min-width: 150px;">
                        <div class="text-dark fw-medium text-uppercase">{{ $payment->mode }}</div>
                        <div class="text-muted small letter-spacing-1">{{ $payment->paid_on->format('d M Y') }}</div>
                    </div>
                    
                    <div class="text-end flex-grow-1" style="font-feature-settings: 'tnum';">
                        <div class="text-success fw-bold h4 mb-0">+{{ hostelease_money($payment->amount) }}</div>
                    </div>
                    
                    <div class="ms-md-4">
                        <form action="{{ route('admin.payments.destroy', $payment) }}" method="POST" onsubmit="return confirm('Reverse this payment? This will restore invoice balances.');">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-light rounded-circle text-danger shadow-sm" style="width: 36px; height: 36px;" title="Reverse Transaction">
                                <i class="fa-solid fa-rotate-left"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            @empty
            <div class="text-center py-5">
                <div class="empty-state">
                    <i class="fa-solid fa-money-bill-transfer text-secondary fs-1 mb-3 opacity-25" style="font-size: 4rem !important;"></i>
                    <h4 class="fw-bold text-dark">No transactions found</h4>
                    <div class="text-secondary">Try adjusting your search criteria.</div>
                </div>
            </div>
            @endforelse
        </div>
        
        @if($payments->hasPages())
        <div class="mt-4">
            {{ $payments->appends(['tab' => 'transactions', 'search' => request('search')])->links() }}
        </div>
        @endif
    </div>

    </div>

    <!-- Ultra-Premium Add Invoice Modal (Teleported) -->
    <template x-teleport="body">
        <div class="custom-overlay-backdrop" x-show="invoiceModalOpen" x-transition.opacity @click="invoiceModalOpen = false" x-cloak style="display: none;">
            
            <form method="POST" action="{{ route('admin.invoices.store') }}" class="custom-overlay-modal" :class="{ 'is-open': invoiceModalOpen }" x-show="invoiceModalOpen" x-transition.opacity @click.stop style="display: none;">
                @csrf
                
                <div class="custom-overlay-header">
                    <h5 class="fw-bold mb-0">New Invoice</h5>
                    <button type="button" class="btn-close" @click="invoiceModalOpen = false"></button>
                </div>
                
                <div class="custom-overlay-body">
                    <!-- Searchable Select for Students -->
                    <div class="mb-4" x-data="searchableSelect({
                        options: [
                            @foreach($students as $student)
                            { value: '{{ $student->id }}', label: '{{ addslashes($student->name) }} ({{ $student->mobile }})' },
                            @endforeach
                        ]
                    })">
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1">Student <span class="text-danger">*</span></label>
                        <input type="hidden" name="student_id" :value="value" required>
                        <div class="position-relative">
                            <button type="button" class="form-control bg-white text-start d-flex justify-content-between align-items-center shadow-sm" @click="open = !open">
                                <span x-text="selectedLabel" :class="{'text-muted': !value}"></span>
                                <i class="fa-solid fa-chevron-down text-muted small"></i>
                            </button>
                            
                            <div x-show="open" @click.outside="open = false" class="position-absolute w-100 bg-white border rounded shadow mt-1 z-3" style="max-height: 250px; overflow-y: auto; display: none;" x-transition>
                                <div class="p-2 border-bottom sticky-top bg-white">
                                    <input type="text" x-model="search" x-ref="searchInput" class="form-control form-control-sm bg-light border-0" placeholder="Search student...">
                                </div>
                                <div class="list-group list-group-flush">
                                    <template x-for="opt in filteredOptions" :key="opt.value">
                                        <button type="button" class="list-group-item list-group-item-action py-2" @click="selectOption(opt.value)" x-text="opt.label"></button>
                                    </template>
                                    <div x-show="filteredOptions.length === 0" class="p-3 text-center text-muted small">No students found</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row gx-3">
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">Type <span class="text-danger">*</span></label>
                            <select name="type" class="form-select bg-white shadow-sm" required>
                                <option value="fee">Hostel Fee</option>
                                <option value="rent">Monthly Rent</option>
                                <option value="ac">AC Bill</option>
                                <option value="other">Other/Fine</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">Amount <span class="text-danger">*</span></label>
                            <div class="input-group shadow-sm rounded">
                                <span class="input-group-text bg-white border-end-0 border text-muted">₹</span>
                                <input type="number" name="amount" class="form-control bg-white border-start-0 border" required min="1" step="0.01">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1">Title / Description <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control bg-white shadow-sm" required placeholder="e.g. Broken chair fine">
                    </div>
                    
                    <div class="mb-2">
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1">Due Date (Optional)</label>
                        <input type="date" name="due_date" class="form-control bg-white shadow-sm">
                    </div>
                </div>
                
                <div class="custom-overlay-footer">
                    <button type="button" class="btn btn-light rounded-pill px-4 fw-bold" @click="invoiceModalOpen = false">Cancel</button>
                    <button type="submit" class="btn rounded-pill px-5 fw-bold shadow-sm" style="background: var(--he-primary); color: #fff;">Create Invoice</button>
                </div>
            </form>
        </div>
    </template>
</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    // Searchable Select Component
    if (!Alpine.data('searchableSelect')) {
        Alpine.data('searchableSelect', (config) => ({
            open: false,
            search: '',
            value: '',
            options: config.options,
            
            get filteredOptions() {
                if (this.search === '') return this.options;
                return this.options.filter(opt => opt.label.toLowerCase().includes(this.search.toLowerCase()));
            },
            get selectedLabel() {
                const selected = this.options.find(opt => opt.value == this.value);
                return selected ? selected.label : '— Select —';
            },
            selectOption(val) {
                this.value = val;
                this.open = false;
                this.search = '';
            },
            init() {
                this.$watch('open', val => {
                    if(val) {
                        this.$nextTick(() => { this.$refs.searchInput.focus(); });
                    } else {
                        this.search = '';
                    }
                });
            }
        }));
    }
});
</script>
@endpush

@endsection
