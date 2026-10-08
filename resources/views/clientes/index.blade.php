@extends('layouts.app')
@section('title', 'Organizaciones')
@section('content')
    <div class="organization-index-page">
    <x-ui.page-header eyebrow="Nivel corporativo" title="Organizaciones" description="Empresas, contactos y centros de trabajo vinculados a INGHERN.">
        <x-slot:actions><x-ui.button :href="route('clientes.create')"><x-ui.icon name="plus" /> Nueva organización</x-ui.button></x-slot:actions>
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
    @if ($clientes->isEmpty())
        <x-ui.panel>
            <x-ui.empty-state
                icon="building-2"
                title="No hay organizaciones para mostrar"
                description="Registra una organización junto con su primer contacto o planta."
            >
                <x-slot:action>
                    <x-ui.button :href="route('clientes.create')" size="small">
                        Crear organización
                    </x-ui.button>
                </x-slot>
            </x-ui.empty-state>
        </x-ui.panel>
    @else
        <div class="organization-card-grid">
            @foreach ($clientes as $cliente)
                <article class="organization-card">
                    <header class="organization-card__header">
                        <span class="organization-card__icon"><x-ui.icon name="building-2" size="23" /></span>
                        <x-ui.badge :status="$cliente->estado" />
                    </header>
                    <div class="organization-card__identity">
                        <h2>{{ $cliente->nombre_display }}</h2>
                        <p>{{ $cliente->razon_social }}</p>
                        <span>{{ $cliente->identificador_tributario ?: 'Identificador por completar' }}</span>
                    </div>
                    <dl class="organization-card__metrics">
                        <div><dt>Plantas</dt><dd>{{ $cliente->plantas_count }}</dd></div>
                        <div><dt>Cotizaciones</dt><dd>{{ $cliente->cotizaciones_count }}</dd></div>
                        <div><dt>Servicios activos</dt><dd>{{ $cliente->servicios_activos_count }}</dd></div>
                        <div><dt>Documentos</dt><dd>{{ $cliente->documentos_propios_count }}</dd></div>
                    </dl>
                    <x-ui.button :href="route('clientes.show', $cliente)" variant="secondary" size="small">Abrir organización <x-ui.icon name="arrow" size="14" /></x-ui.button>
                </article>
            @endforeach
        </div>
        <div class="pagination">{{ $clientes->links() }}</div>
    @endif
    </div>
@endsection
