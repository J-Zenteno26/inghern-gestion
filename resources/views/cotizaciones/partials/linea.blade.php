@php
    $serviceIndex = $serviceIndex ?? null;
    $dotPrefix = $serviceIndex === null
        ? "partidas.{$i}"
        : "servicios.{$serviceIndex}.partidas.{$i}";
    $htmlPrefix = $serviceIndex === null
        ? "partidas[{$i}]"
        : "servicios[{$serviceIndex}][partidas][{$i}]";
    $esNormalizado = ($partida["metodo_precio"] ?? "") === "normalizado";
    $esPrincipal = (string) $i === '0';
    $clase = $partida['clase'] ?? 'servicio';
    $descripcion = (string) ($partida['descripcion'] ?? '');
    $descripcionNormalizada = mb_strtolower($descripcion);
    $tipoVisual = $esPrincipal ? 'servicio' : (
        str_contains($descripcionNormalizada, 'hosped') ? 'hospedaje' : (
        str_contains($descripcionNormalizada, 'aliment') ? 'alimentacion' : (
        str_contains($descripcionNormalizada, 'moviliz') ? 'movilizacion' : (
        str_contains($descripcionNormalizada, 'transport') ? 'transporte' : (
        $clase === 'traslado' ? 'traslado' : ($clase === 'servicio' ? 'servicio' : 'otro')
    )))));
    $etiquetaVisual = match ($tipoVisual) {
        'hospedaje' => 'Hospedaje',
        'transporte' => 'Transporte',
        'alimentacion' => 'Alimentación',
        'movilizacion' => 'Movilización',
        'traslado' => 'Traslado / logística',
        'otro' => 'Costo asociado',
        default => $esPrincipal ? 'Servicio principal' : 'Línea de servicio',
    };
@endphp
<div
    class="quote-line {{ $esPrincipal ? 'quote-line--primary' : 'quote-line--associated' }}"
    data-line
    data-line-kind="{{ $tipoVisual }}"
    @if($esPrincipal) data-primary-line @endif
