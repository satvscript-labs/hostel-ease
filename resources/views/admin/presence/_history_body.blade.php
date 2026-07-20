{{-- Drawer body (fetched via AJAX into #he-drawer-content). Header + mini-stats
     + day-grouped timeline + the manual-correction form. --}}
@php
    use App\Enums\Presence\PresenceState;
    use App\Enums\Presence\PunchSource;
    $person = $profile->presenceable;
    $isStaff = $person instanceof \App\Models\Staff;
    $initial = mb_strtoupper(mb_substr(trim($person?->name ?? '?'), 0, 1));
    $sub = $isStaff
        ? ($person->designation ?: __('Staff'))
        : (($room = $person?->activeAssignment?->bed?->room) ? (($room->floor?->name ? $room->floor->name.' · ' : '').__('Room').' '.$room->room_number) : __('No room'));
    $profileUrl = $isStaff ? route('admin.staff.show', $person) : route('admin.students.show', $person);

    $state = $profile->state;
    $pill = match ($state) { PresenceState::In => 'in', PresenceState::Out => 'out', default => 'unknown' };

    $fmtSecs = function ($secs) {
        $s = abs((int) $secs);
        $d = intdiv($s, 86400); $h = intdiv($s % 86400, 3600); $m = intdiv($s % 3600, 60);
        if ($d > 0) return $d.'d '.$h.'h';
        if ($h > 0) return $h.'h '.$m.'m';
        return max(1, $m).'m';
    };
    $fmtDur = fn ($since) => $since ? $fmtSecs(now()->diffInSeconds($since)) : null;
    $curDur = $fmtDur($profile->state_changed_at);
@endphp

<div class="he-drawer__head">
    <span class="he-drawer__avatar">{{ $initial }}</span>
    <div class="flex-grow-1" style="min-width:0;">
        <div class="fw-bold fs-6 text-truncate">{{ $person?->name ?? __('Unknown') }}</div>
        <div class="small text-muted text-truncate">{{ $sub }}</div>
        <div class="mt-1">
            <span class="pb-pill pb-pill--{{ $pill }}"><span class="pb-pill__dot"></span>{{ $state->label() }}</span>
            @if($curDur)<span class="small text-muted ms-1">· {{ $state === PresenceState::Out ? __('out') : __('inside') }} {{ $curDur }}</span>@endif
        </div>
    </div>
    <button type="button" class="he-drawer__x" @click="$dispatch('close-history')" onclick="window.dispatchEvent(new CustomEvent('close-history'))" aria-label="{{ __('Close') }}"><i class="fa-solid fa-xmark"></i></button>
</div>

<div class="he-drawer__body" x-data="{ correcting: false }">

    {{-- Mini-stats --}}
    <div class="hist-stats">
        <div class="hist-stat"><div class="hist-stat__v">{{ $stats['punches'] }}</div><div class="hist-stat__l">{{ __('Punches · 60d') }}</div></div>
        <div class="hist-stat"><div class="hist-stat__v">{{ $stats['active_days'] }}</div><div class="hist-stat__l">{{ __('Active days') }}</div></div>
        <div class="hist-stat"><div class="hist-stat__v">{{ $stats['last'] ? $stats['last']->punched_at->diffForHumans(null, true) : '—' }}</div><div class="hist-stat__l">{{ __('Last seen') }}</div></div>
    </div>

    {{-- Actions --}}
    <div class="d-flex gap-2 mb-2">
        <button type="button" class="btn btn-sm btn-premium rounded-pill fw-bold px-3 flex-grow-1 tactile-btn" @click="correcting = !correcting">
            <i class="fa-solid fa-pen me-1"></i>{{ __('Correct') }}
        </button>
        <a href="{{ $profileUrl }}" class="btn btn-sm btn-light border rounded-pill fw-bold px-3 tactile-btn"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i>{{ __('Profile') }}</a>
        <form method="POST" action="{{ route('admin.presence.history.reset', $profile) }}" class="m-0" data-confirm="{{ __('Reset this person\'s state to unknown?') }}">
            @csrf
            <button class="he-icon-btn" title="{{ __('Reset state to unknown') }}" aria-label="{{ __('Reset state') }}"><i class="fa-solid fa-rotate-left"></i></button>
        </form>
    </div>

    {{-- Correction form --}}
    <div class="hist-correct" x-show="correcting" x-collapse x-cloak x-data="{ dir: 'out' }">
        <form method="POST" action="{{ route('admin.presence.history.correct', $profile) }}" data-ring-required>
            @csrf
            <input type="hidden" name="direction" :value="dir">
            <label class="form-label small fw-bold text-uppercase mb-1">{{ __('Mark as') }}</label>
            <div class="d-flex gap-2 mb-2">
                <button type="button" class="btn btn-sm rounded-pill fw-bold flex-grow-1" :class="dir==='in' ? 'btn-success' : 'btn-light border'" @click="dir='in'"><i class="fa-solid fa-right-to-bracket me-1"></i>{{ __('Inside') }}</button>
                <button type="button" class="btn btn-sm rounded-pill fw-bold flex-grow-1" :class="dir==='out' ? 'btn-warning' : 'btn-light border'" @click="dir='out'"><i class="fa-solid fa-right-from-bracket me-1"></i>{{ __('Out') }}</button>
            </div>
            <label class="form-label small fw-bold text-uppercase mb-1">{{ __('When') }} <span class="text-muted fw-normal text-lowercase">({{ __('optional · defaults to now') }})</span></label>
            <input type="datetime-local" name="occurred_at" class="form-control form-control-sm bg-light mb-2" max="{{ now()->format('Y-m-d\TH:i') }}" min="{{ now()->subDay()->format('Y-m-d\TH:i') }}">
            <label class="form-label small fw-bold text-uppercase mb-1">{{ __('Reason') }} <span class="text-danger">*</span></label>
            <input type="text" name="reason" class="form-control form-control-sm bg-light mb-2" required maxlength="255" placeholder="{{ __('e.g. forgot to scan out') }}">
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-light border rounded-pill px-3" @click="correcting=false">{{ __('Cancel') }}</button>
                <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3 fw-bold flex-grow-1"><i class="fa-solid fa-check me-1"></i>{{ __('Save correction') }}</button>
            </div>
        </form>
    </div>

    {{-- Timeline --}}
    @forelse($days as $date => $events)
        @php $day = \Illuminate\Support\Carbon::parse($date); @endphp
        <div class="hist-day">{{ $day->isToday() ? __('Today') : ($day->isYesterday() ? __('Yesterday') : $day->format('D, d M Y')) }}</div>
        <div class="hist-line">
            @foreach($events as $i => $p)
                <div class="hist-event">
                    <span class="hist-event__dot hist-event__dot--{{ $p->direction->value }}">
                        <i class="fa-solid fa-{{ $p->direction === PresenceState::In ? 'arrow-right-to-bracket' : ($p->direction === PresenceState::Out ? 'arrow-right-from-bracket' : 'question') }}"></i>
                    </span>
                    <span class="hist-event__time">{{ $p->punched_at->format('H:i') }}</span>
                    <span class="hist-event__meta text-truncate">
                        {{ $p->direction === PresenceState::In ? __('Entered') : ($p->direction === PresenceState::Out ? __('Left') : __('Unclear')) }}
                        @if($p->verify_mode) · {{ $p->verify_mode }}@endif
                        @if($p->device) · {{ $p->device->name }}@endif
                    </span>
                    @if($p->source === PunchSource::Manual)<span class="hist-manual" title="{{ $p->note }}">{{ __('manual') }}</span>@endif
                </div>
                @if($i < $events->count() - 1)
                    <div class="hist-gap">— {{ $p->direction === PresenceState::In ? __('inside') : __('out') }} {{ $fmtSecs($p->punched_at->diffInSeconds($events[$i+1]->punched_at)) }} —</div>
                @endif
            @endforeach
        </div>
    @empty
        <div class="text-center text-muted py-4">
            <i class="fa-solid fa-inbox fs-3 d-block mb-2 opacity-50"></i>
            {{ __('No punches recorded yet.') }}
        </div>
    @endforelse
</div>
