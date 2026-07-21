{{-- Shared board body for the Students and Staff pages (branches on $type).
     The engine is shared; the two views never mix (owner's hard requirement).
     Live: filters fragment-swap #pb-list (§4.3); a ~20s client poll re-fetches
     the current URL and swaps #pb-list + #pb-stats + #pb-fresh (03 §1). --}}
@php
    use App\Enums\Presence\PresenceState;
    $isStaff = $type === 'staff';
    $boardRoute = $isStaff ? 'admin.presence.staff' : 'admin.presence.students';
    $musterType = $isStaff ? 'staff' : 'students';
    $title = $isStaff ? __('Staff Presence') : __('Student Presence');
    $insideLabel = $isStaff ? __('On premises') : __('Inside');
@endphp

<div class="page-enter" x-data="presenceBoard()">

    {{-- Head --}}
    <div class="he-page-head mb-4 stagger-1">
        <div>
            <h1 class="he-page-title">{{ $title }}</h1>
            <p class="he-page-sub">{{ __('Live in–out status from the gate device.') }}</p>
        </div>
        <a href="{{ route('admin.presence.muster', ['type' => $musterType]) }}" target="_blank" rel="noopener"
           class="btn btn-light border rounded-pill px-4 fw-semibold shadow-sm tactile-btn d-none d-md-inline-flex align-items-center">
            <i class="fa-solid fa-print me-2 text-primary"></i>{{ __('Inside now · print') }}
        </a>
    </div>

    {{-- Stats (swapped by the poll) --}}
    <div id="pb-stats" class="mb-3 stagger-2">
        @include('admin.presence._board_stats')
    </div>

    {{-- Freshness + curfew (curfew is a STUDENT concept — never on the staff board) --}}
    <div class="d-flex align-items-center gap-2 flex-wrap mb-4 stagger-3">
        <div id="pb-fresh">
            @include('admin.presence._board_fresh')
        </div>
        @unless($isStaff)
        <button type="button" class="pb-curfew {{ $branch?->hasCurfew() ? 'is-set' : '' }}" @click="curfewOpen = true">
            <i class="fa-solid fa-moon"></i>
            @if($branch?->hasCurfew()){{ __('Curfew') }} {{ $branch->curfewLabel() }}@else{{ __('Set curfew') }}@endif
        </button>
        @endunless
    </div>

    {{-- Filter bar — ONE row (§4.5). Submitting fragment-swaps the list. --}}
    <form method="GET" action="{{ route($boardRoute) }}" data-fragment="#pb-list"
          class="d-flex align-items-center gap-2 mb-3 stagger-4" style="position: relative; z-index: 30;"
          x-data="{ q: @js($search) }">
        <div class="he-search he-search--inline he-search--clearable">
            <span class="he-search__icon"><i class="fa-solid fa-magnifying-glass"></i></span>
            <input type="text" name="search" class="he-search__input" placeholder="{{ __('Search by name…') }}"
                   x-model="q" value="{{ $search }}" autocomplete="off"
                   @input.debounce.450ms="$el.form.requestSubmit()">
            <button type="button" class="he-search__clear" x-show="q" @click="q=''; $el.form.requestSubmit()" x-cloak aria-label="{{ __('Clear') }}"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <x-he-select name="status" icon="filter" icon-only-mobile :selected="$status"
            :options="[
                '' => ['label' => __('All statuses'), 'icon' => 'filter'],
                'out' => ['label' => __('Out'), 'icon' => 'person-walking-arrow-right'],
                'in' => ['label' => $insideLabel, 'icon' => 'house'],
                'unknown' => ['label' => __('Unknown'), 'icon' => 'circle-question'],
            ]" />

        @if($isStaff)
            @if($designations->isNotEmpty())
                <x-he-select name="designation" icon="briefcase" icon-only-mobile :selected="request('designation','')"
                    :options="['' => ['label' => __('All roles'), 'icon' => 'briefcase']] + $designations->mapWithKeys(fn($d) => [$d => ['label' => $d, 'icon' => 'briefcase']])->all()" />
            @endif
        @else
            @if($floors->isNotEmpty())
                <x-he-select name="floor" icon="layer-group" icon-only-mobile :selected="request('floor','')"
                    :options="['' => ['label' => __('All floors'), 'icon' => 'layer-group']] + $floors->mapWithKeys(fn($f) => [$f->id => ['label' => $f->name, 'icon' => 'layer-group']])->all()" />
            @endif
            <x-he-select name="occupation" icon="user-tag" icon-only-mobile :selected="request('occupation','')"
                :options="['' => ['label' => __('All types'), 'icon' => 'user-tag']] + collect($occupations)->mapWithKeys(fn($l,$k) => [$k => ['label' => $l, 'icon' => 'user-tag']])->all()" />
        @endif

        <x-he-select name="sort" icon="arrow-down-wide-short" icon-only-mobile :selected="$sort"
            :options="[
                'longest' => ['label' => __('Longest out first'), 'icon' => 'arrow-down-wide-short'],
                'name' => ['label' => __('Name'), 'icon' => 'arrow-down-a-z'],
                'recent' => ['label' => __('Latest movement'), 'icon' => 'clock-rotate-left'],
            ]" />
    </form>

    {{-- The board --}}
    <div class="he-adaptive">
        <div id="pb-list" data-fragment-container>
            @include('admin.presence._board_rows')
        </div>
    </div>

    {{-- Curfew settings — one control: an on/off switch + a from→to window
         (students only). --}}
    @unless($isStaff)
    <x-he-modal open="curfewOpen" title="{{ __('Student curfew') }}" icon="moon"
        :action="route('admin.presence.curfew')">
        <div x-data="{ on: {{ $branch?->hasCurfew() ? 'true' : 'false' }} }">
            {{-- On/off toggle --}}
            <label class="d-flex align-items-center justify-content-between gap-2 p-3 rounded-3 mb-3" style="background: var(--he-bg-surface-raised); cursor: pointer;">
                <span class="fw-bold">{{ __('Curfew enabled') }}</span>
                <span class="cf-switch" :class="{ 'is-on': on }">
                    <input type="checkbox" name="curfew_enabled" value="1" class="d-none" x-model="on">
                    <span class="cf-switch__knob"></span>
                </span>
            </label>

            <div x-show="on" x-collapse>
                <p class="text-muted small mb-3">{{ __('A student out during this window is flagged "late". The window may cross midnight (e.g. 22:00 → 06:00).') }}</p>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label fw-bold small text-uppercase">{{ __('From') }}</label>
                        <input type="time" name="curfew_from" class="form-control bg-light" value="{{ $branch?->curfew_from }}" :required="on">
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-bold small text-uppercase">{{ __('To') }}</label>
                        <input type="time" name="curfew_to" class="form-control bg-light" value="{{ $branch?->curfew_to }}" :required="on">
                    </div>
                </div>
                <label class="d-flex align-items-center gap-2">
                    <input type="checkbox" name="curfew_notify" value="1" class="form-check-input mt-0" {{ $branch?->curfew_notify ? 'checked' : '' }}>
                    <span class="fw-semibold small">{{ __('Notify the warden when students are still out during curfew') }}</span>
                </label>
            </div>
            <p class="text-muted small mb-0" x-show="!on" x-cloak>{{ __('Turn the switch on to set a curfew window.') }}</p>
        </div>
    </x-he-modal>
    @endunless

    {{-- Per-person history drawer (opened by a row's name) --}}
    @include('admin.presence._history_drawer')
</div>
