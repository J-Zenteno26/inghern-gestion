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
    <form class="toolbar toolbar--stacked" method="GET">
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
                <label for="estado">Estado</label>
                <x-ui.select name="estado">
                    <option value="">Todos los estados</option>
                    @foreach (["prospecto", "planificado", "en_curso", "en_pausa", "finalizado", "cancelado"] as $opcion)
                        <option value="{{ $opcion }}" @selected($estado === $opcion)>
                            {{ ucfirst(str_replace("_", " ", $opcion)) }}
                        </option>
                    @endforeach
                </x-ui.select>
            </div>
            <x-ui.button type="submit" variant="outline" size="small">
                Aplicar filtro
            </x-ui.button>
        </div>
        @if ($buscar || $estado)
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
    <x-ui.panel flush>
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
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th>Servicio</th>
                            <th>Cliente</th>
                            <th>Plantas</th>
                            <th>Fechas</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($servicios as $servicio)
                            <tr>
                                <td>
                                    <span class="table-primary">
                                        {{ $servicio->nombre }}
                                    </span>
                                    <span class="table-secondary">
                                        {{ $servicio->codigo }} ·
                                        {{ $servicio->tipo?->nombre ?? "Sin clasificar" }} ·
                                        {{ $servicio->catalogoServicio?->nombre ?? $servicio->servicio_otro ?? "Pendiente de normalización" }}
                                    </span>
                                </td>
                                <td>
                                    {{ $servicio->cliente->nombre_display }}
                                </td>
                                <td>
                                    {{ $servicio->plantas->pluck("nombre")->join(", ") ?: "Por definir" }}
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
