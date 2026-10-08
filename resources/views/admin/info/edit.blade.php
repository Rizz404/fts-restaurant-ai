@php
    $locale = app()->getLocale();
    $tz = config('restaurant.timezone');
    $titleSource = $draft ?? $published;
    $heading = $titleSource?->translation($locale)?->title;
@endphp

<x-layouts.admin
    :title="filled($heading) ? $heading : $article->slug"
    :heading="filled($heading) ? $heading : $article->slug"
    :nav-items="$navItems"
    :breadcrumbs="[
        ['label' => __('ui.nav.overview'), 'href' => route('admin.dashboard')],
        ['label' => __('ui.nav.info'), 'href' => route('admin.info.index')],
        ['label' => $article->slug],
    ]"
>
    <x-slot:actions>
        <x-ui.link-button :href="route('admin.info.revisions.index', $article)" variant="outline">
            <x-ui.icon name="clipboard-document-list" size="sm" />
            {{ __('info.history') }}
        </x-ui.link-button>
    </x-slot:actions>

    <div class="space-y-6">
        {{-- Published state --}}
        <x-ui.card :title="__('info.published_section')">
            @if ($published)
                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.badge tone="success">{{ __('info.status.published') }} · v{{ $published->version }}</x-ui.badge>
                    <span class="text-caption text-muted">
                        {{ __('info.published_meta', [
                            'date' => $published->published_at?->timezone($tz)->format('Y-m-d H:i'),
                            'name' => $published->publisher?->name,
                        ]) }}
                    </span>
                </div>
                <p class="mt-2 text-body text-muted">
                    {{ __('info.fields.effective_from') }}: {{ $published->effective_from?->timezone($tz)->format('Y-m-d H:i') }}
                    @if ($published->effective_until)
                        · {{ __('info.fields.effective_until') }}: {{ $published->effective_until->timezone($tz)->format('Y-m-d H:i') }}
                    @endif
                </p>

                <x-slot:footer>
                    <x-ui.link-button :href="route('experience.info.show', $article->slug)" variant="ghost">
                        <x-ui.icon name="eye" size="sm" />
                        {{ __('info.view_published') }}
                    </x-ui.link-button>
                    @if ($canPublish)
                        <x-ui.button variant="outline" x-data x-on:click="$dispatch('ui:modal-open', { name: 'archive-article' })">
                            {{ __('info.archive') }}
                        </x-ui.button>
                    @endif
                    @unless ($draft)
                        <form method="POST" action="{{ route('admin.info.revisions.store', $article) }}">
                            @csrf
                            <x-ui.button type="submit">
                                <x-ui.icon name="pencil-square" size="sm" />
                                {{ __('info.new_draft') }}
                            </x-ui.button>
                        </form>
                    @endunless
                </x-slot:footer>
            @else
                <p class="text-body text-muted">{{ __('info.not_published') }}</p>
                @unless ($draft)
                    <x-slot:footer>
                        <form method="POST" action="{{ route('admin.info.revisions.store', $article) }}">
                            @csrf
                            <x-ui.button type="submit">{{ __('info.new_draft') }}</x-ui.button>
                        </form>
                    </x-slot:footer>
                @endunless
            @endif
        </x-ui.card>

        {{-- Draft editor --}}
        @if ($draft)
            <form method="POST" action="{{ route('admin.info.draft.update', $article) }}" novalidate>
                @csrf
                @method('PATCH')

                <x-ui.card :title="__('info.draft_section', ['version' => $draft->version])">
                    <x-slot:header>
                        <x-ui.badge tone="warning">{{ __('info.status.draft') }}</x-ui.badge>
                    </x-slot:header>

                    @include('admin.info._form', ['draft' => $draft])

                    <x-slot:footer>
                        <x-ui.button type="submit">{{ __('info.save_draft') }}</x-ui.button>
                    </x-slot:footer>
                </x-ui.card>
            </form>

            @if ($canPublish)
                <x-ui.card :title="__('info.publish')">
                    <p class="text-body text-muted">{{ __('info.publish_intro') }}</p>
                    <x-slot:footer>
                        <form
                            method="POST"
                            action="{{ route('admin.info.publication.store', $article) }}"
                            x-data
                            x-on:ui:confirmed.window="if ($event.detail.name === 'publish-article') $el.submit()"
                        >
                            @csrf
                            <x-ui.button x-on:click="$dispatch('ui:modal-open', { name: 'publish-article' })">
                                <x-ui.icon name="check-circle" size="sm" />
                                {{ __('info.publish') }}
                            </x-ui.button>
                        </form>
                    </x-slot:footer>
                </x-ui.card>

                <x-ui.confirm-dialog name="publish-article" :title="__('info.publish_confirm_title')" :confirm-label="__('info.publish')">
                    {{ __('info.publish_confirm_body', ['version' => $draft->version]) }}
                </x-ui.confirm-dialog>
            @else
                <x-ui.alert tone="info">{{ __('info.editor_publish_note') }}</x-ui.alert>
            @endif
        @endif

        @if ($published && $canPublish)
            <form
                method="POST"
                action="{{ route('admin.info.publication.destroy', $article) }}"
                x-data
                x-on:ui:confirmed.window="if ($event.detail.name === 'archive-article') $el.submit()"
            >
                @csrf
                @method('DELETE')
            </form>
            <x-ui.confirm-dialog name="archive-article" :title="__('info.archive_confirm_title')" :confirm-label="__('info.archive')" variant="danger">
                {{ __('info.archive_confirm_body') }}
            </x-ui.confirm-dialog>
        @endif
    </div>
</x-layouts.admin>
