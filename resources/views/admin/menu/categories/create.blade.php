<x-layouts.admin
    :title="__('menu.category_create')"
    :heading="__('menu.category_create')"
    :nav-items="$navItems"
    :breadcrumbs="[
        ['label' => __('ui.nav.overview'), 'href' => route('admin.dashboard')],
        ['label' => __('ui.nav.menu'), 'href' => route('admin.menu.index')],
        ['label' => __('menu.categories'), 'href' => route('admin.menu.categories.index')],
        ['label' => __('menu.category_create')],
    ]"
>
    <form method="POST" action="{{ route('admin.menu.categories.store') }}" novalidate class="max-w-3xl">
        @csrf
        <x-ui.card>
            @include('admin.menu.categories._form', ['category' => null])

            <x-slot:footer>
                <x-ui.link-button :href="route('admin.menu.categories.index')" variant="ghost">{{ __('ui.cancel') }}</x-ui.link-button>
                <x-ui.button type="submit">{{ __('menu.save') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.card>
    </form>
</x-layouts.admin>
