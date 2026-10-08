{{--
    AI host copilot content. Card markup lives in the templates below (one per whitelisted type, normalized by
    safe-card-renderer); every message and card field is rendered with x-text and server-built paths only.
    Dish prices, availability, and allergen data come from the server's descriptors. Config comes from the
    current slide. The hidden contact_website field is the honeypot (humans never fill it).
--}}
@props([
    'scene',
])

@php
    $dietCodes = config('restaurant.dietary_tags');
    $allergenCodes = config('restaurant.allergens');
    $chatConfig = [
        'locale' => $scene->locale,
        'scene' => $scene->context['scene'],
        'menuItemId' => $scene->context['menu_item_id'],
        'requestPublicId' => $scene->context['request_public_id'],
        'diet' => collect($dietCodes)->mapWithKeys(fn (string $code) => [$code => __('menu.diet.'.$code)])->all(),
        'allergens' => collect($allergenCodes)->mapWithKeys(fn (string $code) => [$code => __('menu.allergen.'.$code)])->all(),
        'allowed' => [
            'diet' => $dietCodes,
            'allergens' => $allergenCodes,
            'maxParty' => app(\App\Modules\Experience\Support\RestaurantSettings::class)->get()->maxPartySize(),
        ],
        'badgeClasses' => collect(['info', 'warning', 'success', 'neutral', 'danger'])
            ->mapWithKeys(fn (string $tone) => [$tone => \App\Support\Ui\ComponentStyles::badge($tone)])
            ->all(),
        'clientTimeoutMs' => (int) config('assistant.client_timeout_seconds') * 1000,
        'labels' => [
            'version' => __('deck.chat.version'),
            'tooLong' => __('deck.chat.errors.too_long'),
            'busy' => __('deck.chat.errors.busy'),
            'invalid' => __('deck.chat.errors.invalid'),
            'session' => __('deck.chat.errors.session'),
            'rateLimited' => __('deck.chat.errors.rate_limited'),
            'timeout' => __('deck.chat.errors.timeout'),
            'network' => __('deck.chat.errors.network'),
            'generic' => __('deck.chat.errors.generic'),
            'draftExpired' => __('requests.errors.draft_expired'),
            'draftChanged' => __('requests.errors.draft_changed'),
            'slotFull' => __('requests.errors.slot_full'),
            'itemUnavailable' => __('requests.errors.item_unavailable'),
            'contactRequired' => __('requests.errors.contact_required'),
        ],
    ];
    $buttonOutline = \App\Support\Ui\ComponentStyles::button('outline', 'sm');
    $buttonPrimary = \App\Support\Ui\ComponentStyles::button('primary', 'sm');
    $buttonGhost = \App\Support\Ui\ComponentStyles::button('ghost', 'sm');
@endphp

