{{--
    Guest stage: a full-screen illustrated room with the AI host standing in it. The host speaks from a bubble
    (typed narration, optional voice), a round icon dock at the bottom moves the guest between the rooms (pointing
    at an icon makes the host describe that room), the page content opens as a sheet beside the host, and the
    host chat floats over the right side. Guests are anonymous: there is no account menu; staff open /login directly.
    Slots: default (sheet content on room pages), home (the welcome block on the home page), flash (alerts),
    chat (chat body), head.
    Props: mode (home|room), accent (saffron|herb|chili|primary), scene (css hook), sceneLabel (title + chat context),
    backdrop / character / avatar (image URLs), stage (layout: anchor, focus, focusMobile), intro (what the host says),
    topics (suggested questions), speechLang (BCP 47), characterName, tableLabel (QR table note), seo (see x-seo).
--}}
@props([
    'title' => null,
    'navItems' => [],
    'mode' => 'room',
    'accent' => 'saffron',
    'scene' => 'default',
    'sceneLabel' => null,
    'backdrop' => null,
    'character' => null,
    'avatar' => null,
    'stage' => [],
    'intro' => null,
    'topics' => [],
    'speechLang' => 'id-ID',
    'characterName' => null,
    'tableLabel' => null,
    'seo' => [],
])

@php
    use App\Modules\Experience\Enums\SceneCode;

    $images = app(\App\Modules\Experience\Support\SceneImages::class);
    $backdrop ??= $images->backdrop(SceneCode::Welcome);
    $character ??= $images->character();
    $avatar ??= $images->avatar();
    $characterName ??= __('deck.assistant_name');
    $isHome = $mode === 'home';

    // What the host says about each room while the guest points at its icon.
    $hints = collect($navItems)
        ->filter(fn (array $item) => ! empty($item['code']))
        ->mapWithKeys(fn (array $item) => [$item['code'] => __('deck.slides.'.$item['code'].'.summary')])
        ->all();

    $narration = [
        'intro' => (string) $intro,
        'lang' => $speechLang,
        'hints' => $hints,
    ];
@endphp

