{{-- Premium dropdown — replaces raw select elements. See
     .agents/ui_design_guidelines.md section 4 for variants and full usage
     examples. By default, picking an option submits the closest form
     (matches the existing filter-bar convention) — pass submit=false for a
     select that's just one field in a larger form (e.g. inside a
     create/edit modal).

     variant: 'filter' (icon + micro-label pill) | 'status' (colored badge)
     compact: true renders a slim, native-select-height trigger instead of
              the taller filter-pill — use inside dense forms/modals. Also
              caps the menu to the trigger's own width instead of a fixed
              220px, so it never overflows a narrow grid column.
     searchable: true adds a search box inside the dropdown menu, for long
                 option lists (e.g. picking a student).
     iconOnlyMobile: true collapses the filter trigger to a square icon below
                 md, so it can share a row with a search box on a phone. Give
                 each option its own `icon` and the trigger's icon then reports
                 the current value — that IS the readout, which is why the text
                 can go. The menu still spells every option out.
                 Per-option icons: ['paid' => ['label' => 'Paid', 'icon' => 'circle-check']]

     Note: click.outside uses the .capture modifier, not plain .outside.
     Modals wrap their form in @click.stop (so clicking inside doesn't close
     the modal itself) — a plain, bubble-phase click.outside listener gets
     swallowed by that stopPropagation() before it ever reaches document, so
     the dropdown never closes. .capture runs before the click reaches its
     target at all, so it fires regardless, and it also closes any other
     open dropdown before a second one's own @click handler opens it (capture
     goes top-down first). Keep this modifier on every hand-rolled dropdown
     too — see the same fix applied to the .he-picker in
     admin/finance/index.blade.php. --}}
@props([
    'name',
    'options' => [], // filter: ['val' => 'Label']  |  status: ['val' => ['label' => .., 'color' => ..]]
    'selected' => null,
    'variant' => 'filter', // 'filter' | 'status'
    'icon' => 'filter',
    'label' => null,
    'submit' => true,
    'compact' => false,
    'searchable' => false,
    'iconOnlyMobile' => false,
    'placeholder' => '— Select —',
])
@php
    // Per-option `icon` is optional and defaults to null (NOT to the component's
    // $icon): the trigger falls back to $icon itself, but a null here lets the
    // menu skip the <i> entirely rather than render fa-undefined.
    $normalized = collect($options)->mapWithKeys(function ($opt, $key) {
        return is_array($opt)
            ? [(string) $key => ['label' => $opt['label'] ?? $key, 'color' => $opt['color'] ?? 'secondary', 'icon' => $opt['icon'] ?? null]]
            : [(string) $key => ['label' => $opt, 'color' => 'secondary', 'icon' => null]];
    });
    $current = $normalized->get((string) $selected);
    $currentLabel = $current['label'] ?? $placeholder;
    $currentColor = $current['color'] ?? 'secondary';
    // $nextTick: let Alpine flush the x-model binding into the hidden <input>
    // before we submit — otherwise the form posts the *stale* value (the field
    // reads empty and the filter appears to do nothing / "just refresh").
    //
    // requestSubmit(), NOT submit(): the DOM's form.submit() deliberately skips
    // the 'submit' event entirely, so any interception (fragment filtering via
    // data-fragment, validation, confirm dialogs) is bypassed and the page hard
    // -navigates. requestSubmit() fires the event like a real button press, so
    // a filter form can opt into partial refresh just by adding data-fragment.
    // It also runs native validation, which submit() skips.
    $submitJs = $submit ? '$nextTick(() => $el.closest(\'form\')?.requestSubmit());' : '';
