{{--
    Draft fields shared by create and edit. $draft is null on create.
    $slugLocked: slug can no longer change after first publication.
--}}
@php
    $tz = config('restaurant.timezone');
    $toInput = fn ($date) => $date?->timezone($tz)->format('Y-m-d\TH:i');
    $creating = $draft === null && ! isset($article);
@endphp

<div class="space-y-6">
    <div class="grid gap-4 md:grid-cols-2">
        @if ($creating || ! $slugLocked)
            <x-ui.field name="slug" id="info-slug" :label="__('info.fields.slug')" :help="__('info.slug_help')" required>
                <x-ui.input name="slug" id="info-slug" :value="$article->slug ?? null" maxlength="120" autocomplete="off" required />
            </x-ui.field>
        @else
            <div>
                <p class="text-body font-medium text-text">{{ __('info.fields.slug') }}</p>
                <p class="mt-1"><code class="text-caption">{{ $article->slug }}</code></p>
                <p class="mt-1 text-caption text-muted">{{ __('info.slug_locked') }}</p>
            </div>
        @endif

        <x-ui.field name="category" id="info-category" :label="__('info.fields.category')" required>
            <x-ui.select name="category" id="info-category" :options="$categoryOptions" :value="$draft?->category?->value ?? 'general'" required />
        </x-ui.field>

    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <x-ui.field name="effective_from" id="info-effective-from" :label="__('info.fields.effective_from')" :help="__('info.effective_from_help', ['tz' => $tz])">
            <x-ui.input name="effective_from" id="info-effective-from" type="datetime-local" :value="$toInput($draft?->effective_from)" />
        </x-ui.field>
        <x-ui.field name="effective_until" id="info-effective-until" :label="__('info.fields.effective_until')" :help="__('info.effective_until_help')">
            <x-ui.input name="effective_until" id="info-effective-until" type="datetime-local" :value="$toInput($draft?->effective_until)" />
        </x-ui.field>
    </div>

    <div class="space-y-2">
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-body font-medium text-text">{{ __('info.completeness') }}</span>
            @foreach (config('restaurant.locale_names') as $code => $name)
                <x-ui.badge :tone="($completeness[$code] ?? false) ? 'success' : 'warning'">
                    {{ $name }}: {{ ($completeness[$code] ?? false) ? __('info.complete') : __('info.incomplete') }}
                </x-ui.badge>
            @endforeach
        </div>

        <x-ui.tabs
            id="info-translations"
            :items="collect(config('restaurant.locale_names'))->map(fn ($name, $code) => ['id' => $code, 'label' => $name])->values()->all()"
            :selected="collect(config('restaurant.locales'))->first(fn ($code) => $errors->has('translations.'.$code.'.*') || $errors->has('translations.'.$code.'.title') || $errors->has('translations.'.$code.'.body'))"
        >
            @foreach (config('restaurant.locale_names') as $code => $name)
                @php $translation = $draft?->translations->firstWhere('locale', $code); @endphp
                <x-ui.tab-panel :name="$code" tabs-id="info-translations">
                    <div class="grid gap-4" lang="{{ $code }}">
                        <x-ui.field name="translations[{{ $code }}][title]" id="info-{{ $code }}-title" :label="__('info.fields.title').' ('.$name.')'">
                            <x-ui.input name="translations[{{ $code }}][title]" id="info-{{ $code }}-title" :value="$translation?->title" maxlength="180" />
                        </x-ui.field>
                        <x-ui.field name="translations[{{ $code }}][body]" id="info-{{ $code }}-body" :label="__('info.fields.body').' ('.$name.')'" :help="__('info.body_help')">
                            <x-ui.textarea name="translations[{{ $code }}][body]" id="info-{{ $code }}-body" :value="$translation?->body" rows="12" maxlength="20000" />
                        </x-ui.field>
                        <div class="grid gap-4 md:grid-cols-2">
                            <x-ui.field name="translations[{{ $code }}][tags]" id="info-{{ $code }}-tags" :label="__('info.fields.tags')" :help="__('info.list_help')">
                                <x-ui.input name="translations[{{ $code }}][tags]" id="info-{{ $code }}-tags" :value="implode(', ', $translation?->tags ?? [])" />
                            </x-ui.field>
                            <x-ui.field name="translations[{{ $code }}][aliases]" id="info-{{ $code }}-aliases" :label="__('info.fields.aliases')" :help="__('info.aliases_help')">
                                <x-ui.input name="translations[{{ $code }}][aliases]" id="info-{{ $code }}-aliases" :value="implode(', ', $translation?->aliases ?? [])" />
                            </x-ui.field>
                        </div>
                    </div>
                </x-ui.tab-panel>
            @endforeach
        </x-ui.tabs>
    </div>
</div>
