@php($user = auth()->user())
<aside class="hsms-sidebar" x-data="sidebarNav()" x-cloak>
    {{-- ═══════════════════════════════════════════════════════════
         HEADER — Brand Identity
    ═══════════════════════════════════════════════════════════════ --}}
    <div class="sidebar-brand">
        <div class="brand-logo-wrap">
            <img src="{{ asset('hsms-icon.svg') }}" alt="HSMS" class="brand-logo">
            <div class="brand-glow"></div>
        </div>
        <div class="brand-text">
            <span class="brand-name">HSMS</span>
            <span class="brand-tagline">Hostel Management</span>
        </div>
        <button type="button" class="sidebar-close-btn d-lg-none" data-sidebar-close aria-label="Close menu">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    {{-- ═══════════════════════════════════════════════════════════
         NAVIGATION LINKS
    ═══════════════════════════════════════════════════════════════ --}}
    <nav class="sidebar-nav" id="sidebar-nav">
        @if($user->isSuperAdmin())
            {{-- SuperAdmin Links --}}
            <a class="sidebar-link {{ request()->routeIs('superadmin.dashboard') ? 'is-active' : '' }}" href="{{ route('superadmin.dashboard') }}">
                <span class="sidebar-link-icon"><i class="fa-solid fa-gauge-high"></i></span>
                <span class="sidebar-link-label">{{ __('Dashboard') }}</span>
            </a>
            <a class="sidebar-link {{ request()->routeIs('superadmin.hostels.*') ? 'is-active' : '' }}" href="{{ route('superadmin.hostels.index') }}">
                <span class="sidebar-link-icon"><i class="fa-solid fa-hotel"></i></span>
                <span class="sidebar-link-label">{{ __('Hostels') }}</span>
            </a>
            <a class="sidebar-link {{ request()->routeIs('superadmin.subscriptions.*') ? 'is-active' : '' }}" href="{{ route('superadmin.subscriptions.index') }}">
                <span class="sidebar-link-icon"><i class="fa-solid fa-receipt"></i></span>
                <span class="sidebar-link-label">{{ __('Subscriptions') }}</span>
            </a>
            <a class="sidebar-link {{ request()->routeIs('superadmin.activity') ? 'is-active' : '' }}" href="{{ route('superadmin.activity') }}">
                <span class="sidebar-link-icon"><i class="fa-solid fa-list-check"></i></span>
                <span class="sidebar-link-label">{{ __('Activity Logs') }}</span>
            </a>
            <a class="sidebar-link {{ request()->routeIs('superadmin.backups.*') ? 'is-active' : '' }}" href="{{ route('superadmin.backups.index') }}">
                <span class="sidebar-link-icon"><i class="fa-solid fa-database"></i></span>
                <span class="sidebar-link-label">{{ __('Backups') }}</span>
            </a>
        @else
            {{-- Admin / Staff Links --}}

            {{-- Dashboard --}}
            <a class="sidebar-link {{ request()->routeIs('admin.dashboard') ? 'is-active' : '' }}" href="{{ route('admin.dashboard') }}">
                <span class="sidebar-link-icon"><i class="fa-solid fa-gauge-high"></i></span>
                <span class="sidebar-link-label">{{ __('Dashboard') }}</span>
            </a>

            {{-- Property Board --}}
            <a class="sidebar-link {{ request()->routeIs('admin.property.*') ? 'is-active' : '' }}" href="{{ route('admin.property.index') }}">
                <span class="sidebar-link-icon"><i class="fa-solid fa-building"></i></span>
                <span class="sidebar-link-label">{{ __('Property Board') }}</span>
            </a>

            {{-- People --}}
            @php($peopleActive = request()->routeIs('admin.students.*', 'admin.assignments.*', 'admin.registrations.*'))
            <div class="sidebar-group" x-data="{ expanded: {{ $peopleActive ? 'true' : 'false' }} }">
                <button class="sidebar-link" :class="{ 'is-active': {{ $peopleActive ? 'true' : 'false' }}, 'is-expanded': expanded }" @click="expanded = !expanded" type="button">
                    <span class="sidebar-link-icon"><i class="fa-solid fa-users"></i></span>
                    <span class="sidebar-link-label">{{ __('People') }}</span>
                    <span class="sidebar-chevron"><i class="fa-solid fa-chevron-right"></i></span>
                </button>
                <div class="sidebar-submenu" x-show="expanded" x-collapse.duration.350ms>
                    <a class="sidebar-sublink {{ request()->routeIs('admin.students.*') ? 'is-active' : '' }}" href="{{ route('admin.students.index') }}">
                        <span class="sublink-dot"></span>{{ __('Students') }}
                    </a>
                    <a class="sidebar-sublink {{ request()->routeIs('admin.registrations.*') ? 'is-active' : '' }}" href="{{ route('admin.registrations.index') }}">
                        <span class="sublink-dot"></span>{{ __('Registrations') }}
                    </a>
                </div>
            </div>

            {{-- Front Desk --}}
            @php($deskActive = request()->routeIs('admin.frontdesk.*', 'admin.visitors.*', 'admin.complaints.*'))
            <div class="sidebar-group" x-data="{ expanded: {{ $deskActive ? 'true' : 'false' }} }">
                <button class="sidebar-link" :class="{ 'is-active': {{ $deskActive ? 'true' : 'false' }}, 'is-expanded': expanded }" @click="expanded = !expanded" type="button">
                    <span class="sidebar-link-icon"><i class="fa-solid fa-bell-concierge"></i></span>
                    <span class="sidebar-link-label">{{ __('Front Desk') }}</span>
                    <span class="sidebar-chevron"><i class="fa-solid fa-chevron-right"></i></span>
                </button>
                <div class="sidebar-submenu" x-show="expanded" x-collapse.duration.350ms>
                    <a class="sidebar-sublink {{ request()->routeIs('admin.frontdesk.*') && request('tab', 'visitors') === 'visitors' ? 'is-active' : '' }}" href="{{ route('admin.frontdesk.index', ['tab' => 'visitors']) }}">
                        <span class="sublink-dot"></span>{{ __('Visitors') }}
                    </a>
                    <a class="sidebar-sublink {{ request()->routeIs('admin.frontdesk.*') && request('tab') === 'complaints' ? 'is-active' : '' }}" href="{{ route('admin.frontdesk.index', ['tab' => 'complaints']) }}">
                        <span class="sublink-dot"></span>{{ __('Complaints') }}
                    </a>
                </div>
            </div>

            {{-- Finance --}}
            @php($financeActive = request()->routeIs('admin.finance.*', 'admin.pocket-money.*', 'admin.payment-modes.*', 'admin.expenses.*', 'admin.ac-bills.*', 'admin.security-deposits.*'))
            <div class="sidebar-group" x-data="{ expanded: {{ $financeActive ? 'true' : 'false' }} }">
                <button class="sidebar-link" :class="{ 'is-active': {{ $financeActive ? 'true' : 'false' }}, 'is-expanded': expanded }" @click="expanded = !expanded" type="button">
                    <span class="sidebar-link-icon"><i class="fa-solid fa-chart-pie"></i></span>
                    <span class="sidebar-link-label">{{ __('Finance') }}</span>
                    <span class="sidebar-chevron"><i class="fa-solid fa-chevron-right"></i></span>
                </button>
                <div class="sidebar-submenu" x-show="expanded" x-collapse.duration.350ms>
                    <a class="sidebar-sublink {{ request()->routeIs('admin.finance.*') && request('tab') !== 'transactions' ? 'is-active' : '' }}" href="{{ route('admin.finance.index', ['tab' => 'invoices']) }}">
                        <span class="sublink-dot"></span>{{ __('Invoices & Dues') }}
                    </a>
                    <a class="sidebar-sublink {{ request()->routeIs('admin.finance.*') && request('tab') === 'transactions' ? 'is-active' : '' }}" href="{{ route('admin.finance.index', ['tab' => 'transactions']) }}">
                        <span class="sublink-dot"></span>{{ __('Transactions') }}
                    </a>
                    <a class="sidebar-sublink {{ request()->routeIs('admin.expenses.*') ? 'is-active' : '' }}" href="{{ route('admin.expenses.index') }}">
                        <span class="sublink-dot"></span>{{ __('Expenses') }}
                    </a>
                    <a class="sidebar-sublink {{ request()->routeIs('admin.ac-bills.*') ? 'is-active' : '' }}" href="{{ route('admin.ac-bills.index') }}">
                        <span class="sublink-dot"></span>{{ __('AC Bills') }}
                    </a>
                    @if(Route::has('admin.security-deposits.index'))
                    <a class="sidebar-sublink {{ request()->routeIs('admin.security-deposits.*') ? 'is-active' : '' }}" href="{{ route('admin.security-deposits.index') }}">
                        <span class="sublink-dot"></span>{{ __('Security Deposits') }}
                    </a>
                    @endif
                    <a class="sidebar-sublink {{ request()->routeIs('admin.pocket-money.*') ? 'is-active' : '' }}" href="{{ route('admin.pocket-money.index') }}">
                        <span class="sublink-dot"></span>{{ __('Pocket Money') }}
                    </a>
                    <a class="sidebar-sublink {{ request()->routeIs('admin.payment-modes.*') ? 'is-active' : '' }}" href="{{ route('admin.payment-modes.index') }}">
                        <span class="sublink-dot"></span>{{ __('Payment Modes') }}
                    </a>
                </div>
            </div>

            {{-- Staff & Ops --}}
            @php($opsActive = request()->routeIs('admin.staff.*'))
            <div class="sidebar-group" x-data="{ expanded: {{ $opsActive ? 'true' : 'false' }} }">
                <button class="sidebar-link" :class="{ 'is-active': {{ $opsActive ? 'true' : 'false' }}, 'is-expanded': expanded }" @click="expanded = !expanded" type="button">
                    <span class="sidebar-link-icon"><i class="fa-solid fa-briefcase"></i></span>
                    <span class="sidebar-link-label">{{ __('Staff & Ops') }}</span>
                    <span class="sidebar-chevron"><i class="fa-solid fa-chevron-right"></i></span>
                </button>
                <div class="sidebar-submenu" x-show="expanded" x-collapse.duration.350ms>
                    <a class="sidebar-sublink {{ request()->routeIs('admin.staff.*') && request('tab', 'directory') === 'directory' ? 'is-active' : '' }}" href="{{ route('admin.staff.index', ['tab' => 'directory']) }}">
                        <span class="sublink-dot"></span>{{ __('Staff Directory') }}
                    </a>
                    <a class="sidebar-sublink {{ request()->routeIs('admin.staff.*') && request('tab') === 'attendance' ? 'is-active' : '' }}" href="{{ route('admin.staff.index', ['tab' => 'attendance']) }}">
                        <span class="sublink-dot"></span>{{ __('Attendance') }}
                    </a>
                </div>
            </div>

            {{-- Reports (Solo section) --}}
            <a class="sidebar-link {{ request()->routeIs('admin.reports.*') ? 'is-active' : '' }}" href="{{ route('admin.reports.index') }}">
                <span class="sidebar-link-icon"><i class="fa-solid fa-chart-line"></i></span>
                <span class="sidebar-link-label">{{ __('Reports') }}</span>
            </a>
        @endif
    </nav>

    {{-- ═══════════════════════════════════════════════════════════
         FOOTER — Settings Pinned to Bottom + User Info
    ═══════════════════════════════════════════════════════════════ --}}
    <div class="sidebar-footer">
        <div class="sidebar-divider"></div>

        @if(!$user->isSuperAdmin())
        {{-- Settings (pinned to bottom) --}}
        @php($settingsActive = request()->routeIs('admin.users.*', 'admin.branches.*'))
        <a class="sidebar-link sidebar-settings-link {{ $settingsActive ? 'is-active' : '' }}" href="{{ route('admin.users.index') }}">
            <span class="sidebar-link-icon"><i class="fa-solid fa-gear"></i></span>
            <span class="sidebar-link-label">{{ __('Settings') }}</span>
        </a>
        @endif

        {{-- User Card --}}
        <div class="sidebar-user-card">
            <div class="sidebar-user-avatar">
                {{ strtoupper(substr($user->name, 0, 1)) }}
            </div>
            <div class="sidebar-user-info">
                <span class="sidebar-user-name">{{ Str::limit($user->name, 16) }}</span>
                <span class="sidebar-user-role">{{ $user->isSuperAdmin() ? 'Super Admin' : ucfirst(str_replace('_', ' ', $user->role)) }}</span>
            </div>
        </div>
    </div>
