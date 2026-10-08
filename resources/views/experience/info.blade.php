{{-- Info slide: contact links from restaurant settings, then search and the published info articles. --}}
<x-deck-stage :scene="$scene">
    @if ($scene->contactLinks !== [])
        <x-ui.card :title="__('info.contact_title')">
            <ul class="grid gap-3 sm:grid-cols-2" aria-label="{{ __('info.contact_title') }}">
                @foreach ($scene->contactLinks as $link)
                    <li>
                        <a
                            href="{{ $link['href'] }}"
                            @if ($link['type'] !== 'phone') target="_blank" rel="noopener noreferrer" @endif
                            class="flex items-center gap-3 rounded-control border border-border bg-surface px-3 py-2 hover:border-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                        >
                            <x-ui.icon :name="match ($link['type']) { 'phone', 'whatsapp' => 'phone', 'maps' => 'map-pin', default => 'sparkles' }" />
                            <span class="min-w-0">
                                <span class="block text-caption text-muted">{{ $link['label'] }}</span>
                                <span class="block truncate text-body font-medium text-text">{{ $link['display'] }}</span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    @endif

    <x-ui.card :title="__('info.search_title')">
        <form method="GET" action="{{ route('experience.info') }}" role="search" class="grid gap-3 md:grid-cols-[1fr_12rem_auto] md:items-end">
            <x-ui.field name="q" id="info-q" :label="__('ui.search')">
                <x-ui.input name="q" id="info-q" type="search" :value="$query" maxlength="500" :placeholder="__('info.search_placeholder')" />
            </x-ui.field>
            <x-ui.field name="category" id="info-category" :label="__('info.fields.category')">
                <x-ui.select name="category" id="info-category" :options="$categoryOptions" :value="$category" :placeholder="__('ui.all')" />
            </x-ui.field>
            <x-ui.button type="submit">
                <x-ui.icon name="magnifying-glass" size="sm" />
                {{ __('ui.search') }}
            </x-ui.button>
        </form>
    </x-ui.card>

    @if ($results !== null)
        <section aria-labelledby="info-results-title" class="space-y-3">
            <h2 id="info-results-title" class="text-section-title font-semibold text-text">
                {{ trans_choice('info.results_count', count($results), ['count' => count($results), 'query' => $query]) }}
            </h2>

            @forelse ($results as $result)
                @include('experience._info-item', [
                    'href' => route('experience.info.show', $result->slug),
                    'title' => $result->title,
                    'excerpt' => \App\Modules\Knowledge\Data\KnowledgeEvidence::excerpt($result->excerpt, $uiExcerpt),
                    'category' => $result->category,
                ])
            @empty
                <x-ui.empty-state icon="magnifying-glass" :title="__('info.no_results_title')" :description="__('info.no_results_description')" />
            @endforelse
        </section>
    @else
        <section aria-labelledby="info-latest-title" class="space-y-3">
            <h2 id="info-latest-title" class="text-section-title font-semibold text-text">{{ __('info.latest_title') }}</h2>

            @forelse ($latest as $revision)
                @php $translation = $revision->translation(app()->getLocale()); @endphp
                @include('experience._info-item', [
                    'href' => route('experience.info.show', $revision->article->slug),
                    'title' => $translation?->title ?? '',
                    'excerpt' => \App\Modules\Knowledge\Data\KnowledgeEvidence::excerpt($translation?->body ?? '', $uiExcerpt),
                    'category' => $revision->category->value,
                ])
            @empty
                <x-ui.empty-state icon="book-open" :title="__('info.empty_title')" :description="__('info.empty_description')" />
            @endforelse

            @if ($latest->hasPages())
                <div class="rounded-card bg-surface p-3 shadow-card">
                    <x-ui.pagination :paginator="$latest" />
                </div>
            @endif
        </section>
    @endif
</x-deck-stage>
