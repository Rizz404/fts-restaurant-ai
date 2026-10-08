{{--
    Dish fields shared by create and edit. $item is null on create. Allergen and diet changes clear the
    allergen verification on save (shown to the editor in the help text).
--}}
@php
    $creating = $item === null;
    $oldAllergens = old('allergens');
    $currentAllergens = $oldAllergens ?? ($item ? $item->allergens->mapWithKeys(fn ($row) => [$row->allergen_code => $row->relation->value])->all() : []);
    $currentDiet = old('dietary_tags', $item ? $item->dietaryTagCodes() : []);
    $radio = 'size-4 border-border text-primary focus:ring-2 focus:ring-focus focus:ring-offset-0';
@endphp

<div class="space-y-6">
    <div class="grid gap-4 md:grid-cols-2">
        <x-ui.field name="category_id" id="menu-category" :label="__('menu.fields.category')" required>
            <x-ui.select name="category_id" id="menu-category" :options="$categoryOptions" :value="$item?->category_id" :placeholder="__('ui.choose')" required />
        </x-ui.field>

        <x-ui.field name="price" id="menu-price" :label="__('menu.fields.price')" :help="__('menu.price_help', ['currency' => config('restaurant.currency')])">
            <x-ui.input name="price" id="menu-price" type="number" min="0" step="1" :value="$item?->price" inputmode="numeric" />
        </x-ui.field>

        @if ($creating || ! ($slugLocked ?? false))
            <x-ui.field name="slug" id="menu-slug" :label="__('menu.fields.slug')" :help="__('menu.slug_help')">
                <x-ui.input name="slug" id="menu-slug" :value="$item?->slug" maxlength="120" autocomplete="off" />
            </x-ui.field>
        @else
            <div>
                <p class="text-body font-medium text-text">{{ __('menu.fields.slug') }}</p>
                <p class="mt-1"><code class="text-caption">{{ $item->slug }}</code></p>
                <p class="mt-1 text-caption text-muted">{{ __('menu.slug_locked') }}</p>
            </div>
        @endif

        <x-ui.field name="spice_level" id="menu-spice" :label="__('menu.fields.spice_level')">
            <x-ui.select name="spice_level" id="menu-spice" :options="$spiceOptions" :value="$item?->spice_level" :placeholder="__('menu.spice.none')" />
        </x-ui.field>

        <x-ui.field name="sort_order" id="menu-sort" :label="__('menu.fields.sort_order')" :help="__('menu.sort_help')">
            <x-ui.input name="sort_order" id="menu-sort" type="number" min="0" max="9999" :value="$item?->sort_order ?? 0" />
        </x-ui.field>

        <div class="flex flex-col justify-end gap-2">
            <x-ui.checkbox name="is_available" id="menu-available" :checked="$item?->is_available ?? true">{{ __('menu.fields.is_available') }}</x-ui.checkbox>
            <x-ui.checkbox name="is_chef_pick" id="menu-chef-pick" :checked="$item?->is_chef_pick ?? false">{{ __('menu.fields.is_chef_pick') }}</x-ui.checkbox>
        </div>
    </div>

    {{-- Photo --}}
    <div class="grid gap-4 md:grid-cols-[auto_1fr] md:items-start">
        @if ($item?->photoUrl())
            <img src="{{ $item->photoUrl() }}" alt="" class="h-28 w-40 rounded-card border border-border object-cover" loading="lazy">
        @endif
        <div class="space-y-2">
            <x-ui.field name="photo" id="menu-photo" :label="__('menu.fields.photo')" :help="__('menu.photo_help')">
                <x-ui.input name="photo" id="menu-photo" type="file" accept="image/jpeg,image/png,image/webp" />
            </x-ui.field>
            @if ($item?->photo_path)
                <x-ui.checkbox name="remove_photo" id="menu-remove-photo">{{ __('menu.remove_photo') }}</x-ui.checkbox>
            @endif
        </div>
    </div>

    {{-- Diet and allergens --}}
    <fieldset class="space-y-2">
        <legend class="text-body font-medium text-text">{{ __('menu.fields.dietary_tags') }}</legend>
        <div class="flex flex-wrap gap-x-6 gap-y-2">
            @foreach ($dietCodes as $code)
                <x-ui.checkbox name="dietary_tags[]" id="menu-diet-{{ $code }}" :value="$code" :checked="in_array($code, $currentDiet, true)">{{ __('menu.diet.'.$code) }}</x-ui.checkbox>
            @endforeach
        </div>
        @error('dietary_tags')
            <p class="text-caption text-danger">{{ $message }}</p>
        @enderror
    </fieldset>

    <fieldset class="space-y-3">
        <legend class="text-body font-medium text-text">{{ __('menu.fields.allergens') }}</legend>
        <p class="text-caption text-muted">{{ __('menu.allergens_help') }}</p>

        <div class="grid gap-x-6 gap-y-2 md:grid-cols-2">
            @foreach ($allergenCodes as $code)
                @php $selected = $currentAllergens[$code] ?? ''; @endphp
                <div class="flex flex-wrap items-center justify-between gap-2 rounded-control border border-border bg-surface px-3 py-2">
                    <span class="text-body text-text">{{ __('menu.allergen.'.$code) }}</span>
                    <span class="flex flex-wrap gap-x-4 gap-y-1 text-caption text-text">
                        <label class="inline-flex items-center gap-1.5">
                            <input type="radio" class="{{ $radio }}" name="allergens[{{ $code }}]" value="" @checked($selected === '')>
                            {{ __('menu.relation.none') }}
                        </label>
                        @foreach ($relationOptions as $value => $label)
                            <label class="inline-flex items-center gap-1.5">
                                <input type="radio" class="{{ $radio }}" name="allergens[{{ $code }}]" value="{{ $value }}" @checked($selected === $value)>
                                {{ $label }}
                            </label>
                        @endforeach
                    </span>
                </div>
            @endforeach
        </div>
        @error('allergens')
            <p class="text-caption text-danger">{{ $message }}</p>
        @enderror
    </fieldset>

    {{-- Translations --}}
    <div class="space-y-2">
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-body font-medium text-text">{{ __('menu.completeness') }}</span>
            @foreach (config('restaurant.locale_names') as $code => $name)
                <x-ui.badge :tone="($completeness[$code] ?? false) ? 'success' : 'warning'">
                    {{ $name }}: {{ ($completeness[$code] ?? false) ? __('menu.complete') : __('menu.incomplete') }}
                </x-ui.badge>
            @endforeach
        </div>

        <x-ui.tabs
            id="menu-translations"
            :items="collect(config('restaurant.locale_names'))->map(fn ($name, $code) => ['id' => $code, 'label' => $name])->values()->all()"
            :selected="collect(config('restaurant.locales'))->first(fn ($code) => $errors->has('translations.'.$code.'.name') || $errors->has('translations.'.$code.'.description') || $errors->has('translations.'.$code.'.aliases'))"
        >
            @foreach (config('restaurant.locale_names') as $code => $name)
                @php $translation = $item?->translations->firstWhere('locale', $code); @endphp
                <x-ui.tab-panel :name="$code" tabs-id="menu-translations">
                    <div class="grid gap-4" lang="{{ $code }}">
                        <x-ui.field name="translations[{{ $code }}][name]" id="menu-{{ $code }}-name" :label="__('menu.fields.name').' ('.$name.')'">
                            <x-ui.input name="translations[{{ $code }}][name]" id="menu-{{ $code }}-name" :value="$translation?->name" maxlength="120" />
                        </x-ui.field>
                        <x-ui.field name="translations[{{ $code }}][description]" id="menu-{{ $code }}-description" :label="__('menu.fields.description').' ('.$name.')'" :help="__('menu.description_help')">
                            <x-ui.textarea name="translations[{{ $code }}][description]" id="menu-{{ $code }}-description" :value="$translation?->description" rows="5" maxlength="1000" />
                        </x-ui.field>
                        <x-ui.field name="translations[{{ $code }}][aliases]" id="menu-{{ $code }}-aliases" :label="__('menu.fields.aliases')" :help="__('menu.aliases_help')">
                            <x-ui.input name="translations[{{ $code }}][aliases]" id="menu-{{ $code }}-aliases" :value="implode(', ', $translation?->aliases ?? [])" />
                        </x-ui.field>
                    </div>
                </x-ui.tab-panel>
            @endforeach
        </x-ui.tabs>
    </div>
</div>
