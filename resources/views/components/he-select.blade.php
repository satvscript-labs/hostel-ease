{{-- Premium dropdown — replaces raw select elements. See
     .agents/ui_design_guidelines.md section 4 for variants and full usage
     examples. By default, picking an option submits the closest form
     (matches the existing filter-bar convention) — pass submit=false for a
     select that's just one field in a larger form (e.g. inside a
     create/edit modal).

     variant: 'filter' (icon + micro-label pill) | 'status' (colored badge)
     compact: true renders a slim, native-select-height trigger instead of
              the taller filter-pill — use inside dense forms/modals.
     searchable: true adds a search box inside the dropdown menu, for long
                 option lists (e.g. picking a student). --}}
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
    $submitJs = $submit ? '$el.closest(\'form\').submit();' : '';
@endphp
<div
    {{ $attributes->class(['he-select-wrap']) }}
    x-data="{
        open: false,
        search: '',
        value: {{ \Illuminate\Support\Js::from((string) $selected) }},
        label: {{ \Illuminate\Support\Js::from($currentLabel) }},
        color: {{ \Illuminate\Support\Js::from($currentColor) }},
        opts: {{ \Illuminate\Support\Js::from($normalized) }},
        get filteredOptions() {
            const entries = Object.entries(this.opts);
            if (!this.search) return entries;
            const q = this.search.toLowerCase();
            return entries.filter(([val, o]) => o.label.toLowerCase().includes(q));
        },
        select(val) {
            this.value = val;
            this.label = this.opts[val] ? this.opts[val].label : '';
            this.color = this.opts[val] ? this.opts[val].color : 'secondary';
            this.open = false;
            this.search = '';
            {{ $submitJs }}
        },
        toggle() {
            this.open = !this.open;
            if (this.open) this.$nextTick(() => this.$refs.searchInput?.focus());
        },
    }"
    :class="{ 'is-open': open }"
    @click.outside="open = false"
>
    <input type="hidden" name="{{ $name }}" x-model="value">

    @if($variant === 'status')
        <button type="button" @click="toggle()"
            class="he-select-trigger badge rounded-pill px-3 py-2 border-0"
            :class="`bg-${color}-subtle text-${color}`">
            <span x-text="label"></span>
            <i class="fa-solid fa-chevron-down ms-1" style="font-size: 0.65em;"></i>
        </button>
    @elseif($compact)
        <button type="button" @click="toggle()" class="he-select-trigger he-select--compact form-select text-start">
            <span x-text="label"></span>
        </button>
    @else
        <div @click="toggle()" class="he-select-trigger he-select--filter">
            <span class="he-select-icon"><i class="fa-solid fa-{{ $icon }}"></i></span>
            <span class="d-flex flex-column align-items-start" style="line-height: 1.15;">
                @if($label)<span class="he-select-label">{{ $label }}</span>@endif
                <span class="fw-semibold" x-text="label"></span>
            </span>
            <i class="fa-solid fa-chevron-down ms-1 small text-muted"></i>
        </div>
    @endif

    <div class="he-select-menu" x-show="open" x-transition.opacity x-cloak style="display: none;">
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
