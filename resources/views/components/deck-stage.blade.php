{{--
    Deck slide composed inside x-layouts.user (the AI presenter). All data comes from SceneData.
    Welcome is a slide deck: the host introduces the restaurant (welcome with guest preferences, one slide per
    room, then "ask me") with typed subtitles, optional voice, autoplay, keyboard and swipe navigation.
    Every other slide is a numbered canvas: the host presents the slide beside the page content (default slot).
    Slots: default (canvas content), chat (overrides the copilot body).
--}}
@props([
    'scene',
    'seo' => [],
])

@php
    use App\Modules\Experience\Enums\SceneCode;

    $code = $scene->code->value;
    $isWelcome = $scene->code === SceneCode::Welcome;
    $brand = config('restaurant.brand.name');
    $placeholder = app(\App\Modules\Experience\Support\SceneImages::class)->source() === \App\Modules\Experience\Support\SceneImages::SOURCE_PLACEHOLDER;
    $rooms = array_values(array_filter($scene->menu, fn (array $room) => $room['code'] !== 'welcome'));

    $slides = [];
    if ($isWelcome) {
        $slides[] = ['kind' => 'welcome', 'accent' => 'saffron', 'title' => $scene->title, 'text' => $scene->intro, 'label' => $scene->title, 'icon' => 'sparkles', 'image' => $scene->backdropUrl];
        foreach ($rooms as $room) {
            $roomCode = $room['code'];
            $points = __('deck.slides.'.$roomCode.'.points');
            $topics = __('deck.slides.'.$roomCode.'.topics');
            $topics = is_array($topics) ? array_values($topics) : [];
            $slides[] = [
                'kind' => 'room',
                'accent' => $room['accent'],
                'title' => $room['label'],
                'text' => __('deck.slides.'.$roomCode.'.intro'),
                'label' => $room['label'],
                'icon' => $room['icon'],
                'image' => $room['image'],
                'href' => $room['href'],
                'summary' => __('deck.slides.'.$roomCode.'.summary'),
                'points' => is_array($points) ? array_values($points) : $topics,
                'ask' => $topics[0] ?? null,
            ];
        }
        $slides[] = ['kind' => 'ask', 'accent' => 'herb', 'title' => __('deck.ask_prompt'), 'text' => __('deck.tour.finale'), 'label' => __('deck.tour.ask_label'), 'icon' => 'chat-bubble-left-right', 'image' => null];
    }
    $total = count($slides);

    $stageConfig = [
        'intro' => $scene->intro,
        'lang' => $scene->speechLang(),
        'accent' => $scene->code->accent(),
        'slides' => array_map(fn (array $slide) => ['text' => $slide['text'], 'title' => $slide['title'], 'accent' => $slide['accent']], $slides),
    ];

    // Search/sharing metadata: public slides are indexable; the booking pages are not.
    $seoData = array_merge([
        'title' => $scene->title,
        'description' => $isWelcome ? $scene->intro : $scene->summary,
        'noindex' => $scene->code === SceneCode::Bookings,
        'jsonld' => in_array($scene->code, [SceneCode::Welcome, SceneCode::Info], true)
            ? app(\App\Modules\Experience\Support\RestaurantJsonLd::class)->build($scene->intro)
            : null,
    ], $seo);

    $position = 1;
    foreach ($scene->menu as $i => $item) {
        if ($item['active']) {
            $position = $i + 1;
        }
    }
@endphp

<x-layouts.user
    :title="$isWelcome ? null : $scene->title"
    :nav-items="$scene->menu"
    :mode="$isWelcome ? 'deck' : 'room'"
    :accent="$scene->code->accent()"
    :scene="$code"
    :scene-label="$scene->title"
    :avatar="$scene->characterUrl"
    :stage="$stageConfig"
    :table-label="$scene->tableLabel"
    :seo="$seoData"
