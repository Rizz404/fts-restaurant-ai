{{-- Category fields. $category is null on create (the code is then editable). --}}
<div class="grid gap-4">
    @if ($category === null)
        <x-ui.field name="code" id="category-code" :label="__('menu.fields.code')" :help="__('menu.code_help')" required>
            <x-ui.input name="code" id="category-code" maxlength="40" autocomplete="off" required />
        </x-ui.field>
    @else
        <div>
            <p class="text-body font-medium text-text">{{ __('menu.fields.code') }}</p>
            <p class="mt-1"><code class="text-caption">{{ $category->code }}</code></p>
        </div>
    @endif

    <div class="grid gap-4 md:grid-cols-3">
        @foreach (config('restaurant.locale_names') as $code => $name)
            <x-ui.field name="names[{{ $code }}]" id="category-name-{{ $code }}" :label="__('menu.fields.name').' ('.$name.')'" required>
                <x-ui.input name="names[{{ $code }}]" id="category-name-{{ $code }}" :value="$category?->translations->firstWhere('locale', $code)?->name" maxlength="120" lang="{{ $code }}" required />
            </x-ui.field>
        @endforeach
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <x-ui.field name="sort_order" id="category-sort" :label="__('menu.fields.sort_order')">
            <x-ui.input name="sort_order" id="category-sort" type="number" min="0" max="9999" :value="$category?->sort_order ?? 0" />
        </x-ui.field>
        <div class="flex items-end">
            <x-ui.checkbox name="is_active" id="category-active" :checked="$category?->is_active ?? true">{{ __('menu.category_active') }}</x-ui.checkbox>
        </div>
    </div>
</div>
