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
        .hsms-sidebar .nav-link[data-bs-toggle="collapse"] .fa-chevron-down { transition: transform 0.2s; }
        .hsms-sidebar .nav-link[data-bs-toggle="collapse"]:not(.collapsed) .fa-chevron-down { transform: rotate(180deg); }
        .hsms-sidebar .collapse .nav-link { padding-left: 2.5rem; font-size: 0.95rem; }
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
            <a class="nav-link {{ request()->routeIs('superadmin.admins.*') ? 'active' : '' }}" href="{{ route('superadmin.admins.index') }}">
                <i class="fa-solid fa-user-shield"></i> {{ __('Admins') }}
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
            <a class="nav-link {{ $peopleActive ? '' : 'collapsed' }}" data-bs-toggle="collapse" href="#peopleMenu" role="button" aria-expanded="{{ $peopleActive ? 'true' : 'false' }}">
                <i class="fa-solid fa-users"></i> <span class="ms-2">{{ __('People') }}</span>
                <i class="fa-solid fa-chevron-down ms-auto" style="font-size: 0.8em;"></i>
            </a>
            <div class="collapse {{ $peopleActive ? 'show' : '' }}" id="peopleMenu">
                <a class="nav-link {{ request()->routeIs('admin.students.*') ? 'active' : '' }}" href="{{ route('admin.students.index') }}">{{ __('Students') }}</a>
                <a class="nav-link {{ request()->routeIs('admin.assignments.*') ? 'active' : '' }}" href="{{ route('admin.assignments.index') }}">{{ __('Bed Assignment') }}</a>
                <a class="nav-link {{ request()->routeIs('admin.registrations.*') ? 'active' : '' }}" href="{{ route('admin.registrations.index') }}">{{ __('Registrations') }}</a>
            </div>

            <!-- Front Desk Menu -->
            @php($deskActive = request()->routeIs('admin.frontdesk.*', 'admin.visitors.*', 'admin.complaints.*'))
            <a class="nav-link {{ $deskActive ? '' : 'collapsed' }}" data-bs-toggle="collapse" href="#deskMenu" role="button" aria-expanded="{{ $deskActive ? 'true' : 'false' }}">
                <i class="fa-solid fa-bell-concierge"></i> <span class="ms-2">{{ __('Front Desk') }}</span>
                <i class="fa-solid fa-chevron-down ms-auto" style="font-size: 0.8em;"></i>
            </a>
            <div class="collapse {{ $deskActive ? 'show' : '' }}" id="deskMenu">
                <a class="nav-link {{ request()->routeIs('admin.frontdesk.*') && request('tab', 'visitors') === 'visitors' ? 'active' : '' }}" href="{{ route('admin.frontdesk.index', ['tab' => 'visitors']) }}">{{ __('Visitors') }}</a>
                <a class="nav-link {{ request()->routeIs('admin.frontdesk.*') && request('tab') === 'complaints' ? 'active' : '' }}" href="{{ route('admin.frontdesk.index', ['tab' => 'complaints']) }}">{{ __('Complaints') }}</a>
            </div>

            <!-- Finance Menu -->
            @php($financeActive = request()->routeIs('admin.finance.*', 'admin.pocket-money.*', 'admin.payment-modes.*', 'admin.expenses.*'))
            <a class="nav-link {{ $financeActive ? '' : 'collapsed' }}" data-bs-toggle="collapse" href="#financeMenu" role="button" aria-expanded="{{ $financeActive ? 'true' : 'false' }}">
                <i class="fa-solid fa-chart-pie"></i> <span class="ms-2">{{ __('Finance') }}</span>
                <i class="fa-solid fa-chevron-down ms-auto" style="font-size: 0.8em;"></i>
            </a>
            <div class="collapse {{ $financeActive ? 'show' : '' }}" id="financeMenu">
                <a class="nav-link {{ request()->routeIs('admin.finance.*') && request('tab') !== 'transactions' ? 'active' : '' }}" href="{{ route('admin.finance.index', ['tab' => 'invoices']) }}">{{ __('Invoices & Dues') }}</a>
                <a class="nav-link {{ request()->routeIs('admin.finance.*') && request('tab') === 'transactions' ? 'active' : '' }}" href="{{ route('admin.finance.index', ['tab' => 'transactions']) }}">{{ __('Transactions') }}</a>
                <a class="nav-link {{ request()->routeIs('admin.expenses.*') ? 'active' : '' }}" href="{{ route('admin.expenses.index') }}">{{ __('Expenses') }}</a>
                <a class="nav-link {{ request()->routeIs('admin.pocket-money.*') ? 'active' : '' }}" href="{{ route('admin.pocket-money.index') }}">{{ __('Pocket Money') }}</a>
                <a class="nav-link {{ request()->routeIs('admin.payment-modes.*') ? 'active' : '' }}" href="{{ route('admin.payment-modes.index') }}">{{ __('Payment Modes') }}</a>
            </div>
            
            <!-- Operations Menu -->
            @php($opsActive = request()->routeIs('admin.staff.*'))
            <a class="nav-link {{ $opsActive ? '' : 'collapsed' }}" data-bs-toggle="collapse" href="#opsMenu" role="button" aria-expanded="{{ $opsActive ? 'true' : 'false' }}">
                <i class="fa-solid fa-briefcase"></i> <span class="ms-2">{{ __('Staff & Ops') }}</span>
                <i class="fa-solid fa-chevron-down ms-auto" style="font-size: 0.8em;"></i>
            </a>
            <div class="collapse {{ $opsActive ? 'show' : '' }}" id="opsMenu">
                <a class="nav-link {{ request()->routeIs('admin.staff.*') && request('tab', 'directory') === 'directory' ? 'active' : '' }}" href="{{ route('admin.staff.index', ['tab' => 'directory']) }}">{{ __('Staff Directory') }}</a>
                <a class="nav-link {{ request()->routeIs('admin.staff.*') && request('tab') === 'attendance' ? 'active' : '' }}" href="{{ route('admin.staff.index', ['tab' => 'attendance']) }}">{{ __('Attendance') }}</a>
            </div>

            <!-- Insights & Settings Menu -->
            @php($settingsActive = request()->routeIs('admin.reports.*', 'admin.users.*', 'admin.billing'))
            <a class="nav-link {{ $settingsActive ? '' : 'collapsed' }}" data-bs-toggle="collapse" href="#settingsMenu" role="button" aria-expanded="{{ $settingsActive ? 'true' : 'false' }}">
                <i class="fa-solid fa-gear"></i> <span class="ms-2">{{ __('Settings & Tools') }}</span>
                <i class="fa-solid fa-chevron-down ms-auto" style="font-size: 0.8em;"></i>
            </a>
            <div class="collapse {{ $settingsActive ? 'show' : '' }}" id="settingsMenu">
                <a class="nav-link {{ request()->routeIs('admin.reports.*') ? 'active' : '' }}" href="{{ route('admin.reports.index') }}">{{ __('Reports') }}</a>
                <a class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">{{ __('Users & Roles') }}</a>
                <a class="nav-link {{ request()->routeIs('admin.billing') ? 'active' : '' }}" href="{{ route('admin.billing') }}">{{ __('Subscription') }}</a>
            </div>
        @endif
    </nav>

    <div class="px-3 py-2 border-top border-secondary-subtle small text-secondary">
        v1.0 · {{ $user->isSuperAdmin() ? 'Super Admin' : optional($user->hostel)->name }}
    </div>
</aside>
