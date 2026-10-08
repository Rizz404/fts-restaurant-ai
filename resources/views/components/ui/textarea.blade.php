@aware(['help' => null, 'error' => null])

@props([
    'name',
    'id' => null,
    'value' => null,
    'rows' => 4,
    'invalid' => null,
    'describedby' => null,
    'disabled' => false,
])

@php
    $id ??= $name;
    $state = \App\Support\Ui\FieldState::for($name, $id, $errors ?? null, $help, $error);
    $invalid ??= $state->invalid();
    $describedby ??= $state->describedBy();
    $value = old($state->errorKey, $value);
@endphp

<textarea
    name="{{ $name }}"
    id="{{ $id }}"
    rows="{{ (int) $rows }}"
    @if ($invalid) aria-invalid="true" @endif
    @if ($describedby) aria-describedby="{{ $describedby }}" @endif
    @disabled($disabled)
    {{ $attributes->class(['form-textarea', \App\Support\Ui\ComponentStyles::control($invalid)]) }}
>{{ $value }}</textarea>
