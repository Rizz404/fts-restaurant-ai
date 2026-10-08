{{-- Emits ui:confirmed {name}; it never performs a business write itself. --}}
@props([
    'name',
    'title',
    'confirmLabel' => null,
    'cancelLabel' => null,
    'variant' => 'primary',
])

@php
    $variant = in_array($variant, ['primary', 'danger'], true) ? $variant : 'primary';
@endphp

<x-ui.modal :name="$name" :title="$title" size="sm" {{ $attributes }}>
    {{ $slot }}

    <x-slot:footer>
        <x-ui.button variant="outline" x-on:click="close()">{{ $cancelLabel ?? __('ui.cancel') }}</x-ui.button>
        <x-ui.button :variant="$variant" x-on:click="confirm()">{{ $confirmLabel ?? __('ui.confirm') }}</x-ui.button>
    </x-slot:footer>
</x-ui.modal>
