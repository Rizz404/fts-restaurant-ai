{{-- Find a request by reference + the phone or email used when booking. One uniform error for every failure. --}}
@php
    $lookupLabels = [
        'lookupFailed' => __('ui.errors.lookup_failed'),
        'rateLimited' => __('ui.errors.rate_limited'),
        'network' => __('ui.errors.network'),
    ];
@endphp

<x-ui.card>
    <form x-data="requestLookup(@js(['labels' => $lookupLabels]))" x-init="reference = @js($reference ?? '')" x-on:submit.prevent="submit()" class="space-y-3">
        <h2 class="text-section-title font-semibold text-text">{{ __('requests.lookup_title') }}</h2>
        <p class="text-body text-muted">{{ __('requests.lookup_help') }}</p>

        <div class="sr-only" aria-hidden="true">
            <label>{{ __('ui.honeypot_label') }}<input type="text" tabindex="-1" autocomplete="off" x-model="honeypot" data-honeypot></label>
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
            <x-ui.field name="lookup_reference" id="lookup-reference" :label="__('requests.reference')" required>
                <x-ui.input name="lookup_reference" id="lookup-reference" x-model="reference" maxlength="40" autocomplete="off" placeholder="RST-20261007-ABCD2345" />
            </x-ui.field>
            <x-ui.field name="lookup_contact" id="lookup-contact" :label="__('requests.lookup_contact')" required>
                <x-ui.input name="lookup_contact" id="lookup-contact" x-model="contact" maxlength="255" autocomplete="off" />
            </x-ui.field>
        </div>

        <p class="text-caption text-danger" role="alert" x-show="error" x-cloak x-text="error"></p>

        <x-ui.button type="submit" x-bind:disabled="busy || reference.trim() === '' || contact.trim() === ''">
            <x-ui.icon name="magnifying-glass" size="sm" />
            {{ __('requests.lookup_submit') }}
        </x-ui.button>
    </form>
</x-ui.card>
