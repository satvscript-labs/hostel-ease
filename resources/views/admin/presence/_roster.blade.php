{{-- Enrollment roster fragment (swapped wholesale by the Students|Staff tabs and
     pagination, §4.3). $roster is a paginator of Student OR Staff (by $tab);
     each carries ->presenceProfile. Rows are container-tiered (§4.9/4.11). --}}
@php
    use App\Enums\Presence\EnrollmentStatus;
    $ptype = $tab === 'staff' ? 'staff' : 'student';
@endphp

<div class="d-flex flex-column gap-2 stagger">
    @forelse($roster as $person)
        @php
            $profile = $person->presenceProfile;
            $status = $profile?->enrollment_status;
            $initial = mb_strtoupper(mb_substr(trim($person->name), 0, 1));
            if ($tab === 'staff') {
                $sub = $person->designation ?: __('Staff');
                $profileUrl = route('admin.staff.show', $person);
            } else {
                $room = $person->activeAssignment?->bed?->room;
                $sub = $room ? trim(($room->floor?->name ? $room->floor->name.' · ' : '').__('Room').' '.$room->room_number) : __('No room assigned');
                $profileUrl = route('admin.students.show', $person);
            }
        @endphp

        <div class="he-cq-wide pr-row py-2">
            {{-- Who --}}
            <div class="pr-row__who">
                <span class="pr-avatar">{{ $initial }}</span>
                <div style="min-width: 0;">
                    <div class="text-truncate">
                        <a href="{{ $profileUrl }}" class="fw-bold text-dark text-decoration-none">{{ $person->name }}</a>
                    </div>
                    <div class="small text-muted text-truncate">{{ $sub }}</div>
                </div>
            </div>

            {{-- Status --}}
            <div class="pr-row__status">
                @if(! $profile)
                    <span class="pr-pill pr-pill--none"><span class="pr-pill__dot"></span>{{ __('Not enrolled') }}</span>
                @elseif($status === EnrollmentStatus::Active)
                    <span class="pr-pill pr-pill--active"><span class="pr-pill__dot"></span>{{ __('Active') }}</span>
                    <span class="pr-uid">{{ $profile->device_user_id }}</span>
                @elseif($status === EnrollmentStatus::Pending)
                    <span class="pr-pill pr-pill--pending"><span class="pr-pill__dot"></span>{{ __('Pending') }}</span>
                @elseif($status === EnrollmentStatus::Failed)
                    <span class="pr-pill pr-pill--failed"><span class="pr-pill__dot"></span>{{ __('Failed') }}</span>
                @endif
            </div>

            {{-- Actions --}}
            <div class="pr-row__acts">
                @if(! $profile)
                    <form method="POST" action="{{ route('admin.presence.enroll') }}" class="m-0">
                        @csrf
                        <input type="hidden" name="person_type" value="{{ $ptype }}">
                        <input type="hidden" name="person_id" value="{{ $person->public_id }}">
                        <button class="btn btn-sm btn-premium rounded-pill fw-bold px-3 text-nowrap tactile-btn" style="min-height: 34px;">
                            <i class="fa-solid fa-user-plus me-1"></i>{{ __('Enroll') }}
                        </button>
                    </form>
                @else
                    @if($status === EnrollmentStatus::Pending || $status === EnrollmentStatus::Failed)
                        <form method="POST" action="{{ route('admin.presence.profiles.repush', $profile) }}" class="m-0">
                            @csrf
                            <button class="btn btn-sm btn-white border rounded-pill fw-bold px-3 text-nowrap tactile-btn" style="min-height: 34px;">
                                <i class="fa-solid fa-arrow-up-from-bracket me-1"></i>{{ $status === EnrollmentStatus::Failed ? __('Retry') : __('Re-push') }}
                            </button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('admin.presence.profiles.revoke', $profile) }}" class="m-0"
                          data-confirm="{{ __('Revoke gate access for :name? Their punch history is kept.', ['name' => $person->name]) }}">
                        @csrf @method('DELETE')
                        <button class="he-icon-btn is-danger" title="{{ __('Revoke access') }}" aria-label="{{ __('Revoke access') }}"><i class="fa-solid fa-user-slash"></i></button>
                    </form>
                @endif
            </div>
        </div>

        {{-- Phone card --}}
        <div class="he-cq-card py-2">
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="pr-avatar" style="width: 38px; height: 38px; font-size: 0.85rem;">{{ $initial }}</span>
                <div class="flex-grow-1" style="min-width: 0;">
                    <div class="text-truncate"><a href="{{ $profileUrl }}" class="fw-bold text-dark text-decoration-none">{{ $person->name }}</a></div>
                    <div class="small text-muted text-truncate">{{ $sub }}</div>
                </div>
                @if(! $profile)
                    <span class="pr-pill pr-pill--none flex-shrink-0"><span class="pr-pill__dot"></span>{{ __('Not enrolled') }}</span>
                @elseif($status === EnrollmentStatus::Active)
                    <span class="pr-pill pr-pill--active flex-shrink-0"><span class="pr-pill__dot"></span>{{ __('Active') }}</span>
                @elseif($status === EnrollmentStatus::Pending)
                    <span class="pr-pill pr-pill--pending flex-shrink-0"><span class="pr-pill__dot"></span>{{ __('Pending') }}</span>
                @elseif($status === EnrollmentStatus::Failed)
                    <span class="pr-pill pr-pill--failed flex-shrink-0"><span class="pr-pill__dot"></span>{{ __('Failed') }}</span>
                @endif
            </div>
            <div class="he-act-row">
                @if(! $profile)
                    <form method="POST" action="{{ route('admin.presence.enroll') }}" class="m-0 flex-grow-1">
                        @csrf
                        <input type="hidden" name="person_type" value="{{ $ptype }}">
                        <input type="hidden" name="person_id" value="{{ $person->public_id }}">
                        <button class="btn btn-premium rounded-pill fw-bold px-4 w-100" style="min-height: 44px;">
                            <i class="fa-solid fa-user-plus me-1"></i>{{ __('Enroll') }}
                        </button>
                    </form>
                @else
                    @if($profile->device_user_id)<span class="pr-uid me-auto">{{ $profile->device_user_id }}</span>@endif
                    <div class="he-act-right">
                        @if($status === EnrollmentStatus::Pending || $status === EnrollmentStatus::Failed)
                            <form method="POST" action="{{ route('admin.presence.profiles.repush', $profile) }}" class="m-0">
                                @csrf
                                <button class="he-icon-btn he-icon-btn--lg" title="{{ __('Re-push') }}" aria-label="{{ __('Re-push') }}"><i class="fa-solid fa-arrow-up-from-bracket"></i></button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('admin.presence.profiles.revoke', $profile) }}" class="m-0"
                              data-confirm="{{ __('Revoke gate access for :name? Their punch history is kept.', ['name' => $person->name]) }}">
                            @csrf @method('DELETE')
                            <button class="he-icon-btn he-icon-btn--lg is-danger" title="{{ __('Revoke access') }}" aria-label="{{ __('Revoke access') }}"><i class="fa-solid fa-user-slash"></i></button>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    @empty
        <x-he-empty-state icon="user-group"
            title="{{ $tab === 'staff' ? __('No active staff') : __('No active students') }}"
            subtitle="{{ __('People you add will appear here to enroll on the gate.') }}" />
    @endforelse
</div>

@if($roster->hasPages())
    <div class="mt-3">{{ $roster->links() }}</div>
@endif
