{{-- The one canonical stat-tile pattern (renders .bento-card markup).
     Retires .glass-tile / .stat-card-glass / .stat-card-complaints-* /
     .stat-card-visitors, which were never-defined, unstyled class names.
     Full usage examples: .agents/ui_design_guidelines.md --}}
@props([
    'icon' => 'circle-info',
    'label' => '',
    'value' => '',
    'variant' => '', // any of: hero, c2, r2 (space-separated to combine, e.g. "hero c2")
    'color' => 'primary', // primary|accent|success|warning|danger|info
])
@php
    $isHero = str_contains($variant, 'hero');
    $iconStyle = $isHero
        ? 'background: rgba(255,255,255,0.2); color: #fff;'
        : "background: var(--he-{$color}-soft); color: var(--he-{$color});";
@endphp
<div {{ $attributes->class(['bento-card', $variant]) }}>
    <div class="bento-icon mb-2" style="{{ $iconStyle }}">
        <i class="fa-solid fa-{{ $icon }}"></i>
    </div>
    <div class="bento-value">{{ $value }}</div>
    <div class="bento-label">{{ $label }}</div>
    {{ $slot ?? '' }}
</div>
