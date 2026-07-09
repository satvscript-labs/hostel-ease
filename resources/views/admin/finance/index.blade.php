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
            <button type="button" class="btn btn-light rounded-pill px-4 fw-bold shadow-sm text-primary border" @click="feeModalOpen = true">
                <i class="fa-solid fa-wand-magic-sparkles me-1"></i> {{ __('Generate Fee') }}
            </button>
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
        <div class="d-flex flex-column gap-3">
            @forelse($invoices as $index => $invoice)
            <div class="card border-0 shadow-sm rounded-4 invoice-item" 
                 x-show="matchesSearchInvoice('{{ addslashes(strtolower($invoice->student->name)) }}', '{{ $invoice->student->mobile }}', '{{ addslashes(strtolower($invoice->title)) }}', '{{ $invoice->status }}')"
                 style="animation-delay: {{ min($index * 50, 500) }}ms;">
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
        <div class="d-flex flex-column gap-3">
            @forelse($payments as $index => $payment)
            <div class="card border-0 shadow-sm rounded-4 transaction-item"
                 x-show="matchesSearchPayment('{{ addslashes(strtolower($payment->student->name)) }}', '{{ strtolower($payment->receipt_number) }}')"
                 style="animation-delay: {{ min($index * 50, 500) }}ms;">
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

    <!-- Ultra-Premium Generate Fee Modal -->
    <template x-teleport="body">
        <div class="custom-overlay-backdrop" x-show="feeModalOpen" x-transition.opacity @click="feeModalOpen = false" x-cloak style="display: none;">
            
            <form method="POST" action="{{ route('admin.finance.generate-fee') }}" class="custom-overlay-modal" :class="{ 'is-open': feeModalOpen }" x-show="feeModalOpen" x-transition.opacity @click.stop style="display: none;"
                x-data="{
                    feeType: '',
                    feeTypeDropdown: false,
                    studentDropdown: false,
                    studentId: '',
                    studentName: '',
                    studentSearch: '',
                    allStudents: {{ Js::from($studentsJson) }},
                    feeTypes: [
                        { value: 'semester', label: 'Semester Fee', sub: '6× monthly rent', icon: 'fa-solid fa-calendar-days', color: 'text-primary', bg: 'bg-primary-subtle' },
                        { value: 'yearly',   label: 'Yearly Fee',   sub: '12× monthly rent', icon: 'fa-solid fa-calendar-check', color: 'text-success', bg: 'bg-success-subtle' },
                        { value: 'custom',   label: 'Custom Fee',   sub: 'Set amount manually', icon: 'fa-solid fa-pen-to-square', color: 'text-warning', bg: 'bg-warning-subtle' },
                    ],
                    get selectedFeeType() { return this.feeTypes.find(f => f.value === this.feeType); },
                    get filteredStudents() {
                        let pool = this.feeType && this.feeType !== 'custom'
                            ? this.allStudents.filter(s => s.freq === this.feeType)
                            : this.allStudents;
                        if (this.studentSearch.trim()) {
                            const q = this.studentSearch.toLowerCase();
                            pool = pool.filter(s => s.name.toLowerCase().includes(q) || s.mobile.includes(q));
                        }
                        return pool;
                    },
                    selectFeeType(val) {
                        if (this.feeType !== val) {
                            this.studentId = '';
                            this.studentName = '';
                        }
                        this.feeType = val;
                        this.feeTypeDropdown = false;
                    },
                    selectStudent(s) {
                        this.studentId = s.id;
                        this.studentName = s.name + ' · ' + s.mobile;
                        this.studentDropdown = false;
                        this.studentSearch = '';
                    },
                    clearStudent() { this.studentId = ''; this.studentName = ''; }
                }"
            >
                @csrf
                
                <div class="custom-overlay-header">
                    <h5 class="fw-bold mb-0"><i class="fa-solid fa-wand-magic-sparkles text-primary me-2"></i> Generate Fee</h5>
                    <button type="button" class="btn-close" @click="feeModalOpen = false"></button>
                </div>
                
                <div class="custom-overlay-body">
                    <div class="alert alert-info border-0 rounded-3 mb-4 d-flex gap-3 align-items-start">
                        <i class="fa-solid fa-circle-info fs-5 mt-1"></i>
                        <div class="small">
                            <strong>Automated Fee Generation:</strong> The system will automatically calculate the fee amount based on the student's current room assignment. If you specify a custom amount, the auto-calculation will be bypassed.
                        </div>
                    </div>

                    {{-- Step 1: Fee Type (pick first so student list can filter) --}}
                    <div class="mb-4">
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1">Fee Type <span class="text-danger">*</span></label>
                        <input type="hidden" name="fee_type" :value="feeType" required>

                        <div class="position-relative">
                            <div class="d-flex align-items-center justify-content-between form-control bg-light" @click="feeTypeDropdown = !feeTypeDropdown" style="cursor: pointer; height: 3.25rem;">
                                <template x-if="selectedFeeType">
                                    <span class="d-flex align-items-center gap-2 fw-semibold text-dark">
                                        <span :class="[selectedFeeType.bg, selectedFeeType.color, 'rounded-circle d-flex align-items-center justify-content-center']" style="width:28px;height:28px;flex-shrink:0;">
                                            <i :class="selectedFeeType.icon" class="small"></i>
                                        </span>
                                        <span x-text="selectedFeeType.label"></span>
                                        <span class="text-muted small fw-normal" x-text="'— ' + selectedFeeType.sub"></span>
                                    </span>
                                </template>
                                <template x-if="!selectedFeeType">
                                    <span class="text-muted">Choose fee type first...</span>
                                </template>
                                <i class="fa-solid fa-chevron-down text-muted small transition-all" :class="{'fa-chevron-up': feeTypeDropdown}"></i>
                            </div>

                            <div x-show="feeTypeDropdown" @click.outside="feeTypeDropdown = false" x-transition.opacity.duration.200ms class="position-absolute bg-white border rounded-4 shadow-lg mt-2 w-100" style="display:none; z-index:1050;">
                                <div class="list-group list-group-flush rounded-4 py-2">
                                    <template x-for="ft in feeTypes" :key="ft.value">
                                        <a href="javascript:void(0)" class="list-group-item list-group-item-action border-0 py-3 px-4 d-flex align-items-center gap-3" :class="feeType === ft.value ? 'active bg-primary text-white fw-bold' : 'text-dark'" @click="selectFeeType(ft.value)">
                                            <span :class="feeType === ft.value ? 'bg-white text-primary' : [ft.bg, ft.color]" class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width:36px;height:36px;">
                                                <i :class="ft.icon"></i>
                                            </span>
                                            <div>
                                                <div class="fw-bold" x-text="ft.label"></div>
                                                <div class="small" :class="feeType === ft.value ? 'text-white opacity-75' : 'text-muted'" x-text="ft.sub"></div>
                                            </div>
                                        </a>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Step 2: Student (filters based on fee type) --}}
                    <div class="mb-4">
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1">
                            Student <span class="text-danger">*</span>
                            <template x-if="feeType && feeType !== 'custom'">
                                <span class="badge bg-primary-subtle text-primary fw-semibold ms-2 rounded-pill text-lowercase" x-text="feeType + ' students only'"></span>
                            </template>
                        </label>
                        <input type="hidden" name="student_id" :value="studentId" required>

                        <div class="position-relative">
                            <div class="d-flex align-items-center justify-content-between form-control bg-light" @click="studentDropdown = !studentDropdown" style="cursor: pointer; height: 3.25rem;">
                                <span class="fw-semibold text-dark" :class="{'text-muted fw-normal': !studentId}" x-text="studentId ? studentName : 'Choose a student...'"></span>
                                <div class="d-flex align-items-center gap-2">
                                    <i x-show="studentId" class="fa-solid fa-xmark text-muted small" @click.stop="clearStudent()" style="cursor:pointer;" title="Clear"></i>
                                    <i class="fa-solid fa-chevron-down text-muted small transition-all" :class="{'fa-chevron-up': studentDropdown}"></i>
                                </div>
                            </div>

                            {{-- Invisible backdrop --}}
                            <div x-show="studentDropdown" @click="studentDropdown = false" class="position-fixed top-0 start-0 w-100 h-100" style="z-index:1040; display:none;"></div>

                            <div x-show="studentDropdown" x-transition.opacity.duration.200ms class="position-absolute bg-white border rounded-4 shadow-lg mt-2 w-100" style="display:none; z-index:1050; max-height:280px; overflow-y:auto;">
                                <div class="p-2 border-bottom sticky-top bg-white">
                                    <input type="text" x-model="studentSearch" @click.stop class="form-control form-control-sm bg-light" placeholder="Search name or mobile..." x-ref="studentSearchInput">
                                </div>
                                <div class="list-group list-group-flush py-1">
                                    <template x-if="filteredStudents.length === 0">
                                        <div class="p-3 text-center text-muted small">
                                            <i class="fa-solid fa-user-slash me-1 opacity-50"></i>
                                            <span x-text="feeType && feeType !== 'custom' ? 'No ' + feeType + ' students found' : 'No students found'"></span>
                                        </div>
                                    </template>
                                    <template x-for="s in filteredStudents" :key="s.id">
                                        <a href="javascript:void(0)" class="list-group-item list-group-item-action border-0 py-2 px-3 d-flex align-items-center gap-3" :class="studentId == s.id ? 'active bg-primary text-white fw-bold' : 'text-dark'" @click="selectStudent(s)">
                                            <div :class="studentId == s.id ? 'bg-white text-primary' : (s.freq === 'semester' ? 'bg-primary-subtle text-primary' : 'bg-success-subtle text-success')" class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 fw-bold" style="width:34px;height:34px;font-size:0.75rem;" x-text="s.name.charAt(0).toUpperCase()"></div>
                                            <div class="flex-grow-1 min-width-0">
                                                <div class="fw-semibold lh-sm" x-text="s.name"></div>
                                                <div class="small" :class="studentId == s.id ? 'text-white opacity-75' : 'text-muted'" x-text="s.mobile"></div>
                                            </div>
                                            <span class="badge rounded-pill small flex-shrink-0" :class="studentId == s.id ? 'bg-white text-primary' : (s.freq === 'semester' ? 'bg-primary-subtle text-primary' : 'bg-success-subtle text-success')" x-text="s.freq"></span>
                                        </a>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr class="border-secondary opacity-10 my-4">
                    <h6 class="fw-bold text-muted text-uppercase mb-3" style="font-size: 0.75rem; letter-spacing: 1px;">Fee Details</h6>

                    <div class="row gx-3">
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">Amount Override <span class="text-muted fw-normal">(optional)</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted fw-bold">₹</span>
                                <input type="number" name="amount" class="form-control bg-light" min="1" step="0.01" placeholder="Auto">
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">Due Date</label>
                            <input type="date" name="due_date" class="form-control bg-light">
                        </div>
                    </div>
                    
                    <div class="mb-2">
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1">Title / Description <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control bg-light" required placeholder="e.g. Fall Semester Fee">
                    </div>
                </div>
                
                <div class="custom-overlay-footer bg-light">
                    <button type="button" class="btn btn-white border fw-semibold rounded-pill px-4 tactile-btn" @click="feeModalOpen = false">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-semibold rounded-pill px-4 shadow-sm tactile-btn" :disabled="!feeType || !studentId">
                        <i class="fa-solid fa-wand-magic-sparkles me-2"></i> Generate Fee
                    </button>
                </div>
            </form>
        </div>
    </template>

    <!-- Ultra-Premium Add Invoice Modal (Teleported inside x-data) -->
    <template x-teleport="body">
        <div class="custom-overlay-backdrop" x-show="invoiceModalOpen" x-transition.opacity @click="invoiceModalOpen = false" x-cloak style="display: none;">
            
            <form method="POST" action="{{ route('admin.invoices.store') }}" class="custom-overlay-modal" :class="{ 'is-open': invoiceModalOpen }" x-show="invoiceModalOpen" x-transition.opacity @click.stop style="display: none;">
                @csrf
                
                <div class="custom-overlay-header">
                    <h5 class="fw-bold mb-0"><i class="fa-solid fa-file-invoice-dollar text-primary me-2"></i> New Invoice</h5>
                    <button type="button" class="btn-close" @click="invoiceModalOpen = false"></button>
                </div>
                
                <div class="custom-overlay-body">
                    <h6 class="fw-bold text-muted text-uppercase mb-3" style="font-size: 0.75rem; letter-spacing: 1px;">Student Details</h6>
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
                            <button type="button" class="form-control bg-light text-start d-flex justify-content-between align-items-center" @click="open = !open">
                                <span x-text="selectedLabel" :class="{'text-muted': !value}"></span>
                                <div>
                                    <i x-show="value" class="fa-solid fa-xmark text-muted small me-2 cursor-pointer" @click.stop="clearOption()" style="cursor:pointer;" title="Clear"></i>
                                    <i class="fa-solid fa-chevron-down text-muted small"></i>
                                </div>
                            </button>
                            
                            <div x-show="open" @click.outside="open = false" class="position-absolute w-100 bg-white border rounded shadow mt-1 z-3" style="max-height: 250px; overflow-y: auto; display: none;" x-transition>
                                <div class="p-2 border-bottom sticky-top bg-white">
                                    <input type="text" x-model="search" x-ref="searchInput" class="form-control form-control-sm bg-light" placeholder="Search student...">
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

                    <hr class="border-secondary opacity-10 my-4">
                    <h6 class="fw-bold text-muted text-uppercase mb-3" style="font-size: 0.75rem; letter-spacing: 1px;">Invoice Details</h6>

                    <div class="row gx-3">
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">Type <span class="text-danger">*</span></label>
                            <select name="type" class="form-select bg-light" required>
                                <option value="fee">Hostel Fee</option>
                                <option value="rent">Monthly Rent</option>
                                <option value="ac">AC Bill</option>
                                <option value="other">Other/Fine</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">Amount <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted fw-bold">₹</span>
                                <input type="number" name="amount" class="form-control bg-light fw-bold text-dark fs-5" required min="1" step="0.01">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1">Title / Description <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control bg-light" required placeholder="e.g. Broken chair fine">
                    </div>
                    
                    <div class="mb-2">
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1">Due Date (Optional)</label>
                        <input type="date" name="due_date" class="form-control bg-light">
                    </div>
                </div>
                
                <div class="custom-overlay-footer bg-light">
                    <button type="button" class="btn btn-white border fw-semibold rounded-pill px-4 tactile-btn" @click="invoiceModalOpen = false">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-semibold rounded-pill px-4 shadow-sm tactile-btn"><i class="fa-solid fa-check me-2"></i> Create Invoice</button>
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
        invoiceModalOpen: false,
        feeModalOpen: false,
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

    // Searchable Select Component (for Modal)
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
            clearOption() {
                this.value = '';
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
<style>
    .invoice-item, .transaction-item {
        animation: cascadeIn 0.5s cubic-bezier(0.4, 0, 0.2, 1) both;
    }
    @keyframes cascadeIn {
        from { opacity: 0; transform: translateY(30px) scale(0.95); }
        to { opacity: 1; transform: translateY(0) scale(1); }
    }
</style>
@endpush

@endsection
