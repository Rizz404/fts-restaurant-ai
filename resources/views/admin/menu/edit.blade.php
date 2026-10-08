@php
    $translation = $item->translation(app()->getLocale());
    $heading = filled($translation?->name) ? $translation->name : $item->slug;
    $tz = config('restaurant.timezone');
@endphp

<x-layouts.admin
    :title="$heading"
    :heading="$heading"
    :nav-items="$navItems"
    :breadcrumbs="[
        ['label' => __('ui.nav.overview'), 'href' => route('admin.dashboard')],
        ['label' => __('ui.nav.menu'), 'href' => route('admin.menu.index')],
        ['label' => $item->slug],
    ]"
>
    <x-slot:actions>
        <x-ui.badge :tone="$item->status->tone()">{{ $item->status->label() }}</x-ui.badge>
        @if ($item->isPublished())
            <x-ui.link-button :href="route('experience.menu.show', $item->slug)" variant="outline" size="sm">
                <x-ui.icon name="eye" size="sm" />
                {{ __('menu.view_public') }}
            </x-ui.link-button>
        @endif
    </x-slot:actions>

    <div class="space-y-6">
        {{-- Allergen verification --}}
        <x-ui.card :title="__('menu.verification_section')">
            @if ($item->isVerified())
                <x-ui.alert tone="success">
                    {{ __('menu.verified_meta', ['date' => $item->allergen_info_verified_at->timezone($tz)->format('Y-m-d H:i'), 'name' => $item->verifier?->name ?? '–']) }}
                </x-ui.alert>
            @else
                <x-ui.alert tone="warning">{{ __('menu.not_verified') }}</x-ui.alert>
            @endif

            <x-slot:footer>
                <form method="POST" action="{{ route('admin.menu.verification.store', $item) }}">
                    @csrf
                    <x-ui.button type="submit" variant="{{ $item->isVerified() ? 'outline' : 'primary' }}">
                        <x-ui.icon name="check-badge" size="sm" />
                        {{ __('menu.verify') }}
                    </x-ui.button>
                </form>
            </x-slot:footer>
        </x-ui.card>

        {{-- Edit form --}}
        <form method="POST" action="{{ route('admin.menu.update', $item) }}" enctype="multipart/form-data" novalidate>
            @csrf
            @method('PATCH')

            <x-ui.card :title="__('menu.details_section')">
                @include('admin.menu._form', ['item' => $item])

                <x-slot:footer>
                    <x-ui.button type="submit">{{ __('menu.save') }}</x-ui.button>
                </x-slot:footer>
            </x-ui.card>
        </form>

        {{-- Publication --}}
        <x-ui.card :title="__('menu.publication_section')">
            @if ($item->isPublished())
                <p class="text-body text-muted">{{ __('menu.published_intro') }}</p>
            @else
                <p class="text-body text-muted">{{ __('menu.publish_intro') }}</p>
                @if ($publicationErrors !== [])
                    <ul class="mt-3 list-disc space-y-1 pl-5 text-body text-danger">
                        @foreach ($publicationErrors as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                @endif
            @endif

            <x-slot:footer>
                @if ($item->isPublished())
                    <form method="POST" action="{{ route('admin.menu.publication.destroy', $item) }}" x-data x-on:ui:confirmed.window="if ($event.detail.name === 'archive-item') $el.submit()">
                        @csrf
                        @method('DELETE')
                        <x-ui.button type="button" variant="outline" x-on:click="$dispatch('ui:modal-open', { name: 'archive-item' })">{{ __('menu.archive') }}</x-ui.button>
                    </form>
                    <x-ui.confirm-dialog name="archive-item" :title="__('menu.archive_confirm_title')" :confirm-label="__('menu.archive')" variant="danger">
                        {{ __('menu.archive_confirm_body') }}
                    </x-ui.confirm-dialog>
                @else
                    <form method="POST" action="{{ route('admin.menu.publication.store', $item) }}">
                        @csrf
                        <x-ui.button type="submit" :disabled="$publicationErrors !== []">
                            <x-ui.icon name="check-circle" size="sm" />
                            {{ __('menu.publish') }}
                        </x-ui.button>
                    </form>
                @endif
            </x-slot:footer>
        </x-ui.card>
    </div>
</x-layouts.admin>
