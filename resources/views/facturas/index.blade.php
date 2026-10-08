@extends('layouts.app')
@section('title', 'Facturas')
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
        $hayFiltros = $buscar || $clienteId || $plantaId || $estado || $periodo || $ordenCompraFiltro;
    @endphp

    <div class="invoice-index-page">
        <x-ui.page-header
            eyebrow="Comercial"
            title="Facturas"
            description="Emisión y avance de facturación sobre órdenes de compra registradas."
        >
            <x-slot:actions>
                <x-ui.button :href="route('facturas.create', $ordenCompraFiltro ? ['oc' => $ordenCompraFiltro->id] : [])">
                    <x-ui.icon name="plus" />
                    Registrar factura
                </x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        <div class="invoice-overview">
            <div class="metrics metrics--four invoice-overview__metrics">
                <x-ui.metric
                    label="Facturado este mes"
                    :value="$formatMoney($metricas['facturado_mes'])"
                    detail="Monto neto emitido"
                    tone="naranjo"
                    icon="banknote"
                />
                <x-ui.metric
                    label="Saldo por facturar"
                    :value="$formatMoney($metricas['saldo_por_facturar'])"
                    detail="Saldo neto pendiente de las OC"
                    tone="petroleo"
                    icon="calculator"
                />
                <x-ui.metric
                    label="Facturas emitidas este mes"
                    :value="number_format($metricas['emitidas_mes'], 0, ',', '.')"
                    detail="Documentos registrados en el período"
                    icon="receipt"
                />
                <x-ui.metric
                    label="OC con facturación parcial"
                    :value="number_format($metricas['oc_parciales'], 0, ',', '.')"
                    detail="Con saldo neto aún disponible"
                    tone="petroleo"
                    icon="file-text"
                />
            </div>

            <x-ui.panel class="invoice-chart-panel">
                <x-slot:title>
                    <h2 class="ui-panel__title">Facturación mensual</h2>
                </x-slot:title>
                <x-slot:subtitle>Monto neto facturado · últimos 12 meses</x-slot:subtitle>

                <div class="invoice-chart" role="img" aria-label="Facturación neta mensual de los últimos 12 meses">
                    @foreach ($grafico as $mes)
                        <div
                            class="invoice-chart__month"
                            title="{{ $mes['etiqueta'] }} · {{ $formatMoney($mes['monto']) }}"
                            aria-label="{{ $mes['etiqueta'] }}: {{ $formatMoney($mes['monto']) }}"
                        >
                            <span class="invoice-chart__amount">{{ $formatCompact($mes['monto']) }}</span>
                            <div class="invoice-chart__track">
                                <span
                                    class="invoice-chart__bar"
                                    style="height: {{ $mes['porcentaje'] }}%"
                                ></span>
                            </div>
                            <span class="invoice-chart__label">{{ $mes['etiqueta'] }}</span>
                        </div>
                    @endforeach
                </div>
            </x-ui.panel>
        </div>

        @if ($ordenCompraFiltro)
            <div class="invoice-oc-filter">
                <div>
                    <span>Vista filtrada por OC</span>
                    <strong>{{ $ordenCompraFiltro->numero }}</strong>
                    <small>{{ $ordenCompraFiltro->cliente->nombre_display }}</small>
                </div>
                <x-ui.button :href="route('facturas.index')" variant="ghost" size="small">
                    Ver todas
                </x-ui.button>
            </div>
        @endif

        <form class="toolbar toolbar--stacked invoice-index-toolbar" method="GET" data-filter-form>
            @if ($ordenCompraFiltro)
                <input type="hidden" name="oc" value="{{ $ordenCompraFiltro->id }}" />
            @endif
            <div class="toolbar__primary">
                <div class="toolbar__search">
                    <x-ui.icon name="search" />
                    <input
                        class="ui-control"
                        type="search"
                        name="buscar"
                        value="{{ $buscar }}"
                        placeholder="Buscar folio, OC u organización"
                    />
                </div>
                <x-ui.button type="submit" variant="secondary" size="small">
                    <x-ui.icon name="search" size="15" />
                    Buscar
                </x-ui.button>
            </div>
            <div class="toolbar__filters">
                <div class="toolbar__filter">
                    <label for="cliente">
                        <x-ui.icon name="building-2" size="14" />
                        Organización
                    </label>
                    <x-ui.select name="cliente" data-filter-client data-auto-submit>
                        <option value="">Todas las organizaciones</option>
                        @foreach ($clientesFiltro as $cliente)
                            <option value="{{ $cliente->id }}" @selected($clienteId === $cliente->id)>
                                {{ $cliente->nombre_display }}
                            </option>
                        @endforeach
                    </x-ui.select>
                </div>
                <div class="toolbar__filter">
                    <label for="planta">
                        <x-ui.icon name="factory" size="14" />
                        Planta
                    </label>
                    <x-ui.select name="planta" data-filter-plant data-auto-submit>
                        <option value="">Todas las plantas</option>
                        @foreach ($plantasFiltro as $planta)
                            <option value="{{ $planta->id }}" data-client="{{ $planta->cliente_id }}" @selected($plantaId === $planta->id)>
                                {{ $planta->nombre }}
                            </option>
                        @endforeach
                    </x-ui.select>
                </div>
                <div class="toolbar__filter">
                    <label for="estado">
                        <x-ui.icon name="sliders-horizontal" size="14" />
                        Estado
                    </label>
                    <x-ui.select name="estado" data-auto-submit>
                        <option value="">Todos los estados</option>
                        @foreach ($estados as $valor => $etiqueta)
                            <option value="{{ $valor }}" @selected($estado === $valor)>{{ $etiqueta }}</option>
                        @endforeach
                    </x-ui.select>
                </div>
                <div class="toolbar__filter">
                    <label for="periodo">
                        <x-ui.icon name="calendar-days" size="14" />
                        Período
                    </label>
                    <x-ui.select name="periodo" data-auto-submit>
                        <option value="">Todo el historial</option>
                        <option value="este_mes" @selected($periodo === 'este_mes')>Este mes</option>
                        <option value="mes_anterior" @selected($periodo === 'mes_anterior')>Mes anterior</option>
                        <option value="este_ano" @selected($periodo === 'este_ano')>Este año</option>
                    </x-ui.select>
                </div>
            </div>
            @if ($hayFiltros)
                <x-ui.button
                    :href="route('facturas.index')"
                    variant="danger"
                    size="small"
                    class="toolbar__clear"
                >
                    Limpiar búsqueda y filtros
                </x-ui.button>
            @endif
        </form>

        <x-ui.panel flush class="invoice-index-panel">
            @if ($facturas->isEmpty())
                <x-ui.empty-state
                    icon="receipt"
                    :title="$hayFiltros ? 'No hay coincidencias' : 'No hay facturas registradas'"
                    :description="$hayFiltros ? 'Prueba modificando la búsqueda o los filtros aplicados.' : 'Registra la primera factura sobre una orden de compra.'"
                >
                    <x-slot:action>
                        <x-ui.button :href="route('facturas.create', $ordenCompraFiltro ? ['oc' => $ordenCompraFiltro->id] : [])" size="small">
                            Registrar factura
                        </x-ui.button>
                    </x-slot:action>
                </x-ui.empty-state>
            @else
                <div class="ui-table-wrap">
                    <table class="ui-table invoice-index-table">
                        <thead>
                            <tr>
                                <th>Factura</th>
                                <th>Organización</th>
                                <th>OC</th>
                                <th>Emisión</th>
                                <th>Neto</th>
                                <th>Total</th>
                                <th>Estado</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($facturas as $factura)
                                @php
                                    $avance = (float) $factura->ordenCompra->monto > 0
                                        ? ((float) ($factura->ordenCompra->neto_facturado ?? 0) / (float) $factura->ordenCompra->monto) * 100
                                        : 0;
                                @endphp
                                <tr>
                                    <td>
                                        <span class="table-primary">{{ $factura->folio }}</span>
                                        @if ($factura->codigo ?? null)
                                            <span class="table-secondary">{{ $factura->codigo }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="table-primary">{{ $factura->cliente->nombre_display }}</span>
                                        <span class="table-secondary"><x-ui.icon name="factory" size="13" /> {{ $factura->ordenCompra->cotizacion->planta?->nombre ?? 'Planta por asignar' }}</span>
                                    </td>
                                    <td>
                                        <span class="table-primary">{{ $factura->ordenCompra->numero }}</span>
                                        <div class="invoice-progress invoice-progress--compact">
                                            <span><i style="width: {{ min($avance, 100) }}%"></i></span>
                                            <small>{{ number_format($avance, 0, ',', '.') }}%</small>
                                        </div>
                                    </td>
                                    <td>{{ $factura->fecha_emision->format('d/m/Y') }}</td>
                                    <td class="numeric invoice-money">{{ $formatMoney($factura->monto_neto, $factura->moneda) }}</td>
                                    <td class="numeric invoice-money">{{ $formatMoney($factura->total, $factura->moneda) }}</td>
                                    <td><x-ui.badge :status="$factura->estado" tone="info" /></td>
                                    <td>
                                        <x-ui.button :href="route('facturas.show', $factura)" variant="ghost" size="small">
                                            Ver
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
