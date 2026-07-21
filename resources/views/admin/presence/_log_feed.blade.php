{{-- Gate Log feed (fragment) — punches grouped by day, newest first. A matched
     row's name opens the history drawer; an unmatched row offers Match. --}}
@php
    use App\Enums\Presence\PresenceState;
    use App\Enums\Presence\PunchSource;
    $grouped = $punches->getCollection()->groupBy(fn ($p) => $p->punched_at->toDateString());
@endphp

@forelse($grouped as $date => $rows)
    @php $day = \Illuminate\Support\Carbon::parse($date); @endphp
    <div class="log-day">{{ $day->isToday() ? __('Today') : ($day->isYesterday() ? __('Yesterday') : $day->format('D, d M Y')) }}</div>

    <div class="d-flex flex-column">
        @foreach($rows as $p)
            @php
                $person = $p->profile?->presenceable;
                $dir = $p->direction;
            @endphp
            <div class="log-row {{ $person ? '' : 'log-row--unmatched' }}">
                <span class="log-glyph log-glyph--{{ $dir->value }}">
                    <i class="fa-solid fa-{{ $dir === PresenceState::In ? 'arrow-right-to-bracket' : ($dir === PresenceState::Out ? 'arrow-right-from-bracket' : 'question') }}"></i>
                </span>

                <div class="log-who">
                    @if($person)
                        <button type="button" class="log-name" @click="$dispatch('presence-history', { profile: '{{ $p->profile->public_id }}' })">{{ $person->name }}</button>
                        <div class="log-sub">{{ $dir === PresenceState::In ? __('Entered') : ($dir === PresenceState::Out ? __('Left') : __('Unclear')) }}</div>
                    @else
                        <span class="log-name log-name--unknown"><i class="fa-solid fa-circle-question me-1"></i>{{ __('Unknown') }}</span>
                        <div class="log-sub log-sub--mono">{{ $p->device_user_id }}</div>
                    @endif
                </div>

                <div class="log-meta">
                    <span class="log-time">{{ $p->punched_at->format('H:i') }}</span>
                    @if($p->verify_mode)<span class="log-badge">{{ $p->verify_mode }}</span>@endif
                    @if($p->device)<span class="log-dev text-truncate">{{ $p->device->name }}</span>@endif
                    @if($p->source === PunchSource::Manual)<span class="log-badge log-badge--manual" title="{{ $p->note }}">{{ __('manual') }}</span>@endif
                </div>

                @if(! $person)
                    <button type="button" class="btn btn-sm btn-white border rounded-pill fw-bold px-3 text-nowrap tactile-btn log-match"
                            @click="openMatch('{{ $p->device_user_id }}')">
                        <i class="fa-solid fa-link me-1"></i>{{ __('Match') }}
                    </button>
                @endif
            </div>
        @endforeach
    </div>
@empty
    <x-he-empty-state icon="clock-rotate-left" title="{{ __('No punches') }}"
        subtitle="{{ __('No gate activity matches these filters. Try another date or clear the filters.') }}" />
@endforelse

@if($punches->hasPages())
    <div class="mt-3">{{ $punches->links() }}</div>
@endif