>
    {{-- La complejidad técnica se conserva para persistencia, pero no se expone como formulario principal. --}}
    <input type="hidden" name="{{ $htmlPrefix }}[cantidad]" value="{{ $partida['cantidad'] ?? 1 }}" data-qty />
    <input type="hidden" name="{{ $htmlPrefix }}[unidad]" value="{{ $partida['unidad'] ?? \App\Enums\UnidadPrecio::Servicio->value }}" data-manual-unit />
    <input type="hidden" name="{{ $htmlPrefix }}[clase]" value="{{ $clase }}" data-line-class />
    <input type="hidden" name="{{ $htmlPrefix }}[monto_sugerido]" value="{{ $partida['monto_sugerido'] ?? '' }}" data-suggested />
    <input type="hidden" data-normalized-unit value="" />

    <select name="{{ $htmlPrefix }}[metodo_precio]" data-price-method hidden>
        <option value="a_criterio" @selected(($partida['metodo_precio'] ?? '') === 'a_criterio')>Criterio comercial</option>
        <option value="referencia" @selected(($partida['metodo_precio'] ?? '') === 'referencia')>Referencia del catálogo</option>
        <option value="normalizado" @selected($esNormalizado) data-normalized-option>Normalizado por criterios</option>
        <option value="hora_hombre" @selected(($partida['metodo_precio'] ?? '') === 'hora_hombre')>Hora hombre</option>
        <option value="costo_margen" @selected(($partida['metodo_precio'] ?? '') === 'costo_margen')>Costo + margen</option>
    </select>

    @unless($esPrincipal)
        <div class="quote-line__top">
            <div class="quote-line__identity">
                <span class="quote-line__kind" data-line-kind-label>{{ $etiquetaVisual }}</span>
                <span class="quote-line__required-note">Costo asociado</span>
            </div>

            <button class="ui-button ui-button--danger ui-button--icon quote-line__remove" type="button" data-remove-line title="Quitar costo">
                <x-ui.icon name="trash" size="16" />
            </button>
        </div>
    @endunless

    <div class="quote-line__content">
        <x-ui.field
            :name="$dotPrefix.'.descripcion'"
            :label="$esPrincipal ? 'Concepto cotizado' : 'Concepto adicional'"
            class="quote-line__description"
            required
        >
            <input
                class="ui-control"
                name="{{ $htmlPrefix }}[descripcion]"
                value="{{ $descripcion }}"
                placeholder="{{ $esPrincipal ? 'Descripción que verá el cliente' : 'Ej. Hospedaje, traslado o alimentación' }}"
                required
                data-line-description
            />
        </x-ui.field>

        <x-ui.field :name="$dotPrefix.'.precio_unitario'" label="Valor neto" class="quote-line__value" required>
            <div class="quote-money-input">
                <span>$</span>
                <input
                    class="ui-control quote-line__price"
                    name="{{ $htmlPrefix }}[precio_unitario]"
                    type="number"
                    min="0"
                    step="0.01"
                    value="{{ $partida['precio_unitario'] ?? '' }}"
                    placeholder="0"
                    required
                    data-price
                />
            </div>
        </x-ui.field>
    </div>

    <div class="quote-line__context">
        <div class="quote-line__reference">
            <span class="quote-line__pricing-icon"><x-ui.icon name="banknote" size="16" /></span>
            <div>
                <span class="quote-line__meta-label">Referencia económica</span>
                <strong data-reference-display>{{ $esPrincipal ? 'Sin referencia configurada' : 'Precio definido manualmente' }}</strong>
                <span class="quote-line__reference-hint" data-reference-hint>
                    {{ $esPrincipal ? 'Se completa desde el catálogo del servicio.' : 'Completa únicamente el valor que deseas incorporar.' }}
                </span>
            </div>
        </div>

        <div class="quote-line__actions">
            @if($esPrincipal)
                <button type="button" class="quote-text-action" data-use-reference hidden>
                    <x-ui.icon name="rotate-ccw" size="14" />
                    Usar referencia
                </button>
            @endif
            <button type="button" class="quote-text-action" data-toggle-line-note aria-expanded="{{ !empty($partida['justificacion_ajuste']) ? 'true' : 'false' }}">
                <x-ui.icon name="message-square-text" size="14" />
                <span data-note-action-label>{{ !empty($partida['justificacion_ajuste']) ? 'Ocultar nota' : 'Añadir nota o criterio' }}</span>
            </button>
        </div>
    </div>

    <div class="quote-line__note" data-line-note @if(empty($partida['justificacion_ajuste'])) hidden @endif>
        <x-ui.field :name="$dotPrefix.'.justificacion_ajuste'" label="Nota / criterio comercial">
            <input
                class="ui-control"
                name="{{ $htmlPrefix }}[justificacion_ajuste]"
                value="{{ $partida['justificacion_ajuste'] ?? '' }}"
                placeholder="Opcional: deja contexto para este valor"
                data-price-justification
            />
        </x-ui.field>
    </div>

    <div class="quote-line__normalization stack" data-normalized-panel @unless($esNormalizado) hidden @endunless>
        <div class="quote-line__normalization-head">
            <span class="quote-line__normalization-icon"><x-ui.icon name="calculator" size="18" /></span>
            <div>
                <strong>Criterios de normalización</strong>
                <span>La referencia se recalcula según los factores configurados para este tipo de servicio.</span>
            </div>
        </div>
        <div class="definition-grid">
            <div><div class="definition__label">Precio base</div><div class="definition__value" data-normalized-base>Sin registro</div></div>
            <div><div class="definition__label">Unidad de referencia</div><div class="definition__value" data-normalized-reference-unit>Sin registro</div></div>
            <div><div class="definition__label">Factor total</div><div class="definition__value" data-normalized-factor>Sin registro</div></div>
            <div><div class="definition__label">Rango sugerido</div><div class="definition__value" data-normalized-range>Sin registro</div></div>
        </div>
        <div class="form-grid" data-normalized-criteria></div>
        @error($dotPrefix.'.criterios')
            <span class="ui-field__error">{{ $message }}</span>
        @enderror
    </div>
    <script type="application/json" data-selected-criteria>
        {!! json_encode($partida['criterios'] ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}
    </script>
</div>
