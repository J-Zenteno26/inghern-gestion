@extends("layouts.app")
@section("title", "Biblioteca")
@section("content")
@php
    $contextoBiblioteca = array_filter([
        'planta' => $plantaContexto?->getKey(),
        'cotizacion' => $cotizacionContexto?->getKey(),
        'revision_servicio' => $revisionServicioContexto?->getKey(),
        'contexto' => $contextoGeneral ? 'general' : null,
        'vista' => $vista,
    ]);
    $tipoVinculoSeleccionado = old('vinculable_type');
    $idVinculoSeleccionado = old('vinculable_id');
    $opcionVinculoSeleccionada = collect($opcionesVinculo)->first(
        fn (array $opcion) => $opcion['tipo'] === $tipoVinculoSeleccionado
            && (string) $opcion['id'] === (string) $idVinculoSeleccionado,
    );
    $filtrosAvanzadosActivos = (!$plantaContexto && $clienteId)
        || $tipoDocumento !== ''
        || $entidad !== ''
        || $formato !== '';
@endphp

<x-ui.page-header eyebrow="Gestión documental" title="Biblioteca"
    description="Explora y gestiona la documentación técnica, comercial y operacional por contexto.">
    <x-slot:actions>
        <x-ui.button
            :href="route('biblioteca.index', array_merge(request()->except('page'), ['subir' => 1]))"
            class="document-header-upload"
        >
            <x-ui.icon name="plus" size="16" /> Añadir documento
        </x-ui.button>
    </x-slot:actions>
</x-ui.page-header>

<div class="document-library-toolbar">
    <nav class="document-context-nav" aria-label="Contexto de Biblioteca">
        <a href="{{ route('biblioteca.index', ['vista' => 'carpetas']) }}">Biblioteca</a>
        @php($plantaNavegacion = $plantaContexto ?? $cotizacionContexto?->planta)
        @if ($plantaNavegacion)
            <x-ui.icon name="arrow" size="14" />
            <a href="{{ route('biblioteca.index', ['planta' => $plantaNavegacion->getKey(), 'vista' => 'carpetas']) }}">
                Planta {{ $plantaNavegacion->nombre }}
            </a>
        @endif
        @if ($cotizacionContexto)
            <x-ui.icon name="arrow" size="14" />
            <a href="{{ route('biblioteca.index', ['planta' => $plantaNavegacion?->getKey(), 'cotizacion' => $cotizacionContexto->getKey(), 'vista' => 'carpetas']) }}">
                {{ $cotizacionContexto->codigo }}
            </a>
        @endif
        @if ($revisionServicioContexto)
            <x-ui.icon name="arrow" size="14" />
            <span>{{ $revisionServicioContexto->titulo }}</span>
        @endif
    </nav>
</div>

@if ($plantaNavegacion || $cotizacionContexto)
    <div class="document-context">
        <span class="document-context__icon">
            <x-ui.icon :entity="$revisionServicioContexto ? 'servicio' : ($cotizacionContexto ? 'cotizacion' : 'planta')" size="18" />
        </span>
        <div>
            <strong>
                {{ $revisionServicioContexto?->titulo
                    ?? $cotizacionContexto?->revisionActual?->titulo
                    ?? 'Planta '.$plantaNavegacion?->nombre }}
            </strong>
            <span>{{ ($cotizacionContexto?->cliente ?? $plantaNavegacion?->cliente)?->nombre_display }}</span>
        </div>
    </div>
@endif

