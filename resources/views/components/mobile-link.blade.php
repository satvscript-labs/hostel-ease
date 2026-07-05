@props(['mobile' => null, 'label' => null, 'showCall' => true])
@php($phone = hostelease_phone($mobile))
@if($phone)
    <span {{ $attributes->merge(['class' => 'text-nowrap']) }}>
        @if($showCall)
            <a href="tel:{{ $phone }}" class="text-decoration-none">{{ $label ?? $phone }}</a>
        @else
            {{ $label ?? $phone }}
        @endif
        <a href="{{ hostelease_whatsapp_link($mobile) }}" target="_blank" rel="noopener"
           class="text-success ms-1" title="WhatsApp"><i class="fa-brands fa-whatsapp"></i></a>
    </span>
@else
    <span class="text-muted">—</span>
@endif

