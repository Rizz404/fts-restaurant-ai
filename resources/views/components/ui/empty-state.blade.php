@props([
    'title',
    'description' => null,
    'icon' => 'inbox',
])

<div {{ $attributes->class('flex flex-col items-center justify-center gap-3 rounded-card border border-dashed border-border bg-surface px-6 py-10 text-center') }}>
    <span class="flex size-12 items-center justify-center rounded-full bg-surface-muted text-muted">
        <x-ui.icon :name="$icon" size="lg" />
    </span>
    <div class="space-y-1">
        <h3 class="text-section-title font-semibold text-text">{{ $title }}</h3>
        @if ($description !== null)
            <p class="text-body text-muted">{{ $description }}</p>
        @endif
    </div>
    @isset($action)
        <div class="mt-2">{{ $action }}</div>
    @endisset
</div>
