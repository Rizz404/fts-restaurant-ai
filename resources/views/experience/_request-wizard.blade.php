{{--
    Request wizard shared by reservation, order, and the feedback / special request forms.
    $wizard comes from the page controller (type, initialDraft, dates, dishes, ...). Drafts are saved on the server,
    confirmed only by the explicit button. The wizard is Alpine (requestWizard); server-side rules stay the single
    source of truth, so every field error shown here comes back from the API.
--}}
@php
    use App\Modules\Requests\Support\WizardLabels;

    $config = $wizard + ['labels' => WizardLabels::wizard()];
    $type = $wizard['type'];
    $types = $wizard['types'] ?? [$type];
    $isReservation = $type === 'reservation';
    $isOrder = $type === 'order';
    $isForm = ! $isReservation && ! $isOrder;
    $fieldError = fn (string $name) => 'fieldError(\''.$name.'\')';
@endphp

<div x-data="requestWizard(@js($config))" class="space-y-4">
    {{-- Done --}}
    <template x-if="done">
        <x-ui.card>
            <div class="space-y-3" role="status">
                <p class="flex items-center gap-2 text-section-title font-semibold text-text">
                    <x-ui.icon name="check-circle" class="text-success" />
                    {{ __('requests.sent_title') }}
                </p>
                <p class="text-body text-muted">{{ __('requests.sent_text') }}</p>
                <dl class="grid gap-1 text-body">
                    <div class="flex flex-wrap gap-2"><dt class="font-medium text-text">{{ __('requests.reference') }}</dt><dd class="font-mono text-text" x-text="done.request.reference"></dd></div>
                    <div class="flex flex-wrap gap-2"><dt class="font-medium text-text">{{ __('requests.status_label') }}</dt><dd class="text-text" x-text="done.request.status_label"></dd></div>
                    <div class="flex flex-wrap gap-2"><dt class="font-medium text-text">{{ __('requests.details') }}</dt><dd class="text-text" x-text="done.request.summary"></dd></div>
                </dl>
                <p class="text-caption text-muted">{{ __('requests.keep_reference') }}</p>
                <div class="flex flex-wrap gap-2">
                    <x-ui.link-button href="#" x-bind:href="done.request.path">{{ __('requests.open') }}</x-ui.link-button>
                    <x-ui.link-button :href="route('experience.bookings')" variant="outline">{{ __('requests.my_requests') }}</x-ui.link-button>
                </div>
            </div>
        </x-ui.card>
    </template>

    <div x-show="! done" class="space-y-4">
        @if (count($types) > 1)
            <div class="flex flex-wrap gap-2" role="group" aria-label="{{ __('requests.type_label') }}">
                @foreach ($types as $option)
                    <button
                        type="button"
                        class="chip focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                        x-bind:aria-pressed="type === '{{ $option }}'"
                        x-bind:disabled="draft !== null"
                        x-on:click="type = '{{ $option }}'"
                    >{{ __('requests.types.'.$option) }}</button>
                @endforeach
            </div>
        @endif

        {{-- Step indicator --}}
        <ol class="wizard-steps" aria-label="{{ __('requests.steps') }}">
            <template x-for="(name, i) in steps" x-bind:key="name">
                <li x-bind:aria-current="i === step ? 'step' : null" x-bind:class="{ 'is-done': i < step }">
                    <span class="wizard-steps-num" x-text="i + 1"></span>
                    <span class="wizard-steps-label" x-text="@js(__('requests.step_names')) [name]"></span>
                </li>
            </template>
        </ol>

        <div x-show="error" x-cloak role="alert">
            <x-ui.alert tone="danger"><span x-text="error"></span></x-ui.alert>
        </div>

        <div x-show="draft && draft.expired" x-cloak>
            <x-ui.alert tone="warning">{{ __('requests.errors.draft_expired') }}</x-ui.alert>
        </div>

        {{-- Hidden anti-bot field --}}
        <div class="sr-only" aria-hidden="true">
            <label>{{ __('ui.honeypot_label') }}<input type="text" tabindex="-1" autocomplete="off" x-model="honeypot" data-honeypot></label>
        </div>

        <x-ui.card>
            @if ($isReservation)
                {{-- Step: date and party --}}
                <div x-show="current === 'when'" class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field name="date" id="rw-date" :label="__('requests.fields.date')" required>
                        <x-ui.input name="date" id="rw-date" type="date" x-model="date" :min="$wizard['minDate']" :max="$wizard['maxDate']" x-on:change="slotsLoaded = false" />
                        <p class="flex items-start gap-1 text-caption text-danger" x-show="fieldError('date')" x-cloak x-text="fieldError('date')"></p>
                    </x-ui.field>
                    <x-ui.field name="party_size" id="rw-party" :label="__('requests.fields.party_size')" required :help="__('requests.fields.party_size_help', ['max' => $wizard['maxParty']])">
                        <x-ui.input name="party_size" id="rw-party" type="number" x-model.number="partySize" min="1" :max="$wizard['maxParty']" inputmode="numeric" x-on:change="slotsLoaded = false" />
                        <p class="flex items-start gap-1 text-caption text-danger" x-show="fieldError('party_size')" x-cloak x-text="fieldError('party_size')"></p>
                    </x-ui.field>
                </div>

                {{-- Step: slot --}}
                <div x-show="current === 'slot'" x-cloak class="space-y-3">
                    <p class="text-body font-medium text-text">{{ __('requests.fields.slot') }}</p>
                    <p class="text-caption text-muted" x-show="slotsLoading">{{ __('ui.loading') }}</p>
                    <p class="text-caption text-danger" x-show="slotsError" x-cloak x-text="slotsError"></p>
                    <x-ui.alert tone="info" x-show="slotsLoaded && slotsClosed" x-cloak>{{ __('requests.errors.date_closed') }}</x-ui.alert>
                    <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="{{ __('requests.fields.slot') }}">
                        <template x-for="item in slots" x-bind:key="item.slot">
                            <button
                                type="button"
                                role="radio"
                                class="chip focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                                x-bind:aria-checked="slot === item.slot"
                                x-bind:aria-pressed="slot === item.slot"
                                x-bind:disabled="! item.canBook"
                                x-on:click="slot = item.slot"
                            >
                                <span x-text="item.slot"></span>
                                <span class="chip-count" x-show="item.canBook" x-text="item.seats"></span>
                                <span class="chip-count" x-show="! item.canBook && item.seats < partySize">{{ __('requests.slot_full') }}</span>
                                <span class="chip-count" x-show="! item.canBook && item.seats >= partySize">{{ __('requests.slot_unavailable') }}</span>
                            </button>
                        </template>
                    </div>
                    <p class="text-caption text-danger" x-show="fieldError('slot')" x-cloak x-text="fieldError('slot')"></p>
                </div>

                {{-- Step: details --}}
                <div x-show="current === 'details'" x-cloak class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field name="name" id="rw-name" :label="__('requests.fields.name')" required>
                        <x-ui.input name="name" id="rw-name" x-model="contact.name" maxlength="80" autocomplete="name" />
                        <p class="text-caption text-danger" x-show="fieldError('name')" x-cloak x-text="fieldError('name')"></p>
                    </x-ui.field>
                    <x-ui.field name="phone" id="rw-phone" :label="__('requests.fields.phone')" required>
                        <x-ui.input name="phone" id="rw-phone" type="tel" x-model="contact.phone" maxlength="32" autocomplete="tel" inputmode="tel" />
                        <p class="text-caption text-danger" x-show="fieldError('phone')" x-cloak x-text="fieldError('phone')"></p>
                    </x-ui.field>
                    <x-ui.field name="email" id="rw-email" :label="__('requests.fields.email_optional')" class="sm:col-span-2">
                        <x-ui.input name="email" id="rw-email" type="email" x-model="contact.email" maxlength="255" autocomplete="email" />
                        <p class="text-caption text-danger" x-show="fieldError('email')" x-cloak x-text="fieldError('email')"></p>
                    </x-ui.field>
                    <x-ui.field name="seating_preference" id="rw-seating" :label="__('requests.fields.seating')">
                        <x-ui.select name="seating_preference" id="rw-seating" x-model="seating" :options="['any' => __('requests.seating.any'), 'indoor' => __('requests.seating.indoor'), 'outdoor' => __('requests.seating.outdoor')]" />
                    </x-ui.field>
                    <x-ui.field name="notes" id="rw-notes" :label="__('requests.fields.notes')" :help="__('requests.fields.notes_help')" class="sm:col-span-2">
                        <x-ui.textarea name="notes" id="rw-notes" rows="3" x-model="notes" maxlength="500" />
                        <p class="text-caption text-danger" x-show="fieldError('notes')" x-cloak x-text="fieldError('notes')"></p>
                    </x-ui.field>
                </div>
            @endif

            @if ($isOrder)
                {{-- Step: cart --}}
                <div x-show="current === 'cart'" class="space-y-3">
                    <p class="text-body font-medium text-text">{{ __('requests.cart') }}</p>
                    <p class="text-body text-muted" x-show="cart().lines.length === 0">
                        {{ __('requests.cart_empty') }}
                        <a href="{{ route('experience.menu') }}" class="font-medium text-primary underline">{{ __('requests.browse_menu') }}</a>
                    </p>
                    <ul class="divide-y divide-border">
                        <template x-for="line in cart().lines" x-bind:key="line.id">
                            <li class="flex flex-wrap items-center justify-between gap-3 py-2">
                                <div class="min-w-0">
                                    <p class="text-body font-medium text-text" x-text="lineName(line)"></p>
                                    <p class="text-caption text-danger" x-show="! dishAvailable(line)">{{ __('requests.errors.item_unavailable') }}</p>
                                    <p class="text-caption text-muted" x-text="linePrice(line)"></p>
                                </div>
                                <div class="stepper">
                                    <button type="button" class="icon-btn focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus" x-on:click="cart().set(line.id, line.quantity - 1)"><x-ui.icon name="minus" size="sm" /><span class="sr-only">{{ __('requests.less') }}</span></button>
                                    <span class="min-w-6 text-center text-body font-medium text-text" x-text="line.quantity"></span>
                                    <button type="button" class="icon-btn focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus" x-on:click="cart().set(line.id, line.quantity + 1)"><x-ui.icon name="plus" size="sm" /><span class="sr-only">{{ __('requests.more') }}</span></button>
                                    <button type="button" class="icon-btn focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus" x-on:click="cart().remove(line.id)"><x-ui.icon name="trash" size="sm" /><span class="sr-only">{{ __('requests.remove') }}</span></button>
                                </div>
                            </li>
                        </template>
                    </ul>
                    <p class="flex justify-between border-t border-border pt-2 text-body font-semibold text-text" x-show="cart().lines.length > 0">
                        <span>{{ __('requests.total') }} <span class="text-caption font-normal text-muted">({{ __('requests.total_preview') }})</span></span>
                        <span x-text="orderTotal()"></span>
                    </p>
                    <p class="text-caption text-danger" x-show="fieldError('items')" x-cloak x-text="fieldError('items')"></p>
                </div>

                {{-- Step: details --}}
                <div x-show="current === 'details'" x-cloak class="grid gap-4 sm:grid-cols-2">
                    <x-ui.alert tone="info" class="sm:col-span-2" x-show="{{ $wizard['closedToday'] ? 'true' : 'false' }}">{{ __('requests.errors.time_outside_hours') }}</x-ui.alert>
                    <x-ui.field name="fulfilment" id="rw-fulfilment" :label="__('requests.fields.fulfilment')" required>
                        <x-ui.select name="fulfilment" id="rw-fulfilment" x-model="fulfilment" :options="['pickup' => __('requests.fulfilment.pickup'), 'dine_in_preorder' => __('requests.fulfilment.dine_in_preorder')]" />
                    </x-ui.field>
                    <x-ui.field name="requested_time" id="rw-time" :label="__('requests.fields.requested_time')" required :help="__('requests.fields.requested_time_help', ['minutes' => $wizard['leadMinutes']])">
                        <x-ui.input name="requested_time" id="rw-time" type="time" x-model="requestedTime" />
                        <p class="text-caption text-danger" x-show="fieldError('requested_time')" x-cloak x-text="fieldError('requested_time')"></p>
                    </x-ui.field>
                    <x-ui.field name="name" id="rw-name" :label="__('requests.fields.name')" required>
                        <x-ui.input name="name" id="rw-name" x-model="contact.name" maxlength="80" autocomplete="name" />
                        <p class="text-caption text-danger" x-show="fieldError('name')" x-cloak x-text="fieldError('name')"></p>
                    </x-ui.field>
                    <x-ui.field name="phone" id="rw-phone" :label="__('requests.fields.phone')" required>
                        <x-ui.input name="phone" id="rw-phone" type="tel" x-model="contact.phone" maxlength="32" autocomplete="tel" inputmode="tel" />
                        <p class="text-caption text-danger" x-show="fieldError('phone')" x-cloak x-text="fieldError('phone')"></p>
                    </x-ui.field>
                    <x-ui.field name="email" id="rw-email" :label="__('requests.fields.email_optional')" class="sm:col-span-2">
                        <x-ui.input name="email" id="rw-email" type="email" x-model="contact.email" maxlength="255" autocomplete="email" />
                        <p class="text-caption text-danger" x-show="fieldError('email')" x-cloak x-text="fieldError('email')"></p>
                    </x-ui.field>
                    <x-ui.field name="notes" id="rw-notes" :label="__('requests.fields.notes')" :help="__('requests.fields.order_notes_help')" class="sm:col-span-2">
                        <x-ui.textarea name="notes" id="rw-notes" rows="3" x-model="notes" maxlength="500" />
                        <p class="text-caption text-danger" x-show="fieldError('notes')" x-cloak x-text="fieldError('notes')"></p>
                    </x-ui.field>
                </div>
            @endif

            @if ($isForm)
                {{-- Step: form (feedback or special request) --}}
                <div x-show="current === 'form'" class="grid gap-4">
                    <x-ui.field name="kind" id="rw-kind" :label="__('requests.fields.kind')" x-show="type === 'feedback'">
                        <x-ui.select name="kind" id="rw-kind" x-model="kind" :options="['suggestion' => __('requests.feedback_kinds.suggestion'), 'compliment' => __('requests.feedback_kinds.compliment'), 'complaint' => __('requests.feedback_kinds.complaint')]" />
                    </x-ui.field>
                    <x-ui.field name="subject" id="rw-subject" :label="__('requests.fields.subject')" required>
                        <x-ui.input name="subject" id="rw-subject" x-model="subject" maxlength="160" />
                        <p class="text-caption text-danger" x-show="fieldError('subject')" x-cloak x-text="fieldError('subject')"></p>
                    </x-ui.field>
                    <x-ui.field name="body" id="rw-body" :label="__('requests.fields.body')" required>
                        <x-ui.textarea name="body" id="rw-body" rows="5" x-model="body" maxlength="5000" />
                        <p class="text-caption text-danger" x-show="fieldError('body')" x-cloak x-text="fieldError('body')"></p>
                    </x-ui.field>
                    <x-ui.field name="related_reference" id="rw-related" :label="__('requests.fields.related_reference')" :help="__('requests.fields.related_reference_help')" x-show="type === 'feedback'">
                        <x-ui.input name="related_reference" id="rw-related" x-model="relatedReference" maxlength="40" autocomplete="off" />
                        <p class="text-caption text-danger" x-show="fieldError('related_reference')" x-cloak x-text="fieldError('related_reference')"></p>
                    </x-ui.field>

                    <div class="grid gap-4 border-t border-border pt-4 sm:grid-cols-2">
                        <p class="text-caption text-muted sm:col-span-2">{{ __('requests.fields.contact_optional_help') }}</p>
                        <x-ui.field name="name" id="rw-name" :label="__('requests.fields.name_optional')">
                            <x-ui.input name="name" id="rw-name" x-model="contact.name" maxlength="80" autocomplete="name" />
                            <p class="text-caption text-danger" x-show="fieldError('name')" x-cloak x-text="fieldError('name')"></p>
                        </x-ui.field>
                        <x-ui.field name="phone" id="rw-phone" :label="__('requests.fields.phone_optional')">
                            <x-ui.input name="phone" id="rw-phone" type="tel" x-model="contact.phone" maxlength="32" autocomplete="tel" inputmode="tel" />
                            <p class="text-caption text-danger" x-show="fieldError('phone')" x-cloak x-text="fieldError('phone')"></p>
                        </x-ui.field>
                    </div>
                </div>
            @endif

            {{-- Step: review (the server's own copy of the draft) --}}
            <div x-show="current === 'review'" x-cloak class="space-y-3">
                <p class="text-body font-medium text-text">{{ __('requests.review_title') }}</p>
                <template x-if="draft">
                    <dl class="grid gap-2 text-body">
                        <div class="flex flex-wrap gap-2"><dt class="font-medium text-text">{{ __('requests.type_label') }}</dt><dd class="text-text" x-text="draft.type_label"></dd></div>
                        <div class="flex flex-wrap gap-2"><dt class="font-medium text-text">{{ __('requests.details') }}</dt><dd class="text-text" x-text="draft.summary"></dd></div>
                        <div class="flex flex-wrap gap-2" x-show="draft.total_formatted"><dt class="font-medium text-text">{{ __('requests.total') }}</dt><dd class="text-text" x-text="draft.total_formatted"></dd></div>
                        <div class="flex flex-wrap gap-2" x-show="draft.contact.name || draft.contact.phone"><dt class="font-medium text-text">{{ __('requests.contact') }}</dt><dd class="text-text" x-text="[draft.contact.name, draft.contact.phone, draft.contact.email].filter(Boolean).join(' · ')"></dd></div>
                        <div class="flex flex-wrap gap-2"><dt class="font-medium text-text">{{ __('requests.handled_by') }}</dt><dd class="text-text" x-text="draft.team_label"></dd></div>
                    </dl>
                </template>
                <p class="text-body text-text">{{ __('requests.confirm_question') }}</p>
                <p class="text-caption text-muted">{{ __('requests.draft_expiry_note') }}</p>
            </div>
        </x-ui.card>

        {{-- Navigation --}}
        <div class="flex flex-wrap items-center justify-between gap-2">
            <x-ui.button variant="outline" x-on:click="back()" x-bind:disabled="step === 0 || busy">{{ __('requests.back') }}</x-ui.button>

            <div class="flex gap-2">
                <x-ui.button x-show="! isLast" x-on:click="next()" x-bind:disabled="busy">{{ __('requests.next') }}</x-ui.button>
                <x-ui.button x-show="isLast" x-cloak x-on:click="confirm()" x-bind:disabled="busy || ! draft || draft.expired">{{ __('requests.confirm_yes') }}</x-ui.button>
            </div>
        </div>
    </div>
</div>
