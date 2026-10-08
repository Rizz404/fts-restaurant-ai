@props([
    'href' => null,
    'variant' => 'primary',
    'size' => 'md',
    'disabled' => false,
])

@php
    $classes = \App\Support\Ui\ComponentStyles::button($variant, $size);
@endphp

@if ($disabled || $href === null)
    <span role="link" aria-disabled="true" {{ $attributes->class($classes) }}>{{ $slot }}</span>
@else
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@endif
