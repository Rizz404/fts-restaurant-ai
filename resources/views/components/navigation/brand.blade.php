{{--
    variant: light (on canvas) or dark (on the ink rail/sidebar). subtitle shows the product line under the name.
--}}
@props([
    'variant' => 'light',
    'href' => null,
    'subtitle' => false,
    'compact' => false,
])

@php
    $dark = $variant === 'dark';
    $tag = $href !== null ? 'a' : 'span';
@endphp

<{{ $tag }} @if ($href !== null) href="{{ $href }}" @endif {{ $attributes->class('group inline-flex min-w-0 items-center gap-3 rounded-control focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus') }}>
    {{-- The mark is the assistant's spark on the product gradient (colors come from theme tokens). --}}
    <span class="grid size-10 shrink-0 place-items-center rounded-2xl bg-linear-to-br from-primary via-saffron to-herb text-on-primary shadow-glow transition-transform duration-300 ease-cine group-hover:rotate-6 motion-reduce:transition-none">
        <x-ui.icon name="sparkles" />
    </span>
    @unless ($compact)
        <span class="min-w-0 leading-tight">
            <span class="block truncate font-display text-body font-semibold tracking-tight {{ $dark ? 'text-on-primary' : 'text-text' }}">{{ config('restaurant.brand.name') }}</span>
            @if ($subtitle)
                <span class="block truncate font-mono text-[11px] tracking-wider uppercase {{ $dark ? 'text-ink-soft' : 'text-muted' }}">{{ __('ui.brand.subtitle') }}</span>
            @endif
        </span>
    @endunless
</{{ $tag }}>
