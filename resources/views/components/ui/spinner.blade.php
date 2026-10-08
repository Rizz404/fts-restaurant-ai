@props([
    'size' => 'md',
    'label' => null,
])

<span {{ $attributes->class('inline-flex items-center') }} @if ($label !== null) role="status" @else aria-hidden="true" @endif>
    <svg class="{{ \App\Support\Ui\ComponentStyles::spinnerSize($size) }} animate-spin motion-reduce:animate-none" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"></path>
    </svg>
    @if ($label !== null)
        <span class="sr-only">{{ $label }}</span>
    @endif
</span>
