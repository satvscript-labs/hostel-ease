@extends('layouts.app')
@section('title', 'Account · '.($account->owner?->name ?? 'Customer'))

@php
    $unitYearly = $account->unit_price_override ?? config('hostelease.subscription_pricing.yearly', 10000);
    $unitMonthly = $account->unit_price_override ?? config('hostelease.subscription_pricing.monthly', 1000);
    $b = $renewQuote['breakdown'];
@endphp

@push('styles')
<style>
    .a360-hero { background: var(--he-gradient-mesh, linear-gradient(135deg,#0f172a,#1e1b4b)); color:#fff; border-radius: 1.25rem; position: relative; }
    /* Decorative glow lives in its own clipped layer so the hero itself can let the ... menu overflow. */
    .a360-hero-bg { position:absolute; inset:0; border-radius:inherit; overflow:hidden; z-index:0; pointer-events:none; }
    .a360-hero-bg::after { content:''; position:absolute; top:-40%; right:-10%; width:380px; height:380px; background: radial-gradient(circle, rgba(147,51,234,0.35), transparent 70%); }
    .a360-hero .dropdown-menu { z-index: 1080; }
    .a360-metric { font-variant-numeric: tabular-nums; }
    .panel-card { background:#fff; border:1px solid rgba(0,0,0,0.05); border-radius: 1.1rem; transition: all .3s cubic-bezier(.25,1,.5,1); }
    .panel-card:hover { box-shadow: 0 12px 30px rgba(0,0,0,0.04); }
    .rec-pill { font-size:.68rem; font-weight:700; letter-spacing:.3px; }
    .custom-overlay-backdrop { position: fixed; inset: 0; background: rgba(15,23,42,0.6); backdrop-filter: blur(8px); z-index: 9999; display:flex; align-items:center; justify-content:center; padding:1rem; }
    .custom-overlay-modal { width:100%; background:#fff; border-radius:1.25rem; box-shadow:0 25px 50px -12px rgba(0,0,0,.25); display:flex; flex-direction:column; max-height:90vh; transform:scale(.95); opacity:0; transition:all .3s cubic-bezier(.16,1,.3,1); overflow:hidden; }
    .custom-overlay-modal.is-open { transform:scale(1); opacity:1; }
    .custom-overlay-header { padding:1.25rem 1.5rem; border-bottom:1px solid rgba(0,0,0,.05); display:flex; justify-content:space-between; align-items:center; }
    .custom-overlay-body { padding:1.5rem; overflow-y:auto; background:#fafafa; }
    .custom-overlay-footer { padding:1.25rem 1.5rem; border-top:1px solid rgba(0,0,0,.05); display:flex; gap:1rem; justify-content:flex-end; }
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
                <form method="POST" action="{{ route('superadmin.accounts.align', $account) }}" class="d-inline" data-confirm="Align {{ $alignBehind }} branch(es) up to {{ optional($account->current_period_end)->format('d M Y') }} with a prorated top-up?">
                    @csrf<button class="btn btn-outline-light rounded-pill px-3 fw-bold"><i class="fa-solid fa-diagram-project me-2"></i>Align ({{ $alignBehind }})</button>
                </form>
                @endif
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
                    @if($account->unit_price_override !== null)<span class="badge bg-warning-subtle text-warning rounded-pill ms-1" style="font-size:.6rem;">custom</span>@endif
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
                                <div class="fw-bold text-dark">{{ $branch->name }}</div>
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
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-uppercase" style="font-size:.7rem; letter-spacing:.5px;"><tr>
                    <th class="py-3 px-4 border-0">Amount</th>
                    <th class="py-3 px-4 border-0 text-center">Status</th>
                    <th class="py-3 px-4 border-0 text-center">Qty</th>
                    <th class="py-3 px-4 border-0">Term</th>
                    <th class="py-3 px-4 border-0">Method</th>
                    <th class="py-3 px-4 border-0">Date</th>
                </tr></thead>
                <tbody class="border-top-0">
                @forelse($orders as $order)
                    <tr>
                        <td class="px-4 py-3">
                            <div class="fw-bold {{ (float)$order->amount == 0 ? 'text-secondary' : 'text-dark' }}">{{ hostelease_money($order->amount) }}</div>
                            @if((float)$order->discount_total > 0)<div class="small text-success">−{{ hostelease_money($order->discount_total) }} disc</div>@endif
                        </td>
                        <td class="px-4 py-3 text-center"><span class="badge bg-{{ $order->payment_status->value === 'paid' ? 'success' : ($order->payment_status->value === 'pending' ? 'warning' : 'danger') }}-subtle text-{{ $order->payment_status->value === 'paid' ? 'success' : ($order->payment_status->value === 'pending' ? 'warning' : 'danger') }} rounded-pill px-3 py-1">{{ $order->payment_status->label() }}</span></td>
                        <td class="px-4 py-3 text-center fw-bold">{{ $order->quantity }}</td>
                        <td class="px-4 py-3">{{ $order->period?->label() ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $order->payment_method?->label() ?? '—' }}</td>
                        <td class="px-4 py-3 text-muted small">{{ $order->created_at?->format('d M Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-0"><x-he-empty-state icon="receipt" title="No orders yet" subtitle="Renewals and payments will appear here." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($orders->hasPages())<div class="p-3 border-top">{{ $orders->links() }}</div>@endif
    </div>

    {{-- ══ Modals ══ --}}
    <template x-teleport="body">
        <div>
            {{-- Renew all --}}
            <div class="custom-overlay-backdrop" x-show="renewOpen" x-transition.opacity @click.self="renewOpen=false" x-cloak style="display:none;">
                <form method="POST" action="{{ route('superadmin.accounts.renew', $account) }}" class="custom-overlay-modal" style="max-width:560px;" :class="{'is-open':renewOpen}">
                    @csrf
                    <div class="custom-overlay-header"><h5 class="fw-bold mb-0">Renew all branches</h5><button type="button" class="btn-close" @click="renewOpen=false"></button></div>
                    <div class="custom-overlay-body">
                        <div class="d-flex gap-2 mb-4">
                            <button type="button" class="btn flex-fill rounded-pill fw-bold" :class="period==='yearly'?'btn-primary':'btn-light'" @click="period='yearly'">Yearly</button>
                            <button type="button" class="btn flex-fill rounded-pill fw-bold" :class="period==='monthly'?'btn-primary':'btn-light'" @click="period='monthly'">Monthly</button>
                        </div>
                        <input type="hidden" name="period" :value="period">
                        <div class="bg-white border rounded-4 p-3 mb-3">
                            <div class="d-flex justify-content-between mb-1"><span class="text-muted"><span x-text="quantity"></span> branch(es) × <span x-text="money(unit())"></span></span><span class="fw-semibold" x-text="money(quantity*unit())"></span></div>
                            <div class="d-flex justify-content-between align-items-center pt-2 border-top"><span class="fw-bold">Estimated total</span><span class="h5 fw-bold mb-0 text-primary" x-text="money(quantity*unit())"></span></div>
                            <div class="small text-muted mt-1">Discounts are applied automatically on save. Leave amount blank to charge the discounted total.</div>
                        </div>
                        <label class="form-label fw-bold small text-muted">AMOUNT OVERRIDE (₹) <span class="fw-normal">— optional</span></label>
                        <input type="number" step="0.01" name="amount" class="form-control bg-white border shadow-sm mb-3" placeholder="Auto (discounted total)">
                        <label class="form-label fw-bold small text-muted">METHOD</label>
                        <select name="payment_method" class="form-select bg-white border shadow-sm mb-3">
                            <option value="cash">Cash</option><option value="upi">UPI</option><option value="cheque">Cheque</option><option value="rtgs">RTGS / NEFT</option><option value="online">Online</option>
                        </select>
                        <label class="form-label fw-bold small text-muted">TXN / REMARKS</label>
                        <input type="text" name="transaction_number" class="form-control bg-white border shadow-sm mb-2" placeholder="Reference (optional)">
                        <input type="text" name="remarks" class="form-control bg-white border shadow-sm" placeholder="Remarks (optional)">
                    </div>
                    <div class="custom-overlay-footer"><button type="button" class="btn btn-light rounded-pill px-4 fw-bold" @click="renewOpen=false">Cancel</button><button class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">Renew</button></div>
                </form>
            </div>

            {{-- Add branch to cycle --}}
            <div class="custom-overlay-backdrop" x-show="addOpen" x-transition.opacity @click.self="addOpen=false" x-cloak style="display:none;">
                <form method="POST" action="{{ route('superadmin.accounts.add-branch', $account) }}" class="custom-overlay-modal" style="max-width:480px;" :class="{'is-open':addOpen}">
                    @csrf
                    <input type="hidden" name="branch_id" :value="addBranchId">
                    <div class="custom-overlay-header"><h5 class="fw-bold mb-0">Add branch to cycle</h5><button type="button" class="btn-close" @click="addOpen=false"></button></div>
                    <div class="custom-overlay-body">
                        <p class="text-muted small">Co-terminates <span class="fw-bold text-dark" x-text="addBranchName"></span> on the renewal date ({{ optional($account->current_period_end)->format('d M Y') }}) with a prorated charge for the <span x-text="addDays"></span> day(s) remaining.</p>
                        <div class="bg-white border rounded-4 p-3 mb-3 d-flex justify-content-between align-items-center">
                            <span class="text-muted">Prorated (<span x-text="addDays"></span> days)</span>
                            <span class="h5 fw-bold mb-0 text-primary" x-text="money(addAmount)"></span>
                        </div>
                        <label class="form-label fw-bold small text-muted">AMOUNT OVERRIDE (₹) <span class="fw-normal">— optional</span></label>
                        <input type="number" step="0.01" name="amount" class="form-control bg-white border shadow-sm mb-3" :placeholder="'Auto (' + money(addAmount) + ')'">
                        <label class="form-label fw-bold small text-muted">METHOD</label>
                        <select name="payment_method" class="form-select bg-white border shadow-sm">
                            <option value="cash">Cash</option><option value="upi">UPI</option><option value="cheque">Cheque</option><option value="rtgs">RTGS / NEFT</option><option value="online">Online</option>
                        </select>
                    </div>
                    <div class="custom-overlay-footer"><button type="button" class="btn btn-light rounded-pill px-4 fw-bold" @click="addOpen=false">Cancel</button><button class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">Add branch</button></div>
                </form>
            </div>

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

            {{-- Comp --}}
            <div class="custom-overlay-backdrop" x-show="compOpen" x-transition.opacity @click.self="compOpen=false" x-cloak style="display:none;">
                <form method="POST" action="{{ route('superadmin.accounts.comp', $account) }}" class="custom-overlay-modal" style="max-width:480px;" :class="{'is-open':compOpen}">
                    @csrf
                    <div class="custom-overlay-header"><h5 class="fw-bold mb-0">Complimentary coverage</h5><button type="button" class="btn-close" @click="compOpen=false"></button></div>
                    <div class="custom-overlay-body">
                        <p class="text-muted small">Grants free coverage to every branch on one anchor date — recorded as a ₹0 order.</p>
                        <label class="form-label fw-bold small text-muted">TERM</label>
                        <select name="period" class="form-select bg-white border shadow-sm mb-3"><option value="yearly">Yearly</option><option value="monthly">Monthly</option></select>
                        <label class="form-label fw-bold small text-muted">REASON</label>
                        <input type="text" name="reason" class="form-control bg-white border shadow-sm" placeholder="Why this comp?" required>
                    </div>
                    <div class="custom-overlay-footer"><button type="button" class="btn btn-light rounded-pill px-4 fw-bold" @click="compOpen=false">Cancel</button><button class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">Grant</button></div>
                </form>
            </div>

            {{-- Override --}}
            <div class="custom-overlay-backdrop" x-show="overrideOpen" x-transition.opacity @click.self="overrideOpen=false" x-cloak style="display:none;">
                <form method="POST" action="{{ route('superadmin.accounts.override', $account) }}" class="custom-overlay-modal" style="max-width:480px;" :class="{'is-open':overrideOpen}">
                    @csrf
                    <div class="custom-overlay-header"><h5 class="fw-bold mb-0">Custom unit price</h5><button type="button" class="btn-close" @click="overrideOpen=false"></button></div>
                    <div class="custom-overlay-body">
                        <p class="text-muted small">A bespoke per-branch price for this account. Leave blank to fall back to list price ({{ hostelease_money(config('hostelease.subscription_pricing.yearly',10000)) }}/yr).</p>
                        <label class="form-label fw-bold small text-muted">UNIT PRICE (₹ / branch / term)</label>
                        <input type="number" step="0.01" name="unit_price_override" class="form-control bg-white border shadow-sm" value="{{ $account->unit_price_override }}" placeholder="List price">
                    </div>
                    <div class="custom-overlay-footer"><button type="button" class="btn btn-light rounded-pill px-4 fw-bold" @click="overrideOpen=false">Cancel</button><button class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">Save</button></div>
                </form>
            </div>

            {{-- Add discount --}}
            <div class="custom-overlay-backdrop" x-show="discountOpen" x-transition.opacity @click.self="discountOpen=false" x-cloak style="display:none;">
                <form method="POST" action="{{ route('superadmin.accounts.discounts.store', $account) }}" class="custom-overlay-modal" style="max-width:560px;" :class="{'is-open':discountOpen}">
                    @csrf
                    <div class="custom-overlay-header"><h5 class="fw-bold mb-0">Add discount</h5><button type="button" class="btn-close" @click="discountOpen=false"></button></div>
                    <div class="custom-overlay-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">APPLIES</label>
                                <select name="recurrence" class="form-select bg-white border shadow-sm">
                                    <option value="one_time">One-time (next charge)</option>
                                    <option value="one_renewal">Next renewal only</option>
                                    <option value="every_renewal">Permanent (every renewal)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">TYPE</label>
                                <select name="type" class="form-select bg-white border shadow-sm" x-model="dType">
                                    <option value="percentage">Percentage (%)</option>
                                    <option value="fixed">Fixed (₹)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">VALUE</label>
                                <input type="number" step="0.01" name="value" class="form-control bg-white border shadow-sm" required>
                            </div>
                            <div class="col-md-6" x-show="dType==='percentage'">
                                <label class="form-label fw-bold small text-muted">MAX ₹ CAP <span class="fw-normal">— optional</span></label>
                                <input type="number" step="0.01" name="max_amount" class="form-control bg-white border shadow-sm">
                            </div>
                            <div class="col-md-6"><label class="form-label fw-bold small text-muted">STARTS <span class="fw-normal">— optional</span></label><input type="date" name="starts_at" class="form-control bg-white border shadow-sm"></div>
                            <div class="col-md-6"><label class="form-label fw-bold small text-muted">ENDS <span class="fw-normal">— optional</span></label><input type="date" name="ends_at" class="form-control bg-white border shadow-sm"></div>
                            <div class="col-12"><label class="form-label fw-bold small text-muted">REASON</label><input type="text" name="reason" class="form-control bg-white border shadow-sm" placeholder="Negotiation context" required></div>
                        </div>
                    </div>
                    <div class="custom-overlay-footer"><button type="button" class="btn btn-light rounded-pill px-4 fw-bold" @click="discountOpen=false">Cancel</button><button class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">Add discount</button></div>
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
        renewOpen: false, addOpen: false, compOpen: false, overrideOpen: false, discountOpen: false, suspendOpen: false,
        addBranchId: null, addBranchName: '', addDays: 0, addAmount: 0,
        addQuotes: @json($addQuotes),
        dType: 'percentage',
        openAdd(id, name) {
            this.addBranchId = id;
            this.addBranchName = name;
            const q = this.addQuotes[id] || { days: 0, amount: 0 };
            this.addDays = q.days;
            this.addAmount = q.amount;
            this.addOpen = true;
        },
        period: @json($account->period?->value === 'monthly' ? 'monthly' : 'yearly'),
        quantity: {{ $branches->count() }},
        unitYearly: {{ (float) $unitYearly }},
        unitMonthly: {{ (float) $unitMonthly }},
        unit() { return this.period === 'monthly' ? this.unitMonthly : this.unitYearly; },
        money(v) { return '₹' + Number(v || 0).toLocaleString('en-IN', {minimumFractionDigits: 0, maximumFractionDigits: 2}); },
    }));
});
</script>
@endpush
