@extends('layouts.app')
@section('title', $hostel->name . ' - Profile')

@push('styles')
<style>
    .panel-card { background:#fff; border:1px solid rgba(0,0,0,0.05); border-radius:1.1rem; transition:all .3s cubic-bezier(.25,1,.5,1); }
    .panel-card:hover { box-shadow:0 12px 30px rgba(0,0,0,0.04); }

    /* ── Profile hero (light band — hierarchy under Account 360's dark mesh hero) ── */
    .hp-hero { position:relative; overflow:hidden; }
    .hp-hero::before { content:''; position:absolute; top:0; left:0; right:0; height:4px; background:var(--he-gradient-pop, linear-gradient(135deg,#4f46e5,#9333ea)); }
    .hp-hero-ic { width:56px; height:56px; border-radius:16px; background:var(--he-gradient-pop, linear-gradient(135deg,#4f46e5,#9333ea)); color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.4rem; box-shadow:0 8px 20px rgba(79,70,229,.25); flex-shrink:0; }
    .hp-owner-chip { display:inline-flex; align-items:center; gap:.4rem; font-size:.78rem; font-weight:600; color:var(--he-primary,#4f46e5); background:var(--he-primary-soft, rgba(79,70,229,.08)); border-radius:9999px; padding:.25rem .7rem; text-decoration:none; transition:all .2s var(--ease-out-expo, cubic-bezier(.16,1,.3,1)); }
    .hp-owner-chip:hover { background:rgba(79,70,229,.16); color:var(--he-primary-hover,#4338ca); }
    .hp-metrics { display:grid; grid-template-columns:repeat(auto-fit, minmax(130px, 1fr)); border-top:1px solid rgba(15,23,42,.06); background:var(--he-bg-surface-raised,#f8fafc); }
    .hp-metric { padding:.85rem 1.25rem; display:flex; align-items:center; gap:.7rem; }
    .hp-metric + .hp-metric { border-left:1px solid rgba(15,23,42,.05); }
    .hp-metric .hp-m-ic { width:34px; height:34px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:.85rem; flex-shrink:0; }
    .hp-metric .hp-m-val { font-weight:800; line-height:1.1; color:var(--he-text-main,#0f172a); font-variant-numeric:tabular-nums; }
    .hp-metric .hp-m-lbl { font-size:.64rem; font-weight:700; letter-spacing:.5px; text-transform:uppercase; color:var(--he-text-muted,#64748b); }

    .hp-detail { display:flex; justify-content:space-between; align-items:center; gap:1rem; padding:.7rem 0; }
    .hp-detail + .hp-detail { border-top:1px dashed rgba(15,23,42,.07); }
    .hp-detail .hp-d-k { font-size:.68rem; font-weight:700; letter-spacing:.5px; text-transform:uppercase; color:var(--he-text-muted,#64748b); }
    .hp-detail .hp-d-v { font-weight:600; color:var(--he-text-main,#0f172a); text-align:right; word-break:break-word; }

    /* Premium branch-access tiles (Add Admin) — Alpine-driven, same pattern as
       the Comp modal's branch tiles on Account 360. */
    .branch-tile { display:flex; align-items:center; gap:.7rem; text-align:left; background:#fff; border:1.5px solid rgba(15,23,42,.1); border-radius:var(--he-radius-md, 10px); padding:.65rem .8rem; transition:all .2s var(--ease-out-expo, cubic-bezier(.16,1,.3,1)); }
    .branch-tile:hover:not(.is-locked) { border-color:rgba(79,70,229,.4); }
    .branch-tile.is-selected { border-color:var(--he-primary, #4f46e5); background:var(--he-primary-soft, rgba(79,70,229,.07)); box-shadow:0 4px 12px rgba(79,70,229,.1); }
    .branch-tile.is-locked { cursor:not-allowed; background:var(--he-bg-surface-raised, #f8fafc); }
    .branch-tile-check { width:22px; height:22px; flex-shrink:0; border-radius:50%; border:1.5px solid rgba(15,23,42,.2); display:flex; align-items:center; justify-content:center; color:#fff; font-size:.65rem; transition:all .2s; }
    .branch-tile.is-selected .branch-tile-check, .branch-tile.is-locked .branch-tile-check { background:var(--he-primary, #4f46e5); border-color:var(--he-primary, #4f46e5); }
    .branch-tile-check i { opacity:0; transition:opacity .2s; }
    .branch-tile.is-selected .branch-tile-check i, .branch-tile.is-locked .branch-tile-check i { opacity:1; }
    .branch-tile-name { display:block; font-weight:700; color:var(--he-text-main,#0f172a); font-size:.88rem; line-height:1.2; }
    .branch-tile-tag { display:block; font-size:.68rem; color:var(--he-text-muted,#64748b); }
</style>
@endpush

@section('content')
<div class="page-enter" x-data="hostelProfile()">
    {{-- ── Back + hero ── --}}
    <a href="{{ route('superadmin.hostels.index') }}" class="btn btn-sm btn-light rounded-pill px-3 mb-3 shadow-sm"><i class="fa-solid fa-arrow-left me-1"></i> Hostels</a>

    @include('superadmin.partials.credentials')

    <div class="panel-card hp-hero shadow-sm mb-4">
        <div class="p-4 d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="hp-hero-ic"><i class="fa-solid fa-hotel"></i></div>
                <div>
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                        <h1 class="h4 fw-bold mb-0 text-dark tracking-tight">{{ $hostel->name }}</h1>
                        @php($statusColor = $hostel->status === 'active' ? 'success' : ($hostel->status === 'expired' ? 'danger' : 'secondary'))
                        <span class="badge bg-{{ $statusColor }}-subtle text-{{ $statusColor }} border border-{{ $statusColor }}-subtle rounded-pill px-3">
                            @if($hostel->status === 'active')<i class="fa-solid fa-circle me-1" style="font-size:.45rem; vertical-align:middle;"></i>@endif{{ ucfirst($hostel->status) }}
                        </span>
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-2 text-muted small">
                        <span><i class="fa-solid fa-location-dot me-1"></i>{{ $hostel->city ?: '—' }}{{ $hostel->state ? ', '.$hostel->state : '' }}</span>
                        @if($account)
                            <a href="{{ route('superadmin.accounts.show', $account) }}" class="hp-owner-chip" title="Open the owner's Account 360">
                                <i class="fa-solid fa-user-gear"></i> {{ $hostel->owner_name }}
                            </a>
                        @else
                            <span><i class="fa-solid fa-user me-1"></i>{{ $hostel->owner_name }}</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="d-flex gap-2">
                @if($account)
                <a href="{{ route('superadmin.accounts.show', $account) }}" class="btn btn-light border shadow-sm rounded-pill px-4 fw-semibold tactile-btn">
                    <i class="fa-solid fa-user-gear text-primary me-2"></i> Owner Account
                </a>
                @endif
                <button type="button" class="btn btn-primary shadow-sm rounded-pill px-4 fw-semibold tactile-btn" @click="editOpen = true">
                    <i class="fa-solid fa-pen me-2"></i> Edit Profile
                </button>
            </div>
        </div>
        @php($days = $hostel->daysUntilExpiry())
        <div class="hp-metrics">
            <div class="hp-metric">
                <div class="hp-m-ic bg-primary-subtle text-primary"><i class="fa-solid fa-users"></i></div>
                <div><div class="hp-m-val fs-5">{{ $hostel->students_count }}</div><div class="hp-m-lbl">Students</div></div>
            </div>
            <div class="hp-metric">
                <div class="hp-m-ic bg-info-subtle text-info"><i class="fa-solid fa-door-open"></i></div>
                <div><div class="hp-m-val fs-5">{{ $hostel->rooms_count }}</div><div class="hp-m-lbl">Rooms</div></div>
            </div>
            <div class="hp-metric">
                <div class="hp-m-ic bg-warning-subtle text-warning"><i class="fa-solid fa-bed"></i></div>
                <div><div class="hp-m-val fs-5">{{ $hostel->beds_count }}</div><div class="hp-m-lbl">Beds</div></div>
            </div>
            <div class="hp-metric">
                <div class="hp-m-ic bg-{{ $days !== null && $days <= 30 ? 'danger' : 'success' }}-subtle text-{{ $days !== null && $days <= 30 ? 'danger' : 'success' }}"><i class="fa-solid fa-calendar-check"></i></div>
                <div>
                    <div class="hp-m-val">{{ optional($hostel->subscription_end)->format('d M Y') ?? '—' }}</div>
                    <div class="hp-m-lbl">{{ $days !== null ? ($days > 0 ? $days.' days left' : 'Expired') : 'Coverage' }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        {{-- ── Left: details + admins ── --}}
        <div class="col-lg-4">
            <div class="panel-card shadow-sm mb-4">
                <div class="p-3 px-4 border-bottom"><h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-building text-primary me-2"></i>Tenant details</h6></div>
                <div class="px-4 py-2">
                    <div class="hp-detail"><span class="hp-d-k">Owner</span><span class="hp-d-v">{{ $hostel->owner_name }}</span></div>
                    <div class="hp-detail"><span class="hp-d-k">Mobile</span><span class="hp-d-v"><x-mobile-link :mobile="$hostel->mobile" /></span></div>
                    <div class="hp-detail"><span class="hp-d-k">Email</span><span class="hp-d-v">{{ $hostel->email ?? '—' }}</span></div>
                    <div class="hp-detail"><span class="hp-d-k">GST</span><span class="hp-d-v">{{ $hostel->gst_number ?? '—' }}</span></div>
                    <div class="hp-detail"><span class="hp-d-k">Address</span><span class="hp-d-v small">{{ $hostel->address ?? '—' }}</span></div>
                </div>
            </div>

            <div class="panel-card shadow-sm">
                <div class="p-3 px-4 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-user-shield text-primary me-2"></i>Admins</h6>
                    <button class="btn btn-sm btn-light text-primary rounded-pill px-3 fw-semibold shadow-sm tactile-btn" @click="adminOpen = true">
                        <i class="fa-solid fa-plus me-1"></i> Add
                    </button>
                </div>
                @forelse($hostel->admins as $a)
                    @php($initials = collect(explode(' ', $a->name))->map(fn($w) => substr($w, 0, 1))->take(2)->join(''))
                    @php($avatarColor = ['primary', 'success', 'warning', 'info', 'danger'][$a->id % 5])
                    <div class="d-flex justify-content-between align-items-center px-4 py-3 border-bottom">
                        <div class="d-flex align-items-center gap-3 min-w-0">
                            <div class="rounded-circle bg-{{ $avatarColor }}-subtle text-{{ $avatarColor }} d-flex align-items-center justify-content-center fw-bold flex-shrink-0" style="width:38px; height:38px; font-size:.8rem;">{{ strtoupper($initials) }}</div>
                            <div class="min-w-0">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="fw-semibold text-dark text-truncate">{{ $a->name }}</span>
                                    @if(!$a->is_active)<span class="badge bg-secondary-subtle text-secondary rounded-pill" style="font-size:.6rem;">Disabled</span>@endif
                                </div>
                                <div class="small text-muted"><x-mobile-link :mobile="$a->mobile" /></div>
                                @if($a->hostels->where('id', '!=', $hostel->id)->isNotEmpty())
                                    <div class="d-flex flex-wrap gap-1 mt-1">
                                        @foreach($a->hostels->where('id', '!=', $hostel->id) as $assignedBranch)
                                            <span class="badge bg-light border text-secondary rounded-pill" style="font-size:.62rem;">{{ $assignedBranch->name }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                        <div class="text-nowrap">
                            <form action="{{ route('superadmin.admins.toggle', $a) }}" method="POST" class="d-inline">
                                @csrf @method('PATCH')
                                <button class="btn btn-sm btn-light rounded-circle shadow-sm" style="width:30px;height:30px;" title="{{ $a->is_active ? 'Disable admin' : 'Enable admin' }}">
                                    <i class="fa-solid {{ $a->is_active ? 'fa-ban text-warning' : 'fa-check text-success' }}" style="font-size:.75rem;"></i>
                                </button>
                            </form>
                            <form action="{{ route('superadmin.admins.reset', $a) }}" method="POST" class="d-inline ms-1" data-confirm="Generate a new random password for {{ $a->name }}?">
                                @csrf @method('PATCH')
                                <button class="btn btn-sm btn-light rounded-circle shadow-sm" style="width:30px;height:30px;" title="Reset password">
                                    <i class="fa-solid fa-key text-muted" style="font-size:.75rem;"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="p-4"><x-he-empty-state icon="user-shield" title="No admins assigned" subtitle="Add an admin login so this branch can be managed." /></div>
                @endforelse
            </div>
        </div>

        {{-- ── Right: billing & subscriptions ── --}}
        <div class="col-lg-8">
            <div class="panel-card shadow-sm h-100">
                <div class="p-3 px-4 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-receipt text-primary me-2"></i>Billing &amp; subscriptions</h6>
                    <a href="{{ $account ? route('superadmin.accounts.show', $account) : route('superadmin.subscriptions.index') }}" class="btn btn-sm btn-primary rounded-pill px-3 fw-semibold shadow-sm tactile-btn">
                        <i class="fa-solid fa-rotate me-1"></i> Add / Renew
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light text-uppercase" style="font-size:.7rem; letter-spacing:.5px;">
                            <tr>
                                <th class="py-3 px-4 border-0">Period</th>
                                <th class="py-3 px-4 border-0">Plan</th>
                                <th class="py-3 px-4 border-0 text-end">Amount</th>
                                <th class="py-3 px-4 border-0 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="border-top-0">
                        @forelse($hostel->subscriptions as $s)
                            <tr>
                                <td class="px-4 py-3">
                                    <div class="fw-semibold text-dark" style="font-variant-numeric:tabular-nums;">{{ $s->start_date->format('d M Y') }} <i class="fa-solid fa-arrow-right-long mx-1 text-muted" style="font-size:.7rem;"></i> {{ $s->end_date->format('d M Y') }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="badge bg-primary-subtle text-primary rounded-pill px-2 py-1" style="font-size:.68rem; font-weight:700;">{{ $s->plan ? ucfirst($s->plan) : 'Custom' }}</span>
                                </td>
                                <td class="px-4 py-3 text-end">
                                    <span class="fw-bold text-dark" style="font-variant-numeric:tabular-nums;">{{ hostelease_money($s->amount) }}</span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @php($payColor = $s->payment_status === 'paid' ? 'success' : ($s->payment_status === 'pending' ? 'warning' : 'danger'))
                                    <span class="badge bg-{{ $payColor }}-subtle text-{{ $payColor }} border border-{{ $payColor }}-subtle rounded-pill px-3">{{ ucfirst($s->payment_status) }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="p-0"><x-he-empty-state icon="receipt" title="No billing history" subtitle="Renewals recorded for this branch will appear here." /></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- ══ Edit Hostel (server-prefilled — this page's own modal) ══ --}}
    <x-he-modal open="editOpen" title="Edit {{ $hostel->name }}" icon="pen-to-square"
        :action="route('superadmin.hostels.update', $hostel)" method="PUT" :size="800">
        <input type="hidden" name="is_edit" value="1">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-bold small text-muted">HOSTEL NAME <span class="text-danger">*</span></label>
                <input type="text" name="name" value="{{ old('name', $hostel->name) }}" class="form-control bg-white border shadow-sm" required>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold small text-muted">OWNER NAME <span class="text-danger">*</span></label>
                <input type="text" name="owner_name" value="{{ old('owner_name', $hostel->owner_name) }}" class="form-control bg-white border shadow-sm" required>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold small text-muted">MOBILE <span class="text-danger">*</span></label>
                <div class="input-group shadow-sm">
                    <span class="input-group-text bg-white border-end-0 fw-bold text-muted">+91</span>
                    <input type="tel" name="mobile" value="{{ substr(preg_replace('/\D+/', '', old('mobile', $hostel->mobile ?? '')), -10) }}" class="form-control bg-white border-start-0" maxlength="10" inputmode="numeric" required>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold small text-muted">EMAIL</label>
                <input type="email" name="email" value="{{ old('email', $hostel->email) }}" class="form-control bg-white border shadow-sm">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold small text-muted">GST NUMBER</label>
                <input type="text" name="gst_number" value="{{ old('gst_number', $hostel->gst_number) }}" class="form-control bg-white border shadow-sm">
            </div>

            <div class="col-12" x-data="{ showAdvanced: false }">
                <button type="button" @click="showAdvanced = !showAdvanced" class="btn btn-link text-muted fw-bold text-decoration-none p-0 small d-inline-flex align-items-center">
                    <i class="fa-solid fa-location-dot me-2"></i> Address Details
                    <i class="fa-solid fa-chevron-down ms-2" :class="{ 'fa-rotate-180': showAdvanced }" style="font-size:.7rem;"></i>
                </button>
                <div class="mt-3 row g-3" x-show="showAdvanced" x-collapse x-cloak>
                    <div class="col-12">
                        <label class="form-label fw-bold small text-muted">ADDRESS</label>
                        <textarea name="address" class="form-control bg-white border shadow-sm" rows="2">{{ old('address', $hostel->address) }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted">CITY</label>
                        <input type="text" name="city" value="{{ old('city', $hostel->city) }}" class="form-control bg-white border shadow-sm">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted">STATE</label>
                        <input type="text" name="state" value="{{ old('state', $hostel->state) }}" class="form-control bg-white border shadow-sm">
                    </div>
                </div>
            </div>

            <div class="col-12"><hr class="my-1 text-muted"></div>
            <div class="col-12"><h6 class="fw-bold text-dark mb-0"><i class="fa-solid fa-calendar-check text-primary me-2"></i>Status &amp; Subscription Validity</h6></div>

            <div class="col-md-4">
                <label class="form-label fw-bold small text-muted">HOSTEL STATUS</label>
                <x-he-select name="status" :submit="false" compact :selected="old('status', $hostel->status)" :options="[
                    'active' => 'Active',
                    'expired' => 'Expired',
                    'suspended' => 'Suspended',
                ]" />
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold small text-muted">VALID FROM</label>
                <input type="date" name="subscription_start" value="{{ old('subscription_start', optional($hostel->subscription_start)->format('Y-m-d')) }}" class="form-control bg-white border shadow-sm" required>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold small text-muted">VALID UNTIL</label>
                <input type="date" name="subscription_end" value="{{ old('subscription_end', optional($hostel->subscription_end)->format('Y-m-d')) }}" class="form-control bg-white border shadow-sm" required>
            </div>
        </div>

        <x-slot:footer>
            <button type="button" class="btn btn-light border rounded-pill px-4 fw-bold" @click="editOpen=false">Cancel</button>
            <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm"><i class="fa-solid fa-check me-2"></i>Save Changes</button>
        </x-slot:footer>
    </x-he-modal>

    {{-- ══ Add Admin ══ --}}
    <x-he-modal open="adminOpen" title="Add Admin for {{ $hostel->name }}" icon="user-shield"
        :action="route('superadmin.admins.store')" :size="620">
        <input type="hidden" name="hostel_id" value="{{ $hostel->id }}">
        <p class="text-muted small mb-3">Creates a new administrator login for this branch. A password is auto-generated and shown once.</p>

        <div class="row g-3">
            <div class="col-12">
                <label class="form-label fw-bold small text-muted">FULL NAME <span class="text-danger">*</span></label>
                <input type="text" name="name" value="{{ old('name') }}" class="form-control bg-white border shadow-sm" required placeholder="e.g. Ramesh Patel">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold small text-muted">MOBILE <span class="text-danger">*</span></label>
                <div class="input-group shadow-sm">
                    <span class="input-group-text bg-white border-end-0 fw-bold text-muted">+91</span>
                    <input type="tel" name="mobile" value="{{ old('mobile') }}" maxlength="10" inputmode="numeric" class="form-control bg-white border-start-0" required placeholder="9876543210">
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold small text-muted">EMAIL <span class="fw-normal">— optional</span></label>
                <input type="email" name="email" value="{{ old('email') }}" class="form-control bg-white border shadow-sm" placeholder="admin@example.com">
            </div>
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <label class="form-label fw-bold small text-muted mb-0">ALSO GRANT ACCESS TO <span class="fw-normal">— optional</span></label>
                    <button type="button" class="btn btn-link btn-sm text-decoration-none fw-semibold p-0" @click="toggleAllAdminBranches()" x-show="adminOtherBranches.length" x-text="adminAllOthersSelected ? 'Clear all' : 'Select all'"></button>
                </div>
                <div class="row g-2">
                    <template x-for="b in adminBranches" :key="b.id">
                        <div class="col-sm-6">
                            <button type="button" class="branch-tile w-100"
                                :class="{ 'is-selected': adminSelected.includes(b.id), 'is-locked': b.locked }"
                                @click="!b.locked && toggleAdminBranch(b.id)">
                                <span class="branch-tile-check"><i class="fa-solid" :class="b.locked ? 'fa-lock' : 'fa-check'"></i></span>
                                <span class="text-start">
                                    <span class="branch-tile-name" x-text="b.name"></span>
                                    <span class="branch-tile-tag" x-show="b.locked" x-cloak>this branch — always granted</span>
                                </span>
                            </button>
                        </div>
                    </template>
                    <div class="col-12 text-muted small" x-show="!adminBranches.length" x-cloak>No active branches found.</div>
                </div>
                <template x-for="id in adminSelected" :key="id"><input type="hidden" name="branches[]" :value="id"></template>
            </div>
        </div>

        <x-slot:footer>
            <button type="button" class="btn btn-light border rounded-pill px-4 fw-bold" @click="adminOpen=false">Cancel</button>
            <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm"><i class="fa-solid fa-check me-2"></i>Create Admin</button>
        </x-slot:footer>
    </x-he-modal>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('hostelProfile', () => ({
            // ?edit=1 (the resource edit route redirects here) or a failed edit
            // validation auto-opens the Edit modal; a failed Add-Admin validation
            // (posts hostel_id, never is_edit) reopens that one instead.
            editOpen: {{ (request()->boolean('edit') || ($errors->any() && old('is_edit'))) ? 'true' : 'false' }},
            adminOpen: {{ ($errors->any() && old('hostel_id') && ! old('is_edit')) ? 'true' : 'false' }},

            // Add Admin — branch access tiles. This branch is always granted
            // (locked, non-toggleable); others can be added freely.
            adminBranches: @json($branches->map(fn ($b) => ['id' => $b->id, 'name' => $b->name, 'locked' => $b->id === $hostel->id])->values()),
            adminSelected: {!! json_encode(old('branches', [$hostel->id])) !!},
            toggleAdminBranch(id) {
                const i = this.adminSelected.indexOf(id);
                if (i === -1) this.adminSelected.push(id); else this.adminSelected.splice(i, 1);
            },
            get adminOtherBranches() { return this.adminBranches.filter(b => !b.locked); },
            get adminAllOthersSelected() {
                return this.adminOtherBranches.length > 0 && this.adminOtherBranches.every(b => this.adminSelected.includes(b.id));
            },
            toggleAllAdminBranches() {
                const lockedIds = this.adminBranches.filter(b => b.locked).map(b => b.id);
                this.adminSelected = this.adminAllOthersSelected ? lockedIds : [...lockedIds, ...this.adminOtherBranches.map(b => b.id)];
            },
        }));
    });
</script>
@endpush
