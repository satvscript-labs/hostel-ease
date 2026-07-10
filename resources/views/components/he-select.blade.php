{{-- Premium dropdown — replaces raw select elements. See
     .agents/ui_design_guidelines.md section 4 for the two variants (filter
     pill / status pill) and full usage examples. By default, picking an
     option submits the closest form (matches the existing filter-bar
     convention) — pass submit=false for a select that's just one field in
     a larger form (e.g. inside a create/edit modal). --}}
@props([
    'name',
    'options' => [], // filter: ['val' => 'Label']  |  status: ['val' => ['label' => .., 'color' => ..]]
    'selected' => null,
    'variant' => 'filter', // 'filter' | 'status'
    'icon' => 'filter',
    'label' => null,
    'submit' => true,
])
@php
    $normalized = collect($options)->mapWithKeys(function ($opt, $key) {
        return is_array($opt)
            ? [(string) $key => ['label' => $opt['label'] ?? $key, 'color' => $opt['color'] ?? 'secondary']]
            : [(string) $key => ['label' => $opt, 'color' => 'secondary']];
    });
    $current = $normalized->get((string) $selected) ?? $normalized->first() ?? ['label' => '', 'color' => 'secondary'];
    $submitJs = $submit ? '$el.closest(\'form\').submit();' : '';
@endphp
<div
    {{ $attributes->class(['he-select-wrap']) }}
    x-data="{
        open: false,
        value: {{ \Illuminate\Support\Js::from((string) $selected) }},
        label: {{ \Illuminate\Support\Js::from($current['label']) }},
        color: {{ \Illuminate\Support\Js::from($current['color']) }},
    }"
    :class="{ 'is-open': open }"
    @click.outside="open = false"
>
    <input type="hidden" name="{{ $name }}" x-model="value">

    @if($variant === 'status')
        <button type="button" @click="open = !open"
            class="he-select-trigger badge rounded-pill px-3 py-2 border-0"
            :class="`bg-${color}-subtle text-${color}`">
            <span x-text="label"></span>
            <i class="fa-solid fa-chevron-down ms-1" style="font-size: 0.65em;"></i>
        </button>
    @else
        <div @click="open = !open" class="he-select-trigger he-select--filter">
            <span class="he-select-icon"><i class="fa-solid fa-{{ $icon }}"></i></span>
            <span class="d-flex flex-column align-items-start" style="line-height: 1.15;">
                @if($label)<span class="he-select-label">{{ $label }}</span>@endif
                <span class="fw-semibold" x-text="label"></span>
            </span>
            <i class="fa-solid fa-chevron-down ms-1 small text-muted"></i>
        </div>
    @endif

    <div class="he-select-menu" x-show="open" x-transition.opacity x-cloak style="display: none;">
        @foreach($normalized as $val => $opt)
            <button type="button" class="he-select-option"
                :class="{ active: value === {{ \Illuminate\Support\Js::from($val) }} }"
                @click="value = {{ \Illuminate\Support\Js::from($val) }}; label = {{ \Illuminate\Support\Js::from($opt['label']) }}; color = {{ \Illuminate\Support\Js::from($opt['color']) }}; open = false; {{ $submitJs }}">
                <span>{{ $opt['label'] }}</span>
                <i class="fa-solid fa-check" x-show="value === {{ \Illuminate\Support\Js::from($val) }}"></i>
            </button>
        @endforeach
    </div>
</div>
