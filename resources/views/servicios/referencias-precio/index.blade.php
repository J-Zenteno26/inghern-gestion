@extends("layouts.app")
@section("title", "Referencias de precio")
@section("content")
    <x-ui.page-header
        eyebrow="Operación"
        title="Referencias de precio"
        description="Administra la base económica de cada servicio normalizado. Las variables del tipo se aplicarán en una etapa posterior."
    />

    <div
        class="price-reference-workspace"
        data-price-references
        data-error-catalog="{{ old("_catalogo_servicio_id", "") }}"
    >
        <div class="toolbar">
            <div class="toolbar__search">
                <x-ui.icon name="search" />
                <input
                    class="ui-control"
                    type="search"
                    placeholder="Buscar nombre, código, descripción o tipo"
                    aria-label="Buscar referencias de precio"
                    data-price-search
                />
            </div>
            <select
                class="ui-control"
                aria-label="Filtrar referencias por estado"
                data-price-status
            >
                <option value="todos">Todos</option>
                <option value="configurado">Configurados</option>
                <option value="sin_precio">Sin precio base</option>
            </select>
            <x-ui.button
                type="button"
                variant="outline"
                size="small"
                data-price-clear
            >
                Limpiar
            </x-ui.button>
        </div>

        <p class="muted price-reference-help">
            El nombre y el tipo identifican el catálogo y no se editan desde
            esta pantalla. Un servicio sin precio base no podrá usar el cálculo
            normalizado automático.
        </p>

        <div class="price-reference-groups" data-price-groups>
            @forelse ($tipos as $tipo)
                @php
                    $total = $tipo->catalogoServicios->count();
                    $configurados = $tipo->catalogoServicios
                        ->whereNotNull("precio_base")
                        ->count();
                    $catalogoConError = (int) old(
                        "_catalogo_servicio_id",
                    );
                    $tipoConError =
                        $catalogoConError > 0 &&
                        $tipo->catalogoServicios->contains(
                            fn($catalogo) =>
                                $catalogo->id === $catalogoConError,
                        );
                    $panelId = "tipo-servicio-".$tipo->id;
                @endphp
                <section
                    class="price-reference-group"
                    data-price-group
                    data-empty-group="{{ $total === 0 ? "true" : "false" }}"
                >
                    <button
                        class="price-reference-group__toggle"
                        type="button"
                        aria-expanded="{{ $tipoConError ? "true" : "false" }}"
                        aria-controls="{{ $panelId }}"
                        data-price-group-toggle
                    >
                        <span class="price-reference-group__heading">
                            <span class="price-reference-group__name">
                                {{ $tipo->nombre }}
                            </span>
                            <span class="price-reference-group__meta">
                                {{ $tipo->codigo }}{{ $tipo->familia ? " · ".$tipo->familia : "" }}
                            </span>
                        </span>
                        <span class="price-reference-group__summary">
                            <span>
                                {{ $configurados }} de {{ $total }} configurados
                            </span>
                            <x-ui.icon
                                class="price-reference-group__indicator"
                                name="arrow"
                                size="17"
                            />
                        </span>
                    </button>

                    <div
                        id="{{ $panelId }}"
                        class="price-reference-group__panel"
                        data-price-group-panel
                        @unless ($tipoConError) hidden @endunless
                    >
                        @if ($tipo->catalogoServicios->isEmpty())
                            <div class="price-reference-group__empty">
                                <p class="muted">
                                    Este tipo todavía no tiene servicios
                                    normalizados.
                                </p>
                            </div>
                        @else
                            <div class="price-reference-list">
                                @foreach ($tipo->catalogoServicios as $catalogo)
                                    @php
                                        $esFormularioAnterior =
                                            $catalogoConError === $catalogo->id;
                                        $descripcion = $esFormularioAnterior
                                            ? old("descripcion")
                                            : $catalogo->descripcion;
                                        $precioBase = $esFormularioAnterior
                                            ? old("precio_base")
                                            : $catalogo->precio_base;
                                        $moneda = $esFormularioAnterior
                                            ? old("moneda_precio")
                                            : $catalogo->moneda_precio;
                                        $unidad = $esFormularioAnterior
                                            ? old("unidad_precio")
                                            : $catalogo->unidad_precio;
                                        $unidadAnterior =
                                            filled($catalogo->unidad_precio) &&
                                            \App\Enums\UnidadPrecio::tryFrom(
                                                $catalogo->unidad_precio,
                                            ) === null
                                                ? $catalogo->unidad_precio
                                                : null;
                                        $configurado =
                                            $catalogo->precio_base !== null;
                                        $editorId =
                                            "editor-catalogo-".$catalogo->id;
                                        $textoBusqueda = mb_strtolower(
                                            implode(" ", [
                                                $catalogo->nombre,
                                                $catalogo->codigo,
                                                $catalogo->descripcion,
                                                $tipo->nombre,
                                            ]),
                                        );
                                    @endphp
                                    <article
                                        class="price-reference-item {{ $esFormularioAnterior ? "is-editing" : "" }}"
                                        data-price-item
                                        data-catalog-id="{{ $catalogo->id }}"
                                        data-configured="{{ $configurado ? "true" : "false" }}"
                                        data-search="{{ $textoBusqueda }}"
                                    >
                                        <div class="price-reference-row">
                                            <div>
                                                <span class="table-primary">
                                                    {{ $catalogo->nombre }}
                                                </span>
                                                <span class="table-secondary">
                                                    {{ $catalogo->codigo }}
                                                </span>
                                            </div>
                                            <p class="price-reference-row__description">
                                                {{ $catalogo->descripcion ?: "Sin descripción." }}
                                            </p>
                                            <div class="price-reference-row__price numeric">
                                                @if ($configurado)
                                                    <strong>
                                                        {{ $catalogo->moneda_precio }}
                                                        {{ number_format((float) $catalogo->precio_base, 2, ",", ".") }}
                                                    </strong>
                                                    <span class="table-secondary">
                                                        {{ $catalogo->unidad_precio ?: "Sin unidad" }}
                                                    </span>
                                                @else
                                                    <span class="muted">
                                                        Precio no definido
                                                    </span>
                                                @endif
                                            </div>
                                            <x-ui.badge
                                                :status="$configurado ? 'Configurado' : 'Sin precio base'"
                                                :tone="$configurado ? 'success' : 'warning'"
                                            />
                                            <x-ui.button
                                                type="button"
                                                variant="outline"
                                                size="small"
                                                :aria-expanded="$esFormularioAnterior ? 'true' : 'false'"
                                                aria-controls="{{ $editorId }}"
                                                data-price-edit
                                            >
                                                Editar referencia
                                                <x-ui.icon
                                                    class="price-reference-row__edit-icon"
                                                    name="arrow"
                                                    size="14"
                                                />
                                            </x-ui.button>
                                        </div>

                                        <div
                                            id="{{ $editorId }}"
                                            class="price-reference-editor"
                                            data-price-editor
                                            @unless ($esFormularioAnterior) hidden @endunless
                                        >
                                            <form
                                                id="catalogo-{{ $catalogo->id }}"
                                                method="POST"
                                                action="{{ route("servicios.referencias-precio.update", $catalogo) }}"
                                            >
                                                @csrf
                                                @method("PATCH")
                                                <input
                                                    type="hidden"
                                                    name="_catalogo_servicio_id"
                                                    value="{{ $catalogo->id }}"
                                                />

                                                <div class="price-reference-editor__identity">
                                                    <span class="table-primary">
                                                        {{ $catalogo->nombre }}
                                                    </span>
                                                    <span class="table-secondary">
                                                        {{ $catalogo->codigo }} ·
                                                        {{ $tipo->nombre }}
                                                    </span>
                                                </div>

                                                <div class="form-grid">
                                                    <div class="ui-field form-col-12">
                                                        <label
                                                            class="ui-field__label"
                                                            for="descripcion-{{ $catalogo->id }}"
                                                        >
                                                            Descripción
                                                        </label>
                                                        <textarea
                                                            id="descripcion-{{ $catalogo->id }}"
                                                            class="ui-control {{ $esFormularioAnterior && $errors->has("descripcion") ? "is-invalid" : "" }}"
                                                            name="descripcion"
                                                        >{{ $descripcion }}</textarea>
                                                        @if ($esFormularioAnterior && $errors->has("descripcion"))
                                                            <span class="ui-field__error">
                                                                {{ $errors->first("descripcion") }}
                                                            </span>
                                                        @endif
                                                    </div>
                                                    <div class="ui-field form-col-4">
                                                        <label
                                                            class="ui-field__label"
                                                            for="precio-base-{{ $catalogo->id }}"
                                                        >
                                                            Precio base
                                                        </label>
                                                        <input
                                                            id="precio-base-{{ $catalogo->id }}"
                                                            class="ui-control {{ $esFormularioAnterior && $errors->has("precio_base") ? "is-invalid" : "" }}"
                                                            name="precio_base"
                                                            type="number"
                                                            min="0"
                                                            step="0.01"
                                                            value="{{ $precioBase }}"
                                                        />
                                                        @if ($esFormularioAnterior && $errors->has("precio_base"))
                                                            <span class="ui-field__error">
                                                                {{ $errors->first("precio_base") }}
                                                            </span>
                                                        @endif
                                                    </div>
                                                    <div class="ui-field form-col-4">
                                                        <label
                                                            class="ui-field__label"
                                                            for="moneda-precio-{{ $catalogo->id }}"
                                                        >
                                                            Moneda
                                                        </label>
                                                        <select
                                                            id="moneda-precio-{{ $catalogo->id }}"
                                                            class="ui-control {{ $esFormularioAnterior && $errors->has("moneda_precio") ? "is-invalid" : "" }}"
                                                            name="moneda_precio"
                                                            required
                                                        >
                                                            @foreach (["CLP", "UF", "USD"] as $opcionMoneda)
                                                                <option
                                                                    value="{{ $opcionMoneda }}"
                                                                    @selected($moneda === $opcionMoneda)
                                                                >
                                                                    {{ $opcionMoneda }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                        @if ($esFormularioAnterior && $errors->has("moneda_precio"))
                                                            <span class="ui-field__error">
                                                                {{ $errors->first("moneda_precio") }}
                                                            </span>
                                                        @endif
                                                    </div>
                                                    <div class="ui-field form-col-4">
                                                        <label
                                                            class="ui-field__label"
                                                            for="unidad-precio-{{ $catalogo->id }}"
                                                        >
                                                            Unidad del precio
                                                        </label>
                                                        <select
                                                            id="unidad-precio-{{ $catalogo->id }}"
                                                            class="ui-control {{ $esFormularioAnterior && $errors->has("unidad_precio") ? "is-invalid" : "" }}"
                                                            name="unidad_precio"
                                                        >
                                                            <option value="">
                                                                Selecciona una unidad
                                                            </option>
                                                            @if ($unidadAnterior)
                                                                <option
                                                                    value=""
                                                                    disabled
                                                                    @selected($unidad === $unidadAnterior)
                                                                >
                                                                    Valor anterior:
                                                                    {{ $unidadAnterior }}
                                                                </option>
                                                            @endif
                                                            @foreach (\App\Enums\UnidadPrecio::cases() as $unidadPrecio)
                                                                <option
                                                                    value="{{ $unidadPrecio->value }}"
                                                                    @selected($unidad === $unidadPrecio->value)
                                                                >
                                                                    {{ $unidadPrecio->etiqueta() }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                        @if ($esFormularioAnterior && $errors->has("unidad_precio"))
                                                            <span class="ui-field__error">
                                                                {{ $errors->first("unidad_precio") }}
                                                            </span>
                                                        @endif
                                                    </div>
                                                </div>

                                                @if ($precioBase === null || $precioBase === "")
                                                    <p class="flash flash--warning price-reference-editor__warning">
                                                        Sin precio base: no podrá
                                                        usar cálculo normalizado
                                                        automático.
                                                    </p>
                                                @endif

                                                <div class="form-actions">
                                                    <x-ui.button
                                                        type="button"
                                                        variant="ghost"
                                                        size="small"
                                                        data-price-cancel
                                                    >
                                                        Cancelar
                                                    </x-ui.button>
                                                    <x-ui.button
                                                        type="submit"
                                                        size="small"
                                                    >
                                                        Guardar referencia
                                                    </x-ui.button>
                                                </div>
                                            </form>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </section>
            @empty
                <x-ui.empty-state
                    icon="briefcase"
                    title="No hay tipos de servicio"
                    description="Carga primero el catálogo operativo para administrar sus referencias."
                />
            @endforelse
        </div>

        <div data-price-no-results hidden>
            <x-ui.empty-state
                icon="search"
                title="No hay referencias coincidentes"
                description="Prueba con otros términos o limpia la búsqueda y los filtros."
            >
                <x-slot:action>
                    <x-ui.button
                        type="button"
                        size="small"
                        data-price-clear
                    >
                        Limpiar filtros
                    </x-ui.button>
                </x-slot>
            </x-ui.empty-state>
        </div>
    </div>
@endsection
