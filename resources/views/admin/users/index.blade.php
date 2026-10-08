<x-layouts.admin
    :title="__('ui.nav.accounts')"
    :heading="__('ui.nav.accounts')"
    :nav-items="$navItems"
    :breadcrumbs="[['label' => __('ui.nav.overview'), 'href' => route('admin.dashboard')], ['label' => __('ui.nav.accounts')]]"
>
    <x-slot:actions>
        <x-ui.link-button :href="route('admin.users.create')">
            <x-ui.icon name="plus" size="sm" />
            {{ __('ui.users.create') }}
        </x-ui.link-button>
    </x-slot:actions>

    <form method="GET" action="{{ route('admin.users.index') }}" class="mb-4 grid gap-3 md:grid-cols-[1fr_14rem_12rem_auto] md:items-end">
        <x-ui.field name="q" id="filter-q" :label="__('ui.search')">
            <x-ui.input name="q" id="filter-q" type="search" :value="$filters['q']" :placeholder="__('ui.users.search_placeholder')" />
        </x-ui.field>
        <x-ui.field name="role" id="filter-role" :label="__('ui.users.roles')">
            <x-ui.select name="role" id="filter-role" :options="$roleOptions" :value="$filters['role']" :placeholder="__('ui.all')" />
        </x-ui.field>
        <x-ui.field name="status" id="filter-status" :label="__('ui.status')">
            <x-ui.select
                name="status"
                id="filter-status"
                :options="['active' => __('ui.users.active'), 'inactive' => __('ui.users.inactive')]"
                :value="$filters['status']"
                :placeholder="__('ui.all')"
            />
        </x-ui.field>
        <x-ui.button type="submit" variant="outline">
            <x-ui.icon name="magnifying-glass" size="sm" />
            {{ __('ui.filter') }}
        </x-ui.button>
    </form>

    @if ($users->isEmpty())
        <x-ui.empty-state icon="users" :title="__('ui.users.empty_title')" :description="__('ui.users.empty_description')" />
    @else
        <x-ui.table :headers="[__('ui.name'), __('ui.users.roles'), __('ui.users.staff_teams'), __('ui.status'), ['label' => __('ui.actions'), 'sr_only' => true]]">
            @foreach ($users as $user)
                <x-ui.table.row>
                    <x-ui.table.cell header>
                        <span class="block">{{ $user->name }}</span>
                        <span class="block text-caption font-normal text-muted break-all">{{ $user->email }}</span>
                    </x-ui.table.cell>
                    <x-ui.table.cell>
                        <span class="flex flex-wrap gap-1">
                            @foreach ($user->roles->sortBy(fn ($role) => $role->code->value) as $role)
                                <x-ui.badge>{{ $role->code->label() }}</x-ui.badge>
                            @endforeach
                        </span>
                    </x-ui.table.cell>
                    <x-ui.table.cell>
                        <span class="flex flex-wrap gap-1">
                            @foreach ($user->staffTeams->sortBy(fn ($team) => $team->code->value) as $team)
                                <x-ui.badge tone="info">{{ $team->code->label() }}</x-ui.badge>
                            @endforeach
                        </span>
                    </x-ui.table.cell>
                    <x-ui.table.cell>
                        <x-ui.badge :tone="$user->is_active ? 'success' : 'neutral'">
                            {{ $user->is_active ? __('ui.users.active') : __('ui.users.inactive') }}
                        </x-ui.badge>
                        @if ($user->must_change_password)
                            <x-ui.badge tone="warning">{{ __('ui.users.temporary') }}</x-ui.badge>
                        @endif
                    </x-ui.table.cell>
                    <x-ui.table.cell class="text-right">
                        <x-ui.link-button :href="route('admin.users.edit', $user)" variant="ghost" size="sm">{{ __('ui.edit') }}</x-ui.link-button>
                    </x-ui.table.cell>
                </x-ui.table.row>
            @endforeach
        </x-ui.table>
        <x-ui.pagination :paginator="$users" class="mt-4" />
    @endif
</x-layouts.admin>
