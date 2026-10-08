@php
    use App\Modules\Menu\Support\Money;
@endphp

<x-layouts.admin
    :title="__('ui.nav.menu')"
    :heading="__('ui.nav.menu')"
    :nav-items="$navItems"
    :breadcrumbs="[['label' => __('ui.nav.overview'), 'href' => route('admin.dashboard')], ['label' => __('ui.nav.menu')]]"
>
    @if ($canManage)
        <x-slot:actions>
            <x-ui.link-button :href="route('admin.menu.categories.index')" variant="outline">{{ __('menu.categories') }}</x-ui.link-button>
            <x-ui.link-button :href="route('admin.menu.create')">
                <x-ui.icon name="plus" size="sm" />
                {{ __('menu.create') }}
            </x-ui.link-button>
        </x-slot:actions>
    @else
        <x-ui.alert tone="info" class="mb-4">{{ __('menu.staff_note') }}</x-ui.alert>
    @endif

    <form method="GET" action="{{ route('admin.menu.index') }}" class="mb-4 grid gap-3 md:grid-cols-[1fr_12rem_12rem_auto] md:items-end">
        <x-ui.field name="q" id="filter-q" :label="__('ui.search')">
            <x-ui.input name="q" id="filter-q" type="search" :value="$filters['q']" :placeholder="__('menu.search_placeholder')" />
        </x-ui.field>
        <x-ui.field name="category" id="filter-category" :label="__('menu.fields.category')">
            <x-ui.select name="category" id="filter-category" :options="$categoryOptions" :value="$filters['category']" :placeholder="__('ui.all')" />
        </x-ui.field>
        <x-ui.field name="status" id="filter-status" :label="__('ui.status')">
            <x-ui.select name="status" id="filter-status" :options="$statusOptions" :value="$filters['status']" :placeholder="__('ui.all')" />
        </x-ui.field>
        <x-ui.button type="submit" variant="outline">
            <x-ui.icon name="magnifying-glass" size="sm" />
            {{ __('ui.filter') }}
        </x-ui.button>
    </form>

    @if ($items->isEmpty())
        <x-ui.empty-state icon="cake" :title="__('menu.cms_empty_title')" :description="__('menu.cms_empty_description')" />
    @else
        <x-ui.table :headers="[__('menu.fields.name'), __('menu.fields.category'), __('menu.fields.price'), __('ui.status'), __('menu.fields.is_available'), ['label' => __('ui.actions'), 'sr_only' => true]]">
            @foreach ($items as $item)
                @php $translation = $item->translation(app()->getLocale()); @endphp
                <x-ui.table.row>
                    <x-ui.table.cell header>
                        <span class="block">{{ filled($translation?->name) ? $translation->name : __('menu.untitled') }}</span>
                        <code class="block text-caption font-normal text-muted">{{ $item->slug }}</code>
                    </x-ui.table.cell>
                    <x-ui.table.cell>{{ $item->category->name() }}</x-ui.table.cell>
                    <x-ui.table.cell class="whitespace-nowrap">{{ Money::format($item->price, $item->currency) }}</x-ui.table.cell>
                    <x-ui.table.cell>
                        <span class="flex flex-wrap gap-1">
                            <x-ui.badge :tone="$item->status->tone()">{{ $item->status->label() }}</x-ui.badge>
                            @unless ($item->isVerified())
                                <x-ui.badge tone="warning">{{ __('menu.unverified') }}</x-ui.badge>
                            @endunless
                        </span>
                    </x-ui.table.cell>
                    <x-ui.table.cell>
                        @can('setAvailability', $item)
                            <form method="POST" action="{{ route('admin.menu.availability', $item) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="is_available" value="{{ $item->is_available ? 0 : 1 }}">
                                <x-ui.button type="submit" size="sm" :variant="$item->is_available ? 'outline' : 'danger'">
                                    {{ $item->is_available ? __('menu.mark_sold_out') : __('menu.mark_available') }}
                                </x-ui.button>
                            </form>
                        @else
                            <x-ui.badge :tone="$item->is_available ? 'success' : 'danger'">{{ $item->is_available ? __('menu.available') : __('menu.sold_out') }}</x-ui.badge>
                        @endcan
                    </x-ui.table.cell>
                    <x-ui.table.cell class="text-right">
                        @can('update', $item)
                            <x-ui.link-button :href="route('admin.menu.edit', $item)" variant="ghost" size="sm">{{ __('ui.edit') }}</x-ui.link-button>
                        @endcan
                    </x-ui.table.cell>
                </x-ui.table.row>
            @endforeach
        </x-ui.table>
        <x-ui.pagination :paginator="$items" class="mt-4" />
    @endif
</x-layouts.admin>
