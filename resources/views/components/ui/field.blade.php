{{-- Pair with a control that receives the same name and id. Help/error IDs come from FieldState. --}}
@props([
    'name',
    'id',
    'label',
    'required' => false,
    'help' => null,
    'error' => null,
])

@php
    $state = \App\Support\Ui\FieldState::for($name, $id, $errors ?? null, $help, $error);
@endphp

<div {{ $attributes->class('space-y-1.5') }}>
    <label for="{{ $id }}" class="block text-body font-medium text-text">
        {{ $label }}
        @if ($required)
            <span class="text-danger" aria-hidden="true">*</span>
            <span class="sr-only">({{ __('ui.required') }})</span>
        @endif
    </label>

    {{ $slot }}

    @if ($state->hasHelp())
        <p id="{{ $state->helpId() }}" class="text-caption text-muted">{{ $state->help }}</p>
    @endif

    @if ($state->invalid())
        <p id="{{ $state->errorId() }}" class="flex items-start gap-1 text-caption text-danger">
            <x-ui.icon name="exclamation-circle" size="sm" class="mt-px" />
            <span>{{ $state->error }}</span>
        </p>
    @endif
</div>
