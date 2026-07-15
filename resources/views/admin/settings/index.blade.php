@extends('layouts.app')
@section('title', __('Settings'))

@push('styles')
<style>
    /* .panel-card / .panel-head / .panel-body are canonical in _premium.scss — do not redeclare. */

    /* ── Segmented tab bar (scrolls horizontally on phones) ── */
    .st-tabs { display:flex; gap:.35rem; background:var(--he-bg-surface-raised,#f1f5f9); border:1px solid rgba(15,23,42,.06); border-radius:9999px; padding:.3rem; width:max-content; max-width:100%; overflow-x:auto; -webkit-overflow-scrolling:touch; scrollbar-width:none; }
    .st-tabs::-webkit-scrollbar { display:none; }
    .st-tab { border:0; background:transparent; white-space:nowrap; padding:.5rem 1.1rem; border-radius:9999px; font-weight:700; font-size:.86rem; color:var(--he-text-muted,#64748b); display:flex; align-items:center; gap:.45rem; transition:all .25s var(--ease-out-expo, cubic-bezier(.16,1,.3,1)); }
    .st-tab.active { background:#fff; color:var(--he-primary,#4f46e5); box-shadow:0 2px 8px rgba(15,23,42,.08); }

    /* ── Profile ── */
    .st-avatar { width:64px; height:64px; border-radius:18px; background:var(--he-gradient-pop, linear-gradient(135deg,#4f46e5,#9333ea)); color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.5rem; font-weight:800; box-shadow:0 8px 20px rgba(79,70,229,.25); flex-shrink:0; }
    .st-kv { display:flex; justify-content:space-between; align-items:center; gap:1rem; padding:.7rem 0; }
    .st-kv + .st-kv { border-top:1px dashed rgba(15,23,42,.07); }
    .st-kv .k { font-size:.68rem; font-weight:700; letter-spacing:.5px; text-transform:uppercase; color:var(--he-text-muted,#64748b); }
    .st-kv .v { font-weight:600; color:var(--he-text-main,#0f172a); text-align:right; word-break:break-word; }

    /* ── Role legend ── */
    .role-chip { display:flex; align-items:flex-start; gap:.7rem; padding:.8rem .9rem; background:#fff; border:1px solid rgba(15,23,42,.07); border-radius:var(--he-radius-md,10px); height:100%; }
    .role-chip .rc-ic { width:34px; height:34px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:.85rem; flex-shrink:0; }
    .role-chip .rc-name { font-weight:800; font-size:.85rem; color:var(--he-text-main,#0f172a); line-height:1.2; }
    .role-chip .rc-desc { font-size:.72rem; color:var(--he-text-muted,#64748b); line-height:1.35; }

    /* ── Mobile user cards ── */
    .user-card { padding:1rem 1.1rem; }
    .user-card + .user-card { border-top:1px solid rgba(15,23,42,.06); }

    /* ── Branch cards ── */
    .st-branch { position:relative; overflow:hidden; padding:1.25rem; height:100%; display:flex; flex-direction:column; }
    .st-branch.is-current { background:var(--he-gradient-mesh, linear-gradient(135deg,#0f172a 0%,#1e1b4b 100%)); color:#fff; border:0; }
    .st-branch.is-current::after { content:''; position:absolute; top:-50px; right:-50px; width:150px; height:150px; background:radial-gradient(circle, rgba(147,51,234,.4) 0%, transparent 70%); border-radius:50%; filter:blur(20px); pointer-events:none; }
    .st-branch.is-current .text-dark { color:#fff !important; }
    .st-branch.is-current .text-muted { color:rgba(255,255,255,.72) !important; }
    .st-branch.is-current .border-top { border-color:rgba(255,255,255,.12) !important; }
    .st-branch-ic { width:46px; height:46px; border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:1.1rem; flex-shrink:0; }

    /* ── Lock banner ── */
    .st-lock { display:flex; align-items:flex-start; gap:.8rem; background:var(--he-warning-soft,#fef3c7); border:1px solid rgba(245,158,11,.25); border-radius:var(--he-radius-lg,16px); padding:.9rem 1.1rem; }
    .st-lock i { color:var(--he-warning,#f59e0b); margin-top:.15rem; }

    /* Branch access tiles (user modal) — same pattern as the Super Admin modals */
    .branch-tile { display:flex; align-items:center; gap:.7rem; text-align:left; background:#fff; border:1.5px solid rgba(15,23,42,.1); border-radius:var(--he-radius-md,10px); padding:.6rem .75rem; transition:all .2s var(--ease-out-expo, cubic-bezier(.16,1,.3,1)); width:100%; }
    .branch-tile:hover { border-color:rgba(79,70,229,.4); }
    .branch-tile.is-selected { border-color:var(--he-primary,#4f46e5); background:var(--he-primary-soft, rgba(79,70,229,.07)); box-shadow:0 4px 12px rgba(79,70,229,.1); }
    .branch-tile-check { width:20px; height:20px; flex-shrink:0; border-radius:50%; border:1.5px solid rgba(15,23,42,.2); display:flex; align-items:center; justify-content:center; color:#fff; font-size:.6rem; transition:all .2s; }
    .branch-tile.is-selected .branch-tile-check { background:var(--he-primary,#4f46e5); border-color:var(--he-primary,#4f46e5); }
    .branch-tile-check i { opacity:0; transition:opacity .2s; }
    .branch-tile.is-selected .branch-tile-check i { opacity:1; }

    /* Plan cards (renew modal) */
    .plan-card { border:1.5px solid rgba(15,23,42,.08); border-radius:var(--he-radius-lg,16px); padding:1.25rem; cursor:pointer; transition:all .25s ease; height:100%; background:#fff; }
    .plan-card:hover { border-color:rgba(79,70,229,.3); }
    .plan-card.selected { border-color:var(--he-primary,#4f46e5); background:var(--he-primary-soft, rgba(79,70,229,.04)); box-shadow:0 8px 24px rgba(79,70,229,.08); }
</style>
@endpush

@section('content')
<div x-data="settingsManager()" class="page-enter pb-5">

    {{-- ── Header + tabs ── --}}
    <div class="mb-4">
        <h1 class="h3 fw-bold text-dark mb-1 tracking-tight">{{ __('Settings') }}</h1>
        <p class="text-muted mb-3 small">{{ __('Your profile, staff access, and branches.') }}</p>

        <div class="st-tabs shadow-sm">
            <button type="button" class="st-tab" :class="{ active: activeTab === 'profile' }" @click="activeTab = 'profile'">
                <i class="fa-solid fa-circle-user"></i> {{ __('Profile') }}
            </button>
            <button type="button" class="st-tab" :class="{ active: activeTab === 'users' }" @click="activeTab = 'users'">
                <i class="fa-solid fa-users-gear"></i> {{ __('Users & Roles') }}
            </button>
            <button type="button" class="st-tab" :class="{ active: activeTab === 'branches' }" @click="activeTab = 'branches'">
                <i class="fa-solid fa-building-circle-check"></i> {{ __('My Branches') }}
            </button>
        </div>
    </div>

    @if(session('credentials'))
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center border-0 shadow-sm rounded-4 mb-4" role="alert">
            <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width:42px; height:42px;">
                <i class="fa-solid fa-key"></i>
            </div>
            <div>
                <h6 class="fw-bold mb-1">{{ __('Login created successfully!') }}</h6>
                <p class="mb-0 small">
                    {{ __('Please share these credentials once:') }}<br>
                    <span class="text-dark fw-medium">{{ __('Mobile:') }}</span> <code class="fs-6 bg-transparent text-dark fw-bold p-0">{{ session('credentials')['mobile'] }}</code> &mdash;
                    <span class="text-dark fw-medium">{{ __('Password:') }}</span> <code class="fs-6 bg-transparent text-dark fw-bold p-0">{{ session('credentials')['password'] }}</code>
                </p>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- ══ TAB: PROFILE ══ --}}
    <div x-show="activeTab === 'profile'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-cloak style="display:none;">
        <div class="row g-4">
            <div class="col-lg-5">
                <div class="panel-card shadow-sm p-4 h-100">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="st-avatar">{{ strtoupper(substr($owner->name, 0, 1)) }}</div>
                        <div class="min-w-0">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <h5 class="fw-bold mb-0 text-dark text-truncate">{{ $owner->name }}</h5>
                                @if($viewerIsOwner)
                                    <span class="badge rounded-pill text-white" style="font-size:.62rem; background:var(--he-gradient-pop, linear-gradient(135deg,#4f46e5,#9333ea));"><i class="fa-solid fa-crown me-1" style="font-size:.5rem;"></i>{{ __('Owner') }}</span>
                                @else
                                    <span class="badge bg-primary-subtle text-primary rounded-pill" style="font-size:.62rem;"><i class="fa-solid fa-user-shield me-1" style="font-size:.5rem;"></i>{{ __('Admin') }}</span>
                                @endif
                            </div>
                            <div class="text-muted small">{{ config('hostelease.roles.'.$owner->role, ucfirst($owner->role)) }}</div>
                        </div>
                    </div>
                    <div class="px-1">
                        <div class="st-kv"><span class="k">{{ __('Login (mobile)') }}</span><span class="v">{{ hostelease_phone($owner->mobile) }}</span></div>
                        <div class="st-kv"><span class="k">{{ __('Branches') }}</span><span class="v">{{ $myBranches->count() }}</span></div>
                        <div class="st-kv"><span class="k">{{ __('Renewal date') }}</span><span class="v">{{ $account->current_period_end?->format('d M Y') ?? '—' }}</span></div>
                        <div class="st-kv"><span class="k">{{ __('Member since') }}</span><span class="v">{{ $owner->created_at?->format('M Y') ?? '—' }}</span></div>
                        <div class="st-kv"><span class="k">{{ __('Last login') }}</span><span class="v">{{ $owner->last_login_at?->format('d M Y · h:i A') ?? '—' }}</span></div>
                    </div>
                    <div class="mt-3 pt-3 border-top d-flex flex-wrap gap-2">
                        <a href="{{ route('profile.password') }}" class="btn btn-light border rounded-pill px-4 fw-semibold tactile-btn"><i class="fa-solid fa-key text-primary me-2"></i>{{ __('Change password') }}</a>
                        <a href="{{ route('admin.subscription.index') }}" class="btn btn-light border rounded-pill px-4 fw-semibold tactile-btn"><i class="fa-solid fa-receipt text-primary me-2"></i>{{ __('Subscription') }}</a>
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="panel-card shadow-sm h-100">
                    <div class="p-3 px-4 border-bottom"><h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-id-card text-primary me-2"></i>{{ __('Profile info') }}</h6></div>
                    <form method="POST" action="{{ route('profile.update') }}" class="p-4">
                        @csrf @method('PUT')
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">{{ __('FULL NAME') }} <span class="text-danger">*</span></label>
                                <input type="text" name="name" value="{{ old('name', $owner->name) }}" class="form-control bg-white border shadow-sm" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">{{ __('EMAIL') }}</label>
                                <input type="email" name="email" value="{{ old('email', $owner->email) }}" class="form-control bg-white border shadow-sm" placeholder="you@example.com">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold small text-muted">{{ __('LOGIN MOBILE') }}</label>
                                <div class="input-group shadow-sm">
                                    <span class="input-group-text bg-light fw-bold text-muted"><i class="fa-solid fa-lock" style="font-size:.7rem;"></i></span>
                                    <input type="text" value="{{ hostelease_phone($owner->mobile) }}" class="form-control bg-light border" disabled>
                                </div>
                                <div class="form-text">{{ __('Your mobile is your login and links your branches — contact HostelEase support to change it.') }}</div>
                            </div>
                        </div>
                        <div class="mt-3 text-end">
                            <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm tactile-btn"><i class="fa-solid fa-check me-2"></i>{{ __('Save') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- ══ TAB: USERS & ROLES ══ --}}
    <div x-show="activeTab === 'users'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-cloak style="display:none;">

        {{-- Role legend --}}
        <div class="row g-2 mb-4">
            <div class="col-6 col-lg-3"><div class="role-chip shadow-sm">
                <div class="rc-ic bg-primary-subtle text-primary"><i class="fa-solid fa-crown"></i></div>
                <div><div class="rc-name">{{ __('Manager') }}</div><div class="rc-desc">{{ __('Everything except user management.') }}</div></div>
            </div></div>
            <div class="col-6 col-lg-3"><div class="role-chip shadow-sm">
                <div class="rc-ic bg-info-subtle text-info"><i class="fa-solid fa-calculator"></i></div>
                <div><div class="rc-name">{{ __('Accountant') }}</div><div class="rc-desc">{{ __('Fees, expenses and reports.') }}</div></div>
            </div></div>
            <div class="col-6 col-lg-3"><div class="role-chip shadow-sm">
                <div class="rc-ic bg-warning-subtle text-warning"><i class="fa-solid fa-user-shield"></i></div>
                <div><div class="rc-name">{{ __('Warden') }}</div><div class="rc-desc">{{ __('Students, beds, visitors, complaints.') }}</div></div>
            </div></div>
            <div class="col-6 col-lg-3"><div class="role-chip shadow-sm">
                <div class="rc-ic bg-secondary-subtle text-secondary"><i class="fa-regular fa-eye"></i></div>
                <div><div class="rc-name">{{ __('Viewer') }}</div><div class="rc-desc">{{ __('Read-only across branches.') }}</div></div>
            </div></div>
        </div>

        <div class="panel-card shadow-sm">
            <div class="p-3 px-4 border-bottom d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-users-gear text-primary me-2"></i>{{ __('Team & access') }}</h6>
                    <div class="text-muted" style="font-size:.72rem;">{{ __('Everyone with access to your branches. Admins are set up with HostelEase; you add and manage staff.') }}</div>
                </div>
                <button type="button" @click="openUserModal()" class="btn btn-sm btn-primary rounded-pill px-3 fw-semibold shadow-sm tactile-btn">
                    <i class="fa-solid fa-user-plus me-1"></i> {{ __('Add Staff') }}
                </button>
            </div>

            {{-- Desktop table --}}
            <div class="table-responsive d-none d-lg-block">
                <table class="table align-middle mb-0">
                    <thead class="table-light text-uppercase" style="font-size:.7rem; letter-spacing:.5px;">
                        <tr>
                            <th class="py-3 px-4 border-0">{{ __('Name') }}</th>
                            <th class="py-3 px-4 border-0">{{ __('Role') }}</th>
                            <th class="py-3 px-4 border-0">{{ __('Branch access') }}</th>
                            <th class="py-3 px-4 border-0 text-center">{{ __('Status') }}</th>
                            <th class="py-3 px-4 border-0 text-end"></th>
                        </tr>
                    </thead>
                    <tbody class="border-top-0">
                    @forelse($users as $u)
                        @php($isCoAdmin = $u->isHostelAdmin())
                        @php($ud = ['id' => $u->id, 'name' => $u->name, 'mobile' => $u->mobile, 'role' => $u->role, 'branches' => $u->hostels->pluck('id')->all(), 'is_active' => (bool) $u->is_active])
                        <tr>
                            <td class="px-4 py-3">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="{{ $isCoAdmin ? 'bg-primary-subtle text-primary' : 'bg-light text-secondary' }} rounded-circle d-flex align-items-center justify-content-center fw-bold shadow-sm flex-shrink-0" style="width:38px; height:38px;">{{ strtoupper(substr($u->name, 0, 1)) }}</div>
                                    <div>
                                        <div class="fw-semibold text-dark lh-1 mb-1">{{ $u->name }}@if($u->id === $owner->id)<span class="text-muted fw-normal small"> · {{ __('you') }}</span>@endif</div>
                                        <div class="small text-muted lh-1">{{ hostelease_phone($u->mobile) }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                @if($isCoAdmin)
                                    <span class="badge rounded-pill text-white px-3 py-2 fw-semibold" style="background:var(--he-gradient-pop, linear-gradient(135deg,#4f46e5,#9333ea));"><i class="fa-solid fa-user-shield me-1" style="font-size:.7rem;"></i>{{ __('Admin') }}</span>
                                @else
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-2 fw-semibold">{{ $roles[$u->role] ?? ucfirst($u->role) }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="d-flex flex-wrap gap-1">
                                    @forelse($u->hostels as $bh)
                                        <span class="badge bg-light border text-secondary rounded-pill fw-medium">{{ $bh->name }}</span>
                                    @empty
                                        <span class="text-muted fst-italic small">—</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($u->is_active)
                                    <span class="badge bg-success-subtle text-success rounded-pill px-3 border border-success-subtle fw-semibold"><i class="fa-solid fa-circle me-1" style="font-size:.4rem; vertical-align:middle;"></i>{{ __('Active') }}</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary rounded-pill px-3 border border-secondary-subtle fw-semibold">{{ __('Disabled') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-end text-nowrap">
                                @if($isCoAdmin)
                                    {{-- Co-admin: operational control only (item 16) — enable/disable + reset. --}}
                                    <form action="{{ route('admin.users.toggle', $u) }}" method="POST" class="d-inline" data-confirm="{{ $u->is_active ? __('Disable :name?', ['name' => $u->name]) : __('Enable :name?', ['name' => $u->name]) }}">
                                        @csrf @method('PATCH')
                                        <button class="btn btn-sm btn-light rounded-circle shadow-sm" style="width:32px; height:32px;" title="{{ $u->is_active ? __('Disable') : __('Enable') }}"><i class="fa-solid {{ $u->is_active ? 'fa-ban text-warning' : 'fa-check text-success' }}" style="font-size:.8rem;"></i></button>
                                    </form>
                                    <form action="{{ route('admin.users.reset', $u) }}" method="POST" class="d-inline mx-1" data-confirm="{{ __('Reset password for :name?', ['name' => $u->name]) }}">
                                        @csrf @method('PATCH')
                                        <button class="btn btn-sm btn-light rounded-circle shadow-sm" style="width:32px; height:32px;" title="{{ __('Reset password') }}"><i class="fa-solid fa-key text-warning" style="font-size:.8rem;"></i></button>
                                    </form>
                                @else
                                    <button type="button" @click='openUserModal(@json($ud))' class="btn btn-sm btn-light rounded-circle shadow-sm" style="width:32px; height:32px;" title="{{ __('Edit User') }}"><i class="fa-solid fa-pen text-secondary" style="font-size:.8rem;"></i></button>
                                    <form action="{{ route('admin.users.reset', $u) }}" method="POST" class="d-inline mx-1" data-confirm="{{ __('Reset password for :name?', ['name' => $u->name]) }}">
                                        @csrf @method('PATCH')
                                        <button class="btn btn-sm btn-light rounded-circle shadow-sm" style="width:32px; height:32px;" title="{{ __('Reset password') }}"><i class="fa-solid fa-key text-warning" style="font-size:.8rem;"></i></button>
                                    </form>
                                    <form action="{{ route('admin.users.destroy', $u) }}" method="POST" class="d-inline" data-confirm="{{ __('Remove :name?', ['name' => $u->name]) }}">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-light rounded-circle shadow-sm" style="width:32px; height:32px;" title="{{ __('Delete') }}"><i class="fa-solid fa-trash text-danger" style="font-size:.8rem;"></i></button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-0"><x-he-empty-state icon="users-gear" title="{{ __('No team members yet') }}" subtitle="{{ __('Add staff to help manage your branches.') }}" /></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Mobile cards --}}
            <div class="d-lg-none">
                @forelse($users as $u)
                    @php($isCoAdmin = $u->isHostelAdmin())
                    @php($ud = ['id' => $u->id, 'name' => $u->name, 'mobile' => $u->mobile, 'role' => $u->role, 'branches' => $u->hostels->pluck('id')->all(), 'is_active' => (bool) $u->is_active])
                    <div class="user-card">
                        <div class="d-flex align-items-center gap-3 mb-2">
                            <div class="{{ $isCoAdmin ? 'bg-primary-subtle text-primary' : 'bg-light text-secondary' }} rounded-circle d-flex align-items-center justify-content-center fw-bold shadow-sm flex-shrink-0" style="width:42px; height:42px;">{{ strtoupper(substr($u->name, 0, 1)) }}</div>
                            <div class="min-w-0 flex-grow-1">
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <span class="fw-bold text-dark">{{ $u->name }}@if($u->id === $owner->id)<span class="text-muted fw-normal small"> · {{ __('you') }}</span>@endif</span>
                                    @if(!$u->is_active)<span class="badge bg-secondary-subtle text-secondary rounded-pill" style="font-size:.6rem;">{{ __('Disabled') }}</span>@endif
                                </div>
                                <div class="small text-muted">{{ hostelease_phone($u->mobile) }}</div>
                            </div>
                            @if($isCoAdmin)
                                <span class="badge rounded-pill text-white px-2 fw-semibold flex-shrink-0" style="font-size:.66rem; background:var(--he-gradient-pop, linear-gradient(135deg,#4f46e5,#9333ea));"><i class="fa-solid fa-user-shield me-1" style="font-size:.6rem;"></i>{{ __('Admin') }}</span>
                            @else
                                <span class="badge bg-primary-subtle text-primary rounded-pill px-2 fw-semibold flex-shrink-0" style="font-size:.68rem;">{{ $roles[$u->role] ?? ucfirst($u->role) }}</span>
                            @endif
                        </div>
                        <div class="d-flex flex-wrap gap-1 mb-2">
                            @foreach($u->hostels as $bh)
                                <span class="badge bg-light border text-secondary rounded-pill fw-medium" style="font-size:.66rem;">{{ $bh->name }}</span>
                            @endforeach
                        </div>
                        <div class="d-flex gap-2">
                            @if($isCoAdmin)
                                <form action="{{ route('admin.users.toggle', $u) }}" method="POST" class="flex-grow-1" data-confirm="{{ $u->is_active ? __('Disable :name?', ['name' => $u->name]) : __('Enable :name?', ['name' => $u->name]) }}">
                                    @csrf @method('PATCH')
                                    <button class="btn btn-sm btn-light border rounded-pill px-3 fw-semibold w-100"><i class="fa-solid {{ $u->is_active ? 'fa-ban text-warning' : 'fa-check text-success' }} me-1"></i>{{ $u->is_active ? __('Disable') : __('Enable') }}</button>
                                </form>
                                <form action="{{ route('admin.users.reset', $u) }}" method="POST" class="flex-grow-1" data-confirm="{{ __('Reset password for :name?', ['name' => $u->name]) }}">
                                    @csrf @method('PATCH')
                                    <button class="btn btn-sm btn-light border rounded-pill px-3 fw-semibold w-100"><i class="fa-solid fa-key me-1 text-warning"></i>{{ __('Reset') }}</button>
                                </form>
                            @else
                                <button type="button" @click='openUserModal(@json($ud))' class="btn btn-sm btn-light border rounded-pill px-3 fw-semibold flex-grow-1"><i class="fa-solid fa-pen me-1 text-secondary"></i>{{ __('Edit') }}</button>
                                <form action="{{ route('admin.users.reset', $u) }}" method="POST" class="flex-grow-1" data-confirm="{{ __('Reset password for :name?', ['name' => $u->name]) }}">
                                    @csrf @method('PATCH')
                                    <button class="btn btn-sm btn-light border rounded-pill px-3 fw-semibold w-100"><i class="fa-solid fa-key me-1 text-warning"></i>{{ __('Reset') }}</button>
                                </form>
                                <form action="{{ route('admin.users.destroy', $u) }}" method="POST" data-confirm="{{ __('Remove :name?', ['name' => $u->name]) }}">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-light border rounded-pill px-3"><i class="fa-solid fa-trash text-danger"></i></button>
                                </form>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="p-3"><x-he-empty-state icon="users-gear" title="{{ __('No team members yet') }}" subtitle="{{ __('Add staff to help manage your branches.') }}" /></div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- ══ TAB: MY BRANCHES ══ --}}
    <div x-show="activeTab === 'branches'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-cloak style="display:none;">

        @unless($selfServe)
            {{-- Production lock (P4 item 15): visible plans, supervised operations. --}}
            <div class="st-lock mb-4">
                <i class="fa-solid fa-shield-halved fs-5"></i>
                <div>
                    <div class="fw-bold text-dark" style="font-size:.9rem;">{{ __('Billing is managed by HostelEase support') }}</div>
                    <div class="small text-muted">{{ __('Renewals and new branches are set up for you by our team — contact support and we\'ll handle it. Your coverage below is always up to date.') }}</div>
                </div>
            </div>
        @endunless

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div class="d-flex align-items-center gap-2 text-muted small">
                <i class="fa-solid fa-calendar-check text-primary"></i>
                {{ __('All branches renew together') }}
                @if($account->current_period_end)
                    — <span class="fw-bold text-dark">{{ $account->current_period_end->format('d M Y') }}</span>
                @endif
            </div>
            @if($selfServe)
                <button type="button" @click="modals.branch.open = true" class="btn btn-primary rounded-pill shadow-sm px-4 fw-semibold tactile-btn">
                    <i class="fa-solid fa-plus me-1"></i> {{ __('Add New Branch') }}
                </button>
            @else
                <button type="button" class="btn btn-light border rounded-pill shadow-sm px-4 fw-semibold" disabled title="{{ __('Contact HostelEase support to add a branch') }}">
                    <i class="fa-solid fa-lock me-1 text-muted"></i> {{ __('Add New Branch') }}
                </button>
            @endif
        </div>

        <div class="row g-4 stagger">
            @forelse($myBranches as $branch)
            @php($isCurrent = $activeHostelId == $branch->id)
            @php($days = $branch->daysUntilExpiry())
            <div class="col-md-6 col-lg-4">
                <div class="panel-card shadow-sm st-branch {{ $isCurrent ? 'is-current' : '' }}">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="st-branch-ic {{ $isCurrent ? 'bg-white bg-opacity-10 text-white' : 'bg-primary-subtle text-primary' }}"><i class="fa-solid fa-hotel"></i></div>
                        <div class="min-w-0">
                            <h5 class="fw-bold mb-0 text-dark text-truncate">{{ $branch->name }}</h5>
                            <div class="text-muted small"><i class="fa-solid fa-location-dot me-1"></i>{{ $branch->city ?: __('Location pending') }}</div>
                        </div>
                    </div>

                    <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
                        @if($branch->isActive())
                            <span class="badge bg-success-subtle text-success rounded-pill px-3 py-2 fw-bold" style="font-size:.68rem;"><i class="fa-solid fa-circle-check me-1"></i>{{ __('Active') }}</span>
                        @else
                            <span class="badge bg-danger-subtle text-danger rounded-pill px-3 py-2 fw-bold" style="font-size:.68rem;"><i class="fa-solid fa-triangle-exclamation me-1"></i>{{ __('Expired') }}</span>
                        @endif
                        @if($branch->subscription_end)
                            <span class="small {{ $isCurrent ? 'text-white-50' : 'text-muted' }} fw-semibold">
                                {{ __('Ends') }} {{ $branch->subscription_end->format('d M Y') }}
                                @if(!is_null($days) && $days > 0) · {{ $days }}d @endif
                            </span>
                        @endif
                    </div>

                    <div class="mt-auto pt-3 border-top d-flex gap-2 align-items-center">
                        @if($isCurrent)
                            <div class="d-flex align-items-center gap-2 fw-bold flex-grow-1 {{ $isCurrent ? 'text-white' : 'text-success' }}">
                                <i class="fa-solid fa-circle-check"></i> {{ __('Current branch') }}
                            </div>
                        @else
                            <a href="{{ route('branch.switch', $branch->id) }}" class="btn btn-primary rounded-pill flex-grow-1 fw-bold shadow-sm tactile-btn">
                                <i class="fa-solid fa-right-left me-1"></i> {{ __('Switch') }}
                            </a>
                        @endif
                        @if($selfServe && $razorpayEnabled)
                            <button type="button" @click="openRenewModal({{ $branch->id }}, @js($branch->name))"
                                class="btn {{ $isCurrent ? 'btn-light text-dark' : 'btn-outline-primary' }} rounded-circle d-flex align-items-center justify-content-center tactile-btn"
                                style="width:42px; height:42px;" title="{{ __('Renew subscription') }}">
                                <i class="fa-solid fa-bolt text-warning"></i>
                            </button>
                        @else
                            <span class="btn {{ $isCurrent ? 'btn-light text-dark' : 'btn-light border' }} rounded-circle d-flex align-items-center justify-content-center opacity-50" style="width:42px; height:42px; cursor:not-allowed;" title="{{ __('Renewals are handled by HostelEase support') }}">
                                <i class="fa-solid fa-lock text-muted" style="font-size:.85rem;"></i>
                            </span>
                        @endif
                    </div>
                </div>
            </div>
            @empty
            <div class="col-12"><div class="panel-card shadow-sm p-3"><x-he-empty-state icon="building-circle-exclamation" title="{{ __('No branches found') }}" subtitle="{{ __('You don\'t have any branches in your account yet.') }}" /></div></div>
            @endforelse
        </div>
    </div>

    {{-- ══ User modal ══ --}}
    <template x-teleport="body">
        <div x-show="modals.user.open" class="custom-overlay-backdrop" style="display:none;" x-transition.opacity @click="modals.user.open = false" x-cloak>
            <form :action="modals.user.action" method="POST" class="custom-overlay-modal" style="max-width:560px;" :class="{ 'is-open': modals.user.open }" x-show="modals.user.open" @click.stop>
                @csrf
                <input type="hidden" name="_method" :value="modals.user.method">

                <div class="custom-overlay-header">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-user-plus text-primary me-2"></i><span x-text="modals.user.title"></span></h5>
                    <button type="button" @click="modals.user.open = false" class="btn-close shadow-none"></button>
                </div>

                <div class="custom-overlay-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-bold small text-muted">{{ __('FULL NAME') }} <span class="text-danger">*</span></label>
                            <input type="text" name="name" x-model="modals.user.form.name" class="form-control bg-white border shadow-sm" required placeholder="{{ __('Enter user name') }}">
                        </div>
                        <div class="col-md-6" x-show="!modals.user.isEdit">
                            <label class="form-label fw-bold small text-muted">{{ __('MOBILE (LOGIN)') }} <span class="text-danger">*</span></label>
                            <div class="input-group shadow-sm">
                                <span class="input-group-text bg-white border-end-0 fw-bold text-muted">+91</span>
                                <input type="tel" name="mobile" x-model="modals.user.form.mobile" maxlength="10" inputmode="numeric" class="form-control bg-white border-start-0" placeholder="9876543210" :required="!modals.user.isEdit">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">{{ __('ROLE') }} <span class="text-danger">*</span></label>
                            <x-he-select name="role" :submit="false" compact x-model="modals.user.form.role" placeholder="{{ __('— Select role —') }}" :options="$roles" />
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold small text-muted mb-2">{{ __('BRANCH ACCESS') }} <span class="text-danger">*</span></label>
                            <div class="row g-2">
                                @foreach($userBranches as $b)
                                    <div class="col-sm-6">
                                        <button type="button" class="branch-tile" :class="{ 'is-selected': modals.user.form.branches.includes({{ $b->id }}) }" @click="toggleUserBranch({{ $b->id }})">
                                            <span class="branch-tile-check"><i class="fa-solid fa-check"></i></span>
                                            <span class="fw-semibold text-dark lh-sm" style="font-size:.85rem;">{{ $b->name }}</span>
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                            <template x-for="id in modals.user.form.branches" :key="id"><input type="hidden" name="branches[]" :value="id"></template>
                            <div class="text-danger small mt-2" x-show="!modals.user.form.branches.length" x-cloak><i class="fa-solid fa-triangle-exclamation me-1"></i>{{ __('Select at least one branch.') }}</div>
                        </div>

                        <div class="col-12" x-show="modals.user.isEdit" style="display:none;">
                            <div class="form-check form-switch bg-light p-2 px-3 rounded-3 d-flex align-items-center justify-content-between">
                                <label class="form-check-label fw-bold text-dark m-0" for="u_active" style="font-size:.9rem;">{{ __('Account Active') }}</label>
                                <input class="form-check-input m-0 fs-5 shadow-none" type="checkbox" role="switch" name="is_active" value="1" id="u_active" x-model="modals.user.form.is_active">
                            </div>
                        </div>

                        <div class="col-12" x-show="!modals.user.isEdit">
                            <div class="p-2 px-3 bg-primary-subtle text-primary-emphasis rounded-3 small fw-medium d-flex gap-2 align-items-center">
                                <i class="fa-solid fa-lock opacity-75 flex-shrink-0"></i>
                                <span style="font-size:.8rem; line-height:1.3;">{{ __('A secure password will be generated automatically and displayed to you after saving.') }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="custom-overlay-footer">
                    <button type="button" @click="modals.user.open = false" class="btn btn-light border rounded-pill px-4 fw-bold">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm" :disabled="!modals.user.form.branches.length">
                        <i class="fa-solid fa-check me-1"></i> <span x-text="modals.user.isEdit ? '{{ __('Update') }}' : '{{ __('Save') }}'"></span>
                    </button>
                </div>
            </form>
        </div>
    </template>

    {{-- ══ Add branch modal (self-serve only) ══ --}}
    @if($selfServe)
    <template x-teleport="body">
        <div class="custom-overlay-backdrop" x-show="modals.branch.open" x-transition.opacity @click="modals.branch.open = false" x-cloak style="display:none;">
            <form action="{{ route('admin.branches.store') }}" method="POST" class="custom-overlay-modal" style="max-width:520px;" :class="{ 'is-open': modals.branch.open }" x-show="modals.branch.open" @click.stop>
                @csrf
                <div class="custom-overlay-header">
                    <h5 class="fw-bold mb-0"><i class="fa-solid fa-building-circle-arrow-right text-primary me-2"></i>{{ __('Add New Branch') }}</h5>
                    <button type="button" class="btn-close" @click="modals.branch.open = false"></button>
                </div>
                <div class="custom-overlay-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-bold small text-muted">{{ __('BRANCH NAME') }} <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control bg-white border shadow-sm" placeholder="e.g. Skyline Hostel North" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small text-muted">{{ __('ADDRESS') }}</label>
                            <input type="text" name="address" class="form-control bg-white border shadow-sm" placeholder="{{ __('Street Address') }}">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small text-muted">{{ __('CITY') }}</label>
                            <input type="text" name="city" class="form-control bg-white border shadow-sm">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small text-muted">{{ __('STATE') }}</label>
                            <input type="text" name="state" class="form-control bg-white border shadow-sm">
                        </div>
                    </div>
                </div>
                <div class="custom-overlay-footer">
                    <button type="button" class="btn btn-light border rounded-pill px-4 fw-bold" @click="modals.branch.open = false">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">{{ __('Create Branch') }}</button>
                </div>
            </form>
        </div>
    </template>

    {{-- ══ Renew modal (self-serve only) ══ --}}
    <template x-teleport="body">
        <div class="custom-overlay-backdrop" x-show="modals.renew.open" x-transition.opacity @click="modals.renew.open = false" x-cloak style="display:none;">
            <div class="custom-overlay-modal" style="max-width:600px;" :class="{ 'is-open': modals.renew.open }" x-show="modals.renew.open" @click.stop>
                <div class="custom-overlay-header">
                    <div>
                        <h5 class="fw-bold mb-1">{{ __('Renew Subscription') }}</h5>
                        <div class="text-muted small" x-text="modals.renew.branchName"></div>
                    </div>
                    <button type="button" class="btn-close" @click="modals.renew.open = false"></button>
                </div>
                <div class="custom-overlay-body">
                    <div class="row g-3 mb-2">
                        <div class="col-6">
                            <div class="plan-card d-flex flex-column h-100" :class="{ selected: modals.renew.period === 'monthly' }" @click="modals.renew.period = 'monthly'">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div class="fw-bold text-uppercase small text-muted" :class="{ 'text-primary': modals.renew.period === 'monthly' }">{{ __('Monthly') }}</div>
                                    <i class="fa-solid fa-circle-check text-primary" x-show="modals.renew.period === 'monthly'"></i>
                                </div>
                                <h3 class="fw-bold text-dark mb-1">{{ hostelease_money($monthlyPrice) }}</h3>
                                <div class="small text-muted mt-auto pt-2">{{ __('Billed monthly') }}</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="plan-card d-flex flex-column h-100" :class="{ selected: modals.renew.period === 'yearly' }" @click="modals.renew.period = 'yearly'">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div class="fw-bold text-uppercase small text-muted" :class="{ 'text-primary': modals.renew.period === 'yearly' }">{{ __('Yearly') }}</div>
                                    <i class="fa-solid fa-circle-check text-primary" x-show="modals.renew.period === 'yearly'"></i>
                                </div>
                                <h3 class="fw-bold text-dark mb-1">{{ hostelease_money($yearlyPrice) }}</h3>
                                <div class="small text-success fw-bold mt-auto pt-2">{{ __('Save 16% annually') }}</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="custom-overlay-footer">
                    <button type="button" class="btn btn-light border rounded-pill px-4 fw-bold" @click="modals.renew.open = false">{{ __('Cancel') }}</button>
                    @if($razorpayEnabled)
                        <button type="button" @click="payWithRazorpay()" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm d-flex align-items-center gap-2" :disabled="modals.renew.loading">
                            <span x-show="!modals.renew.loading">{{ __('Proceed to Payment') }}</span>
                            <span x-show="modals.renew.loading" class="spinner-border spinner-border-sm"></span>
                        </button>
                    @else
                        <button type="button" class="btn btn-secondary rounded-pill px-5 fw-bold" disabled>{{ __('Payments Disabled') }}</button>
                    @endif
                </div>
            </div>
        </div>
    </template>
    @endif

</div>
@endsection

@push('scripts')
@if($selfServe && $razorpayEnabled)
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
@endif
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('settingsManager', () => ({
            activeTab: '{{ session('active_tab', request('tab', 'profile')) }}',

            modals: {
                user: {
                    open: {{ ($errors->any() && old('role')) ? 'true' : 'false' }},
                    isEdit: false,
                    title: '{{ __('Add User') }}',
                    action: '{{ route('admin.users.store') }}',
                    method: 'POST',
                    form: {
                        id: null,
                        name: {!! json_encode(old('name', '')) !!},
                        mobile: {!! json_encode(old('mobile') ? substr(preg_replace('/\D+/', '', old('mobile')), -10) : '') !!},
                        role: {!! json_encode(old('role', '')) !!},
                        branches: {!! json_encode(array_map('intval', old('branches', []))) !!},
                        is_active: true,
                    },
                },
                branch: { open: false },
                renew: { open: false, branchId: null, branchName: '', period: 'yearly', loading: false },
            },

            toggleUserBranch(id) {
                const i = this.modals.user.form.branches.indexOf(id);
                if (i === -1) this.modals.user.form.branches.push(id); else this.modals.user.form.branches.splice(i, 1);
            },

            openUserModal(user = null) {
                const m = this.modals.user;
                if (user) {
                    m.isEdit = true;
                    m.title = '{{ __('Edit User') }}';
                    m.action = '{{ url('admin/users') }}/' + user.id;
                    m.method = 'PUT';
                    m.form = {
                        id: user.id,
                        name: user.name || '',
                        mobile: (user.mobile || '').slice(-10),
                        role: user.role || '',
                        branches: (user.branches || []).map(Number),
                        is_active: user.is_active,
                    };
                } else {
                    m.isEdit = false;
                    m.title = '{{ __('Add User') }}';
                    m.action = '{{ route('admin.users.store') }}';
                    m.method = 'POST';
                    m.form = { id: null, name: '', mobile: '', role: '', branches: [], is_active: true };
                }
                m.open = true;
            },

            openRenewModal(id, name) {
                this.modals.renew.branchId = id;
                this.modals.renew.branchName = name;
                this.modals.renew.period = 'yearly';
                this.modals.renew.loading = false;
                this.modals.renew.open = true;
            },

            @if($selfServe && $razorpayEnabled)
            async payWithRazorpay() {
                this.modals.renew.loading = true;
                try {
                    const orderRes = await fetch('{{ route('admin.branches.order') }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                        body: JSON.stringify({ branch_id: this.modals.renew.branchId, period: this.modals.renew.period }),
                    });
                    const orderData = await orderRes.json();
                    if (!orderRes.ok) throw new Error(orderData.message || 'Failed to create order');

                    const rzp = new Razorpay({
                        key: orderData.key,
                        amount: orderData.amount,
                        currency: orderData.currency,
                        name: orderData.name,
                        description: orderData.description,
                        order_id: orderData.order_id,
                        prefill: orderData.prefill,
                        theme: { color: '#4f46e5' },
                        handler: async (response) => {
                            const verifyRes = await fetch('{{ route('admin.branches.verify') }}', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                                body: JSON.stringify({
                                    branch_id: this.modals.renew.branchId,
                                    period: orderData.period,
                                    razorpay_order_id: response.razorpay_order_id,
                                    razorpay_payment_id: response.razorpay_payment_id,
                                    razorpay_signature: response.razorpay_signature,
                                }),
                            });
                            const verifyData = await verifyRes.json();
                            if (verifyRes.ok) window.location.href = verifyData.redirect;
                            else alert(verifyData.message || 'Payment verification failed');
                        },
                    });
                    rzp.on('payment.failed', (response) => alert('Payment Failed: ' + response.error.description));
                    rzp.open();
                } catch (error) {
                    alert(error.message);
                } finally {
                    this.modals.renew.loading = false;
                }
            },
            @endif
        }));
    });
</script>
@endpush
