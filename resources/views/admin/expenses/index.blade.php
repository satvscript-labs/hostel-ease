@extends('layouts.app')
@section('title', __('Expenses'))

@section('content')
<div x-data="expenseBoard()" class="page-enter">

    <div class="d-flex align-items-center justify-content-between mb-4 stagger-1">
        <div>
            <h1 class="h3 mb-0 fw-bold">{{ __('Expenses & Outflows') }}</h1>
            <p class="text-secondary mb-0">{{ __('Manage your hostel expenses and view profit/loss.') }}</p>
        </div>
        <div>
            <button class="btn btn-primary fw-semibold rounded-pill px-4 shadow-sm tactile-btn" @click="expenseModalOpen = true">
                <i class="fa-solid fa-plus me-1"></i> {{ __('Log Expense') }}
            </button>
        </div>
    </div>

    <!-- Bento Stats -->
    <div class="row g-3 mb-4 stagger-2">
        <div class="col-md-4">
            <div class="card card-premium bg-success-subtle border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-success small fw-bold mb-1 text-uppercase letter-spacing-1">Total Income (Selected Period)</div>
                    <div class="h3 text-success mb-0 fw-bold">{{ hostelease_money($summary['income']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-premium bg-danger-subtle border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-danger small fw-bold mb-1 text-uppercase letter-spacing-1">Total Expenses</div>
                    <div class="h3 text-danger mb-0 fw-bold">{{ hostelease_money($summary['expense']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-premium {{ $summary['profit'] >= 0 ? 'bg-primary-subtle' : 'bg-warning-subtle' }} border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="{{ $summary['profit'] >= 0 ? 'text-primary' : 'text-warning-emphasis' }} small fw-bold mb-1 text-uppercase letter-spacing-1">{{ $summary['profit'] >= 0 ? 'Net Profit' : 'Net Loss' }}</div>
                    <div class="h3 {{ $summary['profit'] >= 0 ? 'text-primary' : 'text-warning-emphasis' }} mb-0 fw-bold">{{ hostelease_money($summary['profit']) }}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Premium Filters -->
    <style>
        .full-clickable-date::-webkit-calendar-picker-indicator {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
            opacity: 0;
        }
    </style>
    <div class="d-flex flex-wrap gap-3 mb-4 stagger-3 align-items-center position-relative" style="z-index: 100;">
        
        <form method="GET" x-data="{ categoryOpen: false }" x-ref="filterForm" class="d-flex flex-wrap bg-white rounded-4 rounded-md-pill shadow-sm border p-2 align-items-center">
            
            <!-- From Date -->
            <div class="px-3 py-1 position-relative" style="min-width: 160px; overflow: hidden;">
                <input type="date" name="from" value="{{ $from->toDateString() }}" class="position-absolute w-100 h-100 top-0 start-0 full-clickable-date" style="opacity: 0; cursor: pointer; z-index: 20;" onchange="this.form.submit()">
                <div class="d-flex align-items-center gap-3" style="pointer-events: none;">
                    <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 38px; height: 38px;">
                        <i class="fa-solid fa-calendar-day"></i>
                    </div>
                    <div class="d-flex flex-column">
                        <span class="text-muted text-uppercase" style="font-size: 0.65rem; font-weight: 700; letter-spacing: 0.5px;">From Date</span>
                        <span class="fw-bold fs-6 text-dark" style="line-height: 1;">{{ $from->format('d M, Y') }}</span>
                    </div>
                </div>
            </div>
            
            <div class="d-none d-md-block border-end mx-2" style="height: 32px;"></div>
            
            <!-- To Date -->
            <div class="px-3 py-1 position-relative" style="min-width: 160px; overflow: hidden;">
                <input type="date" name="to" value="{{ $to->toDateString() }}" class="position-absolute w-100 h-100 top-0 start-0 full-clickable-date" style="opacity: 0; cursor: pointer; z-index: 20;" onchange="this.form.submit()">
                <div class="d-flex align-items-center gap-3" style="pointer-events: none;">
                    <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 38px; height: 38px;">
                        <i class="fa-solid fa-calendar-check"></i>
                    </div>
                    <div class="d-flex flex-column">
                        <span class="text-muted text-uppercase" style="font-size: 0.65rem; font-weight: 700; letter-spacing: 0.5px;">To Date</span>
                        <span class="fw-bold fs-6 text-dark" style="line-height: 1;">{{ $to->format('d M, Y') }}</span>
                    </div>
                </div>
            </div>
            
            <div class="d-none d-md-block border-end mx-2" style="height: 32px;"></div>
            
            <!-- Category Custom Dropdown -->
            <div class="position-relative px-3 py-1" style="min-width: 180px;">
                <input type="hidden" name="category" value="{{ request('category') }}">
                <div class="d-flex align-items-center gap-3" @click="categoryOpen = !categoryOpen" style="cursor: pointer; z-index: 10;">
                    <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 38px; height: 38px;">
                        <i class="fa-solid fa-tags"></i>
                    </div>
                    <div class="d-flex flex-column">
                        <span class="text-muted text-uppercase" style="font-size: 0.65rem; font-weight: 700; letter-spacing: 0.5px;">Category</span>
                        <span class="fw-bold fs-6 text-dark" style="line-height: 1;">
                            {{ request('category') ? config('hostelease.expense_categories.'.request('category')) : 'All Categories' }}
                        </span>
                    </div>
                    <i class="fa-solid fa-chevron-down text-muted small ms-2 transition-all" :class="{'fa-chevron-up': categoryOpen}"></i>
                </div>
                
                <div x-show="categoryOpen" @click.outside="categoryOpen = false" x-transition.opacity.duration.200ms class="position-absolute bg-white border rounded-4 shadow-lg mt-3" style="min-width: 240px; left: 0; display: none; z-index: 1050;">
                    <div class="list-group list-group-flush rounded-4 py-2">
                        <a href="javascript:void(0)" class="list-group-item list-group-item-action border-0 py-2 px-4 {{ request('category') == '' ? 'active bg-primary text-white fw-bold' : 'text-dark fw-medium' }}" @click="$refs.filterForm.category.value=''; $refs.filterForm.submit()">
                            <i class="fa-solid fa-layer-group me-2 {{ request('category') == '' ? '' : 'text-muted' }}"></i> All Categories
                        </a>
                        @foreach(config('hostelease.expense_categories') as $k => $l)
                        <a href="javascript:void(0)" class="list-group-item list-group-item-action border-0 py-2 px-4 {{ request('category') == $k ? 'active bg-primary text-white fw-bold' : 'text-dark fw-medium' }}" @click="$refs.filterForm.category.value='{{ $k }}'; $refs.filterForm.submit()">
                            <i class="fa-solid fa-tag me-2 {{ request('category') == $k ? '' : 'opacity-50 text-muted' }}"></i> {{ $l }}
                        </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </form>
        
        <!-- Search -->
        <div class="ms-md-auto flex-grow-1" style="min-width: 300px; max-width: 500px;">
            <div class="bg-white rounded-pill shadow-sm border p-1 d-flex align-items-center h-100" style="min-height: 58px;">
                <div class="bg-light rounded-circle d-flex align-items-center justify-content-center text-muted ms-1" style="width: 38px; height: 38px;">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </div>
                <input type="text" x-model="search" class="form-control border-0 shadow-none bg-transparent fw-semibold px-3 py-2" placeholder="Search expenses...">
            </div>
        </div>
    </div>

    <!-- Expenses List -->
    <div class="card card-premium border-0 shadow-sm stagger-4">
        <div class="card-body p-0">
            @forelse($expenses as $expense)
            <div class="expense-item border-bottom p-4" x-show="search === '' || '{{ strtolower(addslashes($expense->title . ' ' . $expense->paid_to)) }}'.includes(search.toLowerCase())" style="animation-delay: {{ min($loop->index * 0.05, 0.5) }}s;">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-light rounded-circle d-flex align-items-center justify-content-center text-muted" style="width: 48px; height: 48px;">
                            <i class="fa-solid fa-money-bill-transfer fs-5"></i>
                        </div>
                        <div>
                            <div class="fw-bold fs-6 text-dark mb-1">{{ $expense->title }}</div>
                            <div class="d-flex align-items-center gap-3 text-muted small fw-medium">
                                <span><i class="fa-regular fa-calendar me-1"></i> {{ $expense->expense_date->format('d M Y') }}</span>
                                <span><i class="fa-solid fa-wallet me-1"></i> {{ config('hostelease.payment_modes.'.$expense->mode, $expense->mode) }}</span>
                                @if($expense->paid_to)
                                <span><i class="fa-solid fa-user-tag me-1"></i> {{ $expense->paid_to }}</span>
                                @endif
                                @if($expense->reference_number)
                                <span><i class="fa-solid fa-hashtag me-1"></i> {{ $expense->reference_number }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex align-items-center gap-4">
                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-3 py-2 fw-semibold">
                            {{ config('hostelease.expense_categories.'.$expense->category, $expense->category) }}
                        </span>
                        <div class="text-end" style="min-width: 100px;">
                            <div class="text-danger fw-bold fs-5">{{ hostelease_money($expense->amount) }}</div>
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-light btn-sm rounded-circle" type="button" data-bs-toggle="dropdown" style="width: 32px; height: 32px;">
                                <i class="fa-solid fa-ellipsis-vertical text-muted"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3">
                                <li>
                                    <form action="{{ route('admin.expenses.destroy', $expense) }}" method="POST" class="m-0" onsubmit="return confirm('Delete this expense?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="dropdown-item text-danger fw-medium py-2">
                                            <i class="fa-solid fa-trash me-2"></i> Delete Expense
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            @empty
            <div class="text-center py-5">
                <div class="empty-state">
                    <i class="fa-solid fa-money-bill-trend-up text-secondary fs-1 mb-3 opacity-25" style="font-size: 4rem !important;"></i>
                    <h4 class="fw-bold text-dark">No expenses logged</h4>
                    <div class="text-secondary">Expenses for the selected period will appear here.</div>
                </div>
            </div>
            @endforelse

            <div class="text-center py-5" x-show="noResults" x-cloak>
                <div class="empty-state">
                    <i class="fa-solid fa-magnifying-glass text-secondary fs-1 mb-3 opacity-25" style="font-size: 4rem !important;"></i>
                    <h4 class="fw-bold text-dark">No Matches</h4>
                    <div class="text-secondary">No expenses match your search.</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Ultra-Premium Add Expense Modal (Teleported inside x-data) -->
    <template x-teleport="body">
        <div class="custom-overlay-backdrop" x-show="expenseModalOpen" x-transition.opacity @click="expenseModalOpen = false" x-cloak style="display: none;">
            
            <form method="POST" action="{{ route('admin.expenses.store') }}" class="custom-overlay-modal" :class="{ 'is-open': expenseModalOpen }" x-show="expenseModalOpen" x-transition.opacity @click.stop style="display: none;">
                @csrf
                
                <div class="custom-overlay-header">
                    <h5 class="fw-bold mb-0"><i class="fa-solid fa-money-bill-wave text-primary me-2"></i> Log Expense</h5>
                    <button type="button" class="btn-close" @click="expenseModalOpen = false"></button>
                </div>
                
                <div class="custom-overlay-body">
                    <h6 class="fw-bold text-muted text-uppercase mb-3" style="font-size: 0.75rem; letter-spacing: 1px;">Basic Details</h6>
                    
                    <div class="row gx-3">
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">Category <span class="text-danger">*</span></label>
                            <select name="category" class="form-select bg-light" required>
                                @foreach(config('hostelease.expense_categories') as $k=>$l)
                                    <option value="{{ $k }}">{{ $l }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">Date <span class="text-danger">*</span></label>
                            <input type="date" name="expense_date" class="form-control bg-light" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1">Title / Description <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control bg-light" required placeholder="e.g. Plumber for Room 101">
                    </div>
                    
                    <div class="row gx-3">
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">Amount <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted fw-bold">₹</span>
                                <input type="number" step="0.01" min="1" name="amount" class="form-control bg-light fw-bold text-dark fs-5" required>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">Payment Mode</label>
                            <select name="mode" class="form-select bg-light">
                                @foreach(config('hostelease.payment_modes') as $k=>$l)
                                    <option value="{{ $k }}">{{ $l }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <hr class="border-secondary opacity-10 my-4">
                    <h6 class="fw-bold text-muted text-uppercase mb-3" style="font-size: 0.75rem; letter-spacing: 1px;">Additional Details (Optional)</h6>

                    <div class="row gx-3">
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">Paid To</label>
                            <input type="text" name="paid_to" class="form-control bg-light" placeholder="Person or Vendor Name">
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold small text-uppercase letter-spacing-1">Reference No.</label>
                            <input type="text" name="reference_number" class="form-control bg-light" placeholder="Invoice or Bill no.">
                        </div>
                    </div>
                    
                    <div class="mb-2">
                        <label class="form-label fw-bold small text-uppercase letter-spacing-1">Notes</label>
                        <textarea name="notes" class="form-control bg-light" rows="2" placeholder="Any extra information..."></textarea>
                    </div>
                </div>
                
                <div class="custom-overlay-footer bg-light">
                    <button type="button" class="btn btn-white border fw-semibold rounded-pill px-4 tactile-btn" @click="expenseModalOpen = false">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-semibold rounded-pill px-4 shadow-sm tactile-btn"><i class="fa-solid fa-check me-2"></i> Log Expense</button>
                </div>
            </form>
        </div>
    </template>

</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('expenseBoard', () => ({
        search: '',
        expenseModalOpen: false,
        noResults: false,

        checkNoResults() {
            this.$nextTick(() => {
                const items = this.$root.querySelectorAll('.expense-item');
                let visible = 0;
                items.forEach(el => { if (el.style.display !== 'none') visible++; });
                this.noResults = (visible === 0 && items.length > 0);
            });
        },
        init() {
            this.$watch('search', () => this.checkNoResults());
            setTimeout(() => this.checkNoResults(), 100);
        }
    }));
});
</script>
<style>
    .expense-item {
        animation: cascadeIn 0.5s cubic-bezier(0.4, 0, 0.2, 1) both;
    }
    @keyframes cascadeIn {
        from { opacity: 0; transform: translateY(30px) scale(0.95); }
        to { opacity: 1; transform: translateY(0) scale(1); }
    }
</style>
@endpush
@endsection
