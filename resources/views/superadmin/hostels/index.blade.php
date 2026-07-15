@extends('layouts.app')
@section('title', 'Hostels')

@push('styles')
<style>
    /* .panel-card / .panel-head / .panel-body are canonical in _premium.scss — do not redeclare. */
    .h13-tile { display:flex; align-items:center; gap:.9rem; padding:1rem 1.15rem; }
    .h13-tile .h13-ic { width:44px; height:44px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.05rem; flex-shrink:0; }
    .h13-tile .h13-val { font-size:1.35rem; font-weight:800; line-height:1; color:var(--he-text-main,#0f172a); font-variant-numeric:tabular-nums; }
    .h13-tile .h13-lbl { font-size:.68rem; font-weight:700; letter-spacing:.5px; text-transform:uppercase; color:var(--he-text-muted,#64748b); }
    .h13-search { max-width:320px; }
    .h13-search .form-control { border-left:0; }
    .h13-search .input-group-text { background:#fff; }
    .hostel-row { cursor:pointer; }
    .hostel-row td { transition:background-color .18s ease; }
    .hostel-row:hover td { background-color:#f8fafc; }
</style>
@endpush

@section('content')
<div class="page-enter" x-data="hostelsManager()">
    {{-- ── Header ── --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark tracking-tight">Hostels</h1>
            <p class="text-muted mb-0 small">Every tenant branch across the platform — coverage, owners and admins.</p>
        </div>
        <button type="button" @click="createModalOpen = true" class="btn btn-primary shadow-sm rounded-pill px-4 fw-semibold tactile-btn">
            <i class="fa-solid fa-plus me-2"></i> Add Hostel
        </button>
    </div>

    {{-- ── Fleet health tiles ── --}}
    <div class="row g-3 mb-4 stagger">
        <div class="col-6 col-lg-3">
            <div class="panel-card shadow-sm h13-tile">
                <div class="h13-ic bg-primary-subtle text-primary"><i class="fa-solid fa-hotel"></i></div>
                <div><div class="h13-val">{{ $stats['total'] }}</div><div class="h13-lbl">Branches</div></div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="panel-card shadow-sm h13-tile">
                <div class="h13-ic bg-success-subtle text-success"><i class="fa-solid fa-circle-check"></i></div>
                <div><div class="h13-val">{{ $stats['active'] }}</div><div class="h13-lbl">Active</div></div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="panel-card shadow-sm h13-tile">
                <div class="h13-ic bg-warning-subtle text-warning"><i class="fa-solid fa-hourglass-half"></i></div>
                <div><div class="h13-val">{{ $stats['expiring'] }}</div><div class="h13-lbl">Expiring ≤ 30d</div></div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="panel-card shadow-sm h13-tile">
                <div class="h13-ic bg-danger-subtle text-danger"><i class="fa-solid fa-circle-xmark"></i></div>
                <div><div class="h13-val">{{ $stats['expired'] }}</div><div class="h13-lbl">Expired</div></div>
            </div>
        </div>
    </div>

    {{-- ── Directory ── --}}
    <div class="panel-card shadow-sm">
        <div class="p-3 px-4 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-building text-primary me-2"></i>All branches</h6>
            <form method="GET" class="d-flex flex-wrap align-items-center gap-2">
                <div class="input-group input-group-sm shadow-sm h13-search rounded-pill overflow-hidden border">
                    <span class="input-group-text border-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input type="text" name="q" value="{{ request('q') }}" class="form-control border-0" placeholder="Search name, owner, mobile, city…">
                </div>
                <x-he-select name="status" icon="signal" label="Status" :selected="request('status', '')"
                    :options="['' => 'All statuses', 'active' => 'Active', 'expired' => 'Expired', 'suspended' => 'Suspended']" />
                @if(request('q') || request('status'))
                    <a href="{{ route('superadmin.hostels.index') }}" class="btn btn-sm btn-light border rounded-pill px-3" title="Clear filters"><i class="fa-solid fa-xmark"></i></a>
                @endif
            </form>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light text-uppercase" style="font-size:.7rem; letter-spacing:.5px;">
                    <tr>
                        <th class="py-3 px-4 border-0">Hostel / Branch</th>
                        <th class="py-3 px-4 border-0">Owner</th>
                        <th class="py-3 px-4 border-0 text-center">Students</th>
                        <th class="py-3 px-4 border-0">Coverage</th>
                        <th class="py-3 px-4 border-0 text-center">Status</th>
                        <th class="py-3 px-4 border-0 text-end"></th>
                    </tr>
                </thead>
                <tbody class="border-top-0">
                @forelse($hostels as $h)
                    @php($days = $h->daysUntilExpiry())
                    @php($initials = collect(explode(' ', (string) $h->owner_name))->map(fn($w) => substr($w, 0, 1))->take(2)->join(''))
                    @php($avatarColor = ['primary', 'success', 'warning', 'info', 'danger'][strlen((string) $h->owner_name) % 5])
                    @php($statusColor = $h->status === 'active' ? 'success' : ($h->status === 'expired' ? 'danger' : 'secondary'))
                    <tr class="hostel-row" onclick="window.location='{{ route('superadmin.hostels.show', $h) }}'">
                        <td class="px-4 py-3 text-nowrap">
                            <div class="fw-bold text-dark fs-6 lh-1 mb-1">{{ $h->name }}</div>
                            <span class="text-muted small"><i class="fa-solid fa-location-dot me-1"></i>{{ $h->city ?: '—' }}{{ $h->state ? ', '.$h->state : '' }}</span>
                        </td>
                        <td class="px-4 py-3 text-nowrap">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-{{ $avatarColor }}-subtle text-{{ $avatarColor }} d-flex align-items-center justify-content-center fw-bold flex-shrink-0" style="width:38px; height:38px; font-size:.85rem;">
                                    {{ strtoupper($initials) }}
                                </div>
                                <div>
                                    <div class="fw-semibold text-dark lh-1 mb-1">{{ $h->owner_name }}</div>
                                    <div class="small text-muted lh-1" onclick="event.stopPropagation()"><x-mobile-link :mobile="$h->mobile" /></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="badge bg-light text-dark border px-2 py-1 rounded-pill">{{ $h->students_count }}</span>
                        </td>
                        <td class="px-4 py-3 text-nowrap">
                            <div class="fw-semibold text-dark" style="font-variant-numeric:tabular-nums;">{{ optional($h->subscription_end)->format('d M Y') ?? '—' }}</div>
                            @if(!is_null($days))
                                <div class="small mt-1">
                                    @if($days <= 0)
                                        <span class="text-danger fw-semibold"><i class="fa-solid fa-circle-xmark me-1"></i>Expired</span>
                                    @elseif($days <= 30)
                                        <span class="text-warning fw-semibold"><i class="fa-solid fa-clock me-1"></i>{{ $days }} days left</span>
                                    @else
                                        <span class="text-success"><i class="fa-solid fa-check-circle me-1"></i>{{ $days }} days left</span>
                                    @endif
                                </div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="badge bg-{{ $statusColor }}-subtle text-{{ $statusColor }} border border-{{ $statusColor }}-subtle rounded-pill px-3">{{ ucfirst($h->status) }}</span>
                        </td>
                        <td class="px-4 py-3 text-end text-nowrap" onclick="event.stopPropagation()">
                            @if(!empty($accountByHostel[$h->id]))
                            <a href="{{ route('superadmin.accounts.show', $accountByHostel[$h->id]) }}" class="btn btn-sm btn-light rounded-circle shadow-sm" style="width:32px;height:32px;" title="Owner Account">
                                <i class="fa-solid fa-user-gear text-primary"></i>
                            </a>
                            @endif
                            <button type="button" @click="openEditModal({{ $h->id }})" class="btn btn-sm btn-light rounded-circle shadow-sm mx-1" style="width:32px;height:32px;" title="Edit Hostel">
                                <i class="fa-regular fa-pen-to-square text-secondary"></i>
                            </button>
                            <form action="{{ route('superadmin.hostels.destroy', $h) }}" method="POST" class="d-inline" data-confirm="Delete {{ $h->name }}? This removes its admins and data.">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-light rounded-circle shadow-sm" style="width:32px;height:32px;" title="Delete">
                                    <i class="fa-regular fa-trash-can text-danger"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-0"><x-he-empty-state icon="hotel" title="No hostels found"
                        subtitle="{{ (request('q') || request('status')) ? 'Try clearing the search or status filter.' : 'Provision the first hostel to get started.' }}" /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($hostels->hasPages())
            <div class="p-3 border-top">{{ $hostels->links() }}</div>
        @endif
    </div>

    @include('superadmin.hostels.modals')
</div>
@endsection
