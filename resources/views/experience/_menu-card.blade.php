{{--
    One dish card. $item is a MenuEvidence: allergen and diet lists are already empty for dishes whose allergen data
    is not verified, so this card can never show an unverified claim. All text is escaped.
--}}
@php
    /** @var \App\Modules\Menu\Data\MenuEvidence $item */
@endphp

<article class="flex flex-col overflow-hidden rounded-card border border-border bg-surface shadow-card" data-menu-item="{{ $item->itemId }}">
    <a href="{{ route('experience.menu.show', $item->slug) }}" class="block focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus" tabindex="-1" aria-hidden="true">
        @if ($item->photoUrl)
            <img src="{{ $item->photoUrl }}" alt="" loading="lazy" decoding="async" class="aspect-4/3 w-full object-cover {{ $item->isAvailable ? '' : 'opacity-60 grayscale' }}">
        @else
            <span class="grid aspect-4/3 w-full place-items-center bg-surface-muted text-muted"><x-ui.icon name="cake" size="lg" /></span>
        @endif
    </a>

    <div class="flex flex-1 flex-col gap-2 p-4">
        <div class="flex flex-wrap items-center gap-2">
            @unless ($item->isAvailable)
                <x-ui.badge tone="danger">{{ __('menu.sold_out') }}</x-ui.badge>
            @endunless
            @if ($item->isChefPick)
                <x-ui.badge tone="info"><x-ui.icon name="star" size="sm" class="size-3!" />{{ __('menu.chef_pick') }}</x-ui.badge>
            @endif
            @foreach ($item->dietaryTags as $tag)
                <x-ui.badge tone="success">{{ __('menu.diet.'.$tag) }}</x-ui.badge>
            @endforeach
        </div>

        <h3 class="text-section-title font-semibold text-text">
            <a href="{{ route('experience.menu.show', $item->slug) }}" class="rounded hover:text-primary hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus">{{ $item->name }}</a>
        </h3>

        <p class="line-clamp-3 text-body text-muted">{{ $item->description }}</p>

        @if ($item->allergenVerified)
            @if ($item->allergens['contains'] !== [] || $item->allergens['may_contain'] !== [])
                <p class="text-caption text-text">
                    @if ($item->allergens['contains'] !== [])
                        <span class="font-medium">{{ __('menu.contains') }}:</span>
                        {{ collect($item->allergens['contains'])->map(fn ($code) => __('menu.allergen.'.$code))->implode(', ') }}.
                    @endif
                    @if ($item->allergens['may_contain'] !== [])
                        <span class="font-medium">{{ __('menu.may_contain') }}:</span>
                        {{ collect($item->allergens['may_contain'])->map(fn ($code) => __('menu.allergen.'.$code))->implode(', ') }}.
                    @endif
                </p>
            @else
                <p class="text-caption text-muted">{{ __('menu.no_listed_allergens') }}</p>
            @endif
        @else
            <p class="flex items-start gap-1 text-caption text-warning">
                <x-ui.icon name="exclamation-triangle" size="sm" class="mt-px" />
                <span>{{ __('menu.allergen_unverified') }}</span>
            </p>
        @endif

        <div class="mt-auto flex items-center justify-between gap-2 pt-2">
            <p class="text-body font-semibold text-text">{{ $item->priceFormatted() }}</p>
            <div class="flex flex-wrap items-center justify-end gap-2">
                <button
                    type="button"
                    class="prompt focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                    x-data
                    x-on:click="$dispatch('chat:ask', { message: @js(__('menu.ask_about', ['dish' => $item->name])), context: { menu_item_id: {{ $item->itemId }} } })"
                >
                    <x-ui.icon name="sparkles" size="sm" />
                    <span>{{ __('menu.ask_ai_short') }}</span>
                </button>
                @if ($item->isAvailable)
                    <x-ui.button size="sm" x-data x-on:click="$dispatch('cart:add', { id: {{ $item->itemId }}, message: {{ \Illuminate\Support\Js::from(__('menu.added_to_order', ['dish' => $item->name])) }} })">
                        <x-ui.icon name="plus" size="sm" />
                        {{ __('menu.add_to_order') }}
                    </x-ui.button>
                @endif
            </div>
        </div>
    </div>
</article>
