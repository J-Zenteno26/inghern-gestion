@props([
    "name",
])
<select
    id="{{ $name }}"
    name="{{ $name }}"
    {{ $attributes->class(["ui-control", "is-invalid" => $errors->has($name)]) }}
>
    {{ $slot }}
</select>
