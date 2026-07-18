@php($user = auth()->user())
<header class="he-topbar" x-data="{ searchOpen: false }">
    {{-- Mobile Sidebar Toggle --}}
    <button class="topbar-hamburger d-lg-none" data-sidebar-toggle type="button" aria-label="Toggle menu">
        <span class="hamburger-line"></span>
        <span class="hamburger-line"></span>
        <span class="hamburger-line"></span>
    </button>

    {{-- Global Search --}}
    <div class="topbar-search" :class="{ 'is-focused': searchOpen }" data-search-url="{{ route('search') }}">
        <span class="search-icon-badge"><i class="fa-solid fa-magnifying-glass"></i></span>
        <input type="search" id="global-search" class="search-input"
               placeholder="{{ $user->isSuperAdmin() ? __('Search hostels, customers…') : __('Search students, staff, rooms, receipts…') }}"
               autocomplete="off"
               @focus="searchOpen = true" @blur="searchOpen = false">
        <kbd class="search-kbd d-none d-md-inline-flex">⌘K</kbd>

        <div id="search-results" class="he-search-panel"></div>
    </div>

    {{-- Right Side Actions --}}
    <div class="topbar-actions">

        {{-- Branch Switcher --}}
        @php($branches = $user->hostels)
        @if($branches->count() > 1)
            @php($activeId = \App\Support\Tenant::id())
            @php($activeBranch = $branches->firstWhere('id', $activeId))
            <div class="dropdown">
                {{-- W9: the switcher SAYS which branch you're in (desktop) — an
                     owner running several branches shouldn't have to open a
                     menu to know where a payment is about to land. Icon-only
                     below lg, per the owner's call. --}}
                <button class="topbar-branch" data-bs-toggle="dropdown" title="{{ __('Switch Branch') }}">
                    <span class="topbar-branch-ic"><i class="fa-solid fa-code-branch"></i></span>
                    <span class="topbar-branch-txt d-none d-lg-flex">
                        <span class="topbar-branch-lbl">{{ __('Branch') }}</span>
                        <span class="topbar-branch-name">{{ \Illuminate\Support\Str::limit(optional($activeBranch)->name ?? __('Branch'), 18) }}</span>
                    </span>
                    <i class="fa-solid fa-chevron-down d-none d-lg-inline topbar-branch-chev"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-4 mt-2 p-2" style="min-width: 260px;">
                    {{-- Identity header — same language as the account menu (W9). --}}
                    <li class="px-2 pb-2 mb-2 border-bottom">
                        <div class="d-flex align-items-center gap-2 p-2 rounded-3" style="background: var(--he-bg-canvas, #f8fafc);">
                            <span class="rounded-3 d-flex align-items-center justify-content-center text-white flex-shrink-0" style="width: 38px; height: 38px; background: var(--he-gradient-pop, linear-gradient(135deg, #4f46e5, #9333ea));"><i class="fa-solid fa-hotel"></i></span>
                            <div class="min-w-0">
                                <div class="text-uppercase fw-bold text-muted" style="font-size: 0.58rem; letter-spacing: 0.5px;">{{ __('Current Branch') }}</div>
                                <div class="fw-bold text-dark text-truncate" style="font-size: 0.88rem;">{{ optional($activeBranch)->name ?? 'Branch' }}</div>
                            </div>
                        </div>
                    </li>
                    @foreach($branches as $b)
                        @if($activeId != $b->id)
                            <li>
                                <a class="dropdown-item py-2 d-flex align-items-center gap-3 rounded-2" href="{{ route('branch.switch', $b) }}">
                                    <div class="bg-light rounded d-flex align-items-center justify-content-center flex-shrink-0" style="width: 28px; height: 28px;">
                                        <i class="fa-solid fa-building text-secondary" style="font-size: 0.75rem;"></i>
                                    </div>
                                    <span class="fw-semibold small text-truncate">{{ $b->name }}</span>
                                    <i class="fa-solid fa-right-left ms-auto text-muted" style="font-size: 0.6rem;"></i>
                                </a>
                            </li>
                        @endif
                    @endforeach
                    @if($user->isHostelAdmin())
                    <li><hr class="dropdown-divider my-2"></li>
                    <li>
                        <a class="dropdown-item py-2 d-flex align-items-center gap-3 rounded-2 text-primary fw-bold" href="{{ route('admin.settings.index', ['tab' => 'branches']) }}">
                            <div class="bg-primary bg-opacity-10 rounded d-flex align-items-center justify-content-center flex-shrink-0" style="width: 28px; height: 28px;">
                                <i class="fa-solid fa-layer-group text-primary" style="font-size: 0.75rem;"></i>
                            </div>
                            <span class="small">{{ __('Manage Branches') }}</span>
                        </a>
                    </li>
                    @endif
                </ul>
            </div>
        @endif

        {{-- Language Switcher --}}
        <div class="dropdown">
            <button class="topbar-action-btn" data-bs-toggle="dropdown" title="{{ __('Language') }}">
                <span class="lang-badge">{{ strtoupper(app()->getLocale()) }}</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-4 mt-2 py-2">
                @foreach(config('app.available_locales') as $code => $label)
                    <li>
                        <a class="dropdown-item py-2 {{ app()->getLocale() === $code ? 'active bg-primary-subtle text-primary fw-bold' : '' }}" href="{{ route('locale.switch', $code) }}">
                            {{ $label }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>

        {{-- Notifications --}}
        <div class="dropdown">
            <button class="topbar-action-btn position-relative" title="{{ __('Notifications') }}" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                <i class="fa-regular fa-bell"></i>
                @if(($navNotificationCount ?? 0) > 0)
                    <span class="notification-ping"></span>
                @endif
            </button>
            <div class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-4 mt-2 p-0 overflow-hidden" style="width: 340px;">
                <div class="d-flex justify-content-between align-items-center px-4 py-3 bg-light border-bottom">
                    <h6 class="mb-0 fw-bold">{{ __('Notifications') }}</h6>
                    @if(($navNotificationCount ?? 0) > 0)
                        <span class="badge bg-danger rounded-pill">{{ $navNotificationCount }} {{ __('New') }}</span>
                    @endif
                </div>
                <div style="max-height: 380px; overflow-y: auto;">
                    @forelse($navNotifications ?? [] as $n)
                        <div class="dropdown-item d-flex gap-3 px-4 py-3 border-bottom text-wrap" style="white-space: normal; cursor: pointer; transition: background 0.2s;">
                            <div class="mt-1">
                                <div class="rounded-circle bg-{{ $n->level }}-subtle text-{{ $n->level }} d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                    <i class="fa-solid fa-{{ $n->level == 'danger' ? 'triangle-exclamation' : ($n->level == 'success' ? 'check' : 'bell') }} fs-7"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-semibold text-dark" style="font-size: 0.9rem;">{{ $n->title }}</div>
                                <div class="text-secondary mb-1" style="font-size: 0.8rem; line-height: 1.3;">{{ $n->message }}</div>
                                <div class="text-muted" style="font-size: 0.7rem;"><i class="fa-regular fa-clock me-1"></i>{{ $n->created_at->diffForHumans() }}</div>
                            </div>
                        </div>
                    @empty
                        <div class="px-4 py-5 text-center text-muted">
                            <i class="fa-solid fa-box-open fs-1 opacity-25 mb-3"></i>
                            <p class="mb-0 small fw-semibold">{{ __('You\'re all caught up!') }} 🎉</p>
                        </div>
                    @endforelse
                </div>
                <div class="p-2 border-top bg-light text-center">
                    <a href="{{ route('notifications.index') }}" class="btn btn-link btn-sm text-decoration-none fw-semibold w-100">{{ __('View all activity') }}</a>
                </div>
            </div>
        </div>

        {{-- User Profile --}}
        <div class="dropdown ms-1">
            {{-- W9: name + role ride beside the avatar on desktop — the account
                 control reads as a person, not an anonymous circle. --}}
            <button class="topbar-account" data-bs-toggle="dropdown" title="{{ $user->name }}">
                <span class="topbar-avatar">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                <span class="topbar-account-txt d-none d-lg-flex">
                    <span class="topbar-account-name">{{ \Illuminate\Support\Str::limit($user->name, 16) }}</span>
                    <span class="topbar-account-role">{{ config('hostelease.roles.'.$user->role) ?? config('hostelease.staff_roles.'.$user->role, ucfirst($user->role)) }}</span>
                </span>
                <i class="fa-solid fa-chevron-down d-none d-lg-inline topbar-branch-chev"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-4 mt-2 p-2" style="min-width: 250px;">
                {{-- Identity header — gradient avatar, same language as Settings. --}}
                <li class="px-2 pb-2 mb-2 border-bottom">
                    <div class="d-flex align-items-center gap-2 p-2 rounded-3" style="background: var(--he-bg-canvas, #f8fafc);">
                        <span class="rounded-3 d-flex align-items-center justify-content-center text-white fw-bold flex-shrink-0" style="width: 38px; height: 38px; background: var(--he-gradient-pop, linear-gradient(135deg, #4f46e5, #9333ea));">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                        <div class="min-w-0">
                            <div class="fw-bold text-dark text-truncate" style="font-size: 0.88rem;">{{ $user->name }}</div>
                            <div class="small text-muted text-truncate">{{ hostelease_phone($user->mobile) }}</div>
                        </div>
                    </div>
                </li>
                @if($user->isHostelAdmin())
                <li>
                    <a class="dropdown-item py-2 d-flex align-items-center gap-3 rounded-2" href="{{ route('admin.settings.index') }}">
                        <div class="bg-light rounded d-flex align-items-center justify-content-center text-secondary" style="width: 28px; height: 28px;">
                            <i class="fa-solid fa-gear" style="font-size: 0.75rem;"></i>
                        </div>
                        <span class="fw-semibold small">{{ __('Settings') }}</span>
                    </a>
                </li>
                @endif
                <li>
                    <a class="dropdown-item py-2 d-flex align-items-center gap-3 rounded-2" href="{{ route('profile.password') }}">
                        <div class="bg-light rounded d-flex align-items-center justify-content-center text-secondary" style="width: 28px; height: 28px;">
                            <i class="fa-solid fa-key" style="font-size: 0.75rem;"></i>
                        </div>
                        <span class="fw-semibold small">{{ __('Change Password') }}</span>
                    </a>
                </li>
                <li><hr class="dropdown-divider my-2"></li>
                <li>
                    <button class="dropdown-item py-2 d-flex align-items-center gap-3 rounded-2 text-danger" type="button" onclick="document.getElementById('logout-form').submit()">
                        <div class="bg-danger-subtle rounded d-flex align-items-center justify-content-center text-danger" style="width: 28px; height: 28px;">
                            <i class="fa-solid fa-right-from-bracket" style="font-size: 0.75rem;"></i>
                        </div>
                        <span class="fw-semibold small">{{ __('Secure Logout') }}</span>
                    </button>
                </li>
            </ul>
        </div>
    </div>
</header>

<style>
    /* ─── Branch switcher + account triggers (W9) ─────────────
       Desktop: labelled premium pills — the chrome SAYS which branch and who.
       Below lg both collapse to their icon/avatar (the d-none d-lg-* spans). */
    /* Rounded RECTANGLES, not pills: the topbar's geometry is 10–14px squircles
       (search 14px, action buttons + avatar 10px) — a 9999px pill wrapping a
       rounded-square avatar clashed. 12px sits between its neighbours. */
    .topbar-branch, .topbar-account {
        display: flex; align-items: center; gap: 0.55rem;
        /* Quiet white + hairline (owner): the raised grey fill read as a heavy
           slab next to the airy action icons — the search bar earns its fill
           because it IS an input; these are buttons. */
        border: 1.5px solid rgba(0, 0, 0, 0.07);
        background: var(--he-bg-surface, #fff);
        border-radius: 12px;
        padding: 0.3rem 0.6rem 0.3rem 0.3rem;
        transition: all 0.25s var(--ease-out-expo, cubic-bezier(0.16, 1, 0.3, 1));
        cursor: pointer;
    }
    .topbar-branch:hover, .topbar-account:hover,
    .topbar-branch[aria-expanded="true"], .topbar-account[aria-expanded="true"] {
        border-color: rgba(79, 70, 229, 0.35);
        box-shadow: 0 4px 14px rgba(79, 70, 229, 0.12);
        transform: translateY(-1px);
    }
    .topbar-branch:active, .topbar-account:active { transform: translateY(0) scale(0.97); }
    .topbar-branch-ic {
        width: 32px; height: 32px; border-radius: 10px; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        background: var(--he-primary-soft, rgba(79, 70, 229, 0.1));
        color: var(--he-primary, #4f46e5); font-size: 0.8rem;
    }
    .topbar-branch-txt, .topbar-account-txt { flex-direction: column; align-items: flex-start; line-height: 1.15; min-width: 0; }
    .topbar-branch-lbl, .topbar-account-role {
        font-size: 0.58rem; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.5px; color: var(--he-text-muted, #64748b);
    }
    .topbar-branch-name, .topbar-account-name {
        font-size: 0.8rem; font-weight: 700; color: var(--he-text-main, #0f172a);
        white-space: nowrap; max-width: 150px; overflow: hidden; text-overflow: ellipsis;
    }
    .topbar-branch-chev { font-size: 0.55rem; color: var(--he-text-muted, #64748b); }
    @media (max-width: 991.98px) {
        .topbar-branch, .topbar-account { border: 0; background: transparent; padding: 0.2rem; }
    }

    /* ─── Topbar Shell ────────────────────────────────────── */
    .he-topbar {
        position: sticky;
        top: 0;
        z-index: 1020;
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 0.65rem 1.5rem;
        background: rgba(255, 255, 255, 0.82);
        backdrop-filter: blur(20px) saturate(180%);
        -webkit-backdrop-filter: blur(20px) saturate(180%);
        border-bottom: 1px solid rgba(0, 0, 0, 0.04);
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        animation: topbarSlideDown 0.5s cubic-bezier(0.25, 1, 0.5, 1) forwards;
    }
    @keyframes topbarSlideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* ─── Hamburger ───────────────────────────────────────── */
    .topbar-hamburger {
        width: 36px;
        height: 36px;
        padding: 0;
        border: none;
        background: transparent;
        cursor: pointer;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 4px;
        border-radius: 8px;
        transition: background 0.2s ease;
    }
    .topbar-hamburger:hover { background: rgba(0, 0, 0, 0.04); }
    .hamburger-line {
        display: block;
        width: 18px;
        height: 2px;
        background: #64748b;
        border-radius: 2px;
        transition: all 0.3s cubic-bezier(0.25, 1, 0.5, 1);
    }
    .topbar-hamburger:hover .hamburger-line { background: #0f172a; }

    /* ─── Search Bar ──────────────────────────────────────── */
    /* Global search — same premium language as the canonical `.he-search`
       page field (icon badge + 14px radius + raised surface + primary focus
       ring), implemented inline here because the topbar search is a flex row
       carrying the ⌘K hint and the results panel. Keep the two visually in
       step. */
    .topbar-search {
        position: relative; /* positioning context for .he-search-panel */
        flex-grow: 1;
        max-width: 420px;
        display: flex;
        align-items: center;
        gap: 0.6rem;
        padding: 0.4rem 0.75rem 0.4rem 0.4rem;
        border-radius: 14px;
        background: var(--he-bg-surface-raised, #f1f5f9);
        border: 1.5px solid rgba(0, 0, 0, 0.06);
        transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .topbar-search.is-focused {
        background: #fff;
        border-color: var(--he-primary, #4f46e5);
        box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
    }
    .search-icon-badge {
        width: 30px;
        height: 30px;
        flex-shrink: 0;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--he-primary-soft, rgba(79, 70, 229, 0.1));
        color: var(--he-primary, #4f46e5);
        font-size: 0.75rem;
        transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .topbar-search.is-focused .search-icon-badge {
        background: var(--he-primary, #4f46e5);
        color: #fff;
    }
    .search-input {
        border: none;
        background: transparent;
        outline: none;
        flex: 1;
        min-width: 0;
        font-size: 0.9rem;
        color: #0f172a;
        font-weight: 500;
    }
    .search-input::placeholder { color: #94a3b8; font-weight: 400; }
    .search-kbd {
        background: #e2e8f0;
        color: #64748b;
        font-size: 0.65rem;
        font-weight: 600;
        padding: 0.15rem 0.4rem;
        border-radius: 5px;
        font-family: inherit;
        line-height: 1.3;
    }

    /* ─── Actions Row ─────────────────────────────────────── */
    .topbar-actions {
        display: flex;
        align-items: center;
        gap: 0.35rem;
        margin-left: auto;
    }

    /* ─── Action Button ───────────────────────────────────── */
    .topbar-action-btn {
        width: 38px;
        height: 38px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        background: transparent;
        color: #64748b;
        border: 1.5px solid transparent;
        cursor: pointer;
        transition: all 0.25s cubic-bezier(0.25, 1, 0.5, 1);
        position: relative;
    }
    .topbar-action-btn:hover,
    .topbar-action-btn[aria-expanded="true"] {
        background: #f1f5f9;
        color: var(--he-primary, #4f46e5);
        border-color: rgba(79, 70, 229, 0.1);
        transform: translateY(-1px);
    }
    .topbar-action-btn:active {
        transform: translateY(0) scale(0.95);
    }

    /* ─── Language Badge ──────────────────────────────────── */
    .lang-badge {
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.02em;
    }

    /* ─── Notification Ping ───────────────────────────────── */
    .notification-ping {
        position: absolute;
        top: 6px;
        right: 6px;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #ef4444;
        border: 2px solid #fff;
        animation: pingPulse 2s ease-in-out infinite;
    }
    @keyframes pingPulse {
        0%, 100% { transform: scale(1); opacity: 1; }
        50% { transform: scale(1.3); opacity: 0.7; }
    }

    /* ─── User Avatar ─────────────────────────────────────── */
    .topbar-avatar {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        background: linear-gradient(135deg, #4f46e5, #7c3aed);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.9rem;
        cursor: pointer;
        border: 2px solid transparent;
        transition: all 0.25s cubic-bezier(0.25, 1, 0.5, 1);
    }
    .topbar-avatar:hover,
    .topbar-avatar[aria-expanded="true"] {
        border-color: rgba(79, 70, 229, 0.25);
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        transform: translateY(-1px);
    }

    /* ─── Global Search Results ───────────────────────────── */
    /* Self-positioned relative to .topbar-search (not Bootstrap's
       .dropdown-menu, which needs Popper.js for its "position: absolute;
       inset: 0" to resolve correctly — this panel is toggled by plain JS,
       so it gets its own anchored positioning instead, matching the
       .he-select-menu pattern used elsewhere in the app). */
    .he-search-panel {
        position: absolute;
        top: calc(100% + 0.5rem);
        left: 0;
        right: 0;
        z-index: 1050;
        max-height: 400px;
        overflow-y: auto;
        overflow-x: hidden;
        padding: 0.4rem;
        background: var(--he-bg-surface);
        border-radius: var(--he-radius-md);
        box-shadow: var(--he-shadow-lg);
        border: 1px solid rgba(0, 0, 0, 0.06);
        opacity: 0;
        pointer-events: none;
        transform: translateY(-6px) scale(0.98);
        transition: opacity 0.18s var(--ease-out-expo), transform 0.18s var(--ease-out-expo);
    }
    .he-search-panel.show {
        opacity: 1;
        pointer-events: auto;
        transform: translateY(0) scale(1);
    }
    @media (max-width: 575.98px) {
        .he-search-panel { left: -0.5rem; right: -0.5rem; }
    }
    .he-search-group {
        font-size: var(--he-text-xs);
        text-transform: uppercase;
        letter-spacing: 0.06em;
        font-weight: 700;
        color: var(--he-text-muted);
        padding: 0.5rem 0.6rem 0.25rem;
    }
    .he-search-item {
        display: flex;
        align-items: center;
        gap: 0.7rem;
        padding: 0.55rem 0.6rem;
        border-radius: 0.6rem;
        text-decoration: none;
        color: var(--he-text-main);
        transition: background 0.15s var(--ease-out-expo);
    }
    .he-search-item:hover { background: var(--he-primary-soft); }
    .he-search-item.is-loading { pointer-events: none; }
    .he-search-ic {
        width: 34px;
        height: 34px;
        flex-shrink: 0;
        border-radius: 9px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--he-primary-soft);
        color: var(--he-primary);
        font-size: 0.85rem;
    }
    .he-search-text { display: flex; flex-direction: column; min-width: 0; flex: 1; }
    .he-search-label {
        font-weight: 600;
        font-size: 0.85rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .he-search-sub {
        color: var(--he-text-muted);
        font-size: 0.72rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .he-search-go {
        opacity: 0;
        color: var(--he-primary);
        font-size: 0.75rem;
        transition: opacity 0.15s ease, transform 0.15s ease;
    }
    .he-search-item:hover .he-search-go { opacity: 1; transform: translateX(2px); }
    .he-search-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.5rem;
        padding: 1.75rem 1rem;
        color: var(--he-text-muted);
    }
    .he-search-empty i { font-size: 1.5rem; opacity: 0.3; }
    .he-search-empty span { font-size: var(--he-text-sm); }

    /* ─── Topbar Mobile Responsive ────────────────────────── */
    @media (max-width: 575.98px) {
        .he-topbar { flex-wrap: wrap; row-gap: 0.5rem; padding: 0.6rem 1rem; }
        .topbar-search { order: 3; max-width: 100% !important; flex: 0 0 100%; }
        .search-kbd { display: none !important; }
    }
</style>
