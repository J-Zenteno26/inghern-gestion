@extends("layouts.app")
@section("title", "Clientes")
@section("content")
    <x-ui.page-header
        eyebrow="Relaciones"
        title="Clientes"
        description="Empresas, contactos y plantas vinculadas a la operación."
    >
        <x-slot:actions>
            <x-ui.button :href="route('clientes.create')">
                <x-ui.icon name="plus" />
                Nuevo cliente
            </x-ui.button>
        </x-slot>
    </x-ui.page-header>
    <form class="toolbar" method="GET">
        <div class="toolbar__search">
            <x-ui.icon name="search" />
            <input
                class="ui-control"
                type="search"
                name="buscar"
                value="{{ $buscar }}"
                placeholder="Buscar por nombre o RUT"
            />
        </div>
        <x-ui.button type="submit" variant="secondary" size="small">
            Buscar
        </x-ui.button>
        @if ($buscar)
            <x-ui.button
                :href="route('clientes.index')"
                variant="ghost"
                size="small"
            >
                Limpiar
            </x-ui.button>
        @endif
    </form>
    <x-ui.panel flush>
        @if ($clientes->isEmpty())
            <x-ui.empty-state
                icon="users"
                title="No hay clientes para mostrar"
                description="Registra una empresa junto con su primer contacto o planta."
            >
                <x-slot:action>
                    <x-ui.button :href="route('clientes.create')" size="small">
                        Crear cliente
                    </x-ui.button>
                </x-slot>
            </x-ui.empty-state>
        @else
            <div class="ui-table-wrap">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th>Empresa</th>
                            <th>RUT / ID</th>
                            <th>Relación</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($clientes as $cliente)
                            <tr>
                                <td>
                                    <span class="table-primary">
                                        {{ $cliente->nombre_display }}
                                    </span>
                                    <span class="table-secondary">
                                        {{ $cliente->razon_social }}
                                    </span>
                                </td>
                                <td>
                                    {{ $cliente->identificador_tributario }}
                                </td>
                                <td>
                                    <span class="table-primary">
                                        {{ $cliente->servicios_count }}
                                        servicios
                                    </span>
                                    <span class="table-secondary">
                                        {{ $cliente->plantas_count }} plantas ·
                                        {{ $cliente->contactos_count }}
                                        contactos
                                    </span>
                                </td>
                                <td>
                                    <x-ui.badge :status="$cliente->estado" />
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <x-ui.button
                                            :href="route('clientes.show', $cliente)"
                                            variant="ghost"
                                            size="small"
                                        >
                                            Ver ficha
                                            <x-ui.icon name="arrow" size="14" />
                                        </x-ui.button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="pagination">{{ $clientes->links() }}</div>
        @endif
    </x-ui.panel>
@endsection