<form class="document-search" method="GET" action="{{ route('biblioteca.index') }}">
    @foreach ($contextoBiblioteca as $nombre => $valor)
        <input type="hidden" name="{{ $nombre }}" value="{{ $valor }}">
    @endforeach
    <div class="document-search__primary">
        <label class="document-filter document-filter--search">
            <span>Buscar en Biblioteca</span>
            <x-ui.input name="buscar" :value="$buscar" placeholder="Nombre del documento" />
        </label>
        <x-ui.button type="submit" variant="secondary"><x-ui.icon name="search" /> Buscar</x-ui.button>
        <details class="document-filter-disclosure" @if ($filtrosAvanzadosActivos) open @endif>
            <summary class="ui-button ui-button--secondary">
                <x-ui.icon name="sliders-horizontal" size="16" /> Filtros
            </summary>
            <div class="document-filters">
                @unless ($plantaContexto || $cotizacionContexto)
                    <label class="document-filter"><span>Organización</span><x-ui.select name="cliente">
                        <option value="">Todas</option>
                        @foreach ($clientes as $cliente)
                            <option value="{{ $cliente->id }}" @selected($clienteId === $cliente->id)>{{ $cliente->nombre_display }}</option>
                        @endforeach
                    </x-ui.select></label>
                    <label class="document-filter"><span>Planta</span><x-ui.select name="planta">
                        <option value="">Todas</option>
                        @foreach ($plantasFiltro as $plantaFiltro)
                            <option value="{{ $plantaFiltro->id }}">{{ $plantaFiltro->nombre }} · {{ $plantaFiltro->cliente->nombre_display }}</option>
                        @endforeach
                    </x-ui.select></label>
                @endunless
                <label class="document-filter"><span>Tipo documental</span><x-ui.select name="tipo">
                    <option value="">Todos</option>
                    @foreach (\App\Models\Documento::TIPOS as $valor => $etiqueta)
                        <option value="{{ $valor }}" @selected($tipoDocumento === $valor)>{{ $etiqueta }}</option>
                    @endforeach
                </x-ui.select></label>
                <label class="document-filter"><span>Entidad relacionada</span><x-ui.select name="entidad">
                    <option value="">Todas</option>
                    @foreach (\App\Models\DocumentoVinculo::ETIQUETAS as $valor => $etiqueta)
                        <option value="{{ $valor }}" @selected($entidad === $valor)>{{ $etiqueta }}</option>
                    @endforeach
                </x-ui.select></label>
                <label class="document-filter"><span>Formato</span><x-ui.select name="formato">
                    <option value="">Todos</option>
                    @foreach (config('filesystems.document_uploads.allowed_extensions') as $extension)
                        <option value="{{ $extension }}" @selected($formato === $extension)>{{ strtoupper($extension) }}</option>
                    @endforeach
                </x-ui.select></label>
                <div class="document-filters__actions">
                    <x-ui.button type="submit" variant="secondary">Aplicar filtros</x-ui.button>
                    <a class="document-filters__clear" href="{{ route('biblioteca.index', $contextoBiblioteca) }}">
                        <x-ui.icon name="rotate-ccw" size="16" /> Limpiar
                    </a>
                </div>
            </div>
        </details>
        <div class="document-view-switch" aria-label="Modo de visualización">
            <a class="{{ $vista === 'carpetas' ? 'is-active' : '' }}"
                href="{{ route('biblioteca.index', array_merge(request()->except('page'), ['vista' => 'carpetas'])) }}">
                <x-ui.icon name="folder" size="16" /> Carpetas
            </a>
            <a class="{{ $vista === 'lista' ? 'is-active' : '' }}"
                href="{{ route('biblioteca.index', array_merge(request()->except('page'), ['vista' => 'lista'])) }}">
                <x-ui.icon name="menu" size="16" /> Lista
            </a>
        </div>
    </div>
</form>

