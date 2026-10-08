@php
    $turns = $metrics['turns'];
    $requests = $metrics['requests'];
    $seats = $metrics['seats_today'];
    $budget = $metrics['budget'];
    $seconds = fn (?int $ms) => $ms === null ? '–' : number_format($ms / 1000, 1).' s';
    $statusTone = fn (string $status) => match ($status) {
        'ok', 'not_required' => 'success',
        'unavailable', 'stale' => 'warning',
        default => 'danger',
    };
    $tiles = [
        ['label' => __('ui.operations.turns_completed'), 'value' => number_format($turns['completed'])],
        ['label' => __('ui.operations.turns_failed'), 'value' => number_format($turns['failed']).' ('.number_format($turns['error_rate'] * 100, 1).'%)'],
        ['label' => __('ui.operations.latency_p50'), 'value' => $seconds($turns['p50_ms'])],
        ['label' => __('ui.operations.latency_p95'), 'value' => $seconds($turns['p95_ms'])],
        ['label' => __('ui.operations.timeouts'), 'value' => number_format($turns['timeouts'])],
        ['label' => __('ui.operations.no_match'), 'value' => number_format($turns['no_match'])],
        ['label' => __('ui.operations.budget_exhausted'), 'value' => number_format($turns['budget_exhausted'])],
        ['label' => __('ui.operations.rate_limited'), 'value' => number_format($metrics['rate_limited'])],
        ['label' => __('ui.operations.tool_validation_failures'), 'value' => number_format($metrics['tool_validation_failures'])],
        ['label' => __('ui.operations.budget_today'), 'value' => number_format($budget['used']).' / '.number_format($budget['cap'])],
        ['label' => __('ui.operations.requests_pending'), 'value' => number_format($requests['pending'])],
        ['label' => __('ui.operations.oldest_pending'), 'value' => $requests['oldest_pending_minutes'] === null ? '–' : __('ui.operations.minutes', ['count' => number_format($requests['oldest_pending_minutes'])])],
        ['label' => __('ui.operations.seats_today'), 'value' => __('ui.operations.seats_value', ['booked' => number_format($seats['booked']), 'capacity' => number_format($seats['capacity'])])],
    ];
    $activeAlerts = array_keys(array_filter($metrics['alerts']));
    $statuses = \App\Modules\Requests\Enums\RequestStatus::cases();
@endphp

<x-layouts.admin
    :title="__('ui.nav.operations')"
    :heading="__('ui.operations.title')"
    :nav-items="$navItems"
    :breadcrumbs="[['label' => __('ui.nav.overview'), 'href' => route('admin.dashboard')], ['label' => __('ui.nav.operations')]]"
>
    <div class="space-y-6">
        <p class="text-body text-muted">{{ __('ui.operations.intro', ['days' => $metrics['window_days']]) }}</p>

        @if ($activeAlerts === [])
            <x-ui.alert tone="success">{{ __('ui.operations.alerts_none') }}</x-ui.alert>
        @else
            @foreach ($activeAlerts as $alert)
                <x-ui.alert tone="warning">{{ __('ui.operations.alerts.'.$alert) }}</x-ui.alert>
            @endforeach
        @endif

        <section aria-labelledby="ops-metrics">
            <h2 id="ops-metrics" class="mb-3 text-section-title font-semibold text-text">{{ __('ui.operations.metrics') }}</h2>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($tiles as $tile)
                    <x-ui.card padding="sm">
                        <p class="text-caption text-muted">{{ $tile['label'] }}</p>
                        <p class="mt-1 text-section-title font-semibold text-text">{{ $tile['value'] }}</p>
                    </x-ui.card>
                @endforeach
            </div>
        </section>

        <section aria-labelledby="ops-requests">
            <h2 id="ops-requests" class="mb-3 text-section-title font-semibold text-text">{{ __('ui.operations.by_type', ['days' => $metrics['window_days']]) }}</h2>
            <x-ui.table :headers="array_merge([__('requests.type_label')], array_map(fn ($status) => $status->label(), $statuses))">
                @foreach ($requests['by_type_status'] as $type => $counts)
                    <x-ui.table.row>
                        <x-ui.table.cell header>{{ __('requests.types.'.$type) }}</x-ui.table.cell>
                        @foreach ($statuses as $status)
                            <x-ui.table.cell>{{ number_format($counts[$status->value] ?? 0) }}</x-ui.table.cell>
                        @endforeach
                    </x-ui.table.row>
                @endforeach
            </x-ui.table>
        </section>

        <section aria-labelledby="ops-readiness">
            <h2 id="ops-readiness" class="mb-3 text-section-title font-semibold text-text">{{ __('ui.operations.readiness') }}</h2>
            <x-ui.table :headers="[__('ui.operations.check'), __('ui.status'), __('ui.operations.required')]">
                @foreach ($readiness['checks'] as $name => $check)
                    <x-ui.table.row>
                        <x-ui.table.cell header>{{ __('ui.operations.checks.'.$name) }}</x-ui.table.cell>
                        <x-ui.table.cell><x-ui.badge :tone="$statusTone($check['status'])">{{ __('ui.operations.statuses.'.$check['status']) }}</x-ui.badge></x-ui.table.cell>
                        <x-ui.table.cell class="text-muted">{{ $check['required'] ? __('ui.yes') : __('ui.no') }}</x-ui.table.cell>
                    </x-ui.table.row>
                @endforeach
            </x-ui.table>
        </section>
    </div>
</x-layouts.admin>
