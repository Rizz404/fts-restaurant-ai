@props([
    'variant' => 'primary',
    'size' => 'md',
    'type' => 'button',
    'loading' => false,
    'disabled' => false,
])

@php
    $type = in_array($type, ['button', 'submit', 'reset'], true) ? $type : 'button';
@endphp

<button
    type="{{ $type }}"
    {{ $attributes->class(\App\Support\Ui\ComponentStyles::button($variant, $size)) }}
    @disabled($disabled || $loading)
    @if ($loading) aria-busy="true" @endif
>
    @if ($loading)
        <x-ui.spinner size="sm" />
    @endif
    {{ $slot }}
</button>
