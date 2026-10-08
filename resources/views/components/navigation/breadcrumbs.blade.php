{{-- items: list of ['label' => ..., 'href' => ?string]; the last item is the current page. --}}
@props([
    'items' => [],
])

@if ($items !== [])
    <nav aria-label="{{ __('ui.breadcrumbs') }}" {{ $attributes }}>
        <ol class="flex flex-wrap items-center gap-1 text-caption text-muted">
            @foreach ($items as $item)
                <li class="flex items-center gap-1">
                    @if (! $loop->first)
                        <x-ui.icon name="chevron-right" size="sm" />
                    @endif
                    @if ($loop->last || empty($item['href']))
                        <span @if ($loop->last) aria-current="page" @endif class="{{ $loop->last ? 'font-medium text-text' : '' }}">{{ $item['label'] }}</span>
                    @else
                        <a href="{{ $item['href'] }}" class="rounded hover:text-text hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus">{{ $item['label'] }}</a>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
