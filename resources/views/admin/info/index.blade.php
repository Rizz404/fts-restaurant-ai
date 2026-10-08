<x-layouts.admin
    :title="__('ui.nav.info')"
    :heading="__('ui.nav.info')"
    :nav-items="$navItems"
    :breadcrumbs="[['label' => __('ui.nav.overview'), 'href' => route('admin.dashboard')], ['label' => __('ui.nav.info')]]"
>
    <x-slot:actions>
        <x-ui.link-button :href="route('admin.info.create')">
            <x-ui.icon name="plus" size="sm" />
            {{ __('info.create') }}
        </x-ui.link-button>
    </x-slot:actions>

    @if ($articles->isEmpty())
        <x-ui.empty-state icon="book-open" :title="__('info.cms_empty_title')" :description="__('info.cms_empty_description')" />
    @else
        <x-ui.table :headers="[__('info.fields.title'), __('ui.status'), __('ui.updated'), ['label' => __('ui.actions'), 'sr_only' => true]]">
            @foreach ($articles as $article)
                @php $translation = $article->latestRevision?->translation(app()->getLocale()); @endphp
                <x-ui.table.row>
                    <x-ui.table.cell header>
                        <span class="block">{{ filled($translation?->title) ? $translation->title : __('info.untitled') }}</span>
                        <code class="block text-caption font-normal text-muted">{{ $article->slug }}</code>
                    </x-ui.table.cell>
                    <x-ui.table.cell>
                        <span class="flex flex-wrap gap-1">
                            @if ($article->published)
                                <x-ui.badge tone="success">{{ __('info.status.published') }} · v{{ $article->published->version }}</x-ui.badge>
                            @endif
                            @if ($article->draft)
                                <x-ui.badge tone="warning">{{ __('info.status.draft') }} · v{{ $article->draft->version }}</x-ui.badge>
                            @endif
                            @if (! $article->published && ! $article->draft)
                                <x-ui.badge>{{ __('info.status.archived') }}</x-ui.badge>
                            @endif
                        </span>
                    </x-ui.table.cell>
                    <x-ui.table.cell class="whitespace-nowrap text-muted">{{ $article->updated_at->timezone(config('restaurant.timezone'))->format('Y-m-d H:i') }}</x-ui.table.cell>
                    <x-ui.table.cell class="text-right">
                        <x-ui.link-button :href="route('admin.info.edit', $article)" variant="ghost" size="sm">{{ __('ui.edit') }}</x-ui.link-button>
                    </x-ui.table.cell>
                </x-ui.table.row>
            @endforeach
        </x-ui.table>
        <x-ui.pagination :paginator="$articles" class="mt-4" />
    @endif
</x-layouts.admin>
