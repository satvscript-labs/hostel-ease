@extends('layouts.app')
@section('title', 'Customers')

@push('styles')
<style>
    .stat-card { background: rgba(255,255,255,0.85); backdrop-filter: blur(20px); border: 1px solid rgba(0,0,0,0.05); transition: all 0.3s cubic-bezier(0.25,1,0.5,1); }
    .stat-card:hover { transform: translateY(-3px); box-shadow: 0 12px 30px rgba(0,0,0,0.05) !important; }
    .stat-value { font-size: 1.75rem; font-weight: 800; letter-spacing: -0.5px; }
    .stat-label { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; }
    .cust-row { transition: background-color 0.2s ease; cursor: pointer; }
    .cust-row:hover { background-color: #f8fafc !important; }
    .anchor-pill { font-variant-numeric: tabular-nums; }
</style>
@endpush

@section('content')
<div class="page-enter">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark tracking-tight">Customers</h1>
            <p class="text-muted mb-0 small">One account per owner — quantity-based billing on a single renewal date.</p>
        </div>
    </div>

    {{-- Summary --}}
    <div class="row g-3 mb-4 stagger">
        <div class="col-6 col-md-3"><div class="card stat-card shadow-sm rounded-4"><div class="card-body py-4"><div class="stat-value text-primary mb-1">{{ $summary['accounts'] }}</div><div class="stat-label">Accounts</div></div></div></div>
        <div class="col-6 col-md-3"><div class="card stat-card shadow-sm rounded-4"><div class="card-body py-4"><div class="stat-value text-success mb-1">{{ $summary['active'] }}</div><div class="stat-label">Active</div></div></div></div>
        <div class="col-6 col-md-3"><div class="card stat-card shadow-sm rounded-4"><div class="card-body py-4"><div class="stat-value text-warning mb-1">{{ $summary['due_30'] }}</div><div class="stat-label">Renewals due · 30d</div></div></div></div>
        <div class="col-6 col-md-3"><div class="card stat-card shadow-sm rounded-4"><div class="card-body py-4"><div class="stat-value text-dark mb-1">{{ hostelease_money($summary['revenue']) }}</div><div class="stat-label">Lifetime Revenue</div></div></div></div>
    </div>

    {{-- Filters — ONE canonical no-search row (§4.5): two matching he-selects
         (Renewals + Status), both auto-submitting the form. Fragment-driven so
         ONLY #cust-list swaps (W12 fix): the controls live OUTSIDE the swapped
         region, so their submit listeners survive — the old status form sat
         inside a swapped target, lost its listener, and hard-reloaded. Both
         filters combine (one form → both params). --}}
    <div class="mb-3" style="position:relative; z-index:30;">
        {{-- Natural-width, grouped left (NOT .he-filters--nosearch, which
             stretches each filter to fill the row — with only two it left a
             dead gap on the right that read as "separate"). --}}
        <form method="GET" action="{{ route('superadmin.accounts.index') }}" data-fragment="#cust-list"
              class="d-flex flex-wrap gap-2 align-items-center">
            <x-he-select name="due" icon="rotate" label="Renewals" :selected="(string) request('due', '')"
                :options="[
                    '' => ['label' => __('All renewals'), 'icon' => 'rotate'],
                    '7' => ['label' => __('Due ≤ 7 days'), 'icon' => 'bolt'],
                    '30' => ['label' => __('Due ≤ 30 days'), 'icon' => 'clock'],
                ]" />
            <x-he-select name="status" icon="filter" label="Status" :selected="request('status', '')"
                :options="[
                    '' => ['label' => __('All statuses'), 'icon' => 'filter'],
                    'active' => ['label' => __('Active'), 'icon' => 'circle-check'],
                    'grace' => ['label' => __('Grace'), 'icon' => 'hourglass-half'],
                    'expired' => ['label' => __('Expired'), 'icon' => 'circle-xmark'],
                    'trial' => ['label' => __('Trial'), 'icon' => 'gift'],
                    'suspended' => ['label' => __('Suspended'), 'icon' => 'ban'],
                ]" />
        </form>
    </div>

    <div id="cust-list" data-fragment-container class="card stat-card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.5px;"><tr>
                    <th class="py-3 px-4 border-0">Owner</th>
                    <th class="py-3 px-4 border-0 text-center">Branches</th>
                    <th class="py-3 px-4 border-0 text-center">Status</th>
                    <th class="py-3 px-4 border-0">Renews on</th>
                    <th class="py-3 px-4 border-0 text-end">Lifetime value</th>
                    <th class="py-3 px-4 border-0 text-end"></th>
                </tr></thead>
                <tbody class="border-top-0">
                @forelse($accounts as $account)
                    <tr class="cust-row" onclick="window.location='{{ route('superadmin.accounts.show', $account) }}'">
                        <td class="px-4 py-3">
                            <div class="fw-bold text-dark">{{ $account->owner?->name ?? 'Unknown owner' }}</div>
                            <div class="small text-muted"><i class="fa-solid fa-mobile-screen text-primary me-1"></i>{{ $account->owner?->mobile ?? '—' }}</div>
                        </td>
                        <td class="px-4 py-3 text-center fw-bold text-dark">{{ $account->branch_count }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="badge bg-{{ $account->status->color() }}-subtle text-{{ $account->status->color() }} border border-{{ $account->status->color() }}-subtle rounded-pill px-3 py-2">{{ $account->status->label() }}</span>
                        </td>
                        <td class="px-4 py-3 anchor-pill">
                            <div class="fw-medium text-dark">{{ $account->current_period_end ? $account->current_period_end->format('d M Y') : '—' }}</div>
                            @if($account->days_until !== null && $account->status->value !== 'suspended')
                                @php($du = $account->days_until)
                                <div class="small mt-1">
                                    @if($du < 0)
                                        <span class="text-danger fw-semibold"><i class="fa-solid fa-circle-exclamation me-1"></i>{{ abs($du) }}d overdue</span>
                                    @elseif($du === 0)
                                        <span class="text-danger fw-semibold"><i class="fa-solid fa-bolt me-1"></i>Due today</span>
                                    @elseif($du <= 7)
                                        <span class="text-warning fw-semibold"><i class="fa-solid fa-clock me-1"></i>in {{ $du }} days</span>
                                    @elseif($du <= 30)
                                        <span class="text-muted"><i class="fa-regular fa-clock me-1"></i>in {{ $du }} days</span>
                                    @endif
                                </div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-end fw-bold text-dark">{{ hostelease_money($account->ltv) }}</td>
                        <td class="px-4 py-3 text-end"><i class="fa-solid fa-chevron-right text-muted small"></i></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-0">
                        <x-he-empty-state icon="users-gear" title="No customer accounts yet"
                            subtitle="Accounts are created automatically when an owner's first branch is billed." />
                    </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($accounts->hasPages())
            <div class="p-3 border-top">{{ $accounts->withQueryString()->links() }}</div>
        @endif
    </div>
</div>
@endsection
