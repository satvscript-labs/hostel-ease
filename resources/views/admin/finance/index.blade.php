@extends('layouts.app')

@section('title', __('Finance Board'))

@section('content')
<div x-data="{ tab: '{{ request('tab', 'invoices') }}', search: '' }" @tab-changed.window="tab = $event.detail" class="page-enter">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0">{{ __('Finance Board') }}</h1>
            <p class="text-secondary">{{ __('Manage invoices, due balances, and transactions in one place.') }}</p>
        </div>
        <div>
            <!-- Bulk Generate Rent Button (Removed, automated) -->
            <button type="button" class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#addInvoiceModal">
                <i class="fa-solid fa-plus me-1"></i> {{ __('New Invoice') }}
            </button>
        </div>
    </div>

    <!-- Bento Stats -->
    <div class="bento mb-4">
        <div class="bento-card">
            <div class="text-secondary small fw-medium mb-1">{{ __('Total Invoices') }}</div>
            <div class="h3 mb-0">{{ hostelease_money($invoices->sum('amount')) }}</div>
        </div>
        <div class="bento-card">
            <div class="text-secondary small fw-medium mb-1">{{ __('Total Collected') }}</div>
            <div class="h3 mb-0 text-success">{{ hostelease_money($invoices->sum('paid_amount')) }}</div>
        </div>
        <div class="bento-card">
            <div class="text-secondary small fw-medium mb-1">{{ __('Total Outstanding') }}</div>
            <div class="h3 mb-0 text-danger">{{ hostelease_money($invoices->sum('balance')) }}</div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="he-tabs mb-4">
        <button class="he-tab" :class="{ 'active': tab === 'invoices' }" @click="tab = 'invoices'">
            <i class="fa-solid fa-file-invoice me-1"></i> {{ __('Invoices & Dues') }}
        </button>
        <button class="he-tab" :class="{ 'active': tab === 'transactions' }" @click="tab = 'transactions'">
            <i class="fa-solid fa-money-bill-transfer me-1"></i> {{ __('Transactions') }}
        </button>
    </div>

    <!-- Filter & Search Bar -->
    <div class="mb-4 bg-white p-3 rounded border">
        <form action="{{ route('admin.finance.index') }}" method="GET" class="d-flex flex-wrap gap-3 align-items-end">
            <input type="hidden" name="tab" x-model="tab">
            <input type="hidden" name="sort" value="{{ $sort }}">
            <input type="hidden" name="direction" value="{{ $direction }}">
            
            <div class="flex-grow-1" style="min-width: 250px;">
                <label class="form-label small fw-medium text-secondary mb-1">Search</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-search text-secondary"></i></span>
                    <input type="text" name="search" value="{{ $search }}" class="form-control border-start-0 ps-0 bg-light" placeholder="Search by student or receipt..." x-on:input.debounce.600ms="$el.form.submit()">
                </div>
            </div>

            <div x-show="tab === 'invoices'" x-cloak>
                <label class="form-label small fw-medium text-secondary mb-1">Status</label>
                <select name="status" class="form-select bg-light" x-on:change="$el.form.submit()">
                    <option value="">All Invoices</option>
                    <option value="paid" {{ $status === 'paid' ? 'selected' : '' }}>Paid</option>
                    <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="partial" {{ $status === 'partial' ? 'selected' : '' }}>Partial</option>
                </select>
            </div>

            <div>
                <noscript><button type="submit" class="btn btn-dark">Apply</button></noscript>
                @if($search || $status || $sort !== 'id')
                    <a href="{{ route('admin.finance.index', ['tab' => request('tab', 'invoices')]) }}" class="btn btn-light border"><i class="fa-solid fa-xmark"></i> Clear</a>
                @endif
            </div>
        </form>
    </div>

    @php
        $sortLink = function($column, $label) use ($sort, $direction) {
            $dir = ($sort === $column && $direction === 'asc') ? 'desc' : 'asc';
            $icon = $sort === $column 
                ? ($direction === 'asc' ? '<i class="fa-solid fa-sort-up ms-1"></i>' : '<i class="fa-solid fa-sort-down ms-1"></i>') 
                : '<i class="fa-solid fa-sort text-muted ms-1" style="opacity: 0.3;"></i>';
            $url = request()->fullUrlWithQuery(['sort' => $column, 'direction' => $dir]);
            return '<a href="'.$url.'" class="text-dark text-decoration-none d-flex align-items-center">'.$label.$icon.'</a>';
        };
    @endphp

    <!-- Invoices Tab -->
    <div x-show="tab === 'invoices'" x-cloak>
        <div class="card card-premium">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{!! $sortLink('created_at', __('Date')) !!}</th>
                                <th>{{ __('Student') }}</th>
                                <th>{{ __('Title') }}</th>
                                <th>{!! $sortLink('amount', __('Amount')) !!}</th>
                                <th>{!! $sortLink('paid_amount', __('Paid')) !!}</th>
                                <th>{!! $sortLink('balance', __('Balance')) !!}</th>
                                <th>{{ __('Status') }}</th>
                                <th class="text-end">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($invoices as $invoice)
                            <tr>
                                <td>
                                    <div class="fw-medium">{{ $invoice->created_at->format('d M Y') }}</div>
                                    <div class="text-secondary small">{{ $invoice->type }}</div>
                                </td>
                                <td>
                                    <div class="fw-medium">{{ $invoice->student->name }}</div>
                                    <div class="text-secondary small">{{ $invoice->student->mobile }}</div>
                                </td>
                                <td>{{ $invoice->title }}</td>
                                <td class="fw-medium">{{ hostelease_money($invoice->amount) }}</td>
                                <td class="text-success">{{ hostelease_money($invoice->paid_amount) }}</td>
                                <td class="text-danger fw-bold">{{ hostelease_money($invoice->balance) }}</td>
                                <td>
                                    @if($invoice->status === 'paid')
                                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">Paid</span>
                                    @elseif($invoice->status === 'partial')
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill">Partial</span>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill">Pending</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <form action="{{ route('admin.invoices.destroy', $invoice) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this invoice?');">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-light text-danger"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <div class="empty-state">
                                        <i class="fa-solid fa-file-invoice text-secondary fs-1 mb-2"></i>
                                        <div class="text-secondary">No invoices found.</div>
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($invoices->hasPages())
            <div class="card-footer border-0 bg-white">
                {{ $invoices->appends(['tab' => 'invoices', 'search' => request('search')])->links() }}
            </div>
            @endif
        </div>
    </div>

    <!-- Transactions Tab -->
    <div x-show="tab === 'transactions'" x-cloak>
        <div class="card card-premium">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{!! $sortLink('paid_on', __('Date')) !!}</th>
                                <th>{{ __('Receipt') }}</th>
                                <th>{{ __('Student') }}</th>
                                <th>{{ __('Mode') }}</th>
                                <th>{!! $sortLink('amount', __('Amount')) !!}</th>
                                <th class="text-end">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($payments as $payment)
                            <tr>
                                <td>{{ $payment->paid_on->format('d M Y') }}</td>
                                <td class="fw-medium">{{ $payment->receipt_number }}</td>
                                <td>
                                    <div class="fw-medium">{{ $payment->student->name }}</div>
                                </td>
                                <td>
                                    <span class="text-uppercase small">{{ $payment->mode }}</span>
                                </td>
                                <td class="text-success fw-bold">{{ hostelease_money($payment->amount) }}</td>
                                <td class="text-end">
                                    <form action="{{ route('admin.payments.destroy', $payment) }}" method="POST" class="d-inline" onsubmit="return confirm('Reverse this payment? This will restore invoice balances.');">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-light text-danger"><i class="fa-solid fa-rotate-left"></i></button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <div class="empty-state">
                                        <i class="fa-solid fa-money-bill-transfer text-secondary fs-1 mb-2"></i>
                                        <div class="text-secondary">No transactions found.</div>
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($payments->hasPages())
            <div class="card-footer border-0 bg-white">
                {{ $payments->appends(['tab' => 'transactions', 'search' => request('search')])->links() }}
            </div>
            @endif
        </div>
    </div>

