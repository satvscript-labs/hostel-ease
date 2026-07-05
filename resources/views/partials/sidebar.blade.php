@php($user = auth()->user())
<aside class="hsms-sidebar">
    <div class="d-flex align-items-center gap-2 px-3 py-3 border-bottom border-secondary-subtle">
        <img src="{{ asset('hsms-icon.svg') }}" alt="HSMS" style="width:30px;height:30px;border-radius:7px">
        <span class="brand fs-5">HSMS</span>
        <button type="button" class="btn btn-sm btn-dark sidebar-close ms-auto" data-sidebar-close aria-label="Close menu">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

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
                <i class="fa-solid fa-gauge-high"></i> {{ __('Dashboard') }}
            </a>
            <div class="text-uppercase small text-secondary px-3 mt-3 mb-1">{{ __('Property') }}</div>
            <a class="nav-link {{ request()->routeIs('admin.floors.*') ? 'active' : '' }}" href="{{ route('admin.floors.index') }}"><i class="fa-solid fa-layer-group"></i> {{ __('Floors') }}</a>
            <a class="nav-link {{ request()->routeIs('admin.rooms.*') ? 'active' : '' }}" href="{{ route('admin.rooms.index') }}"><i class="fa-solid fa-door-open"></i> {{ __('Rooms') }}</a>
            <a class="nav-link {{ request()->routeIs('admin.beds.*') ? 'active' : '' }}" href="{{ route('admin.beds.layout') }}"><i class="fa-solid fa-bed"></i> {{ __('Beds Layout') }}</a>
            <a class="nav-link {{ request()->routeIs('admin.vacancy.*') ? 'active' : '' }}" href="{{ route('admin.vacancy.index') }}"><i class="fa-solid fa-square-poll-vertical"></i> {{ __('Vacancy') }}</a>
            <div class="text-uppercase small text-secondary px-3 mt-3 mb-1">{{ __('People') }}</div>
            <a class="nav-link {{ request()->routeIs('admin.students.*') ? 'active' : '' }}" href="{{ route('admin.students.index') }}"><i class="fa-solid fa-users"></i> {{ __('Students') }}</a>
            <a class="nav-link {{ request()->routeIs('admin.assignments.*') ? 'active' : '' }}" href="{{ route('admin.assignments.index') }}"><i class="fa-solid fa-bed-pulse"></i> {{ __('Bed Assignment') }}</a>
            <a class="nav-link {{ request()->routeIs('admin.visitors.*') ? 'active' : '' }}" href="{{ route('admin.visitors.index') }}"><i class="fa-solid fa-door-closed"></i> {{ __('Visitors') }}</a>
            <a class="nav-link {{ request()->routeIs('admin.complaints.*') ? 'active' : '' }}" href="{{ route('admin.complaints.index') }}"><i class="fa-solid fa-headset"></i> {{ __('Complaints') }}</a>
            <a class="nav-link {{ request()->routeIs('admin.staff.*') ? 'active' : '' }}" href="{{ route('admin.staff.index') }}"><i class="fa-solid fa-id-badge"></i> {{ __('Staff') }}</a>
            <a class="nav-link {{ request()->routeIs('admin.registrations.*') ? 'active' : '' }}" href="{{ route('admin.registrations.index') }}"><i class="fa-solid fa-user-check"></i> {{ __('Registrations') }}</a>
            <div class="text-uppercase small text-secondary px-3 mt-3 mb-1">{{ __('Finance') }}</div>
            <a class="nav-link {{ request()->routeIs('admin.payments.*') ? 'active' : '' }}" href="{{ route('admin.payments.index') }}"><i class="fa-solid fa-money-bill-wave"></i> {{ __('Fees') }}</a>
            <a class="nav-link {{ request()->routeIs('admin.semester-fees.*') ? 'active' : '' }}" href="{{ route('admin.semester-fees.index') }}"><i class="fa-solid fa-graduation-cap"></i> {{ __('Semester Fees') }}</a>
            <a class="nav-link {{ request()->routeIs('admin.monthly-rents.*') ? 'active' : '' }}" href="{{ route('admin.monthly-rents.index') }}"><i class="fa-solid fa-calendar-day"></i> {{ __('Monthly Rent') }}</a>
            <a class="nav-link {{ request()->routeIs('admin.ac-bills.*') ? 'active' : '' }}" href="{{ route('admin.ac-bills.index') }}"><i class="fa-solid fa-snowflake"></i> {{ __('AC Bills') }}</a>
            <a class="nav-link {{ request()->routeIs('admin.ledger.*') ? 'active' : '' }}" href="{{ route('admin.ledger.index') }}"><i class="fa-solid fa-book"></i> {{ __('Ledger') }}</a>
            <a class="nav-link {{ request()->routeIs('admin.pocket-money.*') ? 'active' : '' }}" href="{{ route('admin.pocket-money.index') }}"><i class="fa-solid fa-wallet"></i> {{ __('Pocket Money') }}</a>
            <a class="nav-link {{ request()->routeIs('admin.expenses.*') ? 'active' : '' }}" href="{{ route('admin.expenses.index') }}"><i class="fa-solid fa-money-bill-trend-up"></i> {{ __('Expenses') }}</a>
            <a class="nav-link {{ request()->routeIs('admin.payment-modes.*') ? 'active' : '' }}" href="{{ route('admin.payment-modes.index') }}"><i class="fa-solid fa-sliders"></i> {{ __('Payment Modes') }}</a>
            <div class="text-uppercase small text-secondary px-3 mt-3 mb-1">{{ __('Insights') }}</div>
            <a class="nav-link {{ request()->routeIs('admin.reports.*') ? 'active' : '' }}" href="{{ route('admin.reports.index') }}"><i class="fa-solid fa-chart-pie"></i> {{ __('Reports') }}</a>
            <div class="text-uppercase small text-secondary px-3 mt-3 mb-1">{{ __('Settings') }}</div>
            <a class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}"><i class="fa-solid fa-user-gear"></i> {{ __('Users & Roles') }}</a>
            <a class="nav-link {{ request()->routeIs('admin.billing') ? 'active' : '' }}" href="{{ route('admin.billing') }}"><i class="fa-solid fa-credit-card"></i> {{ __('Subscription') }}</a>
        @endif
    </nav>

    <div class="px-3 py-2 border-top border-secondary-subtle small text-secondary">
        v1.0 · {{ $user->isSuperAdmin() ? 'Super Admin' : optional($user->hostel)->name }}
    </div>
</aside>
