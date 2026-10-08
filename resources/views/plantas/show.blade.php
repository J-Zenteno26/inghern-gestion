@extends('layouts.app')
@section('title', $planta->nombre)
@section('content')
    <div class="plant-control-page">
        <section class="plant-hero">
            <div class="plant-hero__identity">
                <span class="plant-hero__icon"><x-ui.icon name="factory" size="28" /></span>
                <div>
                    <span class="plant-hero__eyebrow">Centro de control</span>
                    <h1>Planta {{ $planta->nombre }}</h1>
                    <a href="{{ route('clientes.show', $planta->cliente) }}">{{ $planta->cliente->nombre_display }}</a>
                    <p><x-ui.icon name="map-pin" size="15" /> {{ collect([$planta->direccion, $planta->comuna, $planta->ciudad, $planta->region])->filter()->join(', ') ?: 'Ubicación por completar' }}</p>
                </div>
            </div>
            <x-ui.badge :status="$planta->estado" />
        </section>

        <section class="plant-kpis" aria-label="Resumen de la planta">
            <x-ui.metric label="Servicios activos" :value="$serviciosActivos" detail="Trabajo operativo vigente" icon="briefcase-business" tone="petroleo" />
            <x-ui.metric label="Cotizaciones" :value="$totalCotizaciones" detail="Histórico comercial" icon="file-text" />
            <x-ui.metric label="Documentos" :value="$totalDocumentos" detail="Relacionados con esta planta" icon="folder" />
            <x-ui.metric label="Monto aceptado" :value="'$'.number_format($montoAceptado, 0, ',', '.')" detail="Revisiones vigentes · CLP" icon="banknote" tone="naranjo" />
        </section>

        <div class="plant-control-grid">
            <section class="plant-workstream plant-workstream--services">
                <header class="plant-workstream__header">
                    <div><span><x-ui.icon entity="servicio" /></span><div><h2>Servicios</h2><p>Trabajo operativo asociado a esta planta.</p></div></div>
                    <x-ui.button :href="route('servicios.index', ['planta' => $planta->id])" variant="ghost" size="small">Ver todos los servicios <x-ui.icon name="arrow" size="14" /></x-ui.button>
                </header>
                <div class="plant-record-list">
                    @forelse ($servicios as $servicio)
                        <a class="plant-record" href="{{ route('servicios.show', $servicio) }}">
                            <span class="plant-record__icon"><x-ui.icon entity="servicio" size="16" /></span>
                            <span class="plant-record__content"><strong>{{ $servicio->nombre }}</strong><small>{{ $servicio->codigo }} · {{ $servicio->tipo?->nombre ?? 'Sin clasificar' }}</small></span>
                            <x-ui.badge :status="$servicio->estado" />
                        </a>
                    @empty
                        <p class="plant-record-list__empty">No hay servicios asociados.</p>
                    @endforelse
                </div>
            </section>

            <section class="plant-workstream plant-workstream--quotes">
                <header class="plant-workstream__header">
                    <div><span><x-ui.icon entity="cotizacion" /></span><div><h2>Cotizaciones</h2><p>Propuestas comerciales de esta planta.</p></div></div>
                    <x-ui.button :href="route('cotizaciones.index', ['planta' => $planta->id])" variant="ghost" size="small">Ver todas las cotizaciones <x-ui.icon name="arrow" size="14" /></x-ui.button>
                </header>
                <div class="plant-record-list">
                    @forelse ($cotizaciones as $cotizacion)
                        <a class="plant-record" href="{{ route('cotizaciones.show', $cotizacion) }}">
                            <span class="plant-record__icon"><x-ui.icon entity="cotizacion" size="16" /></span>
                            <span class="plant-record__content"><strong>{{ $cotizacion->codigo }} · {{ $cotizacion->revisionActual?->titulo ?? 'Sin título' }}</strong><small>{{ $cotizacion->revisionActual?->fecha_emision?->format('d/m/Y') ?? $cotizacion->created_at?->format('d/m/Y') }} · Rev. {{ $cotizacion->revisionActual?->revision ?? 1 }}</small></span>
                            <x-ui.badge :status="$cotizacion->estado" />
                        </a>
                    @empty
                        <p class="plant-record-list__empty">No hay cotizaciones asignadas.</p>
                    @endforelse
                </div>
            </section>

            <section class="plant-workstream plant-workstream--documents">
                <header class="plant-workstream__header">
                    <div><span><x-ui.icon name="folder" /></span><div><h2>Documentación</h2><p>{{ $totalDocumentos }} archivos relacionados, sin duplicados.</p></div></div>
                    <div class="plant-document-actions">
                        <x-ui.button :href="route('biblioteca.index', ['planta' => $planta->id, 'subir' => 1])" variant="secondary" size="small"><x-ui.icon name="plus" size="14" /><x-ui.icon name="file-text" size="14" /> Añadir documento</x-ui.button>
                        <x-ui.button :href="route('biblioteca.index', ['planta' => $planta->id])" variant="ghost" size="small"><x-ui.icon name="folder" size="14" /> Abrir en Biblioteca</x-ui.button>
                    </div>
                </header>
                <div class="plant-document-list">
                    @forelse ($documentos as $documento)
                        <div><x-ui.icon :name="$documento->icono()" size="16" /><span><strong>{{ $documento->nombre }}</strong><small>{{ strtoupper($documento->extension) }} · {{ $documento->created_at?->format('d/m/Y') }}</small></span></div>
                    @empty
                        <p class="plant-record-list__empty">Aún no hay documentos relacionados.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
@endsection
