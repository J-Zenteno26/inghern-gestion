@props([
    "name",
    "label",
    "hint" => null,
    "value" => 1,
    "checked" => false,
])
<label class="ui-check">
    <input
        type="checkbox"
        name="{{ $name }}"
        value="{{ $value }}"
        @checked(old($name, $checked))
        {{ $attributes }}
    />
    <span class="ui-check__copy">
        <span class="ui-check__label">{{ $label }}</span>
        @if ($hint)
            <span class="ui-check__hint">{{ $hint }}</span>
        @endif
    </span>
</label>
