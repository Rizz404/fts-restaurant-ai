@php
    $crumbs = [['label' => __('ui.nav.overview'), 'href' => route('admin.dashboard')], ['label' => __('ui.nav.requests')]];
@endphp

<x-layouts.admin :title="__('ui.nav.requests')" :heading="__('ui.nav.requests')" :nav-items="$navItems" :breadcrumbs="$crumbs">
    @if ($today !== null)
        <section aria-labelledby="today-title" class="mb-4 rounded-card border border-border bg-surface p-4 shadow-card">
            <h2 id="today-title" class="text-section-title font-semibold text-text">{{ __('requests.today_title') }}</h2>
            <p class="text-caption text-muted">{{ $today['date'] }} · {{ __('requests.today_capacity', ['capacity' => $today['capacity']]) }} · {{ __('requests.today_pending', ['count' => $today['pending']]) }}</p>
            @if ($today['slots'] === [])
                <p class="mt-2 text-body text-muted">{{ __('requests.today_empty') }}</p>
            @else
                <ul class="mt-2 flex flex-wrap gap-2">
                    @foreach ($today['slots'] as $slot)
                        <li class="chip" aria-label="{{ $slot['slot'] }}: {{ $slot['booked'] }}/{{ $today['capacity'] }}">
                            <span>{{ $slot['slot'] }}</span>
                            <span class="chip-count">{{ $slot['booked'] }}/{{ $today['capacity'] }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endif

    <form method="GET" action="{{ route('admin.requests.index') }}" class="mb-4 grid gap-3 md:grid-cols-[12rem_12rem_12rem_auto_auto] md:items-end">
        <x-ui.field name="type" id="filter-type" :label="__('requests.type_label')">
            <x-ui.select name="type" id="filter-type" :options="$typeOptions" :value="$filters['type']" :placeholder="__('ui.all')" />
        </x-ui.field>
        <x-ui.field name="status" id="filter-status" :label="__('ui.status')">
            <x-ui.select name="status" id="filter-status" :options="$statusOptions" :value="$filters['status']" :placeholder="__('requests.scope_active')" />
        </x-ui.field>
        <x-ui.field name="date" id="filter-date" :label="__('requests.fields.date')">
            <x-ui.input name="date" id="filter-date" type="date" :value="$filters['date']" />
        </x-ui.field>
        <label class="inline-flex items-center gap-2 pb-2 text-body text-text">
            <input type="checkbox" name="mine" value="1" @checked($filters['mine']) class="size-4 rounded border-border text-primary focus:ring-2 focus:ring-focus focus:ring-offset-0">
            {{ __('requests.mine') }}
        </label>
        <x-ui.button type="submit" variant="outline">
            <x-ui.icon name="magnifying-glass" size="sm" />
            {{ __('ui.filter') }}
        </x-ui.button>
    </form>

    @if ($requests->isEmpty())
        <x-ui.empty-state icon="inbox" :title="__('requests.admin_empty_title')" :description="__('requests.admin_empty_description')" />
    @else
        <x-ui.table :headers="[__('requests.reference'), __('requests.type_label'), __('requests.details'), __('ui.status'), __('requests.assignee'), ['label' => __('ui.actions'), 'sr_only' => true]]">
            @foreach ($requests as $item)
                <x-ui.table.row>
                    <x-ui.table.cell header><span class="font-mono">{{ $item->reference }}</span></x-ui.table.cell>
                    <x-ui.table.cell>{{ $item->type->label() }}</x-ui.table.cell>
                    <x-ui.table.cell>
                        <span class="block max-w-md truncate">{{ \App\Modules\Requests\Presenters\RequestPresenter::summary($item->type, $item->payload, $item->pricing) }}</span>
                        <span class="block text-caption text-muted">{{ $item->contact_name }}</span>
                    </x-ui.table.cell>
                    <x-ui.table.cell><x-ui.badge :tone="$item->status->tone()">{{ $item->status->label() }}</x-ui.badge></x-ui.table.cell>
                    <x-ui.table.cell>{{ $item->assignee?->name ?? '—' }}</x-ui.table.cell>
                    <x-ui.table.cell class="text-right">
                        <x-ui.link-button :href="route('admin.requests.show', $item->public_id)" variant="ghost" size="sm">{{ __('requests.open') }}</x-ui.link-button>
                    </x-ui.table.cell>
                </x-ui.table.row>
            @endforeach
        </x-ui.table>
        <x-ui.pagination :paginator="$requests" class="mt-4" />
    @endif
</x-layouts.admin>
