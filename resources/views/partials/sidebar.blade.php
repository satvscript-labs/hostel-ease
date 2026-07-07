@php($user = auth()->user())
<aside class="hsms-sidebar">
    <div class="d-flex align-items-center gap-2 px-3 py-3 border-bottom border-secondary-subtle">
        <img src="{{ asset('hsms-icon.svg') }}" alt="HSMS" style="width:30px;height:30px;border-radius:7px">
        <span class="brand fs-5">HSMS</span>
        <button type="button" class="btn btn-sm btn-dark sidebar-close ms-auto" data-sidebar-close aria-label="Close menu">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <style>
        /* Smooth iOS Easing */
        :root {
            --ios-spring: cubic-bezier(0.25, 1, 0.5, 1);
            --ios-bounce: cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        /* Nav Link Base Micro-animations */
        .hsms-sidebar .nav-link {
            transition: all 0.3s var(--ios-spring);
            border-radius: 0.5rem;
            position: relative;
        }

        /* Icon Micro-animations */
        .hsms-sidebar .nav-link i {
            transition: transform 0.4s var(--ios-bounce), color 0.3s ease;
        }
        .hsms-sidebar .nav-link:hover i {
            transform: scale(1.15) translateY(-1px);
        }

        /* Hover Effect for Main Links */
        .hsms-sidebar .nav-link:hover {
            transform: translateX(4px);
            background-color: rgba(var(--bs-primary-rgb), 0.04);
        }

        /* Active Main Link */
        .hsms-sidebar .nav-link.active.fw-bold {
            background-color: rgba(var(--bs-primary-rgb), 0.08);
            color: var(--bs-primary);
        }
        .hsms-sidebar .nav-link.active.fw-bold i {
            color: var(--bs-primary);
        }

        /* Sub Menu Container */
        .hsms-sub-menu { 
            margin-top: 0.25rem; 
            margin-bottom: 0.75rem; 
            padding-bottom: 0.25rem; 
            border-left: 2px solid rgba(var(--bs-secondary-rgb), 0.15); 
            margin-left: 1.5rem; 
            animation: slideDownFade 0.4s var(--ios-spring) forwards;
            transform-origin: top;
            position: relative;
        }

        /* Sub Menu Links */
        .hsms-sidebar .sub-link { 
            padding-left: 1.25rem; 
            font-size: 0.9rem; 
            color: var(--bs-secondary); 
            position: relative;
            margin-bottom: 0.15rem;
            opacity: 0;
            animation: slideInRight 0.4s var(--ios-spring) forwards;
        }
        
        /* Staggered Animation for sub-links */
        .hsms-sidebar .sub-link:nth-child(1) { animation-delay: 0.05s; }
        .hsms-sidebar .sub-link:nth-child(2) { animation-delay: 0.1s; }
        .hsms-sidebar .sub-link:nth-child(3) { animation-delay: 0.15s; }
        .hsms-sidebar .sub-link:nth-child(4) { animation-delay: 0.2s; }
        .hsms-sidebar .sub-link:nth-child(5) { animation-delay: 0.25s; }

        /* Sub Link Hover & Active */
        .hsms-sidebar .sub-link:hover { 
            color: var(--bs-primary); 
            background: transparent; 
            transform: translateX(4px);
        }
        .hsms-sidebar .sub-link.active { 
            color: var(--bs-primary); 
            font-weight: 600; 
        }
        
        /* Force override any global app.scss ::before rules */
        .hsms-sidebar .sub-link.active::before {
            display: none !important;
            content: none !important;
        }

        .hsms-sub-menu .active-indicator {
            position: absolute;
            left: -2px;
            width: 3px;
            background: var(--bs-primary);
            border-radius: 0 4px 4px 0;
            transition: top 0.4s var(--ios-spring), height 0.4s var(--ios-spring);
            z-index: 10;
        }

        /* Keyframes */
        @keyframes slideDownFade {
            from { opacity: 0; transform: scaleY(0.95); }
            to { opacity: 1; transform: scaleY(1); }
        }
        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(-10px); }
            to { opacity: 1; transform: translateX(0); }
        }
        @keyframes popIn {
            0% { transform: scaleY(0); }
            100% { transform: scaleY(1); }
        }
    </style>

    <nav class="nav flex-column flex-nowrap flex-grow-1 overflow-y-auto overflow-x-hidden py-2">
        @if($user->isSuperAdmin())
            <a class="nav-link {{ request()->routeIs('superadmin.dashboard') ? 'active' : '' }}" href="{{ route('superadmin.dashboard') }}">
                <i class="fa-solid fa-gauge-high"></i> {{ __('Dashboard') }}
            </a>
            <a class="nav-link {{ request()->routeIs('superadmin.hostels.*') ? 'active' : '' }}" href="{{ route('superadmin.hostels.index') }}">
                <i class="fa-solid fa-hotel"></i> {{ __('Hostels') }}
            </a>
            <a class="nav-link {{ request()->routeIs('superadmin.subscriptions.*') ? 'active' : '' }}" href="{{ route('superadmin.subscriptions.index') }}">
                <i class="fa-solid fa-receipt"></i> {{ __('Subscriptions') }}
            </a>
            <a class="nav-link {{ request()->routeIs('superadmin.activity') ? 'active' : '' }}" href="{{ route('superadmin.activity') }}"><i class="fa-solid fa-list-check"></i> {{ __('Activity Logs') }}</a>
            <a class="nav-link {{ request()->routeIs('superadmin.backups.*') ? 'active' : '' }}" href="{{ route('superadmin.backups.index') }}"><i class="fa-solid fa-database"></i> {{ __('Backups') }}</a>
        @else
            <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">
                <i class="fa-solid fa-gauge-high"></i> <span class="ms-2">{{ __('Dashboard') }}</span>
            </a>
            
            <a class="nav-link {{ request()->routeIs('admin.property.*') ? 'active' : '' }}" href="{{ route('admin.property.index') }}">
                <i class="fa-solid fa-building"></i> <span class="ms-2">{{ __('Property Board') }}</span>
            </a>

            <!-- People Menu -->
            @php($peopleActive = request()->routeIs('admin.students.*', 'admin.assignments.*', 'admin.registrations.*'))
            <a class="nav-link {{ $peopleActive ? 'active fw-bold' : '' }}" href="{{ route('admin.students.index') }}">
                <i class="fa-solid fa-users"></i> <span class="ms-2">{{ __('People') }}</span>
            </a>
            @if($peopleActive)
            <div class="hsms-sub-menu">
                <a class="nav-link sub-link {{ request()->routeIs('admin.students.*') ? 'active' : '' }}" href="{{ route('admin.students.index') }}">{{ __('Students') }}</a>
                <a class="nav-link sub-link {{ request()->routeIs('admin.registrations.*') ? 'active' : '' }}" href="{{ route('admin.registrations.index') }}">{{ __('Registrations') }}</a>
            </div>
            @endif

            <!-- Front Desk Menu -->
            @php($deskActive = request()->routeIs('admin.frontdesk.*', 'admin.visitors.*', 'admin.complaints.*'))
            <a class="nav-link {{ $deskActive ? 'active fw-bold' : '' }}" href="{{ route('admin.frontdesk.index') }}">
                <i class="fa-solid fa-bell-concierge"></i> <span class="ms-2">{{ __('Front Desk') }}</span>
            </a>
            @if($deskActive)
            <div class="hsms-sub-menu">
                <a class="nav-link sub-link {{ request()->routeIs('admin.frontdesk.*') && request('tab', 'visitors') === 'visitors' ? 'active' : '' }}" href="{{ route('admin.frontdesk.index', ['tab' => 'visitors']) }}">{{ __('Visitors') }}</a>
                <a class="nav-link sub-link {{ request()->routeIs('admin.frontdesk.*') && request('tab') === 'complaints' ? 'active' : '' }}" href="{{ route('admin.frontdesk.index', ['tab' => 'complaints']) }}">{{ __('Complaints') }}</a>
            </div>
            @endif

            <!-- Finance Menu -->
            @php($financeActive = request()->routeIs('admin.finance.*', 'admin.pocket-money.*', 'admin.payment-modes.*', 'admin.expenses.*'))
            <a class="nav-link {{ $financeActive ? 'active fw-bold' : '' }}" href="{{ route('admin.finance.index') }}">
                <i class="fa-solid fa-chart-pie"></i> <span class="ms-2">{{ __('Finance') }}</span>
            </a>
            @if($financeActive)
            <div class="hsms-sub-menu">
                <a class="nav-link sub-link {{ request()->routeIs('admin.finance.*') && request('tab') !== 'transactions' ? 'active' : '' }}" href="{{ route('admin.finance.index', ['tab' => 'invoices']) }}">{{ __('Invoices & Dues') }}</a>
                <a class="nav-link sub-link {{ request()->routeIs('admin.finance.*') && request('tab') === 'transactions' ? 'active' : '' }}" href="{{ route('admin.finance.index', ['tab' => 'transactions']) }}">{{ __('Transactions') }}</a>
                <a class="nav-link sub-link {{ request()->routeIs('admin.expenses.*') ? 'active' : '' }}" href="{{ route('admin.expenses.index') }}">{{ __('Expenses') }}</a>
                <a class="nav-link sub-link {{ request()->routeIs('admin.ac-bills.*') ? 'active' : '' }}" href="{{ route('admin.ac-bills.index') }}">{{ __('AC Bills') }}</a>
                <a class="nav-link sub-link {{ request()->routeIs('admin.pocket-money.*') ? 'active' : '' }}" href="{{ route('admin.pocket-money.index') }}">{{ __('Pocket Money') }}</a>
                <a class="nav-link sub-link {{ request()->routeIs('admin.payment-modes.*') ? 'active' : '' }}" href="{{ route('admin.payment-modes.index') }}">{{ __('Payment Modes') }}</a>
            </div>
            @endif
            
            <!-- Operations Menu -->
            @php($opsActive = request()->routeIs('admin.staff.*'))
            <a class="nav-link {{ $opsActive ? 'active fw-bold' : '' }}" href="{{ route('admin.staff.index') }}">
                <i class="fa-solid fa-briefcase"></i> <span class="ms-2">{{ __('Staff & Ops') }}</span>
            </a>
            @if($opsActive)
            <div class="hsms-sub-menu">
                <a class="nav-link sub-link {{ request()->routeIs('admin.staff.*') && request('tab', 'directory') === 'directory' ? 'active' : '' }}" href="{{ route('admin.staff.index', ['tab' => 'directory']) }}">{{ __('Staff Directory') }}</a>
                <a class="nav-link sub-link {{ request()->routeIs('admin.staff.*') && request('tab') === 'attendance' ? 'active' : '' }}" href="{{ route('admin.staff.index', ['tab' => 'attendance']) }}">{{ __('Attendance') }}</a>
            </div>
            @endif

            <!-- Insights & Settings Menu -->
            @php($settingsActive = request()->routeIs('admin.reports.*', 'admin.users.*', 'admin.billing'))
            <a class="nav-link {{ $settingsActive ? 'active fw-bold' : '' }}" href="{{ route('admin.reports.index') }}">
                <i class="fa-solid fa-gear"></i> <span class="ms-2">{{ __('Settings & Tools') }}</span>
            </a>
            @if($settingsActive)
            <div class="hsms-sub-menu">
                <a class="nav-link sub-link {{ request()->routeIs('admin.reports.*') ? 'active' : '' }}" href="{{ route('admin.reports.index') }}">{{ __('Reports') }}</a>
                <a class="nav-link sub-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">{{ __('Users & Roles') }}</a>
                <a class="nav-link sub-link {{ request()->routeIs('admin.billing') ? 'active' : '' }}" href="{{ route('admin.billing') }}">{{ __('Subscription') }}</a>
            </div>
            @endif
        @endif
    </nav>

    <div class="px-3 py-2 border-top border-secondary-subtle small text-secondary">
        v1.0 · {{ $user->isSuperAdmin() ? 'Super Admin' : optional($user->hostel)->name }}
    </div>
