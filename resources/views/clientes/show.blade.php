@extends("layouts.app")
@section("title", $cliente->nombre_display)
@section("content")
    @php
        $serviciosActivos = $cliente->servicios->whereIn('estado', ['activo', 'en_curso', 'planificado'])->count();
        $cotizacionesAbiertas = $cliente->cotizaciones->whereNotIn('estado', ['aceptada', 'aprobada', 'rechazada', 'anulada', 'cancelado'])->count();
        $contactoPrincipal = $cliente->contactos->firstWhere('es_principal', true) ?? $cliente->contactos->first();
        $tiposServicio = $cliente->servicios->pluck('tipo')->filter()->unique('id')->sortBy('nombre');
        $estadosServicio = $cliente->servicios->pluck('estado')->filter()->unique()->sort()->values();
        $estadosCotizacion = $cliente->cotizaciones->pluck('estado')->filter()->unique()->sort()->values();
        $documentos = $cliente->documentosPropios()->with('usuarioCreador')->latest()->limit(3)->get();
        $totalDocumentos = $cliente->documentosPropios()->count();
        $tiposDocumento = \App\Models\Documento::TIPOS;
        $cotizacionesPorAsignar = $cliente->cotizaciones->whereNull('planta_id')->count();
        $serviciosPorAsignar = $cliente->servicios->filter(fn ($servicio) => $servicio->plantas->isEmpty())->count();
    @endphp

    <section class="organization-hero">
        <div class="organization-hero__identity">
            <div class="organization-hero__icon" aria-hidden="true">
                <x-ui.icon name="building-2" size="26" />
            </div>
            <div>
                <div class="organization-hero__eyebrow">Perfil de organización</div>
                <h1 class="organization-hero__title">{{ $cliente->nombre_display }}</h1>
                <div class="organization-hero__meta">
                    <span>{{ $cliente->razon_social }}</span>
                    @if ($cliente->identificador_tributario)
                        <span class="organization-hero__dot">•</span>
                        <span>{{ $cliente->identificador_tributario }}</span>
                    @endif
                    <x-ui.badge :status="$cliente->estado" />
                </div>
            </div>
        </div>
        <div class="organization-hero__actions">
            <x-ui.button :href="route('clientes.edit', $cliente)" variant="outline">
                <x-ui.icon name="pencil" />
                Editar organización
            </x-ui.button>
            <x-ui.button :href="route('servicios.create', ['cliente' => $cliente->id])" variant="secondary">
                <x-ui.icon name="briefcase-business" />
                Nuevo servicio
            </x-ui.button>
            <x-ui.button :href="route('cotizaciones.create', ['cliente' => $cliente->id])">
                <x-ui.icon name="file-plus-2" />
                Nueva cotización
            </x-ui.button>
        </div>
    </section>

    <section class="organization-metrics" aria-label="Resumen de la organización">
        <article class="organization-metric organization-metric--petrol">
            <div class="organization-metric__icon"><x-ui.icon name="briefcase-business" /></div>
            <div>
                <div class="organization-metric__value">{{ $serviciosActivos }}</div>
                <div class="organization-metric__label">Servicios activos</div>
                <div class="organization-metric__detail">{{ $cliente->servicios->count() }} registrados</div>
            </div>
        </article>
        <article class="organization-metric organization-metric--orange">
            <div class="organization-metric__icon"><x-ui.icon name="file-text" /></div>
            <div>
                <div class="organization-metric__value">{{ $cotizacionesAbiertas }}</div>
                <div class="organization-metric__label">Cotizaciones abiertas</div>
                <div class="organization-metric__detail">{{ $cliente->cotizaciones->count() }} en historial</div>
            </div>
        </article>
        <article class="organization-metric">
            <div class="organization-metric__icon"><x-ui.icon name="users" /></div>
            <div>
                <div class="organization-metric__value">{{ $cliente->contactos->count() }}</div>
                <div class="organization-metric__label">Contactos</div>
                <div class="organization-metric__detail">{{ $contactoPrincipal?->nombre ?? 'Sin contacto principal' }}</div>
            </div>
        </article>
        <article class="organization-metric">
            <div class="organization-metric__icon"><x-ui.icon name="factory" /></div>
            <div>
                <div class="organization-metric__value">{{ $cliente->plantas->count() }}</div>
                <div class="organization-metric__label">Plantas</div>
                <div class="organization-metric__detail">Ubicaciones asociadas</div>
            </div>
        </article>
    </section>

    <section class="organization-plants" aria-labelledby="organization-plants-title">
        <header class="organization-plants__header">
            <div>
                <span class="organization-plants__eyebrow">Centros de trabajo</span>
                <h2 id="organization-plants-title">Plantas</h2>
                <p>Contexto operativo y comercial de la organización.</p>
            </div>
            <span class="organization-plants__count">{{ $cliente->plantas->count() }}</span>
        </header>
        <div class="organization-plant-grid">
            @forelse ($cliente->plantas as $planta)
                @php($serviciosActivosPlanta = $planta->servicios->whereIn('estado', ['prospecto', 'planificado', 'en_curso', 'en_pausa', 'activo'])->count())
                <article class="organization-plant-card">
                    <div class="organization-plant-card__header">
                        <span><x-ui.icon name="factory" size="21" /></span>
                        <x-ui.badge :status="$planta->estado" />
                    </div>
                    <h3>{{ $planta->nombre }}</h3>
                    <p><x-ui.icon name="map-pin" size="14" /> {{ collect([$planta->direccion, $planta->comuna, $planta->ciudad, $planta->region])->filter()->join(', ') ?: 'Ubicación por completar' }}</p>
                    <dl>
                        <div><dt>Servicios activos</dt><dd>{{ $serviciosActivosPlanta }}</dd></div>
                        <div><dt>Cotizaciones</dt><dd>{{ $planta->cotizaciones->count() }}</dd></div>
                        <div><dt>Documentos</dt><dd>{{ $planta->documentos_relacionados_count }}</dd></div>
                    </dl>
                    <x-ui.button :href="route('plantas.show', $planta)" variant="secondary" size="small">Abrir planta <x-ui.icon name="arrow" size="14" /></x-ui.button>
                </article>
            @empty
                <p class="organization-plants__empty">Esta organización aún no tiene plantas registradas.</p>
            @endforelse
        </div>
    </section>

    @if ($cotizacionesPorAsignar > 0 || $serviciosPorAsignar > 0)
        <section class="organization-unassigned">
            <div><span><x-ui.icon name="sliders-horizontal" size="18" /></span><div><h2>Por organizar</h2><p>Registros que todavía no tienen contexto de Planta.</p></div></div>
            <div class="organization-unassigned__actions">
                @if ($cotizacionesPorAsignar > 0)
                    <x-ui.button :href="route('cotizaciones.index', ['cliente' => $cliente->id])" variant="outline" size="small">{{ $cotizacionesPorAsignar }} cotización{{ $cotizacionesPorAsignar === 1 ? '' : 'es' }}</x-ui.button>
                @endif
                @if ($serviciosPorAsignar > 0)
                    <x-ui.button :href="route('servicios.index', ['cliente' => $cliente->id])" variant="outline" size="small">{{ $serviciosPorAsignar }} servicio{{ $serviciosPorAsignar === 1 ? '' : 's' }}</x-ui.button>
                @endif
            </div>
        </section>
    @endif

    <div class="organization-layout">
        <div class="organization-main">
            <section class="organization-panel organization-panel--accordion" data-org-services data-org-accordion>
                <button
                    class="organization-panel__header organization-panel__header--petrol organization-panel__toggle"
                    type="button"
                    aria-expanded="false"
                    aria-controls="organization-services-content"
                    data-org-accordion-toggle
                >
                    <div class="organization-panel__heading">
                        <span class="organization-panel__icon"><x-ui.icon name="briefcase-business" /></span>
                        <div>
                            <h2>Servicios</h2>
                            <p>Trabajo operativo asociado a esta organización.</p>
                        </div>
                    </div>
                    <div class="organization-panel__header-actions">
                        <span class="organization-panel__count" data-org-services-count>{{ $cliente->servicios->count() }}</span>
                        <span class="organization-panel__chevron" aria-hidden="true"><x-ui.icon name="chevron-down" size="18" /></span>
                    </div>
                </button>

                <div class="organization-panel__content" id="organization-services-content" data-org-accordion-content hidden>
                    @if ($cliente->servicios->isEmpty())
                        <x-ui.empty-state icon="briefcase" title="Sin servicios asociados" description="Crea un servicio para iniciar el seguimiento del trabajo con esta organización." />
                    @else
                        <div class="organization-filterbar">
                            <label class="organization-search">
                                <x-ui.icon name="search" />
                                <input class="ui-control" type="search" placeholder="Buscar por código, servicio o planta..." data-org-services-search />
                            </label>
                            <label class="organization-filter">
                                <span>Tipo</span>
                                <select class="ui-control" data-org-services-type>
                                    <option value="">Todos los tipos</option>
                                    @foreach ($tiposServicio as $tipo)
                                        <option value="{{ $tipo->id }}">{{ $tipo->nombre }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="organization-filter">
                                <span>Estado</span>
                                <select class="ui-control" data-org-services-status>
                                    <option value="">Todos los estados</option>
                                    @foreach ($estadosServicio as $estado)
                                        <option value="{{ $estado }}">{{ ucfirst(str_replace('_', ' ', $estado)) }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <button class="organization-filterbar__clear" type="button" data-org-services-clear title="Limpiar filtros">
                                <x-ui.icon name="rotate-ccw" size="17" />
                                Limpiar
                            </button>
                        </div>
                        <div class="organization-table-scroll organization-table-scroll--services">
                            <table class="ui-table organization-table organization-table--services">
                                <thead>
                                    <tr>
                                        <th>Servicio</th>
                                        <th>Tipo</th>
                                        <th>Cobertura</th>
                                        <th>Estado</th>
                                        <th aria-label="Abrir"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($cliente->servicios as $servicio)
                                        @php
                                            $nombresPlantas = $servicio->plantas->pluck('nombre')->filter()->values();
                                            $searchServicio = mb_strtolower(collect([
                                                $servicio->codigo,
                                                $servicio->nombre,
                                                $servicio->tipo?->nombre,
                                                $nombresPlantas->join(' '),
                                            ])->filter()->join(' '));
                                        @endphp
                                        <tr data-org-service-row data-search="{{ $searchServicio }}" data-type="{{ $servicio->tipo_servicio_id }}" data-status="{{ $servicio->estado }}">
                                            <td class="organization-table__service">
                                                <span class="organization-table__code">{{ $servicio->codigo }}</span>
                                                <a class="table-primary organization-table__service-name" href="{{ route('servicios.show', $servicio) }}">{{ $servicio->nombre }}</a>
                                            </td>
                                            <td>{{ $servicio->tipo?->nombre ?? 'Sin clasificar' }}</td>
                                            <td class="organization-table__coverage">
                                                @if ($servicio->plantas->count() === 1)
                                                    <span class="organization-coverage organization-coverage--single">
                                                        <x-ui.icon name="map-pin" size="15" />
                                                        {{ $servicio->plantas->first()->nombre }}
                                                    </span>
                                                @elseif ($servicio->plantas->count() > 1)
                                                    <span class="organization-tooltip" tabindex="0">
                                                        <span class="organization-coverage organization-coverage--multiple">
                                                            <x-ui.icon name="map-pin" size="15" />
                                                            {{ $servicio->plantas->count() }} plantas
                                                        </span>
                                                        <span class="organization-tooltip__content" role="tooltip">
                                                            <strong>Cobertura del servicio</strong>
                                                            @foreach ($servicio->plantas as $planta)
                                                                <span>{{ $planta->nombre }}</span>
                                                            @endforeach
                                                        </span>
                                                    </span>
                                                @else
                                                    <span class="organization-coverage organization-coverage--empty">Sin planta asociada</span>
                                                @endif
                                            </td>
                                            <td><x-ui.badge :status="$servicio->estado" /></td>
                                            <td class="organization-table__open"><a href="{{ route('servicios.show', $servicio) }}" aria-label="Abrir servicio"><x-ui.icon name="arrow" size="17" /></a></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <div class="organization-no-results" data-org-services-empty hidden>
                                <x-ui.icon name="search" />
                                <strong>No encontramos servicios</strong>
                                <span>Prueba con otro texto o limpia los filtros.</span>
                            </div>
                        </div>
                    @endif
                </div>
            </section>

            <section class="organization-panel organization-panel--accordion" data-org-quotes data-org-accordion>
                <button
                    class="organization-panel__header organization-panel__header--orange organization-panel__toggle"
                    type="button"
                    aria-expanded="false"
                    aria-controls="organization-quotes-content"
                    data-org-accordion-toggle
                >
                    <div class="organization-panel__heading">
                        <span class="organization-panel__icon"><x-ui.icon name="file-text" /></span>
                        <div>
                            <h2>Cotizaciones</h2>
                            <p>Propuestas comerciales y sus revisiones vigentes.</p>
                        </div>
                    </div>
                    <div class="organization-panel__header-actions">
                        <span class="organization-panel__count" data-org-quotes-count>{{ $cliente->cotizaciones->count() }}</span>
                        <span class="organization-panel__chevron" aria-hidden="true"><x-ui.icon name="chevron-down" size="18" /></span>
                    </div>
                </button>

                <div class="organization-panel__content" id="organization-quotes-content" data-org-accordion-content hidden>
                @if ($cliente->cotizaciones->isEmpty())
                    <x-ui.empty-state icon="file" title="Sin cotizaciones" description="Las propuestas comerciales de esta organización aparecerán aquí." />
                @else
                    <div class="organization-filterbar organization-filterbar--quotes">
                        <label class="organization-search">
                            <x-ui.icon name="search" />
                            <input class="ui-control" type="search" placeholder="Buscar por código o título..." data-org-quotes-search />
                        </label>
                        <label class="organization-filter">
                            <span>Estado</span>
                            <select class="ui-control" data-org-quotes-status>
                                <option value="">Todos los estados</option>
                                @foreach ($estadosCotizacion as $estado)
                                    <option value="{{ $estado }}">{{ ucfirst(str_replace('_', ' ', $estado)) }}</option>
                                @endforeach
                            </select>
                        </label>
                        <button class="organization-filterbar__clear" type="button" data-org-quotes-clear title="Limpiar filtros">
                            <x-ui.icon name="rotate-ccw" size="17" />
                            Limpiar
                        </button>
                    </div>
                    <div class="organization-table-scroll organization-table-scroll--quotes">
                        <table class="ui-table organization-table">
                            <thead>
                                <tr>
                                    <th>Código / propuesta</th>
                                    <th>Estado</th>
                                    <th>Revisión</th>
                                    <th class="text-right">Total</th>
                                    <th aria-label="Abrir"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($cliente->cotizaciones as $cotizacion)
                                    @php
                                        $tituloCotizacion = $cotizacion->revisionActual?->titulo ?: 'Sin título';
                                        $searchCotizacion = mb_strtolower($cotizacion->codigo.' '.$tituloCotizacion);
                                    @endphp
                                    <tr data-org-quote-row data-search="{{ $searchCotizacion }}" data-status="{{ $cotizacion->estado }}">
                                        <td>
                                            <a class="table-primary" href="{{ route('cotizaciones.show', $cotizacion) }}">{{ $cotizacion->codigo }}</a>
                                            <span class="table-secondary">{{ $tituloCotizacion }}</span>
                                        </td>
                                        <td><x-ui.badge :status="$cotizacion->estado" /></td>
                                        <td class="numeric">Rev. {{ $cotizacion->revisionActual?->revision ?? '—' }}</td>
                                        <td class="numeric text-right organization-table__money">${{ number_format($cotizacion->revisionActual?->total ?? 0, 0, ',', '.') }}</td>
                                        <td class="organization-table__open"><a href="{{ route('cotizaciones.show', $cotizacion) }}" aria-label="Abrir cotización"><x-ui.icon name="arrow" size="17" /></a></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <div class="organization-no-results" data-org-quotes-empty hidden>
                            <x-ui.icon name="search" />
                            <strong>No encontramos cotizaciones</strong>
                            <span>Prueba con otro texto o limpia los filtros.</span>
                        </div>
                    </div>
                @endif
                </div>
            </section>

            <section class="organization-panel organization-documents" aria-labelledby="organization-documents-title">
                <header class="organization-panel__header organization-panel__header--petrol">
                    <div class="organization-panel__heading">
                        <span class="organization-panel__icon"><x-ui.icon name="folder" /></span>
                        <div>
                            <h2 id="organization-documents-title">Documentos</h2>
                            <p>Archivos privados asociados a esta organización.</p>
                        </div>
                    </div>
                    <div class="organization-panel__header-actions">
                        <span class="organization-panel__count">{{ $totalDocumentos }}</span>
                    </div>
                </header>

                @if ($documentos->isEmpty())
                    <p class="organization-document-summary__empty">Aún no hay documentos para esta organización.</p>
                @else
                    <div class="organization-document-summary">
                        @foreach ($documentos as $documento)
                            <div class="organization-document-summary__row">
                                <span class="organization-document-summary__icon"><x-ui.icon :name="$documento->icono()" size="17" /></span>
                                <div class="organization-document-summary__copy"><strong>{{ $documento->nombre }}</strong><span>{{ $tiposDocumento[$documento->tipo_documento] ?? ucfirst(str_replace('_', ' ', $documento->tipo_documento)) }} · {{ strtoupper($documento->extension) }} · {{ $documento->created_at?->format('d/m/Y') ?? '—' }}</span></div>
                                <a class="organization-document-summary__download" href="{{ route('biblioteca.documentos.download', [$cliente, $documento]) }}" title="Descargar"><x-ui.icon name="download" size="16" /></a>
                            </div>
                        @endforeach
                    </div>
                @endif
                <div class="organization-document-summary__footer">
                    <span>{{ $totalDocumentos }} {{ $totalDocumentos === 1 ? 'documento' : 'documentos' }}</span>
                    <div class="organization-panel__header-actions">
                        <x-ui.button :href="route('biblioteca.index', ['cliente' => $cliente->id])" variant="ghost" size="small"><x-ui.icon name="folder" size="15" /> Ver en Biblioteca</x-ui.button>
                        <x-ui.button :href="route('biblioteca.index', ['cliente' => $cliente->id, 'subir' => 1])" variant="secondary" size="small"><x-ui.icon name="plus" size="14" /><x-ui.icon name="file-text" size="15" /> Añadir documento</x-ui.button>
                    </div>
                </div>
            </section>
        </div>

        <aside class="organization-sidebar">
            <section class="organization-sidecard organization-sidecard--general">
                <header class="organization-sidecard__header">
                    <span><x-ui.icon name="building-2" /></span>
                    <div><h2>Información general</h2><p>Datos corporativos y de contacto.</p></div>
                </header>
                <div class="organization-info-list">
                    <div class="organization-info-item">
                        <span class="organization-info-item__icon"><x-ui.icon name="circle-check" /></span>
                        <div><span>Estado</span><strong><x-ui.badge :status="$cliente->estado" /></strong></div>
                    </div>
                    <div class="organization-info-item">
                        <span class="organization-info-item__icon"><x-ui.icon name="id-card" /></span>
                        <div><span>RUT / Identificador</span><strong>{{ $cliente->identificador_tributario ?: 'Sin registrar' }}</strong></div>
                    </div>
                    <div class="organization-info-item">
                        <span class="organization-info-item__icon"><x-ui.icon name="mail" /></span>
                        <div><span>Facturación</span><strong>{{ $cliente->email_facturacion ?: 'Sin registrar' }}</strong></div>
                    </div>
                    <div class="organization-info-item">
                        <span class="organization-info-item__icon"><x-ui.icon name="phone" /></span>
                        <div><span>Teléfono</span><strong>{{ $cliente->telefono ?: 'Sin registrar' }}</strong></div>
                    </div>
                    <div class="organization-info-item">
                        <span class="organization-info-item__icon"><x-ui.icon name="map-pin" /></span>
                        <div><span>Ubicación</span><strong>{{ collect([$cliente->direccion, $cliente->comuna, $cliente->ciudad, $cliente->region])->filter()->join(', ') ?: 'Sin registrar' }}</strong></div>
                    </div>
                </div>
            </section>

            <section class="organization-sidecard">
                <header class="organization-sidecard__header">
                    <span><x-ui.icon name="users" /></span>
                    <div><h2>Contactos</h2><p>{{ $cliente->contactos->count() }} registrados</p></div>
                </header>
                <div class="organization-contact-list">
                    @forelse ($cliente->contactos as $contacto)
                        <article class="organization-contact">
                            <div class="organization-contact__avatar" aria-hidden="true"><x-ui.icon name="user-round" size="18" /></div>
                            <div class="organization-contact__copy">
                                <div class="organization-contact__name">
                                    {{ $contacto->nombre }}
                                    @if ($contacto->es_principal)<span class="organization-tag">Principal</span>@endif
                                </div>
                                @if ($contacto->cargo)<span>{{ $contacto->cargo }}</span>@endif
                                @if ($contacto->email)<a href="mailto:{{ $contacto->email }}">{{ $contacto->email }}</a>@endif
                                @if ($contacto->telefono)<a href="tel:{{ $contacto->telefono }}">{{ $contacto->telefono }}</a>@endif
                            </div>
                        </article>
                    @empty
                        <p class="organization-sidecard__empty">Sin contactos registrados.</p>
                    @endforelse
                </div>
            </section>

            <section class="organization-sidecard">
                <header class="organization-sidecard__header">
                    <span><x-ui.icon name="factory" /></span>
                    <div><h2>Plantas</h2><p>{{ $cliente->plantas->count() }} ubicaciones</p></div>
                </header>
                <div class="organization-plant-list">
                    @forelse ($cliente->plantas as $planta)
                        <article class="organization-plant">
                            <span class="organization-plant__icon"><x-ui.icon name="map-pin" size="17" /></span>
                            <div>
                                <strong>{{ $planta->nombre }}</strong>
                                <span>{{ collect([$planta->comuna, $planta->ciudad, $planta->region])->filter()->join(', ') ?: 'Ubicación por completar' }}</span>
                            </div>
                        </article>
                    @empty
                        <p class="organization-sidecard__empty">Sin plantas registradas.</p>
                    @endforelse
                </div>
            </section>
        </aside>
    </div>
@endsection
