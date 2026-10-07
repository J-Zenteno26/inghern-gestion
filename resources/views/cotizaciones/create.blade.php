@extends("layouts.app")
@section("title", "Nueva cotización")
@section("content")
    @php
        $partidaBase = [
            "clase" => "servicio",
            "descripcion" => "",
            "cantidad" => 1,
            "unidad" => \App\Enums\UnidadPrecio::Servicio->value,
            "metodo_precio" => "a_criterio",
            "monto_sugerido" => "",
            "precio_unitario" => "",
            "justificacion_ajuste" => "",
        ];

        $serviciosIniciales = old("servicios");
        if (! is_array($serviciosIniciales)) {
            $serviciosIniciales = $servicioSeleccionado ? [[
                "tipo_servicio_id" => $servicioSeleccionado?->tipo_servicio_id,
                "catalogo_servicio_id" => $servicioSeleccionado?->catalogo_servicio_id,
                "servicio_id" => $servicioSeleccionado?->id,
                "partidas" => [$partidaBase],
            ]] : [];
        }

        $catalogosSeleccionados = collect($serviciosIniciales)
            ->pluck('catalogo_servicio_id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->all();

        $costosGeneralesIniciales = old('costos_generales', []);
    @endphp

    <div class="quote-editor">
        <x-ui.page-header
            eyebrow="Comercial"
            title="Nueva cotización"
            description="Selecciona los servicios, define su valor y agrega solo los costos asociados que realmente necesites."
        />

        <form method="POST" action="{{ route('cotizaciones.store') }}" data-quote-form novalidate>
            @csrf

            <div
                class="quote-validation-summary"
                role="alert"
                data-quote-validation-summary
                @unless ($errors->any()) hidden @endunless
            >
                <strong>Revisa los campos requeridos</strong>
                <span data-quote-validation-message>
                    @if ($errors->any())
                        Hay información incompleta o inválida. Corrige los campos marcados para guardar el borrador.
                    @endif
                </span>
            </div>

            <x-ui.panel class="quote-proposal-card">
                <x-slot:title>
                    <div class="quote-panel-heading">
                        <span class="quote-panel-heading__icon"><x-ui.icon name="file-text" size="20" /></span>
                        <div>
                            <h2 class="ui-panel__title">Datos de la propuesta</h2>
                            <span>Información comercial que identifica esta cotización.</span>
                        </div>
                    </div>
                </x-slot>

                <div class="quote-header-grid">
                    <x-ui.field name="cliente_id" label="Organización" required>
                        @if ($servicioSeleccionado)
                            <input type="hidden" name="cliente_id" value="{{ $servicioSeleccionado->cliente_id }}" />
                            <x-ui.select name="cliente_bloqueado" data-client-select disabled>
                                @foreach ($clientes as $cliente)
                                    @if ($cliente->id === $servicioSeleccionado->cliente_id)
                                        <option value="{{ $cliente->id }}" selected>{{ $cliente->nombre_display }}</option>
                                    @endif
                                @endforeach
                            </x-ui.select>
                        @else
                            <x-ui.select name="cliente_id" data-client-select required>
                                <option value="">Seleccionar organización</option>
                                @foreach ($clientes as $cliente)
                                    <option
                                        value="{{ $cliente->id }}"
                                        @selected(old('cliente_id', $clienteSeleccionado) == $cliente->id)
                                    >
                                        {{ $cliente->nombre_display }}
                                    </option>
                                @endforeach
                            </x-ui.select>
                        @endif
                    </x-ui.field>

                    <x-ui.field name="contacto_id" label="Contacto destinatario">
                        <x-ui.select name="contacto_id" data-contact-select>
                            <option value="">Sin contacto definido</option>
                            @foreach ($clientes as $cliente)
                                @foreach ($cliente->contactos as $contacto)
                                    <option
                                        value="{{ $contacto->id }}"
                                        data-client="{{ $cliente->id }}"
                                        @selected(old('contacto_id') == $contacto->id)
                                    >
                                        {{ $contacto->nombre }}{{ $contacto->cargo ? ' · '.$contacto->cargo : '' }}
                                    </option>
                                @endforeach
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>

                    <x-ui.field name="titulo" label="Título de la propuesta" required>
                        <x-ui.input
                            name="titulo"
                            required
                            placeholder="Ej. Actualización de documentación de procesos"
                        />
                    </x-ui.field>

                    <x-ui.field name="moneda" label="Moneda" required>
                        <x-ui.select name="moneda">
                            <option @selected(old('moneda', 'CLP') === 'CLP')>CLP</option>
                            <option @selected(old('moneda') === 'UF')>UF</option>
                            <option @selected(old('moneda') === 'USD')>USD</option>
                        </x-ui.select>
                    </x-ui.field>

                    <x-ui.field name="iva_porcentaje" label="IVA (%)" required>
                        <x-ui.input
                            name="iva_porcentaje"
                            type="number"
                            :value="old('iva_porcentaje', 19)"
                            min="0"
                            max="100"
                            step="0.01"
                            data-iva
                        />
                    </x-ui.field>
                </div>
            </x-ui.panel>

            <div class="quote-workspace">
                <div class="quote-workspace__main">
                    <x-ui.panel class="quote-services-panel">
                        <x-slot:title>
                            <div class="quote-panel-heading quote-panel-heading--services">
                                <span class="quote-panel-heading__icon"><x-ui.icon name="briefcase-business" size="20" /></span>
                                <div>
                                    <h2 class="ui-panel__title">Catálogo de servicios</h2>
                                    <span>Selecciona uno o varios servicios para incorporarlos a la propuesta.</span>
                                </div>
                            </div>
                        </x-slot>
                        <x-slot:actions>
                            <button class="ui-button ui-button--outline ui-button--small" type="button" data-toggle-service-catalog aria-expanded="true">
                                <span data-catalog-toggle-label>Ocultar catálogo</span>
                                <x-ui.icon name="chevron-down" size="15" />
                            </button>
                        </x-slot>

                        <div class="quote-service-catalog" data-service-catalog>
                            <div class="toolbar quote-service-catalog__toolbar">
                                <div class="toolbar__search">
                                    <x-ui.icon name="search" size="16" />
                                    <input class="ui-control" type="search" placeholder="Buscar por código, nombre o descripción" data-catalog-search />
                                </div>
                                <select class="ui-control toolbar__select" data-catalog-type-filter aria-label="Filtrar por tipo de servicio">
                                    <option value="">Todos los tipos</option>
                                    @foreach ($tiposDisponibles as $tipo)
                                        <option value="{{ $tipo->id }}">{{ $tipo->nombre }}</option>
                                    @endforeach
                                </select>
                                <span class="quote-service-catalog__counter"><strong data-selected-services-count>0</strong> seleccionados</span>
                            </div>

                            <div class="quote-services-guide">
                                <x-ui.icon name="circle-check" size="17" />
                                <span>Los servicios operativos existentes se vincularán sin duplicarse. Los demás quedarán pendientes de creación al aceptar la cotización.</span>
                            </div>

                            <div class="quote-service-catalog__groups" data-catalog-groups>
                                @foreach ($tiposDisponibles as $tipo)
                                    @php($serviciosDelTipo = $catalogoServicios->where('tipo_servicio_id', $tipo->id))
                                    <section class="quote-catalog-group" data-catalog-group data-type-id="{{ $tipo->id }}">
                                        <div class="quote-catalog-group__header">
                                            <div>
                                                <span>Tipo de servicio</span>
                                                <h3>{{ $tipo->nombre }}</h3>
                                            </div>
                                            <span class="quote-catalog-group__count">{{ $serviciosDelTipo->count() }} {{ $serviciosDelTipo->count() === 1 ? 'servicio' : 'servicios' }}</span>
                                        </div>
                                        <div class="quote-catalog-group__grid">
                                            @foreach ($serviciosDelTipo as $catalogo)
                                                <label
                                                    class="quote-catalog-card"
                                                    data-catalog-card
                                                    data-type-id="{{ $tipo->id }}"
                                                    data-search="{{ str($catalogo->codigo.' '.$catalogo->nombre.' '.$catalogo->descripcion)->lower() }}"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        value="{{ $catalogo->id }}"
                                                        data-catalog-checkbox
                                                        @checked(in_array((string) $catalogo->id, $catalogosSeleccionados, true))
                                                    />
                                                    <span class="quote-catalog-card__check" aria-hidden="true"></span>
                                                    <span class="quote-catalog-card__content">
                                                        <span class="quote-catalog-card__topline">
                                                            <strong>{{ $catalogo->codigo }}</strong>
                                                            <span class="quote-catalog-card__status" data-catalog-link-status>Nuevo servicio</span>
                                                        </span>
                                                        <span class="quote-catalog-card__name">{{ $catalogo->nombre }}</span>
                                                        @if ($catalogo->descripcion)
                                                            <span class="quote-catalog-card__description">{{ $catalogo->descripcion }}</span>
                                                        @endif
                                                        <span class="quote-catalog-card__reference">
                                                            @if ($catalogo->precio_base !== null)
                                                                Referencia: {{ $catalogo->moneda_precio }} {{ number_format((float) $catalogo->precio_base, 0, ',', '.') }}{{ $catalogo->unidad_precio ? ' · '.$catalogo->unidad_precio : '' }}
                                                            @else
                                                                Sin referencia económica
                                                            @endif
                                                        </span>
                                                    </span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </section>
                                @endforeach
                                <p class="quote-service-catalog__empty" data-catalog-empty hidden>No hay servicios que coincidan con la búsqueda.</p>
                            </div>
                        </div>

                        <div class="quote-included-services">
                            <div class="quote-included-services__heading">
                                <div>
                                    <span>Composición de la propuesta</span>
                                    <h3>Servicios incluidos</h3>
                                </div>
                                <span data-included-services-summary>Ningún servicio seleccionado</span>
                            </div>
                            <div class="quote-included-services__empty" data-included-services-empty>
                                Selecciona servicios desde el catálogo para comenzar.
                            </div>
                        </div>

                        <div class="stack" data-quote-services>
                            @foreach ($serviciosIniciales as $serviceIndex => $servicioForm)
                                <section class="quote-service" data-quote-service data-catalog-id="{{ $servicioForm['catalogo_servicio_id'] ?? '' }}">
                                    <div class="quote-service__header">
                                        <div class="quote-service__title-group">
                                            <span class="quote-service__index" data-service-order>{{ str_pad((string) ($loop->iteration), 2, '0', STR_PAD_LEFT) }}</span>
                                            <div>
                                                <span class="quote-service__eyebrow"><span data-summary-code>Servicio cotizado</span> · <span data-quote-service-status>Nuevo servicio</span></span>
                                                <strong data-summary-name>Servicio seleccionado</strong>
                                            </div>
                                        </div>
                                        <div class="quote-service__header-actions">
                                            <strong class="quote-service__header-subtotal" data-service-subtotal>$0</strong>
                                            <button
                                                class="ui-button ui-button--ghost ui-button--icon quote-service__collapse"
                                                type="button"
                                                data-toggle-quote-service
                                                aria-expanded="true"
                                                title="Minimizar servicio"
                                            >
                                                <x-ui.icon name="chevron-down" size="17" />
                                            </button>
                                            <button
                                                class="ui-button ui-button--danger ui-button--icon"
                                                type="button"
                                                data-remove-quote-service
                                                title="Quitar servicio"
                                            >
                                                <x-ui.icon name="trash" size="16" />
                                            </button>
                                        </div>
                                    </div>

                                    <input
                                        type="hidden"
                                        name="servicios[{{ $serviceIndex }}][servicio_id]"
                                        value="{{ $servicioForm['servicio_id'] ?? '' }}"
                                        data-operational-service
                                        @if (! empty($servicioForm['servicio_id']) && empty($servicioForm['catalogo_servicio_id'])) data-preserve="true" @endif
                                    />
                                    <input type="hidden" name="servicios[{{ $serviceIndex }}][tipo_servicio_id]" value="{{ $servicioForm['tipo_servicio_id'] ?? '' }}" data-quote-service-type />
                                    <input type="hidden" name="servicios[{{ $serviceIndex }}][catalogo_servicio_id]" value="{{ $servicioForm['catalogo_servicio_id'] ?? '' }}" data-quote-catalog-service />

                                    <div class="quote-service__body" data-quote-service-body>
                                    <div class="quote-service__context" data-quote-service-summary>
                                        <div class="quote-service__identity">
                                            <p data-summary-description>La descripción configurada en el catálogo aparecerá aquí como contexto.</p>
                                        </div>
                                        <div class="quote-service__operational">
                                            <span class="quote-service__operational-label">Vínculo operativo</span>
                                            <span class="ui-badge ui-badge--info" data-summary-operational>Sin definir</span>
                                        </div>
                                    </div>

                                        <div class="quote-service__lines quote-service__lines--direct" data-lines>
                                        @foreach (($servicioForm['partidas'] ?? [$partidaBase]) as $i => $partida)
                                            @include('cotizaciones.partials.linea', [
                                                'serviceIndex' => $serviceIndex,
                                                'i' => $i,
                                                'partida' => $partida,
                                            ])
                                        @endforeach
                                        </div>
                                    </div>
                                </section>
                            @endforeach
                        </div>

                        <section class="quote-general-costs" aria-labelledby="quote-general-costs-title">
                            <div class="quote-general-costs__header">
                                <div>
                                    <span>Aplican a la propuesta completa</span>
                                    <h3 id="quote-general-costs-title">Costos asociados a la propuesta</h3>
                                    <p>Se suman al total general sin alterar el subtotal de cada servicio.</p>
                                </div>
                                <strong data-general-costs-subtotal>$0</strong>
                            </div>
                            <div class="quote-general-costs__actions" aria-label="Agregar costo global">
                                <button type="button" class="quote-cost-chip" data-add-general-cost="hospedaje"><x-ui.icon name="bed-double" size="15" /> Hospedaje</button>
                                <button type="button" class="quote-cost-chip" data-add-general-cost="transporte"><x-ui.icon name="car-front" size="15" /> Transporte</button>
                                <button type="button" class="quote-cost-chip" data-add-general-cost="alimentacion"><x-ui.icon name="utensils" size="15" /> Alimentación</button>
                                <button type="button" class="quote-cost-chip" data-add-general-cost="movilizacion"><x-ui.icon name="map-pinned" size="15" /> Movilización</button>
                                <button type="button" class="quote-cost-chip" data-add-general-cost="otro"><x-ui.icon name="circle-plus" size="15" /> Otro costo</button>
                            </div>
                            <div class="quote-general-costs__lines" data-general-costs>
                                @foreach ($costosGeneralesIniciales as $costIndex => $costo)
                                    <article class="quote-general-cost" data-general-cost>
                                        <input type="hidden" name="costos_generales[{{ $costIndex }}][clase]" value="{{ $costo['clase'] ?? 'costo_adicional' }}" data-general-cost-class />
                                        <input type="hidden" name="costos_generales[{{ $costIndex }}][cantidad]" value="1" />
                                        <input type="hidden" name="costos_generales[{{ $costIndex }}][unidad]" value="servicio" />
                                        <input type="hidden" name="costos_generales[{{ $costIndex }}][metodo_precio]" value="a_criterio" />
                                        <input type="hidden" name="costos_generales[{{ $costIndex }}][monto_sugerido]" value="{{ $costo['monto_sugerido'] ?? $costo['precio_unitario'] ?? 0 }}" data-general-cost-suggested />
                                        <input type="hidden" name="costos_generales[{{ $costIndex }}][justificacion_ajuste]" value="{{ $costo['justificacion_ajuste'] ?? 'Costo global definido para la propuesta.' }}" />
                                        <label class="ui-field">
                                            <span class="ui-field__label">Concepto</span>
                                            <input class="ui-control" name="costos_generales[{{ $costIndex }}][descripcion]" value="{{ $costo['descripcion'] ?? '' }}" required data-general-cost-description />
                                        </label>
                                        <label class="ui-field">
                                            <span class="ui-field__label">Valor neto</span>
                                            <span class="quote-money-input">
                                                <span>$</span>
                                                <input class="ui-control" type="number" min="0" step="0.01" name="costos_generales[{{ $costIndex }}][precio_unitario]" value="{{ $costo['precio_unitario'] ?? 0 }}" required data-general-cost-value />
                                            </span>
                                        </label>
                                        <button class="ui-button ui-button--danger ui-button--icon" type="button" data-remove-general-cost title="Eliminar costo"><x-ui.icon name="trash" size="16" /></button>
                                    </article>
                                @endforeach
                                <p class="quote-general-costs__empty" data-general-costs-empty @if (count($costosGeneralesIniciales)) hidden @endif>No hay costos globales agregados.</p>
                            </div>
                        </section>
                    </x-ui.panel>
                </div>

                <aside class="quote-workspace__summary">
                    <div class="quote-summary-card">
                        <div class="quote-summary-card__header">
                            <span class="quote-summary-card__icon"><x-ui.icon name="receipt" size="20" /></span>
                            <div>
                                <span class="quote-summary-card__eyebrow">Vista previa</span>
                                <strong>Propuesta comercial</strong>
                            </div>
                        </div>
                        <div class="quote-preview__identity">
                            <span data-preview-title>Propuesta sin título</span>
                            <strong data-preview-organization>Organización sin seleccionar</strong>
                            <small data-preview-contact>Sin contacto definido</small>
                        </div>
                        <div class="quote-preview__section">
                            <span class="quote-preview__label">Servicios</span>
                            <div class="quote-preview__items" data-preview-services></div>
                            <p class="quote-preview__empty" data-preview-services-empty>Selecciona servicios para previsualizarlos.</p>
                        </div>
                        <div class="quote-preview__section">
                            <span class="quote-preview__label">Costos asociados</span>
                            <div class="quote-preview__items" data-preview-costs></div>
                            <p class="quote-preview__empty" data-preview-costs-empty>Sin costos globales.</p>
                        </div>
                        <div class="quote-summary">
                            <div class="quote-summary__row">
                                <span>Subtotal neto</span>
                                <strong data-subtotal>$0</strong>
                            </div>
                            <div class="quote-summary__row">
                                <span>IVA</span>
                                <strong data-tax>$0</strong>
                            </div>
                            <div class="quote-summary__row quote-summary__row--total">
                                <span>Total</span>
                                <strong data-total>$0</strong>
                            </div>
                        </div>
                        <p class="quote-summary-card__hint">
                            <x-ui.icon name="calculator" size="15" />
                            La vista previa se actualiza automáticamente mientras construyes la propuesta.
                        </p>
                        <div class="quote-summary-card__actions">
                            <x-ui.button type="submit">
                                <x-ui.icon name="file-text" size="16" />
                                Guardar borrador
                            </x-ui.button>
                            <x-ui.button :href="route('cotizaciones.index')" variant="outline">Cancelar</x-ui.button>
                        </div>
                    </div>
                </aside>
            </div>
        </form>
    </div>

    <script type="application/json" data-quote-pricing>
        {!! json_encode($configuracionPrecios, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}
    </script>
    <script type="application/json" data-operational-services>
        {!! json_encode($serviciosOperativosPorCliente, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}
    </script>

    <template data-line-template>
        @include('cotizaciones.partials.linea', [
            'serviceIndex' => '__SERVICE_INDEX__',
            'i' => '__LINE_INDEX__',
            'partida' => $partidaBase,
        ])
    </template>

    <template data-quote-service-template>
        <section class="quote-service" data-quote-service data-catalog-id="__CATALOG_ID__">
            <div class="quote-service__header">
                <div class="quote-service__title-group">
                    <span class="quote-service__index" data-service-order>00</span>
                    <div>
                        <span class="quote-service__eyebrow"><span data-summary-code>Servicio cotizado</span> · <span data-quote-service-status>Nuevo servicio</span></span>
                        <strong data-summary-name>Servicio seleccionado</strong>
                    </div>
                </div>
                <div class="quote-service__header-actions">
                    <strong class="quote-service__header-subtotal" data-service-subtotal>$0</strong>
                    <button class="ui-button ui-button--ghost ui-button--icon quote-service__collapse" type="button" data-toggle-quote-service aria-expanded="true" title="Minimizar servicio">
                        <x-ui.icon name="chevron-down" size="17" />
                    </button>
                    <button class="ui-button ui-button--danger ui-button--icon" type="button" data-remove-quote-service title="Quitar servicio">
                        <x-ui.icon name="trash" size="16" />
                    </button>
                </div>
            </div>
            <input type="hidden" name="servicios[__SERVICE_INDEX__][servicio_id]" value="" data-operational-service />
            <input type="hidden" name="servicios[__SERVICE_INDEX__][tipo_servicio_id]" value="__TYPE_ID__" data-quote-service-type />
            <input type="hidden" name="servicios[__SERVICE_INDEX__][catalogo_servicio_id]" value="__CATALOG_ID__" data-quote-catalog-service />
            <div class="quote-service__body" data-quote-service-body>
            <div class="quote-service__context" data-quote-service-summary>
                <div class="quote-service__identity">
                    <p data-summary-description>La descripción configurada en el catálogo aparecerá aquí como contexto.</p>
                </div>
                <div class="quote-service__operational">
                    <span class="quote-service__operational-label">Vínculo operativo</span>
                    <span class="ui-badge ui-badge--info" data-summary-operational>Sin definir</span>
                </div>
            </div>
                <div class="quote-service__lines quote-service__lines--direct" data-lines>
                @include('cotizaciones.partials.linea', [
                    'serviceIndex' => '__SERVICE_INDEX__',
                    'i' => 0,
                    'partida' => $partidaBase,
                ])
                </div>
            </div>
        </section>
    </template>

    <template data-general-cost-template>
        <article class="quote-general-cost" data-general-cost>
            <input type="hidden" name="costos_generales[__COST_INDEX__][clase]" value="__COST_CLASS__" data-general-cost-class />
            <input type="hidden" name="costos_generales[__COST_INDEX__][cantidad]" value="1" />
            <input type="hidden" name="costos_generales[__COST_INDEX__][unidad]" value="servicio" />
            <input type="hidden" name="costos_generales[__COST_INDEX__][metodo_precio]" value="a_criterio" />
            <input type="hidden" name="costos_generales[__COST_INDEX__][monto_sugerido]" value="0" data-general-cost-suggested />
            <input type="hidden" name="costos_generales[__COST_INDEX__][justificacion_ajuste]" value="__COST_JUSTIFICATION__" />
            <label class="ui-field">
                <span class="ui-field__label">Concepto</span>
                <input class="ui-control" name="costos_generales[__COST_INDEX__][descripcion]" value="__COST_DESCRIPTION__" required data-general-cost-description />
            </label>
            <label class="ui-field">
                <span class="ui-field__label">Valor neto</span>
                <span class="quote-money-input">
                    <span>$</span>
                    <input class="ui-control" type="number" min="0" step="0.01" name="costos_generales[__COST_INDEX__][precio_unitario]" value="0" required data-general-cost-value />
                </span>
            </label>
            <button class="ui-button ui-button--danger ui-button--icon" type="button" data-remove-general-cost title="Eliminar costo"><x-ui.icon name="trash" size="16" /></button>
        </article>
    </template>
@endsection
