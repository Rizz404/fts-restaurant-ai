{{--
    Admin/editor/staff frame. Menu items come from the server (navItems);
    the layout name does not decide roles.
    Slots: default, actions, topbar, account (defaults to the account menu when signed in),
    navigation (overrides navItems). Shows session('status') as a success alert.
--}}
@props([
    'title' => null,
    'heading' => null,
    'navItems' => [],
    'breadcrumbs' => [],
])

<x-layouts.base :title="$title" :seo="['noindex' => true]">
    @isset($head)
        <x-slot:head>{{ $head }}</x-slot:head>
    @endisset

    <div x-data="adminShell" class="min-h-dvh lg:pl-64">
        {{-- Desktop sidebar --}}
        <aside class="fixed inset-y-0 left-0 z-30 hidden w-64 flex-col bg-secondary text-on-primary lg:flex">
            <div class="flex h-16 shrink-0 items-center px-5">
                <x-navigation.brand variant="dark" />
            </div>
            <div class="flex-1 overflow-y-auto px-3 py-4">
                @isset($navigation)
                    {{ $navigation }}
                @else
                    <x-navigation.admin-sidebar :items="$navItems" />
                @endisset
            </div>
        </aside>

        {{-- Mobile drawer (< lg) --}}
        <div
            x-show="drawerOpen"
            x-cloak
            x-on:keydown.escape.prevent.stop="closeDrawer()"
            class="fixed inset-0 z-40 lg:hidden"
        >
            <div x-show="drawerOpen" x-transition.opacity.duration.150ms class="fixed inset-0 bg-overlay" aria-hidden="true"></div>
            <div
                id="admin-drawer"
                x-show="drawerOpen"
                x-transition:enter="transition duration-200 ease-out motion-reduce:transition-none"
                x-transition:enter-start="-translate-x-full"
                x-transition:enter-end="translate-x-0"
                x-transition:leave="transition duration-150 ease-in motion-reduce:transition-none"
                x-transition:leave-start="translate-x-0"
                x-transition:leave-end="-translate-x-full"
                x-trap.inert.noscroll="drawerOpen"
                x-on:click.outside="closeDrawer()"
                role="dialog"
                aria-modal="true"
                aria-label="{{ __('ui.nav.admin') }}"
                class="fixed inset-y-0 left-0 flex w-72 max-w-[85vw] flex-col bg-secondary text-on-primary shadow-overlay"
            >
                <div class="flex h-16 shrink-0 items-center justify-between gap-3 px-5">
                    <x-navigation.brand variant="dark" />
                    <button
                        type="button"
                        x-on:click="closeDrawer()"
                        class="-m-1 rounded-control p-1 text-on-primary/80 hover:text-on-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-on-primary"
                    >
                        <x-ui.icon name="x-mark" :label="__('ui.nav.close_menu')" />
                    </button>
                </div>
                <div class="flex-1 overflow-y-auto px-3 py-4">
                    @isset($navigation)
                        {{ $navigation }}
                    @else
                        <x-navigation.admin-sidebar :items="$navItems" />
                    @endisset
                </div>
            </div>
        </div>

        <div class="flex min-h-dvh flex-col">
            <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-border bg-surface/80 px-4 backdrop-blur-md md:px-6">
                <button
                    type="button"
                    x-on:click="openDrawer()"
                    aria-controls="admin-drawer"
                    aria-expanded="false"
                    x-bind:aria-expanded="drawerOpen"
                    class="-ml-1 rounded-control p-2 text-text hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus lg:hidden"
                >
                    <x-ui.icon name="bars-3" :label="__('ui.nav.open_menu')" />
                </button>

                <div class="min-w-0 flex-1">{{ $topbar ?? '' }}</div>

                <x-navigation.language-switch />
                @can('requests.staff')
                    <x-navigation.notification-bell />
                @endcan
                @isset($account)
                    {{ $account }}
                @else
                    @auth
                        <x-navigation.account-menu :site-href="route('experience.welcome')" />
                    @endauth
                @endisset
            </header>

            <main id="main-content" tabindex="-1" class="page-enter flex-1 px-4 py-6 focus:outline-none md:px-6">
                <x-navigation.breadcrumbs :items="$breadcrumbs" class="mb-2" />

                @if (session('status'))
                    <x-ui.alert tone="success" dismissible class="mb-4">{{ session('status') }}</x-ui.alert>
                @endif

                @if ($heading !== null || isset($actions))
                    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <h1 class="font-display text-3xl leading-tight font-semibold tracking-tight text-text">{{ $heading ?? $title }}</h1>
                        @isset($actions)
                            <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
                        @endisset
                    </div>
                @endif

                {{ $slot }}
            </main>
        </div>
    </div>
</x-layouts.base>
