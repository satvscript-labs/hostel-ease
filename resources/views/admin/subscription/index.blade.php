@extends('layouts.app')
@section('title', 'My Subscription')

{{-- ─────────────────────────────────────────────────────────────────────────
     The owner's billing page — rebuilt in S3 on the same core as the operator's
     Account 360 (_artifact/saas_billing_autopay/14_S3_DESIGN.md).

     · Every figure comes from the same quote functions Account 360 uses, so the
       owner sees their own negotiated price and the two surfaces cannot disagree.
     · Paying anything goes through a PENDING ORDER written when the price is shown,
       never a quote re-priced when the money arrives.
     · The browser sends a charge shape, never an amount; confirming sends only
       Razorpay's three ids.
     · A charge already open — a link we sent, a proforma, the owner's own earlier
       attempt — is what gets paid. Never a second demand beside it.

     BLADE ORDER MATTERS: the one multi-line PHP block below must stay ABOVE every
     inline single-expression use further down (the branch-state chips). Blade pairs
     the first opener it finds with the first closer, before comments are stripped —
     a second block added lower down swallows the page (see show.blade.php for
     Account 360, where that bit).
   ───────────────────────────────────────────────────────────────────────── --}}

@php
    use App\Enums\AccountStatus;
    $status = $account->status;
    $days = $account->daysUntilAnchor();
    $q = $quotes[$displayPeriod];
    $anchorFmt = $account->current_period_end?->format('d M Y');

    $hero = match ($status) {
        AccountStatus::Trial => ['tone' => 'trial', 'icon' => 'gift', 'label' => __('Free trial'), 'cta' => __('Subscribe now')],
        AccountStatus::Grace => ['tone' => 'warn', 'icon' => 'triangle-exclamation', 'label' => __('Expired — grace period'), 'cta' => __('Renew now to restore')],
        AccountStatus::Expired => ['tone' => 'danger', 'icon' => 'circle-xmark', 'label' => __('Subscription expired'), 'cta' => __('Renew now')],
        AccountStatus::Suspended => ['tone' => 'muted', 'icon' => 'lock', 'label' => __('Account on hold'), 'cta' => null],
        default => ['tone' => ($days !== null && $days <= 30) ? 'due' : 'active', 'icon' => 'circle-check', 'label' => __('Active'), 'cta' => __('Renew all now')],
    };
@endphp

