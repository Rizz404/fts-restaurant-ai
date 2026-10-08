<x-layouts.guest
    :title="__('ui.auth.change_password_title')"
    :heading="__('ui.auth.change_password_title')"
    :description="$mustChange ? __('ui.auth.must_change_intro') : __('ui.auth.change_password_intro')"
>
    <form method="POST" action="{{ route('password.update') }}" class="space-y-4" novalidate x-data="{ submitting: false }" x-on:submit="submitting = true">
        @csrf

        <x-ui.field name="current_password" id="current-password" :label="__('ui.auth.current_password')" required>
            <x-ui.input name="current_password" id="current-password" type="password" autocomplete="current-password" required autofocus />
        </x-ui.field>

        <x-ui.field
            name="password"
            id="new-password"
            :label="__('ui.auth.new_password')"
            :help="__('ui.auth.password_rule', ['min' => \App\Modules\Identity\Validation\UserAccessRules::PASSWORD_MIN])"
            required
        >
            <x-ui.input name="password" id="new-password" type="password" autocomplete="new-password" required />
        </x-ui.field>

        <x-ui.field name="password_confirmation" id="new-password-confirmation" :label="__('ui.auth.confirm_password')" required>
            <x-ui.input name="password_confirmation" id="new-password-confirmation" type="password" autocomplete="new-password" required />
        </x-ui.field>

        <x-ui.button type="submit" class="w-full" x-bind:disabled="submitting" x-bind:aria-busy="submitting">
            {{ __('ui.auth.save_password') }}
        </x-ui.button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-4 text-center" x-data x-on:submit="$store.ui.clearChatStorage()">
        @csrf
        <x-ui.button type="submit" variant="ghost" size="sm">{{ __('ui.account.logout') }}</x-ui.button>
    </form>
</x-layouts.guest>
