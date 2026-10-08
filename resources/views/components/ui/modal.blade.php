{{-- Open with: $dispatch('ui:modal-open', { name: '...' }). Slots: default body, footer. --}}
@props([
    'name',
    'title',
    'size' => 'md',
])

@php
    $id = 'modal-'.\Illuminate\Support\Str::slug($name);
@endphp

<div x-data="modal(@js($name))" {{ $attributes }}>
    <div
        x-show="open"
        x-cloak
        class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center"
    >
        <div
            x-show="open"
            x-transition.opacity.duration.150ms
            class="fixed inset-0 bg-overlay"
            aria-hidden="true"
        ></div>

        <div
            x-show="open"
            x-transition.duration.150ms
            x-trap.inert.noscroll="open"
            x-on:click.outside="close()"
            x-on:keydown.escape.prevent.stop="close()"
            role="dialog"
            aria-modal="true"
            aria-labelledby="{{ $id }}-title"
            class="relative flex max-h-[calc(100dvh-2rem)] w-full flex-col rounded-dialog bg-surface shadow-overlay {{ \App\Support\Ui\ComponentStyles::modalSize($size) }}"
        >
            <div class="flex items-start justify-between gap-4 border-b border-border px-4 py-3 md:px-6">
                <h2 id="{{ $id }}-title" class="text-section-title font-semibold text-text">{{ $title }}</h2>
                <button
                    type="button"
                    x-on:click="close()"
                    class="-m-1 rounded-control p-1 text-muted hover:text-text focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                >
                    <x-ui.icon name="x-mark" :label="__('ui.close')" />
                </button>
            </div>

            <div class="overflow-y-auto px-4 py-4 text-body text-text md:px-6">
                {{ $slot }}
            </div>

            @isset($footer)
                <div class="flex flex-col-reverse gap-2 border-t border-border px-4 py-3 sm:flex-row sm:justify-end md:px-6">
                    {{ $footer }}
                </div>
            @endisset
        </div>
    </div>
</div>
