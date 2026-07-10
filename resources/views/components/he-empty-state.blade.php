{{-- Standard empty state (faded icon + title + subtext), per
     .agents/ui_design_guidelines.md section 5. Use for any list/table with zero rows. --}}
@props([
    'icon' => 'circle-info',
    'title' => 'Nothing here yet',
    'subtitle' => null,
])
<div {{ $attributes->class(['text-center py-5']) }}>
    <div class="empty-state">
        <i class="fa-solid fa-{{ $icon }} text-secondary fs-1 mb-3 opacity-25" style="font-size: 4rem !important;"></i>
        <h4 class="fw-bold text-dark">{{ $title }}</h4>
        @if($subtitle)
            <div class="text-secondary">{{ $subtitle }}</div>
        @endif
        {{ $slot ?? '' }}
    </div>
</div>
