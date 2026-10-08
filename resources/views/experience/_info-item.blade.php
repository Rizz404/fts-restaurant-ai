{{-- One info result/list item. All text is escaped; the excerpt is plain text. --}}
<article class="rounded-card border border-border bg-surface p-4 shadow-card">
    <div class="flex flex-wrap items-center gap-2">
        <x-ui.badge tone="info">{{ __('info.categories.'.$category) }}</x-ui.badge>
    </div>
    <h3 class="mt-2 text-section-title font-semibold">
        <a href="{{ $href }}" class="rounded text-text hover:text-primary hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus">{{ $title }}</a>
    </h3>
    <p class="mt-1 text-body text-muted">{{ $excerpt }}</p>
</article>
