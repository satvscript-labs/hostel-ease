{{-- Board list rows (fragment content, swapped by filters/paging and the live
     poll). Shared by the Students and Staff boards via $type. Three tiers
     (§4.9/4.11): one-line grid ≥880, two-line reflow 640–880, iOS card <640. --}}
@php
    use App\Enums\Presence\PresenceState;
    // Short duration ("2h 40m" / "3d 4h" / "45m"). Mirrored in the JS ticker so
    // live updates match the server render.
    $fmtDur = function ($since) {
        if (! $since) return null;
        $s = abs(now()->diffInSeconds($since));
        $d = intdiv($s, 86400); $h = intdiv($s % 86400, 3600); $m = intdiv($s % 3600, 60);
        if ($d > 0) return $d.'d '.$h.'h';
        if ($h > 0) return $h.'h '.$m.'m';
        return max(1, $m).'m';
    };
@endphp

<div class="d-flex flex-column gap-2 stagger">
    @forelse($profiles as $profile)
        @continue(! $profile->presenceable)
        @php
            $person = $profile->presenceable;
            $state = $profile->state;
            $since = $profile->state_changed_at;
            $dur = $fmtDur($since);
            $stale = $profile->has_missed_punch;
            $initial = mb_strtoupper(mb_substr(trim($person->name), 0, 1));

            if ($type === 'staff') {
                $sub = $person->designation ?: __('Staff');
                $profileUrl = route('admin.staff.show', $person);
            } else {
                $room = $person->activeAssignment?->bed?->room;
                $sub = $room ? (($room->floor?->name ? $room->floor->name.' · ' : '').__('Room').' '.$room->room_number) : __('No room');
                $profileUrl = route('admin.students.show', $person);
            }

            $pill = match ($state) {
                PresenceState::In => 'in', PresenceState::Out => 'out', default => 'unknown',
            };
            $lp = $profile->lastPunch;
        @endphp

        {{-- Wide / tablet-reflow row --}}
        <div class="card shadow-sm rounded-4 he-cq-wide pb-card {{ $state === PresenceState::Out ? 'is-out' : '' }}">
            <div class="card-body p-3 p-lg-4 pb-row" style="display: grid;">
                <div class="pb-row__id">
                    <span class="pb-avatar">{{ $initial }}</span>
                    <div style="min-width: 0;">
                        <div class="text-truncate">
                            <a href="{{ $profileUrl }}" class="fw-bold text-dark text-decoration-none">{{ $person->name }}</a>
                            @if($stale)<span class="pb-stale" title="{{ __('A scan was likely missed') }}"><i class="fa-solid fa-triangle-exclamation"></i>{{ __('check') }}</span>@endif
                        </div>
                        <div class="small text-muted text-truncate">{{ $sub }}</div>
                    </div>
                </div>

                <div class="pb-row__state">
                    <span class="pb-pill pb-pill--{{ $pill }}"><span class="pb-pill__dot"></span>{{ $state->label() }}</span>
                </div>

                <div class="pb-row__meta">
                    <div class="pb-cell-dur">
                        @if($dur)
                            <div class="pb-row-lbl small text-muted text-uppercase" style="font-size:.6rem;letter-spacing:.06em;font-weight:700;">{{ $state === PresenceState::Out ? __('Out for') : __('Inside') }}</div>
                            <span class="pb-dur" data-since="{{ $since->toIso8601String() }}">{{ $dur }}</span>
                        @else
                            <span class="text-muted small">{{ __('No punches yet') }}</span>
                        @endif
                    </div>
                    <div class="pb-cell-last text-truncate">
                        @if($lp)
                            <div class="pb-row-lbl small text-muted text-uppercase" style="font-size:.6rem;letter-spacing:.06em;font-weight:700;">{{ __('Last punch') }}</div>
                            <span class="pb-last text-truncate">
                                <b>{{ $lp->direction === PresenceState::Out ? __('Out') : __('In') }}</b>
                                {{ $lp->punched_at->isToday() ? $lp->punched_at->format('H:i') : $lp->punched_at->format('d M · H:i') }}
                                @if($lp->device) · {{ $lp->device->name }}@endif
                            </span>
                        @else
                            <span class="text-muted small">—</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Phone iOS row — whole row taps to profile --}}
        <a href="{{ $profileUrl }}" class="card shadow-sm rounded-4 he-cq-card pb-card {{ $state === PresenceState::Out ? 'is-out' : '' }} pb-ios text-reset">
            <span class="pb-avatar" style="width:40px;height:40px;font-size:.85rem;">{{ $initial }}</span>
            <span class="pb-ios__body">
                <span class="d-block pb-ios__name text-truncate">{{ $person->name }} @if($stale)<i class="fa-solid fa-triangle-exclamation text-warning small"></i>@endif</span>
                <span class="d-block pb-ios__sub text-truncate">
                    {{ $type === 'staff' ? $sub : ($person->activeAssignment?->bed?->room?->room_number ?? __('No room')) }}
                    · {{ strtolower($state->label()) }}@if($dur) <span data-since="{{ $since->toIso8601String() }}">{{ $dur }}</span>@endif
                </span>
            </span>
            <span class="pb-ios__dot pb-ios__dot--{{ $pill }}"></span>
        </a>
    @empty
        @if($isFiltered)
            <x-he-empty-state icon="magnifying-glass" title="{{ __('No matches') }}"
                subtitle="{{ __('No one matches your filters right now.') }}" />
        @else
            <x-he-empty-state icon="user-group" title="{{ __('No one enrolled yet') }}"
                subtitle="{{ __('Enroll people on the Devices page and their live status appears here.') }}" />
        @endif
    @endforelse
</div>

@if($profiles->hasPages())
    <div class="mt-3">{{ $profiles->links() }}</div>
@endif
