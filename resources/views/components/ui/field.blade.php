@props([
    "label",
    "name",
    "required" => false,
    "hint" => null,
])
<div {{ $attributes->class("ui-field") }}>
    <label class="ui-field__label" for="{{ $name }}">
        {{ $label }}
        @if ($required)
            <span class="ui-field__required">*</span>
        @endif
    </label>
    {{ $slot }}
    @if ($hint)
        <span class="ui-field__hint">{{ $hint }}</span>
    @endif

    @error($name)
        <span class="ui-field__error">{{ $message }}</span>
    @enderror
</div>
