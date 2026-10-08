<x-layouts.admin
    :title="$user->name"
    :heading="$user->name"
    :nav-items="$navItems"
    :breadcrumbs="[
        ['label' => __('ui.nav.overview'), 'href' => route('admin.dashboard')],
        ['label' => __('ui.nav.accounts'), 'href' => route('admin.users.index')],
        ['label' => $user->name],
    ]"
>
    <div class="space-y-6">
        <form method="POST" action="{{ route('admin.users.update', $user) }}" novalidate>
            @csrf
            @method('PUT')

            <x-ui.card :title="__('ui.users.account_section')">
                <x-slot:header>
                    @if ($user->must_change_password)
                        <x-ui.badge tone="warning">{{ __('ui.users.temporary') }}</x-ui.badge>
                    @endif
                </x-slot:header>

                @include('admin.users._form', ['user' => $user])

                <div class="mt-6 border-t border-border pt-6">
                    <x-ui.checkbox
                        name="is_active"
                        id="user-is-active"
                        :checked="$user->is_active"
                        :describedby="$errors->has('is_active') ? 'user-is-active-error' : 'user-is-active-help'"
                    >
                        {{ __('ui.users.is_active') }}
                    </x-ui.checkbox>
                    <p id="user-is-active-help" class="mt-1 text-caption text-muted">{{ __('ui.users.is_active_help') }}</p>
                    @error('is_active')
                        <p id="user-is-active-error" class="mt-1 text-caption text-danger">{{ $message }}</p>
                    @enderror
                </div>

                <x-slot:footer>
                    <x-ui.link-button :href="route('admin.users.index')" variant="ghost">{{ __('ui.cancel') }}</x-ui.link-button>
                    <x-ui.button type="submit">{{ __('ui.save') }}</x-ui.button>
                </x-slot:footer>
            </x-ui.card>
        </form>

        <form
            method="POST"
            action="{{ route('admin.users.reset-password', $user) }}"
            novalidate
            x-data
            x-on:ui:confirmed.window="if ($event.detail.name === 'reset-password') $el.submit()"
        >
            @csrf

            <x-ui.card :title="__('ui.users.reset_password')">
                <p class="mb-4 text-body text-muted">{{ __('ui.users.reset_password_intro') }}</p>

                <div class="grid gap-4 md:grid-cols-2">
                    <x-ui.field
                        name="password"
                        id="reset-password"
                        :label="__('ui.users.temporary_password')"
                        :help="__('ui.users.temporary_password_help', ['min' => \App\Modules\Identity\Validation\UserAccessRules::PASSWORD_MIN])"
                        :error="$errors->resetPassword->first('password')"
                        required
                    >
                        <x-ui.input name="password" id="reset-password" type="password" autocomplete="new-password" required />
                    </x-ui.field>

                    <x-ui.field name="password_confirmation" id="reset-password-confirmation" :label="__('ui.auth.confirm_password')" required>
                        <x-ui.input name="password_confirmation" id="reset-password-confirmation" type="password" autocomplete="new-password" required />
                    </x-ui.field>
                </div>

                <x-slot:footer>
                    <x-ui.button variant="danger" x-on:click="$dispatch('ui:modal-open', { name: 'reset-password' })">
                        {{ __('ui.users.reset_password') }}
                    </x-ui.button>
                </x-slot:footer>
            </x-ui.card>
        </form>

        <x-ui.confirm-dialog
            name="reset-password"
            :title="__('ui.users.reset_password_confirm_title')"
            :confirm-label="__('ui.users.reset_password')"
            variant="danger"
        >
            {{ __('ui.users.reset_password_confirm_body', ['name' => $user->name]) }}
        </x-ui.confirm-dialog>
    </div>
</x-layouts.admin>
