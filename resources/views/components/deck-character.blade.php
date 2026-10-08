{{--
    The AI host in an arch frame. Random placeholder photos (placeholder=true) are clipped to the arch;
    a final transparent cut-out stands in front of it. Decorative (aria-hidden); falls back to a spark icon.
    Set floaters=false for small instances.
--}}
@props([
    'url',
    'placeholder' => false,
    'floaters' => true,
])

<div
    x-data="sceneImage"
    aria-hidden="true"
    @if ($placeholder) data-placeholder @endif
    {{ $attributes->class('deck-char') }}
>
    <span class="arch"></span>
    <span class="podium"></span>
    <div class="deck-char-img">
        <img
            x-ref="image"
            src="{{ $url }}"
            alt=""
            decoding="async"
            referrerpolicy="no-referrer"
            x-show="! failed"
            x-on:error="failed = true"
        >
    </div>
    <span class="deck-char-fallback" x-show="failed" x-cloak><x-ui.icon name="sparkles" size="lg" /></span>
    @if ($floaters)
        <span class="floater floater-a"><x-ui.icon name="sparkles" /></span>
        <span class="floater floater-b"><x-ui.icon name="cake" size="sm" /></span>
    @endif
</div>
