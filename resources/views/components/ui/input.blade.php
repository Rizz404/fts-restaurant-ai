@aware(['help' => null, 'error' => null])

@props([
    'name',
    'id' => null,
    'type' => 'text',
    'value' => null,
    'invalid' => null,
    'describedby' => null,
    'disabled' => false,
])

@php
    $id ??= $name;
    $state = \App\Support\Ui\FieldState::for($name, $id, $errors ?? null, $help, $error);
    $invalid ??= $state->invalid();
    $describedby ??= $state->describedBy();
    // Passwords never echo a previous value.
    $value = $type === 'password' ? null : old($state->errorKey, $value);
@endphp

<input
    type="{{ $type }}"
    name="{{ $name }}"
    id="{{ $id }}"
    @if ($value !== null) value="{{ $value }}" @endif
    @if ($invalid) aria-invalid="true" @endif
    @if ($describedby) aria-describedby="{{ $describedby }}" @endif
    @disabled($disabled)
    {{ $attributes->class(['form-input', \App\Support\Ui\ComponentStyles::control($invalid)]) }}
>
