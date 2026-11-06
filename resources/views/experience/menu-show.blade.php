<x-restaurant-stage :scene="$scene" :seo="['title' => $item->name, 'description' => $item->description]">
    <div>
        <x-ui.link-button :href="route('experience.menu')" variant="secondary" size="sm">
            <x-ui.icon name="chevron-left" size="sm" />
            {{ __('menu.back_to_menu') }}
        </x-ui.link-button>
    </div>

    <article class="overflow-hidden rounded-card border border-border bg-surface shadow-card" lang="{{ $item->locale }}">
        @if ($item->photoUrl)
            <img src="{{ $item->photoUrl }}" alt="" decoding="async" class="max-h-96 w-full object-cover {{ $item->isAvailable ? '' : 'opacity-60 grayscale' }}">
        @endif

        <div class="space-y-4 p-4 md:p-6">
            <div class="flex flex-wrap items-center gap-2">
                <x-ui.badge tone="neutral">{{ $item->categoryName }}</x-ui.badge>
                @unless ($item->isAvailable)
                    <x-ui.badge tone="danger">{{ __('menu.sold_out') }}</x-ui.badge>
                @endunless
                @if ($item->isChefPick)
                    <x-ui.badge tone="info">{{ __('menu.chef_pick') }}</x-ui.badge>
                @endif
                @foreach ($item->dietaryTags as $tag)
                    <x-ui.badge tone="success">{{ __('menu.diet.'.$tag) }}</x-ui.badge>
                @endforeach
            </div>

            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="text-page-title font-semibold text-text">{{ $item->name }}</h2>
                <p class="text-section-title font-semibold text-text">{{ $item->priceFormatted() }}</p>
            </div>

            <p class="whitespace-pre-line break-words text-body leading-7 text-text">{{ $item->description }}</p>

            @if ($item->spiceLevel !== null)
                <p class="text-body text-muted">{{ __('menu.fields.spice_level') }}: {{ __('menu.spice.'.$item->spiceLevel) }}</p>
            @endif

            <section aria-labelledby="allergen-title" class="space-y-2 rounded-control bg-surface-muted p-3">
                <h3 id="allergen-title" class="text-body font-semibold text-text">{{ __('menu.fields.allergens') }}</h3>
                @if ($item->allergenVerified)
                    @if ($item->allergens['contains'] === [] && $item->allergens['may_contain'] === [])
                        <p class="text-body text-text">{{ __('menu.no_listed_allergens') }}</p>
                    @endif
                    @if ($item->allergens['contains'] !== [])
                        <p class="text-body text-text"><span class="font-medium">{{ __('menu.contains') }}:</span> {{ collect($item->allergens['contains'])->map(fn ($code) => __('menu.allergen.'.$code))->implode(', ') }}</p>
                    @endif
                    @if ($item->allergens['may_contain'] !== [])
                        <p class="text-body text-text"><span class="font-medium">{{ __('menu.may_contain') }}:</span> {{ collect($item->allergens['may_contain'])->map(fn ($code) => __('menu.allergen.'.$code))->implode(', ') }}</p>
                    @endif
                    <p class="text-caption text-muted">{{ __('menu.ask_staff_allergy') }}</p>
                @else
                    <p class="flex items-start gap-1 text-body text-warning">
                        <x-ui.icon name="exclamation-triangle" size="sm" class="mt-0.5" />
                        <span>{{ __('menu.allergen_unverified') }}</span>
                    </p>
                @endif
            </section>

            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    class="prompt focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                    x-data
                    x-on:click="$dispatch('chat:ask', { message: @js(__('menu.ask_about', ['dish' => $item->name])), context: { menu_item_id: {{ $item->itemId }} } })"
                >
                    <x-ui.icon name="sparkles" size="sm" />
                    <span>{{ __('menu.ask_ai') }}</span>
                </button>
                @if ($item->isAvailable)
                    <x-ui.button x-data x-on:click="$dispatch('cart:add', { id: {{ $item->itemId }}, message: {{ \Illuminate\Support\Js::from(__('menu.added_to_order', ['dish' => $item->name])) }} })">
                        <x-ui.icon name="plus" size="sm" />
                        {{ __('menu.add_to_order') }}
                    </x-ui.button>
                @endif
            </div>
        </div>
    </article>
</x-restaurant-stage>
