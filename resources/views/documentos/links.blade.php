@extends("layouts.app")
@section("title", "Vínculos · " . $documento->nombre)
@section("content")
    <x-ui.page-header eyebrow="Biblioteca" :title="$documento->nombre" description="Gestiona las entidades relacionadas sin eliminar el documento.">
        <x-slot:actions><x-ui.button :href="route('biblioteca.index', ['cliente' => $cliente->id])" variant="outline"><x-ui.icon name="arrow-left" /> Volver a Biblioteca</x-ui.button></x-slot:actions>
    </x-ui.page-header>

    <div class="document-links-layout">
        <x-ui.panel>
            <x-slot:title>Vínculos actuales</x-slot:title>
            <x-slot:subtitle>{{ $cliente->nombre_display }}</x-slot:subtitle>
            <div class="document-link-list">
                @forelse ($documento->vinculos as $vinculo)
                    @php($relacionado = $vinculo->vinculable)
                    @if ($relacionado)
                        <div class="document-link-row">
                            <div><strong>{{ \App\Models\DocumentoVinculo::ETIQUETAS[array_search($vinculo->vinculable_type, \App\Models\DocumentoVinculo::TIPOS, true)] ?? 'Entidad' }}</strong><span>{{ $relacionado->codigo ?? $relacionado->numero ?? $relacionado->folio ?? $relacionado->nombre_display ?? $relacionado->nombre ?? 'Registro' }}</span></div>
                            <form action="{{ route('biblioteca.documentos.links.destroy', [$cliente, $documento, $vinculo]) }}" method="POST" onsubmit="return confirm('¿Quitar este vínculo? El documento y el archivo se conservarán.')">@csrf @method('DELETE')<button class="document-link-remove" type="submit"><x-ui.icon name="link-2-off" size="16" /> Quitar vínculo</button></form>
                        </div>
                    @endif
                @empty
                    <x-ui.empty-state icon="link" title="Sin vínculos" description="Añade una relación con una entidad de esta organización." />
                @endforelse
            </div>
        </x-ui.panel>

        <x-ui.panel>
            <x-slot:title>Añadir vínculo</x-slot:title>
            <x-slot:subtitle>Solo se muestran entidades de esta organización.</x-slot:subtitle>
            <form class="document-link-form" action="{{ route('biblioteca.documentos.links.store', [$cliente, $documento]) }}" method="POST" data-document-link-form data-fixed-client="{{ $cliente->id }}">@csrf
                <x-ui.field name="vinculable_type" label="Tipo de entidad" required><x-ui.select name="vinculable_type" required data-document-link-type><option value="">Seleccionar tipo</option>@foreach (\App\Models\DocumentoVinculo::ETIQUETAS as $valor => $etiqueta)<option value="{{ $valor }}">{{ $etiqueta }}</option>@endforeach</x-ui.select></x-ui.field>
                <x-ui.field name="vinculable_id" label="Entidad" required><x-ui.select name="vinculable_id" required data-document-link-entity disabled><option value="">Seleccionar entidad</option>@foreach ($opcionesVinculo as $opcion)<option value="{{ $opcion['id'] }}" data-client="{{ $opcion['cliente_id'] }}" data-type="{{ $opcion['tipo'] }}">{{ $opcion['etiqueta'] }}</option>@endforeach</x-ui.select></x-ui.field>
                <x-ui.button type="submit"><x-ui.icon name="link" /> Añadir vínculo</x-ui.button>
            </form>
        </x-ui.panel>
    </div>
@endsection