</aside>

{{-- ═══════════════════════════════════════════════════════════
     SIDEBAR STYLES — Ultra Premium Design System
═══════════════════════════════════════════════════════════════ --}}
<style>
    /* ─── Easing Tokens ───────────────────────────────────── */
    :root {
        --sb-spring: cubic-bezier(0.25, 1, 0.5, 1);
        --sb-bounce: cubic-bezier(0.34, 1.56, 0.64, 1);
        --sb-smooth: cubic-bezier(0.4, 0, 0.2, 1);
    }

    /* ─── Brand Header ────────────────────────────────────── */
    .sidebar-brand {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 1.25rem 1.15rem;
        position: relative;
    }
    .sidebar-brand::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 1.15rem;
        right: 1.15rem;
        height: 1px;
        background: linear-gradient(90deg, transparent, rgba(148, 163, 184, 0.15), transparent);
    }
    .brand-logo-wrap {
        position: relative;
        flex-shrink: 0;
    }
    .brand-logo {
        width: 34px;
        height: 34px;
        border-radius: 10px;
        position: relative;
        z-index: 1;
    }
    .brand-glow {
        position: absolute;
        inset: -3px;
        border-radius: 13px;
        background: linear-gradient(135deg, rgba(79, 70, 229, 0.4), rgba(147, 51, 234, 0.4));
        filter: blur(6px);
        opacity: 0.6;
        animation: glowPulse 3s ease-in-out infinite;
    }
    @keyframes glowPulse {
        0%, 100% { opacity: 0.4; transform: scale(1); }
        50% { opacity: 0.7; transform: scale(1.05); }
    }
    .brand-text {
        display: flex;
        flex-direction: column;
        line-height: 1.1;
    }
    .brand-name {
        font-weight: 800;
        font-size: 1.05rem;
        color: #fff;
        letter-spacing: 0.03em;
    }
    .brand-tagline {
        font-size: 0.65rem;
        color: rgba(148, 163, 184, 0.7);
        letter-spacing: 0.04em;
        font-weight: 500;
    }
    .sidebar-close-btn {
        position: absolute;
        right: 1rem;
        top: 50%;
        transform: translateY(-50%);
        width: 30px;
        height: 30px;
        border-radius: 8px;
        border: none;
        background: rgba(255, 255, 255, 0.06);
        color: rgba(148, 163, 184, 0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s var(--sb-smooth);
    }
    .sidebar-close-btn:hover {
        background: rgba(239, 68, 68, 0.15);
        color: #ef4444;
    }

    /* ─── Navigation Container ────────────────────────────── */
    .sidebar-nav {
        flex: 1 1 auto;
        overflow-y: auto;
        overflow-x: hidden;
        padding: 0.5rem 0.65rem;
        scrollbar-width: thin;
        scrollbar-color: rgba(148, 163, 184, 0.2) transparent;
    }
    .sidebar-nav::-webkit-scrollbar { width: 4px; }
    .sidebar-nav::-webkit-scrollbar-track { background: transparent; }
    .sidebar-nav::-webkit-scrollbar-thumb { background: rgba(148, 163, 184, 0.2); border-radius: 4px; }
    .sidebar-nav::-webkit-scrollbar-thumb:hover { background: rgba(148, 163, 184, 0.4); }

    /* ─── Sidebar Link (Main Items) ───────────────────────── */
    .sidebar-link {
        display: flex;
        align-items: center;
        gap: 0.7rem;
        padding: 0.55rem 0.75rem;
        margin: 0.12rem 0;
        border-radius: 0.6rem;
        color: rgba(203, 213, 225, 0.85);
        text-decoration: none;
        font-size: 0.875rem;
        font-weight: 500;
        cursor: pointer;
        border: none;
        background: transparent;
        width: 100%;
        text-align: left;
        position: relative;
        transition: all 0.25s var(--sb-spring);
    }
    .sidebar-link:hover {
        color: #fff;
        background: rgba(255, 255, 255, 0.06);
        transform: translateX(3px);
    }
    .sidebar-link:hover .sidebar-link-icon {
        transform: scale(1.12);
    }
    .sidebar-link:active {
        transform: translateX(3px) scale(0.98);
    }

    /* ─── Active State ────────────────────────────────────── */
    .sidebar-link.is-active {
        color: #fff;
        background: rgba(79, 70, 229, 0.18);
    }
    .sidebar-link.is-active::before {
        content: '';
        position: absolute;
        left: -0.65rem;
        top: 0.3rem;
        bottom: 0.3rem;
        width: 3px;
        border-radius: 0 3px 3px 0;
        background: linear-gradient(180deg, #4f46e5, #7c3aed);
        animation: indicatorSlideIn 0.4s var(--sb-bounce) forwards;
    }
    @keyframes indicatorSlideIn {
        from { transform: scaleY(0); opacity: 0; }
        to { transform: scaleY(1); opacity: 1; }
    }

    /* ─── Icon ────────────────────────────────────────────── */
    .sidebar-link-icon {
        width: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
        opacity: 0.75;
        transition: transform 0.35s var(--sb-bounce), opacity 0.2s ease;
        flex-shrink: 0;
    }
    .sidebar-link.is-active .sidebar-link-icon { opacity: 1; }

    /* ─── Chevron (Expandable Groups) ─────────────────────── */
    .sidebar-chevron {
        margin-left: auto;
        font-size: 0.65rem;
        opacity: 0.4;
        transition: transform 0.3s var(--sb-spring), opacity 0.2s ease;
    }
    .sidebar-link.is-expanded .sidebar-chevron {
        transform: rotate(90deg);
        opacity: 0.7;
    }

    /* ─── Submenu ─────────────────────────────────────────── */
    .sidebar-submenu {
        margin-left: 1.35rem;
        padding-left: 0.75rem;
        border-left: 1.5px solid rgba(148, 163, 184, 0.1);
        margin-bottom: 0.25rem;
        position: relative;
    }

    .sidebar-sublink {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        padding: 0.4rem 0.65rem;
        margin: 0.06rem 0;
        border-radius: 0.5rem;
        color: rgba(148, 163, 184, 0.75);
        text-decoration: none;
        font-size: 0.82rem;
        font-weight: 500;
        transition: all 0.25s var(--sb-spring);
        position: relative;
    }
    .sidebar-sublink:hover {
        color: #e2e8f0;
        transform: translateX(4px);
    }
    .sidebar-sublink.is-active {
        color: #a5b4fc;
        font-weight: 600;
    }
    .sidebar-sublink.is-active .sublink-dot {
        background: #818cf8;
        box-shadow: 0 0 6px rgba(129, 140, 248, 0.5);
        transform: scale(1.3);
    }

    /* ─── Sublink Dot ─────────────────────────────────────── */
    .sublink-dot {
        width: 5px;
        height: 5px;
        border-radius: 50%;
        background: rgba(148, 163, 184, 0.35);
        flex-shrink: 0;
        transition: all 0.3s var(--sb-bounce);
    }

    /* ─── Footer ──────────────────────────────────────────── */
    .sidebar-footer {
        flex-shrink: 0;
        padding: 0 0.65rem 0.75rem;
    }
    .sidebar-divider {
        height: 1px;
        background: linear-gradient(90deg, transparent, rgba(148, 163, 184, 0.12), transparent);
        margin: 0.5rem 0.5rem 0.5rem;
    }

    /* ─── Settings Link (Bottom) ──────────────────────────── */
    .sidebar-settings-link {
        margin-bottom: 0.5rem !important;
    }
    .sidebar-settings-link .sidebar-link-icon i {
        transition: transform 0.5s var(--sb-smooth);
    }
    .sidebar-settings-link:hover .sidebar-link-icon i {
        transform: rotate(90deg) scale(1.1);
    }

    /* ─── User Card ───────────────────────────────────────── */
    .sidebar-user-card {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        padding: 0.6rem 0.75rem;
        border-radius: 0.7rem;
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid rgba(255, 255, 255, 0.04);
        transition: all 0.25s var(--sb-smooth);
    }
    .sidebar-user-card:hover {
        background: rgba(255, 255, 255, 0.07);
        border-color: rgba(255, 255, 255, 0.06);
    }
    .sidebar-user-avatar {
        width: 32px;
        height: 32px;
        border-radius: 9px;
        background: linear-gradient(135deg, #4f46e5, #7c3aed);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.8rem;
        flex-shrink: 0;
    }
    .sidebar-user-info {
        display: flex;
        flex-direction: column;
        line-height: 1.15;
        min-width: 0;
    }
    .sidebar-user-name {
        font-size: 0.8rem;
        font-weight: 600;
        color: #e2e8f0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .sidebar-user-role {
        font-size: 0.68rem;
        color: rgba(148, 163, 184, 0.65);
        font-weight: 500;
    }

    /* ─── Entry Animations ────────────────────────────────── */
    .sidebar-link,
    .sidebar-sublink {
        opacity: 0;
        animation: sidebarFadeIn 0.5s var(--sb-spring) forwards;
    }
    @keyframes sidebarFadeIn {
        from { opacity: 0; transform: translateX(-12px); }
        to { opacity: 1; transform: translateX(0); }
    }
    /* Stagger on page load */
    .sidebar-nav > :nth-child(1) .sidebar-link,
    .sidebar-nav > a:nth-child(1) { animation-delay: 0.03s; }
    .sidebar-nav > :nth-child(2) .sidebar-link,
    .sidebar-nav > a:nth-child(2) { animation-delay: 0.06s; }
    .sidebar-nav > :nth-child(3) .sidebar-link,
    .sidebar-nav > a:nth-child(3) { animation-delay: 0.09s; }
    .sidebar-nav > :nth-child(4) .sidebar-link,
    .sidebar-nav > a:nth-child(4) { animation-delay: 0.12s; }
    .sidebar-nav > :nth-child(5) .sidebar-link,
    .sidebar-nav > a:nth-child(5) { animation-delay: 0.15s; }
    .sidebar-nav > :nth-child(6) .sidebar-link,
    .sidebar-nav > a:nth-child(6) { animation-delay: 0.18s; }
    .sidebar-nav > :nth-child(7) .sidebar-link,
    .sidebar-nav > a:nth-child(7) { animation-delay: 0.21s; }
    .sidebar-nav > :nth-child(8) .sidebar-link,
    .sidebar-nav > a:nth-child(8) { animation-delay: 0.24s; }
    .sidebar-nav > :nth-child(9) .sidebar-link,
    .sidebar-nav > a:nth-child(9) { animation-delay: 0.27s; }

    /* Sublink stagger */
    .sidebar-submenu .sidebar-sublink:nth-child(1) { animation-delay: 0.05s; }
    .sidebar-submenu .sidebar-sublink:nth-child(2) { animation-delay: 0.1s; }
    .sidebar-submenu .sidebar-sublink:nth-child(3) { animation-delay: 0.15s; }
    .sidebar-submenu .sidebar-sublink:nth-child(4) { animation-delay: 0.18s; }
    .sidebar-submenu .sidebar-sublink:nth-child(5) { animation-delay: 0.21s; }
    .sidebar-submenu .sidebar-sublink:nth-child(6) { animation-delay: 0.24s; }
    .sidebar-submenu .sidebar-sublink:nth-child(7) { animation-delay: 0.27s; }

    .sidebar-footer { animation: sidebarFadeIn 0.5s var(--sb-spring) 0.35s forwards; opacity: 0; }
</style>

<script>
    function sidebarNav() {
        return {
            init() {
                // Handle sub-link clicks that switch tabs (SPA behavior)
                this.$el.querySelectorAll('.sidebar-sublink').forEach(link => {
                    link.addEventListener('click', (e) => {
                        const url = new URL(link.href, window.location.origin);

                        // SPA tab switch (same page, different tab)
                        if (url.pathname === window.location.pathname && url.searchParams.has('tab')) {
                            e.preventDefault();

                            // Update active state
                            const submenu = link.closest('.sidebar-submenu');
                            submenu.querySelectorAll('.sidebar-sublink').forEach(s => s.classList.remove('is-active'));
                            link.classList.add('is-active');

                            // Push URL and dispatch tab-changed
                            const newTab = url.searchParams.get('tab');
                            window.history.pushState({}, '', link.href);
                            window.dispatchEvent(new CustomEvent('tab-changed', { detail: newTab }));
                        }
                    });
                });

                // Listen for external tab sync events
                window.addEventListener('sync-sidebar-tab', (e) => {
                    const newTab = e.detail;
                    this.$el.querySelectorAll('.sidebar-submenu').forEach(submenu => {
                        const targetLink = Array.from(submenu.querySelectorAll('.sidebar-sublink')).find(link => {
                            const u = new URL(link.href, window.location.origin);
                            return u.pathname === window.location.pathname && u.searchParams.get('tab') === newTab;
                        });
                        if (targetLink && !targetLink.classList.contains('is-active')) {
                            submenu.querySelectorAll('.sidebar-sublink').forEach(s => s.classList.remove('is-active'));
                            targetLink.classList.add('is-active');
                        }
                    });
                });
            }
        };
    }
</script>
