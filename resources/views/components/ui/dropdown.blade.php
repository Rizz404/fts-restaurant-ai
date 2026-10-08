{{-- Slots: trigger (button content) and items (x-ui.dropdown.item). trigger-variant uses the button variants. --}}
@props([
    'align' => 'left',
    'triggerVariant' => 'outline',
    'id' => null,
])

@php
    $id ??= 'dropdown-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(8));
    $alignClasses = $align === 'right' ? 'right-0 origin-top-right' : 'left-0 origin-top-left';
@endphp

<div
    x-data="dropdown"
    x-on:click.outside="close(false)"
    x-on:keydown.escape.prevent.stop="close()"
    {{ $attributes->class('relative inline-block text-left') }}
>
    <button
        type="button"
        x-ref="trigger"
        x-on:click="toggle()"
        x-on:keydown.arrow-down.prevent="show()"
        x-on:keydown.arrow-up.prevent="show(true)"
        aria-haspopup="menu"
        aria-controls="{{ $id }}"
        aria-expanded="false"
        x-bind:aria-expanded="open"
        {{ $trigger->attributes->class(\App\Support\Ui\ComponentStyles::button($triggerVariant, 'md')) }}
    >
        {{ $trigger }}
    </button>

    <div
        x-ref="menu"
        id="{{ $id }}"
        role="menu"
        x-show="open"
        x-cloak
        x-transition.opacity.duration.150ms
        x-on:keydown.arrow-down.prevent="move(1)"
        x-on:keydown.arrow-up.prevent="move(-1)"
        x-on:keydown.home.prevent="first()"
        x-on:keydown.end.prevent="last()"
        x-on:keydown.tab="close(false)"
        class="absolute z-30 mt-2 min-w-48 rounded-control border border-border bg-surface p-1 shadow-overlay {{ $alignClasses }}"
    >
        {{ $slot }}
    </div>
</div>
