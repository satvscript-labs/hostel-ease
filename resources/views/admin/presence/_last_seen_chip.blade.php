{{-- Last-seen presence chip for a student/staff profile header (idea #4).
     Pass $person (Student or Staff). Renders nothing if they're not enrolled.
     Read-only: a quiet, one-line indicator of their current gate state. --}}
@php
    use App\Enums\Presence\PresenceState;
    $pp = $person->presenceProfile;
@endphp
@if($pp)
    @php
        $st = $pp->state;
        $cls = match ($st) { PresenceState::In => 'in', PresenceState::Out => 'out', default => 'unknown' };
        $since = $pp->state_changed_at;
        $dur = null;
        if ($since) {
            $s = abs(now()->diffInSeconds($since));
            $d = intdiv($s, 86400); $h = intdiv($s % 86400, 3600); $m = intdiv($s % 3600, 60);
            $dur = $d > 0 ? "{$d}d {$h}h" : ($h > 0 ? "{$h}h {$m}m" : max(1, $m).'m');
        }
    @endphp
    <span class="ls-chip ls-chip--{{ $cls }}" title="{{ __('Gate presence') }}">
        <span class="ls-chip__dot"></span>
        {{ $st->label() }}@if($dur) · {{ $st === PresenceState::Out ? __('out') : ($st === PresenceState::In ? __('since') : '') }}
            @if($st === PresenceState::In && $since){{ $since->isToday() ? $since->format('H:i') : $since->format('d M') }}@else {{ $dur }}@endif
        @endif
    </span>
    @once
    @push('styles')
    <style>
        .ls-chip { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.3rem 0.75rem; border-radius: 999px; font-size: 0.75rem; font-weight: 700; white-space: nowrap; }
        .ls-chip__dot { width: 8px; height: 8px; border-radius: 50%; }
        .ls-chip--in { background: var(--he-success-soft); color: #047857; } .ls-chip--in .ls-chip__dot { background: var(--he-success); }
        .ls-chip--out { background: var(--he-warning-soft); color: #b45309; } .ls-chip--out .ls-chip__dot { background: var(--he-warning); }
        .ls-chip--unknown { background: var(--he-bg-surface-raised); color: var(--he-text-muted); } .ls-chip--unknown .ls-chip__dot { background: var(--he-text-muted); }
    </style>
    @endpush
    @endonce
@endif
