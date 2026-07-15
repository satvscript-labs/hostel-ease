{{-- The canonical stat-tile for NEUTRAL stats (renders .bento-card markup):
     white card, colored icon badge, value, label. Reach for this by default.

     One sanctioned exception — .stat-card-glass + its .stat-card-visitors /
     .stat-card-complaints-* variants (defined in _premium.scss). Those are
     gradient tiles used where the COLOR ITSELF carries meaning at a glance
     (red = open, amber = in progress, green = resolved). Front Desk is their
     only consumer. Confirmed as a keep in W5.

     (An earlier version of this comment claimed those classes were "retired"
     and "never-defined, unstyled class names". Both claims were false — they
     are fully defined in _premium.scss and actively rendering. Corrected W5.)

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
