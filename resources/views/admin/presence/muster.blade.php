<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Evacuation Muster') }} · {{ $branch?->name }}</title>
    @php
        use App\Enums\Presence\PresenceState;
        $all = $students->concat($staff);
        $confirmed = $all->filter(fn ($p) => $p->state === PresenceState::In);
        $uncertain = $all->filter(fn ($p) => $p->state === PresenceState::Unknown);

        $groupStudents = fn ($rows) => $rows->filter(fn ($p) => $p->presenceable instanceof \App\Models\Student)
            ->groupBy(fn ($p) => $p->presenceable->activeAssignment?->bed?->room?->floor?->name ?: __('Unassigned'));
        $groupStaff = fn ($rows) => $rows->filter(fn ($p) => $p->presenceable instanceof \App\Models\Staff)
            ->groupBy(fn ($p) => $p->presenceable->designation ?: __('Staff'));
    @endphp
    <style>
        :root { --ink: #0f172a; --muted: #64748b; --line: #cbd5e1; --warn: #b45309; }
        * { box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', -apple-system, Segoe UI, Roboto, sans-serif; color: var(--ink); margin: 0; background: #f1f5f9; }
        .sheet { max-width: 800px; margin: 1.5rem auto; background: #fff; padding: 2rem 2.25rem; box-shadow: 0 10px 30px rgba(0,0,0,0.08); }
        .top { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; border-bottom: 3px solid var(--ink); padding-bottom: 0.9rem; }
        .top h1 { font-size: 1.5rem; margin: 0 0 0.2rem; letter-spacing: -0.02em; }
        .top .meta { font-size: 0.82rem; color: var(--muted); }
        .top .right { text-align: right; font-size: 0.82rem; color: var(--muted); }
        .tally { display: inline-flex; align-items: baseline; gap: 0.4rem; margin-top: 0.4rem; }
        .tally b { font-size: 1.8rem; color: var(--ink); }
        .print-btn { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.5rem 1rem; border: 0; border-radius: 999px; background: #4f46e5; color: #fff; font-weight: 700; cursor: pointer; font-size: 0.85rem; }
        h2 { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.08em; color: var(--muted); margin: 1.6rem 0 0.5rem; border-bottom: 1px solid var(--line); padding-bottom: 0.3rem; }
        .grp { margin: 0.9rem 0; }
        .grp__h { font-weight: 800; font-size: 0.95rem; margin-bottom: 0.35rem; }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        td { padding: 0.35rem 0.5rem; border-bottom: 1px solid #e2e8f0; }
        td.n { width: 2rem; color: var(--muted); font-variant-numeric: tabular-nums; }
        td.chk { width: 2rem; text-align: center; }
        td.chk span { display: inline-block; width: 14px; height: 14px; border: 1.5px solid var(--line); border-radius: 3px; }
        .uncertain h2 { color: var(--warn); }
        .uncertain td { background: #fffbeb; }
        .empty { color: var(--muted); font-style: italic; padding: 0.5rem 0; }
        .foot { margin-top: 1.6rem; border-top: 1px solid var(--line); padding-top: 0.7rem; font-size: 0.8rem; color: var(--muted); display: flex; justify-content: space-between; }
        @media print {
            body { background: #fff; }
            .sheet { box-shadow: none; margin: 0; max-width: none; padding: 0; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
<div class="sheet">
    <div class="top">
        <div>
            <h1>{{ __('Evacuation Muster') }}</h1>
            <div class="meta">{{ $branch?->name }} · {{ __('Everyone currently inside') }}</div>
            <div class="tally"><b>{{ $confirmed->count() }}</b> <span>{{ __('confirmed inside') }}</span>@if($uncertain->count()) · <span style="color:var(--warn)">{{ $uncertain->count() }} {{ __('uncertain') }}</span>@endif</div>
        </div>
        <div class="right">
            <button class="print-btn no-print" onclick="window.print()">🖨 {{ __('Print') }}</button>
            <div style="margin-top:0.5rem;">{{ __('Generated') }}<br>{{ $generatedAt->format('d M Y · H:i') }}</div>
        </div>
    </div>

    @php $renderGroups = function($groups, $showRoom) { return $groups; }; @endphp

    {{-- Confirmed inside --}}
    @if($students->isNotEmpty())
        @php $sg = $groupStudents($confirmed); @endphp
        <h2>{{ __('Students inside') }} ({{ $confirmed->filter(fn($p)=>$p->presenceable instanceof \App\Models\Student)->count() }})</h2>
        @forelse($sg as $floor => $rows)
            <div class="grp">
                <div class="grp__h">{{ $floor }}</div>
                <table>
                    @foreach($rows as $i => $p)
                        <tr>
                            <td class="n">{{ $i + 1 }}</td>
                            <td>{{ $p->presenceable->name }}</td>
                            <td style="color:var(--muted)">{{ __('Room') }} {{ $p->presenceable->activeAssignment?->bed?->room?->room_number ?? '—' }}</td>
                            <td class="chk"><span></span></td>
                        </tr>
                    @endforeach
                </table>
            </div>
        @empty
            <div class="empty">{{ __('No students confirmed inside.') }}</div>
        @endforelse
    @endif

    @if($staff->isNotEmpty())
        @php $stg = $groupStaff($confirmed); @endphp
        <h2>{{ __('Staff inside') }} ({{ $confirmed->filter(fn($p)=>$p->presenceable instanceof \App\Models\Staff)->count() }})</h2>
        @forelse($stg as $desig => $rows)
            <div class="grp">
                <div class="grp__h">{{ $desig }}</div>
                <table>
                    @foreach($rows as $i => $p)
                        <tr><td class="n">{{ $i + 1 }}</td><td>{{ $p->presenceable->name }}</td><td class="chk"><span></span></td></tr>
                    @endforeach
                </table>
            </div>
        @empty
            <div class="empty">{{ __('No staff confirmed inside.') }}</div>
        @endforelse
    @endif

    {{-- Uncertain --}}
    @if($uncertain->isNotEmpty())
        <div class="uncertain">
            <h2>⚠ {{ __('Status uncertain — verify in person') }} ({{ $uncertain->count() }})</h2>
            <table>
                @foreach($uncertain as $i => $p)
                    <tr>
                        <td class="n">{{ $i + 1 }}</td>
                        <td>{{ $p->presenceable->name }}</td>
                        <td style="color:var(--muted)">{{ $p->presenceable instanceof \App\Models\Student ? (__('Room').' '.($p->presenceable->activeAssignment?->bed?->room?->room_number ?? '—')) : ($p->presenceable->designation ?: __('Staff')) }}</td>
                        <td class="chk"><span></span></td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endif

    <div class="foot">
        <span>{{ __('Checked by') }}: ____________________</span>
        <span>{{ config('hostelease.company.name', 'HostelEase') }}</span>
    </div>
</div>
</body>
</html>
