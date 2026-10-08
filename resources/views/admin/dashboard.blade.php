<x-layouts.admin
    :title="__('ui.nav.overview')"
    :heading="__('ui.admin.dashboard_title')"
    :nav-items="$navItems"
>
    <div class="space-y-6">
        <p class="text-body text-muted">{{ __('ui.admin.dashboard_intro') }}</p>

        @if ($stats !== null)
            <div class="grid gap-4 sm:grid-cols-3">
                <x-ui.card padding="sm">
                    <p class="text-caption text-muted">{{ __('ui.admin.stats.active_users') }}</p>
                    <p class="mt-1 text-page-title font-semibold">{{ number_format($stats['active_users']) }}</p>
                </x-ui.card>
                <x-ui.card padding="sm">
                    <p class="text-caption text-muted">{{ __('ui.admin.stats.inactive_users') }}</p>
                    <p class="mt-1 text-page-title font-semibold">{{ number_format($stats['inactive_users']) }}</p>
                </x-ui.card>
                <x-ui.card padding="sm">
                    <p class="text-caption text-muted">{{ __('ui.admin.stats.teams') }}</p>
                    <p class="mt-1 text-page-title font-semibold">{{ number_format($stats['teams']) }}</p>
                </x-ui.card>
            </div>

            <div class="flex flex-wrap gap-3">
                <x-ui.link-button :href="route('admin.users.create')">
                    <x-ui.icon name="plus" size="sm" />
                    {{ __('ui.users.create') }}
                </x-ui.link-button>
            </div>
        @else
            <x-ui.empty-state icon="sparkles" :title="__('ui.admin.coming_title')" :description="__('ui.admin.coming_description')" />
        @endif
    </div>
</x-layouts.admin>
