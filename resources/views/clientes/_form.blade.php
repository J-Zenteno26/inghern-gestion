<x-ui.panel>
    <x-slot:title>
        <h2 class="ui-panel__title">Identificación de la empresa</h2>
    </x-slot>
    <x-slot:subtitle>
        Datos legales y de contacto administrativo
    </x-slot>
    <div class="form-grid">
        <x-ui.field
            class="form-col-8"
            name="razon_social"
            label="Razón social"
            required
        >
            <x-ui.input
                name="razon_social"
                :value="$cliente->razon_social ?? null"
                required
            />
        </x-ui.field>
        <x-ui.field
            class="form-col-4"
            name="identificador_tributario"
            label="RUT / identificador"
            required
        >
            <x-ui.input
                name="identificador_tributario"
                :value="$cliente->identificador_tributario ?? null"
                required
            />
        </x-ui.field>
        <x-ui.field
            class="form-col-6"
            name="nombre_fantasia"
            label="Nombre de fantasía"
        >
            <x-ui.input
                name="nombre_fantasia"
                :value="$cliente->nombre_fantasia ?? null"
            />
        </x-ui.field>
        <x-ui.field
            class="form-col-3"
            name="email_facturacion"
            label="Correo de facturación"
        >
            <x-ui.input
                name="email_facturacion"
                type="email"
                :value="$cliente->email_facturacion ?? null"
            />
        </x-ui.field>
        <x-ui.field class="form-col-3" name="telefono" label="Teléfono">
            <x-ui.input name="telefono" :value="$cliente->telefono ?? null" />
        </x-ui.field>
        <x-ui.field class="form-col-6" name="direccion" label="Dirección">
            <x-ui.input
                name="direccion"
                :value="$cliente->direccion ?? null"
            />
        </x-ui.field>
        <x-ui.field class="form-col-3" name="comuna" label="Comuna">
            <x-ui.input name="comuna" :value="$cliente->comuna ?? null" />
        </x-ui.field>
        <x-ui.field class="form-col-3" name="ciudad" label="Ciudad">
            <x-ui.input name="ciudad" :value="$cliente->ciudad ?? null" />
        </x-ui.field>
    </div>
</x-ui.panel>
@unless (isset($cliente))
    <x-ui.panel>
        <x-slot:title>
            <h2 class="ui-panel__title">Punto de partida</h2>
        </x-slot>
        <x-slot:subtitle>
            Estos datos son opcionales y se podrán completar en la ficha
        </x-slot>
        <div class="form-grid">
            <x-ui.field
                class="form-col-4"
                name="contacto_nombre"
                label="Contacto principal"
            >
                <x-ui.input name="contacto_nombre" />
            </x-ui.field>
            <x-ui.field
                class="form-col-4"
                name="contacto_email"
                label="Correo del contacto"
            >
                <x-ui.input name="contacto_email" type="email" />
            </x-ui.field>
            <x-ui.field
                class="form-col-4"
                name="contacto_telefono"
                label="Teléfono del contacto"
            >
                <x-ui.input name="contacto_telefono" />
            </x-ui.field>
            <x-ui.field
                class="form-col-6"
                name="planta_nombre"
                label="Primera planta"
            >
                <x-ui.input
                    name="planta_nombre"
                    placeholder="Ej. Planta Quilicura"
                />
            </x-ui.field>
        </div>
    </x-ui.panel>
@endunless

<div class="form-actions">
    <x-ui.button
        :href="isset($cliente) ? route('clientes.show', $cliente) : route('clientes.index')"
        variant="outline"
    >
        Cancelar
    </x-ui.button>
    <x-ui.button type="submit">
        {{ isset($cliente) ? "Guardar cambios" : "Crear cliente" }}
    </x-ui.button>
</div>
