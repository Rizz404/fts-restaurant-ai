{{-- Map domain statuses to a tone in a presenter; the badge only knows tones. --}}
@props([
    'tone' => 'neutral',
])

<span {{ $attributes->class(\App\Support\Ui\ComponentStyles::badge($tone)) }}>{{ $slot }}</span>
