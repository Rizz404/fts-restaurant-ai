@php
    $selectedDiet = $filters->dietaryTags;
    $selectedAllergens = $filters->excludeAllergens;
    $queryString = fn (array $override) => array_filter(array_merge([
        'q' => $filters->query,
        'diet' => $filters->dietaryTags ?: null,
        'exclude_allergens' => $filters->excludeAllergens ?: null,
        'max_price' => $filters->maxPrice,
        'category' => $filters->category,
    ], $override), fn ($value) => $value !== null && $value !== '');
@endphp

<x-restaurant-stage :scene="$scene">
    {{-- Category tabs --}}
    <nav aria-label="{{ __('menu.categories') }}" class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1">
        <a
            href="{{ route('experience.menu', $queryString(['category' => null])) }}"
            @if ($filters->category === null) aria-current="page" @endif
            class="chip focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus"
            aria-pressed="{{ $filters->category === null ? 'true' : 'false' }}"
        >{{ __('ui.all') }}</a>
        @foreach ($categories as $category)
            <a
                href="{{ route('experience.menu', $queryString(['category' => $category->code])) }}"
                @if ($filters->category === $category->code) aria-current="page" @endif
                class="chip whitespace-nowrap focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                aria-pressed="{{ $filters->category === $category->code ? 'true' : 'false' }}"
            >{{ $category->name() }}</a>
        @endforeach
    </nav>

    {{-- Search and filters --}}
    <x-ui.card padding="sm">
        <form method="GET" action="{{ route('experience.menu') }}" role="search" class="grid gap-3">
            @if ($filters->category)
                <input type="hidden" name="category" value="{{ $filters->category }}">
            @endif

            <div class="grid gap-3 md:grid-cols-[1fr_12rem_auto] md:items-end">
                <x-ui.field name="q" id="menu-q" :label="__('ui.search')">
                    <x-ui.input name="q" id="menu-q" type="search" :value="$filters->query" maxlength="200" :placeholder="__('menu.search_placeholder')" />
                </x-ui.field>
                <x-ui.field name="max_price" id="menu-max-price" :label="__('menu.max_price')">
                    <x-ui.input name="max_price" id="menu-max-price" type="number" min="1" step="1000" inputmode="numeric" :value="$filters->maxPrice" />
                </x-ui.field>
                <x-ui.button type="submit">
                    <x-ui.icon name="magnifying-glass" size="sm" />
                    {{ __('ui.search') }}
                </x-ui.button>
            </div>

            <fieldset class="space-y-2">
                <legend class="text-body font-medium text-text">{{ __('menu.fields.dietary_tags') }}</legend>
                <div class="flex flex-wrap gap-x-5 gap-y-2">
                    @foreach (config('restaurant.dietary_tags') as $tag)
                        <label class="inline-flex items-center gap-2 text-body text-text">
                            <input type="checkbox" name="diet[]" value="{{ $tag }}" @checked(in_array($tag, $selectedDiet, true)) class="size-4 rounded border-border text-primary focus:ring-2 focus:ring-focus focus:ring-offset-0">
                            {{ __('menu.diet.'.$tag) }}
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <details class="rounded-control border border-border px-3 py-2" @if ($selectedAllergens !== []) open @endif>
                <summary class="cursor-pointer text-body font-medium text-text focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus">{{ __('menu.exclude_allergens') }}</summary>
                <p class="mt-2 text-caption text-muted">{{ __('menu.exclude_allergens_help') }}</p>
                <div class="mt-2 flex flex-wrap gap-x-5 gap-y-2">
                    @foreach (config('restaurant.allergens') as $code)
                        <label class="inline-flex items-center gap-2 text-body text-text">
                            <input type="checkbox" name="exclude_allergens[]" value="{{ $code }}" @checked(in_array($code, $selectedAllergens, true)) class="size-4 rounded border-border text-primary focus:ring-2 focus:ring-focus focus:ring-offset-0">
                            {{ __('menu.allergen.'.$code) }}
                        </label>
                    @endforeach
                </div>
            </details>
        </form>
    </x-ui.card>

    @if ($unverifiedExcluded > 0)
        <x-ui.alert tone="warning">{{ trans_choice('menu.unverified_excluded', $unverifiedExcluded, ['count' => $unverifiedExcluded]) }}</x-ui.alert>
    @endif

    @if ($items === [])
        <x-ui.empty-state icon="cake" :title="$filters->isEmpty() ? __('menu.empty_title') : __('menu.no_results_title')" :description="$filters->isEmpty() ? __('menu.empty_description') : __('menu.no_results_description')" />
    @else
        @foreach (collect($items)->groupBy('categoryCode') as $code => $group)
            <section aria-labelledby="menu-cat-{{ $code }}" class="space-y-3">
                <h2 id="menu-cat-{{ $code }}" class="text-section-title font-semibold text-text">{{ $group->first()->categoryName }}</h2>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($group as $item)
                        @include('experience._menu-card', ['item' => $item])
                    @endforeach
                </div>
            </section>
        @endforeach
    @endif
</x-restaurant-stage>
