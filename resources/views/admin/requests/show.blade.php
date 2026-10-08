@php
    use App\Modules\Requests\Support\WizardLabels;

    $crumbs = [
        ['label' => __('ui.nav.overview'), 'href' => route('admin.dashboard')],
        ['label' => __('ui.nav.requests'), 'href' => route('admin.requests.index')],
        ['label' => $view['reference']],
    ];
@endphp

<x-layouts.admin :title="$view['reference']" :heading="$view['reference']" :nav-items="$navItems" :breadcrumbs="$crumbs">
    <div
        class="grid gap-4 lg:grid-cols-[2fr_3fr]"
        x-data="requestThread(@js([
            'requestId' => $view['request_id'],
            'role' => 'staff',
            'status' => $view['status'],
            'statusLabel' => $view['status_label'],
            'statusTone' => $view['status_tone'],
            'version' => $view['version'],
            'assigneeId' => $view['assignee']['id'] ?? '',
            'labels' => WizardLabels::thread(),
        ]))"
    >
        <div class="space-y-4">
            <x-ui.card>
                <div class="space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-caption text-muted">{{ $view['type_label'] }} · {{ $view['team_label'] }}</p>
                        <x-ui.badge :tone="$view['status_tone']">{{ $view['status_label'] }}</x-ui.badge>
                    </div>

                    <p class="text-body font-medium text-text">{{ $view['summary'] }}</p>

                    <dl class="grid gap-1 text-body">
                        @if ($view['contact']['name'])
                            <div class="flex flex-wrap gap-2"><dt class="font-medium text-text">{{ __('requests.fields.name') }}</dt><dd class="text-text">{{ $view['contact']['name'] }}</dd></div>
                        @endif
                        @if ($view['contact']['phone'])
                            <div class="flex flex-wrap gap-2"><dt class="font-medium text-text">{{ __('requests.fields.phone') }}</dt><dd><a class="text-primary underline" href="tel:{{ preg_replace('/[^0-9+]/', '', $view['contact']['phone']) }}">{{ $view['contact']['phone'] }}</a></dd></div>
                        @endif
                        @if ($view['contact']['email'])
                            <div class="flex flex-wrap gap-2"><dt class="font-medium text-text">{{ __('requests.fields.email') }}</dt><dd class="break-all text-text">{{ $view['contact']['email'] }}</dd></div>
                        @endif
                        @if ($view['table_label'])
                            <div class="flex flex-wrap gap-2"><dt class="font-medium text-text">{{ __('requests.table') }}</dt><dd class="text-text">{{ $view['table_label'] }}</dd></div>
                        @endif
                        <div class="flex flex-wrap gap-2"><dt class="font-medium text-text">{{ __('requests.received') }}</dt><dd class="text-text">{{ \Illuminate\Support\Carbon::parse($view['created_at'])->timezone(config('restaurant.timezone'))->isoFormat('D MMM YYYY HH:mm') }}</dd></div>
                    </dl>

                    @php $payload = $view['payload']; @endphp
                    @if (! empty($payload['notes']))
                        <p class="rounded-control bg-surface-muted p-3 text-body whitespace-pre-line text-text">{{ $payload['notes'] }}</p>
                    @endif
                    @if (! empty($payload['body']))
                        <p class="rounded-control bg-surface-muted p-3 text-body whitespace-pre-line text-text">{{ $payload['body'] }}</p>
                    @endif

                    @if (! empty($view['pricing']))
                        <ul class="divide-y divide-border text-body">
                            @foreach ($view['pricing']['lines'] as $line)
                                <li class="flex justify-between gap-3 py-1"><span>{{ $line['quantity'] }}× {{ $line['name'] }}</span><span class="text-muted">{{ \App\Modules\Menu\Support\Money::format((int) $line['line_total'], $view['pricing']['currency']) }}</span></li>
                            @endforeach
                            <li class="flex justify-between gap-3 py-1 font-semibold text-text"><span>{{ __('requests.total') }}</span><span>{{ $view['pricing']['total_formatted'] }}</span></li>
                        </ul>
                    @endif
                </div>
            </x-ui.card>

            @if ($statusActions !== [])
                <x-ui.card>
                    <div class="space-y-3">
                        <h2 class="text-section-title font-semibold text-text">{{ __('requests.change_status') }}</h2>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($statusActions as $action)
                                <x-ui.button :variant="$action['variant']" x-on:click="askStatus('{{ $action['status'] }}', {{ $action['needs_reason'] ? 'true' : 'false' }})" x-bind:disabled="busy">{{ $action['label'] }}</x-ui.button>
                            @endforeach
                        </div>

                        <div x-show="confirmingStatus" x-cloak class="space-y-2">
                            <label for="status-reason" class="block text-body font-medium text-text">{{ __('requests.reason') }}</label>
                            <textarea id="status-reason" rows="2" maxlength="500" x-model="reason" class="form-textarea w-full rounded-control border border-border bg-surface text-body text-text focus:border-primary focus:ring-2 focus:ring-focus"></textarea>
                            <div class="flex gap-2">
                                <x-ui.button x-on:click="changeStatus(confirmingStatus)" x-bind:disabled="busy">{{ __('requests.confirm_action') }}</x-ui.button>
                                <x-ui.button variant="outline" x-on:click="confirmingStatus = null; reason = ''">{{ __('ui.cancel') }}</x-ui.button>
                            </div>
                        </div>
                    </div>
                </x-ui.card>
            @endif

            @if (! $guestRequest->isTerminal() && $assignees->isNotEmpty())
                <x-ui.card>
                    <form x-on:submit.prevent="assign()" class="space-y-2">
                        <x-ui.field name="assignee_id" id="assignee" :label="__('requests.assignee')">
                            <x-ui.select name="assignee_id" id="assignee" x-model="assigneeId" :options="$assignees->mapWithKeys(fn ($user) => [$user->id => $user->name])->all()" :placeholder="__('requests.unassigned')" />
                        </x-ui.field>
                        <x-ui.button type="submit" variant="outline" x-bind:disabled="busy || ! assigneeId">{{ __('requests.assign') }}</x-ui.button>
                    </form>
                </x-ui.card>
            @endif

            <x-ui.card>
                <div class="space-y-2">
                    <h2 class="text-section-title font-semibold text-text">{{ __('requests.timeline') }}</h2>
                    <ol class="space-y-2 border-l border-border pl-4">
                        @foreach ($guestRequest->events->sortBy('id') as $event)
                            <li class="text-body">
                                <span class="font-medium text-text">{{ __('requests.events.'.$event->event_code) }}</span>
                                @if ($event->next_status)
                                    <span class="text-muted">→ {{ __('requests.status.'.$event->next_status) }}</span>
                                @endif
                                <span class="block text-caption text-muted">{{ $event->created_at->timezone(config('restaurant.timezone'))->isoFormat('D MMM HH:mm') }} · {{ $event->actor?->name ?? __('requests.actor_'.$event->actor_type) }}</span>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </x-ui.card>
        </div>

        <x-ui.card>
            <p class="mb-2 text-caption text-danger" x-show="error" x-cloak role="alert" x-text="error"></p>
            <x-request-thread role="staff" />
        </x-ui.card>
    </div>
</x-layouts.admin>
