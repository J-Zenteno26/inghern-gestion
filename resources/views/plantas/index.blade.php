@extends('layouts.app')
@section('title', 'Plantas')
@section('content')
    <div class="plants-page">
        <x-ui.page-header
            eyebrow="Contexto de trabajo"
            title="Plantas"
            description="Centros operativos y comerciales de las organizaciones."
        />

        <form class="toolbar plants-toolbar" method="GET">
            <div class="toolbar__search">
                <x-ui.icon name="search" />
                <input class="ui-control" type="search" name="buscar" value="{{ $buscar }}" placeholder="Buscar por nombre o ubicación" />
            </div>
            <div class="toolbar__filter">
                <label for="cliente">Organización</label>
                <x-ui.select name="cliente">
                    <option value="">Todas las organizaciones</option>
                    @foreach ($clientes as $cliente)
                        <option value="{{ $cliente->id }}" @selected($clienteId === $cliente->id)>{{ $cliente->nombre_display }}</option>
                    @endforeach
                </x-ui.select>
            </div>
            <x-ui.button type="submit" variant="secondary" size="small">
                <x-ui.icon name="sliders-horizontal" size="15" />
                Filtrar
            </x-ui.button>
            @if ($buscar || $clienteId)
                <x-ui.button :href="route('plantas.index')" variant="ghost" size="small">Limpiar</x-ui.button>
            @endif
        </form>

        @if ($plantas->isEmpty())
            <x-ui.panel>
                <x-ui.empty-state icon="factory" title="No hay plantas para mostrar" description="Cambia los filtros o registra una planta desde su organización." />
            </x-ui.panel>
        @else
            <div class="plant-card-grid">
                @foreach ($plantas as $planta)
                    <article class="plant-card">
                        <div class="plant-card__topline">
                            <span class="plant-card__icon"><x-ui.icon name="factory" size="22" /></span>
                            <x-ui.badge :status="$planta->estado" />
                        </div>
                        <div class="plant-card__identity">
                            <h2>{{ $planta->nombre }}</h2>
                            <a href="{{ route('clientes.show', $planta->cliente) }}">{{ $planta->cliente->nombre_display }}</a>
                            <p><x-ui.icon name="map-pin" size="15" /> {{ collect([$planta->direccion, $planta->comuna, $planta->ciudad, $planta->region])->filter()->join(', ') ?: 'Ubicación por completar' }}</p>
                        </div>
                        <dl class="plant-card__metrics">
                            <div><dt>Servicios activos</dt><dd>{{ $planta->servicios_activos_count }}</dd></div>
                            <div><dt>Cotizaciones</dt><dd>{{ $planta->cotizaciones_count }}</dd></div>
                            <div><dt>Documentos</dt><dd>{{ $planta->documentos_relacionados_count }}</dd></div>
                        </dl>
                        <x-ui.button :href="route('plantas.show', $planta)" variant="secondary" size="small">
                            Abrir planta
                            <x-ui.icon name="arrow" size="15" />
                        </x-ui.button>
                    </article>
                @endforeach
            </div>
            <div class="pagination">{{ $plantas->links() }}</div>
        @endif
    </div>
@endsection
