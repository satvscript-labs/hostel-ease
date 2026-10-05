@extends('layouts.app')
@section('title', 'My Subscription')

{{-- ─────────────────────────────────────────────────────────────────────────
     The owner's billing page — redesigned 2026-10-06 (doc 23) on the same core as
     the operator's Account 360.

     Layout, top to bottom, in the order an owner thinks:
       1. Hero        — where the plan stands, and the one thing to do next.
       2. Next step   — every charge waiting, every branch not yet covered, each
                        with ONE button. Nothing to do → the card is not shown.
       3. Branches · Payments & receipts.
     Every charge opens the SAME review sheet first (the shared billing summary —
     identical rows to Account 360), and only then Razorpay. Nothing is paid
     without being seen.

     Money rules, unchanged: the browser sends a charge SHAPE, never an amount;
     confirming sends only Razorpay's three ids; a charge already open is what
     gets paid, never a second one beside it. Every figure here comes from the
     server's quotes — the sheet only shows them.

     BLADE ORDER MATTERS: the one multi-line PHP block below must stay ABOVE every
     inline single-expression use further down. Blade pairs the first opener with
     the first closer before comments are stripped — a second block lower down
     swallows the page.
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

    // ── Next step: everything waiting on the owner, in one list ──
    $dueRows = collect($due);
    $billedAddNames = $dueRows->where('kind', 'add_branch')->where('payable', true)->flatMap(fn ($r) => array_column($r['lines'], 'name'))->all();
    $singleBehind = null;
    if (! $alignOffer && count($addable) === 1) {
        $firstId = array_key_first($addable);
        $singleBehind = $addable[$firstId] + ['id' => $firstId];
        if (in_array($singleBehind['name'], $billedAddNames, true)) {
            $singleBehind = null;   // already billed — it is in the list as a charge
        }
    }
    $hasSteps = $dueRows->isNotEmpty() || $alignOffer || $singleBehind;
@endphp

@push('styles')
<style>
    /* ══ My Subscription — doc 23 ══
       One motion idea: things RISE into place, in reading order, once. Interaction
       motion answers an action: the total pulses when the term changes; the sheet
       eases in. All of it switched off for reduced motion. */
    .os-rise { opacity: 0; animation: os-rise .55s var(--ease-out-expo, cubic-bezier(.16,1,.3,1)) forwards; animation-delay: calc(var(--i, 0) * 60ms + 80ms); }
    @keyframes os-rise { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }

    /* Hero — container-measured so metrics shrink as a group on phones. */
    .sub-hero { border-radius: 1.4rem; position: relative; overflow: hidden; color: #fff; container-type: inline-size; }
    .sub-hero-bg { position: absolute; inset: 0; border-radius: inherit; overflow: hidden; z-index: 0; pointer-events: none; }
    .sub-hero-bg::after { content: ''; position: absolute; top: -40%; right: -8%; width: 420px; height: 420px; background: radial-gradient(circle, rgba(147,51,234,.4), transparent 70%); }
    .hero-active { background: var(--he-gradient-mesh, linear-gradient(135deg,#0f172a,#1e1b4b)); }
    .hero-trial  { background: linear-gradient(135deg,#4f46e5,#7c3aed); }
    .hero-due    { background: linear-gradient(135deg,#7c3aed,#b45309); }
    .hero-warn   { background: linear-gradient(135deg,#b45309,#7c2d12); }
    .hero-danger { background: linear-gradient(135deg,#7f1d1d,#450a0a); }
    .hero-muted  { background: linear-gradient(135deg,#334155,#0f172a); }
    .sub-metrics { display: grid; grid-template-columns: minmax(72px, auto) minmax(0, 1fr); gap: .85rem 1.25rem; }
    @container (min-width: 560px) { .sub-metrics { grid-template-columns: auto minmax(0,1.2fr) auto minmax(0,1.2fr); column-gap: 2rem; } }
    .sub-metric-lbl { font-size: .62rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: rgba(255,255,255,.55); white-space: nowrap; }
    .sub-metric { white-space: nowrap; font-variant-numeric: tabular-nums; font-size: clamp(.95rem, 3.6cqi + .3rem, 1.5rem); }
    .sub-lock { display: flex; align-items: flex-start; gap: .7rem; background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.18); border-radius: 14px; padding: .75rem .95rem; backdrop-filter: blur(10px); max-width: 340px; }
    .sub-lock-ic { width: 34px; height: 34px; border-radius: 10px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; background: rgba(255,255,255,.18); font-size: .85rem; }
    .sub-lock-title { font-weight: 800; font-size: .8rem; line-height: 1.25; }
    .sub-lock-sub { font-size: .7rem; opacity: .75; line-height: 1.35; }

    /* Next step — a list of ONE-button items. */
    .os-steps { border: 1px solid rgba(79,70,229,.22); }
    .os-step { display: flex; align-items: center; gap: 1rem; padding: 1rem 1.5rem; }
    .os-step + .os-step { border-top: 1px solid rgba(15,23,42,.06); }
    .os-step-ic { width: 42px; height: 42px; border-radius: 12px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; background: var(--he-primary-soft, rgba(79,70,229,.08)); color: var(--he-primary, #4f46e5); }
    .os-step-ic.is-warn { background: var(--he-warning-soft, #fef3c7); color: #b45309; }
    .os-step-body { flex: 1 1 auto; min-width: 0; }
    .os-step-title { font-weight: 700; color: var(--he-text-main, #0f172a); }
    .os-step-sub { font-size: .8rem; color: var(--he-text-muted, #64748b); }
    .os-step-amt { font-weight: 800; font-variant-numeric: tabular-nums; color: var(--he-text-main, #0f172a); white-space: nowrap; }
    @media (max-width: 575.98px) { .os-step { flex-wrap: wrap; padding: 1rem 1.1rem; } .os-step-act { width: 100%; } .os-step-act > .btn { width: 100%; } }

    /* Branches */
    .os-branch { display: flex; align-items: flex-start; gap: .85rem; padding: .95rem 1.5rem; }
    .os-branch + .os-branch { border-top: 1px solid rgba(15,23,42,.06); }
    .os-dot { width: 10px; height: 10px; border-radius: 50%; margin-top: .45rem; flex-shrink: 0; }
    .os-dot.ok { background: var(--he-success, #10b981); box-shadow: 0 0 0 4px rgba(16,185,129,.12); }
    .os-dot.warn { background: #f59e0b; box-shadow: 0 0 0 4px rgba(245,158,11,.14); }
    .os-dot.off { background: #ef4444; box-shadow: 0 0 0 4px rgba(239,68,68,.12); }
    .os-dot.muted { background: #94a3b8; }
    .os-branch-name { font-weight: 700; color: var(--he-text-main, #0f172a); }
    .os-branch-meta { font-size: .82rem; color: var(--he-text-muted, #64748b); }
    .os-chip { font-size: .7rem; font-weight: 700; border-radius: 9999px; padding: .2rem .6rem; white-space: nowrap; }
    .os-kebab { width: 32px; height: 32px; border-radius: 10px; border: 0; background: transparent; color: var(--he-text-muted, #64748b); }
    .os-kebab:hover, .os-kebab[aria-expanded="true"] { background: var(--he-bg-surface-raised, #f1f5f9); color: var(--he-text-main, #0f172a); }
    .os-kebab:focus-visible { outline: 2px solid var(--he-primary, #4f46e5); outline-offset: 2px; }

    /* Payments & receipts */
    .os-pay { display: flex; align-items: center; gap: .85rem; padding: .85rem 1.5rem; }
    .os-pay + .os-pay { border-top: 1px solid rgba(15,23,42,.06); }
    .os-pay-ic { width: 36px; height: 36px; border-radius: 10px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; background: var(--he-success-soft, #d1fae5); color: #047857; font-size: .85rem; }
    .os-pay-ic.is-free { background: var(--he-primary-soft, rgba(79,70,229,.08)); color: var(--he-primary, #4f46e5); }

    /* Review sheet */
    .custom-overlay-backdrop { position: fixed; inset: 0; background: rgba(15,23,42,.6); backdrop-filter: blur(8px); z-index: 9999; display: flex; align-items: center; justify-content: center; padding: 1rem; }
    .custom-overlay-modal { width: 100%; background: #fff; border-radius: 1.25rem; box-shadow: 0 25px 50px -12px rgba(0,0,0,.25); display: flex; flex-direction: column; max-height: 92vh; transform: scale(.96) translateY(6px); opacity: 0; transition: all .32s cubic-bezier(.16,1,.3,1); overflow: hidden; }
    .custom-overlay-modal.is-open { transform: none; opacity: 1; }
    .custom-overlay-header { padding: 1.25rem 1.5rem; border-bottom: 1px solid rgba(0,0,0,.05); display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; }
    .custom-overlay-body { padding: 1.5rem; overflow-y: auto; background: #fafafa; }
    .custom-overlay-footer { padding: 1.1rem 1.5rem; border-top: 1px solid rgba(0,0,0,.05); display: flex; gap: .75rem; justify-content: flex-end; align-items: center; flex-wrap: wrap; }
    @media (max-width: 575px) { .custom-overlay-modal { max-height: 100vh; border-radius: 1.25rem 1.25rem 0 0; align-self: flex-end; transform: translateY(24px); } .custom-overlay-backdrop { padding: 0; align-items: flex-end; } }
    .plan-pick { border: 1.5px solid rgba(0,0,0,.08); border-radius: 1rem; padding: .9rem 1rem; cursor: pointer; background: #fff; transition: all .2s var(--ease-out-expo, cubic-bezier(.16,1,.3,1)); text-align: left; width: 100%; }
    .plan-pick.on { border-color: var(--bs-primary); background: rgba(79,70,229,.05); box-shadow: 0 6px 18px rgba(79,70,229,.12); }
    .os-pulse { animation: os-pulse .35s ease; }
    @keyframes os-pulse { 50% { transform: scale(1.035); } }
    .os-incl { display: flex; flex-wrap: wrap; gap: .4rem; }
    .os-incl span { font-size: .74rem; font-weight: 600; background: #fff; border: 1px solid rgba(15,23,42,.1); border-radius: 9999px; padding: .2rem .6rem; color: var(--he-text-main, #0f172a); }
    .os-trust { font-size: .72rem; color: var(--he-text-muted, #64748b); margin-right: auto; }
    .os-steps-dots { display: flex; gap: .35rem; }
    .os-steps-dots i { width: 22px; height: 4px; border-radius: 4px; background: rgba(15,23,42,.12); transition: background .3s ease; }
    .os-steps-dots i.on { background: var(--he-primary, #4f46e5); }
    .sub-sticky { position: fixed; left: 0; right: 0; bottom: 0; z-index: 1030; background: #fff; border-top: 1px solid rgba(0,0,0,.08); padding: .7rem 1rem; box-shadow: 0 -6px 20px rgba(0,0,0,.06); }

    @media (prefers-reduced-motion: reduce) {
        .os-rise { animation: none; opacity: 1; }
        .os-pulse { animation: none; }
        .custom-overlay-modal { transition: none; }
    }
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

    {{-- ══ 1. Hero ══ --}}
    <div class="sub-hero hero-{{ $hero['tone'] }} p-4 mb-4 shadow">
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

                {{-- The one primary action. A renewal already billed — by us or by the
                     owner's earlier attempt — is what gets paid, never a second one. --}}
                @if($status === AccountStatus::Suspended)
                    <a href="mailto:{{ config('hostelease.company.email') }}" class="btn btn-light rounded-pill px-4 fw-bold shadow-sm"><i class="fa-solid fa-headset me-2"></i>{{ __('Contact support') }}</a>
                @elseif($openRenewal && ($openRenewal['link_url'] || $canManage))
                    <div class="d-flex flex-wrap gap-2">
                        @if($openRenewal['link_url'])
                            <a href="{{ $openRenewal['link_url'] }}" target="_blank" rel="noopener" class="btn btn-light rounded-pill px-4 fw-bold shadow-sm tactile-btn">
                                <i class="fa-solid fa-lock me-2"></i>{{ __('Pay') }} {{ hostelease_money($openRenewal['amount']) }}
                            </a>
                        @else
                            <button class="btn btn-light rounded-pill px-4 fw-bold shadow-sm tactile-btn" @click="openOrder({{ $openRenewal['id'] }})">
                                <i class="fa-solid fa-lock me-2"></i>{{ __('Pay') }} {{ hostelease_money($openRenewal['amount']) }}
                            </button>
                        @endif
                        @if($canManage && $openRenewal['own'])
                            <button class="btn btn-outline-light rounded-pill px-3 fw-bold" @click="openRenew()"><i class="fa-solid fa-repeat me-2"></i>{{ __('Change term') }}</button>
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
                <div class="os-rise" style="--i:0"><div class="sub-metric-lbl">{{ __('Branches') }}</div><div class="h4 fw-bold mb-0 sub-metric">{{ $branches->count() }}</div></div>
                <div class="os-rise" style="--i:1"><div class="sub-metric-lbl">{{ $status === AccountStatus::Trial ? __('Trial ends') : __('Renews on') }}</div><div class="h4 fw-bold mb-0 sub-metric">{{ $anchorFmt ?? '—' }}</div></div>
                <div class="os-rise" style="--i:2"><div class="sub-metric-lbl">{{ __('Term') }}</div><div class="h4 fw-bold mb-0 sub-metric">{{ $account->period?->isPaid() ? $account->period->label() : __('Trial') }}</div></div>
                <div class="os-rise" style="--i:3">
                    @if($openRenewal)
                        {{-- Something is already billed: that is the number, in its own term. --}}
                        <div class="sub-metric-lbl">{{ __('Due now') }}</div>
                        <div class="h4 fw-bold mb-0 sub-metric">{{ hostelease_money($openRenewal['amount']) }}</div>
                        <div class="small text-white-50" style="white-space:nowrap;">{{ $openRenewal['period'] }}</div>
                    @elseif($status === AccountStatus::Trial)
                        {{-- No term chosen yet: show both, never one as if it were THE price. --}}
                        <div class="sub-metric-lbl">{{ __('After the trial') }}</div>
                        <div class="h4 fw-bold mb-0 sub-metric">{{ hostelease_money($quotes['yearly']['final']) }}<span class="fs-6 fw-normal text-white-50">/{{ __('yr') }}</span></div>
                        <div class="small text-white-50" style="white-space:nowrap;">{{ __('or') }} {{ hostelease_money($quotes['monthly']['final']) }}/{{ __('mo') }}</div>
                    @else
                        <div class="sub-metric-lbl">{{ __('Next total') }}</div>
                        <div class="h4 fw-bold mb-0 sub-metric">{{ hostelease_money($q['final']) }}</div>
                        @if($q['discount'] > 0)<div class="small text-white-50" style="white-space:nowrap;">{{ __('Discount') }} −{{ hostelease_money($q['discount']) }}</div>@endif
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ══ 2. Next step ══ Everything waiting on the owner, each with one button. --}}
    @if($hasSteps)
        <div class="panel-card shadow-sm mb-4 os-steps os-rise" style="--i:4">
            <div class="p-3 px-4 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid {{ $dueRows->isNotEmpty() ? 'fa-file-invoice' : 'fa-list-check' }} text-primary me-2"></i>{{ $dueRows->isNotEmpty() ? __('Payment due') : __('Next step') }}
                </h6>
                <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-2">{{ $dueRows->count() + ($alignOffer ? 1 : 0) + ($singleBehind ? 1 : 0) }}</span>
            </div>

            @foreach($due as $row)
                <div class="os-step">
                    <div class="os-step-ic {{ $row['payable'] ? '' : 'is-warn' }}"><i class="fa-solid {{ $row['kind'] === 'renewal' ? 'fa-arrows-rotate' : (in_array($row['kind'], ['add_branch', 'align'], true) ? 'fa-diagram-project' : 'fa-file-invoice') }}"></i></div>
                    <div class="os-step-body">
                        <div class="os-step-title">{{ $row['label'] }}@if($row['period']) · {{ $row['period'] }}@endif <span class="fw-normal text-muted small">· {{ $row['quantity'] }} {{ __('branch(es)') }}</span></div>
                        <div class="os-step-sub">
                            {{ $row['invoice'] }} · {{ __('raised') }} {{ $row['raised'] }}
                            @if($row['link_expires']) · {{ __('link valid until') }} {{ $row['link_expires'] }} @endif
                        </div>
                    </div>
                    <div class="os-step-amt">{{ hostelease_money($row['amount']) }}</div>
                    <div class="os-step-act">
                        @if($row['stale'])
                            <div class="d-flex flex-column align-items-end gap-1">
                                <span class="badge bg-warning-subtle text-warning rounded-pill px-3 py-2" title="{{ __('Your branches changed since this was raised, so the amount has changed.') }}">
                                    <i class="fa-solid fa-rotate me-1"></i>{{ __('Amount changed — renew again for the new total') }}
                                </span>
                                @if($canManage && $row['own'])
                                    <button type="button" class="btn btn-link btn-sm p-0 fw-semibold" @click="openRenew()">{{ __('Renew again') }}</button>
                                @endif
                            </div>
                        @elseif(! $row['payable'])
                            <span class="badge bg-success-subtle text-success rounded-pill px-3 py-2">
                                <i class="fa-solid fa-circle-check me-1"></i>{{ __('Already covered — nothing to pay') }}
                            </span>
                        @elseif($row['link_url'])
                            <a href="{{ $row['link_url'] }}" target="_blank" rel="noopener" class="btn btn-sm btn-primary rounded-pill px-3 fw-semibold shadow-sm">
                                <i class="fa-solid fa-lock me-1"></i>{{ __('Pay securely') }}
                            </a>
                        @elseif($canManage)
                            <div class="d-flex align-items-center gap-3 justify-content-end">
                                @if($row['own'])
                                    <button type="button" class="btn btn-link btn-sm text-muted p-0" @click="openRenew()">{{ __('Change term') }}</button>
                                @endif
                                <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 fw-semibold shadow-sm" @click="openOrder({{ $row['id'] }})">
                                    {{ __('Review & pay') }}
                                </button>
                            </div>
                        @else
                            <span class="small text-muted">{{ __('Contact us to pay') }}</span>
                        @endif
                    </div>
                </div>
            @endforeach

            @if($alignOffer)
                <div class="os-step">
                    <div class="os-step-ic"><i class="fa-solid fa-diagram-project"></i></div>
                    <div class="os-step-body">
                        <div class="os-step-title">{{ __(':n branches end before :date', ['n' => $alignOffer['count'], 'date' => $alignOffer['anchor']]) }}</div>
                        <div class="os-step-sub">{{ __('Bring them onto your renewal date in one payment, so every branch renews together.') }}</div>
                    </div>
                    <div class="os-step-amt">{{ hostelease_money($alignOffer['total']) }}</div>
                    <div class="os-step-act"><button type="button" class="btn btn-sm btn-primary rounded-pill px-3 fw-semibold shadow-sm" @click="openAlign()">{{ __('Bring all up to date') }}</button></div>
                </div>
            @endif

            @if($singleBehind)
                <div class="os-step">
                    <div class="os-step-ic"><i class="fa-solid fa-diagram-project"></i></div>
                    <div class="os-step-body">
                        <div class="os-step-title">{{ __(':name is not covered to :date', ['name' => $singleBehind['name'], 'date' => $singleBehind['anchor']]) }}</div>
                        <div class="os-step-sub">{{ __('Add it to your plan so it renews with every other branch.') }}</div>
                    </div>
                    <div class="os-step-amt">{{ hostelease_money($singleBehind['amount']) }}</div>
                    <div class="os-step-act"><button type="button" class="btn btn-sm btn-primary rounded-pill px-3 fw-semibold shadow-sm" @click="openPlan({{ $singleBehind['id'] }})">{{ __('Add to plan') }}</button></div>
                </div>
            @endif
        </div>
    @endif

    <div class="row g-4">
        {{-- ══ 3a. Branches ══ --}}
        <div class="col-lg-7">
            <div class="panel-card shadow-sm h-100 os-rise" style="--i:5">
                <div class="p-3 px-4 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-hotel text-primary me-2"></i>{{ __('Your branches') }}</h6>
                    <span class="text-muted small">
                        {{ $billableCount }} {{ __('on your plan') }}
                        @if($branches->count() > $billableCount) · {{ $branches->count() - $billableCount }} {{ __('closing') }} @endif
                    </span>
                </div>

                @forelse($branches as $branch)
                    @php($state = $branch->cancellationState())
                    @php($behind = isset($addable[$branch->id]))
                    <div class="os-branch os-rise" style="--i:{{ 6 + $loop->index }}">
                        <span class="os-dot {{ in_array($state, ['cancelled', 'closed'], true) ? 'muted' : (! $branch->isActive() ? 'off' : ($behind ? 'warn' : 'ok')) }}"></span>
                        <div class="flex-grow-1 min-w-0">
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <span class="os-branch-name">{{ $branch->name }}</span>
                                @php($chip = match($state) {
                                    'removal_requested' => ['warning', __('Removal asked')],
                                    'cancelled' => ['secondary', __('Closing')],
                                    'closed' => ['secondary', __('Closed')],
                                    default => $branch->isActive() ? ($behind ? ['warning', __('Behind')] : ['success', __('Active')]) : ['danger', __('Not active')],
                                })
                                <span class="os-chip bg-{{ $chip[0] }}-subtle text-{{ $chip[0] }}">{{ $chip[1] }}</span>
                                @if($branch->free_renewals > 0 && ! $branch->isCancelled())
                                    <span class="os-chip bg-primary-subtle text-primary"><i class="fa-solid fa-gift me-1"></i>{{ $branch->free_renewals === 1 ? __('Next renewal free') : __(':n renewals free', ['n' => $branch->free_renewals]) }}</span>
                                @endif
                            </div>
                            <div class="os-branch-meta mt-1">
                                @if($state === 'cancelled')
                                    {{ __('Closing') }} {{ $branch->subscription_end?->format('d M Y') }} · {{ __('not billed at your next renewal') }}
                                @elseif($state === 'closed')
                                    {{ __('Closed') }} {{ $branch->subscription_end?->format('d M Y') }}
                                @elseif(! $branch->subscription_end)
                                    {{ $behind ? __('Not on your plan yet — add it to switch it on') : __('Starts when you subscribe — included in your next payment') }}
                                @elseif($behind)
                                    {{ __('Covered to') }} {{ $branch->subscription_end->format('d M Y') }} · {{ __('your plan renews') }} {{ $anchorFmt }}
                                @else
                                    {{ __('Covered to') }} {{ $branch->subscription_end->format('d M Y') }}
                                @endif
                            </div>
                            @if($state === 'removal_requested')
                                <div class="small mt-1" style="color:#ea580c;"><i class="fa-solid fa-clock me-1"></i>{{ __('Removal requested — our team will be in touch. Nothing has changed yet.') }}</div>
                            @endif
                        </div>

                        <div class="d-flex align-items-center gap-1 flex-shrink-0">
                            {{-- One clear action on the page lives in Next step. A branch's own
                                 options — paying for it alone, asking to remove it — sit in its
                                 menu, so a list of behind branches is not a wall of buttons.
                                 Removal is a REQUEST (D11), owner only — the gate is the FK. --}}
                            @if($behind || ($viewerOwnsAccount && ! $branch->isCancelled()))
                                <div class="dropdown">
                                    <button class="os-kebab" data-bs-toggle="dropdown" aria-expanded="false" aria-label="{{ __('More for :name', ['name' => $branch->name]) }}"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-4 p-2">
                                        @if($behind)
                                            <li>
                                                <button type="button" class="dropdown-item rounded-3 py-2" @click="openPlan({{ $branch->id }})">
                                                    <i class="fa-solid fa-plus me-2 text-primary"></i>{{ __('Add to plan') }} · {{ hostelease_money($addable[$branch->id]['amount']) }}
                                                </button>
                                            </li>
                                            @if($viewerOwnsAccount && ! $branch->isCancelled())<li><hr class="dropdown-divider"></li>@endif
                                        @endif
                                        @if(! $viewerOwnsAccount || $branch->isCancelled())
                                            {{-- nothing more for this viewer --}}
                                        @elseif($state === 'removal_requested')
                                            <li>
                                                <form method="POST" action="{{ route('admin.branches.withdraw-removal', $branch) }}" data-confirm="{{ __('Withdraw your request to remove') }} {{ $branch->name }}?">
                                                    @csrf @method('DELETE')
                                                    <button class="dropdown-item rounded-3 py-2"><i class="fa-solid fa-rotate-left me-2 text-muted"></i>{{ __('Withdraw') }}</button>
                                                </form>
                                            </li>
                                        @else
                                            <li>
                                                <button type="button" class="dropdown-item rounded-3 py-2 text-danger"
                                                        @click="removeBranchId = {{ $branch->id }}; removeBranchName = @js($branch->name); removeOpen = true">
                                                    <i class="fa-solid fa-circle-minus me-2"></i>{{ __('Request removal') }}
                                                </button>
                                            </li>
                                        @endif
                                    </ul>
                                </div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="p-4"><x-he-empty-state icon="hotel" title="{{ __('No branches yet') }}" subtitle="{{ __('Add a branch to get started.') }}" /></div>
                @endforelse
            </div>
        </div>

        {{-- ══ 3b. Payments & receipts ══ Money paid, and free grants as "Free". --}}
        <div class="col-lg-5">
            <div class="panel-card shadow-sm h-100 os-rise" style="--i:6">
                <div class="p-3 px-4 border-bottom"><h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-receipt text-primary me-2"></i>{{ __('Payments & receipts') }}</h6></div>
                @forelse($history as $h)
                    <div class="os-pay">
                        <span class="os-pay-ic {{ $h['free'] ? 'is-free' : '' }}"><i class="fa-solid {{ $h['free'] ? 'fa-gift' : 'fa-check' }}"></i></span>
                        <div class="flex-grow-1 min-w-0">
                            <div class="fw-bold text-dark">{{ $h['free'] ? __('Free') : hostelease_money($h['amount']) }}</div>
                            <div class="small text-muted text-truncate">{{ $h['label'] }}@if($h['period']) · {{ $h['period'] }}@endif · {{ $h['branches'] }} {{ __('branch(es)') }} · {{ $h['date'] }}</div>
                        </div>
                        @if($h['receipt'])
                            <a href="{{ $h['receipt'] }}" class="he-icon-btn" title="{{ __('Download receipt') }}" aria-label="{{ __('Download receipt :no', ['no' => $h['invoice']]) }}"><i class="fa-solid fa-download"></i></a>
                        @endif
                    </div>
                @empty
                    <div class="p-4"><x-he-empty-state icon="receipt" title="{{ __('No payments yet') }}" subtitle="{{ __('Your renewals and receipts will appear here.') }}" /></div>
                @endforelse
            </div>
        </div>
    </div>

    @unless($selfServe)
        <div class="d-flex align-items-start gap-3 mt-4 p-3 px-4 rounded-4 shadow-sm" style="background:var(--he-warning-soft,#fef3c7); border:1px solid rgba(245,158,11,.25);">
            <i class="fa-solid fa-shield-halved fs-5 mt-1" style="color:var(--he-warning,#f59e0b);"></i>
            <div>
                <div class="fw-bold text-dark" style="font-size:.92rem;">{{ __('Billing is managed by HostelEase support') }}</div>
                <div class="small text-muted">{{ __('Your plans and coverage above are always up to date. To renew, add a branch, or change your plan, contact support and our team will set it up on your account. Any payment link we send you can be paid right here.') }}</div>
            </div>
        </div>
    @endunless

    {{-- ── Mobile action bar ── --}}
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
                        <button class="btn btn-primary rounded-pill px-4 fw-bold flex-grow-1 tactile-btn" @click="openOrder({{ $openRenewal['id'] }})"><i class="fa-solid fa-lock me-2"></i>{{ __('Pay now') }}</button>
                    @endif
                @else
                    <div class="flex-shrink-0">
                        <div class="small text-muted lh-1" x-text="period === 'monthly' ? @js(__('Monthly total')) : @js(__('Yearly total'))"></div>
                        <div class="fw-bold text-dark" x-text="money(quotes[period].final)"></div>
                    </div>
                    <button class="btn btn-primary rounded-pill px-4 fw-bold flex-grow-1 tactile-btn" @click="openRenew()"><i class="fa-solid fa-arrows-rotate me-2"></i>{{ $hero['cta'] }}</button>
                @endif
            </div>
        </div>
    @endif

    @if($canManage)
    {{-- ══ The review sheet ══ One sheet for every charge. It only SHOWS the
         server's figures; what is charged is decided again on the server. --}}
    <template x-teleport="body">
        <div class="custom-overlay-backdrop" x-show="sheet.open" x-transition.opacity @click.self="closeSheet()" @keydown.escape.window="closeSheet()" x-cloak style="display:none;">
            <div class="custom-overlay-modal" style="max-width:540px;" :class="{ 'is-open': sheet.open }" role="dialog" aria-modal="true" :aria-label="sheetTitle">
                <div class="custom-overlay-header">
                    <div>
                        <h5 class="fw-bold mb-0" x-text="sheetTitle"></h5>
                        <div class="small text-muted mt-1" x-text="sheetSubtitle"></div>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="os-steps-dots" x-show="sheet.kind === 'add'" aria-hidden="true"><i class="on"></i><i :class="{ on: addStep === 2 }"></i></div>
                        <button type="button" class="btn-close" @click="closeSheet()" :disabled="loading" aria-label="{{ __('Close') }}"></button>
                    </div>
                </div>

                <div class="custom-overlay-body">
                    {{-- Renew: the term first --}}
                    <div x-show="sheet.kind === 'renew'" class="row g-2 mb-3">
                        <div class="col-6">
                            <button type="button" class="plan-pick h-100" :class="{ 'on': period === 'yearly' }" @click="setPeriod('yearly')">
                                <div class="fw-bold text-uppercase small" :class="period === 'yearly' ? 'text-primary' : 'text-muted'">{{ __('Yearly') }}</div>
                                <div class="h5 fw-bold text-dark mb-0" x-text="money(quotes.yearly.unit)"></div>
                                <div class="small text-muted">{{ __('per branch') }}</div>
                                @if($yearlySaving)<div class="small text-success fw-bold">{{ __('Save') }} {{ $yearlySaving }}%</div>@endif
                            </button>
                        </div>
                        <div class="col-6">
                            <button type="button" class="plan-pick h-100" :class="{ 'on': period === 'monthly' }" @click="setPeriod('monthly')">
                                <div class="fw-bold text-uppercase small" :class="period === 'monthly' ? 'text-primary' : 'text-muted'">{{ __('Monthly') }}</div>
                                <div class="h5 fw-bold text-dark mb-0" x-text="money(quotes.monthly.unit)"></div>
                                <div class="small text-muted">{{ __('per branch') }}</div>
                            </button>
                        </div>
                    </div>

                    {{-- Add a branch, step 1: its details --}}
                    <div x-show="sheet.kind === 'add' && addStep === 1" x-transition:enter.duration.250ms>
                        <label class="form-label fw-bold small text-muted" for="os-add-name">{{ __('BRANCH NAME') }}</label>
                        <input id="os-add-name" type="text" x-model="add.name" class="form-control bg-white border shadow-sm mb-3" placeholder="e.g. Sunrise Riverside" maxlength="255" @keydown.enter.prevent="add.name && (addStep = 2)">
                        <label class="form-label fw-bold small text-muted" for="os-add-city">{{ __('CITY') }} <span class="fw-normal">— {{ __('optional') }}</span></label>
                        <input id="os-add-city" type="text" x-model="add.city" class="form-control bg-white border shadow-sm" placeholder="e.g. Surat" maxlength="100">
                    </div>

                    {{-- Every money view: the shared summary (the same rows Account 360 shows) --}}
                    <div x-show="sheet.kind !== 'add' || addStep === 2" x-transition:enter.duration.250ms :class="{ 'os-pulse': bump }">
                        <x-he-billing-summary data="sheetSummary" />
                    </div>

                    {{-- Renew: the grouped top-ups, itemised on demand --}}
                    <div x-show="sheet.kind === 'renew' && (quotes[period].topups || []).length > 2" x-data="{ more: false }" class="mt-2">
                        <button type="button" class="btn btn-link btn-sm p-0 fw-semibold text-decoration-none" @click="more = !more">
                            <i class="fa-solid fa-chevron-right me-1 small" :style="more ? 'transform:rotate(90deg)' : ''" style="transition:transform .2s ease"></i>
                            <span x-text="more ? @js(__('Hide the top-ups')) : @js(__('See each branch\'s top-up'))"></span>
                        </button>
                        <div x-show="more" x-collapse>
                            <div class="he-summary shadow-sm mt-2">
                                <template x-for="t in (quotes[period].topups || [])" :key="t.name">
                                    <div class="he-summary-row he-summary-row--line"><span x-text="t.name + ' · ' + t.days + ' ' + @js(__('days'))"></span><span class="he-summary-amt" x-text="money(t.amount)"></span></div>
                                </template>
                            </div>
                        </div>
                    </div>

                    {{-- Renew: exactly which branches this renews, and which are left out --}}
                    <div x-show="sheet.kind === 'renew'" class="mt-3">
                        <div class="small fw-semibold text-muted mb-2">{{ __('This renews') }}</div>
                        <div class="os-incl"><template x-for="n in (quotes[period].included || [])" :key="n"><span x-text="n"></span></template></div>
                        <div class="small text-muted mt-2" x-show="(quotes[period].closing || []).length">
                            <i class="fa-solid fa-circle-info me-1"></i>{{ __('Not included (closing):') }} <span x-text="(quotes[period].closing || []).join(', ')"></span>
                        </div>
                    </div>
                </div>

                <div class="custom-overlay-footer">
                    <span class="os-trust" x-show="sheetPays"><i class="fa-solid fa-shield-halved me-1"></i>{{ __('Secure payment by Razorpay') }}</span>

                    {{-- x-show sits on a plain wrapper: Bootstrap's .d-flex is !important
                         and would override the display:none x-show writes. --}}
                    <div class="ms-auto" x-show="sheet.kind === 'add' && addStep === 1">
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-light rounded-pill px-4 fw-bold" @click="closeSheet()">{{ __('Cancel') }}</button>
                            <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" @click="addStep = 2" :disabled="!add.name">{{ __('Continue') }} <i class="fa-solid fa-arrow-right ms-1"></i></button>
                        </div>
                    </div>
                    <div class="ms-auto" x-show="sheet.kind === 'add' && addStep === 2">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <button type="button" class="btn btn-light rounded-pill px-3 fw-bold" @click="addStep = 1" :disabled="loading"><i class="fa-solid fa-arrow-left me-1"></i>{{ __('Back') }}</button>
                            <template x-if="newBranch && newBranch.mode === 'prorate'">
                                <button type="button" class="btn btn-link text-muted fw-semibold text-decoration-none" @click="addBranch(false)" :disabled="loading">{{ __('Add now, pay later') }}</button>
                            </template>
                            <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" @click="addBranch(newBranch && newBranch.mode === 'prorate')" :disabled="loading">
                                <span x-show="!loading" x-text="addCta"></span>
                                <span x-show="loading" class="spinner-border spinner-border-sm"></span>
                            </button>
                        </div>
                    </div>

                    {{-- Every charge --}}
                    <div class="ms-auto" x-show="sheet.kind && sheet.kind !== 'add'">
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-light rounded-pill px-4 fw-bold" @click="closeSheet()" :disabled="loading">{{ __('Cancel') }}</button>
                            <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" @click="pay()" :disabled="loading">
                                <span x-show="!loading"><i class="fa-solid fa-lock me-1"></i>{{ __('Pay') }} <span x-text="money(sheetSummary.final)"></span></span>
                                <span x-show="loading" class="spinner-border spinner-border-sm"></span>
                            </button>
                        </div>
                    </div>
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
        loading: false,
        removeOpen: false,
        removeBranchId: null,
        removeBranchName: '',

        // Server figures — displayed, never sent back.
        period: @json($displayPeriod),
        quotes: @json($quotes),
        addable: @json((object) $addable),
        alignOffer: @json($alignOffer),
        newBranch: @json($newBranch),
        dueRows: @json((object) collect($due)->keyBy('id')->all()),

        sheet: { open: false, kind: null, id: null },
        addStep: 1,
        add: { name: '', city: '' },
        bump: false,

        money(v) { const n = Number(v || 0); return '₹' + n.toLocaleString('en-IN', { minimumFractionDigits: Number.isInteger(Math.round(n * 100) / 100) ? 0 : 2, maximumFractionDigits: 2 }); },

        // ── Opening the sheet ──
        openSheet(kind, id = null) { this.sheet = { open: true, kind, id }; },
        closeSheet() { if (!this.loading) this.sheet.open = false; },
        openRenew() { this.openSheet('renew'); },
        openOrder(id) { this.openSheet('order', id); },
        openPlan(id) { this.openSheet('plan', id); },
        openAlign() { this.openSheet('align'); },
        openAdd() { this.add = { name: '', city: '' }; this.addStep = 1; this.openSheet('add'); },
        setPeriod(p) {
            if (this.period === p) return;
            this.period = p;
            // The total changed: say so, briefly.
            this.bump = false; this.$nextTick(() => { this.bump = true; setTimeout(() => this.bump = false, 360); });
        },

        get sheetPays() { return this.sheet.kind && (this.sheet.kind !== 'add' || (this.addStep === 2 && this.newBranch?.mode === 'prorate')); },
        get addCta() {
            const n = this.newBranch || {};
            if (n.mode === 'prorate') return @js(__('Add & pay')) + ' ' + this.money(n.final);
            return n.mode === 'trial' ? @js(__('Add to my trial')) : @js(__('Add branch'));
        },

        get sheetTitle() {
            const r = this.dueRows[this.sheet.id];
            switch (this.sheet.kind) {
                case 'renew': return @js(__('Renew all branches'));
                case 'order': return r ? r.label + (r.period ? ' · ' + r.period : '') : @js(__('Charge'));
                case 'plan': return @js(__('Add to your plan'));
                case 'align': return @js(__('Bring all up to date'));
                case 'add': return @js(__('Add a new branch'));
            }
            return '';
        },
        get sheetSubtitle() {
            switch (this.sheet.kind) {
                case 'renew': return @js(__('Every branch renews together, on one date.'));
                case 'order': { const r = this.dueRows[this.sheet.id]; return r ? (r.invoice + ' · ' + @js(__('raised')) + ' ' + r.raised) : ''; }
                case 'plan': { const a = this.addable[this.sheet.id]; return a ? a.name : ''; }
                case 'align': return this.alignOffer ? @js(__('Onto your renewal date')) + ' — ' + this.alignOffer.anchor : '';
                case 'add': return this.addStep === 1 ? @js(__('Step 1 of 2 · details')) : @js(__('Step 2 of 2 · what it costs'));
            }
            return '';
        },

        // ── The rows. Every one comes from a server quote. ──
        get sheetSummary() {
            const k = this.sheet.kind;
            if (k === 'renew') {
                const q = this.quotes[this.period];
                const rows = [{ label: q.quantity + ' ' + @js(__('branch(es)')) + ' × ' + this.money(q.unit) + (this.period === 'monthly' ? '/mo' : '/yr'), amount: q.subtotal, kind: 'line' }];
                const tops = q.topups || [];
                if (tops.length > 2) {
                    // Several branches behind: one line, the detail one tap away below.
                    rows.push({ label: @js(__('Bring')) + ' ' + tops.length + ' ' + @js(__('branches up to')) + ' ' + q.current_anchor, amount: tops.reduce((s, t) => s + t.amount, 0), kind: 'line' });
                } else {
                    tops.forEach(t => rows.push({ label: @js(__('Up to')) + ' ' + q.current_anchor + ' · ' + t.name + ' · ' + t.days + 'd', amount: t.amount, kind: 'line' }));
                }
                (q.complimentary || []).forEach(c => rows.push({ label: @js(__('Free renewal')) + ' · ' + c.name, amount: c.amount, kind: 'discount' }));
                if (q.volume > 0) rows.push({ label: @js(__('Multi-branch discount')), amount: q.volume, kind: 'discount' });
                if (q.manual > 0) rows.push({ label: @js(__('Your discount')), amount: q.manual, kind: 'discount' });
                return { rows, finalLabel: @js(__('Total payable')), final: q.final, note: @js(__('New renewal date')) + ': ' + q.new_anchor + ' — ' + @js(__('all branches together.')) };
            }
            if (k === 'order') {
                const r = this.dueRows[this.sheet.id];
                if (!r) return { rows: [], final: 0 };
                const rows = r.lines.map(l => l.free
                    ? { label: @js(__('Free renewal')) + ' · ' + l.name, amount: 0, kind: 'subtle' }
                    : { label: l.name + (l.from && l.to ? ' · ' + l.from + ' → ' + l.to : ''), amount: l.amount, kind: 'line' });
                if (r.discount > 0) rows.push({ label: @js(__('Includes discounts of')), amount: r.discount, kind: 'subtle' });
                return { rows, finalLabel: @js(__('Total payable')), final: r.amount, note: @js(__('The amount on your invoice')) + ' ' + r.invoice + '.' };
            }
            if (k === 'plan') {
                const a = this.addable[this.sheet.id];
                if (!a) return { rows: [], final: 0 };
                const rows = [{ label: a.days + ' ' + @js(__('days to')) + ' ' + a.anchor + ' · ' + this.money(a.unit) + ' ' + @js(__('per term, prorated')), amount: a.prorated, kind: 'line' }];
                if (a.volume > 0) rows.push({ label: @js(__('Multi-branch discount')), amount: a.volume, kind: 'discount' });
                if (a.manual > 0) rows.push({ label: @js(__('Your discount')), amount: a.manual, kind: 'discount' });
                return { rows, finalLabel: @js(__('Total payable')), final: a.amount, note: @js(__('Covered to')) + ' ' + a.anchor + ' — ' + @js(__('then it renews with every other branch.')) };
            }
            if (k === 'align') {
                const o = this.alignOffer;
                if (!o) return { rows: [], final: 0 };
                return { rows: o.lines.map(l => ({ label: l.name + ' · ' + l.days + ' ' + @js(__('days')), amount: l.amount, kind: 'line' })), finalLabel: @js(__('Total payable')), final: o.total, note: @js(__('Every branch then renews together on')) + ' ' + o.anchor + '.' };
            }
            if (k === 'add') {
                const n = this.newBranch || {};
                const name = this.add.name || @js(__('New branch'));
                if (n.mode === 'trial') return { rows: [{ label: name + ' · ' + @js(__('joins your free trial')), amount: 0, kind: 'line' }], finalLabel: @js(__('Payable now')), final: 0, note: @js(__('It works until')) + ' ' + n.until + '. ' + @js(__('Every branch is billed together when you subscribe.')) };
                if (n.mode === 'prorate') {
                    const rows = [{ label: name + ' · ' + n.days + ' ' + @js(__('days to')) + ' ' + n.anchor, amount: n.prorated, kind: 'line' }];
                    if (n.volume > 0) rows.push({ label: @js(__('Multi-branch discount')), amount: n.volume, kind: 'discount' });
                    if (n.manual > 0) rows.push({ label: @js(__('Your discount')), amount: n.manual, kind: 'discount' });
                    return { rows, finalLabel: @js(__('Payable now')), final: n.final, note: @js(__('Then it renews with every branch on')) + ' ' + n.anchor + '. ' + @js(__('Or add it now and pay later — it stays switched off until it is paid for.')) };
                }
                return { rows: [{ label: name + ' · ' + @js(__('added to your plan')), amount: 0, kind: 'line' }], finalLabel: @js(__('Payable now')), final: 0, note: @js(__('It starts when you subscribe, at')) + ' ' + this.money(n.yearly) + '/' + @js(__('yr or')) + ' ' + this.money(n.monthly) + '/' + @js(__('mo per branch.')) };
            }
            return { rows: [], final: 0 };
        },

        // ── Paying. A SHAPE goes to the server, never an amount. ──
        pay() {
            const k = this.sheet.kind;
            const expected = this.sheetSummary.final;
            if (k === 'renew') return this.start({ charge: 'renewal', period: this.period }, expected);
            if (k === 'order') return this.start({ charge: 'order', order_id: this.sheet.id }, expected);
            if (k === 'plan') return this.start({ charge: 'add_branch', branch_id: this.sheet.id }, expected);
            if (k === 'align') return this.start({ charge: 'align' }, expected);
        },

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

        // `expected` only tells the owner if the price moved since the page loaded —
        // it is never sent, and never what they are charged.
        async start(body, expected) {
            this.loading = true;
            try {
                const result = await this.post(@json(route('admin.subscription.checkout')), body);
                if (result.mode === 'checkout' && expected !== undefined && Math.abs(result.razorpay.amount / 100 - expected) > 0.005) {
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
                this.toast(result.message, 'info');
                window.location.href = result.url;
                return;
            }
            if (result.mode === 'checkout') {
                this.sheet.open = false;
                return this.openCheckout(result.razorpay);
            }
            // 'paid', 'created' or 'held' — 'held' needs a human check: never shown as success.
            this.sheet.open = false;
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

        // ONLY Razorpay's three ids — the server finds the charge and reads the amount.
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
