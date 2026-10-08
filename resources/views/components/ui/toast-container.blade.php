{{-- Rendered once by x-layouts.base. Push with: $dispatch('ui:toast', { message, tone }). --}}
<div
    x-data
    role="status"
    aria-live="polite"
    aria-relevant="additions"
    class="pointer-events-none fixed inset-x-0 bottom-0 z-[60] flex flex-col items-center gap-2 p-4 sm:items-end"
>
    <template x-for="toast in $store.ui.toasts.items" x-bind:key="toast.id">
        <div
            class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-card border p-4 text-body shadow-overlay"
            x-bind:class="$store.ui.toasts.toneClass(toast.tone)"
        >
            <p class="min-w-0 flex-1" x-text="toast.message"></p>
            <button
                type="button"
                x-on:click="$store.ui.toasts.dismiss(toast.id)"
                class="-m-1 rounded-control p-1 text-muted hover:text-text focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus"
            >
                <x-ui.icon name="x-mark" size="sm" :label="__('ui.dismiss')" />
            </button>
        </div>
    </template>
</div>
