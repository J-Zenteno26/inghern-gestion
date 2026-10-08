@props([
    'name' => null,
    'type' => 'text',
    'value' => null,
])
<input
    id="{{ $name }}"
    name="{{ $name }}"
    type="{{ $type }}"
    value="{{ old($name, $value) }}"
    {{ $attributes->class(["ui-control", "is-invalid" => $errors->has($name)]) }}
/>
