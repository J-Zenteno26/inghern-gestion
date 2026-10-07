@extends("layouts.app")
@section("title", "Inicio")
@section("content")
    <x-ui.page-header
        eyebrow="Centro de operación"
        title="Panorama de trabajo"
        description="Servicios, relaciones y propuestas que requieren atención."
    >
        <x-slot:actions>
            <x-ui.button :href="route('servicios.create')" variant="secondary">
                <x-ui.icon name="plus" />
                Nuevo servicio
            </x-ui.button>
            <x-ui.button :href="route('cotizaciones.create')">
                <x-ui.icon name="plus" />
                Nueva cotización
            </x-ui.button>
        </x-slot>
    </x-ui.page-header>
    <div class="metrics">
        @foreach ($metricas as $metrica)
            <x-ui.metric
                :label="$metrica['etiqueta']"
                :value="$metrica['valor']"
                :detail="$metrica['detalle']"
                :tone="$metrica['tono']"
            />
        @endforeach
    </div>
    <div class="dashboard-grid">
        <x-ui.panel flush>
            <x-slot:title>
                <h2 class="ui-panel__title">Actividad de servicios</h2>
            </x-slot>
            <x-slot:subtitle>
                Los últimos registros operativos
            </x-slot>
            <x-slot:actions>
                <x-ui.button
                    :href="route('servicios.index')"
                    variant="ghost"
                    size="small"
                >
                    Ver todos
                    <x-ui.icon name="arrow" size="14" />
                </x-ui.button>
            </x-slot>
            @if ($servicios->isEmpty())
                <x-ui.empty-state
                    icon="briefcase"
                    title="Aún no hay servicios"
                    description="Crea el primer servicio para comenzar el seguimiento operativo."
                >
                    <x-slot:action>
                        <x-ui.button
                            :href="route('servicios.create')"
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
                                            {{ $servicio->codigo }}
                                        </span>
                                    </td>
                                    <td>
                                        {{ $servicio->cliente->nombre_display }}
                                    </td>
                                    <td>
                                        <x-ui.badge
                                            :status="$servicio->estado"
                                        />
                                    </td>
                                    <td class="text-right">
                                        <x-ui.button
                                            :href="route('servicios.show', $servicio)"
                                            variant="ghost"
                                            size="small"
                                        >
                                            Abrir
                                        </x-ui.button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.panel>
        <x-ui.panel flush>
            <x-slot:title>
                <h2 class="ui-panel__title">Propuestas recientes</h2>
            </x-slot>
            <x-slot:subtitle>
                Estado comercial vigente
            </x-slot>
            @if ($cotizaciones->isEmpty())
                <x-ui.empty-state
                    icon="file"
                    title="Sin cotizaciones"
                    description="Las propuestas aparecerán aquí al crear su primera revisión."
                />
            @else
                <div class="ui-table-wrap">
                    <table class="ui-table">
                        <tbody>
                            @foreach ($cotizaciones as $cotizacion)
                                <tr>
                                    <td>
                                        <span class="table-primary">
                                            {{ $cotizacion->codigo }}
                                        </span>
                                        <span class="table-secondary">
                                            {{ $cotizacion->cliente->nombre_display }}
                                        </span>
                                    </td>
                                    <td>
                                        <x-ui.badge
                                            :status="$cotizacion->estado"
                                        />
                                    </td>
                                    <td class="text-right numeric">
                                        ${{ number_format($cotizacion->revisionActual?->total ?? 0, 0, ",", ".") }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.panel>
    </div>
@endsection
