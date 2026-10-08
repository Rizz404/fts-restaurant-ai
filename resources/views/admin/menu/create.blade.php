<x-layouts.admin
    :title="__('menu.create')"
    :heading="__('menu.create')"
    :nav-items="$navItems"
    :breadcrumbs="[
        ['label' => __('ui.nav.overview'), 'href' => route('admin.dashboard')],
        ['label' => __('ui.nav.menu'), 'href' => route('admin.menu.index')],
        ['label' => __('menu.create')],
    ]"
>
    <form method="POST" action="{{ route('admin.menu.store') }}" enctype="multipart/form-data" novalidate>
        @csrf

        <x-ui.card>
            <p class="mb-6 text-body text-muted">{{ __('menu.create_intro') }}</p>

            @include('admin.menu._form', ['item' => null, 'completeness' => [], 'slugLocked' => false])

            <x-slot:footer>
                <x-ui.link-button :href="route('admin.menu.index')" variant="ghost">{{ __('ui.cancel') }}</x-ui.link-button>
                <x-ui.button type="submit">{{ __('menu.save') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.card>
    </form>
</x-layouts.admin>
