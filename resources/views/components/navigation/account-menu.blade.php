{{--
    Signed-in account menu. Links to other areas are passed by the caller
    (decided server-side); this component performs no permission checks.
--}}
@props([
    'variant' => 'light',
    'siteHref' => null,
    'adminHref' => null,
])

@php
    $user = auth()->user();
@endphp

@if ($user !== null)
    <x-ui.dropdown align="right" :trigger-variant="match ($variant) { 'dark' => 'secondary', 'glass' => 'glass', default => 'outline' }" {{ $attributes }}>
        <x-slot:trigger>
            <x-ui.icon name="user-circle" />
            <span class="hidden max-w-40 truncate sm:inline">{{ $user->name }}</span>
            <span class="sr-only sm:hidden">{{ __('ui.account.menu') }}</span>
            <x-ui.icon name="chevron-down" size="sm" />
        </x-slot:trigger>

        <div class="border-b border-border px-3 py-2">
            <p class="truncate text-body font-medium text-text">{{ $user->name }}</p>
            <p class="truncate text-caption text-muted">{{ $user->email }}</p>
        </div>
        <div class="py-1">
            @if ($siteHref !== null)
                <x-ui.dropdown.item :href="$siteHref" icon="home">{{ __('ui.account.site') }}</x-ui.dropdown.item>
            @endif
            @if ($adminHref !== null)
                <x-ui.dropdown.item :href="$adminHref" icon="cog-6-tooth">{{ __('ui.account.admin') }}</x-ui.dropdown.item>
            @endif
            <x-ui.dropdown.item :href="route('password.change')" icon="pencil-square">{{ __('ui.account.change_password') }}</x-ui.dropdown.item>
        </div>
        <form method="POST" action="{{ route('logout') }}" class="border-t border-border pt-1" x-on:submit="$store.ui.clearChatStorage()">
            @csrf
            <x-ui.dropdown.item type="submit" icon="arrow-left-start-on-rectangle">{{ __('ui.account.logout') }}</x-ui.dropdown.item>
        </form>
    </x-ui.dropdown>
@endif
