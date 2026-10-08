{{-- In-app notifications for the signed-in user (items and paths come from the API). --}}
@props([
    'variant' => 'light',
])

@auth
    <div x-data="notificationBell" {{ $attributes }}>
        <x-ui.dropdown align="right" :trigger-variant="match ($variant) { 'dark' => 'secondary', 'glass' => 'glass', default => 'ghost' }">
            <x-slot:trigger class="relative px-2">
                <x-ui.icon name="bell" :label="__('ui.notifications.title')" />
                <span
                    x-show="unread > 0"
                    x-cloak
                    x-text="unread > 9 ? '9+' : unread"
                    class="absolute -top-1 -right-1 min-w-5 rounded-full bg-danger px-1 text-center text-caption font-semibold text-on-primary"
                ></span>
            </x-slot:trigger>

            <div class="w-72 max-w-[85vw]">
                <p class="border-b border-border px-3 py-2 text-caption font-semibold tracking-wide text-muted uppercase">{{ __('ui.notifications.title') }}</p>
                <p x-show="items.length === 0" class="px-3 py-4 text-body text-muted">{{ __('ui.notifications.empty') }}</p>
                <template x-for="item in items" x-bind:key="item.id">
                    <button
                        type="button"
                        role="menuitem"
                        tabindex="-1"
                        x-on:click="open(item)"
                        x-bind:disabled="! item.path"
                        class="flex w-full flex-col items-start gap-0.5 rounded-control px-3 py-2 text-left hover:bg-surface-muted focus:bg-surface-muted focus:outline-none disabled:opacity-60"
                    >
                        <span class="text-body font-medium text-text" x-bind:class="item.read ? '' : 'font-semibold'" x-text="item.label"></span>
                        <span class="text-caption text-muted" x-show="item.reference" x-text="item.reference + ' · ' + item.summary"></span>
                    </button>
                </template>
            </div>
        </x-ui.dropdown>
    </div>
@endauth
