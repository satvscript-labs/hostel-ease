@php($user = auth()->user())
<header class="hsms-topbar d-flex align-items-center gap-3">
    <!-- Mobile Sidebar Toggle -->
    <button class="btn btn-light rounded-circle shadow-sm d-lg-none d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;" data-sidebar-toggle type="button">
        <i class="fa-solid fa-bars text-secondary"></i>
    </button>

    <!-- Global Search -->
    <div class="flex-grow-1 position-relative" style="max-width: 400px;" data-search-url="{{ route('search') }}">
        <div class="input-group search-group shadow-sm" style="border-radius: 50px; overflow: hidden; background: #f8fafc; border: 1px solid rgba(0,0,0,0.05); transition: all 0.3s ease;">
            <span class="input-group-text bg-transparent border-0 pe-1">
                <i class="fa-solid fa-magnifying-glass text-secondary"></i>
            </span>
            <input type="search" id="global-search" class="form-control bg-transparent border-0 shadow-none ps-2"
                   placeholder="{{ $user->isSuperAdmin() ? __('Search hostels…') : __('Search students, rooms, beds…') }}"
                   autocomplete="off" style="font-size: 0.9rem;">
        </div>
        <div id="search-results" class="dropdown-menu w-100 shadow-lg border-0 rounded-4 mt-2" style="max-height: 400px; overflow-y: auto;"></div>
    </div>
    
    <style>
        .search-group:focus-within {
            background: #ffffff !important;
            border-color: var(--bs-primary) !important;
            box-shadow: 0 0 0 4px rgba(var(--bs-primary-rgb), 0.1) !important;
        }
        .topbar-icon-btn {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: #f8fafc;
            color: #64748b;
            border: 1px solid transparent;
            transition: all 0.2s ease;
        }
        .topbar-icon-btn:hover, .topbar-icon-btn[aria-expanded="true"] {
            background: #ffffff;
            color: var(--bs-primary);
            border-color: rgba(var(--bs-primary-rgb), 0.2);
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            transform: translateY(-1px);
        }
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--bs-primary), #6366f1);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1rem;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.2s ease;
        }
        .user-avatar:hover, .user-avatar[aria-expanded="true"] {
            box-shadow: 0 0 0 4px rgba(var(--bs-primary-rgb), 0.15);
        }
    </style>

    <!-- Right Side Actions -->
    <div class="d-flex align-items-center gap-2 ms-auto">

        {{-- Branch switcher (multi-branch hostel admins) --}}
        @if($user->isHostelAdmin())
            @php($branches = $user->hostels)
            @if($branches->count() > 1)
                @php($activeId = \App\Support\Tenant::id())
                @php($activeBranch = $branches->firstWhere('id', $activeId))
                <div class="dropdown">
                    <button class="topbar-icon-btn" data-bs-toggle="dropdown" title="{{ __('Switch Branch') }}">
                        <i class="fa-solid fa-code-branch"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-4 mt-2 py-2">
                        <li>
                            <h6 class="dropdown-header text-uppercase small fw-bold text-primary">{{ __('Current Branch') }}</h6>
                        </li>
                        <li>
                            <div class="px-3 py-1 fw-bold text-dark mb-2">{{ \Illuminate\Support\Str::limit(optional($activeBranch)->name ?? 'Branch', 20) }}</div>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        @foreach($branches as $b)
                            @if($activeId != $b->id)
                                <li>
                                    <a class="dropdown-item py-2 d-flex align-items-center gap-2" href="{{ route('branch.switch', $b) }}">
                                        <div class="bg-light rounded d-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">
                                            <i class="fa-solid fa-building text-secondary" style="font-size: 0.8rem;"></i>
                                        </div>
                                        {{ $b->name }}
                                    </a>
                                </li>
                            @endif
                        @endforeach
                    </ul>
                </div>
            @endif
        @endif

        {{-- Language switcher --}}
        <div class="dropdown">
            <button class="topbar-icon-btn" data-bs-toggle="dropdown" title="{{ __('Language') }}">
                <span class="fw-bold" style="font-size: 0.85rem;">{{ strtoupper(app()->getLocale()) }}</span>
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
            <button class="topbar-icon-btn position-relative" title="{{ __('Notifications') }}" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                <i class="fa-regular fa-bell"></i>
                @if(($navNotificationCount ?? 0) > 0)
                    <span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle" style="margin-top: 5px; margin-left: -5px;">
                        <span class="visually-hidden">New alerts</span>
                    </span>
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
            <div class="user-avatar" data-bs-toggle="dropdown" title="{{ $user->name }}">
                {{ strtoupper(substr($user->name, 0, 1)) }}
            </div>
            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-4 mt-2 p-2" style="min-width: 240px;">
                <li class="px-3 py-2 mb-2 border-bottom">
                    <div class="fw-bold text-dark text-truncate">{{ $user->name }}</div>
                    <div class="small text-muted">{{ hostelease_phone($user->mobile) }}</div>
                </li>
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
