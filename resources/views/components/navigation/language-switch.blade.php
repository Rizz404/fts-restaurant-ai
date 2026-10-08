{{-- Links add ?lang=xx to the current URL; SetLocale stores the choice in the session. --}}
@props([
    'variant' => 'light',
])

@php
    $current = app()->getLocale();
    $names = config('restaurant.locale_names');
    $dark = $variant === 'dark';
@endphp

<nav aria-label="{{ __('ui.language.label') }}" {{ $attributes }}>
    <ul class="flex items-center gap-0.5 rounded-full p-0.5 {{ $dark ? 'bg-on-primary/10' : 'border border-border bg-surface/80 backdrop-blur-md' }}">
        @foreach (config('restaurant.locales') as $code)
            @php $isCurrent = $code === $current; @endphp
            <li>
                <a
                    href="{{ request()->fullUrlWithQuery(['lang' => $code]) }}"
                    hreflang="{{ $code }}"
                    lang="{{ $code }}"
                    @if ($isCurrent) aria-current="true" @endif
                    class="block rounded-full px-2.5 py-1 font-mono text-caption font-semibold uppercase transition-colors duration-200 motion-reduce:transition-none focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus {{ $isCurrent ? ($dark ? 'bg-surface text-text' : 'bg-secondary text-on-primary') : ($dark ? 'text-on-primary/80 hover:text-on-primary' : 'text-muted hover:text-text') }}"
                >
                    <span aria-hidden="true">{{ $code }}</span>
                    <span class="sr-only">{{ $names[$code] ?? $code }}</span>
                </a>
            </li>
        @endforeach
    </ul>
</nav>
