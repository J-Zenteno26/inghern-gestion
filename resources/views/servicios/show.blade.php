@extends("layouts.app")
@section("title", $servicio->nombre)
@section("content")
    @php
        $hitoFecha = $servicio->fecha_inicio_estimada ?? $servicio->fecha_termino_estimada;
        $hitoDetalle = $servicio->fecha_inicio_estimada ? "Inicio estimado" : "Término estimado";
    @endphp
    <div class="service-detail-page">
        <x-ui.page-header
            :eyebrow="'Servicio · '.$servicio->codigo"
            :title="$servicio->nombre"
            :description="$servicio->cliente->nombre_display"
        >
            <x-slot:actions>
                <div class="service-detail-header-actions">
                    <x-ui.badge :status="$servicio->estado" />
                    <x-ui.button
                        :href="route('cotizaciones.create', ['cliente' => $servicio->cliente_id, 'servicio' => $servicio->id])"
                        variant="secondary"
                    >
                        <x-ui.icon name="plus" />
                        Cotización relacionada
                    </x-ui.button>
                </div>
            </x-slot>
        </x-ui.page-header>

        <section class="metrics {{ $hitoFecha ? 'metrics--four' : '' }}" aria-label="Resumen del servicio">
            <x-ui.metric
                label="Estado actual"
                :value="ucfirst(str_replace('_', ' ', $servicio->estado))"
                detail="Seguimiento operativo"
                tone="petroleo"
            />
            <x-ui.metric
                label="Plantas asociadas"
                :value="number_format($servicio->plantas->count(), 0, ',', '.')"
                detail="Ubicaciones incluidas"
            />
            <x-ui.metric
                label="Cotizaciones vinculadas"
                :value="number_format($cotizacionesVinculadas->count(), 0, ',', '.')"
                detail="Vínculos comerciales registrados"
                tone="petroleo"
            />
            @if ($hitoFecha)
                <x-ui.metric
                    label="Referencia temporal"
                    :value="$hitoFecha->format('d/m/Y')"
                    :detail="$hitoDetalle"
                />
            @endif
        </section>

        <div class="service-detail-layout">
            <main class="stack">
                <x-ui.panel class="service-detail-panel service-detail-panel--scope">
                    <x-slot:title>
                        <div class="service-detail-panel__titlewrap">
                            <span class="service-detail-panel__icon">
                                <x-ui.icon name="briefcase-business" />
                            </span>
                            <div>
                                <h2 class="ui-panel__title">Alcance operativo</h2>
                                <p class="ui-panel__subtitle">Definición y referencias técnicas del trabajo.</p>
                            </div>
                        </div>
                    </x-slot>
                    <div class="service-scope-copy">
                        <span>Descripción del servicio</span>
                        <p>{{ $servicio->descripcion ?: "El alcance todavía no ha sido detallado." }}</p>
                    </div>
                    <div class="service-facts">
                        <div class="service-fact">
                            <span>Tipo de servicio</span>
                            <strong>{{ $servicio->tipo?->nombre ?? "Sin clasificar" }}</strong>
                        </div>
                        <div class="service-fact">
                            <span>Servicio normalizado</span>
                            <strong>
                                {{ $servicio->catalogoServicio?->nombre ?? $servicio->servicio_otro ?? "Pendiente de normalización" }}
                            </strong>
                        </div>
                        <div class="service-fact">
                            <span>Estado</span>
                            <strong><x-ui.badge :status="$servicio->estado" /></strong>
                        </div>
                        <div class="service-fact">
                            <span>Inicio estimado</span>
                            <strong>{{ $servicio->fecha_inicio_estimada?->format("d/m/Y") ?? "Por definir" }}</strong>
                        </div>
                        <div class="service-fact">
                            <span>Término estimado</span>
                            <strong>{{ $servicio->fecha_termino_estimada?->format("d/m/Y") ?? "Por definir" }}</strong>
                        </div>
                    </div>
                </x-ui.panel>

                <x-ui.panel class="service-detail-panel service-commercial-panel" flush>
                    <x-slot:title>
                        <div class="service-detail-panel__titlewrap">
                            <span class="service-detail-panel__icon">
                                <x-ui.icon name="file-text" />
                            </span>
                            <div>
                                <h2 class="ui-panel__title">Vínculos comerciales</h2>
                                <p class="ui-panel__subtitle">Cotizaciones relacionadas con este servicio.</p>
                            </div>
                        </div>
                    </x-slot>
                    @if ($cotizacionesVinculadas->isEmpty())
                        <x-ui.empty-state
                            icon="file"
                            title="Sin cotizaciones vinculadas"
                            description="Este servicio puede existir antes, durante o después de una propuesta comercial."
                        />
                    @else
                        <div class="ui-table-wrap">
                            <table class="ui-table service-commercial-table">
                                <thead>
                                    <tr>
                                        <th>Cotización</th>
                                        <th>Estado</th>
                                        <th>Revisión</th>
                                        <th>Emisión</th>
                                        <th class="text-right">Total</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($cotizacionesVinculadas as $cotizacion)
                                        @php($revision = $cotizacion->revisionActual)
                                        <tr>
                                            <td>
                                                <span class="table-primary">{{ $cotizacion->codigo }}</span>
                                                <span class="table-secondary">{{ $revision?->titulo ?? "Sin revisión actual" }}</span>
                                            </td>
                                            <td>
                                                <x-ui.badge :status="$revision?->estado ?? $cotizacion->estado" />
                                            </td>
                                            <td class="numeric">Rev. {{ $revision?->revision ?? "—" }}</td>
                                            <td>{{ $revision?->fecha_emision?->format("d/m/Y") ?? "Borrador" }}</td>
                                            <td class="numeric text-right">
                                                @if ($revision)
                                                    {{ $revision->moneda }}
                                                    ${{ number_format($revision->total, 0, ",", ".") }}
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td>
                                                <div class="table-actions">
                                                    <x-ui.button
                                                        :href="route('cotizaciones.show', $cotizacion)"
                                                        variant="ghost"
                                                        size="small"
                                                    >
                                                        Abrir
                                                        <x-ui.icon name="arrow" size="14" />
                                                    </x-ui.button>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-ui.panel>
            </main>

            <aside class="service-context-rail">
                <x-ui.panel class="service-context-panel">
                    <x-slot:title>
                        <div class="service-detail-panel__titlewrap">
                            <span class="service-detail-panel__icon">
                                <x-ui.icon name="building-2" />
                            </span>
                            <div>
                                <h2 class="ui-panel__title">Contexto operativo</h2>
                                <p class="ui-panel__subtitle">Organización y cobertura asociada.</p>
                            </div>
                        </div>
                    </x-slot>
                    <div class="service-context-section">
                        <div class="service-context-section__heading">
                            <x-ui.icon name="building-2" size="17" />
                            <span>Organización</span>
                        </div>
                        <a
                            class="service-context-link"
                            href="{{ route('clientes.show', $servicio->cliente) }}"
                        >
                            {{ $servicio->cliente->nombre_display }}
                        </a>
                        <span class="service-context-meta">
                            {{ $servicio->cliente->identificador_tributario ?: "Identificador por completar" }}
                        </span>
                    </div>
                    <div class="service-context-section">
                        <div class="service-context-section__heading">
                            <x-ui.icon name="factory" size="17" />
                            <span>Plantas</span>
                            <span class="service-context-count">{{ $servicio->plantas->count() }}</span>
                        </div>
                        <div class="service-context-plants">
                            @forelse ($servicio->plantas as $planta)
                                <div class="service-context-plant">
                                    <span class="service-context-plant__icon">
                                        <x-ui.icon name="map-pin" size="15" />
                                    </span>
                                    <div>
                                        <strong>{{ $planta->nombre }}</strong>
                                        <span>{{ $planta->ciudad ?: "Ubicación por completar" }}</span>
                                    </div>
                                </div>
                            @empty
                                <p class="service-context-empty">No se asociaron plantas.</p>
                            @endforelse
                        </div>
                    </div>
                </x-ui.panel>
            </aside>
        </div>
    </div>
@endsection
