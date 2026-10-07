@extends('layouts.app')
@section('title', 'Pago '.$pago->codigo)
@section('content')
    @php
        $formatMoney = static fn ($amount, $currency = 'CLP') => $currency.' $'.number_format((float) $amount, 0, ',', '.');
        $moneda = $pago->facturas->first()?->moneda ?? 'CLP';
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
                    <div><dt>Monto total</dt><dd class="payment-money">{{ $formatMoney($pago->monto_total, $moneda) }}</dd></div>
                    <div><dt>Medio de pago</dt><dd>{{ $medioPago }}</dd></div>
                    <div><dt>Referencia</dt><dd>{{ $pago->referencia ?: 'Sin referencia' }}</dd></div>
                    <div class="payment-detail-grid__wide"><dt>Observación</dt><dd>{{ $pago->observacion ?: 'Sin observación' }}</dd></div>
                </dl>
            </x-ui.panel>

            <x-ui.panel class="payment-show-assignments">
                <x-slot:title>
                    <div class="payment-panel-heading">
                        <span><x-ui.icon name="receipt" size="19" /></span>
                        <h2 class="ui-panel__title">Facturas asociadas</h2>
                    </div>
                </x-slot:title>
                <div class="payment-assignment-list">
                    @foreach ($pago->facturas as $factura)
                        <div>
                            <span><strong>{{ $factura->folio }}</strong><small>{{ $factura->cliente->nombre_display }}</small></span>
                            <strong class="payment-money">{{ $formatMoney($factura->pivot->monto_asignado, $factura->moneda) }}</strong>
                        </div>
                    @endforeach
                </div>
            </x-ui.panel>
        </div>
    </div>
@endsection