</div>

<!-- Add Invoice Modal -->
<div class="modal fade" id="addInvoiceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" action="{{ route('admin.invoices.store') }}" method="POST">
            @csrf
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">New Invoice</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Student</label>
                    <select name="student_id" class="form-select" required>
                        <option value="">Select Student</option>
                        @foreach($students as $student)
                            <option value="{{ $student->id }}">{{ $student->name }} ({{ $student->mobile }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Type</label>
                        <select name="type" class="form-select" required>
                            <option value="fee">Hostel Fee</option>
                            <option value="rent">Monthly Rent</option>
                            <option value="ac">AC Bill</option>
                            <option value="other">Other/Fine</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Amount</label>
                        <div class="input-group">
                            <span class="input-group-text">₹</span>
                            <input type="number" name="amount" class="form-control" required min="1" step="0.01">
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Title / Description</label>
                    <input type="text" name="title" class="form-control" required placeholder="e.g. Broken chair fine">
                </div>
                <div class="mb-0">
                    <label class="form-label">Due Date (Optional)</label>
                    <input type="date" name="due_date" class="form-control">
                </div>
            </div>
            <div class="modal-footer border-0 bg-light rounded-bottom">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-dark">Create Invoice</button>
            </div>
        </form>
    </div>
</div>

@endsection
