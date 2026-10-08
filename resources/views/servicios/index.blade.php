@extends("layouts.app")
@section("title", "Servicios")
@section("content")
    @php
        $metricasServicios = [
            "total" => \App\Models\Servicio::query()->count(),
            "en_curso" => \App\Models\Servicio::query()->where("estado", "en_curso")->count(),
            "prospectos" => \App\Models\Servicio::query()->where("estado", "prospecto")->count(),
            "finalizados" => \App\Models\Servicio::query()->where("estado", "finalizado")->count(),
        ];
    @endphp
    <x-ui.page-header
        eyebrow="Operación"
        title="Servicios"
        description="Cada trabajo conserva su cliente, plantas, alcance y vínculo con las propuestas que lo originaron."
    >
        <x-slot:actions>
            <x-ui.button :href="route('servicios.create')" variant="secondary">
                <x-ui.icon name="plus" />
                Nuevo servicio
            </x-ui.button>
        </x-slot>
    </x-ui.page-header>
    <div class="metrics metrics--four">
        <x-ui.metric
            label="Total de servicios"
            :value="number_format($metricasServicios['total'], 0, ',', '.')"
            detail="Histórico operativo acumulado"
        />
        <x-ui.metric
            label="En curso / activos"
            :value="number_format($metricasServicios['en_curso'], 0, ',', '.')"
            detail="Trabajos actualmente en ejecución"
            tone="petroleo"
        />
        <x-ui.metric
            label="Prospectos"
            :value="number_format($metricasServicios['prospectos'], 0, ',', '.')"
            detail="Oportunidades aún por planificar"
            tone="naranjo"
        />
        <x-ui.metric
            label="Finalizados"
            :value="number_format($metricasServicios['finalizados'], 0, ',', '.')"
            detail="Servicios con operación concluida"
            tone="success"
        />
    </div>
    <form class="toolbar toolbar--stacked service-index-toolbar" method="GET" data-filter-form>
        <div class="toolbar__primary">
            <div class="toolbar__search">
                <x-ui.icon name="search" />
                <input
                    class="ui-control"
                    type="search"
                    name="buscar"
                    value="{{ $buscar }}"
                    placeholder="Buscar código, servicio o cliente"
                />
            </div>
            <x-ui.button type="submit" variant="secondary" size="small">
                Buscar
            </x-ui.button>
        </div>
        <div class="toolbar__filters toolbar__filters--compact">
            <div class="toolbar__filter">
                <label for="cliente"><x-ui.icon entity="organizacion" size="14" /> Organización</label>
                <x-ui.select name="cliente" data-client-select data-filter-client data-auto-submit>
                    <option value="">Todas las organizaciones</option>
                    @foreach ($clientesFiltro as $cliente)
                        <option value="{{ $cliente->id }}" @selected($clienteId === $cliente->id)>
                            {{ $cliente->nombre_display }}
                        </option>
                    @endforeach
                </x-ui.select>
            </div>
            <div class="toolbar__filter">
                <label for="planta"><x-ui.icon entity="planta" size="14" /> Planta</label>
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
                <label for="estado"><x-ui.icon name="sliders-horizontal" size="14" /> Estado</label>
                <x-ui.select name="estado" data-auto-submit>
                    <option value="">Todos los estados</option>
                    @foreach (["prospecto", "planificado", "en_curso", "en_pausa", "finalizado", "cancelado"] as $opcion)
                        <option value="{{ $opcion }}" @selected($estado === $opcion)>
                            {{ ucfirst(str_replace("_", " ", $opcion)) }}
                        </option>
                    @endforeach
                </x-ui.select>
            </div>
        </div>
        @if ($buscar || $estado || $clienteId || $plantaId)
            <x-ui.button
                :href="route('servicios.index')"
                variant="danger"
                size="small"
                class="toolbar__clear"
            >
                Limpiar búsqueda y filtros
            </x-ui.button>
        @endif
    </form>
    <x-ui.panel flush class="service-index-panel">
        @if ($servicios->isEmpty())
            <x-ui.empty-state
                icon="briefcase"
                title="No hay servicios para mostrar"
                description="Crea el primer registro operativo o cambia los filtros de búsqueda."
            >
                <x-slot:action>
                    <x-ui.button
                        :href="route('servicios.create')"
                        variant="secondary"
                        size="small"
                    >
                        Crear servicio
                    </x-ui.button>
                </x-slot>
            </x-ui.empty-state>
        @else
            <div class="ui-table-wrap">
                <table class="ui-table service-index-table">
                    <thead>
                        <tr>
                            <th><span class="service-table-heading"><x-ui.icon entity="servicio" size="14" /> Servicio</span></th>
                            <th><span class="service-table-heading"><x-ui.icon entity="planta" size="14" /> Plantas</span></th>
                            <th><span class="service-table-heading"><x-ui.icon entity="organizacion" size="14" /> Organización</span></th>
                            <th><span class="service-table-heading"><x-ui.icon name="calendar-days" size="14" /> Fechas</span></th>
                            <th><span class="service-table-heading"><x-ui.icon name="sliders-horizontal" size="14" /> Estado</span></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($servicios as $servicio)
                            <tr>
                                <td>
                                    <div class="service-index-table__identity">
                                        <span class="service-index-table__icon"><x-ui.icon entity="servicio" size="16" /></span>
                                        <span class="service-index-table__copy">
                                            <span class="table-primary">{{ $servicio->nombre }}</span>
                                            <span class="table-secondary">
                                                {{ $servicio->codigo }} ·
                                                {{ $servicio->tipo?->nombre ?? "Sin clasificar" }} ·
                                                {{ $servicio->catalogoServicio?->nombre ?? $servicio->servicio_otro ?? "Pendiente de normalización" }}
                                            </span>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <span class="table-primary">{{ $servicio->plantas->pluck("nombre")->join(", ") ?: "Por definir" }}</span>
                                </td>
                                <td>
                                    <span class="table-secondary">{{ $servicio->cliente->nombre_display }}</span>
                                </td>
                                <td>
                                    <span class="table-primary">
                                        {{ $servicio->fecha_inicio_estimada?->format("d/m/Y") ?? "Sin fecha" }}
                                    </span>
                                    <span class="table-secondary">
                                        Término
                                        {{ $servicio->fecha_termino_estimada?->format("d/m/Y") ?? "por definir" }}
                                    </span>
                                </td>
                                <td>
                                    <x-ui.badge :status="$servicio->estado" />
                                </td>
                                <td>
                                    <x-ui.button
                                        :href="route('servicios.show', $servicio)"
                                        variant="ghost"
                                        size="small"
                                    >
                                        Abrir
                                        <x-ui.icon name="arrow" size="14" />
                                    </x-ui.button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="pagination">{{ $servicios->links() }}</div>
        @endif
    </x-ui.panel>
@endsection