<x-layouts.base :title="$title" :seo="$seo" class="stage-page">
    @isset($head)
        <x-slot:head>{{ $head }}</x-slot:head>
    @endisset

    <main
        id="main-content"
        tabindex="-1"
        x-data="userShell"
        x-bind:class="{ 'is-chat-open': chatOpen, 'is-busy': aiBusy }"
        class="stage focus:outline-none"
        data-stage
        data-mode="{{ $mode }}"
        data-scene="{{ $scene }}"
        data-accent="{{ $accent }}"
        style="--stage-focus: {{ $stage['focus'] ?? 'center 50%' }}; --stage-focus-mobile: {{ $stage['focusMobile'] ?? '50% 50%' }}"
    >
        <div class="stage-loader" data-stage-loader role="status">
            <span class="stage-mark" aria-hidden="true"><x-ui.icon name="sparkles" /></span>
            <p>{{ __('deck.stage.loading') }}</p>
        </div>

        <img src="{{ $backdrop }}" alt="" class="stage-image" fetchpriority="high" decoding="async" referrerpolicy="no-referrer">
        <div class="stage-shade" aria-hidden="true"></div>
        <img src="{{ $character }}" alt="" class="stage-character" data-anchor="{{ $stage['anchor'] ?? 'right' }}" decoding="async" referrerpolicy="no-referrer">
        <p class="stage-tour" data-stage-tour aria-hidden="true"></p>

        <div x-data="stageNarration(@js($narration))" class="stage-scene" x-bind:class="{ 'is-typing': typing, 'is-speaking': speaking }">
            <header class="stage-header">
                <x-navigation.brand variant="dark" :href="route('experience.welcome')" class="stage-brand" data-stage-exit data-tour-line="{{ __('deck.stage.walk_home') }}" />

                @if ($tableLabel !== null)
                    <p class="table-chip">
                        <x-ui.icon name="map-pin" size="sm" class="size-3.5!" />
                        {{ __('deck.table', ['label' => $tableLabel]) }}
                    </p>
                @endif

                <div class="stage-tools">
                    <button
                        type="button"
                        x-on:click="toggleSound()"
                        aria-pressed="false"
                        x-bind:aria-pressed="soundOn"
                        class="stage-tool"
                    >
                        <x-ui.icon name="speaker-x-mark" size="sm" x-show="! soundOn" />
                        <x-ui.icon name="speaker-wave" size="sm" x-show="soundOn" x-cloak />
                        <span class="sr-only" x-text="soundOn ? @js(__('deck.sound_on')) : @js(__('deck.sound_off'))">{{ __('deck.sound_off') }}</span>
                    </button>

                    <x-navigation.language-switch variant="dark" class="stage-lang" />
                </div>
            </header>

            {{ $flash ?? '' }}

            @if ($isHome)
                <section class="stage-home" aria-labelledby="scene-title">{{ $home ?? '' }}</section>
            @else
                <section class="stage-sheet" aria-labelledby="scene-title">
                    <header class="stage-sheet-head">
                        <div class="min-w-0">
                            <p class="stage-eyebrow">{{ __('deck.explore') }}</p>
                            <h1 id="scene-title" class="stage-title">{{ $sceneLabel ?? $title }}</h1>
                        </div>
                        <a
                            href="{{ route('experience.welcome') }}"
                            class="stage-close"
                            data-stage-exit
                            data-tour-line="{{ __('deck.stage.walk_home') }}"
                        >
                            <x-ui.icon name="x-mark" :label="__('deck.stage.home')" />
                        </a>
                    </header>
                    <div class="stage-sheet-body">
                        <div class="space-y-4">{{ $slot }}</div>
                    </div>
                </section>
            @endif

            {{-- The host's speech bubble: the full line is in the HTML; the typed copy is visual only. --}}
            <aside class="stage-bubble" aria-label="{{ $characterName }}">
                <span x-data="sceneImage" class="bubble-avatar" aria-hidden="true">
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
                </span>

                <div class="min-w-0">
                    <p class="bubble-name" aria-hidden="true">
                        <span>{{ $characterName }}</span>
                        <span class="thinking-dots" x-show="thinking" x-cloak><i style="--i: 0"></i><i style="--i: 1"></i><i style="--i: 2"></i></span>
                    </p>
                    <p class="sr-only">{{ $intro }}</p>
                    <p class="bubble-text" aria-hidden="true" data-narrator-text data-pending x-init="$el.removeAttribute('data-pending')" x-text="typed">{{ $intro }}</p>

                    @if ($topics !== [])
                        <ul class="bubble-topics" aria-label="{{ __('deck.suggested_topics') }}">
                            @foreach (array_slice($topics, 0, 3) as $topic)
                                <li>
                                    <button
                                        type="button"
                                        class="prompt"
                                        x-on:click="$dispatch('chat:ask', { message: {{ \Illuminate\Support\Js::from($topic) }} })"
                                    >
                                        <x-ui.icon name="sparkles" size="sm" />
                                        <span>{{ $topic }}</span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <div class="bubble-actions">
                        <button type="button" class="bubble-action" data-narrator-skip x-show="thinking || typing" x-cloak x-on:click="skipTyping()">{{ __('deck.skip') }}</button>
                        <button
                            type="button"
                            class="bubble-action"
                            data-narrator-listen
                            x-show="narratorSupported"
                            x-cloak
                            x-on:click="toggleNarrator()"
                            aria-pressed="false"
                            x-bind:aria-pressed="voice"
                        >
                            <x-ui.icon name="speaker-wave" size="sm" />
                            <span x-text="voice ? @js(__('deck.narrator_stop')) : @js(__('deck.narrator_play'))">{{ __('deck.narrator_play') }}</span>
                        </button>
                    </div>
                </div>
            </aside>

            <nav class="stage-dock" aria-label="{{ __('deck.stage.dock') }}">
                <ul>
                    @foreach ($navItems as $item)
                        @php
                            $href = $item['href'] ?? null;
                            $isActive = (bool) ($item['active'] ?? false);
                            $tour = ($item['code'] ?? null) === 'welcome' ? __('deck.stage.walk_home') : __('deck.stage.walk_to', ['room' => $item['label']]);
                        @endphp
                        <li>
                            @if ($href !== null)
                                <a
                                    href="{{ $href }}"
                                    class="dock-item"
                                    data-stage-exit
                                    data-tour-line="{{ $tour }}"
                                    data-room="{{ $item['code'] ?? '' }}"
                                    aria-label="{{ $item['label'] }}"
                                    @if ($isActive) aria-current="page" @endif
                                    x-on:mouseenter="hint('{{ $item['code'] ?? '' }}')"
                                    x-on:mouseleave="unhint()"
                                    x-on:focus="hint('{{ $item['code'] ?? '' }}')"
                                    x-on:blur="unhint()"
                                >
                                    <x-ui.icon :name="$item['icon']" />
                                    <span class="dock-label" aria-hidden="true">{{ $item['tab'] ?? $item['label'] }}</span>
                                </a>
                            @else
                                <span class="dock-item is-disabled" aria-disabled="true" aria-label="{{ $item['label'] }}" title="{{ __('ui.nav.soon') }}">
                                    <x-ui.icon :name="$item['icon']" />
                                    <span class="dock-label" aria-hidden="true">{{ $item['tab'] ?? $item['label'] }}</span>
                                </span>
                            @endif
                        </li>
                    @endforeach

                    @isset($chat)
                        <li class="dock-sep" aria-hidden="true"></li>
                        <li>
                            <button
                                type="button"
                                id="chat-trigger"
                                x-on:click="$dispatch('chat:open')"
                                aria-controls="chat-dock"
                                aria-expanded="false"
                                x-bind:aria-expanded="chatOpen"
                                aria-label="{{ __('deck.chat.ask_ai') }}"
                                class="dock-item dock-ask"
                            >
                                <span class="orb orb-xs" aria-hidden="true"><span class="orb-core"></span></span>
                                <span class="dock-label" aria-hidden="true">{{ __('deck.chat.ask_ai') }}</span>
                            </button>
                        </li>
                    @endisset
                </ul>
            </nav>
        </div>

        @isset($chat)
            <section
                id="chat-dock"
                x-on:keydown.escape.prevent.stop="closeChat()"
                aria-labelledby="chat-dock-title"
                class="stage-chat"
                inert
                x-bind:inert="! chatOpen"
            >
                <div class="stage-chat-inner">
                    <div class="copilot-head">
                        <span x-data="sceneImage" class="copilot-avatar">
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

        <noscript><p class="stage-noscript">{{ __('deck.stage.noscript') }}</p></noscript>
    </main>
</x-layouts.base>
