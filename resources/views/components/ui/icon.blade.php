@props([
    'name',
    'size' => 'md',
    'label' => null,
])

@php
    $attrs = $attributes
        ->class([\App\Support\Ui\ComponentStyles::iconSize($size), 'shrink-0'])
        ->merge($label !== null ? ['role' => 'img', 'aria-label' => $label] : ['aria-hidden' => 'true'])
        ->getAttributes();
    $class = $attrs['class'];
    unset($attrs['class']);
@endphp

{{ svg(\App\Support\Ui\ComponentStyles::icon($name), $class, $attrs + ['focusable' => 'false']) }}
