@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'prefix' => null,
    'hint' => null,
    'placeholder' => null,
    'autocomplete' => null,
    'inputmode' => null,
    'maxlength' => null,
    'minlength' => null,
    'autofocus' => false,
    'required' => true,
])

{{-- One guest field: label, input, optional prefix, show/hide for passwords, hint, and
     its OWN error message underneath — wired for screen readers (aria-invalid +
     aria-describedby). Login used to show only the first error at the top of the
     form, so a mistake in one field read like a problem with all of them. --}}
@php
    $id = 'f-'.$name;
    $error = $errors->first($name);
    $describedBy = trim(($error ? $id.'-error ' : '').($hint ? $id.'-hint' : ''));
    $isPassword = $type === 'password';
@endphp

<div class="g-field" @if($isPassword) x-data="{ show: false, caps: false }" @endif>
    <label for="{{ $id }}" class="g-label">{{ $label }}</label>

    <div class="g-control {{ $error ? 'is-invalid' : '' }}">
        @if($prefix)<span class="g-prefix" aria-hidden="true">{{ $prefix }}</span>@endif

        <input id="{{ $id }}" name="{{ $name }}" class="g-input"
               @if($isPassword) :type="show ? 'text' : 'password'" type="password" @else type="{{ $type }}" @endif
               @if(! $isPassword && $value !== null) value="{{ $value }}" @endif
               @if($placeholder) placeholder="{{ $placeholder }}" @endif
               @if($autocomplete) autocomplete="{{ $autocomplete }}" @endif
               @if($inputmode) inputmode="{{ $inputmode }}" @endif
               @if($maxlength) maxlength="{{ $maxlength }}" @endif
               @if($minlength) minlength="{{ $minlength }}" @endif
               @if($required) required @endif
               @if($autofocus) autofocus @endif
               @if($error) aria-invalid="true" @endif
               @if($describedBy) aria-describedby="{{ $describedBy }}" @endif
               @if($isPassword) @keyup="caps = $event.getModifierState && $event.getModifierState('CapsLock')" @blur="caps = false" @endif
               {{ $attributes }}>

        @if($isPassword)
            <button type="button" class="g-reveal" @click="show = !show"
                    :aria-label="show ? @js(__('Hide password')) : @js(__('Show password'))" :aria-pressed="show.toString()">
                <i class="fa-regular" :class="show ? 'fa-eye-slash' : 'fa-eye'"></i>
            </button>
        @endif
    </div>

    @if($isPassword)
        <div class="g-hint" x-show="caps" x-cloak style="color:#b45309;"><i class="fa-solid fa-arrow-up me-1"></i>{{ __('Caps Lock is on.') }}</div>
    @endif

    @if($error)
        <div id="{{ $id }}-error" class="g-error"><i class="fa-solid fa-circle-exclamation"></i><span>{{ $error }}</span></div>
    @elseif($hint)
        <div id="{{ $id }}-hint" class="g-hint">{{ $hint }}</div>
    @endif
</div>
