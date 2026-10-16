{{-- The owner's renewal card (S4 · 24_S4_DESIGN.md §7). Data: RenewalCard::for().

     Built to be READ: a countdown ring, one plain headline, the money line, and a
     timeline that shows what happens if nothing is done. Early on it can be told to
     "remind me later" (server-side, per user, per cycle); in the last days it cannot,
     only hidden for this visit. Spans the dashboard's 12-column grid. --}}
@props(['card' => null, 'askEmail' => false])

@once
@push('styles')
<style>
    .rc-wrap { grid-column: span 12; display: flex; flex-direction: column; gap: .75rem; }
    .rc {
        --rc-tone: var(--he-primary, #4f46e5);
        --rc-soft: rgba(79,70,229,.08);
        position: relative; display: grid; grid-template-columns: auto 1fr auto; align-items: center; gap: 1.4rem;
        padding: 1.25rem 1.5rem; background: #fff; border-radius: 22px;
        box-shadow: 0 0 0 1px rgba(15,23,42,.06), 0 1px 2px rgba(15,23,42,.04), 0 12px 32px -12px rgba(15,23,42,.14);
    }
    .rc--soon  { --rc-tone: #d97706; --rc-soft: rgba(245,158,11,.10); }
    .rc--final, .rc--today { --rc-tone: #ea580c; --rc-soft: rgba(234,88,12,.10); }
    .rc--grace { --rc-tone: #dc2626; --rc-soft: rgba(220,38,38,.09); }

    /* Countdown ring */
    .rc-ring { position: relative; width: 84px; height: 84px; flex-shrink: 0; }
    .rc-ring svg { width: 100%; height: 100%; transform: rotate(-90deg); }
    .rc-ring .track { stroke: var(--rc-soft); }
    .rc-ring .fill { stroke: var(--rc-tone); stroke-linecap: round; transition: stroke-dashoffset 1.1s var(--ease-out-expo, cubic-bezier(.16,1,.3,1)); }
    .rc-ring-num { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; line-height: 1; }
    .rc-ring-num b { font-size: 1.55rem; font-weight: 800; letter-spacing: -.03em; color: var(--he-text-main, #0f172a); font-variant-numeric: tabular-nums; }
    .rc-ring-num b.is-word { font-size: 1rem; }
    .rc-ring-num span { font-size: .62rem; font-weight: 700; color: var(--he-text-muted, #64748b); margin-top: .2rem; }

    .rc-body { min-width: 0; }
    .rc-head { font-size: 1.1rem; font-weight: 800; letter-spacing: -.02em; color: var(--he-text-main, #0f172a); line-height: 1.25; }
    .rc-money { font-size: .86rem; color: var(--he-text-muted, #64748b); margin-top: .2rem; font-variant-numeric: tabular-nums; }
    .rc-note { font-size: .78rem; color: var(--he-text-muted, #64748b); margin-top: .35rem; }

    /* What happens next — read left to right */
    .rc-line { display: flex; align-items: flex-start; margin-top: .9rem; max-width: 520px; }
    .rc-pt { position: relative; flex: 1 1 0; min-width: 0; padding-top: 16px; }
    .rc-pt::before { content: ''; position: absolute; top: 5px; left: 0; right: 0; height: 2px; background: rgba(15,23,42,.08); }
    .rc-pt:first-child::before { left: 5px; }
    .rc-pt:last-child::before { right: auto; width: 6px; }
    .rc-pt i { position: absolute; top: 0; left: 0; width: 12px; height: 12px; border-radius: 50%; background: #fff; border: 2px solid rgba(15,23,42,.18); }
    .rc-pt.is-next i { border-color: var(--rc-tone); background: var(--rc-tone); box-shadow: 0 0 0 4px var(--rc-soft); }
    .rc-pt.is-last i { border-color: rgba(15,23,42,.3); }
    .rc-pt-label { display: block; font-size: .66rem; font-weight: 700; color: var(--he-text-muted, #64748b); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .rc-pt-date { display: block; font-size: .8rem; font-weight: 800; color: var(--he-text-main, #0f172a); }
    .rc-pt.is-next .rc-pt-date { color: var(--rc-tone); }

    .rc-act { display: flex; flex-direction: column; align-items: stretch; gap: .45rem; min-width: 168px; }
    .rc-cta { background: var(--rc-tone); color: #fff; border: 0; font-weight: 700; border-radius: 999px; padding: .6rem 1.2rem; text-align: center; text-decoration: none; box-shadow: 0 8px 18px -8px var(--rc-tone); transition: transform .2s var(--ease-out-expo, cubic-bezier(.16,1,.3,1)), filter .2s; white-space: nowrap; }
    .rc-cta:hover { color: #fff; filter: brightness(1.06); transform: translateY(-1px); }
    .rc-later { background: transparent; border: 0; color: var(--he-text-muted, #64748b); font-size: .78rem; font-weight: 700; padding: .2rem; }
    .rc-later:hover { color: var(--he-text-main, #0f172a); }
    .rc-choices { display: flex; flex-wrap: wrap; gap: .35rem; justify-content: center; }
    .rc-choice { border: 1px solid rgba(15,23,42,.1); background: #fff; border-radius: 999px; padding: .3rem .7rem; font-size: .74rem; font-weight: 700; color: var(--he-text-main, #0f172a); transition: border-color .2s, background .2s; }
    .rc-choice:hover { border-color: var(--rc-tone); background: var(--rc-soft); }
    .rc-x { position: absolute; top: .55rem; right: .65rem; border: 0; background: transparent; color: #94a3b8; width: 28px; height: 28px; border-radius: 50%; font-size: .8rem; }
    .rc-x:hover { background: rgba(15,23,42,.06); color: var(--he-text-main, #0f172a); }

    /* Snoozed: a quiet pill that still says when */
    .rc-pill { display: flex; align-items: center; gap: .7rem; padding: .55rem .6rem .55rem 1rem; background: #fff; border-radius: 999px; box-shadow: 0 0 0 1px rgba(15,23,42,.06), 0 4px 14px -8px rgba(15,23,42,.12); font-size: .82rem; color: var(--he-text-muted, #64748b); }
    .rc-pill b { color: var(--he-text-main, #0f172a); }
    .rc-pill i.fa-moon { color: var(--he-primary, #4f46e5); }
    .rc-pill-btn { border: 0; background: var(--he-bg-surface-raised, #f1f5f9); border-radius: 999px; padding: .3rem .8rem; font-size: .74rem; font-weight: 700; color: var(--he-text-main, #0f172a); }

    /* Email ask */
    .rc-mail { display: flex; align-items: center; gap: .9rem; padding: .8rem 1rem; background: linear-gradient(135deg, rgba(79,70,229,.06), rgba(147,51,234,.05)); border-radius: 16px; box-shadow: 0 0 0 1px rgba(79,70,229,.12); }
    .rc-mail-ic { width: 38px; height: 38px; border-radius: 12px; background: #fff; color: var(--he-primary, #4f46e5); display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: 0 4px 12px -6px rgba(79,70,229,.4); }
    .rc-mail-txt { flex: 1 1 auto; min-width: 0; font-size: .84rem; color: var(--he-text-main, #0f172a); }
    .rc-mail-txt span { display: block; font-size: .74rem; color: var(--he-text-muted, #64748b); }

    @media (max-width: 767.98px) {
        .rc { grid-template-columns: auto 1fr; gap: 1rem; padding: 1.1rem; }
        .rc-act { grid-column: 1 / -1; min-width: 0; }
        .rc-ring { width: 70px; height: 70px; }
        .rc-ring-num b { font-size: 1.3rem; }
        .rc-mail { flex-wrap: wrap; }
        .rc-mail-txt { flex: 1 1 calc(100% - 60px); }
        .rc-mail .btn { width: 100%; }
    }
    @media (prefers-reduced-motion: reduce) {
        .rc-ring .fill, .rc-cta { transition: none; }
    }
</style>
@endpush
@endonce

@if($card || $askEmail)
<div class="rc-wrap">
    @if($card)
        @php($snoozable = ! empty($card['snoozeChoices']))
        <div x-data="{
                key: 'he-renewal-hide:{{ $card['cycle'] }}:{{ now()->toDateString() }}',
                hidden: false, open: {{ $card['snoozedUntil'] ? 'false' : 'true' }}, later: false, drawn: false,
                init() {
                    try { this.hidden = sessionStorage.getItem(this.key) === '1'; } catch (e) {}
                    requestAnimationFrame(() => setTimeout(() => this.drawn = true, 60));
                },
                hide() { this.hidden = true; try { sessionStorage.setItem(this.key, '1'); } catch (e) {} },
             }" x-show="!hidden" x-transition.opacity>

            @if($card['snoozedUntil'])
                <div class="rc-pill" x-show="!open">
                    <i class="fa-solid fa-moon"></i>
                    <span class="flex-grow-1 min-w-0 text-truncate">{{ __('Renewal on') }} <b>{{ $card['timeline'][0]['date'] }}</b> · {{ __('quiet until :date', ['date' => $card['snoozedUntil']->format('d M')]) }}</span>
                    <button type="button" class="rc-pill-btn" @click="open = true">{{ __('Show') }}</button>
                </div>
            @endif

            <div class="rc rc--{{ $card['tone'] }}" x-show="open" x-cloak role="region" aria-label="{{ __('Renewal') }}">
                @php($circ = 2 * M_PI * 34)
                <div class="rc-ring" aria-hidden="true">
                    <svg viewBox="0 0 80 80">
                        <circle class="track" cx="40" cy="40" r="34" fill="none" stroke-width="7"/>
                        <circle class="fill" cx="40" cy="40" r="34" fill="none" stroke-width="7"
                                stroke-dasharray="{{ round($circ, 2) }}"
                                :stroke-dashoffset="drawn ? {{ round($circ * (1 - $card['progress']), 2) }} : {{ round($circ, 2) }}"/>
                    </svg>
                    <div class="rc-ring-num">
                        <b class="{{ is_int($card['ringNumber']) ? '' : 'is-word' }}">{{ $card['ringNumber'] }}</b>
                        @if($card['ringLabel'] !== '')<span>{{ $card['ringLabel'] }}</span>@endif
                    </div>
                </div>

                <div class="rc-body">
                    <div class="rc-head">{{ $card['headline'] }}</div>
                    <div class="rc-money">{{ $card['money'] }}</div>
                    <div class="rc-line">
                        @foreach($card['timeline'] as $i => $pt)
                            <div class="rc-pt {{ $i === $card['position'] ? 'is-next' : '' }} {{ $loop->last ? 'is-last' : '' }}">
                                <i></i>
                                <span class="rc-pt-label">{{ $pt['label'] }}</span>
                                <span class="rc-pt-date">{{ $pt['date'] }}</span>
                            </div>
                        @endforeach
                    </div>
                    @if($card['note'])<div class="rc-note"><i class="fa-solid fa-circle-info me-1"></i>{{ $card['note'] }}</div>@endif
                </div>

                <div class="rc-act">
                    <a href="{{ $card['cta']['url'] }}" class="rc-cta tactile-btn" @if($card['cta']['external']) target="_blank" rel="noopener" @endif>
                        @if($card['cta']['external'])<i class="fa-solid fa-lock me-1"></i>@endif{{ $card['cta']['label'] }}
                    </a>
                    @if($snoozable)
                        <button type="button" class="rc-later" x-show="!later" @click="later = true"><i class="fa-regular fa-clock me-1"></i>{{ __('Remind me later') }}</button>
                        <div x-show="later" x-cloak x-transition.opacity>
                            <div class="rc-choices">
                                @foreach($card['snoozeChoices'] as $choice)
                                    <form method="POST" action="{{ route('admin.subscription.snooze') }}" class="m-0">
                                        @csrf
                                        <input type="hidden" name="before" value="{{ $choice['before'] }}">
                                        <button type="submit" class="rc-choice" title="{{ $choice['date'] }}">{{ trans_choice(':n day before|:n days before', $choice['before'], ['n' => $choice['before']]) }}</button>
                                    </form>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    @if($card['snoozedUntil'])
                        <form method="POST" action="{{ route('admin.subscription.snooze.clear') }}" class="m-0 text-center">
                            @csrf @method('DELETE')
                            <button type="submit" class="rc-later">{{ __('Keep reminding me') }}</button>
                        </form>
                    @endif
                </div>

                @if($card['snoozedUntil'])
                    <button type="button" class="rc-x" @click="open = false" aria-label="{{ __('Fold') }}"><i class="fa-solid fa-chevron-up"></i></button>
                @elseif(! $snoozable)
                    {{-- The last days: no snooze — only out of the way for this visit. --}}
                    <button type="button" class="rc-x" @click="hide()" aria-label="{{ __('Hide for now') }}" title="{{ __('Hide for now') }}"><i class="fa-solid fa-xmark"></i></button>
                @endif
            </div>
        </div>
    @endif

    @if($askEmail)
        <div class="rc-mail" x-data="{ hidden: false, key: 'he-email-ask-until',
                init() { try { this.hidden = Number(localStorage.getItem(this.key) || 0) > Date.now(); } catch (e) {} },
                later() { this.hidden = true; try { localStorage.setItem(this.key, String(Date.now() + 14 * 864e5)); } catch (e) {} } }"
             x-show="!hidden" x-transition.opacity>
            <div class="rc-mail-ic"><i class="fa-solid fa-envelope-circle-check"></i></div>
            <div class="rc-mail-txt">
                <b>{{ __('Add your email so reminders and receipts reach you') }}</b>
                <span>{{ __('Takes a minute — we send a 6-digit code to confirm it.') }}</span>
            </div>
            <a href="{{ route('admin.settings.index', ['tab' => 'profile', 'email' => 1]) }}" class="btn btn-primary btn-sm rounded-pill px-3 fw-bold">{{ __('Add email') }}</a>
            <button type="button" class="btn btn-link btn-sm text-muted text-decoration-none fw-semibold" @click="later()">{{ __('Later') }}</button>
        </div>
    @endif
</div>
@endif
