@extends('layouts.app')
@section('title', 'Pagos')
@section('content')
    @php
        $formatMoney = static fn ($amount, $currency = 'CLP') => $currency.' $'.number_format((float) $amount, 0, ',', '.');
        $formatCompact = static function ($amount): string {
            $amount = (float) $amount;

            return match (true) {
                abs($amount) >= 1_000_000_000 => '$'.number_format($amount / 1_000_000_000, 1, ',', '').'B',
                abs($amount) >= 1_000_000 => '$'.number_format($amount / 1_000_000, 1, ',', '').'M',
                abs($amount) >= 1_000 => '$'.number_format($amount / 1_000, 0, ',', '').'K',
                default => '$'.number_format($amount, 0, ',', '.'),
            };
        };
        $toneForStatus = static fn (string $status) => match ($status) {
            'pagada' => 'success',
            'pago_parcial' => 'info',
            'sobrepagada' => 'warning',
            'anulada' => 'danger',
            default => 'neutral',
        };
    @endphp

    <div class="payment-index-page">
        <x-ui.page-header
            eyebrow="Comercial"
            title="Pagos"
            description="Seguimiento de cobros asociados a facturas emitidas."
        >
            <x-slot:actions>
                <x-ui.button :href="route('pagos.create')">
                    <x-ui.icon name="plus" size="16" />
                    Registrar pago
                </x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        <div class="payment-overview">
            <div class="metrics metrics--four payment-overview__metrics">
                <x-ui.metric
                    label="Cobrado este mes"
                    :value="$formatMoney($metricas['cobrado_mes'])"
                    detail="Dinero efectivamente recibido"
                    tone="naranjo"
                    icon="banknote"
                />
                <x-ui.metric
                    label="Pendiente de cobro"
                    :value="$formatMoney($metricas['pendiente_cobro'])"
                    detail="Saldo total aún no cubierto"
                    tone="petroleo"
                    icon="calculator"
                />
                <x-ui.metric
                    label="Facturas con pago parcial"
                    :value="number_format($metricas['pagos_parciales'], 0, ',', '.')"
                    detail="Documentos con abonos registrados"
                    icon="receipt"
                />
                <x-ui.metric
                    label="Esperando fecha de pago"
                    :value="number_format($metricas['esperando_fecha'], 0, ',', '.')"
                    detail="El cliente aún no informa fecha"
                    tone="petroleo"
                    icon="calendar-days"
                />
            </div>

            <x-ui.panel class="payment-chart-panel">
                <x-slot:title>
                    <h2 class="ui-panel__title">Cobros mensuales</h2>
                </x-slot:title>
                <x-slot:subtitle>Dinero efectivamente cobrado · últimos 12 meses</x-slot:subtitle>

                <div class="payment-chart" role="img" aria-label="Cobros mensuales de los últimos 12 meses">
                    @foreach ($grafico as $mes)
                        <div
                            class="payment-chart__month"
                            title="{{ $mes['etiqueta'] }} · {{ $formatMoney($mes['monto']) }}"
                            aria-label="{{ $mes['etiqueta'] }}: {{ $formatMoney($mes['monto']) }}"
                        >
                            <span class="payment-chart__amount">{{ $formatCompact($mes['monto']) }}</span>
                            <div class="payment-chart__track">
                                <span class="payment-chart__bar" style="height: {{ $mes['porcentaje'] }}%"></span>
                            </div>
                            <span class="payment-chart__label">{{ $mes['etiqueta'] }}</span>
                        </div>
                    @endforeach
                </div>
            </x-ui.panel>
        </div>

        <div class="payment-insights">
            <x-ui.panel class="payment-tracking-panel">
                <x-slot:title>
                    <div class="payment-panel-heading">
                        <x-ui.icon name="receipt" size="17" />
                        <h2 class="ui-panel__title">Seguimiento de cobro</h2>
                    </div>
                </x-slot:title>
                <x-slot:subtitle>Estado operativo de las facturas activas</x-slot:subtitle>

                <div class="payment-tracking">
                    <div class="payment-tracking__item payment-tracking__item--waiting">
                        <span class="payment-tracking__icon"><x-ui.icon name="clock-3" size="18" /></span>
                        <div class="payment-tracking__content">
                            <span>Esperando fecha</span>
                            <strong>{{ number_format($seguimiento['esperando_fecha'], 0, ',', '.') }}</strong>
                            <small>Sin fecha informada</small>
                        </div>
                    </div>
                    <div class="payment-tracking__item payment-tracking__item--scheduled">
                        <span class="payment-tracking__icon"><x-ui.icon name="calendar-check" size="18" /></span>
                        <div class="payment-tracking__content">
                            <span>Con fecha informada</span>
                            <strong>{{ number_format($seguimiento['con_fecha'], 0, ',', '.') }}</strong>
                            <small>Programadas, aún sin abonos</small>
                        </div>
                    </div>
                    <div class="payment-tracking__item payment-tracking__item--partial">
                        <span class="payment-tracking__icon"><x-ui.icon name="circle-dollar-sign" size="18" /></span>
                        <div class="payment-tracking__content">
                            <span>Pago parcial</span>
                            <strong>{{ number_format($seguimiento['pago_parcial'], 0, ',', '.') }}</strong>
                            <small>Con saldo pendiente</small>
                        </div>
                    </div>
                    <div class="payment-tracking__item payment-tracking__item--success">
                        <span class="payment-tracking__icon"><x-ui.icon name="circle-check" size="18" /></span>
                        <div class="payment-tracking__content">
                            <span>Pagadas</span>
                            <strong>{{ number_format($seguimiento['pagadas'], 0, ',', '.') }}</strong>
                            <small>Cubiertas completamente</small>
                        </div>
                    </div>
                </div>
            </x-ui.panel>

            <x-ui.panel class="payment-upcoming-panel">
                <x-slot:title>
                    <div class="payment-panel-heading">
                        <x-ui.icon name="calendar-days" size="17" />
                        <h2 class="ui-panel__title">Próximos pagos informados</h2>
                    </div>
                </x-slot:title>
                <x-slot:subtitle>Fechas comunicadas por el cliente con saldo pendiente</x-slot:subtitle>

                @if ($proximosPagos->isEmpty())
                    <div class="payment-upcoming__empty">
                        <x-ui.icon name="calendar-days" size="18" />
                        <span>No hay pagos informados próximos.</span>
                    </div>
                @else
                    <div class="payment-upcoming">
                        @foreach ($proximosPagos as $factura)
                            <div class="payment-upcoming__item">
                                <div class="payment-upcoming__date">
                                    <strong>{{ $factura->fecha_pago_informada_cliente->translatedFormat('d') }}</strong>
                                    <span>{{ strtoupper($factura->fecha_pago_informada_cliente->translatedFormat('M')) }}</span>
                                </div>
                                <div class="payment-upcoming__identity">
                                    <strong>{{ $factura->folio }}</strong>
                                    <span>{{ $factura->cliente->nombre_display }}</span>
                                </div>
                                <div class="payment-upcoming__amount">
                                    <span>Saldo</span>
                                    <strong>{{ $formatMoney(max($factura->saldoPago(), 0), $factura->moneda) }}</strong>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-ui.panel>
        </div>

        <x-ui.panel flush class="payment-index-panel">
            @if ($facturas->isEmpty())
                <x-ui.empty-state
                    icon="banknote"
                    title="No hay facturas para gestionar"
                    description="Registra facturas antes de comenzar a ingresar pagos."
                />
            @else
                <div class="ui-table-wrap">
                    <table class="ui-table payment-index-table">
                        <thead>
                            <tr>
                                <th><span class="payment-table-heading"><x-ui.icon name="receipt" size="14" />Factura</span></th>
                                <th><span class="payment-table-heading"><x-ui.icon name="building-2" size="14" />Organización</span></th>
                                <th><span class="payment-table-heading"><x-ui.icon name="calendar-check" size="14" />Fecha informada</span></th>
                                <th class="numeric"><span class="payment-table-heading payment-table-heading--numeric"><x-ui.icon name="banknote" size="14" />Total</span></th>
                                <th class="numeric"><span class="payment-table-heading payment-table-heading--numeric"><x-ui.icon name="circle-dollar-sign" size="14" />Pagado</span></th>
                                <th><span class="payment-table-heading"><x-ui.icon name="chart-no-axes-column-increasing" size="14" />Avance</span></th>
                                <th class="numeric"><span class="payment-table-heading payment-table-heading--numeric"><x-ui.icon name="calculator" size="14" />Saldo</span></th>
                                <th><span class="payment-table-heading"><x-ui.icon name="circle-check" size="14" />Estado</span></th>
                                <th><span class="payment-table-heading"><x-ui.icon name="arrow" size="14" />Acción</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($facturas as $factura)
                                @php
                                    $estadoPago = $factura->estadoPago();
                                    $totalPagado = $factura->totalPagado();
                                    $saldo = $factura->saldoPago();
                                    $avance = (float) $factura->total > 0
                                        ? ($totalPagado / (float) $factura->total) * 100
                                        : 0;
                                @endphp
                                <tr>
                                    <td>
                                        <span class="table-primary">{{ $factura->folio }}</span>
                                        <span class="table-secondary">{{ $factura->fecha_emision->format('d/m/Y') }}</span>
                                    </td>
                                    <td>{{ $factura->cliente->nombre_display }}</td>
                                    <td>
                                        <span class="payment-table-value {{ $factura->fecha_pago_informada_cliente ? 'payment-table-value--scheduled' : 'payment-table-value--waiting' }}">
                                            <x-ui.icon :name="$factura->fecha_pago_informada_cliente ? 'calendar-check' : 'clock-3'" size="15" />
                                            {{ $factura->fecha_pago_informada_cliente?->translatedFormat('d M Y') ?? 'Sin informar' }}
                                        </span>
                                    </td>
                                    <td class="numeric payment-money">{{ $formatMoney($factura->total, $factura->moneda) }}</td>
                                    <td class="numeric">{{ $formatMoney($totalPagado, $factura->moneda) }}</td>
                                    <td>
                                        <div class="payment-progress">
                                            <span><i style="width: {{ min(max($avance, 0), 100) }}%"></i></span>
                                            <small>{{ number_format($avance, 0, ',', '.') }}%</small>
                                        </div>
                                    </td>
                                    <td class="numeric {{ $saldo < 0 ? 'payment-balance--overpaid' : '' }}">
                                        {{ $formatMoney($saldo, $factura->moneda) }}
                                    </td>
                                    <td><x-ui.badge :status="$estadoPago" :tone="$toneForStatus($estadoPago)" /></td>
                                    <td>
                                        <x-ui.button :href="route('pagos.create', ['factura' => $factura->id])" variant="ghost" size="small">
                                            Registrar pago
                                            <x-ui.icon name="arrow" size="15" />
                                        </x-ui.button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="pagination">{{ $facturas->links() }}</div>
            @endif
        </x-ui.panel>
    </div>
@endsection