</aside>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const updateIndicator = (menu, activeLink, animate = true) => {
            let indicator = menu.querySelector('.active-indicator');
            if (!indicator) {
                indicator = document.createElement('div');
                indicator.className = 'active-indicator';
                if (!animate) indicator.style.transition = 'none';
                menu.appendChild(indicator);
            }
            if (animate) indicator.style.transition = 'top 0.4s cubic-bezier(0.25, 1, 0.5, 1), height 0.4s cubic-bezier(0.25, 1, 0.5, 1)';
            
            // Calculate relative to the menu container
            const linkRect = activeLink.getBoundingClientRect();
            const menuRect = menu.getBoundingClientRect();
            const relativeTop = linkRect.top - menuRect.top;
            
            indicator.style.top = (relativeTop + linkRect.height * 0.15) + 'px';
            indicator.style.height = (linkRect.height * 0.7) + 'px';
        };

        // Determine if we should skip cascade
        const shouldSkipCascade = sessionStorage.getItem('hsms_skip_cascade') === 'true';
        if (shouldSkipCascade) {
            sessionStorage.removeItem('hsms_skip_cascade');
        }

        document.querySelectorAll('.hsms-sub-menu').forEach(menu => {
            // Disable animations if returning from a cross-page sub-link click
            if (shouldSkipCascade) {
                menu.style.animation = 'none';
                menu.querySelectorAll('.sub-link').forEach(link => {
                    link.style.animation = 'none';
                    link.style.opacity = '1';
                    link.style.transform = 'none';
                });
            }

            // Position indicator on load
            const active = menu.querySelector('.sub-link.active');
            if (active) {
                // small delay to let CSS render
                setTimeout(() => updateIndicator(menu, active, false), 50);
            }

            // Listen for external tab sync requests (e.g. from Alpine components switching tabs)
            window.addEventListener('sync-sidebar-tab', (e) => {
                const newTab = e.detail;
                const targetLink = Array.from(menu.querySelectorAll('.sub-link')).find(link => {
                    const url = new URL(link.href, window.location.origin);
                    return url.pathname === window.location.pathname && url.searchParams.get('tab') === newTab;
                });
                
                if (targetLink && !targetLink.classList.contains('active')) {
                    menu.querySelectorAll('.sub-link').forEach(sib => sib.classList.remove('active'));
                    targetLink.classList.add('active');
                    updateIndicator(menu, targetLink, true);
                }
            });
            
            // Handle clicks
            menu.querySelectorAll('.sub-link').forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    if (link.classList.contains('active')) return;

                    // Animate indicator visually immediately
                    menu.querySelectorAll('.sub-link').forEach(sib => sib.classList.remove('active'));
                    link.classList.add('active');
                    updateIndicator(menu, link, true);
                    
                    // Mark session to skip cascade on next load (for cross-page navigation)
                    sessionStorage.setItem('hsms_skip_cascade', 'true');

                    const url = new URL(link.href);
                    if (url.pathname === window.location.pathname && url.searchParams.has('tab')) {
                        // SPA Tab Navigation
                        const newTab = url.searchParams.get('tab');
                        window.history.pushState({}, '', link.href);
                        window.dispatchEvent(new CustomEvent('tab-changed', { detail: newTab }));
                    } else {
                        // Cross-page navigation: wait for the smooth slide to finish, then navigate
                        setTimeout(() => {
                            window.location.href = link.href;
                        }, 300);
                    }
                });
            });
        });
    });
</script>
