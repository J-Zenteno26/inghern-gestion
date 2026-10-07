@extends('layouts.app')
@section('title', 'Factura '.$factura->folio)
@section('content')
    @php
        $formatMoney = static fn ($amount) => $factura->moneda.' $'.number_format((float) $amount, 0, ',', '.');
        $condicionPago = match ($factura->condicion_pago) {
            'credito' => 'Crédito',
            'contado' => 'Al contado',
            default => 'Sin registrar',
        };
        $estadoVisual = match (true) {
            $factura->estado === 'anulada' => 'anulada',
            $factura->fecha_pago_informada_cliente !== null => 'con_fecha_de_pago',
            default => 'emitida',
        };
        $tonoEstado = match ($estadoVisual) {
            'anulada' => 'danger',
            default => 'info',
        };
    @endphp

    <div class="invoice-show-page">
        <x-ui.page-header
            eyebrow="Comercial · Facturas"
            :title="'Factura '.$factura->folio"
            description="Detalle comercial del documento emitido."
        >
            <x-slot:actions>
                <x-ui.button :href="route('facturas.index')" variant="outline">Volver a facturas</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        <div class="invoice-show-layout">
            <x-ui.panel class="invoice-show-main">
                <x-slot:title>
                    <div class="invoice-show-title">
                        <span><x-ui.icon name="receipt" size="19" /></span>
                        <div>
                            <h2 class="ui-panel__title">{{ $factura->folio }}</h2>
                            <div class="ui-panel__subtitle">Emitida el {{ $factura->fecha_emision->format('d/m/Y') }}</div>
                        </div>
                    </div>
                </x-slot:title>
                <x-slot:actions><x-ui.badge :status="$estadoVisual" :tone="$tonoEstado" /></x-slot:actions>

                <details
                    class="invoice-payment-date {{ $factura->fecha_pago_informada_cliente ? 'invoice-payment-date--registered' : '' }}"
                    @if ($errors->has('fecha_pago_informada_cliente')) open @endif
                >
                    <summary class="invoice-payment-date__summary">
                        <span class="invoice-payment-date__milestone">
                            <span class="invoice-payment-date__icon">
                                <x-ui.icon name="calendar-days" size="18" />
                            </span>
                            <span>
                                @if ($factura->fecha_pago_informada_cliente)
                                    <small>Fecha de pago informada</small>
                                    <strong>{{ $factura->fecha_pago_informada_cliente->translatedFormat('d M Y') }}</strong>
                                @else
                                    <strong>Registrar fecha de pago</strong>
                                    <small>Informada manualmente por el cliente</small>
                                @endif
                            </span>
                        </span>
                        <span class="invoice-payment-date__action">
                            {{ $factura->fecha_pago_informada_cliente ? 'Editar fecha' : 'Registrar fecha' }}
                            <x-ui.icon name="chevron-down" size="16" />
                        </span>
                    </summary>

                    <form
                        method="POST"
                        action="{{ route('facturas.fecha-pago-informada.update', $factura) }}"
                        class="invoice-payment-date__form"
                    >
                        @csrf
                        @method('PATCH')
                        <x-ui.field
                            name="fecha_pago_informada_cliente"
                            label="Fecha de pago informada por cliente"
                            required
                        >
                            <x-ui.input
                                name="fecha_pago_informada_cliente"
                                type="date"
                                :value="$factura->fecha_pago_informada_cliente?->toDateString()"
                                required
                            />
                        </x-ui.field>
                        <div class="invoice-payment-date__actions">
                            <x-ui.button :href="route('facturas.show', $factura)" variant="ghost" size="small">
                                Cancelar
                            </x-ui.button>
                            <x-ui.button type="submit" size="small">
                                <x-ui.icon name="calendar-days" size="15" />
                                Guardar fecha
                            </x-ui.button>
                        </div>
                    </form>
                </details>

                <dl class="invoice-detail-grid">
                    <div><dt>Organización</dt><dd>{{ $factura->cliente->nombre_display }}</dd></div>
                    <div><dt>Orden de compra</dt><dd>{{ $factura->ordenCompra->numero }}</dd></div>
                    <div><dt>Fecha de emisión</dt><dd>{{ $factura->fecha_emision->format('d/m/Y') }}</dd></div>
                    <div><dt>Moneda</dt><dd>{{ $factura->moneda }}</dd></div>
                    <div>
                        <dt>Condición de pago</dt>
                        <dd>
                            {{ $condicionPago }}
                            @if ($factura->condicion_pago === 'credito' && $factura->dias_pago)
                                · {{ $factura->dias_pago }} días
                            @endif
                        </dd>
                    </div>
                    <div><dt>Neto</dt><dd class="invoice-money">{{ $formatMoney($factura->monto_neto) }}</dd></div>
                    <div><dt>IVA ({{ number_format((float) $factura->iva_porcentaje, 2, ',', '.') }}%)</dt><dd>{{ $formatMoney($factura->iva) }}</dd></div>
                    <div class="invoice-detail-grid__total"><dt>Total</dt><dd>{{ $formatMoney($factura->total) }}</dd></div>
                    <div class="invoice-detail-grid__wide"><dt>Observación</dt><dd>{{ $factura->observacion ?: 'Sin observación' }}</dd></div>
                </dl>
            </x-ui.panel>

            <x-ui.panel class="invoice-show-links">
                <x-slot:title><h2 class="ui-panel__title">Origen comercial</h2></x-slot:title>
                <div class="invoice-origin">
                    <div><span>OC</span><strong>{{ $factura->ordenCompra->numero }}</strong></div>
                    <div><span>Cotización</span><strong>{{ $factura->ordenCompra->cotizacion->codigo }}</strong></div>
                    <x-ui.button :href="route('cotizaciones.show', $factura->ordenCompra->cotizacion)" variant="secondary">
                        Ver OC y cotización
                        <x-ui.icon name="arrow" size="15" />
                    </x-ui.button>
                </div>
            </x-ui.panel>
        </div>
    </div>
@endsection
