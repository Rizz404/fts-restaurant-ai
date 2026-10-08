{{--
    Message list + compose box of one request. Must sit inside an element with x-data="requestThread(...)".
    Guests only ever see public messages; staff can send an internal note (never shown to the guest).
    Message text is rendered with x-text, never as HTML.
--}}
@props([
    'role' => 'guest',
])

<section aria-labelledby="thread-title" class="space-y-3">
    <h2 id="thread-title" class="text-section-title font-semibold text-text">{{ __('requests.messages') }}</h2>

    <p class="text-body text-muted" x-show="! loading && messages.length === 0" x-cloak>{{ __('requests.no_replies') }}</p>
    <p class="text-caption text-muted" x-show="loading">{{ __('ui.loading') }}</p>

    <ul x-ref="list" class="max-h-96 space-y-3 overflow-y-auto" aria-live="polite" aria-relevant="additions">
        <template x-for="message in messages" x-bind:key="message.id">
            <li class="flex" x-bind:class="message.mine ? 'justify-end' : 'justify-start'">
                <div
                    class="max-w-[85%] rounded-card border px-3 py-2"
                    x-bind:class="message.visibility === 'internal' ? 'border-warning bg-warning-soft' : (message.mine ? 'border-primary/30 bg-primary-soft' : 'border-border bg-surface-muted')"
                >
                    <p class="flex flex-wrap items-center gap-2 text-caption text-muted">
                        <span class="font-medium text-text" x-text="message.author_name"></span>
                        <span x-show="message.visibility === 'internal'" x-cloak class="font-medium text-warning">{{ __('requests.internal_note') }}</span>
                        <time x-bind:datetime="message.created_at" x-text="time(message.created_at)"></time>
                    </p>
                    <p class="mt-1 text-body whitespace-pre-line text-text" x-text="message.text"></p>
                </div>
            </li>
        </template>
    </ul>

    <p class="text-caption text-danger" x-show="error" x-cloak role="alert" x-text="error"></p>

    <form x-on:submit.prevent="send()" class="space-y-2" x-show="! terminal || @js($role === 'staff')">
        <label for="thread-text" class="block text-body font-medium text-text">{{ __('requests.write_message') }}</label>
        <textarea
            id="thread-text"
            rows="3"
            maxlength="5000"
            x-model="text"
            class="form-textarea w-full rounded-control border border-border bg-surface text-body text-text focus:border-primary focus:ring-2 focus:ring-focus"
        ></textarea>

        @if ($role === 'staff')
            <fieldset class="flex flex-wrap gap-4">
                <legend class="sr-only">{{ __('requests.visibility') }}</legend>
                <label class="inline-flex items-center gap-2 text-body text-text"><input type="radio" value="public" x-model="visibility" class="size-4 text-primary focus:ring-2 focus:ring-focus"> {{ __('requests.visibility_public') }}</label>
                <label class="inline-flex items-center gap-2 text-body text-text"><input type="radio" value="internal" x-model="visibility" class="size-4 text-primary focus:ring-2 focus:ring-focus"> {{ __('requests.visibility_internal') }}</label>
            </fieldset>
        @endif

        <x-ui.button type="submit" x-bind:disabled="sending || text.trim() === ''">
            <x-ui.icon name="paper-airplane" size="sm" />
            {{ __('requests.send') }}
        </x-ui.button>
    </form>

    <p class="text-caption text-muted" x-show="terminal && @js($role === 'guest')" x-cloak>{{ __('requests.thread_closed') }}</p>
</section>
