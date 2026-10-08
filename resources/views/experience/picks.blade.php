{{--
    Chef's Picks: dishes marked by the chef that are published and available (from the CMS). The "recommend for me"
    button asks the AI host, whose answer appears in the copilot; it never replaces this list.
--}}
<x-deck-stage :scene="$scene">
    <x-ui.card padding="sm">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-body text-muted">{{ __('menu.picks_intro') }}</p>
            <button
                type="button"
                class="prompt focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                x-data
                x-on:click="$dispatch('chat:ask', { message: @js(__('menu.recommend_message')) })"
            >
                <x-ui.icon name="sparkles" size="sm" />
                <span>{{ __('menu.recommend_for_me') }}</span>
            </button>
        </div>
    </x-ui.card>

    @if ($items === [])
        <x-ui.empty-state icon="star" :title="__('menu.picks_empty_title')" :description="__('menu.picks_empty_description')">
            <x-slot:action>
                <x-ui.link-button :href="route('experience.menu')" variant="outline">{{ __('menu.view_menu') }}</x-ui.link-button>
            </x-slot:action>
        </x-ui.empty-state>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($items as $item)
                @include('experience._menu-card', ['item' => $item])
            @endforeach
        </div>
    @endif
</x-deck-stage>