@push('styles')
<style>
    /* The hero measures ITSELF (container units), so its metrics shrink as a
       whole instead of wrapping mid-figure on phones (§4.10 rule 2). Safe to
       contain: nothing inside floats a dropdown (§4.9 warning). */
    .sub-hero { border-radius: 1.4rem; position: relative; overflow: hidden; color:#fff; container-type: inline-size; }
    .sub-metric, .sub-hero .h4 { white-space: nowrap; font-variant-numeric: tabular-nums; font-size: clamp(0.95rem, 3.6cqi + 0.3rem, 1.5rem); }
    /* Asymmetric metric grid: short values (Branches, Term) take the space
       they need; long ones (date, money) get the remainder. Two paired rows
       on phones, one four-across row when the hero is wide. */
    .sub-metrics { display: grid; grid-template-columns: minmax(72px, auto) minmax(0, 1fr); gap: 0.85rem 1.25rem; }
    .sub-metric-lbl { font-size: 0.62rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: rgba(255, 255, 255, 0.55); white-space: nowrap; }
    @container (min-width: 560px) {
        .sub-metrics { grid-template-columns: auto minmax(0, 1.2fr) auto minmax(0, 1.2fr); column-gap: 2rem; }
    }
    /* Lock notice (W9): a glass CARD, not a cramped pill — it holds a sentence,
       and a sentence needs a surface, an icon anchor, and room to wrap. */
    .sub-lock { display:flex; align-items:flex-start; gap:.7rem; background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.18); border-radius:14px; padding:.75rem .95rem; backdrop-filter:blur(10px); max-width:340px; }
    .sub-lock-ic { width:34px; height:34px; border-radius:10px; flex-shrink:0; display:flex; align-items:center; justify-content:center; background:rgba(255,255,255,.18); font-size:.85rem; }
    .sub-lock-title { font-weight:800; font-size:.8rem; line-height:1.25; }
    .sub-lock-sub { font-size:.7rem; opacity:.75; line-height:1.35; }
    .sub-hero-bg { position:absolute; inset:0; border-radius:inherit; overflow:hidden; z-index:0; pointer-events:none; }
    .sub-hero-bg::after { content:''; position:absolute; top:-40%; right:-8%; width:420px; height:420px; background:radial-gradient(circle, rgba(147,51,234,0.4), transparent 70%); }
    .hero-active  { background: var(--he-gradient-mesh, linear-gradient(135deg,#0f172a,#1e1b4b)); }
    .hero-trial   { background: linear-gradient(135deg,#4f46e5,#7c3aed); }
    .hero-due     { background: linear-gradient(135deg,#7c3aed,#b45309); }
    .hero-warn    { background: linear-gradient(135deg,#b45309,#7c2d12); }
    .hero-danger  { background: linear-gradient(135deg,#7f1d1d,#450a0a); }
    .hero-muted   { background: linear-gradient(135deg,#334155,#0f172a); }
    .sub-metric { font-variant-numeric: tabular-nums; }
    /* .panel-card / .panel-head / .panel-body are canonical in _premium.scss — do not redeclare. */
    .plan-pick { border:1.5px solid rgba(0,0,0,0.08); border-radius:1rem; padding:1rem 1.1rem; cursor:pointer; transition:all .2s var(--ease-out-expo,cubic-bezier(.16,1,.3,1)); }
    .plan-pick.on { border-color:var(--bs-primary); background:rgba(79,70,229,.05); box-shadow:0 6px 18px rgba(79,70,229,.12); }
    .custom-overlay-backdrop { position:fixed; inset:0; background:rgba(15,23,42,0.6); backdrop-filter:blur(8px); z-index:9999; display:flex; align-items:center; justify-content:center; padding:1rem; }
    .custom-overlay-modal { width:100%; background:#fff; border-radius:1.25rem; box-shadow:0 25px 50px -12px rgba(0,0,0,.25); display:flex; flex-direction:column; max-height:92vh; transform:scale(.95); opacity:0; transition:all .3s cubic-bezier(.16,1,.3,1); overflow:hidden; }
    .custom-overlay-modal.is-open { transform:scale(1); opacity:1; }
    .custom-overlay-header { padding:1.25rem 1.5rem; border-bottom:1px solid rgba(0,0,0,.05); display:flex; justify-content:space-between; align-items:center; }
    .custom-overlay-body { padding:1.5rem; overflow-y:auto; background:#fafafa; }
    .custom-overlay-footer { padding:1.1rem 1.5rem; border-top:1px solid rgba(0,0,0,.05); display:flex; gap:.75rem; justify-content:flex-end; }
    @media (max-width: 575px) { .custom-overlay-modal { max-height:100vh; border-radius:1.25rem 1.25rem 0 0; align-self:flex-end; } .custom-overlay-backdrop { padding:0; align-items:flex-end; } }
    .sub-sticky { position:fixed; left:0; right:0; bottom:0; z-index:1030; background:#fff; border-top:1px solid rgba(0,0,0,.08); padding:.7rem 1rem; box-shadow:0 -6px 20px rgba(0,0,0,.06); }
</style>
@endpush

@section('content')
<div class="page-enter" x-data="ownerSubscription()">
    <div class="he-page-head mb-4">
        <div>
            <h1 class="he-page-title">{{ __('My Subscription') }}</h1>
            <p class="he-page-sub">{{ __('Every branch renews together, on one date, in one payment.') }}</p>
        </div>
    </div>

    {{-- ── Status hero ── --}}
    <div class="sub-hero hero-{{ $hero['tone'] }} p-4 p-md-4 mb-4 shadow">
        <div class="sub-hero-bg"></div>
        <div class="position-relative" style="z-index:1;">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <span class="badge bg-white bg-opacity-25 rounded-pill px-3 py-2 mb-2"><i class="fa-solid fa-{{ $hero['icon'] }} me-1"></i>{{ $hero['label'] }}</span>
                    <div class="text-white-50 small">
                        @if($status === AccountStatus::Trial)
                            {{ __('Trial ends') }} {{ $anchorFmt ?? '—' }}@if($days !== null && $days >= 0) · {{ $days }} {{ __('day(s) left') }} @endif
                        @elseif($status === AccountStatus::Grace)
                            {{ __('Access ends soon — renew to keep your hostels running.') }}
                        @elseif($status === AccountStatus::Expired)
                            {{ __('Your hostels are blocked until you renew.') }}
                        @elseif($status === AccountStatus::Suspended)
                            {{ __('Your account is on hold. Please contact support.') }}
                        @else
                            @if($days !== null && $days >= 0) {{ __('Renews in') }} {{ $days }} {{ __('day(s).') }} @else {{ __('Renews on the date below.') }} @endif
                        @endif
                    </div>
                </div>

                {{-- The one primary action. If a renewal is ALREADY billed — by us, or
                     by the owner's own earlier attempt — the button pays THAT, never
                     raises a second one beside it (design 14 §3). --}}
                @if($status === AccountStatus::Suspended)
                    <a href="mailto:{{ config('hostelease.company.email') }}" class="btn btn-light rounded-pill px-4 fw-bold shadow-sm"><i class="fa-solid fa-headset me-2"></i>{{ __('Contact support') }}</a>
                @elseif($openRenewal && ($openRenewal['link_url'] || $canManage))
                    <div class="d-flex flex-wrap gap-2">
                        @if($openRenewal['link_url'])
                            <a href="{{ $openRenewal['link_url'] }}" target="_blank" rel="noopener" class="btn btn-light rounded-pill px-4 fw-bold shadow-sm tactile-btn">
                                <i class="fa-solid fa-lock me-2"></i>{{ __('Pay') }} {{ hostelease_money($openRenewal['amount']) }}
                            </a>
                        @else
                            <button class="btn btn-light rounded-pill px-4 fw-bold shadow-sm tactile-btn" @click="payOrder({{ $openRenewal['id'] }})" :disabled="loading">
                                <i class="fa-solid fa-lock me-2"></i>{{ __('Pay') }} {{ hostelease_money($openRenewal['amount']) }}
                            </button>
                        @endif
                        @if($canManage)
                            <button class="btn btn-outline-light rounded-pill px-3 fw-bold" @click="openAdd()"><i class="fa-solid fa-plus me-2"></i>{{ __('Add a branch') }}</button>
                        @endif
                    </div>
                @elseif($canManage && $hero['cta'])
                    <div class="d-flex flex-wrap gap-2">
                        <button class="btn btn-light rounded-pill px-4 fw-bold shadow-sm tactile-btn" @click="openRenew()"><i class="fa-solid fa-arrows-rotate me-2"></i>{{ $hero['cta'] }}</button>
                        <button class="btn btn-outline-light rounded-pill px-3 fw-bold" @click="openAdd()"><i class="fa-solid fa-plus me-2"></i>{{ __('Add a branch') }}</button>
                    </div>
                @elseif(! $selfServe)
                    <div class="sub-lock">
                        <span class="sub-lock-ic"><i class="fa-solid fa-shield-halved"></i></span>
                        <span>
                            <span class="sub-lock-title d-block">{{ __('Managed by HostelEase') }}</span>
                            <span class="sub-lock-sub d-block">{{ __('Renewals and new branches are set up for you — contact support anytime.') }}</span>
                        </span>
                    </div>
                @elseif(! $viewerOwnsAccount)
                    <div class="sub-lock">
                        <span class="sub-lock-ic"><i class="fa-solid fa-user-shield"></i></span>
                        <span>
                            <span class="sub-lock-title d-block">{{ __('Managed by the account owner') }}</span>
                            <span class="sub-lock-sub d-block">{{ __('Only the account owner can renew or add branches.') }}</span>
                        </span>
                    </div>
                @endif
            </div>

            <div class="sub-metrics mt-3">
                <div><div class="sub-metric-lbl">{{ __('Branches') }}</div><div class="h4 fw-bold mb-0 sub-metric">{{ $branches->count() }}</div></div>
                <div><div class="sub-metric-lbl">{{ $status === AccountStatus::Trial ? __('Trial ends') : __('Renews on') }}</div><div class="h4 fw-bold mb-0 sub-metric">{{ $anchorFmt ?? '—' }}</div></div>
                <div><div class="sub-metric-lbl">{{ __('Term') }}</div><div class="h4 fw-bold mb-0 sub-metric">{{ $account->period?->isPaid() ? $account->period->label() : __('Trial') }}</div></div>
                <div>
                    <div class="sub-metric-lbl">{{ __('Next total') }}</div>
                    <div class="h4 fw-bold mb-0 sub-metric">{{ hostelease_money($q['final']) }}</div>
                    @if($q['discount'] > 0)<div class="small text-white-50" style="white-space:nowrap;">{{ __('Discount') }} −{{ hostelease_money($q['discount']) }}</div>@endif
                </div>
            </div>
        </div>
    </div>

    {{-- ── Payment due ──
         Every charge already open on the account, whoever opened it. A payment link
         the HostelEase team sent is payable here even with self-serve switched off:
         it is OUR instrument, not self-serve (design 14 §4, roadmap item 23). --}}
    @if(count($due))
        <div class="panel-card shadow-sm mb-4" style="border-color: rgba(79,70,229,.25);">
            <div class="p-3 px-4 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-file-invoice text-primary me-2"></i>{{ __('Payment due') }}</h6>
                <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-2">{{ count($due) }}</span>
            </div>
            @foreach($due as $row)
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 px-4 py-3 {{ ! $loop->last ? 'border-bottom' : '' }}">
                    <div class="min-w-0">
                        <div class="fw-bold text-dark">{{ hostelease_money($row['amount']) }} <span class="fw-normal text-muted small">· {{ $row['label'] }}@if($row['period']) · {{ $row['period'] }}@endif</span></div>
                        <div class="small text-muted">
                            {{ $row['invoice'] }} · {{ __('raised') }} {{ $row['raised'] }}
                            @if($row['link_expires']) · {{ __('link valid until') }} {{ $row['link_expires'] }} @endif
                        </div>
                    </div>
                    {{-- An OVERTAKEN charge — every date it would grant is already
                         covered — is shown, but never offered: paying it would buy
                         nothing (S3 audit). We clear it up on our side. --}}
                    @if($row['stale'])
                        <span class="badge bg-warning-subtle text-warning rounded-pill px-3 py-2" title="{{ __('Part of it was paid separately, so the amount has changed.') }}">
                            <i class="fa-solid fa-rotate me-1"></i>{{ __('Amount changed — renew again for the new total') }}
                        </span>
                    @elseif(! $row['payable'])
                        <span class="badge bg-success-subtle text-success rounded-pill px-3 py-2">
                            <i class="fa-solid fa-circle-check me-1"></i>{{ __('Already covered — nothing to pay') }}
                        </span>
                    @elseif($row['link_url'])
                        <a href="{{ $row['link_url'] }}" target="_blank" rel="noopener" class="btn btn-sm btn-primary rounded-pill px-3 fw-semibold shadow-sm">
                            <i class="fa-solid fa-lock me-1"></i>{{ __('Pay securely') }}
                        </a>
                    @elseif($canManage)
                        <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 fw-semibold shadow-sm" @click="payOrder({{ $row['id'] }})" :disabled="loading">
                            <i class="fa-solid fa-lock me-1"></i>{{ __('Pay now') }}
                        </button>
                    @else
                        <span class="small text-muted">{{ __('Contact us to pay') }}</span>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    <div class="row g-4">
        {{-- ── Branches ── --}}
        <div class="col-lg-7">
            <div class="panel-card shadow-sm h-100">
                <div class="p-3 px-4 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-hotel text-primary me-2"></i>{{ __('Your branches') }}</h6>
                    <span class="text-muted small">
                        {{ $billableCount }} {{ __('on your plan') }}
                        @if($branches->count() > $billableCount) · {{ $branches->count() - $billableCount }} {{ __('closing') }} @endif
                    </span>
                </div>
                <div class="stagger">
                    @forelse($branches as $branch)
                        @php($state = $branch->cancellationState())
                        @php($behind = ! $branch->isCancelled() && $account->current_period_end && $account->current_period_end->isFuture() && (! $branch->subscription_end || $branch->subscription_end->lt($account->current_period_end)))
                        <div class="px-4 py-3 border-bottom">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div class="min-w-0">
                                    <div class="fw-bold text-dark">{{ $branch->name }}</div>
                                    <div class="small text-muted">
                                        @if($state === 'cancelled')
                                            {{ __('Closing') }} {{ $branch->subscription_end?->format('d M Y') }} · {{ __('not billed at your next renewal') }}
                                        @elseif($state === 'closed')
                                            {{ __('Closed') }} {{ $branch->subscription_end?->format('d M Y') }}
                                        @else
                                            {{ __('Ends') }} {{ $branch->subscription_end ? $branch->subscription_end->format('d M Y') : '—' }}
                                        @endif
                                    </div>
                                    @if($state === 'removal_requested')
                                        <div class="small mt-1" style="color:#ea580c;">
                                            <i class="fa-solid fa-clock me-1"></i>{{ __('Removal requested — our team will be in touch. Nothing has changed yet.') }}
                                        </div>
                                    @endif
                                </div>
                                <div class="d-flex flex-column align-items-end gap-2">
                                    @php($chip = match($state) {
                                        'removal_requested' => ['warning', __('Removal asked')],
                                        'cancelled' => ['secondary', __('Closing')],
                                        'closed' => ['secondary', __('Closed')],
                                        default => $branch->isActive() ? ($behind ? ['warning', __('Behind')] : ['success', __('Active')]) : ['danger', __('Expired')],
                                    })
                                    <span class="badge bg-{{ $chip[0] }}-subtle text-{{ $chip[0] }} rounded-pill px-3 py-2">{{ $chip[1] }}</span>
                                    @if($branch->free_renewals > 0 && ! $branch->isCancelled())
                                        <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-1"><i class="fa-solid fa-gift me-1"></i>{{ $branch->free_renewals === 1 ? __('Next renewal free') : __(':n renewals free', ['n' => $branch->free_renewals]) }}</span>
                                    @endif

                                    {{-- Bring a behind branch onto the renewal date, at the price
                                         the operator would quote — the same function prices both. --}}
                                    @if(isset($addable[$branch->id]))
                                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-semibold"
                                                @click="payAddBranch({{ $branch->id }})" :disabled="loading">
                                            {{ __('Add to plan') }} · {{ hostelease_money($addable[$branch->id]['amount']) }}
                                        </button>
                                    @endif

                                    {{-- Removal is a REQUEST, never a self-service cancel (D11). Owner
                                         only — a co-admin shares the role, so the gate is the FK. --}}
                                    @if($viewerOwnsAccount && ! $branch->isCancelled())
                                        @if($state === 'removal_requested')
                                            <form method="POST" action="{{ route('admin.branches.withdraw-removal', $branch) }}"
                                                  data-confirm="{{ __('Withdraw your request to remove') }} {{ $branch->name }}?">
                                                @csrf @method('DELETE')
                                                <button class="btn btn-sm btn-light text-muted rounded-pill px-3 fw-semibold shadow-sm">{{ __('Withdraw') }}</button>
                                            </form>
                                        @else
                                            <button type="button" class="btn btn-sm btn-link text-muted p-0 small"
                                                    @click="removeBranchId = {{ $branch->id }}; removeBranchName = @js($branch->name); removeOpen = true">
                                                {{ __('Request removal') }}
                                            </button>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="p-4"><x-he-empty-state icon="hotel" title="{{ __('No branches yet') }}" subtitle="{{ __('Add a branch to get started.') }}" /></div>
                    @endforelse
                </div>
                @if($branches->contains(fn ($b) => ! $b->isCancelled() && $account->current_period_end && $account->current_period_end->isFuture() && (! $b->subscription_end || $b->subscription_end->lt($account->current_period_end))))
                    <div class="px-4 py-3 small text-muted bg-light bg-opacity-50"><i class="fa-solid fa-circle-info text-warning me-1"></i>{{ __('Renewing all brings every branch onto the same date.') }}</div>
                @endif
            </div>
        </div>

        {{-- ── Payment history ── --}}
        <div class="col-lg-5">
            <div class="panel-card shadow-sm h-100">
                <div class="p-3 px-4 border-bottom"><h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-receipt text-primary me-2"></i>{{ __('Recent payments') }}</h6></div>
                @forelse($orders as $order)
                    <div class="d-flex justify-content-between align-items-center px-4 py-3 border-bottom">
                        <div>
                            <div class="fw-bold text-dark">{{ hostelease_money($order->amount) }}</div>
                            <div class="small text-muted">{{ $order->kind?->label() ?? '' }} · {{ $order->quantity }} {{ __('branch(es)') }} · {{ $order->created_at?->format('d M Y') }}</div>
                        </div>
                        <span class="badge bg-success-subtle text-success rounded-pill px-3 py-1">{{ __('Paid') }}</span>
                    </div>
                @empty
                    <div class="p-4"><x-he-empty-state icon="receipt" title="{{ __('No payments yet') }}" subtitle="{{ __('Your renewals will appear here.') }}" /></div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- ── Mobile sticky action bar ── --}}
    @if($status !== AccountStatus::Suspended && (($openRenewal && ($openRenewal['link_url'] || $canManage)) || ($canManage && $hero['cta'])))
        <div class="d-lg-none" style="height:76px;"></div>
        <div class="sub-sticky d-lg-none">
            <div class="d-flex align-items-center justify-content-between gap-3">
                @if($openRenewal)
                    <div class="flex-shrink-0">
                        <div class="small text-muted lh-1">{{ __('Due now') }}</div>
                        <div class="fw-bold text-dark">{{ hostelease_money($openRenewal['amount']) }}</div>
                    </div>
                    @if($openRenewal['link_url'])
                        <a href="{{ $openRenewal['link_url'] }}" target="_blank" rel="noopener" class="btn btn-primary rounded-pill px-4 fw-bold flex-grow-1"><i class="fa-solid fa-lock me-2"></i>{{ __('Pay now') }}</a>
                    @else
                        <button class="btn btn-primary rounded-pill px-4 fw-bold flex-grow-1 tactile-btn" @click="payOrder({{ $openRenewal['id'] }})" :disabled="loading"><i class="fa-solid fa-lock me-2"></i>{{ __('Pay now') }}</button>
                    @endif
                @else
                    <div class="flex-shrink-0">
                        <div class="small text-muted lh-1">{{ __('Next total') }}</div>
                        <div class="fw-bold text-dark" x-text="money(q().final)"></div>
                    </div>
                    <button class="btn btn-primary rounded-pill px-4 fw-bold flex-grow-1 tactile-btn" @click="openRenew()"><i class="fa-solid fa-arrows-rotate me-2"></i>{{ $hero['cta'] }}</button>
                @endif
            </div>
        </div>
    @endif

    @unless($selfServe)
        <div class="d-flex align-items-start gap-3 mt-4 p-3 px-4 rounded-4 shadow-sm" style="background:var(--he-warning-soft,#fef3c7); border:1px solid rgba(245,158,11,.25);">
            <i class="fa-solid fa-shield-halved fs-5 mt-1" style="color:var(--he-warning,#f59e0b);"></i>
            <div>
                <div class="fw-bold text-dark" style="font-size:.92rem;">{{ __('Billing is managed by HostelEase support') }}</div>
                <div class="small text-muted">{{ __('Your plans and coverage above are always up to date. To renew, add a branch, or change your plan, contact support and our team will set it up on your account. Any payment link we send you can be paid right here.') }}</div>
            </div>
        </div>
    @endunless

    @if($canManage)
    {{-- ══ Renew-all ══ Itemised from the SAME quote the operator sees. The term is
         the only thing the owner chooses; every figure is the server's. --}}
    <template x-teleport="body">
        <div class="custom-overlay-backdrop" x-show="renewOpen" x-transition.opacity @click.self="renewOpen = false" x-cloak style="display:none;">
            <div class="custom-overlay-modal" style="max-width:520px;" :class="{ 'is-open': renewOpen }">
                <div class="custom-overlay-header"><h5 class="fw-bold mb-0">{{ __('Renew all branches') }}</h5><button type="button" class="btn-close" @click="renewOpen = false" :disabled="loading"></button></div>
                <div class="custom-overlay-body">
                    <div class="text-muted small text-uppercase mb-2" style="letter-spacing:.5px;">{{ __('Choose term') }}</div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <div class="plan-pick h-100" :class="{ 'on': period === 'yearly' }" @click="period = 'yearly'">
                                <div class="fw-bold text-uppercase small" :class="period === 'yearly' ? 'text-primary' : 'text-muted'">{{ __('Yearly') }}</div>
                                <div class="h5 fw-bold text-dark mb-0" x-text="money(quotes.yearly.unit)"></div>
                                <div class="small text-muted">{{ __('per branch') }}</div>
                                @if($yearlySaving)<div class="small text-success fw-bold">{{ __('Save') }} {{ $yearlySaving }}%</div>@endif
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="plan-pick h-100" :class="{ 'on': period === 'monthly' }" @click="period = 'monthly'">
                                <div class="fw-bold text-uppercase small" :class="period === 'monthly' ? 'text-primary' : 'text-muted'">{{ __('Monthly') }}</div>
                                <div class="h5 fw-bold text-dark mb-0" x-text="money(quotes.monthly.unit)"></div>
                                <div class="small text-muted">{{ __('per branch') }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white border rounded-4 p-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted"><span x-text="q().quantity"></span> {{ __('branch(es)') }} × <span x-text="money(q().unit)"></span></span>
                            <span class="fw-semibold" x-text="money(q().subtotal)"></span>
                        </div>
                        <template x-for="c in (q().complimentary || [])" :key="c.name">
                            <div class="d-flex justify-content-between mb-1 text-success"><span><i class="fa-solid fa-gift me-1"></i>{{ __('Free renewal') }} · <span x-text="c.name"></span></span><span x-text="'−' + money(c.amount)"></span></div>
                        </template>
                        <template x-if="q().volume > 0">
                            <div class="d-flex justify-content-between mb-1 text-success"><span>{{ __('Multi-branch discount') }}</span><span x-text="'−' + money(q().volume)"></span></div>
                        </template>
                        <template x-if="q().manual > 0">
                            <div class="d-flex justify-content-between mb-1 text-success"><span>{{ __('Your discount') }}</span><span x-text="'−' + money(q().manual)"></span></div>
                        </template>
                        {{-- Branches behind the current renewal date are brought up to it
                             first — otherwise they would run free until then. --}}
                        <template x-if="(q().topups || []).length">
                            <div class="mt-2 pt-2 border-top">
                                <div class="small text-muted mb-1">{{ __('Brings these branches up to') }} <span class="fw-semibold text-dark" x-text="q().current_anchor"></span> {{ __('first') }}:</div>
                                <template x-for="t in q().topups" :key="t.name">
                                    <div class="d-flex justify-content-between mb-1"><span class="text-muted"><span x-text="t.name"></span> · <span x-text="t.days"></span> {{ __('days') }}</span><span class="fw-semibold" x-text="money(t.amount)"></span></div>
                                </template>
                            </div>
                        </template>
                        <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                            <span class="fw-bold">{{ __('Total payable') }}</span>
                            <span class="h4 fw-bold mb-0 text-primary" x-text="money(q().final)"></span>
                        </div>
                        <div class="small text-muted mt-2"><i class="fa-solid fa-calendar-check me-1"></i>{{ __('New renewal date') }}: <span class="fw-semibold text-dark" x-text="q().new_anchor"></span> — {{ __('all branches together.') }}</div>
                    </div>
                </div>
                <div class="custom-overlay-footer">
                    <button type="button" class="btn btn-light rounded-pill px-4 fw-bold" @click="renewOpen = false" :disabled="loading">{{ __('Cancel') }}</button>
                    <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm d-flex align-items-center gap-2" @click="payRenewal()" :disabled="loading">
                        <span x-show="!loading"><i class="fa-solid fa-lock me-1"></i>{{ __('Pay') }} <span x-text="money(q().final)"></span></span>
                        <span x-show="loading" class="spinner-border spinner-border-sm"></span>
                    </button>
                </div>
            </div>
        </div>
    </template>

    {{-- ══ Add a branch ══ No trial — the account's one free trial went to its first
         branch. The branch is created either way; it becomes active once paid for, so
         an abandoned payment leaves it waiting with an "Add to plan" button, not lost. --}}
    <template x-teleport="body">
        <div class="custom-overlay-backdrop" x-show="addOpen" x-transition.opacity @click.self="addOpen = false" x-cloak style="display:none;">
            <div class="custom-overlay-modal" style="max-width:500px;" :class="{ 'is-open': addOpen }">
                <div class="custom-overlay-header"><h5 class="fw-bold mb-0">{{ __('Add a branch') }}</h5><button type="button" class="btn-close" @click="addOpen = false" :disabled="loading"></button></div>
                <div class="custom-overlay-body">
                    <label class="form-label fw-bold small text-muted">{{ __('BRANCH NAME') }}</label>
                    <input type="text" x-model="add.name" class="form-control bg-white border shadow-sm mb-3" placeholder="e.g. Sunrise Riverside" maxlength="255">
                    <label class="form-label fw-bold small text-muted">{{ __('CITY') }} <span class="fw-normal">— {{ __('optional') }}</span></label>
                    <input type="text" x-model="add.city" class="form-control bg-white border shadow-sm mb-3" placeholder="e.g. Surat" maxlength="100">

                    <div class="alert bg-info-subtle text-info border-0 rounded-3 small mb-0">
                        <i class="fa-solid fa-circle-info me-1"></i>
                        {{-- The free trial belongs to the ACCOUNT, once (owner decision,
                             2026-10-04) — a branch added later is never a trial branch. --}}
                        @if($trialJoinable)
                            {{ __('It joins your free trial and works straight away, until') }} {{ $anchorFmt }}. {{ __('Every branch is billed together when you subscribe.') }}
                        @elseif($canAddPaid)
                            {{ __('A new branch becomes active once it is paid for — prorated to') }} {{ $anchorFmt }} {{ __('so everything renews together. You will see the exact amount before you pay.') }}
                        @else
                            {{ __('A new branch becomes active when you subscribe — it is included in your plan from your first payment.') }}
                        @endif
                    </div>
                </div>
                <div class="custom-overlay-footer d-flex flex-column flex-sm-row gap-2">
                    @if($canAddPaid)
                        <button type="button" class="btn btn-link text-muted fw-semibold text-decoration-none px-0 order-2 order-sm-1 me-sm-auto"
                                @click="addBranch(false)" :disabled="!add.name || loading">{{ __('Add now, pay later') }}</button>
                    @else
                        <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm"
                                @click="addBranch(false)" :disabled="!add.name || loading">{{ __('Add branch') }}</button>
                    @endif
                    @if($canAddPaid)
                        <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm order-1 order-sm-2 d-flex align-items-center justify-content-center gap-2"
                                @click="addBranch(true)" :disabled="!add.name || loading">
                            <span x-show="!loading"><i class="fa-solid fa-lock me-1"></i>{{ __('Add & pay') }}</span>
                            <span x-show="loading" class="spinner-border spinner-border-sm"></span>
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </template>
    @endif

    {{-- ══ Request branch removal (D11) ══ A REQUEST, not a cancel. Owner-only. --}}
    @if($viewerOwnsAccount)
        <template x-teleport="body">
            <div class="custom-overlay-backdrop" x-show="removeOpen" x-transition.opacity @click.self="removeOpen = false" x-cloak style="display:none;">
                <form method="POST" action="{{ route('admin.branches.request-removal') }}" class="custom-overlay-modal" style="max-width:520px;" :class="{ 'is-open': removeOpen }">
                    @csrf
                    <input type="hidden" name="branch_id" :value="removeBranchId">
                    <div class="custom-overlay-header">
                        <h5 class="fw-bold mb-0">{{ __('Request branch removal') }}</h5>
                        <button type="button" class="btn-close" @click="removeOpen = false"></button>
                    </div>
                    <div class="custom-overlay-body">
                        <div class="fw-bold text-dark mb-2" x-text="removeBranchName"></div>
                        <p class="small text-muted">
                            {{ __('We\'ll contact you before anything changes. Once it\'s confirmed, the branch') }}
                            <strong>{{ __('keeps working until the end of the time you\'ve already paid for') }}</strong>
                            {{ __('and simply isn\'t billed at your next renewal. There\'s no refund for the remaining time, and nothing is cancelled today.') }}
                        </p>
                        <label class="form-label fw-bold small text-muted">{{ __('WHY ARE YOU REMOVING IT?') }} <span class="text-danger">*</span></label>
                        <input type="text" name="reason" class="form-control bg-white border shadow-sm" required maxlength="255" placeholder="{{ __('e.g. we\'re closing this property') }}">
                        <div class="form-text">{{ __('This helps us help you — if it\'s about price or a feature, tell us and we\'ll try to sort it.') }}</div>
                    </div>
                    <div class="custom-overlay-footer d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-light border rounded-pill px-4 fw-bold" @click="removeOpen = false">{{ __('Never mind') }}</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">{{ __('Send request') }}</button>
                    </div>
                </form>
            </div>
        </template>
    @endif
</div>
@endsection

@push('scripts')
@if($canManage)
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
@endif
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('ownerSubscription', () => ({
        renewOpen: false,
        addOpen: false,
        loading: false,
        removeOpen: false,
        removeBranchId: null,
        removeBranchName: '',
        period: @json($displayPeriod),
        quotes: @json($quotes),
        add: { name: '', city: '' },

        money(v) { const n = Number(v || 0); return '₹' + n.toLocaleString('en-IN', { minimumFractionDigits: Number.isInteger(Math.round(n * 100) / 100) ? 0 : 2, maximumFractionDigits: 2 }); },
        q() { return this.quotes[this.period]; },
        openRenew() { this.renewOpen = true; },
        openAdd() { this.add = { name: '', city: '' }; this.addOpen = true; },

        // Never alert(): it blocks the page. The app's toast if present, else a
        // SweetAlert toast, else the console — never a dialog.
        toast(message, type) {
            if (window.showToast) return window.showToast(message, type || 'info');
            if (window.Swal) return Swal.fire({ toast: true, position: 'top-end', icon: type === 'error' ? 'error' : (type || 'info'), title: message, showConfirmButton: false, timer: 4500 });
            console.log(message);
        },

        async post(url, body) {
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) },
                body: JSON.stringify(body),
            });
            let data = {};
            try { data = await res.json(); } catch (e) { /* non-JSON error page */ }
            if (!res.ok) throw new Error(data.message || (res.status === 429 ? 'Too many attempts — please wait a minute.' : 'Something went wrong. Please try again.'));
            return data;
        },

        // ── Starting a charge ──
        // The browser sends a charge SHAPE — never an amount. Every figure the
        // customer pays is read from the server's pending order (S2 audit brief).
        // `expected` is ONLY for telling the customer if the price moved since the page
        // loaded — it is never sent to the server, and never what they are charged.
        payRenewal() { return this.start({ charge: 'renewal', period: this.period }, this.q().final); },
        payAddBranch(id) { return this.start({ charge: 'add_branch', branch_id: id }); },
        payOrder(id) { return this.start({ charge: 'order', order_id: id }); },

        async start(body, expected) {
            this.loading = true;
            try {
                const result = await this.post(@json(route('admin.subscription.checkout')), body);

                // The page can be hours old. If the server's amount differs from what
                // this page showed, say so BEFORE the payment window opens — Razorpay
                // shows the right figure, but nobody should be surprised by it.
                if (result.mode === 'checkout' && expected !== undefined
                    && Math.abs(result.razorpay.amount / 100 - expected) > 0.005) {
                    this.toast('The total has been updated since this page loaded — it is now ' + this.money(result.razorpay.amount / 100) + '.', 'info');
                }

                this.handle(result);
            } catch (e) {
                this.toast(e.message, 'error');
                this.loading = false;
            }
        },

        async addBranch(payNow) {
            if (!this.add.name) return;
            this.loading = true;
            try {
                const result = await this.post(@json(route('admin.subscription.add-branch')), { name: this.add.name, city: this.add.city, pay_now: payNow });
                if (result.created) this.toast(result.created, 'success');
                this.handle(result);
            } catch (e) {
                this.toast(e.message, 'error');
                this.loading = false;
            }
        },

        handle(result) {
            if (result.mode === 'link') {
                // The team already sent a link for this charge — pay THAT, never a
                // second demand beside it. Same tab, so the page reflects it on return.
                this.toast(result.message, 'info');
                window.location.href = result.url;
                return;
            }
            if (result.mode === 'checkout') {
                this.renewOpen = false;
                this.addOpen = false;
                return this.openCheckout(result.razorpay);
            }
            // 'paid', 'created' or 'held' — nothing to pay right now. 'held' means a
            // payment was found that needs a human check: it must not read as success.
            this.toast(result.message, result.mode === 'held' ? 'warning' : 'success');
            setTimeout(() => { window.location.href = result.redirect || window.location.href; }, 1200);
        },

        openCheckout(opts) {
            const rzp = new Razorpay({
                key: opts.key, order_id: opts.order_id, amount: opts.amount, currency: opts.currency,
                name: opts.name, description: opts.description, prefill: opts.prefill,
                theme: { color: '#4f46e5' },
                modal: { ondismiss: () => { this.loading = false; } },
                handler: (response) => this.confirm(response),
            });
            rzp.on('payment.failed', () => {
                this.toast('That payment did not go through, and you have not been charged. You can try again.', 'error');
                this.loading = false;
            });
            rzp.open();
        },

        // ── Confirming ──
        // ONLY Razorpay's three ids. Which charge this paid for is looked up on the
        // server from the Razorpay order id; the amount is read back from Razorpay.
        async confirm(response) {
            try {
                const result = await this.post(@json(route('admin.subscription.confirm')), {
                    razorpay_order_id: response.razorpay_order_id,
                    razorpay_payment_id: response.razorpay_payment_id,
                    razorpay_signature: response.razorpay_signature,
                });
                if (window.Swal) {
                    await Swal.fire({
                        icon: result.state === 'pending' ? 'info' : (result.state === 'refused' ? 'warning' : 'success'),
                        title: result.state === 'pending' ? 'Almost done' : (result.state === 'refused' ? 'We are checking it' : "You're all set!"),
                        text: result.message, confirmButtonColor: '#4f46e5',
                    });
                } else {
                    this.toast(result.message, result.state === 'refused' ? 'warning' : 'success');
                }
                window.location.href = result.redirect || window.location.href;
            } catch (e) {
                this.toast(e.message, 'error');
                this.loading = false;
            }
        },
    }));
});
</script>
@endpush
