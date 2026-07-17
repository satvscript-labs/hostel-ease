{{-- Entity picker — the canonical "search a list of records and pick one"
     control (students, staff, ...). Distinct from <x-he-select>: that one is
     for a short list of fixed VALUES (a status, a category); this one is for a
     long list of ENTITIES that need searching and a richer row (avatar +
     name + a secondary line).

     Renders the .he-picker markup from _premium.scss: a compact trigger that
     opens an in-flow panel (search box + scrollable list) BELOW the field
     rather than as a floating menu — so it never clips inside a modal.

     Extracted W5 from admin/finance/index.blade.php's hand-rolled New-Invoice
     student picker, which BRD §1b names as the reference pattern. Front Desk
     needed the same control twice (Add Visitor "Visiting Student" + Log
     Complaint "Reported By"), which is what promoted it from a page-local
     pattern to a component. Front Desk's own `searchableSelect` + its
     page-local .search-select-* CSS are retired by it.

     options: [ ['id' => 1, 'name' => 'Amit Shah', 'sub' => '+919000000100'], ... ]
              `sub` is optional (renders a muted second line).
              `tag` is optional (a short amber chip on the right of the row —
              a state the picker should surface at choosing time, e.g. a
              student who already holds a deposit). It also rides along in the
              `he-picker-change` payload's `item`, so a parent scope can react.
     none:    label for the "no selection" row (e.g. "— General Visit —").
              Omit to make the picker selection-only (no clear row).

     Dispatches `he-picker-change` ({ name, value, item }) on select, mirroring
     <x-he-select>'s `he-select-change` bridge — so a parent x-data scope can
     react (conditional fields) without reaching into this component's scope.

     Note: @click.outside uses .capture, not plain .outside — a modal wrapping
     its form in @click.stop swallows bubble-phase listeners before they reach
     document, so a plain .outside picker would never close. Same law as
     <x-he-select>. --}}
@props([
    'name',
    'options' => [],
    'selected' => null,
    'placeholder' => '— Select —',
    'none' => null,
    'searchPlaceholder' => 'Search…',
    'emptyText' => 'No matches',
])
@php
    $items = collect($options)->map(fn ($o) => [
        'id' => (string) ($o['id'] ?? ''),
        'name' => (string) ($o['name'] ?? ''),
        'sub' => isset($o['sub']) ? (string) $o['sub'] : null,
        'tag' => isset($o['tag']) && filled($o['tag']) ? (string) $o['tag'] : null,
    ])->values();
@endphp
<div
    {{ $attributes->class(['he-picker']) }}
    x-data="{
        open: false,
        search: '',
        value: {{ \Illuminate\Support\Js::from((string) ($selected ?? '')) }},
        items: {{ \Illuminate\Support\Js::from($items) }},
        noneLabel: {{ \Illuminate\Support\Js::from($none) }},
        placeholder: {{ \Illuminate\Support\Js::from($placeholder) }},
        // Derived from value (not a stored copy) so an external change to the
        // model reflects in the trigger — same reasoning as he-select.
        get selectedItem() { return this.items.find(i => i.id === this.value) ?? null; },
        get displayLabel() {
            if (this.selectedItem) return this.selectedItem.name;
            if (this.value === '' && this.noneLabel) return this.noneLabel;
            return this.placeholder;
        },
        get filtered() {
            if (!this.search) return this.items;
            const q = this.search.toLowerCase();
            return this.items.filter(i =>
                i.name.toLowerCase().includes(q) || (i.sub ?? '').toLowerCase().includes(q)
            );
        },
        select(val) {
            this.value = val;
            this.open = false;
            this.search = '';
            this.$dispatch('he-picker-change', {
                name: {{ \Illuminate\Support\Js::from($name) }},
                value: val,
                item: this.selectedItem,
            });
        },
        toggle() {
            this.open = !this.open;
            // $nextTick: the panel must be visible before it can be measured
            // (§4.7 — a panel opens into the space that exists; it never makes
            // the page grow a scrollbar to fit it). This one is tall, so near
            // the bottom of a viewport it flips above the trigger.
            if (this.open) this.$nextTick(() => {
                window.hePlaceMenu?.(this.$el, this.$refs.pickerPanel);
                this.$refs.pickerSearch?.focus();
            });
        },
    }"
    x-modelable="value"
    :class="{ 'is-open': open }"
    @click.outside.capture="open = false"
>
    <input type="hidden" name="{{ $name }}" x-model="value">

    <button type="button" class="he-picker-trigger" @click="toggle()">
        <span class="d-flex align-items-center gap-2 text-truncate">
            <template x-if="selectedItem">
                <span class="he-picker-avatar" style="width: 28px; height: 28px; font-size: 0.8rem;"
                      x-text="selectedItem.name.charAt(0).toUpperCase()"></span>
            </template>
            <span class="text-truncate" :class="selectedItem ? 'fw-semibold text-dark' : 'text-muted'"
                  x-text="displayLabel"></span>
        </span>
        <i class="fa-solid fa-chevron-down small chevron"></i>
    </button>

    <div class="he-picker-panel" x-ref="pickerPanel" x-show="open" x-transition.opacity x-cloak style="display: none;">
        <div class="he-picker-search">
            <input type="text" x-model="search" x-ref="pickerSearch"
                   class="form-control form-control-sm bg-light border-0"
                   placeholder="{{ $searchPlaceholder }}">
        </div>
        <div class="he-picker-list">
            @if($none !== null)
                <button type="button" class="he-picker-option" @click="select('')" x-show="!search">
                    <span class="he-picker-avatar" style="background: var(--he-bg-surface-raised); color: var(--he-text-muted);">
                        <i class="fa-solid fa-minus small"></i>
                    </span>
                    <span class="flex-grow-1 text-muted fw-semibold" style="min-width: 0;">{{ $none }}</span>
                    <i class="fa-solid fa-check text-primary" x-show="value === ''"></i>
                </button>
            @endif
            <template x-for="i in filtered" :key="i.id">
                <button type="button" class="he-picker-option" @click="select(i.id)">
                    <span class="he-picker-avatar" x-text="i.name.charAt(0).toUpperCase()"></span>
                    <span class="flex-grow-1" style="min-width: 0;">
                        <span class="d-block fw-bold text-dark text-truncate" x-text="i.name"></span>
                        <template x-if="i.sub">
                            <span class="d-block small text-muted text-truncate" x-text="i.sub"></span>
                        </template>
                    </span>
                    {{-- A state worth knowing BEFORE you pick, not after. --}}
                    <template x-if="i.tag">
                        <span class="he-picker-tag" x-text="i.tag"></span>
                    </template>
                    <i class="fa-solid fa-check text-primary" x-show="value === i.id"></i>
                </button>
            </template>
            <div class="he-picker-empty" x-show="filtered.length === 0">{{ $emptyText }}</div>
        </div>
    </div>
</div>