>
    @if (session('status'))
        <x-slot:flash>
            <div class="ws-flash">
                <x-ui.alert tone="success" dismissible>{{ session('status') }}</x-ui.alert>
            </div>
        </x-slot:flash>
    @endif

    @if ($isWelcome)
        <x-slot:topbar>
            <p class="slide-counter" aria-hidden="true">
                <b x-text="pad(index + 1)">01</b><span>/</span><span x-text="pad(count)">{{ sprintf('%02d', $total) }}</span>
            </p>
        </x-slot:topbar>

        {{-- Desktop sorter: one thumbnail per deck slide. Phones use the room tab bar below instead. --}}
        <x-slot:navigation>
            <nav class="sorter-nav sorter-nav--slides" aria-label="{{ __('deck.tour.slides') }}">
                <ul>
                    @foreach ($slides as $i => $slide)
                        <li>
                            <button
                                type="button"
                                class="thumb focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-saffron"
                                x-on:click="go({{ $i }})"
                                @if ($i === 0) aria-current="true" @endif
                                x-bind:aria-current="index === {{ $i }} ? 'true' : null"
                            >
                                @if ($slide['image'])
                                    <img src="{{ $slide['image'] }}" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer">
                                @endif
                                <span class="thumb-num" aria-hidden="true">{{ sprintf('%02d', $i + 1) }}</span>
                                <span class="thumb-icon"><x-ui.icon :name="$slide['icon']" size="sm" /></span>
                                <span class="thumb-label">{{ $slide['label'] }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            </nav>
        </x-slot:navigation>
    @endif

    @if ($isWelcome)
        <section
            class="deck"
            data-dir="next"
            data-char="right"
            x-bind:data-dir="direction"
            x-bind:data-char="index % 2 === 0 ? 'right' : 'left'"
            x-bind:class="{ 'is-typing': typing, 'is-speaking': speaking }"
            x-on:touchstart.passive="touchStart($event)"
            x-on:touchend.passive="touchEnd($event)"
            aria-roledescription="carousel"
            aria-label="{{ __('deck.tour.label') }}"
        >
            <x-deck-character :url="$scene->characterUrl" :placeholder="$placeholder" />

            <div class="slides">
                @foreach ($slides as $i => $slide)
                    <article
                        class="slide {{ $i === 0 ? 'is-active' : '' }}"
                        data-char="{{ $i % 2 === 0 ? 'right' : 'left' }}"
                        x-bind:class="slideClass({{ $i }})"
                        aria-roledescription="slide"
                        aria-label="{{ $i + 1 }} / {{ $total }}"
                    >
                        @if ($slide['kind'] === 'welcome')
                            <p class="slide-eyebrow slide-in" style="--n: 0"><span class="status-dot" aria-hidden="true"></span>{{ __('deck.welcome_eyebrow') }}</p>
                            <h1 id="scene-title" class="slide-title slide-in" style="--n: 1"><span class="sr-only">{{ $scene->title }}: </span><span class="grad">{{ $brand }}</span></h1>
                            <p class="slide-sub slide-in" style="--n: 2">{{ __('deck.tagline') }}</p>

                            <x-guest-preferences class="slide-in" style="--n: 3" />

                            <ul class="agenda is-compact slide-in" style="--n: 4" aria-label="{{ __('deck.tour.agenda') }}">
                                @foreach ($rooms as $room)
                                    <li>
                                        <a href="{{ $room['href'] }}" class="focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus">
                                            <span class="agenda-num">{{ sprintf('%02d', $loop->iteration + 1) }}</span>
                                            <span class="min-w-0">
                                                <span class="agenda-title">{{ $room['label'] }}</span>
                                                <span class="agenda-sub">{{ __('deck.slides.'.$room['code'].'.summary') }}</span>
                                            </span>
                                            <x-ui.icon name="arrow-right" size="sm" />
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                            <div class="slide-cta slide-in" style="--n: 5">
                                <x-ui.button variant="ai" size="lg" x-on:click="next()">
                                    {{ __('deck.tour.start') }}
                                    <x-ui.icon name="arrow-right" size="sm" />
                                </x-ui.button>
                            </div>
                        @elseif ($slide['kind'] === 'room')
                            <p class="slide-num slide-in" style="--n: 0" aria-hidden="true">{{ sprintf('%02d', $i + 1) }}</p>
                            <p class="slide-eyebrow slide-in" style="--n: 1"><x-ui.icon :name="$slide['icon']" size="sm" />{{ __('deck.explore') }}</p>
                            <h2 class="slide-title slide-in" style="--n: 2">{{ $slide['title'] }}</h2>
                            <p class="slide-sub slide-in" style="--n: 3">{{ $slide['summary'] }}</p>
                            @if ($slide['points'] !== [])
                                <ul class="points slide-in" style="--n: 4" aria-label="{{ __('deck.tour.points') }}">
                                    @foreach ($slide['points'] as $point)
                                        <li><span aria-hidden="true"><x-ui.icon name="check" size="sm" class="size-3.5!" /></span><span>{{ $point }}</span></li>
                                    @endforeach
                                </ul>
                            @endif
                            <div class="slide-cta slide-in" style="--n: 5">
                                <x-ui.link-button :href="$slide['href']" variant="ai" size="lg">
                                    {{ __('deck.tour.enter', ['room' => $slide['label']]) }}
                                    <x-ui.icon name="arrow-right" size="sm" />
                                </x-ui.link-button>
                                @if ($slide['ask'])
                                    <button
                                        type="button"
                                        class="prompt focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                                        x-on:click="$dispatch('chat:ask', { message: {{ \Illuminate\Support\Js::from($slide['ask']) }} })"
                                    >
                                        <x-ui.icon name="sparkles" size="sm" />
                                        <span>{{ __('deck.tour.ask_here') }}</span>
                                    </button>
                                @endif
                            </div>
                        @else
                            <p class="slide-num slide-in" style="--n: 0" aria-hidden="true">{{ sprintf('%02d', $i + 1) }}</p>
                            <p class="slide-eyebrow slide-in" style="--n: 1"><x-ui.icon name="sparkles" size="sm" />{{ $slide['label'] }}</p>
                            <h2 class="slide-title slide-in" style="--n: 2"><span class="grad">{{ $slide['title'] }}</span></h2>
                            <p class="slide-sub slide-in" style="--n: 3">{{ __('deck.tour.finale') }}</p>
                            <form
                                class="cmd slide-in"
                                style="--n: 4"
                                role="search"
                                x-data="{ q: '' }"
                                x-on:submit.prevent="if (q.trim()) { $dispatch('chat:ask', { message: q.trim() }); q = ''; }"
                            >
                                <div class="cmd-inner">
                                    <x-ui.icon name="sparkles" class="text-primary" />
                                    <label for="hero-command" class="sr-only">{{ __('deck.chat.input_label') }}</label>
                                    <input
                                        id="hero-command"
                                        type="text"
                                        x-model="q"
                                        maxlength="2000"
                                        autocomplete="off"
                                        placeholder="{{ __('deck.command_placeholder') }}"
                                    >
                                    <button type="submit" class="send-btn focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2 focus-visible:ring-offset-surface" x-bind:disabled="! q.trim()">
                                        <x-ui.icon name="paper-airplane" />
                                        <span class="sr-only">{{ __('deck.chat.send') }}</span>
                                    </button>
                                </div>
                            </form>
                            @if ($scene->suggestedTopics !== [])
                                <ul class="prompts slide-in" style="--n: 5" aria-label="{{ __('deck.suggested_topics') }}">
                                    @foreach ($scene->suggestedTopics as $topic)
                                        <li>
                                            <button
                                                type="button"
                                                class="prompt focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                                                x-on:click="$dispatch('chat:ask', { message: {{ \Illuminate\Support\Js::from($topic) }} })"
                                            >
                                                <x-ui.icon name="sparkles" size="sm" />
                                                <span>{{ $topic }}</span>
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        @endif
                    </article>
                @endforeach
            </div>

            <div class="caption" role="group" aria-label="{{ __('deck.tour.captions') }}">
                <span x-data="sceneImage" class="caption-avatar" aria-hidden="true">
                    <img
                        x-ref="image"
                        src="{{ $scene->characterUrl }}"
                        alt=""
                        decoding="async"
                        referrerpolicy="no-referrer"
                        x-show="! failed"
                        x-on:error="failed = true"
                    >
                    <x-ui.icon name="sparkles" x-show="failed" x-cloak />
                </span>

                <div class="min-w-0">
                    <p class="caption-name" aria-hidden="true">
                        <span>{{ $scene->characterName }}</span>
                        <span class="thinking-dots" x-show="thinking" x-cloak><i style="--i: 0"></i><i style="--i: 1"></i><i style="--i: 2"></i></span>
                    </p>
                    {{-- Screen readers get the full line when the slide changes; the typed copy is visual only. --}}
                    <p class="sr-only" aria-live="polite" x-text="titleNow + ': ' + textNow">{{ $scene->intro }}</p>
                    <p class="caption-text ai-text" aria-hidden="true" data-pending x-init="$el.removeAttribute('data-pending')" x-text="typed">{{ $scene->intro }}</p>
                </div>

                <div class="caption-controls">
                    <div class="dots" role="group" aria-label="{{ __('deck.tour.slides') }}">
                        @foreach ($slides as $i => $slide)
                            <button
                                type="button"
                                class="focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                                aria-label="{{ $i + 1 }}: {{ $slide['label'] }}"
                                x-on:click="go({{ $i }})"
                                @if ($i === 0) aria-current="true" @endif
                                x-bind:aria-current="index === {{ $i }} ? 'true' : null"
                            ></button>
                        @endforeach
                    </div>

                    <div class="caption-buttons">
                        <button type="button" class="bubble-action focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus" x-show="thinking || typing" x-cloak x-on:click="skipTyping()">{{ __('deck.skip') }}</button>

                        <button
                            type="button"
                            class="icon-btn focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                            x-show="narratorSupported"
                            x-cloak
                            x-on:click="toggleNarrator()"
                            aria-pressed="false"
                            x-bind:aria-pressed="voice"
                        >
                            <x-ui.icon name="speaker-wave" size="sm" />
                            <span class="sr-only" x-text="voice ? @js(__('deck.narrator_stop')) : @js(__('deck.narrator_play'))">{{ __('deck.narrator_play') }}</span>
                        </button>

                        <button
                            type="button"
                            class="icon-btn focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                            x-on:click="toggleAutoplay()"
                            aria-pressed="false"
                            x-bind:aria-pressed="autoplay"
                        >
                            <x-ui.icon name="play" size="sm" x-show="! autoplay" />
                            <x-ui.icon name="pause" size="sm" x-show="autoplay" x-cloak />
                            <span class="sr-only" x-text="autoplay ? @js(__('deck.tour.pause')) : @js(__('deck.tour.autoplay'))">{{ __('deck.tour.autoplay') }}</span>
                        </button>

                        <button type="button" class="icon-btn focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus" x-on:click="prev()" x-bind:disabled="index === 0" disabled>
                            <x-ui.icon name="arrow-left" size="sm" />
                            <span class="sr-only">{{ __('deck.tour.prev') }}</span>
                        </button>
                        <button type="button" class="icon-btn icon-btn-ai focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus" x-on:click="next()" x-bind:disabled="index === count - 1">
                            <x-ui.icon name="arrow-right" size="sm" />
                            <span class="sr-only">{{ __('deck.tour.next') }}</span>
                        </button>
                    </div>
                </div>

                <div class="progress" aria-hidden="true" x-bind:class="{ 'is-running': dwelling }" x-bind:style="'--dwell: ' + dwellMs + 'ms'"><i></i></div>
            </div>
        </section>
    @else
        <div class="room">
            <aside class="presenter" aria-label="{{ $scene->characterName }}" x-bind:class="{ 'is-typing': typing, 'is-speaking': speaking }">
                <x-deck-character :url="$scene->characterUrl" :placeholder="$placeholder" />

                <div class="bubble">
                    <span x-data="sceneImage" class="caption-avatar" aria-hidden="true">
                        <img
                            x-ref="image"
                            src="{{ $scene->characterUrl }}"
                            alt=""
                            decoding="async"
                            referrerpolicy="no-referrer"
                            x-show="! failed"
                            x-on:error="failed = true"
                        >
                        <x-ui.icon name="sparkles" x-show="failed" x-cloak />
                    </span>

                    <div class="min-w-0">
                        <p class="caption-name" aria-hidden="true">
                            <span>{{ $scene->characterName }}</span>
                            <span class="thinking-dots" x-show="thinking" x-cloak><i style="--i: 0"></i><i style="--i: 1"></i><i style="--i: 2"></i></span>
                        </p>

                        <p class="sr-only">{{ $scene->intro }}</p>
                        <p class="bubble-text ai-text" aria-hidden="true" data-pending x-init="$el.removeAttribute('data-pending')" x-text="typed">{{ $scene->intro }}</p>

                        @if ($scene->suggestedTopics !== [])
                            <ul class="prompts mt-3" aria-label="{{ __('deck.suggested_topics') }}">
                                @foreach ($scene->suggestedTopics as $topic)
                                    <li>
                                        <button
                                            type="button"
                                            class="prompt focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus"
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
                            <button type="button" class="bubble-action focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus" x-show="thinking || typing" x-cloak x-on:click="skipTyping()">{{ __('deck.skip') }}</button>
                            <button
                                type="button"
                                class="bubble-action focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus"
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
                </div>
            </aside>

            <section class="canvas" aria-labelledby="scene-title">
                <header class="canvas-head">
                    <img src="{{ $scene->backdropUrl }}" alt="" decoding="async" referrerpolicy="no-referrer">
                    <p class="slide-num" aria-hidden="true">{{ sprintf('%02d', $position) }}</p>
                    <div class="min-w-0 pb-1">
                        <p class="slide-eyebrow">{{ __('deck.explore') }}</p>
                        <h1 id="scene-title" class="slide-title">{{ $scene->title }}</h1>
                    </div>
                </header>
                <div class="canvas-body">
                    <div class="space-y-4">{{ $slot }}</div>
                </div>
            </section>
        </div>
    @endif

    <x-slot:chat>
        @isset($chat)
            {{ $chat }}
        @else
            <x-deck-chat :scene="$scene" />
        @endisset
    </x-slot:chat>

    <x-slot:head>
        <script type="application/json" id="scene-context">@json($scene->context)</script>
        <noscript><style>.ai-text[data-pending] { opacity: 1; }</style></noscript>
        {{ $head ?? '' }}
    </x-slot:head>
</x-layouts.user>
