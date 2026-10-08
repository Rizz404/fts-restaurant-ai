{{--
    Guest presenter frame: the AI host. A slide sorter on the left (a tab bar on phones), a top bar with the
    "Ask AI" command pill, the page (a slide deck on Welcome, a slide canvas elsewhere), and a copilot
    panel (chat) that slides over from the right (full screen on phones). The whole frame tints to the accent
    of the current slide. Guests are anonymous: there is no account menu here; staff open /login directly.
    Slots: default (page content), flash (alerts above the content), topbar (extra top bar content, e.g. the slide counter),
    chat (copilot body), navigation (desktop slide thumbnails on the Welcome deck), head.
    Props: mode (deck|room), accent (saffron|herb|chili|primary), scene (css hook), sceneLabel (breadcrumb + copilot context),
    avatar (face image for the copilot header), stage (config for the deckStage Alpine component),
    tableLabel (QR table note, display only), seo (head metadata, see x-seo).
--}}
@props([
    'title' => null,
    'navItems' => [],
    'mode' => 'room',
    'accent' => 'saffron',
    'scene' => 'default',
    'sceneLabel' => null,
    'avatar' => null,
    'stage' => [],
    'tableLabel' => null,
    'seo' => [],
])

<x-layouts.base :title="$title" :seo="$seo">
    @isset($head)
        <x-slot:head>{{ $head }}</x-slot:head>
    @endisset

    <div
        x-data="userShell"
        x-bind:class="{ 'is-copilot-open': chatOpen, 'is-busy': aiBusy }"
        class="ws"
        data-mode="{{ $mode }}"
        data-scene="{{ $scene }}"
    >
        <div
            x-data="deckStage(@js($stage))"
            class="ws-inner"
            data-accent="{{ $accent }}"
            x-bind:data-accent="accentNow"
        >
            <div class="ws-bg" aria-hidden="true"><i></i></div>

            <aside class="sorter">
                <div class="sorter-inner">
                    <div class="sorter-brand">
                        <x-navigation.brand variant="dark" subtitle :href="route('experience.welcome')" />
                    </div>

                    @isset($navigation)
                        {{ $navigation }}
                        <x-navigation.user-menu :items="$navItems" class="sorter-nav--rooms" />
                    @else
                        <x-navigation.user-menu :items="$navItems" />
                    @endisset
                </div>
            </aside>

            <div class="ws-body">
                <header class="ws-top">
                    <x-navigation.brand compact :href="route('experience.welcome')" class="lg:hidden" />

                    @if ($sceneLabel && $mode === 'room')
                        <p class="ws-crumb">{{ config('restaurant.brand.short_name') }} / <b>{{ $sceneLabel }}</b></p>
                    @endif

                    @if ($tableLabel !== null)
                        <p class="table-chip">
                            <x-ui.icon name="map-pin" size="sm" class="size-3.5!" />
                            {{ __('deck.table', ['label' => $tableLabel]) }}
                        </p>
                    @endif

                    {{ $topbar ?? '' }}

                    @isset($chat)
                        <button
                            type="button"
                            id="chat-trigger"
                            x-on:click="$dispatch('chat:open')"
                            aria-controls="chat-dock"
                            aria-expanded="false"
                            x-bind:aria-expanded="chatOpen"
                            class="cmd-pill focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                        >
                            <span class="orb orb-xs" aria-hidden="true"><span class="orb-core"></span></span>
                            <span class="cmd-pill-label">{{ __('deck.chat.ask_ai') }}</span>
                            <kbd class="kbd" aria-hidden="true" x-text="shortcut">Ctrl K</kbd>
                        </button>
                    @endisset

                    <div class="ws-top-actions">
                        <button
                            type="button"
                            x-on:click="toggleSound()"
                            aria-pressed="false"
                            x-bind:aria-pressed="soundOn"
                            class="icon-btn max-sm:hidden focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                        >
                            <x-ui.icon name="speaker-x-mark" size="sm" x-show="! soundOn" />
                            <x-ui.icon name="speaker-wave" size="sm" x-show="soundOn" x-cloak />
                            <span class="sr-only" x-text="soundOn ? @js(__('deck.sound_on')) : @js(__('deck.sound_off'))">{{ __('deck.sound_off') }}</span>
                        </button>

                        <x-navigation.language-switch />
                    </div>
                </header>

                {{ $flash ?? '' }}

                <main id="main-content" tabindex="-1" class="ws-main">
                    <div class="ws-content">{{ $slot }}</div>
                </main>
            </div>
        </div>

        @isset($chat)
            <section
                id="chat-dock"
                x-on:keydown.escape.prevent.stop="closeChat()"
                aria-labelledby="chat-dock-title"
                class="ws-copilot"
            >
                <div class="ws-copilot-inner">
                    <div class="copilot-head">
                        <span x-data="sceneImage" class="copilot-avatar">
                            @if ($avatar)
                                <img
                                    x-ref="image"
                                    src="{{ $avatar }}"
                                    alt=""
                                    decoding="async"
                                    referrerpolicy="no-referrer"
                                    x-show="! failed"
                                    x-on:error="failed = true"
                                >
                                <x-ui.icon name="sparkles" x-show="failed" x-cloak />
                            @else
                                <x-ui.icon name="sparkles" />
                            @endif
                            <i class="status-dot" aria-hidden="true"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h2 id="chat-dock-title" class="truncate font-display text-section-title font-semibold tracking-tight">{{ __('deck.chat.title') }}</h2>
                            <div class="mt-0.5 flex flex-wrap items-center gap-2">
                                <span class="text-caption text-muted" x-text="aiBusy ? @js(__('deck.chat.thinking')) : @js(__('deck.chat.online'))">{{ __('deck.chat.online') }}</span>
                                @if ($sceneLabel)
                                    <span class="context-chip"><x-ui.icon name="squares-2x2" size="sm" class="size-3!" />{{ $sceneLabel }}</span>
                                @endif
                            </div>
                        </div>
                        <button
                            type="button"
                            x-on:click="closeChat()"
                            class="icon-btn focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                        >
                            <x-ui.icon name="x-mark" :label="__('deck.chat.close')" />
                        </button>
                    </div>
                    <div class="flex min-h-0 flex-1 flex-col">{{ $chat }}</div>
                </div>
            </section>
        @endisset
    </div>
</x-layouts.base>
