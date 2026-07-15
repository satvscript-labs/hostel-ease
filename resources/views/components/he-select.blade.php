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
    'placeholder' => '— Select —',
])
@php
    $normalized = collect($options)->mapWithKeys(function ($opt, $key) {
        return is_array($opt)
            ? [(string) $key => ['label' => $opt['label'] ?? $key, 'color' => $opt['color'] ?? 'secondary']]
            : [(string) $key => ['label' => $opt, 'color' => 'secondary']];
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
    {{ $attributes->class(['he-select-wrap']) }}
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
            if (this.open) this.$nextTick(() => this.$refs.searchInput?.focus());
        },
    }"
    x-modelable="value"
    :class="{ 'is-open': open }"
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
            <span class="he-select-icon"><i class="fa-solid fa-{{ $icon }}"></i></span>
            <span class="d-flex flex-column align-items-start" style="line-height: 1.15;">
                @if($label)<span class="he-select-label">{{ $label }}</span>@endif
                <span class="fw-semibold" x-text="displayLabel"></span>
            </span>
            <i class="fa-solid fa-chevron-down ms-1 small text-muted"></i>
        </div>
    @endif

    <div class="he-select-menu @if($compact) he-select-menu--compact @endif" x-show="open" x-transition.opacity x-cloak style="display: none;">
        @if($searchable)
            <div class="p-2 border-bottom">
                <input type="text" x-model="search" x-ref="searchInput"
                    class="form-control form-control-sm bg-light border-0" placeholder="Search...">
            </div>
        @endif
        <div @if($searchable) style="max-height: 220px; overflow-y: auto;" @endif>
            <template x-for="[val, opt] in filteredOptions" :key="val">
                <button type="button" class="he-select-option" :class="{ active: value === val }" @click="select(val)">
                    <span x-text="opt.label"></span>
                    <i class="fa-solid fa-check" x-show="value === val"></i>
                </button>
            </template>
            <div x-show="filteredOptions.length === 0" class="p-3 text-center text-muted small">No results</div>
        </div>
    </div>
</div>
