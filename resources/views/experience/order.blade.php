<x-restaurant-stage :scene="$scene">
    <p class="text-body text-muted">{{ __('requests.order_intro') }}</p>

    @if ($wizard['closedToday'])
        <x-ui.alert tone="warning">{{ __('requests.closed_today') }}</x-ui.alert>
    @elseif ($wizard['hours'] !== [])
        <p class="text-caption text-muted">{{ __('requests.order_hours', ['hours' => collect($wizard['hours'])->map(fn ($range) => is_array($range) ? ($range['open'] ?? $range[0]).'–'.($range['close'] ?? $range[1]) : $range)->implode(', ')]) }}</p>
    @endif

    @include('experience._request-wizard', ['wizard' => $wizard])
</x-restaurant-stage>
