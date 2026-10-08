{{-- headers: list of strings or ['label' => ..., 'sr_only' => bool]. Rows use x-ui.table.row / x-ui.table.cell. --}}
@props([
    'headers' => [],
    'caption' => null,
])

<div class="overflow-x-auto rounded-card border border-border bg-surface shadow-card">
    <table {{ $attributes->class('min-w-full divide-y divide-border text-left text-body') }}>
        @if ($caption !== null)
            <caption class="caption-top px-4 pt-3 text-left text-caption text-muted">{{ $caption }}</caption>
        @endif
        @if ($headers !== [])
            <thead class="bg-surface-muted">
                <tr>
                    @foreach ($headers as $header)
                        @php $header = is_array($header) ? $header : ['label' => $header]; @endphp
                        <th scope="col" class="px-4 py-3 text-caption font-semibold tracking-wide text-muted uppercase whitespace-nowrap">
                            @if ($header['sr_only'] ?? false)
                                <span class="sr-only">{{ $header['label'] }}</span>
                            @else
                                {{ $header['label'] }}
                            @endif
                        </th>
                    @endforeach
                </tr>
            </thead>
        @endif
        <tbody class="divide-y divide-border">
            {{ $slot }}
        </tbody>
    </table>
</div>
