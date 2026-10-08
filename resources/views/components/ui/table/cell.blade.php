@props([
    'header' => false,
])

@if ($header)
    <th scope="row" {{ $attributes->class('px-4 py-3 align-middle font-medium text-text') }}>{{ $slot }}</th>
@else
    <td {{ $attributes->class('px-4 py-3 align-middle text-text') }}>{{ $slot }}</td>
@endif
