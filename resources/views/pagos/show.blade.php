@extends('layouts.app')
@section('title', 'Pago '.$pago->codigo)
@section('content')
    @php
        $formatMoney = static fn ($amount, $currency = 'CLP') => $currency.' $'.number_format((float) $amount, 0, ',', '.');
    @endphp

    <div class="payment-show-page">
        <x-ui.page-header eyebrow="Comercial · Pagos" :title="'Pago '.$pago->codigo" description="Detalle del ingreso y su distribución entre facturas.">
            <x-slot:actions>
                <x-ui.button :href="route('pagos.index')" variant="outline">Volver a pagos</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        <div class="payment-show-layout">
            <x-ui.panel class="payment-show-main">
                <x-slot:title>
                    <div class="payment-panel-heading">
                        <span><x-ui.icon name="banknote" size="19" /></span>
                        <div>
                            <h2 class="ui-panel__title">{{ $pago->codigo }}</h2>
                            <div class="ui-panel__subtitle">Registrado el {{ $pago->fecha_pago->format('d/m/Y') }}</div>
                        </div>
                    </div>
                </x-slot:title>
                <dl class="payment-detail-grid">
                    <div><dt>Fecha de pago</dt><dd>{{ $pago->fecha_pago->translatedFormat('d M Y') }}</dd></div>
                    <div><dt>Organización</dt><dd>{{ $pago->cliente->nombre_display }}</dd></div>
                    <div><dt>Monto total</dt><dd class="payment-money">{{ $formatMoney($pago->monto_total, $pago->moneda) }}</dd></div>
                    <div>
                        <dt>Estado documental</dt>
                        <dd>
                            <x-ui.badge
                                :status="$pago->estado"
                                :tone="$pago->estado === 'anulado' ? 'danger' : 'success'"
                            />
                        </dd>
                    </div>
                    <div><dt>Medio de pago</dt><dd>{{ $medioPago }}</dd></div>
                    <div><dt>Referencia</dt><dd>{{ $pago->referencia ?: 'Sin referencia' }}</dd></div>
                    <div class="payment-detail-grid__wide"><dt>Observación</dt><dd>{{ $pago->observacion ?: 'Sin observación' }}</dd></div>
                    @if ($pago->estado === 'anulado')
                        <div class="payment-detail-grid__wide">
                            <dt>Motivo de anulación</dt>
                            <dd>{{ data_get($eventoAnulacion?->cambios, 'motivo', 'Sin motivo registrado') }}</dd>
                        </div>
                        <div>
                            <dt>Anulado el</dt>
                            <dd>{{ $eventoAnulacion?->created_at?->format('d/m/Y H:i') ?? 'Sin fecha registrada' }}</dd>
                        </div>
                    @endif
                </dl>
            </x-ui.panel>

            <x-ui.panel class="payment-show-assignments">
                <x-slot:title>
                    <div class="payment-panel-heading">
                        <span><x-ui.icon name="receipt" size="19" /></span>
                        <h2 class="ui-panel__title">Facturas asociadas</h2>
                    </div>
                </x-slot:title>
                @if ($pago->estado === 'anulado')
                    <p class="muted">Estas asignaciones se conservan como trazabilidad histórica y no cuentan como dinero actualmente cobrado.</p>
                @endif
                <div class="payment-assignment-list">
                    @foreach ($pago->facturas as $factura)
                        <div>
                            <span><strong>{{ $factura->folio }}</strong><small>{{ $factura->cliente->nombre_display }}</small></span>
                            <span>
                                <strong class="payment-money">{{ $formatMoney($factura->pivot->monto_asignado, $factura->moneda) }}</strong>
                                <x-ui.button :href="route('facturas.show', $factura)" variant="ghost" size="small">Ver factura</x-ui.button>
                            </span>
                        </div>
                    @endforeach
                </div>
            </x-ui.panel>

            @if ($pago->estado === 'registrado')
                <x-ui.panel id="anular-pago" class="payment-show-assignments">
                    <x-slot:title><h2 class="ui-panel__title">Anular pago</h2></x-slot:title>

                    <form
                        method="POST"
                        action="{{ route('pagos.anular', $pago) }}"
                        class="stack"
                        onsubmit="return confirm('¿Confirmas la anulación de este pago? Sus asignaciones se conservarán como trazabilidad histórica.')"
                    >
                        @csrf
                        @method('PATCH')

                        @error('pago')
                            <span class="ui-field__error">{{ $message }}</span>
                        @enderror

                        <x-ui.field
                            name="motivo"
                            label="Motivo de anulación"
                            hint="Obligatorio. Quedará registrado en el historial del pago."
                            required
                        >
                            <x-ui.textarea name="motivo" rows="4" maxlength="1000" required />
                        </x-ui.field>

                        <x-ui.checkbox
                            name="confirmacion"
                            label="Confirmo que deseo anular este pago"
                            hint="El pago dejará de contar como dinero cobrado sin eliminar sus asignaciones."
                            required
                        />
                        @error('confirmacion')
                            <span class="ui-field__error">{{ $message }}</span>
                        @enderror

                        <x-ui.button type="submit" variant="danger">Anular pago</x-ui.button>
                    </form>
                </x-ui.panel>
            @endif
        </div>
    </div>
@endsection