<x-ui.panel class="document-library__upload-panel">
    <details class="document-upload" @if ($errors->any() || request()->boolean('subir')) open @endif>
        <summary class="ui-button ui-button--primary document-upload__summary">
            <x-ui.icon name="plus" size="15" />
            <x-ui.icon name="file-text" size="17" />
            Añadir documento
        </summary>
        <form class="document-upload__form" action="{{ route('biblioteca.documentos.store') }}" method="POST"
            enctype="multipart/form-data" data-document-link-form>
            @csrf
            <x-ui.field name="archivo" label="Archivo" required
                hint="PDF, JPG, PNG, DOC, DOCX, XLS, XLSX y DWG · máximo 20 MB">
                <x-ui.input name="archivo" type="file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.dwg"
                    required data-document-file />
                <span class="document-file-name" data-document-file-name>Ningún archivo seleccionado</span>
            </x-ui.field>
            <x-ui.field name="nombre" label="Nombre del documento" required>
                <x-ui.input name="nombre" maxlength="180" placeholder="Ej. Plano planta norte" required />
            </x-ui.field>
            <x-ui.field name="tipo_documento" label="Tipo documental" required class="document-type-field">
                <div class="document-type-options" role="radiogroup" aria-label="Tipo documental">
                    @foreach (\App\Models\Documento::TIPOS as $valor => $etiqueta)
                        <label class="document-type-option">
                            <input type="radio" name="tipo_documento" value="{{ $valor }}" @checked(old('tipo_documento') === $valor) required />
                            <span><x-ui.icon :name="\App\Models\Documento::iconoParaTipo($valor)" size="18" /></span>
                            <strong>{{ $etiqueta }}</strong>
                        </label>
                    @endforeach
                </div>
            </x-ui.field>
            @if ($cotizacionContexto)
                <input type="hidden" name="cliente_id" value="{{ $cotizacionContexto->cliente_id }}">
                <input type="hidden" name="cotizacion_id" value="{{ $cotizacionContexto->id }}">
                <x-ui.field name="cliente_contexto" label="Organización">
                    <x-ui.input name="cliente_contexto" :value="$cotizacionContexto->cliente->nombre_display" disabled />
                </x-ui.field>
                <x-ui.field name="cotizacion_contexto" label="Cotización">
                    <x-ui.input name="cotizacion_contexto" :value="$cotizacionContexto->codigo" disabled />
                </x-ui.field>
                @if (!$contextoGeneral)
                    <x-ui.field name="revision_servicio_id" label="Servicio cotizado"
                        hint="Opcional; sin selección se guardará como documento general">
                        <x-ui.select name="revision_servicio_id">
                            <option value="">Documento general de la cotización</option>
                            @foreach ($serviciosCotizacion as $servicioCotizado)
                                <option value="{{ $servicioCotizado->id }}" @selected((string) old('revision_servicio_id', $revisionServicioContexto?->id) === (string) $servicioCotizado->id)>
                                    Rev. {{ str_pad((string) $servicioCotizado->revision->revision, 2, '0', STR_PAD_LEFT) }} ·
                                    {{ $servicioCotizado->titulo }}
                                </option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>
                @endif
            @elseif ($plantaContexto)
                <input type="hidden" name="cliente_id" value="{{ $plantaContexto->cliente_id }}">
                <input type="hidden" name="vinculable_type" value="planta">
                <input type="hidden" name="vinculable_id" value="{{ $plantaContexto->id }}">
                <x-ui.field name="cliente_contexto" label="Organización">
                    <x-ui.input name="cliente_contexto" :value="$plantaContexto->cliente->nombre_display" disabled />
                </x-ui.field>
                <x-ui.field name="planta_contexto" label="Planta">
                    <x-ui.input name="planta_contexto" :value="$plantaContexto->nombre" disabled />
                </x-ui.field>
            @else
                <x-ui.field name="cliente_id" label="Organización" required>
                    <x-ui.select name="cliente_id" required data-document-client>
                        <option value="">Seleccionar organización</option>
                        @foreach ($clientes as $cliente)
                            <option value="{{ $cliente->id }}" @selected((string) old('cliente_id', $clienteId) === (string) $cliente->id)>{{ $cliente->nombre_display }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>
                <x-ui.field name="vinculable_type" label="Vincular también a">
                    <x-ui.select name="vinculable_type" data-document-link-type>
                        <option value="">Sin vínculo adicional</option>
                        @foreach (\App\Models\DocumentoVinculo::ETIQUETAS as $valor => $etiqueta)
                            @continue($valor === 'cliente')
                            <option value="{{ $valor }}" @selected(old('vinculable_type') === $valor)>{{ $etiqueta }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>
                <x-ui.field name="vinculable_id" label="Entidad relacionada" class="document-link-entity-field">
                    <div class="payment-combobox" data-document-entity-combobox>
                        <x-ui.icon name="link" size="17" class="payment-combobox__leading" />
                        <input
                            class="ui-control payment-combobox__input {{ $errors->has('vinculable_id') ? 'is-invalid' : '' }}"
                            type="text"
                            role="combobox"
                            aria-autocomplete="list"
                            aria-expanded="false"
                            aria-controls="document-entity-options"
                            placeholder="Selecciona primero una organización y un tipo de vínculo"
                            autocomplete="off"
                            value="{{ $opcionVinculoSeleccionada['etiqueta'] ?? '' }}"
                            data-document-entity-search
                            disabled
                        />
                        <button
                            class="payment-combobox__toggle"
                            type="button"
                            aria-label="Mostrar entidades relacionadas"
                            data-document-entity-toggle
                            disabled
                        >
                            <x-ui.icon name="chevron-down" size="17" />
                        </button>
                        <input
                            type="hidden"
                            name="vinculable_id"
                            value="{{ $idVinculoSeleccionado }}"
                            data-document-link-entity
                            disabled
                        />
                        <div
                            class="payment-combobox__list"
                            id="document-entity-options"
                            role="listbox"
                            data-document-entity-list
                            hidden
                        >
                            @foreach ($opcionesVinculo as $opcion)
                                <button
                                    type="button"
                                    role="option"
                                    class="payment-combobox__option"
                                    aria-selected="{{ $opcionVinculoSeleccionada === $opcion ? 'true' : 'false' }}"
                                    data-document-entity-option
                                    data-client="{{ $opcion['cliente_id'] }}"
                                    data-type="{{ $opcion['tipo'] }}"
                                    data-value="{{ $opcion['id'] }}"
                                    data-label="{{ $opcion['etiqueta'] }}"
                                >
                                    <strong>{{ $opcion['etiqueta'] }}</strong>
                                </button>
                            @endforeach
                            <div class="payment-combobox__empty" data-document-entity-empty hidden>
                                No se encontraron entidades relacionadas.
                            </div>
                        </div>
                    </div>
                </x-ui.field>
            @endif
            <x-ui.field name="descripcion" label="Descripción" hint="Opcional" class="document-upload__description">
                <x-ui.textarea name="descripcion" rows="2" maxlength="10000"
                    placeholder="Contexto breve para identificar el archivo" />
            </x-ui.field>
            <div class="document-upload__actions">
                <x-ui.button type="submit"><x-ui.icon name="plus" size="15" /><x-ui.icon name="file-text" size="16" /> Añadir documento</x-ui.button>
            </div>
        </form>
    </details>
</x-ui.panel>

@if ($vista === 'carpetas')
    <div class="document-browser">
        @if (!$plantaContexto && !$cotizacionContexto)
            <section class="document-browser__section document-browser__section--folders">
                <div class="document-section-heading">
                    <div><span>Explorar por contexto</span><h2>Plantas</h2></div>
                    <strong>{{ $plantasBiblioteca->count() }} {{ $plantasBiblioteca->count() === 1 ? 'planta' : 'plantas' }}</strong>
                </div>
                @if ($plantasBiblioteca->isEmpty())
                    <x-ui.empty-state icon="folder" title="Sin plantas" description="No hay plantas disponibles para explorar." />
                @else
                    <div class="document-folder-grid">
                        @foreach ($plantasBiblioteca as $planta)
                            <a class="document-folder-card" href="{{ route('biblioteca.index', ['planta' => $planta->getKey(), 'vista' => 'carpetas']) }}">
                                <span class="document-folder-card__icon"><x-ui.icon entity="planta" size="23" /></span>
                                <span class="document-folder-card__content">
                                    <small>Planta</small>
                                    <strong>{{ $planta->nombre }}</strong>
                                    <span>{{ $planta->cliente->nombre_display }}</span>
                                </span>
                                <span class="document-folder-card__metrics">
                                    <span><strong>{{ $planta->documentos_count }}</strong> documentos</span>
                                </span>
                                <span class="document-folder-card__action">Abrir <x-ui.icon name="arrow" size="15" /></span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </section>
        @elseif ($plantaContexto && !$cotizacionContexto)
            <section class="document-browser__section document-browser__section--folders">
                <div class="document-section-heading">
                    <div><span>Planta {{ $plantaContexto->nombre }}</span><h2>Cotizaciones</h2></div>
                    <strong>{{ $cotizacionesPlanta->count() }} {{ $cotizacionesPlanta->count() === 1 ? 'cotización' : 'cotizaciones' }}</strong>
                </div>
                @if ($cotizacionesPlanta->isEmpty())
                    <x-ui.empty-state icon="folder" title="Sin cotizaciones" description="Esta planta todavía no tiene cotizaciones asociadas." />
                @else
                    <div class="document-folder-grid">
                        @foreach ($cotizacionesPlanta as $cotizacion)
                            <a class="document-folder-card" href="{{ route('biblioteca.index', ['planta' => $plantaContexto->getKey(), 'cotizacion' => $cotizacion->getKey(), 'vista' => 'carpetas']) }}">
                                <span class="document-folder-card__icon document-folder-card__icon--commercial"><x-ui.icon entity="cotizacion" size="23" /></span>
                                <span class="document-folder-card__content">
                                    <small>{{ $cotizacion->codigo }}</small>
                                    <strong>{{ $cotizacion->revisionActual?->titulo ?? 'Sin título' }}</strong>
                                    <x-ui.badge :status="$cotizacion->estado" />
                                </span>
                                <span class="document-folder-card__metrics">
                                    <span><strong>{{ $cotizacion->documentos_count }}</strong> documentos</span>
                                    <span><strong>{{ $cotizacion->revisionActual?->servicios->count() ?? 0 }}</strong> servicios cotizados</span>
                                </span>
                                <span class="document-folder-card__action">Abrir cotización <x-ui.icon name="arrow" size="15" /></span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </section>
        @elseif ($cotizacionContexto && !$revisionServicioContexto)
            <section class="document-browser__section document-browser__section--folders document-browser__section--services">
                <div class="document-section-heading">
                    <div><span>{{ $cotizacionContexto->codigo }}</span><h2>Servicios cotizados</h2></div>
                    <strong>{{ $serviciosCotizacion->count() }} {{ $serviciosCotizacion->count() === 1 ? 'servicio' : 'servicios' }}</strong>
                </div>
                @if ($serviciosCotizacion->isEmpty())
                    <x-ui.empty-state icon="folder" title="Sin servicios cotizados" description="La revisión vigente no tiene servicios asociados." />
                @else
                    <div class="document-folder-grid">
                        @foreach ($serviciosCotizacion as $servicioCotizado)
                            <a class="document-folder-card" href="{{ route('biblioteca.index', ['planta' => $plantaNavegacion?->getKey(), 'cotizacion' => $cotizacionContexto->getKey(), 'revision_servicio' => $servicioCotizado->getKey(), 'vista' => 'carpetas']) }}">
                                <span class="document-folder-card__icon"><x-ui.icon entity="servicio" size="23" /></span>
                                <span class="document-folder-card__content">
                                    <small>Servicio</small>
                                    <strong>{{ $servicioCotizado->titulo }}</strong>
                                    <span>Rev. {{ str_pad((string) $servicioCotizado->revision->revision, 2, '0', STR_PAD_LEFT) }}</span>
                                </span>
                                <span class="document-folder-card__metrics">
                                    <span><strong>{{ $servicioCotizado->documentos_count }}</strong> documentos</span>
                                </span>
                                <span class="document-folder-card__action">Abrir servicio <x-ui.icon name="arrow" size="15" /></span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </section>
        @endif

        @if ($plantaContexto || $cotizacionContexto || $revisionServicioContexto || $buscar !== '' || $filtrosAvanzadosActivos)
            <section class="document-browser__section document-browser__section--files {{ $cotizacionContexto && !$revisionServicioContexto ? 'document-browser__section--first' : '' }}">
                <div class="document-section-heading">
                    <div>
                        <span>Archivos</span>
                        <h2>
                            @if ($revisionServicioContexto)
                                Documentos del servicio
                            @elseif ($cotizacionContexto)
                                Documentos generales
                            @else
                                {{ $plantaContexto ? 'Documentos generales de la planta' : 'Resultados de Biblioteca' }}
                            @endif
                        </h2>
                    </div>
                    <strong>{{ $documentos->total() }} {{ $documentos->total() === 1 ? 'documento' : 'documentos' }}</strong>
                </div>
                @if ($documentos->isEmpty())
                    <x-ui.empty-state icon="folder" title="Sin documentos" description="No hay documentos que coincidan con este contexto y sus filtros." />
                @else
                    <div class="document-file-list">
                        @foreach ($documentos as $documento)
                            <article class="document-file-row">
                                <span class="document-file-row__icon"><x-ui.icon :name="$documento->icono()" size="19" /></span>
                                <div class="document-file-row__content">
                                    <strong>{{ $documento->nombre }}</strong>
                                    <span>
                                        {{ \App\Models\Documento::TIPOS[$documento->tipo_documento] ?? ucfirst(str_replace('_', ' ', $documento->tipo_documento)) }}
                                        · {{ strtoupper($documento->extension) }}
                                        · {{ \Illuminate\Support\Number::fileSize((int) $documento->tamano, 1) }}
                                        · {{ $documento->created_at?->format('d/m/Y') ?? '—' }}
                                    </span>
                                    <div class="document-links">
                                        @forelse ($documento->vinculos as $vinculo)
                                            @php($relacionado = $vinculo->vinculable)
                                            @if ($relacionado)
                                                <span>{{ \App\Models\DocumentoVinculo::ETIQUETAS[array_search($vinculo->vinculable_type, \App\Models\DocumentoVinculo::TIPOS, true)] ?? 'Entidad' }} · {{ $relacionado->codigo ?? $relacionado->numero ?? $relacionado->folio ?? $relacionado->titulo ?? $relacionado->nombre_display ?? $relacionado->nombre ?? 'Registro' }}</span>
                                            @endif
                                        @empty
                                            <span class="document-links__empty">Sin contexto adicional</span>
                                        @endforelse
                                    </div>
                                </div>
                                <div class="document-actions">
                                    @if (in_array(strtolower($documento->extension), ['pdf', 'jpg', 'jpeg', 'png', 'xlsx'], true))
                                        <a class="document-action--preview" href="{{ route('biblioteca.documentos.preview', array_merge(['cliente' => $documento->cliente_id, 'documento' => $documento->getKey()], $contextoBiblioteca)) }}"><x-ui.icon name="search" size="16" /> Previsualizar</a>
                                    @endif
                                    <a href="{{ route('biblioteca.documentos.download', [$documento->cliente, $documento]) }}"><x-ui.icon name="download" size="16" /> Descargar</a>
                                    <a href="{{ route('biblioteca.documentos.links', [$documento->cliente, $documento]) }}"><x-ui.icon name="link" size="16" /> Gestionar vínculos</a>
                                </div>
                            </article>
                        @endforeach
                    </div>
                    <div class="document-pagination">{{ $documentos->links() }}</div>
                @endif
            </section>
        @endif
    </div>
@else
    <x-ui.panel flush class="document-library__panel">
        @if ($documentos->isEmpty())
            <x-ui.empty-state icon="folder" title="Sin documentos" description="No hay documentos que coincidan con los filtros seleccionados." />
        @else
            <div class="document-table-scroll">
                <table class="ui-table document-table">
                    <thead><tr><th>Documento</th><th>Organización</th><th>Vinculado a</th><th>Archivo</th><th>Fecha</th><th>Usuario</th><th class="text-right">Acciones</th></tr></thead>
                    <tbody>
                        @foreach ($documentos as $documento)
                            <tr>
                                <td><div class="document-name"><span><x-ui.icon :name="$documento->icono()" size="17" /></span><div><strong>{{ $documento->nombre }}</strong><small>{{ \App\Models\Documento::TIPOS[$documento->tipo_documento] ?? ucfirst(str_replace('_', ' ', $documento->tipo_documento)) }}</small></div></div></td>
                                <td><a class="table-primary" href="{{ route('clientes.show', $documento->cliente) }}">{{ $documento->cliente->nombre_display }}</a></td>
                                <td><div class="document-links">@forelse ($documento->vinculos as $vinculo)@php($relacionado = $vinculo->vinculable)@if ($relacionado)<span>{{ \App\Models\DocumentoVinculo::ETIQUETAS[array_search($vinculo->vinculable_type, \App\Models\DocumentoVinculo::TIPOS, true)] ?? 'Entidad' }} · {{ $relacionado->codigo ?? $relacionado->numero ?? $relacionado->folio ?? $relacionado->titulo ?? $relacionado->nombre_display ?? $relacionado->nombre ?? 'Registro' }}</span>@endif @empty<span class="document-links__empty">Sin vínculos</span>@endforelse</div></td>
                                <td><span class="document-format">{{ strtoupper($documento->extension) }}</span> <span class="document-table__size">{{ \Illuminate\Support\Number::fileSize((int) $documento->tamano, 1) }}</span></td>
                                <td class="numeric">{{ $documento->created_at?->format('d/m/Y') ?? '—' }}</td>
                                <td>{{ $documento->usuarioCreador?->name ?? 'No disponible' }}</td>
                                <td><div class="document-actions">
                                    @if (in_array(strtolower($documento->extension), ['pdf', 'jpg', 'jpeg', 'png', 'xlsx'], true))
                                        <a class="document-action--preview" href="{{ route('biblioteca.documentos.preview', array_merge(['cliente' => $documento->cliente_id, 'documento' => $documento->getKey()], $contextoBiblioteca)) }}"><x-ui.icon name="search" size="16" /> Previsualizar</a>
                                    @endif
                                    <a href="{{ route('biblioteca.documentos.download', [$documento->cliente, $documento]) }}"><x-ui.icon name="download" size="16" /> Descargar</a><a href="{{ route('biblioteca.documentos.links', [$documento->cliente, $documento]) }}"><x-ui.icon name="link" size="16" /> Gestionar vínculos</a>
                                </div></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="document-pagination">{{ $documentos->links() }}</div>
        @endif
    </x-ui.panel>
@endif
@endsection
