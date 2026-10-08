{{-- options: list of ['value' => ..., 'label' => ...] or [value => label]; labels are translated by the caller. --}}
@aware(['help' => null, 'error' => null])

@props([
    'name',
    'id' => null,
    'options' => [],
    'value' => null,
    'placeholder' => null,
    'invalid' => null,
    'describedby' => null,
    'disabled' => false,
])

@php
    $id ??= $name;
    $state = \App\Support\Ui\FieldState::for($name, $id, $errors ?? null, $help, $error);
    $invalid ??= $state->invalid();
    $describedby ??= $state->describedBy();
    $selected = old($state->errorKey, $value);
    $normalized = [];
    foreach ($options as $key => $option) {
        $normalized[] = is_array($option)
            ? ['value' => (string) $option['value'], 'label' => $option['label']]
            : ['value' => (string) $key, 'label' => $option];
    }
@endphp

<select
    name="{{ $name }}"
    id="{{ $id }}"
    @if ($invalid) aria-invalid="true" @endif
    @if ($describedby) aria-describedby="{{ $describedby }}" @endif
    @disabled($disabled)
    {{ $attributes->class(['form-select', \App\Support\Ui\ComponentStyles::control($invalid)]) }}
>
    @if ($placeholder !== null)
        <option value="" @selected($selected === null || $selected === '')>{{ $placeholder }}</option>
    @endif
    @foreach ($normalized as $option)
        <option value="{{ $option['value'] }}" @selected($selected !== null && (string) $selected === $option['value'])>{{ $option['label'] }}</option>
    @endforeach
</select>
