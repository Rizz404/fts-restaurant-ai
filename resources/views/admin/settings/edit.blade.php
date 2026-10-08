<x-layouts.admin
    :title="__('ui.nav.settings')"
    :heading="__('deck.settings.title')"
    :nav-items="$navItems"
    :breadcrumbs="[['label' => __('ui.nav.overview'), 'href' => route('admin.dashboard')], ['label' => __('ui.nav.settings')]]"
>
    <form method="POST" action="{{ route('admin.settings.update') }}" novalidate class="max-w-3xl space-y-6">
        @csrf
        @method('PATCH')

        <x-ui.card :title="__('deck.settings.branding')">
            <p class="mb-4 text-body text-muted">{{ __('deck.settings.intro') }}</p>

            <div class="grid gap-4">
                <x-ui.field name="brand_name" id="settings-brand-name" :label="__('deck.settings.brand_name')" required>
                    <x-ui.input name="brand_name" id="settings-brand-name" :value="$settings->brand_name" maxlength="160" required />
                </x-ui.field>

                <x-ui.tabs
                    id="welcome-tabs"
                    :items="collect(config('restaurant.locale_names'))->map(fn ($name, $code) => ['id' => $code, 'label' => $name])->values()->all()"
                    :selected="collect(config('restaurant.locales'))->first(fn ($code) => $errors->has('welcome_text.'.$code))"
                >
                    @foreach (config('restaurant.locale_names') as $code => $name)
                        <x-ui.tab-panel :name="$code" tabs-id="welcome-tabs">
                            <x-ui.field
                                name="welcome_text[{{ $code }}]"
                                id="settings-welcome-{{ $code }}"
                                :label="__('deck.settings.welcome_text').' ('.$name.')'"
                                :help="__('deck.settings.welcome_help')"
                            >
                                <x-ui.textarea
                                    name="welcome_text[{{ $code }}]"
                                    id="settings-welcome-{{ $code }}"
                                    :value="$settings->welcome_text[$code] ?? ''"
                                    rows="4"
                                    maxlength="1000"
                                    lang="{{ $code }}"
                                />
                            </x-ui.field>
                        </x-ui.tab-panel>
                    @endforeach
                </x-ui.tabs>
            </div>
        </x-ui.card>

        <x-ui.card :title="__('deck.settings.contact')">
            <p class="mb-4 text-body text-muted">{{ __('deck.settings.contact_help') }}</p>

            <div class="grid gap-4 md:grid-cols-2">
                @foreach (['phone' => 'tel', 'whatsapp' => 'text', 'instagram' => 'text', 'maps_url' => 'url'] as $key => $type)
                    <x-ui.field name="contact_links[{{ $key }}]" id="settings-contact-{{ $key }}" :label="__('deck.settings.'.$key)">
                        <x-ui.input
                            name="contact_links[{{ $key }}]"
                            id="settings-contact-{{ $key }}"
                            :type="$type"
                            :value="$settings->contact_links[$key] ?? ''"
                            maxlength="300"
                            autocomplete="off"
                        />
                    </x-ui.field>
                @endforeach
            </div>
        </x-ui.card>

        <x-ui.card :title="__('deck.settings.reservation')">
            <p class="mb-4 text-body text-muted">{{ __('deck.settings.reservation_help') }}</p>

            <div class="grid gap-4 md:grid-cols-2">
                @foreach (['slot_capacity', 'max_party_size', 'min_lead_minutes', 'max_advance_days'] as $key)
                    <x-ui.field name="{{ $key }}" id="settings-{{ $key }}" :label="__('deck.settings.'.$key)">
                        <x-ui.input name="{{ $key }}" id="settings-{{ $key }}" type="number" min="0" :value="$settings->{$key}" :placeholder="(string) config('restaurant.reservation.'.$key)" />
                    </x-ui.field>
                @endforeach
            </div>

            <fieldset class="mt-6">
                <legend class="text-body font-medium text-text">{{ __('deck.settings.opening_hours') }}</legend>
                <p class="mb-3 text-caption text-muted">{{ __('deck.settings.opening_hours_help', ['tz' => config('restaurant.timezone')]) }}</p>

                <div class="grid gap-3">
                    @foreach (\App\Models\RestaurantSetting::WEEKDAYS as $day)
                        @php $range = $hours[$day][0] ?? ['open' => '', 'close' => '']; @endphp
                        <div class="grid grid-cols-[6rem_1fr_1fr] items-start gap-3">
                            <span class="pt-2 text-body text-text">{{ __('deck.settings.days.'.$day) }}</span>
                            <x-ui.field name="opening_hours[{{ $day }}][open]" id="settings-hours-{{ $day }}-open" :label="__('deck.settings.open')">
                                <x-ui.input name="opening_hours[{{ $day }}][open]" id="settings-hours-{{ $day }}-open" type="time" :value="$range['open']" />
                            </x-ui.field>
                            <x-ui.field name="opening_hours[{{ $day }}][close]" id="settings-hours-{{ $day }}-close" :label="__('deck.settings.close')">
                                <x-ui.input name="opening_hours[{{ $day }}][close]" id="settings-hours-{{ $day }}-close" type="time" :value="$range['close']" />
                            </x-ui.field>
                        </div>
                    @endforeach
                </div>
            </fieldset>

            <x-ui.field name="closed_dates" id="settings-closed-dates" class="mt-6" :label="__('deck.settings.closed_dates')" :help="__('deck.settings.closed_dates_help')">
                <x-ui.textarea name="closed_dates" id="settings-closed-dates" :value="$closedDatesText" rows="4" maxlength="5000" />
            </x-ui.field>

            <x-slot:footer>
                <x-ui.button type="submit">{{ __('ui.save') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.card>
    </form>
</x-layouts.admin>
