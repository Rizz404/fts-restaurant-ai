@if ($paginator->hasPages())
    @php
        $pageClasses = \App\Support\Ui\ComponentStyles::button('outline', 'sm');
        $currentClasses = \App\Support\Ui\ComponentStyles::button('primary', 'sm');
    @endphp
    <nav aria-label="{{ __('ui.pagination.label') }}" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        @if (method_exists($paginator, 'total'))
            <p class="text-caption text-muted">
                {{ __('ui.pagination.showing', ['from' => $paginator->firstItem(), 'to' => $paginator->lastItem(), 'total' => $paginator->total()]) }}
            </p>
        @endif

        <ul class="flex flex-wrap items-center gap-1">
            <li>
                @if ($paginator->onFirstPage())
                    <x-ui.link-button variant="outline" size="sm" disabled>
                        <x-ui.icon name="chevron-left" size="sm" :label="__('ui.pagination.previous')" />
                    </x-ui.link-button>
                @else
                    <x-ui.link-button :href="$paginator->previousPageUrl()" variant="outline" size="sm" rel="prev">
                        <x-ui.icon name="chevron-left" size="sm" :label="__('ui.pagination.previous')" />
                    </x-ui.link-button>
                @endif
            </li>

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li><span class="px-2 text-caption text-muted" aria-hidden="true">{{ $element }}</span></li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        <li>
                            @if ($page == $paginator->currentPage())
                                <span aria-current="page" class="{{ $currentClasses }}">
                                    <span class="sr-only">{{ __('ui.pagination.page', ['page' => $page]) }}</span>
                                    <span aria-hidden="true">{{ $page }}</span>
                                </span>
                            @else
                                <a href="{{ $url }}" class="{{ $pageClasses }}">
                                    <span class="sr-only">{{ __('ui.pagination.page', ['page' => $page]) }}</span>
                                    <span aria-hidden="true">{{ $page }}</span>
                                </a>
                            @endif
                        </li>
                    @endforeach
                @endif
            @endforeach

            <li>
                @if ($paginator->hasMorePages())
                    <x-ui.link-button :href="$paginator->nextPageUrl()" variant="outline" size="sm" rel="next">
                        <x-ui.icon name="chevron-right" size="sm" :label="__('ui.pagination.next')" />
                    </x-ui.link-button>
                @else
                    <x-ui.link-button variant="outline" size="sm" disabled>
                        <x-ui.icon name="chevron-right" size="sm" :label="__('ui.pagination.next')" />
                    </x-ui.link-button>
                @endif
            </li>
        </ul>
    </nav>
@endif
