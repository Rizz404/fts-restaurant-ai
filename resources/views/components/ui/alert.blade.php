@props([
    'tone' => 'info',
    'title' => null,
    'dismissible' => false,
    'role' => 'status',
])

@php
    $role = $role === 'alert' ? 'alert' : 'status';
@endphp

<div
    role="{{ $role }}"
    @if ($dismissible) x-data="{ shown: true }" x-show="shown" @endif
    {{ $attributes->class(\App\Support\Ui\ComponentStyles::alert($tone)) }}
>
    <x-ui.icon :name="\App\Support\Ui\ComponentStyles::alertIcon($tone)" :class="\App\Support\Ui\ComponentStyles::alertIconTone($tone)" />

    <div class="min-w-0 flex-1 space-y-1">
        @if ($title !== null)
            <p class="font-semibold">{{ $title }}</p>
        @endif
        <div>{{ $slot }}</div>
    </div>

    @if ($dismissible)
        <button
            type="button"
            x-on:click="shown = false"
            class="-m-1 rounded-control p-1 text-muted hover:text-text focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus"
        >
            <x-ui.icon name="x-mark" size="sm" :label="__('ui.dismiss')" />
        </button>
    @endif
</div>
