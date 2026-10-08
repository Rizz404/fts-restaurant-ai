{{-- My Bookings: this guest session's requests, lookup by reference, and the feedback / special request form. --}}
<x-deck-stage :scene="$scene">
    <section aria-labelledby="mine-title" class="space-y-3">
        <h2 id="mine-title" class="text-section-title font-semibold text-text">{{ __('requests.my_requests') }}</h2>

        @if ($requests === [])
            <x-ui.empty-state icon="calendar-days" :title="__('requests.empty_title')" :description="__('requests.empty_description')">
                <x-slot:action>
                <div class="flex flex-wrap justify-center gap-2">
                    <x-ui.link-button :href="route('experience.reservation')">{{ __('requests.types.reservation') }}</x-ui.link-button>
                    <x-ui.link-button :href="route('experience.order')" variant="outline">{{ __('requests.types.order') }}</x-ui.link-button>
                </div>
                </x-slot:action>
            </x-ui.empty-state>
        @else
            <ul class="grid gap-3 sm:grid-cols-2">
                @foreach ($requests as $item)
                    <li>
                        <a href="{{ $item['path'] }}" class="block rounded-card border border-border bg-surface p-4 shadow-card hover:border-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus">
                            <span class="flex flex-wrap items-center justify-between gap-2">
                                <span class="font-mono text-body font-semibold text-text">{{ $item['reference'] }}</span>
                                <x-ui.badge :tone="$item['status_tone']">{{ $item['status_label'] }}</x-ui.badge>
                            </span>
                            <span class="mt-1 block text-caption text-muted">{{ $item['type_label'] }}</span>
                            <span class="mt-1 block text-body text-text">{{ $item['summary'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    @include('experience._request-lookup', ['reference' => ''])

    <section aria-labelledby="feedback-title" class="space-y-3">
        <h2 id="feedback-title" class="text-section-title font-semibold text-text">{{ __('requests.feedback_title') }}</h2>
        <p class="text-body text-muted">{{ __('requests.feedback_intro') }}</p>
        @include('experience._request-wizard', ['wizard' => $wizard])
    </section>
</x-deck-stage>
