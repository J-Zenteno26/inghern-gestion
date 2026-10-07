@props([
    "name",
    "value" => null,
])
<textarea
    id="{{ $name }}"
    name="{{ $name }}"
    {{ $attributes->class(["ui-control", "is-invalid" => $errors->has($name)]) }}
>
{{ old($name, $value) }}</textarea>
