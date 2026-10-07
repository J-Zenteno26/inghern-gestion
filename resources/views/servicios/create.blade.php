@extends("layouts.app")
@section("title", "Nuevo servicio")
@section("content")
    <div class="service-create-page">
        <x-ui.page-header
            eyebrow="Operación"
            title="Nuevo servicio"
            description="Registra el trabajo como unidad operativa; podrá relacionarse con una o más cotizaciones."
        >
            <x-slot:actions>
                <x-ui.button :href="route('servicios.index')" variant="outline">
                    Volver a servicios
                </x-ui.button>
            </x-slot>
        </x-ui.page-header>
        <form method="POST" action="{{ route("servicios.store") }}">
            @csrf
            <div class="service-form-layout">
                <x-ui.panel>
                    <x-slot:title>
                        <h2 class="ui-panel__title">Definición del servicio</h2>
                    </x-slot>
                    <div class="form-grid">
                        <x-ui.field
                            class="form-col-6"
                            name="cliente_id"
                            label="Cliente"
                            required
                        >
                            <x-ui.select name="cliente_id" data-client-select required>
                                <option value="">Seleccionar cliente</option>
                                @foreach ($clientes as $cliente)
                                    <option
                                        value="{{ $cliente->id }}"
                                        @selected(old("cliente_id", $clienteSeleccionado) == $cliente->id)
                                    >
                                        {{ $cliente->nombre_display }}
                                    </option>
                                @endforeach
                            </x-ui.select>
                        </x-ui.field>
                        <x-ui.field
                            class="form-col-6"
                            name="tipo_servicio_id"
                            label="Tipo de servicio"
                            required
                        >
                            <x-ui.select
                                name="tipo_servicio_id"
                                data-service-type-select
                                required
                            >
                                <option value="">Seleccionar tipo</option>
                                @foreach ($tipos as $tipo)
                                    <option
                                        value="{{ $tipo->id }}"
                                        @selected(old("tipo_servicio_id") == $tipo->id)
                                    >
                                        {{ $tipo->nombre }}
                                    </option>
                                @endforeach
                            </x-ui.select>
                        </x-ui.field>
                        <x-ui.field
                            class="form-col-6"
                            name="servicio_catalogo_seleccion"
                            label="Servicio"
                            required
                        >
                            <x-ui.select
                                name="servicio_catalogo_seleccion"
                                data-service-catalog-select
                                required
                            >
                                <option value="">Seleccionar servicio</option>
                                @foreach ($tipos as $tipo)
                                    @foreach ($tipo->catalogoServicios as $opcion)
                                        <option
                                            value="{{ $opcion->id }}"
                                            data-service-type="{{ $tipo->id }}"
                                            @selected(old("servicio_catalogo_seleccion") == $opcion->id)
                                        >
                                            {{ $opcion->nombre }}
                                        </option>
                                    @endforeach
                                @endforeach
                                <option
                                    value="otro"
                                    data-service-other-option
                                    @selected(old("servicio_catalogo_seleccion") === "otro")
                                >
                                    Otro
                                </option>
                            </x-ui.select>
                        </x-ui.field>
                        <x-ui.field
                            class="form-col-6"
                            name="servicio_otro"
                            label="Especificar servicio"
                            data-service-other-field
                            :hidden="old('servicio_catalogo_seleccion') !== 'otro'"
                            required
                        >
                            <x-ui.input
                                name="servicio_otro"
                                data-service-other-input
                                placeholder="Describe el servicio no incluido en el catálogo"
                            />
                        </x-ui.field>
                        <x-ui.field
                            class="form-col-8"
                            name="nombre"
                            label="Nombre operativo"
                            required
                            hint="Identifica el trabajo concreto, incluyendo planta o alcance cuando corresponda."
                        >
                            <x-ui.input
                                name="nombre"
                                required
                                placeholder="Ej. Actualización de P&ID – Planta Concepción"
                            />
                        </x-ui.field>
                        <x-ui.field
                            class="form-col-4"
                            name="estado"
                            label="Estado inicial"
                            required
                        >
                            <div class="ui-choice-group" role="radiogroup" aria-label="Estado inicial">
                                @foreach (["prospecto" => "Prospecto", "planificado" => "Planificado", "en_curso" => "En curso"] as $valor => $texto)
                                    <label class="ui-choice">
                                        <input
                                            type="radio"
                                            name="estado"
                                            value="{{ $valor }}"
                                            @checked(old("estado", "prospecto") === $valor)
                                            required
                                        />
                                        <span>{{ $texto }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </x-ui.field>
                        <x-ui.field
                            class="form-col-12"
                            name="descripcion"
                            label="Descripción"
                        >
                            <x-ui.textarea
                                name="descripcion"
                                placeholder="Objetivo, alcance inicial y antecedentes relevantes"
                            />
                        </x-ui.field>
                        <x-ui.field
                            class="form-col-6"
                            name="fecha_inicio_estimada"
                            label="Inicio estimado"
                        >
                            <x-ui.input name="fecha_inicio_estimada" type="date" />
                        </x-ui.field>
                        <x-ui.field
                            class="form-col-6"
                            name="fecha_termino_estimada"
                            label="Término estimado"
                        >
                            <x-ui.input name="fecha_termino_estimada" type="date" />
                        </x-ui.field>
                    </div>
                </x-ui.panel>
                <x-ui.panel class="service-plants-panel">
                    <x-slot:title>
                        <h2 class="ui-panel__title">Plantas involucradas</h2>
                    </x-slot>
                    <x-slot:subtitle>
                        Solo se mostrarán las plantas pertenecientes al cliente
                        seleccionado
                    </x-slot>
                    <div class="service-plant-list" data-plants>
                        @foreach ($clientes as $cliente)
                            @foreach ($cliente->plantas as $planta)
                                <div class="service-plant-option" data-client="{{ $cliente->id }}" hidden>
                                    <x-ui.checkbox
                                        name="plantas[]"
                                        :value="$planta->id"
                                        :label="$planta->nombre"
                                        :hint="$planta->ciudad"
                                        :checked="in_array($planta->id, old('plantas', []))"
                                    />
                                </div>
                            @endforeach
                        @endforeach
                        <p class="muted service-plant-list__empty" data-no-plants>
                            Selecciona un cliente para ver sus plantas.
                        </p>
                    </div>
                </x-ui.panel>
            </div>
            <div class="form-actions">
                <x-ui.button :href="route('servicios.index')" variant="outline">
                    Cancelar
                </x-ui.button>
                <x-ui.button type="submit" variant="secondary">Crear servicio</x-ui.button>
            </div>
        </form>
    </div>
@endsection
