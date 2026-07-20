@extends('layouts.app')
@section('title', __('Gate Log'))

@push('styles')
@include('admin.presence._history_styles')
<style>
    .log-day {
        position: sticky; top: 0; z-index: 2;
        font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.06em;
        color: var(--he-text-muted); background: var(--he-bg-canvas);
        padding: 0.6rem 0.25rem 0.35rem;
    }
    .log-row {
        display: flex; align-items: center; gap: 0.85rem;
        padding: 0.7rem 0.85rem; border-radius: var(--he-radius-md);
        transition: background 0.18s var(--ease-out-expo);
    }
    .log-row:hover { background: var(--he-bg-surface); }
    .log-row--unmatched { background: rgba(245, 158, 11, 0.06); }
    .log-glyph {
        width: 34px; height: 34px; flex-shrink: 0; border-radius: 50%;
        display: flex; align-items: center; justify-content: center; color: #fff; font-size: 0.8rem;
    }
    .log-glyph--in { background: var(--he-success); }
    .log-glyph--out { background: var(--he-warning); }
    .log-glyph--unknown { background: var(--he-text-muted); }
    .log-who { flex: 1; min-width: 0; }
    .log-name {
        border: 0; background: none; padding: 0; font-weight: 700; color: var(--he-text-main);
        text-align: left; text-decoration: none;
    }
    button.log-name:hover { color: var(--he-primary); text-decoration: underline; }
    .log-name--unknown { color: var(--he-warning); }
    .log-sub { font-size: 0.76rem; color: var(--he-text-muted); }
    .log-sub--mono { font-family: ui-monospace, monospace; }
    .log-meta { display: flex; align-items: center; gap: 0.6rem; flex-shrink: 0; }
    .log-time { font-weight: 700; font-variant-numeric: tabular-nums; }
    .log-badge {
        font-size: 0.62rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em;
        background: var(--he-bg-surface-raised); color: var(--he-text-muted);
        padding: 0.15rem 0.45rem; border-radius: 999px;
    }
    .log-badge--manual { background: var(--he-primary-soft); color: var(--he-primary); }
    .log-dev { font-size: 0.76rem; color: var(--he-text-muted); max-width: 120px; }
    @media (max-width: 575.98px) {
        .log-meta .log-dev { display: none; }
        .log-match { padding: 0.3rem 0.7rem !important; }
    }
    .lg-toggle {
        display: inline-flex; align-items: center; gap: 0.4rem;
        padding: 0.5rem 0.9rem; border-radius: var(--he-radius-full);
        border: 1px solid rgba(0,0,0,0.08); background: var(--he-bg-surface);
        font-size: 0.8rem; font-weight: 700; color: var(--he-text-muted); cursor: pointer;
        transition: all 0.18s var(--ease-out-expo); white-space: nowrap;
    }
    .lg-toggle.is-on { background: var(--he-warning); border-color: var(--he-warning); color: #fff; }
    .lg-date { border: 1px solid rgba(0,0,0,0.08); border-radius: var(--he-radius-full); padding: 0.45rem 0.9rem; font-size: 0.82rem; font-weight: 600; background: var(--he-bg-surface); color: var(--he-text-main); }
</style>
@endpush

@section('content')
<div class="page-enter" x-data="gateLog()">

    <div class="he-page-head mb-4 stagger-1">
        <div>
            <h1 class="he-page-title">{{ __('Gate Log') }}</h1>
            <p class="he-page-sub">{{ __('Every punch, in and out — the full register.') }}</p>
        </div>
        <a href="{{ route('admin.presence.log.export', request()->query()) }}"
           class="btn btn-light border rounded-pill px-4 fw-semibold shadow-sm tactile-btn d-none d-md-inline-flex align-items-center">
            <i class="fa-solid fa-file-csv me-2 text-primary"></i>{{ __('Export CSV') }}
        </a>
    </div>

    {{-- Filters — one row (§4.5) --}}
    <form method="GET" action="{{ route('admin.presence.log') }}" data-fragment="#log-feed"
          class="d-flex align-items-center gap-2 mb-3 flex-wrap stagger-2" style="position: relative; z-index: 30;"
          x-data="{ q: @js($filters['search']) }">
        <div class="he-search he-search--inline he-search--clearable">
            <span class="he-search__icon"><i class="fa-solid fa-magnifying-glass"></i></span>
            <input type="text" name="search" class="he-search__input" placeholder="{{ __('Name or device ID…') }}"
                   x-model="q" value="{{ $filters['search'] }}" autocomplete="off" @input.debounce.450ms="$el.form.requestSubmit()">
            <button type="button" class="he-search__clear" x-show="q" @click="q=''; $el.form.requestSubmit()" x-cloak aria-label="{{ __('Clear') }}"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <input type="date" name="date" class="lg-date" value="{{ $filters['date'] }}" max="{{ now()->format('Y-m-d') }}" @change="$el.form.requestSubmit()">

        <x-he-select name="type" icon="users" icon-only-mobile :selected="$filters['type']"
            :options="[
                '' => ['label' => __('Everyone'), 'icon' => 'users'],
                'students' => ['label' => __('Students'), 'icon' => 'graduation-cap'],
                'staff' => ['label' => __('Staff'), 'icon' => 'briefcase'],
            ]" />

        <x-he-select name="direction" icon="arrows-left-right" icon-only-mobile :selected="$filters['direction']"
            :options="[
                '' => ['label' => __('In & Out'), 'icon' => 'arrows-left-right'],
                'in' => ['label' => __('In only'), 'icon' => 'arrow-right-to-bracket'],
                'out' => ['label' => __('Out only'), 'icon' => 'arrow-right-from-bracket'],
            ]" />

        @if($devices->count() > 1)
            <x-he-select name="device" icon="door-open" icon-only-mobile :selected="$filters['device']"
                :options="['' => ['label' => __('All devices'), 'icon' => 'door-open']] + $devices->mapWithKeys(fn($d) => [$d->id => ['label' => $d->name, 'icon' => 'door-open']])->all()" />
        @endif

        <label class="lg-toggle {{ $filters['unmatched'] ? 'is-on' : '' }}">
            <input type="checkbox" name="unmatched" value="1" class="d-none" {{ $filters['unmatched'] ? 'checked' : '' }} @change="$el.form.requestSubmit()">
            <i class="fa-solid fa-circle-question"></i>{{ __('Unmatched') }}
            @if($unmatchedCount > 0)<span class="badge bg-white text-warning rounded-pill">{{ $unmatchedCount }}</span>@endif
        </label>
    </form>

    <div class="panel-card stagger-3">
        <div class="panel-body">
            <div id="log-feed" data-fragment-container>
                @include('admin.presence._log_feed')
            </div>
        </div>
    </div>

    {{-- Quarantine match modal (unmatched → person) --}}
    @if($matchPeople->isNotEmpty())
    <x-he-modal open="matchOpen" title="{{ __('Match a device ID') }}" icon="link"
        :action="route('admin.presence.quarantine.match')">
        <p class="text-muted small mb-3">{{ __('Device ID') }} <span class="log-sub--mono fw-bold" x-text="matchId"></span>. {{ __('Who is this? Their past punches under this ID will be attached.') }}</p>
        <input type="hidden" name="device_user_id" :value="matchId">
        <input type="hidden" name="person_type" :value="matchType">
        <input type="hidden" name="person_id" :value="matchPersonId">
        <label class="form-label fw-bold small text-uppercase">{{ __('Person') }}</label>
        <x-he-picker name="match_person" :options="$matchPeople"
            search-placeholder="{{ __('Search students & staff…') }}" placeholder="{{ __('Select a person') }}"
            @he-picker-change="onMatchPick($event.detail)" />
    </x-he-modal>
    @endif

    {{-- History drawer --}}
    @include('admin.presence._history_drawer')
</div>
@endsection

@push('scripts')
<script>
    function gateLog() {
        return {
            matchOpen: false, matchId: '', matchType: '', matchPersonId: '',
            openMatch(id) { this.matchId = id; this.matchType = ''; this.matchPersonId = ''; this.matchOpen = true; },
            onMatchPick(detail) {
                const [type, ...rest] = (detail.value || '').split(':');
                this.matchType = type || ''; this.matchPersonId = rest.join(':');
            },
        };
    }
</script>
@endpush
