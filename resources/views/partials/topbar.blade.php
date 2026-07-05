@php($user = auth()->user())
<header class="hsms-topbar d-flex align-items-center gap-3 px-3 px-lg-4 py-2">
    <button class="btn btn-light d-lg-none" data-sidebar-toggle type="button">
        <i class="fa-solid fa-bars"></i>
    </button>

    <div class="flex-grow-1 position-relative" style="max-width: 360px;"
         data-search-url="{{ route('search') }}">
        <div class="input-group input-group-sm">
            <span class="input-group-text bg-white border-end-0"><i class="fa-solid fa-magnifying-glass text-secondary"></i></span>
            <input type="search" id="global-search" class="form-control border-start-0"
                   placeholder="{{ $user->isSuperAdmin() ? __('Search hostels…') : __('Search students, rooms, beds…') }}"
                   autocomplete="off">
        </div>
        <div id="search-results" class="dropdown-menu w-100 shadow" style="max-height:380px;overflow:auto;"></div>
    </div>

    {{-- Branch switcher (multi-branch hostel admins) --}}
    @if($user->isHostelAdmin())
        @php($branches = $user->hostels)
        @if($branches->count() > 1)
            @php($activeId = \App\Support\Tenant::id())
            @php($activeBranch = $branches->firstWhere('id', $activeId))
            <div class="dropdown">
                <button class="btn btn-light btn-sm dropdown-toggle" data-bs-toggle="dropdown" title="Branch">
                    <i class="fa-solid fa-code-branch text-primary"></i>
                    <span class="d-none d-md-inline">{{ \Illuminate\Support\Str::limit(optional($activeBranch)->name ?? 'Branch', 18) }}</span>
                </button>
                <ul class="dropdown-menu">
                    <li><h6 class="dropdown-header">Switch branch</h6></li>
                    @foreach($branches as $b)
                        <li><a class="dropdown-item {{ $activeId == $b->id ? 'active' : '' }}" href="{{ route('branch.switch', $b) }}">
                            <i class="fa-solid fa-building me-2"></i>{{ $b->name }}
                        </a></li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endif

    {{-- Language switcher --}}
    <div class="dropdown">
        <button class="btn btn-light btn-sm dropdown-toggle" data-bs-toggle="dropdown" title="Language">
            <i class="fa-solid fa-globe"></i>
            <span class="d-none d-md-inline">{{ config('app.available_locales')[app()->getLocale()] ?? 'EN' }}</span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            @foreach(config('app.available_locales') as $code => $label)
                <li>
                    <a class="dropdown-item {{ app()->getLocale() === $code ? 'active' : '' }}" href="{{ route('locale.switch', $code) }}">{{ $label }}</a>
                </li>
            @endforeach
        </ul>
    </div>

    <div class="dropdown">
        <a href="#" class="position-relative text-secondary" title="Notifications" data-bs-toggle="dropdown" data-bs-auto-close="outside">
            <i class="fa-solid fa-bell fs-5"></i>
            @if(($navNotificationCount ?? 0) > 0)
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:.6rem;">{{ $navNotificationCount }}</span>
            @endif
        </a>
        <div class="dropdown-menu dropdown-menu-end p-0" style="width:320px;max-height:420px;overflow:auto;">
            <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                <strong class="small">{{ __('Notifications') }}</strong>
                <a href="{{ route('notifications.index') }}" class="small text-decoration-none">{{ __('View all') }}</a>
            </div>
            @forelse($navNotifications ?? [] as $n)
                <div class="dropdown-item d-flex gap-2 py-2 border-bottom text-wrap" style="white-space:normal;">
                    <i class="fa-solid fa-circle text-{{ $n->level }} mt-1" style="font-size:.5rem;"></i>
                    <div class="flex-grow-1">
                        <div class="small fw-semibold">{{ $n->title }}</div>
                        <div class="small text-muted">{{ $n->message }}</div>
                        <div class="text-muted" style="font-size:.7rem;">{{ $n->created_at->diffForHumans() }}</div>
                    </div>
                </div>
            @empty
                <div class="px-3 py-4 text-center text-muted small">You're all caught up 🎉</div>
            @endforelse
        </div>
    </div>

    <div class="dropdown">
        <button class="btn btn-light d-flex align-items-center gap-2 dropdown-toggle" data-bs-toggle="dropdown">
            <span class="d-none d-sm-inline">{{ $user->name }}</span>
            <i class="fa-solid fa-circle-user fs-5"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><span class="dropdown-item-text small text-muted">{{ hsms_phone($user->mobile) }}</span></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="{{ route('profile.password') }}"><i class="fa-solid fa-key me-2"></i>{{ __('Change Password') }}</a></li>
            <li>
                <button class="dropdown-item text-danger" type="button"
                        onclick="document.getElementById('logout-form').submit()">
                    <i class="fa-solid fa-right-from-bracket me-2"></i>{{ __('Logout') }}
                </button>
            </li>
        </ul>
    </div>
</header>
