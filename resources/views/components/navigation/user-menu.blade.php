{{--
    Slide navigation: slide-sorter thumbnails (a tab bar on phones).
    items: list of ['label' => ..., 'tab' => ?string, 'href' => ?string, 'icon' => ?string, 'image' => ?string, 'active' => bool].
--}}
@props([
    'items' => [],
])

<nav aria-label="{{ __('deck.nav.slides') }}" {{ $attributes->class('sorter-nav') }}>
    <ul>
        @foreach ($items as $item)
            @php
                $active = (bool) ($item['active'] ?? false);
                $href = $item['href'] ?? null;
                $tag = $href !== null ? 'a' : 'span';
            @endphp
            <li>
                <{{ $tag }}
                    @if ($href !== null) href="{{ $href }}" @else aria-disabled="true" title="{{ __('ui.nav.soon') }}" @endif
                    @if ($active) aria-current="page" @endif
                    aria-label="{{ $item['label'] }}"
                    class="thumb focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-saffron {{ $href === null ? 'cursor-not-allowed opacity-50' : '' }}"
                >
                    @if (! empty($item['image']))
                        <img src="{{ $item['image'] }}" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer">
                    @endif
                    <span class="thumb-num" aria-hidden="true">{{ sprintf('%02d', $loop->iteration) }}</span>
                    <span class="thumb-icon">
                        @if (! empty($item['icon']))<x-ui.icon :name="$item['icon']" size="sm" />@endif
                    </span>
                    <span class="thumb-label" aria-hidden="true">{{ $item['tab'] ?? $item['label'] }}</span>
                </{{ $tag }}>
            </li>
        @endforeach
    </ul>
</nav>
