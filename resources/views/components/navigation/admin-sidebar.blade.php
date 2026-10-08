{{--
    items: list of ['label' => ..., 'href' => ?string, 'icon' => ?string, 'active' => bool].
    Items without href render as disabled "soon" entries. Visibility is decided
    by the server (Plan 2); this component never guesses roles.
--}}
@props([
    'items' => [],
])

<nav aria-label="{{ __('ui.nav.admin') }}" {{ $attributes }}>
    <ul class="flex flex-col gap-1">
        @foreach ($items as $item)
            @php
                $active = (bool) ($item['active'] ?? false);
                $href = $item['href'] ?? null;
            @endphp
            <li>
                @if ($href !== null)
                    <a
                        href="{{ $href }}"
                        @if ($active) aria-current="page" @endif
                        class="relative flex items-center gap-3 rounded-control px-3 py-2 text-body font-medium transition-colors duration-200 motion-reduce:transition-none focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-saffron {{ $active ? 'bg-secondary-hover text-on-primary before:absolute before:inset-y-2 before:left-0 before:w-0.5 before:rounded-full before:bg-saffron' : 'text-on-primary/80 hover:bg-secondary-hover hover:text-on-primary' }}"
                    >
                        @if (! empty($item['icon']))<x-ui.icon :name="$item['icon']" />@endif
                        <span class="truncate">{{ $item['label'] }}</span>
                    </a>
                @else
                    <span aria-disabled="true" class="flex cursor-not-allowed items-center gap-3 rounded-control px-3 py-2 text-body font-medium text-on-primary/60">
                        @if (! empty($item['icon']))<x-ui.icon :name="$item['icon']" />@endif
                        <span class="truncate">{{ $item['label'] }}</span>
                        <span class="ml-auto rounded-full bg-secondary-hover px-2 py-0.5 text-caption text-on-primary/80">{{ __('ui.nav.soon') }}</span>
                    </span>
                @endif
            </li>
        @endforeach
    </ul>
</nav>
