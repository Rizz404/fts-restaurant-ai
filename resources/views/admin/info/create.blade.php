<x-layouts.admin
    :title="__('info.create')"
    :heading="__('info.create')"
    :nav-items="$navItems"
    :breadcrumbs="[
        ['label' => __('ui.nav.overview'), 'href' => route('admin.dashboard')],
        ['label' => __('ui.nav.info'), 'href' => route('admin.info.index')],
        ['label' => __('info.create')],
    ]"
>
    <form method="POST" action="{{ route('admin.info.store') }}" novalidate>
        @csrf

        <x-ui.card>
            <p class="mb-6 text-body text-muted">{{ __('info.create_intro') }}</p>

            @include('admin.info._form', ['draft' => null, 'slugLocked' => false, 'completeness' => []])

            <x-slot:footer>
                <x-ui.link-button :href="route('admin.info.index')" variant="ghost">{{ __('ui.cancel') }}</x-ui.link-button>
                <x-ui.button type="submit">{{ __('info.save_draft') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.card>
    </form>
</x-layouts.admin>
