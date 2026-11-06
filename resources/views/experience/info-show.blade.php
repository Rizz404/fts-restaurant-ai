<x-restaurant-stage :scene="$scene" :seo="['title' => $translation?->title ?? $scene->title, 'description' => $translation?->body ?? $scene->summary]">
    <div>
        <x-ui.link-button :href="route('experience.info')" variant="secondary" size="sm">
            <x-ui.icon name="chevron-left" size="sm" />
            {{ __('info.back_to_search') }}
        </x-ui.link-button>
    </div>

    <article class="rounded-card border border-border bg-surface p-4 shadow-card md:p-6" lang="{{ $translation?->locale }}">
        <div class="flex flex-wrap items-center gap-2">
            <x-ui.badge tone="info">{{ $revision->category->label() }}</x-ui.badge>
        </div>

        <h2 class="mt-3 text-page-title font-semibold text-text">{{ $translation?->title }}</h2>

        <div class="mt-6 max-w-none text-body leading-7 text-text whitespace-pre-line break-words">{{ $translation?->body }}</div>

        @if (! empty($translation?->tags))
            <ul class="mt-6 flex flex-wrap gap-2" aria-label="{{ __('info.fields.tags') }}">
                @foreach ($translation->tags as $tag)
                    <li><x-ui.badge>{{ $tag }}</x-ui.badge></li>
                @endforeach
            </ul>
        @endif
    </article>
</x-restaurant-stage>
