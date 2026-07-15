@extends('layouts.app')
@section('title', 'Account · '.($account->owner?->name ?? 'Customer'))

@php
    $unitYearly = $account->unit_price_override_yearly ?? config('hostelease.subscription_pricing.yearly', 10000);
    $unitMonthly = $account->unit_price_override_monthly ?? config('hostelease.subscription_pricing.monthly', 1000);
    $customYearly = $account->unit_price_override_yearly !== null;
    $customMonthly = $account->unit_price_override_monthly !== null;
@endphp

@push('styles')
<style>
    .a360-hero { background: var(--he-gradient-mesh, linear-gradient(135deg,#0f172a,#1e1b4b)); color:#fff; border-radius: 1.25rem; position: relative; }
    /* Decorative glow lives in its own clipped layer so the hero itself can let the ... menu overflow. */
    .a360-hero-bg { position:absolute; inset:0; border-radius:inherit; overflow:hidden; z-index:0; pointer-events:none; }
    .a360-hero-bg::after { content:''; position:absolute; top:-40%; right:-10%; width:380px; height:380px; background: radial-gradient(circle, rgba(147,51,234,0.35), transparent 70%); }
    .a360-hero .dropdown-menu { z-index: 1080; }
    .a360-metric { font-variant-numeric: tabular-nums; }
    /* .panel-card / .panel-head / .panel-body are canonical in _premium.scss — do not redeclare. */
    .rec-pill { font-size:.68rem; font-weight:700; letter-spacing:.3px; }
    .branch-link:hover { color: var(--he-primary, #4f46e5) !important; }
    .custom-overlay-backdrop { position: fixed; inset: 0; background: rgba(15,23,42,0.6); backdrop-filter: blur(8px); z-index: 9999; display:flex; align-items:center; justify-content:center; padding:1rem; }
    .custom-overlay-modal { width:100%; background:#fff; border-radius:1.25rem; box-shadow:0 25px 50px -12px rgba(0,0,0,.25); display:flex; flex-direction:column; max-height:90vh; transform:scale(.95); opacity:0; transition:all .3s cubic-bezier(.16,1,.3,1); overflow:hidden; }
    .custom-overlay-modal.is-open { transform:scale(1); opacity:1; }
    .custom-overlay-header { padding:1.25rem 1.5rem; border-bottom:1px solid rgba(0,0,0,.05); display:flex; justify-content:space-between; align-items:center; }
    .custom-overlay-body { padding:1.5rem; overflow-y:auto; background:#fafafa; }
    .custom-overlay-footer { padding:1.25rem 1.5rem; border-top:1px solid rgba(0,0,0,.05); display:flex; gap:1rem; justify-content:flex-end; }

    /* ── Expandable Orders & payments rows ── */
    .order-table tbody.order-group { border-top: 1px solid rgba(15,23,42,.05); }
    .order-table tbody.order-group:first-of-type { border-top: 0; }
    .order-row { cursor: pointer; transition: background-color .18s ease; }
    .order-row:hover { background-color: #f8fafc; }
    .order-row.is-open { background-color: var(--he-primary-soft, rgba(79,70,229,.08)); }
    .order-caret { transition: transform .25s var(--ease-out-expo, cubic-bezier(.16,1,.3,1)); font-size:.8rem; }
    .order-caret.rotated { transform: rotate(90deg); color: var(--he-primary, #4f46e5); }
    .order-detail-row td { background: var(--he-bg-surface-raised, #f1f5f9); }
    .order-detail { padding: 1.1rem 1.5rem 1.35rem 3rem; }
    .od-label { font-size:.66rem; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color: var(--he-text-muted, #64748b); margin-bottom:.5rem; }
    .od-box { background:#fff; border:1px solid rgba(15,23,42,.07); border-radius: var(--he-radius-md, 10px); overflow:hidden; }
    .od-line { display:flex; justify-content:space-between; gap:1rem; padding:.5rem .85rem; font-size:.86rem; }
    .od-line + .od-line { border-top:1px dashed rgba(15,23,42,.07); }
    .od-line--total { font-weight:800; color: var(--he-text-main, #0f172a); background: var(--he-primary-soft, rgba(79,70,229,.06)); }
    .od-meta { display:grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap:.6rem 1.25rem; background:#fff; border:1px solid rgba(15,23,42,.07); border-radius: var(--he-radius-md, 10px); padding:.85rem 1rem; }
    .od-meta > div { display:flex; flex-direction:column; gap:.1rem; min-width:0; }
    .od-k { font-size:.66rem; text-transform:uppercase; letter-spacing:.5px; color: var(--he-text-muted, #64748b); font-weight:700; }
    .od-v { font-size:.86rem; font-weight:600; color: var(--he-text-main, #0f172a); word-break:break-word; }
    .od-remarks { margin-top:.6rem; font-size:.84rem; color: var(--he-text-main, #334155); background:#fff; border:1px solid rgba(15,23,42,.07); border-radius: var(--he-radius-md, 10px); padding:.55rem .85rem; }
    .od-lines { background:#fff; border:1px solid rgba(15,23,42,.07); border-radius: var(--he-radius-md, 10px); overflow:hidden; }
    .od-line-row { display:flex; align-items:center; justify-content:space-between; gap:1rem; padding:.6rem .95rem; flex-wrap:wrap; }
    .od-line-row + .od-line-row { border-top:1px solid rgba(15,23,42,.05); }
    .od-line-row .od-coverage { flex:1; text-align:center; min-width:150px; }

    /* ── Comp modal — stepper, branch tiles, gift preview ── */
    .comp-stepper { display:flex; align-items:stretch; border:1px solid rgba(15,23,42,.12); border-radius: var(--he-radius-full, 9999px); overflow:hidden; background:#fff; }
    .comp-step { border:0; background:#fff; width:44px; color: var(--he-primary, #4f46e5); font-size:.9rem; display:flex; align-items:center; justify-content:center; }
    .comp-step:hover { background: var(--he-primary-soft, rgba(79,70,229,.08)); }
    .comp-step-input { border:0; text-align:center; width:100%; font-weight:800; font-size:1.1rem; color: var(--he-text-main,#0f172a); -moz-appearance:textfield; }
    .comp-step-input::-webkit-outer-spin-button, .comp-step-input::-webkit-inner-spin-button { -webkit-appearance:none; margin:0; }
    .comp-step-input:focus { outline:0; }
    .comp-tile { display:flex; align-items:center; gap:.7rem; text-align:left; background:#fff; border:1.5px solid rgba(15,23,42,.1); border-radius: var(--he-radius-md, 10px); padding:.65rem .8rem; transition: all .2s var(--ease-out-expo, cubic-bezier(.16,1,.3,1)); }
    .comp-tile:hover { border-color: rgba(79,70,229,.4); }
    .comp-tile.is-selected { border-color: var(--he-primary, #4f46e5); background: var(--he-primary-soft, rgba(79,70,229,.07)); box-shadow: 0 4px 12px rgba(79,70,229,.1); }
    .comp-tile-check { width:22px; height:22px; flex-shrink:0; border-radius:50%; border:1.5px solid rgba(15,23,42,.2); display:flex; align-items:center; justify-content:center; color:#fff; font-size:.65rem; transition: all .2s; }
    .comp-tile.is-selected .comp-tile-check { background: var(--he-primary, #4f46e5); border-color: var(--he-primary, #4f46e5); }
    .comp-tile-check i { opacity:0; transition:opacity .2s; }
    .comp-tile.is-selected .comp-tile-check i { opacity:1; }
    .comp-tile-name { display:block; font-weight:700; color: var(--he-text-main,#0f172a); font-size:.88rem; line-height:1.2; }
    .comp-tile-end { display:block; font-size:.72rem; color: var(--he-text-muted,#64748b); }
    .comp-preview { background: var(--he-bg-surface-raised, #f1f5f9); border:1px solid rgba(15,23,42,.07); border-radius: var(--he-radius-lg, 16px); overflow:hidden; }
    .comp-preview-head { display:flex; align-items:center; flex-wrap:wrap; gap:.4rem; padding:.7rem 1rem; font-size:.86rem; color: var(--he-text-main,#0f172a); border-bottom:1px solid rgba(15,23,42,.06); background:#fff; }
    .comp-preview-badge { margin-left:auto; font-size:.72rem; font-weight:700; color: var(--he-success,#10b981); background: var(--he-success-soft,#d1fae5); border-radius: var(--he-radius-full,9999px); padding:.2rem .6rem; }
    .comp-preview-row { display:flex; justify-content:space-between; align-items:center; gap:1rem; padding:.5rem 1rem; }
    .comp-preview-row + .comp-preview-row { border-top:1px dashed rgba(15,23,42,.07); }
</style>
@endpush

@section('content')
<div class="page-enter" x-data="account360()">
    <a href="{{ route('superadmin.accounts.index') }}" class="btn btn-sm btn-light rounded-pill px-3 mb-3 shadow-sm"><i class="fa-solid fa-arrow-left me-1"></i> Customers</a>

    {{-- ── Header band ── --}}
    <div class="a360-hero p-4 p-md-4 mb-4 shadow">
        <div class="a360-hero-bg"></div>
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 position-relative" style="z-index:3;">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <h1 class="h3 fw-bold mb-0">{{ $account->owner?->name ?? 'Customer' }}</h1>
                    <span class="badge bg-{{ $account->status->color() }}-subtle text-{{ $account->status->color() }} rounded-pill px-3 py-2">{{ $account->status->label() }}</span>
                </div>
                <div class="text-white-50 small">
                    <i class="fa-solid fa-mobile-screen me-1"></i>{{ $account->owner?->mobile ?? '—' }}
                    @if($account->owner?->email)<span class="mx-2">·</span><i class="fa-solid fa-envelope me-1"></i>{{ $account->owner->email }}@endif
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-light rounded-pill px-4 fw-bold shadow-sm" @click="renewOpen = true"><i class="fa-solid fa-arrows-rotate me-2"></i>Renew all</button>
                @if($alignBehind > 0)
                <button class="btn btn-outline-light rounded-pill px-3 fw-bold" @click="alignOpen = true"><i class="fa-solid fa-diagram-project me-2"></i>Align ({{ $alignBehind }})</button>
                @endif
                <button class="btn btn-outline-light rounded-pill px-3 fw-bold" @click="addHostelOpen = true"><i class="fa-solid fa-building-circle-arrow-right me-2"></i>Add hostel</button>
                <div class="dropdown">
                    <button class="btn btn-outline-light rounded-pill px-3 fw-bold" data-bs-toggle="dropdown"><i class="fa-solid fa-ellipsis"></i></button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-4 p-2">
                        <li><button class="dropdown-item rounded-3 py-2" @click="compOpen = true"><i class="fa-solid fa-gift text-primary me-2"></i>Comp (free coverage)</button></li>
                        <li><button class="dropdown-item rounded-3 py-2" @click="overrideOpen = true"><i class="fa-solid fa-tag text-primary me-2"></i>Set custom price</button></li>
                        <li><button class="dropdown-item rounded-3 py-2" @click="discountOpen = true"><i class="fa-solid fa-percent text-primary me-2"></i>Add discount</button></li>
                        <li><hr class="dropdown-divider"></li>
                        @if($account->status->value === 'suspended')
                            <li>
                                <form method="POST" action="{{ route('superadmin.accounts.reactivate', $account) }}" data-confirm="Reactivate this account and all its branches?">
                                    @csrf<button class="dropdown-item rounded-3 py-2 text-success"><i class="fa-solid fa-play me-2"></i>Reactivate</button>
                                </form>
                            </li>
                        @else
                            <li><button class="dropdown-item rounded-3 py-2 text-danger" @click="suspendOpen = true"><i class="fa-solid fa-ban me-2"></i>Suspend account</button></li>
                        @endif
                    </ul>
                </div>
            </div>
        </div>
        <div class="row g-3 mt-2 position-relative" style="z-index:1;">
            <div class="col-6 col-md-3"><div class="text-white-50 small text-uppercase" style="letter-spacing:.5px;">Branches</div><div class="h4 fw-bold mb-0 a360-metric">{{ $branches->count() }}</div></div>
            <div class="col-6 col-md-3"><div class="text-white-50 small text-uppercase" style="letter-spacing:.5px;">Renews on</div><div class="h4 fw-bold mb-0 a360-metric">{{ $account->current_period_end ? $account->current_period_end->format('d M Y') : '—' }}</div></div>
            <div class="col-6 col-md-3"><div class="text-white-50 small text-uppercase" style="letter-spacing:.5px;">Term</div><div class="h4 fw-bold mb-0">{{ $account->period?->label() ?? 'Yearly' }}</div></div>
            <div class="col-6 col-md-3">
                <div class="text-white-50 small text-uppercase" style="letter-spacing:.5px;">Unit price</div>
                <div class="h4 fw-bold mb-0 a360-metric">{{ hostelease_money($unitYearly) }}<span class="fs-6 fw-normal text-white-50">/yr</span>
                    @if($customYearly)<span class="badge bg-warning-subtle text-warning rounded-pill ms-1" style="font-size:.6rem;">custom</span>@endif
                </div>
                <div class="text-white-50" style="font-size:.72rem;">
                    {{ hostelease_money($unitMonthly) }}/mo
                    @if($customMonthly)<span class="badge bg-warning-subtle text-warning rounded-pill" style="font-size:.55rem;">custom</span>@endif
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        {{-- ── Branches ── --}}
        <div class="col-lg-5">
            <div class="panel-card shadow-sm h-100">
                <div class="p-3 px-4 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-hotel text-primary me-2"></i>Branches</h6>
                    <span class="text-muted small">{{ $branches->count() }} total</span>
                </div>
                <div class="stagger">
                    @forelse($branches as $branch)
                        @php($behind = $account->current_period_end && $account->current_period_end->isFuture() && (! $branch->subscription_end || $branch->subscription_end->lt($account->current_period_end)))
                        <div class="d-flex justify-content-between align-items-center px-4 py-3 border-bottom">
                            <div>
                                <a href="{{ route('superadmin.hostels.show', $branch) }}" class="fw-bold text-dark text-decoration-none branch-link" title="Open hostel profile">
                                    {{ $branch->name }} <i class="fa-solid fa-arrow-up-right-from-square text-muted ms-1" style="font-size:.6rem;"></i>
                                </a>
                                <div class="small text-muted">Ends {{ $branch->subscription_end ? $branch->subscription_end->format('d M Y') : '—' }}</div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                @if($branch->isActive())
                                    <span class="badge bg-success-subtle text-success rounded-pill px-3 py-2">Active</span>
                                @else
                                    <span class="badge bg-danger-subtle text-danger rounded-pill px-3 py-2">Expired</span>
                                @endif
                                @if($behind)
                                    <button class="btn btn-sm btn-light text-primary rounded-pill px-3 fw-semibold shadow-sm" @click="openAdd({{ $branch->id }}, @js($branch->name))" title="Prorate to the renewal date"><i class="fa-solid fa-plus me-1"></i>Add to cycle</button>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="p-4"><x-he-empty-state icon="hotel" title="No branches" subtitle="This owner has no branches yet." /></div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- ── Discounts ── --}}
        <div class="col-lg-7">
            <div class="panel-card shadow-sm h-100">
                <div class="p-3 px-4 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-percent text-primary me-2"></i>Discounts</h6>
                    <button class="btn btn-sm btn-light text-primary rounded-pill px-3 fw-semibold shadow-sm" @click="discountOpen = true"><i class="fa-solid fa-plus me-1"></i>Add</button>
                </div>
                @forelse($discounts as $discount)
                    <div class="d-flex justify-content-between align-items-center px-4 py-3 border-bottom">
                        <div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="fw-bold text-dark">
                                    {{ $discount->type->value === 'percentage' ? rtrim(rtrim(number_format($discount->value,2),'0'),'.').'%' : hostelease_money($discount->value) }} off
                                </span>
                                <span class="badge bg-primary-subtle text-primary rounded-pill rec-pill px-2 py-1">{{ $discount->recurrence->label() }}</span>
                                @if($discount->status->value !== 'active')
                                    <span class="badge bg-secondary-subtle text-secondary rounded-pill rec-pill px-2 py-1">{{ $discount->status->label() }}</span>
                                @endif
                            </div>
                            <div class="small text-muted">{{ $discount->reason }}</div>
                        </div>
                        @if($discount->status->value === 'active')
                        <form method="POST" action="{{ route('superadmin.accounts.discounts.revoke', [$account, $discount]) }}" data-confirm="Revoke this discount?">
                            @csrf @method('DELETE')<button class="btn btn-sm btn-light rounded-circle shadow-sm" style="width:32px;height:32px;" title="Revoke"><i class="fa-solid fa-xmark text-danger"></i></button>
                        </form>
                        @endif
                    </div>
                @empty
                    <div class="p-4"><x-he-empty-state icon="percent" title="No discounts" subtitle="Negotiated discounts for this customer appear here." /></div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- ── Payment history ── --}}
    <div class="panel-card shadow-sm mt-4">
        <div class="p-3 px-4 border-bottom"><h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-receipt text-primary me-2"></i>Orders &amp; payments</h6></div>
        <div class="table-responsive">
            <table class="table align-middle mb-0 order-table">
                <thead class="table-light text-uppercase" style="font-size:.7rem; letter-spacing:.5px;"><tr>
                    <th class="py-3 ps-4 pe-2 border-0" style="width:32px;"></th>
                    <th class="py-3 px-2 border-0">Amount</th>
                    <th class="py-3 px-2 border-0 text-center">Status</th>
                    <th class="py-3 px-2 border-0 text-center">Qty</th>
                    <th class="py-3 px-2 border-0">Term</th>
                    <th class="py-3 px-2 border-0">Method</th>
                    <th class="py-3 px-4 border-0 text-end">Date</th>
                </tr></thead>
                @forelse($orders as $order)
                    @php($statusColor = $order->payment_status->value === 'paid' ? 'success' : ($order->payment_status->value === 'pending' ? 'warning' : 'danger'))
                    <tbody class="order-group" x-data="{ open: false }">
                        <tr class="order-row" @click="open = !open" :class="{ 'is-open': open }">
                            <td class="ps-4 pe-2 py-3"><i class="fa-solid fa-chevron-right order-caret text-muted" :class="{ 'rotated': open }"></i></td>
                            <td class="px-2 py-3">
                                <div class="fw-bold a360-metric {{ (float)$order->amount == 0 ? 'text-secondary' : 'text-dark' }}">{{ hostelease_money($order->amount) }}</div>
                                @if((float)$order->discount_total > 0)<div class="small text-success fw-semibold">−{{ hostelease_money($order->discount_total) }} discount</div>@endif
                            </td>
                            <td class="px-2 py-3 text-center"><span class="badge bg-{{ $statusColor }}-subtle text-{{ $statusColor }} rounded-pill px-3 py-1">{{ $order->payment_status->label() }}</span></td>
                            <td class="px-2 py-3 text-center fw-bold">{{ $order->quantity }}</td>
                            <td class="px-2 py-3">{{ $order->period?->label() ?? '—' }}</td>
                            <td class="px-2 py-3">{{ $order->payment_method?->label() ?? '—' }}</td>
                            <td class="px-4 py-3 text-muted small text-end text-nowrap">{{ $order->created_at?->format('d M Y') }}</td>
                        </tr>
                        <tr class="order-detail-row">
                            <td colspan="7" class="p-0 border-0">
                                <div x-show="open" x-collapse x-cloak>
                                    <div class="order-detail">
                                        <div class="row g-3">
                                            {{-- Charge breakdown --}}
                                            <div class="col-md-4">
                                                <div class="od-label">Charge breakdown</div>
                                                <div class="od-box">
                                                    <div class="od-line"><span>Subtotal</span><span class="a360-metric">{{ hostelease_money($order->subtotal) }}</span></div>
                                                    @if((float)$order->discount_total > 0)
                                                        <div class="od-line text-success"><span>Discount</span><span class="a360-metric">−{{ hostelease_money($order->discount_total) }}</span></div>
                                                    @endif
                                                    <div class="od-line od-line--total"><span>{{ (float)$order->amount == 0 ? 'Complimentary' : 'Total paid' }}</span><span class="a360-metric">{{ hostelease_money($order->amount) }}</span></div>
                                                </div>
                                            </div>
                                            {{-- Meta --}}
                                            <div class="col-md-8">
                                                <div class="od-label">Details</div>
                                                <div class="od-meta">
                                                    <div><span class="od-k">Order</span><span class="od-v">#{{ $order->id }}</span></div>
                                                    <div><span class="od-k">Placed</span><span class="od-v">{{ $order->created_at?->format('d M Y · h:i A') ?? '—' }}</span></div>
                                                    <div><span class="od-k">Term</span><span class="od-v">{{ $order->period?->label() ?? '—' }}</span></div>
                                                    <div><span class="od-k">Method</span><span class="od-v">{{ $order->payment_method?->label() ?? '—' }}</span></div>
                                                    <div><span class="od-k">Status</span><span class="od-v text-{{ $statusColor }}">{{ $order->payment_status->label() }}</span></div>
                                                    <div><span class="od-k">Branches</span><span class="od-v">{{ $order->quantity }}</span></div>
                                                    @if($order->transaction_number)<div><span class="od-k">Txn / Ref</span><span class="od-v font-monospace">{{ $order->transaction_number }}</span></div>@endif
                                                    @if($order->razorpay_order_id)<div><span class="od-k">Razorpay</span><span class="od-v font-monospace">{{ $order->razorpay_order_id }}</span></div>@endif
                                                    @if($order->legacy_subscription_id)<div><span class="od-k">Migrated</span><span class="od-v">legacy #{{ $order->legacy_subscription_id }}</span></div>@endif
                                                </div>
                                                @if($order->remarks)
                                                    <div class="od-remarks"><i class="fa-solid fa-quote-left text-muted me-2 small"></i>{{ $order->remarks }}</div>
                                                @endif
                                            </div>
                                            {{-- Per-branch coverage --}}
                                            <div class="col-12">
                                                <div class="od-label">Branch coverage ({{ $order->lines->count() }})</div>
                                                @if($order->lines->isEmpty())
                                                    <div class="text-muted small fst-italic">No branch lines recorded for this order.</div>
                                                @else
                                                    <div class="od-lines">
                                                        @foreach($order->lines as $line)
                                                            <div class="od-line-row">
                                                                <div class="fw-semibold text-dark"><i class="fa-solid fa-hotel text-primary me-2 small"></i>{{ $line->branch?->name ?? 'Branch #'.$line->branch_id }}</div>
                                                                <div class="od-coverage small text-muted">
                                                                    {{ optional($line->start_date)->format('d M Y') ?? '—' }}
                                                                    <i class="fa-solid fa-arrow-right-long mx-1" style="font-size:.7rem;"></i>
                                                                    {{ optional($line->end_date)->format('d M Y') ?? '—' }}
                                                                </div>
                                                                <div class="fw-bold a360-metric text-dark">{{ hostelease_money($line->amount) }}</div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                @empty
                    <tbody><tr><td colspan="7" class="p-0"><x-he-empty-state icon="receipt" title="No orders yet" subtitle="Renewals and payments will appear here." /></td></tr></tbody>
                @endforelse
            </table>
        </div>
        @if($orders->hasPages())<div class="p-3 border-top">{{ $orders->withQueryString()->links() }}</div>@endif
    </div>

    {{-- ══ Billing modals (redesigned — shared shell + live summary) ══ --}}
    @include('superadmin.accounts._billing_modals')

    {{-- ══ Modals ══ --}}
    <template x-teleport="body">
        <div>
            {{-- Suspend --}}
            <div class="custom-overlay-backdrop" x-show="suspendOpen" x-transition.opacity @click.self="suspendOpen=false" x-cloak style="display:none;">
                <form method="POST" action="{{ route('superadmin.accounts.suspend', $account) }}" class="custom-overlay-modal" style="max-width:480px;" :class="{'is-open':suspendOpen}">
                    @csrf
                    <div class="custom-overlay-header"><h5 class="fw-bold mb-0">Suspend account</h5><button type="button" class="btn-close" @click="suspendOpen=false"></button></div>
                    <div class="custom-overlay-body">
                        <p class="text-danger small"><i class="fa-solid fa-triangle-exclamation me-1"></i>Blocks every branch on this account immediately, regardless of remaining coverage. Only an explicit Reactivate lifts it — renewals will not clear it.</p>
                        <label class="form-label fw-bold small text-muted">REASON</label>
                        <input type="text" name="reason" class="form-control bg-white border shadow-sm" placeholder="Why is this account being suspended?" required>
                    </div>
                    <div class="custom-overlay-footer"><button type="button" class="btn btn-light rounded-pill px-4 fw-bold" @click="suspendOpen=false">Cancel</button><button class="btn btn-danger rounded-pill px-5 fw-bold shadow-sm">Suspend</button></div>
                </form>
            </div>

        </div>
    </template>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('account360', () => ({
        renewOpen: false, addOpen: false, alignOpen: false, compOpen: false, overrideOpen: false, discountOpen: false, suspendOpen: false, addHostelOpen: false,
        dType: 'percentage',

        r2(v) { return Math.round(v * 100) / 100; },

        // Builds {rows, finalLabel, final, note} for a single-subtotal charge
        // (Renew, Add): list line → engine discounts → optional override adjustment.
        buildSummary({ lineLabel, subtotal, volume, manual, auto, override, note }) {
            subtotal = subtotal || 0; volume = volume || 0; manual = manual || 0;
            auto = (auto === undefined || auto === null) ? subtotal : auto;
            const rows = [{ label: lineLabel, amount: subtotal, kind: 'line' }];
            if (volume > 0) rows.push({ label: 'Volume tier discount', amount: volume, kind: 'discount' });
            if (manual > 0) rows.push({ label: 'Negotiated discount', amount: manual, kind: 'discount' });
            let final = auto;
            const ov = parseFloat(override);
            if (override !== '' && override !== null && !isNaN(ov)) {
                if (ov < auto) {
                    if (volume > 0 || manual > 0) rows.push({ label: 'Auto price', amount: auto, kind: 'subtle' });
                    const adj = this.r2(auto - ov);
                    const pct = auto > 0 ? (adj / auto * 100) : 0;
                    rows.push({ label: 'Manual adjustment (−' + pct.toFixed(1) + '%)', amount: adj, kind: 'discount' });
                }
                final = ov;
            }
            return { rows, finalLabel: 'Payable now', final, note };
        },

        // ── Renew all ──
        period: @json($displayPeriod),
        renewQuotes: @json($renewQuotes),
        renewOverride: '',
        get renewSummary() {
            const q = this.renewQuotes[this.period] || {};
            return this.buildSummary({
                lineLabel: (q.quantity || 0) + ' branch(es) × ' + heMoney(q.unit) + '/' + (this.period === 'monthly' ? 'mo' : 'yr'),
                subtotal: q.subtotal, volume: q.volume, manual: q.manual, auto: q.auto,
                override: this.renewOverride,
                note: q.quantity ? ('Renews all branches to ' + q.new_anchor) : '',
            });
        },

        // ── Add to cycle ──
        addBranchId: null, addBranchName: '', addOverride: '',
        addQuotes: @json($addQuotes),
        openAdd(id, name) { this.addBranchId = id; this.addBranchName = name; this.addOverride = ''; this.addOpen = true; },
        get addQuote() { return this.addQuotes[this.addBranchId] || {}; },
        get addSummary() {
            const q = this.addQuote;
            return this.buildSummary({
                lineLabel: 'Prorated · ' + (q.days || 0) + ' day(s) remaining',
                subtotal: q.prorated, volume: q.volume, manual: q.manual, auto: q.auto,
                override: this.addOverride,
                note: '',
            });
        },

        // ── Align ──
        alignQuote: @json($alignQuote),
        alignOverride: '',
        get alignSummary() {
            const q = this.alignQuote;
            const rows = (q.lines || []).map(l => ({ label: l.name + ' · ' + l.days + 'd', amount: l.amount, kind: 'line' }));
            const subtotal = q.subtotal || 0;
            let final = subtotal;
            const ov = parseFloat(this.alignOverride);
            if (this.alignOverride !== '' && !isNaN(ov)) {
                if (ov < subtotal) {
                    rows.push({ label: 'Subtotal', amount: subtotal, kind: 'subtle' });
                    const adj = this.r2(subtotal - ov);
                    const pct = subtotal > 0 ? (adj / subtotal * 100) : 0;
                    rows.push({ label: 'Manual adjustment (−' + pct.toFixed(1) + '%)', amount: adj, kind: 'discount' });
                }
                final = ov;
            }
            return { rows, finalLabel: 'Payable now', final, note: q.count ? ('Aligns ' + q.count + ' branch(es) to ' + q.anchor) : '' };
        },

        // ── Comp ──
        compTerm: 'yearly',
        compMultiplier: 1,
        compBranches: @json($compBranches),
        compSelected: @json($compBranchIds),
        toggleCompBranch(id) {
            const i = this.compSelected.indexOf(id);
            if (i === -1) this.compSelected.push(id); else this.compSelected.splice(i, 1);
        },
        get compAllSelected() { return this.compBranches.length > 0 && this.compSelected.length === this.compBranches.length; },
        toggleCompAll() { this.compSelected = this.compAllSelected ? [] : this.compBranches.map(b => b.id); },
        get compMultiplierLabel() {
            const n = parseInt(this.compMultiplier) || 1;
            const unit = this.compTerm === 'monthly' ? 'month' : 'year';
            return n + ' ' + unit + (n > 1 ? 's' : '');
        },
        compNewEnd(b) {
            const today = new Date(); today.setHours(0, 0, 0, 0);
            let from = b.end ? new Date(b.end + 'T00:00:00') : today;
            if (from < today) from = today;
            const n = parseInt(this.compMultiplier) || 1;
            const d = new Date(from);
            if (this.compTerm === 'yearly') d.setFullYear(d.getFullYear() + n); else d.setMonth(d.getMonth() + n);
            return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
        },
        get compPreview() {
            return this.compBranches
                .filter(b => this.compSelected.includes(b.id))
                .map(b => ({ name: b.name, from: b.endLabel, to: this.compNewEnd(b) }));
        },

        // ── Add hostel to owner ── (Paid co-terminate at the account cadence, or Trial)
        ahPlan: 'paid',
        ahOverride: '',
        ahPaidPeriod: @json($paidPeriod),
        addHostelQuote: @json($addHostelQuote),
        get addHostelSummary() {
            if (this.ahPlan === 'trial') {
                return { rows: [{ label: '14-day free trial', amount: 0, kind: 'line' }], finalLabel: 'Payable now', final: 0, note: 'Starts a 14-day trial from today (own clock, not co-terminated)' };
            }
            const q = this.addHostelQuote;
            const rows = [];
            const basis = q.mode === 'prorate' ? (q.days + ' days · prorated to anchor') : 'Full ' + this.ahPaidPeriod + ' term';
            rows.push({ label: '1 new branch · ' + basis, amount: q.prorated, kind: 'line' });
            if (q.volume > 0) rows.push({ label: 'Volume tier', amount: q.volume, kind: 'discount' });
            if (q.manual > 0) rows.push({ label: 'Negotiated discount', amount: q.manual, kind: 'discount' });
            let final = q.auto;
            const override = parseFloat(this.ahOverride);
            if (this.ahOverride !== '' && !isNaN(override) && override < q.auto) {
                rows.push({ label: 'Auto price', amount: q.auto, kind: 'subtle' });
                const adj = Math.round((q.auto - override) * 100) / 100;
                const pct = q.auto > 0 ? Math.round(adj / q.auto * 1000) / 10 : 0;
                rows.push({ label: 'Manual adjustment (−' + pct + '%)', amount: adj, kind: 'discount' });
                final = override;
            } else if (this.ahOverride !== '' && !isNaN(override)) {
                final = override;
            }
            const note = q.mode === 'prorate' ? ('Co-terminates on ' + q.anchor) : 'New full term from today';
            return { rows, finalLabel: 'Payable now', final, note };
        },

        // ── Custom unit price (per-period) ──
        priceTab: 'yearly',
        priceYearly: @json($account->unit_price_override_yearly !== null ? (float) $account->unit_price_override_yearly : ''),
        priceMonthly: @json($account->unit_price_override_monthly !== null ? (float) $account->unit_price_override_monthly : ''),
        listYearly: {{ (float) config('hostelease.subscription_pricing.yearly', 10000) }},
        listMonthly: {{ (float) config('hostelease.subscription_pricing.monthly', 1000) }},
        priceEffective(term) {
            const custom = term === 'monthly' ? this.priceMonthly : this.priceYearly;
            const list = term === 'monthly' ? this.listMonthly : this.listYearly;
            const v = parseFloat(custom);
            return (custom !== '' && custom !== null && !isNaN(v)) ? { amount: v, custom: true } : { amount: list, custom: false };
        },

        money(v) { return heMoney(v); },
    }));
});
</script>
@endpush
