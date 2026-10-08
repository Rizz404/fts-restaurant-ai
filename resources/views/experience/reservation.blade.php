<x-deck-stage :scene="$scene">
    <p class="text-body text-muted">{{ __('requests.reservation_intro') }}</p>

    @include('experience._request-wizard', ['wizard' => $wizard])
</x-deck-stage>
