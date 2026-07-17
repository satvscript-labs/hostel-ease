@props(['staff', 'size' => 44])

{{-- The staff photo, at last. It has been uploaded, compressed and swapped on
     edit since the field was added, and never once rendered — every surface
     drew an initial-letter circle instead (W7.1). Falls back to that initial
     when there is no photo, which is most of them. --}}
@if($staff->photo_url)
    <img src="{{ $staff->photo_url }}" alt="{{ $staff->name }}"
        {{ $attributes->merge(['class' => 'rounded-circle flex-shrink-0 object-fit-cover']) }}
        style="width: {{ $size }}px; height: {{ $size }}px; object-fit: cover;">
@else
    <div {{ $attributes->merge(['class' => 'avatar bg-light text-primary fw-bold rounded-circle d-flex align-items-center justify-content-center flex-shrink-0']) }}
         style="width: {{ $size }}px; height: {{ $size }}px; font-size: {{ round($size * 0.4) }}px;"
         aria-hidden="true">
        {{ mb_substr($staff->name, 0, 1) }}
    </div>
@endif
