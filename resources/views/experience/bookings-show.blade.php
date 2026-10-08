@php
    use App\Modules\Requests\Support\WizardLabels;
@endphp

<x-deck-stage :scene="$scene">
    <p>
        <a href="{{ route('experience.bookings') }}" class="inline-flex items-center gap-1 text-body font-medium text-primary hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus">
            <x-ui.icon name="arrow-left" size="sm" />
            {{ __('requests.my_requests') }}
        </a>
    </p>

    @if ($booking === null)
        {{-- Not this guest's request (or no such reference): the same lookup form either way. --}}
        <x-ui.alert tone="info">{{ __('requests.lookup_needed') }}</x-ui.alert>
        @include('experience._request-lookup', ['reference' => $reference])
    @else
        <div
            class="space-y-4"
            x-data="requestThread(@js([
                'requestId' => $booking['request_id'],
                'role' => 'guest',
                'status' => $booking['status'],
                'statusLabel' => $booking['status_label'],
                'statusTone' => $booking['status_tone'],
                'version' => $booking['version'],
                'canCancel' => $booking['can_cancel'],
                'labels' => WizardLabels::thread(),
            ]))"
        >
            <x-ui.card>
                <div class="space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="font-mono text-section-title font-semibold text-text">{{ $booking['reference'] }}</h2>
                        <x-ui.badge :tone="$booking['status_tone']">
                            <span x-text="statusLabel">{{ $booking['status_label'] }}</span>
                        </x-ui.badge>
                    </div>
                    <p class="text-caption text-muted">{{ $booking['type_label'] }}</p>
                    <p class="text-body text-text">{{ $booking['summary'] }}</p>

                    @if (! empty($booking['pricing']))
                        <ul class="divide-y divide-border text-body">
                            @foreach ($booking['pricing']['lines'] as $line)
                                <li class="flex justify-between gap-3 py-1"><span>{{ $line['quantity'] }}× {{ $line['name'] }}</span><span class="text-muted">{{ \App\Modules\Menu\Support\Money::format((int) $line['line_total'], $booking['pricing']['currency']) }}</span></li>
                            @endforeach
                            <li class="flex justify-between gap-3 py-1 font-semibold text-text"><span>{{ __('requests.total') }}</span><span>{{ $booking['pricing']['total_formatted'] }}</span></li>
                        </ul>
                    @endif

                    <div x-show="canCancel" x-cloak class="space-y-2 border-t border-border pt-3">
                        <template x-if="confirmingStatus === null">
                            <x-ui.button variant="outline" x-on:click="confirmingStatus = 'cancelled'">{{ __('requests.cancel_request') }}</x-ui.button>
                        </template>
                        <template x-if="confirmingStatus === 'cancelled'">
                            <div class="space-y-2">
                                <p class="text-body text-text">{{ __('requests.cancel_confirm') }}</p>
                                <label for="cancel-reason" class="block text-body font-medium text-text">{{ __('requests.cancel_reason') }}</label>
                                <textarea id="cancel-reason" rows="2" maxlength="500" x-model="reason" class="form-textarea w-full rounded-control border border-border bg-surface text-body text-text focus:border-primary focus:ring-2 focus:ring-focus"></textarea>
                                <div class="flex gap-2">
                                    <x-ui.button variant="danger" x-on:click="cancelRequest()" x-bind:disabled="busy">{{ __('requests.cancel_yes') }}</x-ui.button>
                                    <x-ui.button variant="outline" x-on:click="confirmingStatus = null" x-bind:disabled="busy">{{ __('requests.cancel_no') }}</x-ui.button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </x-ui.card>

            <x-ui.card>
                <x-request-thread role="guest" />
            </x-ui.card>
        </div>
    @endif
</x-deck-stage>
