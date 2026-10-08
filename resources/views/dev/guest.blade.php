<x-layouts.guest
    :title="__('ui.dev.guest_title')"
    :heading="__('ui.dev.guest_title')"
    :description="__('ui.dev.guest_intro')"
>
    <x-slot:before>
        @include('dev._banner')
    </x-slot:before>

    <form
        class="space-y-4"
        novalidate
        x-data="{ submitting: false }"
        x-on:submit.prevent="submitting = true; $dispatch('ui:toast', { message: @js(__('ui.dev.guest_submitted')), tone: 'info' }); setTimeout(() => submitting = false, 600)"
    >
        <x-ui.field name="email" id="guest-email" :label="__('ui.email')" required>
            <x-ui.input name="email" id="guest-email" type="email" autocomplete="username" required />
        </x-ui.field>

        <x-ui.field name="password" id="guest-password" :label="__('ui.password')" required>
            <x-ui.input name="password" id="guest-password" type="password" autocomplete="current-password" required />
        </x-ui.field>

        <x-ui.checkbox name="remember" id="guest-remember">{{ __('ui.dev.fields.remember') }}</x-ui.checkbox>

        <x-ui.button type="submit" class="w-full" x-bind:disabled="submitting" x-bind:aria-busy="submitting">
            {{ __('ui.dev.guest_submit') }}
        </x-ui.button>
    </form>
</x-layouts.guest>
