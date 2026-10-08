@php
    $sections = ['buttons', 'links', 'forms', 'validation', 'cards', 'alerts', 'table', 'empty', 'dialogs', 'dropdown', 'tabs', 'toasts', 'icons'];
    $variants = ['primary', 'secondary', 'outline', 'ghost', 'danger'];
    $tones = ['neutral', 'success', 'warning', 'danger', 'info'];
    $headers = [__('ui.name'), __('ui.category'), __('ui.status'), __('ui.updated'), ['label' => __('ui.actions'), 'sr_only' => true]];
@endphp

<x-layouts.base :title="__('ui.dev.gallery_title')">
    <header class="sticky top-0 z-20 border-b border-border bg-surface">
        <div class="mx-auto flex h-16 max-w-6xl items-center gap-3 px-4 md:px-6">
            <x-navigation.brand :href="route('experience.welcome')" />
            <nav aria-label="{{ __('ui.dev.previews') }}" class="ml-auto hidden md:block">
                <ul class="flex items-center gap-1">
                    <li><x-ui.link-button :href="route('dev.ui')" variant="ghost" size="sm" aria-current="page">{{ __('ui.landing.preview_gallery') }}</x-ui.link-button></li>
                    <li><x-ui.link-button :href="route('dev.admin')" variant="ghost" size="sm">{{ __('ui.landing.preview_admin') }}</x-ui.link-button></li>
                    <li><x-ui.link-button :href="route('dev.user')" variant="ghost" size="sm">{{ __('ui.landing.preview_user') }}</x-ui.link-button></li>
                    <li><x-ui.link-button :href="route('dev.guest')" variant="ghost" size="sm">{{ __('ui.landing.preview_guest') }}</x-ui.link-button></li>
                </ul>
            </nav>
            <x-navigation.language-switch class="ml-auto md:ml-0" />
        </div>
    </header>

    <main id="main-content" tabindex="-1" class="mx-auto max-w-6xl space-y-8 px-4 py-8 focus:outline-none md:px-6">
        <div class="space-y-3">
            <h1 class="text-page-title font-semibold text-text">{{ __('ui.dev.gallery_title') }}</h1>
            <p class="max-w-3xl text-body text-muted">{{ __('ui.dev.gallery_intro') }}</p>
            @include('dev._banner')
        </div>

        <nav aria-label="{{ __('ui.dev.gallery_title') }}">
            <ul class="flex flex-wrap gap-2">
                @foreach ($sections as $section)
                    <li><a href="#{{ $section }}" class="text-caption font-medium text-primary hover:underline">{{ __('ui.dev.sections.'.$section) }}</a></li>
                @endforeach
            </ul>
        </nav>

        {{-- Buttons --}}
        <x-ui.card id="buttons" :title="__('ui.dev.sections.buttons')">
            <div class="space-y-4">
                <div class="flex flex-wrap items-center gap-3">
                    @foreach ($variants as $variant)
                        <x-ui.button :variant="$variant">{{ __('ui.dev.variants.'.$variant) }}</x-ui.button>
                    @endforeach
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <x-ui.button size="sm">{{ __('ui.dev.variants.small') }}</x-ui.button>
                    <x-ui.button size="md">{{ __('ui.dev.variants.medium') }}</x-ui.button>
                    <x-ui.button size="lg">{{ __('ui.dev.variants.large') }}</x-ui.button>
                    <x-ui.button variant="outline" disabled>{{ __('ui.dev.variants.disabled') }}</x-ui.button>
                    <x-ui.button loading>{{ __('ui.dev.variants.loading') }}</x-ui.button>
                    <x-ui.button variant="primary" type="submit" form="preview-validation">
                        <x-ui.icon name="check" size="sm" />
                        {{ __('ui.save') }}
                    </x-ui.button>
                </div>
            </div>
        </x-ui.card>

        {{-- Link buttons --}}
        <x-ui.card id="links" :title="__('ui.dev.sections.links')">
            <div class="flex flex-wrap items-center gap-3">
                @foreach ($variants as $variant)
                    <x-ui.link-button :href="route('dev.ui').'#links'" :variant="$variant">{{ __('ui.dev.variants.'.$variant) }}</x-ui.link-button>
                @endforeach
                <x-ui.link-button variant="outline" disabled>{{ __('ui.dev.variants.disabled') }}</x-ui.link-button>
            </div>
        </x-ui.card>

        {{-- Form controls --}}
        <x-ui.card id="forms" :title="__('ui.dev.sections.forms')">
            <form class="grid gap-4 md:grid-cols-2" x-on:submit.prevent>
                <x-ui.field name="email" id="preview-email" :label="__('ui.email')">
                    <x-ui.input name="email" id="preview-email" type="email" autocomplete="email" />
                </x-ui.field>

                <x-ui.field name="full_name" id="preview-full-name" :label="__('ui.dev.fields.full_name')" :help="__('ui.dev.fields.full_name_help')" required>
                    <x-ui.input name="full_name" id="preview-full-name" autocomplete="name" maxlength="120" required />
                </x-ui.field>

                <x-ui.field name="team" id="preview-team" :label="__('ui.dev.fields.team')">
                    <x-ui.select
                        name="team"
                        id="preview-team"
                        :placeholder="__('ui.dev.fields.choose')"
                        :options="['starters' => __('ui.dev.fields.team_hr'), 'mains' => __('ui.dev.fields.team_it')]"
                    />
                </x-ui.field>

                <x-ui.field name="password" id="preview-password" :label="__('ui.password')">
                    <x-ui.input name="password" id="preview-password" type="password" autocomplete="current-password" />
                </x-ui.field>

                <x-ui.field name="notes" id="preview-notes" :label="__('ui.dev.fields.notes')" :help="__('ui.dev.fields.notes_help')" class="md:col-span-2">
                    <x-ui.textarea name="notes" id="preview-notes" rows="3" />
                </x-ui.field>

                <div class="flex flex-col gap-2 md:col-span-2">
                    <x-ui.checkbox name="remember" id="preview-remember">{{ __('ui.dev.fields.remember') }}</x-ui.checkbox>
                    <x-ui.checkbox name="remember_disabled" id="preview-remember-disabled" disabled checked>{{ __('ui.dev.variants.disabled') }}</x-ui.checkbox>
                </div>
            </form>
        </x-ui.card>

        {{-- Validation --}}
        <x-ui.card id="validation" :title="__('ui.dev.sections.validation')">
            <p class="mb-4 text-body text-muted">{{ __('ui.dev.validation_intro') }}</p>
            <form id="preview-validation" class="grid gap-4 md:grid-cols-2" x-on:submit.prevent="$dispatch('ui:toast', { message: @js(__('ui.dev.guest_submitted')), tone: 'info' })">
                <x-ui.field name="preview_error_email" id="preview-error-email" :label="__('ui.email')" required>
                    <x-ui.input name="preview_error_email" id="preview-error-email" type="email" value="not-an-email" autocomplete="off" />
                </x-ui.field>

                <x-ui.field name="preview_error_subject" id="preview-error-subject" :label="__('ui.dev.fields.subject')" :help="__('ui.dev.fields.notes_help')">
                    <x-ui.input name="preview_error_subject" id="preview-error-subject" maxlength="200" />
                </x-ui.field>
            </form>
        </x-ui.card>

        {{-- Cards and badges --}}
        <div id="cards" class="grid gap-4 md:grid-cols-2">
            <x-ui.card :title="__('ui.request_details')">
                <x-slot:header>
                    <x-ui.badge tone="info">{{ __('ui.preview') }}</x-ui.badge>
                </x-slot:header>
                <p>{{ __('ui.dev.card_body') }}</p>
                <p class="mt-2 text-muted">{{ __('ui.dev.synthetic_note') }}: {{ $unsafeSample }}</p>
                <x-slot:footer>
                    <x-ui.button variant="ghost" size="sm">{{ __('ui.cancel') }}</x-ui.button>
                    <x-ui.button size="sm">{{ __('ui.save') }}</x-ui.button>
                </x-slot:footer>
            </x-ui.card>

            <x-ui.card :title="__('ui.dev.sections.cards')" padding="sm">
                <div class="flex flex-wrap gap-2">
                    @foreach ($tones as $tone)
                        <x-ui.badge :tone="$tone">{{ __('ui.dev.tones.'.$tone) }}</x-ui.badge>
                    @endforeach
                </div>
            </x-ui.card>
        </div>

        {{-- Alerts --}}
        <section id="alerts" class="space-y-3" aria-labelledby="alerts-title">
            <h2 id="alerts-title" class="text-section-title font-semibold text-text">{{ __('ui.dev.sections.alerts') }}</h2>
            <x-ui.alert tone="info" :title="__('ui.dev.tones.info')">{{ __('ui.dev.alert_info') }}</x-ui.alert>
            <x-ui.alert tone="success" dismissible>{{ __('ui.dev.alert_success') }}</x-ui.alert>
            <x-ui.alert tone="warning">{{ __('ui.dev.alert_warning') }}</x-ui.alert>
            <x-ui.alert tone="danger" role="alert" :title="__('ui.dev.tones.danger')">{{ __('ui.dev.alert_danger') }}</x-ui.alert>
        </section>

        {{-- Table and pagination --}}
        <section id="table" class="space-y-3" aria-labelledby="table-title">
            <h2 id="table-title" class="text-section-title font-semibold text-text">{{ __('ui.dev.sections.table') }}</h2>
            <x-ui.table :headers="$headers" :caption="__('ui.dev.table_caption')">
                @foreach ($rows as $row)
                    <x-ui.table.row>
                        <x-ui.table.cell header>{{ $row['name'] }}</x-ui.table.cell>
                        <x-ui.table.cell>{{ $row['category'] }}</x-ui.table.cell>
                        <x-ui.table.cell><x-ui.badge :tone="$row['tone']">{{ __('ui.dev.tones.'.$row['tone']) }}</x-ui.badge></x-ui.table.cell>
                        <x-ui.table.cell class="whitespace-nowrap text-muted">{{ $row['updated'] }}</x-ui.table.cell>
                        <x-ui.table.cell class="text-right">
                            <x-ui.button variant="ghost" size="sm">{{ __('ui.dev.menu_view') }}</x-ui.button>
                        </x-ui.table.cell>
                    </x-ui.table.row>
                @endforeach
            </x-ui.table>
            <x-ui.pagination :paginator="$rows" />
        </section>

        {{-- Empty and loading --}}
        <section id="empty" class="grid gap-4 md:grid-cols-2" aria-label="{{ __('ui.dev.sections.empty') }}">
            <x-ui.empty-state :title="__('ui.dev.empty_title')" :description="__('ui.dev.empty_description')" icon="clipboard-document-list">
                <x-slot:action>
                    <x-ui.button size="sm">
                        <x-ui.icon name="plus" size="sm" />
                        {{ __('ui.dev.empty_action') }}
                    </x-ui.button>
                </x-slot:action>
            </x-ui.empty-state>
            <div class="space-y-4">
                <x-ui.spinner :label="__('ui.loading')" class="text-primary" />
                <x-ui.skeleton count="4" :label="__('ui.loading')" />
                <x-ui.skeleton shape="card" />
            </div>
        </section>

        {{-- Dialogs --}}
        <x-ui.card id="dialogs" :title="__('ui.dev.sections.dialogs')">
            <div
                class="flex flex-wrap gap-3"
                x-data
                x-on:ui:confirmed.window="if ($event.detail.name === 'preview-confirm') $dispatch('ui:toast', { message: @js(__('ui.dev.confirmed_toast')), tone: 'success' })"
            >
                <x-ui.button variant="outline" x-on:click="$dispatch('ui:modal-open', { name: 'preview-modal' })">{{ __('ui.dev.open_modal') }}</x-ui.button>
                <x-ui.button variant="danger" x-on:click="$dispatch('ui:modal-open', { name: 'preview-confirm' })">
                    <x-ui.icon name="trash" size="sm" />
                    {{ __('ui.dev.open_confirm') }}
                </x-ui.button>
            </div>

            <x-ui.modal name="preview-modal" :title="__('ui.dev.modal_title')">
                <div class="space-y-4">
                    <p>{{ __('ui.dev.modal_body') }}</p>
                    <x-ui.field name="modal_subject" id="preview-modal-subject" :label="__('ui.dev.fields.subject')">
                        <x-ui.input name="modal_subject" id="preview-modal-subject" />
                    </x-ui.field>
                </div>
                <x-slot:footer>
                    <x-ui.button variant="outline" x-on:click="close()">{{ __('ui.cancel') }}</x-ui.button>
                    <x-ui.button x-on:click="close()">{{ __('ui.save') }}</x-ui.button>
                </x-slot:footer>
            </x-ui.modal>

            <x-ui.confirm-dialog name="preview-confirm" :title="__('ui.dev.confirm_title')" :confirm-label="__('ui.dev.confirm_label')" variant="danger">
                {{ __('ui.dev.confirm_body') }}
            </x-ui.confirm-dialog>
        </x-ui.card>

        <div class="grid gap-4 md:grid-cols-2">
            {{-- Dropdown --}}
            <x-ui.card id="dropdown" :title="__('ui.dev.sections.dropdown')">
                <div class="flex flex-wrap gap-3">
                    <x-ui.dropdown>
                        <x-slot:trigger>
                            {{ __('ui.dev.menu') }}
                            <x-ui.icon name="chevron-down" size="sm" />
                        </x-slot:trigger>
                        <x-ui.dropdown.item :href="route('dev.ui').'#dropdown'" icon="eye">{{ __('ui.dev.menu_view') }}</x-ui.dropdown.item>
                        <x-ui.dropdown.item icon="pencil-square">{{ __('ui.dev.menu_edit') }}</x-ui.dropdown.item>
                        <x-ui.dropdown.item icon="inbox" disabled>{{ __('ui.dev.menu_archive') }}</x-ui.dropdown.item>
                    </x-ui.dropdown>

                    <x-ui.dropdown align="right">
                        <x-slot:trigger class="px-2" aria-label="{{ __('ui.more') }}">
                            <x-ui.icon name="ellipsis-vertical" />
                        </x-slot:trigger>
                        <x-ui.dropdown.item>{{ __('ui.dev.menu_view') }}</x-ui.dropdown.item>
                        <x-ui.dropdown.item>{{ __('ui.dev.menu_edit') }}</x-ui.dropdown.item>
                    </x-ui.dropdown>
                </div>
            </x-ui.card>

            {{-- Toasts --}}
            <x-ui.card id="toasts" :title="__('ui.dev.sections.toasts')">
                <div class="flex flex-wrap gap-3" x-data>
                    @foreach (['info', 'success', 'warning', 'danger'] as $tone)
                        <x-ui.button variant="outline" size="sm" x-on:click="$dispatch('ui:toast', { message: {{ \Illuminate\Support\Js::from(__('ui.dev.toast_message')) }}, tone: '{{ $tone }}' })">
                            {{ __('ui.dev.toast_button') }} · {{ __('ui.dev.tones.'.$tone) }}
                        </x-ui.button>
                    @endforeach
                </div>
            </x-ui.card>
        </div>

        {{-- Tabs --}}
        <x-ui.card id="tabs" :title="__('ui.dev.sections.tabs')">
            <x-ui.tabs id="preview-tabs" :items="[
                ['id' => 'overview', 'label' => __('ui.dev.tab_overview')],
                ['id' => 'history', 'label' => __('ui.dev.tab_history')],
                ['id' => 'settings', 'label' => __('ui.dev.tab_settings')],
            ]">
                <x-ui.tab-panel name="overview" tabs-id="preview-tabs">{{ __('ui.dev.tab_overview_body') }}</x-ui.tab-panel>
                <x-ui.tab-panel name="history" tabs-id="preview-tabs">{{ __('ui.dev.tab_history_body') }}</x-ui.tab-panel>
                <x-ui.tab-panel name="settings" tabs-id="preview-tabs">{{ __('ui.dev.tab_settings_body') }}</x-ui.tab-panel>
            </x-ui.tabs>
        </x-ui.card>

        {{-- Icons --}}
        <x-ui.card id="icons" :title="__('ui.dev.sections.icons')">
            <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                @foreach ($icons as $icon)
                    <li class="flex items-center gap-2 text-caption text-muted">
                        <x-ui.icon :name="$icon" class="text-text" />
                        <code>{{ $icon }}</code>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    </main>
</x-layouts.base>
