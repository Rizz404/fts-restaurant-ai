{{--
    Dish card inside the chat. Include inside an Alpine scope where the card data is bound to $bind
    ("card" or "dish"). Allergen and diet labels are only filled for dishes the server marked verified.
--}}
@php
    $c = $bind ?? 'card';
@endphp

<a
    x-bind:href="{{ $c }}.path"
    class="block rounded-control border border-border bg-surface px-3 py-2 hover:border-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus"
>
    <span class="flex items-start justify-between gap-2">
        <span class="block break-words text-body font-medium text-text" x-text="{{ $c }}.name"></span>
        <span class="shrink-0 text-body font-semibold text-text" x-text="{{ $c }}.price"></span>
    </span>
    <span class="mt-1 flex flex-wrap gap-1">
        <span x-show="! {{ $c }}.available" x-bind:class="badge('danger')">{{ __('menu.sold_out') }}</span>
        <span x-show="{{ $c }}.chefPick" x-bind:class="badge('info')">{{ __('menu.chef_pick') }}</span>
        <template x-for="label in {{ $c }}.dietLabels" x-bind:key="label">
            <span x-bind:class="badge('success')" x-text="label"></span>
        </template>
    </span>
    <span class="mt-1 block break-words text-caption text-muted" x-text="{{ $c }}.description"></span>
    <span x-show="{{ $c }}.verified && {{ $c }}.containsLabels.length" class="mt-1 block text-caption text-text">
        <b>{{ __('menu.contains') }}:</b> <span x-text="{{ $c }}.containsLabels.join(', ')"></span>
    </span>
    <span x-show="{{ $c }}.verified && {{ $c }}.mayContainLabels.length" class="mt-1 block text-caption text-text">
        <b>{{ __('menu.may_contain') }}:</b> <span x-text="{{ $c }}.mayContainLabels.join(', ')"></span>
    </span>
    <span x-show="! {{ $c }}.verified" class="mt-1 block text-caption text-warning">{{ __('menu.allergen_unverified') }}</span>
</a>
