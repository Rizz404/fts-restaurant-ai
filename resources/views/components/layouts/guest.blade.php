{{--
    Sign-in page for login, password change, and other guest pages: the form on a calm tinted canvas, and on wide
    screens a showcase panel where the AI character greets you and a mock command bar "reads" real prompts
    from the restaurant slides.
    Slots: default (form), before (above the card header), head. Props: heading/description appear in the card header.
--}}
@props([
    'title' => null,
    'heading' => null,
    'description' => null,
    'width' => 'md',
])

@php
    $images = app(\App\Modules\Experience\Support\SceneImages::class);
    $placeholder = $images->source() === \App\Modules\Experience\Support\SceneImages::SOURCE_PLACEHOLDER;
    $greeting = __('deck.slides.welcome.intro');
    $prompts = collect(\App\Modules\Experience\Enums\SceneCode::values())
        ->flatMap(fn (string $code) => (array) __('deck.slides.'.$code.'.topics'))
        ->filter(fn ($prompt) => is_string($prompt) && $prompt !== '')
        ->values()
        ->all();
@endphp

<x-layouts.base :title="$title" :seo="['noindex' => true]">
    @isset($head)
        <x-slot:head>{{ $head }}</x-slot:head>
    @endisset

    <div x-data="loginDemo(@js($prompts), @js($greeting))" x-bind:class="{ 'is-busy': busy }" class="auth">
        <div class="ws-bg" aria-hidden="true"><i></i></div>

        <div class="auth-side">
            <header class="auth-top">
                <x-navigation.brand subtitle />
                <x-navigation.language-switch />
            </header>

            <main id="main-content" tabindex="-1" class="auth-main focus:outline-none">
                <div class="auth-mini" aria-hidden="true">
                    <x-deck-character :url="$images->character()" :placeholder="$placeholder" :floaters="false" />
                </div>

                <div class="auth-card" data-width="{{ $width === 'lg' ? 'lg' : 'md' }}">
                    @if (session('status'))
                        <x-ui.alert tone="info" class="mb-4">{{ session('status') }}</x-ui.alert>
                    @endif

                    {{ $before ?? '' }}

                    @if ($heading !== null)
                        <div class="auth-card-head">
                            <p class="eyebrow"><span class="status-dot" aria-hidden="true"></span>{{ __('ui.brand.subtitle') }}</p>
                            <h1 class="auth-title">{{ $heading }}</h1>
                            @if ($description !== null)
                                <p class="mt-2 text-body text-muted">{{ $description }}</p>
                            @endif
                        </div>
                    @endif

                    {{ $slot }}
                </div>
            </main>

            <footer class="auth-foot">
                &copy; {{ now()->year }} {{ config('restaurant.brand.name') }} · {{ __('ui.footer.staff_area') }}
            </footer>
        </div>

        <aside class="auth-showcase" aria-hidden="true">
            <x-deck-character :url="$images->character()" :placeholder="$placeholder" />

            <div class="auth-bubble" x-bind:class="{ 'is-typing': typingGreeting }">
                <p class="caption-name">{{ __('deck.assistant_name') }}</p>
                <p class="ai-text mt-1" x-text="said">{{ $greeting }}</p>
            </div>

            @if ($prompts !== [])
                <div class="mock-cmd" x-bind:class="{ 'is-typing': typing }">
                    <x-ui.icon name="sparkles" class="text-herb" />
                    <span class="ai-text min-w-0 flex-1 truncate" x-text="shown">{{ $prompts[0] }}</span>
                    <span class="send-btn"><x-ui.icon name="paper-airplane" size="sm" /></span>
                </div>
            @endif
        </aside>
    </div>
</x-layouts.base>