@endphp
<div
    {{ $attributes->class(['he-select-wrap', 'he-select--icon-mobile' => $iconOnlyMobile]) }}
    x-data="{
        open: false,
        search: '',
        value: {{ \Illuminate\Support\Js::from((string) $selected) }},
        opts: {{ \Illuminate\Support\Js::from($normalized) }},
        placeholder: {{ \Illuminate\Support\Js::from($placeholder) }},
        // Derived from value so an EXTERNAL change (parent x-model, e.g. an edit
        // modal prefilling via openEdit) reflects in the trigger — a plain state
        // var would only update on our own select().
        get displayLabel() { const o = this.opts[this.value]; return o ? o.label : this.placeholder; },
        get displayColor() { const o = this.opts[this.value]; return o ? o.color : 'secondary'; },
        // The current option's own icon, else the component's default — this is
        // the whole readout once .he-select--icon-mobile drops the text.
        get displayIcon() { const o = this.opts[this.value]; return (o && o.icon) ? o.icon : {{ \Illuminate\Support\Js::from($icon) }}; },
        get filteredOptions() {
            const entries = Object.entries(this.opts);
            if (!this.search) return entries;
            const q = this.search.toLowerCase();
            return entries.filter(([val, o]) => o.label.toLowerCase().includes(q));
        },
        select(val) {
            this.value = val;
            this.open = false;
            this.search = '';
            // Bridges the value out to an ancestor's own x-data scope (e.g. a
            // form toggling conditional fields on this select's value) — the
            // hidden input's x-model updates the DOM but doesn't dispatch a
            // native input/change event, so a listener on it wouldn't fire.
            this.$dispatch('he-select-change', { name: {{ \Illuminate\Support\Js::from($name) }}, value: val, label: this.displayLabel });
            {{ $submitJs }}
        },
        toggle() {
            this.open = !this.open;
            // $nextTick: the menu must be VISIBLE before it can be measured
            // (§4.7 — a menu opens into the space that exists, it never makes
            // the page grow a scrollbar to fit it).
            if (this.open) this.$nextTick(() => {
                window.hePlaceMenu?.(this.$el, this.$refs.menu);
                this.$refs.searchInput?.focus();
            });
        },
    }"
    x-modelable="value"
    {{-- is-filtered: a non-empty value is selected. An icon-only trigger can't
         say "a filter is active" on its own, so the border does. --}}
    :class="{ 'is-open': open, 'is-filtered': value !== '' && value !== null }"
    @click.outside.capture="open = false"
>
    <input type="hidden" name="{{ $name }}" x-model="value">

    @if($variant === 'status')
        <button type="button" @click="toggle()"
            class="he-select-trigger badge rounded-pill px-3 py-2 border-0"
            :class="`bg-${displayColor}-subtle text-${displayColor}`">
            <span x-text="displayLabel"></span>
            <i class="fa-solid fa-chevron-down ms-1" style="font-size: 0.65em;"></i>
        </button>
    @elseif($compact)
        <button type="button" @click="toggle()" class="he-select-trigger he-select--compact form-select text-start">
            <span x-text="displayLabel"></span>
        </button>
    @else
        <div @click="toggle()" class="he-select-trigger he-select--filter">
            <span class="he-select-icon"><i class="fa-solid" :class="'fa-' + displayIcon"></i></span>
            {{-- No Bootstrap display utilities here: `d-flex` is
                 `display: flex !important` and silently beat the
                 `display: none` that icon-only-mobile relies on. .he-select-text
                 owns its own layout in _premium.scss. --}}
            <span class="he-select-text">
                @if($label)<span class="he-select-label">{{ $label }}</span>@endif
                <span class="fw-semibold" x-text="displayLabel"></span>
            </span>
            <i class="fa-solid fa-chevron-down ms-1 small text-muted he-select-chevron"></i>
        </div>
    @endif

    <div class="he-select-menu @if($compact) he-select-menu--compact @endif" x-ref="menu" x-show="open" x-transition.opacity x-cloak style="display: none;">
        @if($searchable)
            <div class="p-2 border-bottom">
                <input type="text" x-model="search" x-ref="searchInput"
                    class="form-control form-control-sm bg-light border-0" placeholder="Search...">
            </div>
        @endif
        <div @if($searchable) style="max-height: 220px; overflow-y: auto;" @endif>
            <template x-for="[val, opt] in filteredOptions" :key="val">
                <button type="button" class="he-select-option" :class="{ active: value === val }" @click="select(val)">
                    <span class="d-flex align-items-center gap-2" style="min-width: 0;">
                        {{-- Teaches the icon→value mapping the collapsed
                             trigger relies on. Skipped when an option has no
                             icon of its own. --}}
                        <i class="fa-solid text-muted" style="width: 1em;" :class="'fa-' + opt.icon" x-show="opt.icon"></i>
                        <span class="text-truncate" x-text="opt.label"></span>
                    </span>
                    <i class="fa-solid fa-check" x-show="value === val"></i>
                </button>
            </template>
            <div x-show="filteredOptions.length === 0" class="p-3 text-center text-muted small">No results</div>
        </div>
    </div>
</div>
