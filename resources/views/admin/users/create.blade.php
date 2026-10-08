<x-layouts.admin
    :title="__('ui.users.create')"
    :heading="__('ui.users.create')"
    :nav-items="$navItems"
    :breadcrumbs="[
        ['label' => __('ui.nav.overview'), 'href' => route('admin.dashboard')],
        ['label' => __('ui.nav.accounts'), 'href' => route('admin.users.index')],
        ['label' => __('ui.users.create')],
    ]"
>
    <form method="POST" action="{{ route('admin.users.store') }}" novalidate>
        @csrf

        <x-ui.card :title="__('ui.users.account_section')">
            @include('admin.users._form', ['user' => null])

            <div class="mt-6 grid gap-4 border-t border-border pt-6 md:grid-cols-2">
                <x-ui.field
                    name="password"
                    id="user-password"
                    :label="__('ui.users.temporary_password')"
                    :help="__('ui.users.temporary_password_help', ['min' => \App\Modules\Identity\Validation\UserAccessRules::PASSWORD_MIN])"
                    required
                >
                    <x-ui.input name="password" id="user-password" type="password" autocomplete="new-password" required />
                </x-ui.field>

                <x-ui.field name="password_confirmation" id="user-password-confirmation" :label="__('ui.auth.confirm_password')" required>
                    <x-ui.input name="password_confirmation" id="user-password-confirmation" type="password" autocomplete="new-password" required />
                </x-ui.field>
            </div>

            <x-slot:footer>
                <x-ui.link-button :href="route('admin.users.index')" variant="ghost">{{ __('ui.cancel') }}</x-ui.link-button>
                <x-ui.button type="submit">{{ __('ui.users.create') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.card>
    </form>
</x-layouts.admin>
