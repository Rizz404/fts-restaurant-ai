<x-layouts.user :title="__('ui.dev.user_title')" :nav-items="$navItems">
    <div class="space-y-4">
        @include('dev._banner')

        <x-ui.card :title="__('ui.dev.user_greeting')">
            <p>{{ __('ui.dev.user_intro') }}</p>
            <p class="mt-2 text-muted">{{ __('ui.dev.synthetic_note') }}: {{ $unsafeSample }}</p>
            <x-slot:footer>
                <x-ui.link-button variant="outline" disabled>{{ __('deck.slides.menu.label') }}</x-ui.link-button>
                <x-ui.link-button variant="outline" disabled>{{ __('deck.slides.bookings.label') }}</x-ui.link-button>
            </x-slot:footer>
        </x-ui.card>
    </div>

    <x-slot:chat>
        <div class="p-4">
            <x-ui.empty-state icon="chat-bubble-left-right" :title="__('deck.chat.title')" :description="__('ui.dev.user_chat_body')" class="border-0" />
        </div>
    </x-slot:chat>
</x-layouts.user>
