@extends('layouts.app')
@section('title', 'Billing archive')

{{-- ─────────────────────────────────────────────────────────────────────────
     READ-ONLY ARCHIVE (S1 · decision D8).

     `subscriptions` was the original per-branch ledger. Since S1 nothing writes
     it: every charge is a subscription_order with per-branch lines, which is the
     only ledger. This page keeps the historical rows readable and nothing more —
     its old Add / Edit / Accept / Delete controls were removed with the routes
     behind them, because each capability now lives somewhere better:

       record · renew · align · comp   → Customers → Account 360
       accept a pending payment        → Account 360 → Orders → Accept
       write off a mistaken record     → Account 360 → Orders → Void
       per-branch history              → the hostel profile's Billing card

     Not in the sidebar; reachable by URL as a power-user fallback.
   ───────────────────────────────────────────────────────────────────────── --}}

@push('styles')
<style>
    .arch-tile { background: rgba(255,255,255,.85); backdrop-filter: blur(20px); border: 1px solid rgba(0,0,0,.05); border-radius: 1rem; }
    .arch-value { font-size: 1.5rem; font-weight: 800; letter-spacing: -.5px; font-variant-numeric: tabular-nums; }
    .arch-label { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #64748b; }
    .arch-note { background: linear-gradient(135deg, rgba(79,70,229,.07), rgba(124,58,237,.05)); border: 1px solid rgba(79,70,229,.15); border-radius: 1rem; }
    /* Aligned Row System (§4.11): fixed tracks, so figures never drift between rows. */
    .arch-row { display: grid; grid-template-columns: minmax(0,1.6fr) 7rem minmax(0,1fr) 7.5rem 6.5rem; gap: 1rem; align-items: center; }
    @media (max-width: 991.98px) { .arch-row { grid-template-columns: minmax(0,1fr) auto; row-gap: .35rem; } .arch-row .arch-hide-sm { display: none; } }
    .arch-num { font-variant-numeric: tabular-nums; }
</style>
@endpush

@section('content')
<div class="container-fluid py-4 page-enter">

    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div>
            <h4 class="fw-bold mb-1 text-dark">Billing archive</h4>
            <p class="text-muted mb-0 small">Historical per-branch subscription records. Read-only.</p>
        </div>
        <a href="{{ route('superadmin.accounts.index') }}" class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm">
            <i class="fa-solid fa-users me-2"></i>Go to Customers
        </a>
    </div>

    <div class="arch-note p-3 px-4 mb-4 d-flex align-items-start gap-3">
        <i class="fa-solid fa-box-archive mt-1" style="color: var(--he-primary);"></i>
        <div class="small">
            <div class="fw-bold text-dark mb-1">Nothing new is written here</div>
            <div class="text-muted">
                Billing moved to the account ledger: one order per payment, with a line per branch.
                Record a charge, renew, align or comp from <a href="{{ route('superadmin.accounts.index') }}" class="fw-semibold">Customers → Account&nbsp;360</a>;
                accept a pending payment or write off a mistaken record from that account's <strong>Orders</strong> list.
                Per-branch history lives on each hostel's profile.
                @if($summary['last_written'])
                    <br><span class="text-muted">Last archive entry: {{ \Illuminate\Support\Carbon::parse($summary['last_written'])->format('d M Y') }}.</span>
                @endif
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4 stagger">
        <div class="col-6 col-lg-4">
            <div class="arch-tile p-3 px-4 h-100">
                <div class="arch-label">Archived records</div>
                <div class="arch-value text-dark">{{ number_format($summary['records']) }}</div>
            </div>
        </div>
        <div class="col-6 col-lg-4">
            <div class="arch-tile p-3 px-4 h-100">
                <div class="arch-label">Paid (lifetime)</div>
                <div class="arch-value text-success">{{ hostelease_money($summary['paid']) }}</div>
            </div>
        </div>
        <div class="col-6 col-lg-4">
            <div class="arch-tile p-3 px-4 h-100">
                <div class="arch-label">Never settled</div>
                <div class="arch-value text-warning">{{ hostelease_money($summary['pending']) }}</div>
            </div>
        </div>
    </div>

    <div class="panel-card shadow-sm">
        <div class="p-3 px-4 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-clock-rotate-left text-primary me-2"></i>Records</h6>
            <form method="GET" class="d-flex align-items-center gap-2">
                <x-he-select name="status" :submit="true" compact :selected="request('status')" :options="['' => 'All statuses', 'paid' => 'Paid', 'pending' => 'Pending', 'failed' => 'Failed']" />
            </form>
        </div>

        <div class="px-4 py-2 border-bottom d-none d-lg-block">
            <div class="arch-row arch-label">
                <div>Branch</div>
                <div>Plan</div>
                <div>Covered</div>
                <div class="text-end">Amount</div>
                <div class="text-end">Status</div>
            </div>
        </div>

        <div class="stagger">
            @forelse($subscriptions as $s)
                <div class="px-4 py-3 border-bottom arch-row">
                    <div class="text-truncate">
                        @if($s->hostel)
                            {{-- The hostel profile is the live surface; link by the model so the
                                 opaque public_id is used, never the integer (public-id hardening U4). --}}
                            <a href="{{ route('superadmin.hostels.show', $s->hostel) }}" class="fw-bold text-dark text-decoration-none">{{ $s->hostel->name }}</a>
                        @else
                            <span class="fw-bold text-muted">Branch #{{ $s->hostel_id }}</span>
                        @endif
                        <div class="small text-muted">
                            {{ $s->payment_method ? ucfirst($s->payment_method) : 'No method recorded' }}
                            @if($s->transaction_number) · {{ $s->transaction_number }} @endif
                        </div>
                    </div>
                    <div class="small text-muted arch-hide-sm">{{ ucfirst((string) $s->plan) }}</div>
                    <div class="small text-muted arch-hide-sm arch-num">
                        {{ $s->start_date?->format('d M Y') }} → {{ $s->end_date?->format('d M Y') }}
                    </div>
                    <div class="text-end fw-bold text-dark arch-num arch-hide-sm">{{ hostelease_money($s->amount) }}</div>
                    <div class="text-end">
                        @php($tone = $s->payment_status === 'paid' ? 'success' : ($s->payment_status === 'pending' ? 'warning' : 'danger'))
                        <span class="badge bg-{{ $tone }}-subtle text-{{ $tone }} rounded-pill px-3 py-2">{{ ucfirst((string) $s->payment_status) }}</span>
                    </div>
                </div>
            @empty
                <div class="p-4">
                    <x-he-empty-state icon="box-archive" title="Nothing archived"
                        subtitle="No historical per-branch records exist. All billing lives in the account ledger." />
                </div>
            @endforelse
        </div>

        @if($subscriptions->hasPages())
            <div class="p-3 px-4">{{ $subscriptions->links() }}</div>
        @endif
    </div>
</div>
@endsection