<div x-data="restaurantChat(@js($chatConfig))" class="flex min-h-0 flex-1 flex-col">
    <div x-ref="log" class="min-h-0 flex-1 space-y-3 overflow-y-auto scroll-smooth p-4" role="log" aria-live="polite" aria-relevant="additions">
        <div x-show="messages.length === 0" class="chat-bubble flex flex-col items-center gap-4 px-2 py-8 text-center">
            <span class="orb orb-sm" style="--size: 72px" aria-hidden="true"><i class="orb-ring"></i><span class="orb-core"></span></span>
            <p class="max-w-xs text-body text-muted">{{ __('deck.chat.intro') }}</p>
            @if ($scene->suggestedTopics !== [])
                <ul class="flex flex-col items-stretch gap-2" aria-label="{{ __('deck.suggested_topics') }}">
                    @foreach ($scene->suggestedTopics as $topic)
                        <li>
                            <button
                                type="button"
                                class="flex w-full items-center gap-2 rounded-control border border-border bg-surface px-3 py-2 text-left text-body transition duration-200 hover:border-primary hover:shadow-glow focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus motion-reduce:transition-none"
                                x-on:click="$dispatch('chat:ask', { message: {{ \Illuminate\Support\Js::from($topic) }} })"
                            >
                                <x-ui.icon name="sparkles" size="sm" class="text-primary" />
                                <span>{{ $topic }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <template x-for="message in messages" x-bind:key="message.key">
            <div
                class="chat-bubble rounded-card px-3.5 py-2.5 text-body"
                x-bind:class="message.role === 'user' ? 'ml-8 rounded-br-md bg-linear-to-br from-primary to-saffron text-on-primary' : 'mr-4 rounded-bl-md border border-border bg-surface text-text shadow-card'"
            >
                <span class="sr-only" x-text="message.role === 'user' ? @js(__('deck.chat.you')) : @js(__('deck.chat.title'))"></span>
                <p class="break-words whitespace-pre-line" x-text="message.text"></p>
                <p x-show="message.stale" class="mt-1 text-caption text-warning">{{ __('deck.assistant.history_stale') }}</p>

                <div x-show="message.cards.length" class="mt-2 space-y-2">
                    <template x-for="card in message.cards" x-bind:key="card.key">
                        <div>
                            {{-- One dish --}}
                            <template x-if="card.type === 'menu_item_card'">
                                @include('components.chat.menu-card', ['bind' => 'card'])
                            </template>

                            {{-- Several dishes --}}
                            <template x-if="card.type === 'menu_list'">
                                <ul class="space-y-2" aria-label="{{ __('ui.nav.menu') }}">
                                    <template x-for="dish in card.items" x-bind:key="dish.key">
                                        <li>
                                            @include('components.chat.menu-card', ['bind' => 'dish'])
                                        </li>
                                    </template>
                                </ul>
                            </template>

                            {{-- Info source --}}
                            <template x-if="card.type === 'source_card'">
                                <a
                                    x-bind:href="card.path"
                                    class="block rounded-control border border-border bg-surface px-3 py-2 hover:border-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                                >
                                    <span class="sr-only">{{ __('deck.chat.sources') }}:</span>
                                    <span class="block text-body font-medium text-text" x-text="card.title"></span>
                                    <span class="block text-caption text-muted" x-text="card.meta"></span>
                                </a>
                            </template>

                            {{-- Reservation slots --}}
                            <template x-if="card.type === 'availability_slots'">
                                <div class="rounded-control border border-border bg-surface px-3 py-2">
                                    <p class="text-caption font-semibold text-text">
                                        {{ __('deck.chat.cards.slots_title') }}:
                                        <span x-text="card.dateLabel || card.date"></span>
                                        <span x-show="card.partySize" x-text="' · ' + card.partySize"></span>
                                    </p>
                                    <p x-show="card.slots.length === 0" class="text-caption text-muted">{{ __('deck.chat.cards.no_slots') }}</p>
                                    <ul class="mt-1 flex flex-wrap gap-2">
                                        <template x-for="slot in card.slots" x-bind:key="slot.key">
                                            <li>
                                                <span
                                                    class="inline-flex items-center gap-1 rounded-control border px-2 py-1 text-caption"
                                                    x-bind:class="slot.canBook ? 'border-border bg-surface text-text' : 'border-border bg-surface-muted text-muted'"
                                                >
                                                    <b x-text="slot.slot"></b>
                                                    <span x-text="slot.canBook ? @js(__('deck.chat.cards.seats_left', ['count' => ':n'])).replace(':n', slot.seats) : @js(__('deck.chat.cards.slot_full'))"></span>
                                                </span>
                                            </li>
                                        </template>
                                    </ul>
                                    <a x-show="card.path" x-bind:href="card.path" class="mt-2 {{ $buttonOutline }}">{{ __('deck.slides.reservation.label') }}</a>
                                </div>
                            </template>

                            {{-- Own requests --}}
                            <template x-if="card.type === 'request_list'">
                                <ul class="divide-y divide-border rounded-control border border-border bg-surface" aria-label="{{ __('requests.my_requests') }}">
                                    <template x-for="item in card.items" x-bind:key="item.requestId">
                                        <li>
                                            <a x-bind:href="item.path" class="block px-3 py-2 hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus">
                                                <span class="flex items-center justify-between gap-2">
                                                    <span class="text-caption text-muted" x-text="item.reference + ' · ' + item.typeLabel"></span>
                                                    <span x-bind:class="badge(item.tone)" x-text="item.statusLabel"></span>
                                                </span>
                                                <span class="block break-words text-body font-medium text-text" x-text="item.summary"></span>
                                            </a>
                                        </li>
                                    </template>
                                </ul>
                            </template>

                            {{-- One own request --}}
                            <template x-if="card.type === 'request_detail'">
                                <div class="rounded-control border border-border bg-surface px-3 py-2">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-caption text-muted" x-text="card.reference + ' · ' + card.typeLabel"></span>
                                        <span x-bind:class="badge(card.tone)" x-text="card.statusLabel"></span>
                                    </div>
                                    <p class="break-words text-body font-medium text-text" x-text="card.summary"></p>
                                    <p class="text-caption text-muted" x-text="[card.teamLabel, card.assignee].filter(Boolean).join(' · ')"></p>
                                    <p class="mt-2 text-caption font-semibold text-text">{{ __('requests.latest_replies') }}</p>
                                    <p x-show="card.messages.length === 0" class="text-caption text-muted">{{ __('requests.no_replies') }}</p>
                                    <ul class="mt-1 space-y-1">
                                        <template x-for="reply in card.messages" x-bind:key="reply.key">
                                            <li class="rounded-control bg-surface-muted px-2 py-1">
                                                <span class="block text-caption text-muted" x-text="[reply.author, reply.when].filter(Boolean).join(' · ')"></span>
                                                <span class="block break-words whitespace-pre-line text-caption text-text" x-text="reply.text"></span>
                                            </li>
                                        </template>
                                    </ul>
                                    <a x-show="card.path" x-bind:href="card.path" class="mt-2 {{ $buttonOutline }}">{{ __('requests.open') }}</a>
                                </div>
                            </template>

                            {{-- Draft prepared by the host: only these buttons confirm it --}}
                            <template x-if="card.type === 'request_draft'">
                                <div class="rounded-control border border-primary bg-surface px-3 py-2">
                                    <p class="text-caption font-semibold text-primary">{{ __('deck.chat.cards.draft_title') }}</p>
                                    <p class="text-caption text-muted" x-text="[card.typeLabel, card.teamLabel].filter(Boolean).join(' · ')"></p>
                                    <p class="mt-1 break-words whitespace-pre-line text-body font-medium text-text" x-text="card.summary"></p>
                                    <p x-show="card.total" class="text-caption text-text" x-text="card.total"></p>
                                    <p x-show="! card.expired" class="mt-1 text-caption text-muted">{{ __('deck.chat.cards.draft_note') }}</p>
                                    <p x-show="card.expired" class="mt-1 text-caption text-warning">{{ __('requests.errors.draft_expired') }}</p>
                                    <p x-show="card.error" x-text="card.error" role="alert" class="mt-1 text-caption text-danger"></p>

                                    <p x-show="card.confirming" class="mt-2 text-caption font-semibold text-text">{{ __('requests.confirm_question') }}</p>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <a x-bind:href="card.path" x-show="card.path" class="{{ $buttonOutline }}">{{ __('deck.chat.cards.review_draft') }}</a>
                                        <button
                                            type="button"
                                            x-show="! card.confirming"
                                            x-on:click="askConfirm(card)"
                                            x-bind:disabled="card.expired || card.busy"
                                            class="{{ $buttonPrimary }}"
                                        >{{ __('ui.confirm') }}</button>
                                        <button
                                            type="button"
                                            x-show="card.confirming"
                                            x-on:click="confirmDraft(message, card)"
                                            x-bind:disabled="card.expired || card.busy"
                                            x-bind:aria-busy="card.busy ? 'true' : 'false'"
                                            class="{{ $buttonPrimary }}"
                                        >{{ __('requests.confirm_yes') }}</button>
                                        <button
                                            type="button"
                                            x-show="card.confirming"
                                            x-on:click="cancelConfirm(card)"
                                            x-bind:disabled="card.busy"
                                            class="{{ $buttonGhost }}"
                                        >{{ __('ui.cancel') }}</button>
                                    </div>
                                </div>
                            </template>

                            {{-- Request created by the confirm API (canonical result, not the model) --}}
                            <template x-if="card.type === 'request_confirmation'">
                                <a
                                    x-bind:href="card.path"
                                    class="block rounded-control border border-success bg-success-soft px-3 py-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                                >
                                    <span class="flex items-center justify-between gap-2">
                                        <span class="text-caption font-semibold text-success">{{ __('deck.chat.cards.confirmed_title') }}</span>
                                        <span x-bind:class="badge(card.tone)" x-text="card.statusLabel"></span>
                                    </span>
                                    <span class="block text-caption text-muted">{{ __('deck.chat.cards.reference') }}: <span x-text="card.reference"></span></span>
                                    <span class="block break-words text-body font-medium text-text" x-text="card.summary"></span>
                                </a>
                            </template>
                        </div>
                    </template>
                </div>

                <div x-show="message.actions.length" class="mt-2 flex flex-wrap gap-2">
                    <template x-for="action in message.actions" x-bind:key="action.key">
                        <a x-bind:href="action.path" x-text="action.label" class="{{ $buttonOutline }}"></a>
                    </template>
                </div>
            </div>
        </template>

        <div x-show="pending" x-cloak class="chat-bubble flex items-center gap-2 text-caption text-muted">
            <span class="thinking-dots text-primary" aria-hidden="true"><i style="--i: 0"></i><i style="--i: 1"></i><i style="--i: 2"></i></span>
            <span>{{ __('deck.chat.thinking') }}</span>
        </div>
    </div>

    <div x-show="error" x-cloak role="alert" class="mx-4 mb-2 flex items-start justify-between gap-2 rounded-control bg-danger-soft px-3 py-2 text-caption text-danger">
        <span x-text="error"></span>
        <button type="button" x-show="canRetry" x-on:click="retry()" class="shrink-0 font-semibold underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus">
            {{ __('deck.chat.retry') }}
        </button>
    </div>

    <form x-on:submit.prevent="send()" class="chat-composer">
        <div class="sr-only" aria-hidden="true">
            <label>{{ __('ui.honeypot_label') }} <input type="text" tabindex="-1" autocomplete="off" x-model="honeypot"></label>
        </div>
        <label for="chat-input" class="sr-only">{{ __('deck.chat.input_label') }}</label>
        <textarea
            id="chat-input"
            x-model="draft"
            x-on:keydown.enter="onEnter($event)"
            x-bind:maxlength="maxInput"
            rows="1"
            placeholder="{{ __('deck.chat.placeholder') }}"
            class="form-textarea {{ \App\Support\Ui\ComponentStyles::control() }} flex-1 resize-none"
        ></textarea>
        <button
            type="submit"
            class="chat-send focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2 focus-visible:ring-offset-surface"
            x-bind:disabled="pending || ! draft.trim()"
        >
            <x-ui.icon name="paper-airplane" />
            <span class="sr-only">{{ __('deck.chat.send') }}</span>
        </button>
        <span class="sr-only" x-text="draft.length + ' / ' + maxInput"></span>
    </form>
</div>
