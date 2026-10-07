@props([
    "field",
    "label",
    "current" => "",
    "direction" => "asc",
])

@php
    $active = $current === $field;
    $nextDirection = $active && $direction === "asc" ? "desc" : "asc";
    $query = array_merge(request()->except(["orden", "direccion", "page"]), [
        "orden" => $field,
        "direccion" => $nextDirection,
    ]);
@endphp

<a
    href="{{ route("cotizaciones.index", $query) }}"
    {{ $attributes->class(["sort-link", "is-active" => $active]) }}
>
    <span>{{ $label }}</span>
    <span class="sort-link__indicator" aria-hidden="true">
        {{ $active ? ($direction === "asc" ? "↑" : "↓") : "↕" }}
    </span>
    <span class="sr-only">
        Ordenar {{ $nextDirection === "asc" ? "ascendente" : "descendente" }}
    </span>
</a>
