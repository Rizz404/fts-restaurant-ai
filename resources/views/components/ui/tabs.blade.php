{{-- items: list of ['id' => ..., 'label' => ...]. Put x-ui.tab-panel elements in the default slot. --}}
@props([
    'id',
    'items' => [],
    'selected' => null,
])

@php
    $active = $selected ?? ($items[0]['id'] ?? null);
@endphp

<div x-data="tabs(@js($id), @js($active))" {{ $attributes }}>
    <div
        x-ref="tablist"
        role="tablist"
        x-on:keydown="onKeydown($event)"
        class="flex gap-1 overflow-x-auto border-b border-border"
    >
        @foreach ($items as $item)
            @php $isActive = $item['id'] === $active; @endphp
            <button
                type="button"
                role="tab"
                id="{{ $id }}-tab-{{ $item['id'] }}"
                data-tab="{{ $item['id'] }}"
                aria-controls="{{ $id }}-panel-{{ $item['id'] }}"
                aria-selected="{{ $isActive ? 'true' : 'false' }}"
                x-bind:aria-selected="isSelected(@js($item['id']))"
                tabindex="{{ $isActive ? '0' : '-1' }}"
                x-bind:tabindex="isSelected(@js($item['id'])) ? 0 : -1"
                x-on:click="select(@js($item['id']))"
                class="-mb-px border-b-2 px-4 py-2 text-body font-medium whitespace-nowrap transition-colors duration-150 motion-reduce:transition-none focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                x-bind:class="isSelected(@js($item['id'])) ? 'border-primary text-primary' : 'border-transparent text-muted hover:text-text'"
            >
                {{ $item['label'] }}
            </button>
        @endforeach
    </div>

    <div class="pt-4">
        {{ $slot }}
    </div>
</div>
