<x-layouts.admin
    :title="__('menu.categories')"
    :heading="__('menu.categories')"
    :nav-items="$navItems"
    :breadcrumbs="[
        ['label' => __('ui.nav.overview'), 'href' => route('admin.dashboard')],
        ['label' => __('ui.nav.menu'), 'href' => route('admin.menu.index')],
        ['label' => __('menu.categories')],
    ]"
>
    <x-slot:actions>
        <x-ui.link-button :href="route('admin.menu.categories.create')">
            <x-ui.icon name="plus" size="sm" />
            {{ __('menu.category_create') }}
        </x-ui.link-button>
    </x-slot:actions>

    @if ($categories->isEmpty())
        <x-ui.empty-state icon="tag" :title="__('menu.categories_empty_title')" :description="__('menu.categories_empty_description')" />
    @else
        <x-ui.table :headers="[__('menu.fields.name'), __('menu.fields.code'), __('menu.fields.sort_order'), __('menu.dishes'), __('ui.status'), ['label' => __('ui.actions'), 'sr_only' => true]]">
            @foreach ($categories as $category)
                <x-ui.table.row>
                    <x-ui.table.cell header>{{ $category->name() }}</x-ui.table.cell>
                    <x-ui.table.cell><code class="text-caption">{{ $category->code }}</code></x-ui.table.cell>
                    <x-ui.table.cell>{{ $category->sort_order }}</x-ui.table.cell>
                    <x-ui.table.cell>{{ $category->items_count }}</x-ui.table.cell>
                    <x-ui.table.cell>
                        <x-ui.badge :tone="$category->is_active ? 'success' : 'neutral'">{{ $category->is_active ? __('ui.users.active') : __('ui.users.inactive') }}</x-ui.badge>
                    </x-ui.table.cell>
                    <x-ui.table.cell class="text-right">
                        <x-ui.link-button :href="route('admin.menu.categories.edit', $category)" variant="ghost" size="sm">{{ __('ui.edit') }}</x-ui.link-button>
                    </x-ui.table.cell>
                </x-ui.table.row>
            @endforeach
        </x-ui.table>
    @endif
</x-layouts.admin>
