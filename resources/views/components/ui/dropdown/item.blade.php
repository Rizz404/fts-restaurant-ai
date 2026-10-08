{{--
    Use href for navigation (native link). Without href it is a button: type="button" closes the
    menu for local UI actions; type="submit" submits the surrounding form (e.g. logout).
--}}
@props([
    'href' => null,
    'icon' => null,
    'disabled' => false,
    'type' => 'button',
])

@php
    $classes = 'flex w-full items-center gap-2 rounded-control px-3 py-2 text-left text-body text-text hover:bg-surface-muted focus:bg-surface-muted focus:outline-none disabled:cursor-not-allowed disabled:opacity-60 aria-disabled:cursor-not-allowed aria-disabled:opacity-60';
    $type = $type === 'submit' ? 'submit' : 'button';
@endphp

@if ($href !== null && ! $disabled)
    <a href="{{ $href }}" role="menuitem" tabindex="-1" {{ $attributes->class($classes) }}>
        @if ($icon)<x-ui.icon :name="$icon" size="sm" class="text-muted" />@endif
        {{ $slot }}
    </a>
@else
    <button
        type="{{ $type }}"
        role="menuitem"
        tabindex="-1"
        @if ($disabled) aria-disabled="true" disabled @elseif ($type === 'button') x-on:click="close()" @endif
        {{ $attributes->class($classes) }}
    >
        @if ($icon)<x-ui.icon :name="$icon" size="sm" class="text-muted" />@endif
        {{ $slot }}
    </button>
@endif
