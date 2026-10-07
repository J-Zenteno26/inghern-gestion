@props(["contacto" => null, "snapshot" => []])

@php
    $snapshot = is_array($snapshot) ? $snapshot : [];
    $nombre = $snapshot["nombre"] ?? $contacto?->nombre;
    $cargo = $snapshot["cargo"] ?? $contacto?->cargo;
    $email = $snapshot["email"] ?? $contacto?->email;
    $telefono = $snapshot["telefono"] ?? $contacto?->telefono;
@endphp

<div {{ $attributes->class(["contact-summary"]) }}>
    @if ($nombre)
        <span class="contact-summary__name">{{ $nombre }}</span>

        @if ($cargo)
            <span class="contact-summary__meta">{{ $cargo }}</span>
        @endif

        @if ($email)
            <a class="contact-summary__link" href="mailto:{{ $email }}">
                {{ $email }}
            </a>
        @endif

        @if ($telefono)
            <a
                class="contact-summary__link"
                href="tel:{{ preg_replace('/[^+0-9]/', '', $telefono) }}"
            >
                {{ $telefono }}
            </a>
        @endif

        @unless ($cargo || $email || $telefono)
            <span class="contact-summary__empty">
                Sin datos de contacto registrados
            </span>
        @endunless
    @else
        <span class="contact-summary__empty">Sin encargado definido</span>
    @endif
</div>
