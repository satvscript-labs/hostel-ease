{{-- Shimmer skeleton placeholder for async/heavy content. Uses .skeleton
     from _premium.scss. Full usage examples: .agents/ui_design_guidelines.md --}}
@props([
    'rows' => 1,
    'height' => '20px',
    'gap' => '0.6rem',
])
<div {{ $attributes->class(['d-flex flex-column']) }} style="gap: {{ $gap }};" aria-hidden="true">
    @for($i = 0; $i < $rows; $i++)
        <div class="skeleton w-100" style="height: {{ $height }};"></div>
    @endfor
</div>
