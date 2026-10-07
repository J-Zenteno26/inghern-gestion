@props([
    "label",
    "value",
    "detail",
    "tone" => "azul",
    "icon" => null,
])
<article class="metric metric--{{ $tone }}{{ $icon ? ' metric--with-icon' : '' }}">
    @if ($icon)
        <span class="metric__icon" aria-hidden="true">
            <x-ui.icon :name="$icon" size="20" />
        </span>
    @endif
    <div class="metric__label">{{ $label }}</div>
    <div class="metric__value numeric">{{ $value }}</div>
    <div class="metric__detail">{{ $detail }}</div>
</article>
