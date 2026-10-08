@props([
    'name',
    'id' => null,
    'value' => '1',
    'checked' => false,
    'disabled' => false,
    'describedby' => null,
])

@php
    $id ??= $name;
    $key = \App\Support\Ui\FieldState::errorKey($name);
    // After a failed submit an unchecked box is absent from the old input;
    // array names (roles[]) are checked when their value is in the old list.
    if (\Illuminate\Support\Arr::except(request()->old(), ['_token', '_method']) !== []) {
        $old = old($key);
        $checked = str_ends_with($name, '[]')
            ? in_array((string) $value, array_map('strval', (array) $old), true)
            : $old !== null;
    } else {
        $checked = (bool) $checked;
    }
@endphp

<label for="{{ $id }}" class="inline-flex items-start gap-2 text-body text-text">
    <input
        type="checkbox"
        name="{{ $name }}"
        id="{{ $id }}"
        value="{{ $value }}"
        @checked($checked)
        @disabled($disabled)
        @if ($describedby) aria-describedby="{{ $describedby }}" @endif
        {{ $attributes->class(['form-checkbox mt-1', \App\Support\Ui\ComponentStyles::CHECKBOX]) }}
    >
    <span>{{ $slot }}</span>
</label>
