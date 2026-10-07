@extends('layouts.app')
@section('title', $cotizacion->codigo)
@section('content')
    @php
        $formatMoney = static fn ($amount, $currency = null) => $amount === null
            ? 'Sin registro'
            : trim(($currency ?: $revisionActual->moneda) . ' $' . number_format((float) $amount, 0, ',', '.'));

        $claseLabels = [
            'servicio' => 'Servicio principal',
            'traslado' => 'Traslado / hospedaje',
            'costo_adicional' => 'Costo adicional',
        ];

        $metodoLabels = [
            'a_criterio' => 'Criterio comercial',
            'referencia' => 'Referencia del catálogo',
            'normalizado' => 'Normalizado por criterios',
            'hora_hombre' => 'Hora hombre',
            'costo_margen' => 'Costo + margen',
        ];
    @endphp

    <div class="quote-show-page">
    <x-ui.page-header
        :eyebrow="'Cotización · ' . $cotizacion->codigo"
        :title="$revisionActual->titulo"
        :description="$cotizacion->cliente->nombre_display"
    >
        <x-slot:actions>
            <div class="quote-show-header-actions">
                <button type="button" class="ui-button ui-button--outline ui-button--small">
                    <x-ui.icon name="download" size="16" />
                    Exportar PDF
                </button>
                <button type="button" class="ui-button ui-button--outline ui-button--small">
                    <x-ui.icon name="file-text" size="16" />
                    Compartir
                </button>
                @if ($cotizacion->estado !== 'aceptada')
                    <form method="POST" action="{{ route('cotizaciones.aceptar', $cotizacion) }}">
                        @csrf
                        @method('PATCH')
                        <x-ui.button type="submit" size="small">
                            <x-ui.icon name="circle-check" size="16" />
                            Aceptar cotización
                        </x-ui.button>
                    </form>
                @endif
                <x-ui.badge :status="$cotizacion->estado" />
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    @foreach ($revisionActual->bloques->where('tipo', 'nota_importacion') as $nota)
        <div class="flash flash--warning">
            <div>
                <strong>{{ $nota->titulo }}</strong>
                <div>{{ $nota->contenido }}</div>
            </div>
        </div>
    @endforeach

    <section class="quote-show-metrics">
        <article class="quote-show-metric quote-show-metric--blue">
            <span class="quote-show-metric__icon">
                <x-ui.icon name="briefcase-business" size="18" />
            </span>
            <div>
                <span class="quote-show-metric__label">Servicios incluidos</span>
                <strong class="quote-show-metric__value">{{ $revisionActual->servicios->count() }}</strong>
                <span class="quote-show-metric__detail">Bloques cotizados en esta revisión</span>
            </div>
        </article>
        <article class="quote-show-metric quote-show-metric--orange">
            <span class="quote-show-metric__icon">
                <x-ui.icon name="list-plus" size="18" />
            </span>
            <div>
                <span class="quote-show-metric__label">Líneas económicas</span>
                <strong class="quote-show-metric__value">{{ $revisionActual->partidas->count() }}</strong>
                <span class="quote-show-metric__detail">Conceptos que componen la propuesta</span>
            </div>
        </article>
        <article class="quote-show-metric quote-show-metric--slate">
            <span class="quote-show-metric__icon">
                <x-ui.icon name="banknote" size="18" />
            </span>
            <div>
                <span class="quote-show-metric__label">Subtotal neto</span>
                <strong class="quote-show-metric__value">{{ $formatMoney($revisionActual->subtotal) }}</strong>
                <span class="quote-show-metric__detail">Antes de IVA</span>
            </div>
        </article>
        <article class="quote-show-metric quote-show-metric--petrol">
            <span class="quote-show-metric__icon">
                <x-ui.icon name="receipt" size="18" />
            </span>
            <div>
                <span class="quote-show-metric__label">Total de la propuesta</span>
                <strong class="quote-show-metric__value">{{ $formatMoney($revisionActual->total) }}</strong>
                <span class="quote-show-metric__detail">Incluye IVA {{ number_format($revisionActual->iva_porcentaje, 0) }}%</span>
            </div>
        </article>
    </section>

    <div class="detail-grid quote-show-layout">
        <div class="stack">
            <x-ui.panel class="quote-show-panel quote-show-panel--economy">
                <x-slot:title>
                    <div class="quote-show-panel__titlewrap">
                        <span class="quote-show-panel__icon">
                            <x-ui.icon name="receipt" size="18" />
                        </span>
                        <div>
                            <h2 class="ui-panel__title">Composición económica</h2>
                            <div class="ui-panel__subtitle">
                                Revisión {{ str_pad((string) $revisionActual->revision, 2, '0', STR_PAD_LEFT) }} · lectura comercial de la propuesta
                            </div>
                        </div>
                    </div>
                </x-slot:title>

                <div class="quote-show-lines">
                    @foreach ($revisionActual->partidas as $partida)
                        @php
                            $claseLabel = $claseLabels[$partida->clase] ?? str($partida->clase)->replace('_', ' ')->title();
                            $metodoLabel = $metodoLabels[$partida->metodo_precio] ?? str($partida->metodo_precio)->replace('_', ' ')->title();
                            $lineTone = match ($partida->clase) {
                                'traslado' => 'travel',
                                'costo_adicional' => 'other',
                                default => 'service',
                            };
                            $showDetail = filled($partida->justificacion_ajuste)
                                || $partida->monto_sugerido !== null
                                || $partida->metodo_precio === 'normalizado';
                        @endphp

                        <article class="quote-show-line quote-show-line--{{ $lineTone }}">
                            <div class="quote-show-line__number">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</div>
                            <div class="quote-show-line__header">
                                <div class="quote-show-line__identity">
                                    <div class="quote-show-line__eyebrow">{{ $claseLabel }}</div>
                                    <h3>{{ $partida->descripcion }}</h3>
                                    <div class="quote-show-line__chips">
                                        <span class="quote-show-chip">{{ $metodoLabel }}</span>
                                        @if ($partida->monto_sugerido !== null)
                                            <span class="quote-show-chip quote-show-chip--soft">
                                                Referencia {{ $formatMoney($partida->monto_sugerido, $partida->moneda_precio_snapshot ?? $revisionActual->moneda) }}
                                            </span>
                                        @else
                                            <span class="quote-show-chip quote-show-chip--soft">Sin referencia de catálogo</span>
                                        @endif
                                    </div>
                                </div>

                                <div class="quote-show-line__amounts">
                                    <div class="quote-show-line__amount-card">
                                        <span>Valor cotizado</span>
                                        <strong>{{ $formatMoney($partida->monto_neto) }}</strong>
                                    </div>
                                </div>
                            </div>

                            @if ($showDetail)
                                <details class="quote-show-disclosure">
                                    <summary>
                                        <x-ui.icon name="calculator" size="16" />
                                        Ver criterio y detalle técnico
                                    </summary>
                                    <div class="quote-show-disclosure__body">
                                        <div class="definition-grid">
                                            <div>
                                                <div class="definition__label">Justificación</div>
                                                <div class="definition__value">{{ $partida->justificacion_ajuste ?: 'Sin registro' }}</div>
                                            </div>
                                            <div>
                                                <div class="definition__label">Método aplicado</div>
                                                <div class="definition__value">{{ $metodoLabel }}</div>
                                            </div>
                                            <div>
                                                <div class="definition__label">Clase registrada</div>
                                                <div class="definition__value">{{ $claseLabel }}</div>
                                            </div>
                                            @if ($partida->metodo_precio === 'normalizado')
                                                <div>
                                                    <div class="definition__label">Precio base utilizado</div>
                                                    <div class="definition__value">
                                                        @if ($partida->precio_base_snapshot !== null)
                                                            {{ $formatMoney($partida->precio_base_snapshot, $partida->moneda_precio_snapshot) }} · {{ \App\Enums\UnidadPrecio::tryFrom($partida->unidad_precio_snapshot)?->etiqueta() ?? $partida->unidad_precio_snapshot }}
                                                        @else
                                                            Sin registro
                                                        @endif
                                                    </div>
                                                </div>
                                                <div>
                                                    <div class="definition__label">Factor total</div>
                                                    <div class="definition__value">{{ $partida->factor_total_snapshot !== null ? number_format($partida->factor_total_snapshot, 4, ',', '.') : 'Sin registro' }}</div>
                                                </div>
                                                <div>
                                                    <div class="definition__label">Rango calculado</div>
                                                    <div class="definition__value">
                                                        @if ($partida->rango_minimo_snapshot !== null && $partida->rango_maximo_snapshot !== null)
                                                            {{ $formatMoney($partida->rango_minimo_snapshot, $partida->moneda_precio_snapshot) }} – {{ $formatMoney($partida->rango_maximo_snapshot, $partida->moneda_precio_snapshot) }}
                                                        @else
                                                            Sin registro
                                                        @endif
                                                    </div>
                                                </div>
                                                <div>
                                                    <div class="definition__label">Criterios seleccionados</div>
                                                    <div class="definition__value">
                                                        @forelse ($partida->valoresVariables as $criterio)
                                                            <div>
                                                                {{ str($criterio->variable_codigo)->replace('_', ' ')->title() }}: {{ $criterio->nivel_nombre }} · factor {{ number_format($criterio->factor_aplicado, 4, ',', '.') }} · peso {{ number_format($criterio->peso_aplicado, 4, ',', '.') }}
                                                            </div>
                                                        @empty
                                                            Sin registro
                                                        @endforelse
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </details>
                            @endif
                        </article>
                    @endforeach
                </div>

                <x-slot:footer>
                    <div class="quote-show-summary">
                        <div class="quote-show-summary__row">
                            <span>Subtotal neto</span>
                            <strong>{{ $formatMoney($revisionActual->subtotal) }}</strong>
                        </div>
                        <div class="quote-show-summary__row">
                            <span>IVA ({{ number_format($revisionActual->iva_porcentaje, 0) }}%)</span>
                            <strong>{{ $formatMoney($revisionActual->iva, '') }}</strong>
                        </div>
                        <div class="quote-show-summary__row quote-show-summary__row--total">
                            <span>Total de la propuesta</span>
                            <strong>{{ $formatMoney($revisionActual->total) }}</strong>
                        </div>
                    </div>
                </x-slot:footer>
            </x-ui.panel>

            <x-ui.panel class="quote-show-panel quote-show-panel--services">
                <x-slot:title>
                    <div class="quote-show-panel__titlewrap">
                        <span class="quote-show-panel__icon">
                            <x-ui.icon name="briefcase-business" size="18" />
                        </span>
                        <div>
                            <h2 class="ui-panel__title">Servicios cotizados</h2>
                            <div class="ui-panel__subtitle">Bloques incluidos en esta propuesta comercial</div>
                        </div>
                    </div>
                </x-slot:title>

                <div class="quote-show-service-list">
                    @forelse ($revisionActual->servicios->sortBy('orden') as $servicioCotizado)
                        @php
                            $servicio = $servicioCotizado->servicio;
                            $catalogo = $servicioCotizado->catalogoServicio ?? $servicio?->catalogoServicio;
                        @endphp
                        <article class="quote-show-service-card">
                            <div class="quote-show-service-card__header">
                                <div class="quote-show-service-card__identity">
                                    <span class="quote-show-service-card__icon">
                                        <x-ui.icon name="briefcase-business" size="17" />
                                    </span>
                                    <div>
                                        <span class="quote-show-service-card__eyebrow">{{ $catalogo?->codigo ?? 'Servicio cotizado' }}</span>
                                        <h3>{{ $servicioCotizado->titulo }}</h3>
                                    </div>
                                </div>
                                @if ($servicio)
                                    <x-ui.badge status="activo" />
                                @else
                                    <span class="ui-badge ui-badge--warning">Pendiente de creación</span>
                                @endif
                            </div>
                            <div class="quote-show-service-card__grid">
                                <div>
                                    <span>Tipo</span>
                                    <strong>{{ $catalogo?->tipo?->nombre ?? $servicio?->tipo?->nombre ?? 'Sin clasificar' }}</strong>
                                </div>
                                <div>
                                    <span>Catálogo</span>
                                    <strong>{{ $catalogo?->nombre ?? 'Sin vínculo de catálogo' }}</strong>
                                </div>
                                <div>
                                    <span>Descripción conservada</span>
                                    <strong>{{ $servicioCotizado->descripcion ?: 'Sin descripción registrada' }}</strong>
                                </div>
                                <div>
                                    <span>Vínculo operativo</span>
                                    <strong>
                                        @if ($servicio)
                                            <a href="{{ route('servicios.show', $servicio) }}">{{ $servicio->codigo }} · {{ $servicio->nombre }}</a>
                                        @else
                                            Se creará al aceptar la cotización
                                        @endif
                                    </strong>
                                </div>
                            </div>
                        </article>
                    @empty
                        <p class="muted">Sin servicios cotizados</p>
                    @endforelse
                </div>
            </x-ui.panel>
        </div>

        <aside class="stack">
            <x-ui.panel class="quote-show-panel quote-show-panel--issuance">
                <x-slot:title>
                    <div class="quote-show-panel__titlewrap">
                        <span class="quote-show-panel__icon">
                            <x-ui.icon name="file-text" size="18" />
                        </span>
                        <div>
                            <h2 class="ui-panel__title">Datos de emisión</h2>
                            <div class="ui-panel__subtitle">Contexto comercial y control de emisión</div>
                        </div>
                    </div>
                </x-slot:title>

                <div class="quote-show-info-list">
                    <div class="quote-show-info-item">
                        <span class="quote-show-info-item__icon"><x-ui.icon name="building-2" size="16" /></span>
                        <div>
                            <span>Organización</span>
                            <strong><a href="{{ route('clientes.show', $cotizacion->cliente) }}">{{ $cotizacion->cliente->nombre_display }}</a></strong>
                        </div>
                    </div>
                    <div class="quote-show-info-item">
                        <span class="quote-show-info-item__icon"><x-ui.icon name="user-round" size="16" /></span>
                        <div>
                            <span>Encargado</span>
                            <strong><x-ui.contact-summary :contacto="$revisionActual->contacto" :snapshot="$revisionActual->contacto_snapshot" /></strong>
                        </div>
                    </div>
                    <div class="quote-show-info-item">
                        <span class="quote-show-info-item__icon"><x-ui.icon name="banknote" size="16" /></span>
                        <div>
                            <span>Moneda</span>
                            <strong>{{ $revisionActual->moneda }}</strong>
                        </div>
                    </div>
                    <div class="quote-show-info-item">
                        <span class="quote-show-info-item__icon"><x-ui.icon name="file-text" size="16" /></span>
                        <div>
                            <span>Revisión vigente</span>
                            <strong>Revisión {{ str_pad((string) $revisionActual->revision, 2, '0', STR_PAD_LEFT) }}</strong>
                        </div>
                    </div>
                    <div class="quote-show-info-item">
                        <span class="quote-show-info-item__icon"><x-ui.icon name="rotate-ccw" size="16" /></span>
                        <div>
                            <span>Fecha de emisión</span>
                            <strong>{{ $revisionActual->fecha_emision?->format('d/m/Y') ?? 'Borrador sin fecha emitida' }}</strong>
                        </div>
                    </div>
                </div>
            </x-ui.panel>

            @if ($cotizacion->estado === 'aceptada' || $cotizacion->ordenCompra)
                <x-ui.panel class="quote-show-panel quote-show-panel--purchase-order">
                    <x-slot:title>
                        <div class="quote-show-panel__titlewrap">
                            <span class="quote-show-panel__icon">
                                <x-ui.icon name="receipt" size="18" />
                            </span>
                            <div>
                                <h2 class="ui-panel__title">Orden de compra</h2>
                                <div class="ui-panel__subtitle">Respaldo comercial de la cotización aceptada</div>
                            </div>
                        </div>
                    </x-slot:title>

                    @if ($cotizacion->ordenCompra)
                        @php($ordenCompra = $cotizacion->ordenCompra)
                        <div class="quote-purchase-order-summary">
                            <div>
                                <span>Número de OC</span>
                                <strong>{{ $ordenCompra->numero }}</strong>
                            </div>
                            <div>
                                <span>Fecha</span>
                                <strong>{{ $ordenCompra->fecha->format('d/m/Y') }}</strong>
                            </div>
                            <div>
                                <span>Monto informado</span>
                                <strong>{{ $formatMoney($ordenCompra->monto) }}</strong>
                            </div>
                            <div>
                                <span>Estado</span>
                                <strong><x-ui.badge :status="$ordenCompra->estado" tone="info" /></strong>
                            </div>
                            @if ($ordenCompra->observacion)
                                <div class="quote-purchase-order-summary__observation">
                                    <span>Observación</span>
                                    <strong>{{ $ordenCompra->observacion }}</strong>
                                </div>
                            @endif
                        </div>

                        <section class="quote-invoices quote-invoices--summary">
                            <div class="quote-invoices__header">
                                <div>
                                    <h3>Facturas</h3>
                                    <p>Resumen de facturación asociado a esta orden de compra.</p>
                                </div>
                                <x-ui.button
                                    :href="route('facturas.index', ['oc' => $ordenCompra->id])"
                                    variant="secondary"
                                    size="small"
                                >
                                    Gestionar facturas
                                    <x-ui.icon name="arrow" size="14" />
                                </x-ui.button>
                            </div>

                            <div class="quote-invoices__metrics quote-invoices__metrics--summary">
                                <div>
                                    <span>Cantidad de facturas</span>
                                    <strong>{{ number_format($cantidadFacturas, 0, ',', '.') }}</strong>
                                </div>
                                <div>
                                    <span>Neto facturado</span>
                                    <strong>{{ $formatMoney($netoFacturado) }}</strong>
                                </div>
                                <div class="{{ $saldoNetoPorFacturar < 0 ? 'is-over' : '' }}">
                                    <span>Saldo por facturar</span>
                                    <strong>{{ $formatMoney($saldoNetoPorFacturar) }}</strong>
                                </div>
                                <div>
                                    <span>Última factura</span>
                                    <strong>
                                        {{ $ultimaFactura ? $ultimaFactura->folio.' · '.$ultimaFactura->fecha_emision->format('d/m/Y') : 'Sin facturas' }}
                                    </strong>
                                </div>
                            </div>

                            <div class="quote-invoices__progress">
                                <div>
                                    <span>Avance de facturación</span>
                                    <strong>{{ number_format($avanceFacturacion, 0, ',', '.') }}%</strong>
                                </div>
                                <span class="quote-invoices__progress-track">
                                    <i style="width: {{ min($avanceFacturacion, 100) }}%"></i>
                                </span>
                            </div>

                            @if ($saldoNetoPorFacturar < 0)
                                <div class="quote-invoices__warning" role="alert">
                                    El neto facturado sobrepasa el monto de la OC en {{ $formatMoney(abs($saldoNetoPorFacturar)) }}.
                                </div>
                            @endif
                        </section>
                    @else
                        <details
                            class="quote-purchase-order-form"
                            @if ($errors->hasAny(['numero', 'fecha', 'monto', 'observacion', 'cotizacion'])) open @endif
                        >
                            <summary>Registrar OC</summary>
                            <form method="POST" action="{{ route('cotizaciones.orden-compra.store', $cotizacion) }}" class="stack">
                                @csrf

                                @error('cotizacion')
                                    <span class="ui-field__error">{{ $message }}</span>
                                @enderror

                                <x-ui.field name="numero" label="Número de OC" required>
                                    <x-ui.input name="numero" required maxlength="100" />
                                </x-ui.field>

                                <x-ui.field name="fecha" label="Fecha de OC" required>
                                    <x-ui.input name="fecha" type="date" required />
                                </x-ui.field>

                                <x-ui.field
                                    name="monto"
                                    label="Monto de OC"
                                    hint="Registra el monto real informado; puede diferir del total cotizado."
                                    required
                                >
                                    <x-ui.input
                                        name="monto"
                                        type="number"
                                        :value="$revisionActual->total"
                                        min="0.01"
                                        step="0.01"
                                        required
                                    />
                                    <span class="quote-purchase-order-form__quoted-total">
                                        Total cotización: {{ $formatMoney($revisionActual->total) }}
                                    </span>
                                </x-ui.field>

                                <details
                                    class="quote-purchase-order-form__observation"
                                    @if ($errors->has('observacion') || filled(old('observacion'))) open @endif
                                >
                                    <summary>
                                        <span aria-hidden="true">+</span>
                                        Añadir observación
                                    </summary>
                                    <x-ui.field name="observacion" label="Observación" hint="Opcional">
                                        <x-ui.textarea name="observacion" rows="3" maxlength="5000" />
                                    </x-ui.field>
                                </details>

                                <div class="quote-purchase-order-form__state">
                                    <span>Estado inicial</span>
                                    <x-ui.badge status="registrada" tone="info" />
                                </div>

                                <x-ui.button type="submit">
                                    <x-ui.icon name="receipt" size="16" />
                                    Registrar OC
                                </x-ui.button>
                            </form>
                        </details>
                    @endif
                </x-ui.panel>
            @endif

            <x-ui.panel class="quote-show-panel quote-show-panel--history">
                <x-slot:title>
                    <div class="quote-show-panel__titlewrap">
                        <span class="quote-show-panel__icon">
                            <x-ui.icon name="rotate-ccw" size="18" />
                        </span>
                        <div>
                            <h2 class="ui-panel__title">Historial de revisiones</h2>
                            <div class="ui-panel__subtitle">Trazabilidad de cambios sobre la propuesta</div>
                        </div>
                    </div>
                </x-slot:title>

                <div class="quote-show-history">
                    @foreach ($cotizacion->revisiones->sortByDesc('revision') as $item)
                        <article class="quote-show-history__item {{ $item->id === $revisionActual->id ? 'is-current' : '' }}">
                            <span class="quote-show-history__dot"></span>
                            <div class="quote-show-history__content">
                                <div class="quote-show-history__head">
                                    <strong>Revisión {{ str_pad((string) $item->revision, 2, '0', STR_PAD_LEFT) }}</strong>
                                    <x-ui.badge :status="$item->estado" />
                                </div>
                                <div class="table-secondary">
                                    {{ $item->created_at->format('d/m/Y H:i') }}
                                    @if ($item->id === $revisionActual->id)
                                        · actual
                                    @endif
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </x-ui.panel>
        </aside>
    </div>
    </div>
@endsection
