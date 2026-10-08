{{--
    Guest preference chips on the Welcome slide: dietary tags, allergens to avoid, and party size. Stored only in this
    browser (localStorage, whitelisted codes, no personal data) and sent as `preferences` in the AI turn context.
    Allergen filters here are a convenience: the menu only trusts verified allergen data (see Plan 4).
--}}
@php
    $diet = config('restaurant.dietary_tags');
    $allergens = config('restaurant.allergens');
    $maxParty = (int) app(\App\Modules\Experience\Support\RestaurantSettings::class)->get()->maxPartySize();
@endphp

<div
    x-data="guestPreferences(@js(['diet' => $diet, 'allergens' => $allergens, 'maxParty' => $maxParty]))"
    {{ $attributes->class('prefs') }}
    role="group"
    aria-label="{{ __('deck.prefs.title') }}"
>
    <p class="prefs-title">{{ __('deck.prefs.title') }}</p>

    <ul class="chips" aria-label="{{ __('deck.prefs.diet') }}">
        @foreach ($diet as $tag)
            <li>
                <button
                    type="button"
                    class="chip focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                    aria-pressed="false"
                    x-bind:aria-pressed="has('dietary_tags', '{{ $tag }}')"
                    x-on:click="toggle('dietary_tags', '{{ $tag }}')"
                >{{ __('menu.diet.'.$tag) }}</button>
            </li>
        @endforeach
    </ul>

    <div class="prefs-row">
        <details class="prefs-allergens">
            <summary class="chip focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus">
                {{ __('deck.prefs.allergens') }}
                <span class="chip-count" x-show="selected.exclude_allergens.length > 0" x-cloak x-text="selected.exclude_allergens.length"></span>
            </summary>
            <ul class="chips mt-2" aria-label="{{ __('deck.prefs.allergens') }}">
                @foreach ($allergens as $code)
                    <li>
                        <button
                            type="button"
                            class="chip focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                            aria-pressed="false"
                            x-bind:aria-pressed="has('exclude_allergens', '{{ $code }}')"
                            x-on:click="toggle('exclude_allergens', '{{ $code }}')"
                        >{{ __('menu.allergen.'.$code) }}</button>
                    </li>
                @endforeach
            </ul>
        </details>

        <div class="stepper" role="group" aria-label="{{ __('deck.prefs.party') }}">
            <span class="stepper-label" aria-hidden="true">{{ __('deck.prefs.party') }}</span>
            <button type="button" class="icon-btn focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus" x-on:click="changeParty(-1)" x-bind:disabled="! selected.party_size">
                <x-ui.icon name="minus" size="sm" />
                <span class="sr-only">{{ __('deck.prefs.party_less') }}</span>
            </button>
            <output class="stepper-value" aria-live="polite" x-text="selected.party_size ?? '–'">–</output>
            <button type="button" class="icon-btn focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus" x-on:click="changeParty(1)" x-bind:disabled="selected.party_size >= maxParty">
                <x-ui.icon name="plus" size="sm" />
                <span class="sr-only">{{ __('deck.prefs.party_more') }}</span>
            </button>
        </div>

        <button type="button" class="bubble-action focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus" x-show="hasAny" x-cloak x-on:click="reset()">{{ __('deck.prefs.clear') }}</button>
    </div>
</div>
