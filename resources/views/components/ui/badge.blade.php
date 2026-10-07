@props([
    "status",
    "tone" => null,
])
@php
    $tone ??= match ($status) {
        "activo", "activa", "finalizado", "aprobada", "aceptada" => "success",
        "en_curso", "enviada", "planificado" => "info",
        "borrador", "prospecto", "en_revision", "en_pausa", "por_validar" => "warning",
        "cancelado", "anulada", "rechazada", "inactivo" => "danger",
        default => "neutral",
    };
@endphp

<span
    {{ $attributes->class("ui-badge" . ($tone !== "neutral" ? " ui-badge--" . $tone : "")) }}
>
    {{ str_replace("_", " ", $status) }}
</span>
