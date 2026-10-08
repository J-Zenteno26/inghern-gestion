@extends("layouts.app")
@section("title", "Cotizaciones")
@section("content")
    <div class="quote-index-page">
    <x-ui.page-header
        eyebrow="Comercial"
        title="Cotizaciones"
        description="Propuestas y revisiones con sus decisiones de precio conservadas en el tiempo."
    >
        <x-slot:actions>
            <x-ui.button :href="route('cotizaciones.create')">
                <x-ui.icon name="plus" />
                Nueva cotización
            </x-ui.button>
        </x-slot>
    </x-ui.page-header>
    <div class="metrics metrics--four">
        <x-ui.metric
            label="Total de cotizaciones"
            :value="number_format($metricas['total'], 0, ',', '.')"
            detail="Histórico acumulado"
            icon="receipt"
        />
        <x-ui.metric
            label="Cotizaciones del mes"
            :value="number_format($metricas['mes'], 0, ',', '.')"
            detail="Registradas en el período actual"
            tone="petroleo"
            icon="calendar-days"
        />
        <x-ui.metric
            label="Por validar"
            :value="number_format($metricas['por_validar'], 0, ',', '.')"
            detail="Requieren revisión de datos"
            tone="petroleo"
            icon="file-text"
        />
        <x-ui.metric
            label="Monto cotizado este mes"
            :value="'$'.number_format($metricas['monto_mes'], 0, ',', '.')"
            detail="CLP · revisiones actuales"
            tone="naranjo"
            icon="banknote"
        />
    </div>
    <form class="toolbar toolbar--stacked quote-index-toolbar" method="GET" data-filter-form>
        <input type="hidden" name="orden" value="{{ $orden }}" />
        <input type="hidden" name="direccion" value="{{ $direccion }}" />
        <div class="toolbar__primary">
            <div class="toolbar__search">
                <x-ui.icon name="search" />
                <input
                    class="ui-control"
                    type="search"
                    name="buscar"
                    value="{{ $buscar }}"
                    placeholder="Buscar código, propuesta, cliente o encargado"
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
                        <option
                            value="{{ $cliente->id }}"
                            @selected($clienteId === $cliente->id)
                        >
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
                        <option
                            value="{{ $planta->id }}"
                            data-client="{{ $planta->cliente_id }}"
                            @selected($plantaId === $planta->id)
                        >
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
                        <option value="{{ $valor }}" @selected($estado === $valor)>
                            {{ $etiqueta }}
                        </option>
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
                    <option value="este_mes" @selected($periodo === "este_mes")>
                        Este mes
                    </option>
                    <option
                        value="mes_anterior"
                        @selected($periodo === "mes_anterior")
                    >
                        Mes anterior
                    </option>
                    <option value="este_ano" @selected($periodo === "este_ano")>
                        Este año
                    </option>
                </x-ui.select>
            </div>
        </div>
        @if ($buscar || $clienteId || $plantaId || $estado || $periodo)
            <x-ui.button
                :href="route('cotizaciones.index')"
                variant="danger"
                size="small"
                class="toolbar__clear"
            >
                Limpiar búsqueda y filtros
            </x-ui.button>
        @endif
    </form>
    <x-ui.panel flush class="quote-index-panel">
        @if ($cotizaciones->isEmpty())
            <x-ui.empty-state
                icon="file"
                :title="$buscar || $clienteId || $plantaId || $estado || $periodo ? 'No hay coincidencias' : 'No hay cotizaciones'"
                :description="$buscar || $clienteId || $plantaId || $estado || $periodo ? 'Prueba modificando la búsqueda o los filtros aplicados.' : 'Crea una propuesta y registra los criterios usados para definir cada valor.'"
            >
                <x-slot:action>
                    @if ($buscar || $clienteId || $plantaId || $estado || $periodo)
                        <x-ui.button
                            :href="route('cotizaciones.index')"
                            variant="outline"
                            size="small"
                        >
                            Limpiar filtros
                        </x-ui.button>
                    @else
                        <x-ui.button
                            :href="route('cotizaciones.create')"
                            size="small"
                        >
                            Crear cotización
                        </x-ui.button>
                    @endif
                </x-slot>
            </x-ui.empty-state>
        @else
            <div class="ui-table-wrap">
                <table class="ui-table quote-index-table">
                    <thead>
                        <tr>
                            <th>
                                <x-ui.sort-link
                                    field="propuesta"
                                    label="Propuesta"
                                    :current="$orden"
                                    :direction="$direccion"
                                />
                            </th>
                            <th>Planta / organización</th>
                            <th>Encargado</th>
                            <th>Revisión</th>
                            <th>Total</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($cotizaciones as $cotizacion)
                            <tr>
                                <td class="quote-index-table__proposal-cell">
                                    <span class="quote-index-table__proposal-icon">
                                        <x-ui.icon name="file-text" size="17" />
                                    </span>
                                    <span class="quote-index-table__proposal-copy">
                                        <span class="table-primary">
                                            {{ $cotizacion->codigo }}
                                        </span>
                                        <span class="table-secondary">
                                            {{ $cotizacion->revisionActual?->titulo ?? "Sin título" }}
                                        </span>
                                    </span>
                                </td>
                                <td class="quote-index-table__client">
                                    <x-ui.icon name="factory" size="15" />
                                    <span>
                                        <span class="table-primary">{{ $cotizacion->planta?->nombre ?? 'Planta por asignar' }}</span>
                                        <span class="table-secondary">{{ $cotizacion->cliente->nombre_display }}</span>
                                    </span>
                                </td>
                                <td>
                                    <x-ui.contact-summary
                                        :contacto="$cotizacion->revisionActual?->contacto"
                                        :snapshot="$cotizacion->revisionActual?->contacto_snapshot"
                                    />
                                </td>
                                <td>
                                    <span class="quote-index-table__revision">
                                        R{{ str_pad((string) ($cotizacion->revisionActual?->revision ?? 1), 2, "0", STR_PAD_LEFT) }}
                                    </span>
                                </td>
                                <td class="numeric quote-index-table__total">
                                    {{ $cotizacion->revisionActual?->moneda }}
                                    ${{ number_format($cotizacion->revisionActual?->total ?? 0, 0, ",", ".") }}
                                </td>
                                <td>
                                    <x-ui.badge
                                        :status="$cotizacion->estado"
                                    />
                                </td>
                                <td>
                                    <x-ui.button
                                        :href="route('cotizaciones.show', $cotizacion)"
                                        variant="ghost"
                                        size="small"
                                    >
                                        Abrir
                                        <x-ui.icon name="arrow" size="15" />
                                    </x-ui.button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="pagination">{{ $cotizaciones->links() }}</div>
        @endif
    </x-ui.panel>
    </div>
@endsection
