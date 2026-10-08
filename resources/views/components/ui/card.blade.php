@props([
    'padding' => 'md',
    'title' => null,
])

<section {{ $attributes->class(\App\Support\Ui\ComponentStyles::card($padding)) }}>
    @if ($title !== null || isset($header))
        <header class="mb-4 flex flex-wrap items-start justify-between gap-3">
            @if ($title !== null)
                <h2 class="text-section-title font-semibold text-text">{{ $title }}</h2>
            @endif
            {{ $header ?? '' }}
        </header>
    @endif

    <div class="text-body text-text">
        {{ $slot }}
    </div>

    @isset($footer)
        <footer class="mt-4 flex flex-wrap items-center justify-end gap-2 border-t border-border pt-4">
            {{ $footer }}
        </footer>
    @endisset
</section>
