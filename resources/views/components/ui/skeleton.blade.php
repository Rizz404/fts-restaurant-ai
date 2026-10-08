{{-- Placeholder shapes only; never show fake numbers while loading. --}}
@props([
    'shape' => 'line',
    'count' => 1,
    'label' => null,
])

@php
    $shape = in_array($shape, ['line', 'card'], true) ? $shape : 'line';
    $count = max(1, min(12, (int) $count));
@endphp

<div
    {{ $attributes->class($shape === 'card' ? 'grid gap-4' : 'space-y-2') }}
    @if ($label !== null) role="status" aria-busy="true" @else aria-hidden="true" @endif
>
    @for ($i = 0; $i < $count; $i++)
        @if ($shape === 'card')
            <div class="space-y-3 rounded-card border border-border bg-surface p-4 shadow-card">
                <div class="h-4 w-1/3 animate-pulse rounded bg-surface-muted motion-reduce:animate-none"></div>
                <div class="h-3 w-full animate-pulse rounded bg-surface-muted motion-reduce:animate-none"></div>
                <div class="h-3 w-5/6 animate-pulse rounded bg-surface-muted motion-reduce:animate-none"></div>
            </div>
        @else
            <div class="h-3 animate-pulse rounded bg-surface-muted motion-reduce:animate-none {{ $i % 3 === 2 ? 'w-2/3' : 'w-full' }}"></div>
        @endif
    @endfor
    @if ($label !== null)
        <span class="sr-only">{{ $label }}</span>
    @endif
</div>
