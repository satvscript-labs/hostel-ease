{{-- Standard teleported overlay modal. See .agents/ui_design_guidelines.md
     section 5 for the full markup spec and usage examples.

     Must be placed inside a parent element that already has an Alpine
     x-data scope declaring the boolean named by the "open" prop (matches
     the existing convention — see admin/expenses/index.blade.php's
     expenseModalOpen). The trigger button elsewhere on the page just
     flips that same boolean to true. Pass a footer slot to override the
     default Cancel/Save buttons. --}}
@props([
    'open' => 'modalOpen',
    'title' => '',
    'icon' => null,
    'action' => null,
    'method' => 'POST',
    'size' => 550,
])
@php
    $tag = $action ? 'form' : 'div';
    $httpMethod = strtoupper($method);
@endphp
<template x-teleport="body">
    <div class="custom-overlay-backdrop" x-show="{{ $open }}" x-transition.opacity @click="{{ $open }} = false" x-cloak style="display: none;">
        <{{ $tag }}
            @if($action) method="{{ $httpMethod === 'GET' ? 'GET' : 'POST' }}" action="{{ $action }}" @endif
            {{ $attributes->class(['custom-overlay-modal']) }}
            :class="{ 'is-open': {{ $open }} }" x-show="{{ $open }}" x-transition.opacity @click.stop
            style="display: none; max-width: {{ $size }}px;"
        >
            @if($action)
                @if($httpMethod !== 'GET')
                    @csrf
                @endif
                @if(!in_array($httpMethod, ['GET', 'POST']))
                    @method($httpMethod)
                @endif
            @endif

            <div class="custom-overlay-header">
                <h5 class="fw-bold mb-0">
                    @if($icon)<i class="fa-solid fa-{{ $icon }} text-primary me-2"></i>@endif
                    {{ $title }}
                </h5>
                <button type="button" class="btn-close" @click="{{ $open }} = false"></button>
            </div>

            <div class="custom-overlay-body">
                {{ $slot }}
            </div>

            <div class="custom-overlay-footer">
                @isset($footer)
                    {{ $footer }}
                @else
                    <button type="button" class="btn btn-light border fw-semibold rounded-pill px-4" @click="{{ $open }} = false">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-semibold rounded-pill px-4 shadow-sm"><i class="fa-solid fa-check me-2"></i> Save</button>
                @endisset
            </div>
        </{{ $tag }}>
    </div>
</template>
