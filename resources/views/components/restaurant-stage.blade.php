{{--
    One scene of the guest stage, composed inside x-layouts.user. All data comes from SceneData.
    Home: the host welcomes the guest beside a short welcome block (preferences, two doors to the menu and a table).
    Every other scene: the page content (default slot) opens as a sheet beside the host.
    Slots: default (sheet content), chat (overrides the chat body), head.
--}}
@props([
    'scene',
    'seo' => [],
])

@php
    use App\Modules\Experience\Enums\SceneCode;

    $code = $scene->code->value;
    $isHome = $scene->code === SceneCode::Welcome;
    $brand = config('restaurant.brand.name');

    // Search/sharing metadata: public scenes are indexable; the booking pages are not.
    $seoData = array_merge([
        'title' => $scene->title,
        'description' => $isHome ? $scene->intro : $scene->summary,
        'noindex' => $scene->code === SceneCode::Bookings,
        'jsonld' => in_array($scene->code, [SceneCode::Welcome, SceneCode::Info], true)
            ? app(\App\Modules\Experience\Support\RestaurantJsonLd::class)->build($scene->intro)
            : null,
    ], $seo);
@endphp

<x-layouts.user
    :title="$isHome ? null : $scene->title"
    :nav-items="$scene->menu"
    :mode="$isHome ? 'home' : 'room'"
    :accent="$scene->code->accent()"
    :scene="$code"
    :scene-label="$scene->title"
    :backdrop="$scene->backdropUrl"
    :character="$scene->characterUrl"
    :avatar="$scene->avatarUrl"
    :stage="$scene->stage"
    :intro="$scene->intro"
    :topics="$scene->suggestedTopics"
    :speech-lang="$scene->speechLang()"
    :character-name="$scene->characterName"
    :table-label="$scene->tableLabel"
    :seo="$seoData"
>
    @if (session('status'))
        <x-slot:flash>
            <div class="stage-flash">
                <x-ui.alert tone="success" dismissible>{{ session('status') }}</x-ui.alert>
            </div>
        </x-slot:flash>
    @endif

    @if ($isHome)
        <x-slot:home>
            <p class="stage-eyebrow"><span class="status-dot" aria-hidden="true"></span>{{ __('deck.welcome_eyebrow') }}</p>
            <h1 id="scene-title" class="stage-display"><span class="sr-only">{{ $scene->title }}: </span>{{ $brand }}</h1>
            <p class="stage-lead">{{ __('deck.tagline') }}</p>

            <div class="stage-actions">
                <a
                    href="{{ route('experience.menu') }}"
                    class="stage-action is-primary"
                    data-stage-exit
                    data-tour-line="{{ __('deck.stage.walk_to', ['room' => __('deck.slides.menu.label')]) }}"
                >
                    <x-ui.icon name="cake" size="sm" />
                    {{ __('deck.stage.open_menu') }}
                </a>
                <a
                    href="{{ route('experience.reservation') }}"
                    class="stage-action"
                    data-stage-exit
                    data-tour-line="{{ __('deck.stage.walk_to', ['room' => __('deck.slides.reservation.label')]) }}"
                >
                    <x-ui.icon name="calendar-days" size="sm" />
                    {{ __('deck.stage.open_reservation') }}
                </a>
            </div>

            <details class="home-prefs">
                <summary>
                    <x-ui.icon name="adjustments-horizontal" size="sm" />
                    {{ __('deck.prefs.title') }}
                </summary>
                <x-guest-preferences />
            </details>

            <p class="stage-hint">{{ __('deck.stage.hint') }}</p>
        </x-slot:home>
    @endif

    {{ $slot }}

    <x-slot:chat>
        @isset($chat)
            {{ $chat }}
        @else
            <x-deck-chat :scene="$scene" />
        @endisset
    </x-slot:chat>

    <x-slot:head>
        <script type="application/json" id="scene-context">@json($scene->context)</script>
        <noscript><style>[data-pending] { opacity: 1; }</style></noscript>
        {{ $head ?? '' }}
    </x-slot:head>
</x-layouts.user>
