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
        $totalPagado = $factura->totalPagado();
        $saldoPago = $factura->saldoPago();
        $estadoPago = $factura->estadoPago();
        $tonoEstadoDocumental = match ($factura->estado) {
            'anulada' => 'danger',
            default => 'info',
        };
        $tonoEstadoPago = match ($estadoPago) {
            'pagada' => 'success',
            'pago_parcial' => 'info',
            'sobrepagada' => 'warning',
            'anulada' => 'danger',
            default => 'neutral',
        };
    @endphp

    <div class="invoice-show-page">
        <x-ui.page-header
            eyebrow="Comercial · Facturas"
            :title="'Factura '.$factura->folio"
            description="Detalle comercial del documento emitido."
        >
            <x-slot:actions>
                @if ($factura->estado !== 'anulada')
                    @if ($factura->pagos_count > 0)
                        <x-ui.button
                            variant="danger"
                            disabled
                            aria-disabled="true"
                            title="La factura tiene pagos asignados"
                        >
                            Anular factura
                        </x-ui.button>
                    @else
                        <x-ui.button href="#anular-factura" variant="danger">Anular factura</x-ui.button>
                    @endif
                @endif
                <x-ui.button :href="route('facturas.index')" variant="outline">Volver a facturas</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        @error('factura')
            <div class="flash flash--error" role="alert">{{ $message }}</div>
        @enderror

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
                <x-slot:actions><x-ui.badge :status="$factura->estado" :tone="$tonoEstadoDocumental" /></x-slot:actions>

                @if ($factura->estado !== 'anulada')
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
                @endif

                <dl class="invoice-detail-grid">
                    <div><dt>Organización</dt><dd>{{ $factura->cliente->nombre_display }}</dd></div>
                    <div><dt>Orden de compra</dt><dd>{{ $factura->ordenCompra->numero }}</dd></div>
                    <div><dt>Fecha de emisión</dt><dd>{{ $factura->fecha_emision->format('d/m/Y') }}</dd></div>
                    <div><dt>Moneda</dt><dd>{{ $factura->moneda }}</dd></div>
                    <div><dt>Estado documental</dt><dd><x-ui.badge :status="$factura->estado" :tone="$tonoEstadoDocumental" /></dd></div>
                    <div><dt>Estado de pago</dt><dd><x-ui.badge :status="$estadoPago" :tone="$tonoEstadoPago" /></dd></div>
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
                    <div class="invoice-detail-grid__total"><dt>Total factura</dt><dd>{{ $formatMoney($factura->total) }}</dd></div>
                    <div><dt>Total pagado</dt><dd class="invoice-money">{{ $formatMoney($totalPagado) }}</dd></div>
                    <div><dt>Saldo pendiente</dt><dd class="invoice-money">{{ $formatMoney($saldoPago) }}</dd></div>
                    <div><dt>Fecha de pago informada por cliente</dt><dd>{{ $factura->fecha_pago_informada_cliente?->format('d/m/Y') ?? 'Sin informar' }}</dd></div>
                    @if ($factura->estado === 'anulada')
                        <div class="invoice-detail-grid__wide">
                            <dt>Motivo de anulación</dt>
                            <dd>{{ data_get($eventoAnulacion?->cambios, 'motivo', 'Sin motivo registrado') }}</dd>
                        </div>
                        <div>
                            <dt>Anulada el</dt>
                            <dd>{{ $eventoAnulacion?->created_at?->format('d/m/Y H:i') ?? 'Sin fecha registrada' }}</dd>
                        </div>
                    @endif
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

            @if ($factura->estado !== 'anulada')
                <x-ui.panel id="anular-factura" class="invoice-show-links">
                    <x-slot:title><h2 class="ui-panel__title">Anular factura</h2></x-slot:title>

                    @if ($factura->pagos_count > 0)
                        <div class="stack">
                            <p class="muted">
                                Esta factura tiene {{ number_format($factura->pagos_count, 0, ',', '.') }} pago{{ $factura->pagos_count === 1 ? '' : 's' }} asignado{{ $factura->pagos_count === 1 ? '' : 's' }}.
                                Primero debes resolver o reasignar esos pagos antes de anularla.
                            </p>
                            <x-ui.button variant="danger" disabled aria-disabled="true">Anulación bloqueada</x-ui.button>
                        </div>
                    @else
                        <form
                            method="POST"
                            action="{{ route('facturas.anular', $factura) }}"
                            class="stack"
                            onsubmit="return confirm('¿Confirmas la anulación de esta factura? Esta acción conservará el documento y su trazabilidad.')"
                        >
                            @csrf
                            @method('PATCH')

                            @error('factura')
                                <span class="ui-field__error">{{ $message }}</span>
                            @enderror

                            <x-ui.field
                                name="motivo"
                                label="Motivo de anulación"
                                hint="Obligatorio. Quedará registrado en el historial de la factura."
                                required
                            >
                                <x-ui.textarea name="motivo" rows="4" maxlength="1000" required />
                            </x-ui.field>

                            <x-ui.checkbox
                                name="confirmacion"
                                label="Confirmo que deseo anular esta factura"
                                hint="La factura dejará de contar en la OC y no tendrá saldo pendiente de cobro."
                                required
                            />
                            @error('confirmacion')
                                <span class="ui-field__error">{{ $message }}</span>
                            @enderror

                            <x-ui.button type="submit" variant="danger">Confirmar anulación</x-ui.button>
                        </form>
                    @endif
                </x-ui.panel>
            @endif
        </div>
    </div>
@endsection
