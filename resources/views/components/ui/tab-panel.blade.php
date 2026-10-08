@aware(['items' => [], 'selected' => null])

@props([
    'name',
    'tabsId',
])

@php
    $isActive = $name === ($selected ?? ($items[0]['id'] ?? null));
@endphp

<div
    role="tabpanel"
    id="{{ $tabsId }}-panel-{{ $name }}"
    aria-labelledby="{{ $tabsId }}-tab-{{ $name }}"
    tabindex="0"
    x-show="isSelected(@js($name))"
    @unless ($isActive) x-cloak @endunless
    {{ $attributes->class('rounded-control focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus') }}
>
    {{ $slot }}
</div>
