<x-layouts.guest
    :title="__('ui.auth.login_title')"
    :heading="__('ui.auth.login_title')"
    :description="__('ui.auth.login_intro')"
>
    <form method="POST" action="{{ route('login.store') }}" class="space-y-4" novalidate x-data="{ submitting: false }" x-on:submit="submitting = true">
        @csrf

        <x-ui.field name="email" id="login-email" :label="__('ui.email')" required>
            <x-ui.input name="email" id="login-email" type="email" autocomplete="username" required autofocus />
        </x-ui.field>

        <x-ui.field name="password" id="login-password" :label="__('ui.password')" required>
            <x-ui.input name="password" id="login-password" type="password" autocomplete="current-password" required />
        </x-ui.field>

        <x-ui.checkbox name="remember" id="login-remember">{{ __('ui.auth.remember') }}</x-ui.checkbox>

        <x-ui.button type="submit" variant="ai" size="lg" class="w-full" x-bind:disabled="submitting" x-bind:aria-busy="submitting">
            {{ __('ui.auth.login') }}
            <x-ui.icon name="arrow-right" size="sm" />
        </x-ui.button>

        <p class="text-caption text-muted">{{ __('ui.auth.forgot_hint') }}</p>
    </form>
</x-layouts.guest>
