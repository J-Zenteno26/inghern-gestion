@extends('layouts.app')
@section('title', 'Previsualizar documento')
@section('content')
@php
    $previewRouteParameters = array_merge($contextoBiblioteca, [
        'cliente' => $cliente->getKey(),
        'documento' => $documento->getKey(),
    ]);
    $columnName = static function (int $column): string {
        $name = '';
        while ($column > 0) {
            $column--;
            $name = chr(65 + ($column % 26)).$name;
            $column = intdiv($column, 26);
        }

        return $name;
    };
@endphp

<div class="document-preview-nav">
    <a href="{{ route('biblioteca.index', $contextoBiblioteca) }}">
        <x-ui.icon name="arrow-left" size="16" /> Volver a Biblioteca
    </a>
    <span>Biblioteca</span>
    <x-ui.icon name="arrow" size="14" />
    <strong>{{ $documento->nombre }}</strong>
</div>

<x-ui.page-header eyebrow="Vista previa privada" :title="$documento->nombre"
    :description="(\App\Models\Documento::TIPOS[$documento->tipo_documento] ?? ucfirst(str_replace('_', ' ', $documento->tipo_documento))).' · '.strtoupper($documento->extension).' · '.\Illuminate\Support\Number::fileSize((int) $documento->tamano, 1)">
    <x-slot:actions>
        <x-ui.button :href="route('biblioteca.documentos.download', [$cliente, $documento])">
            <x-ui.icon name="download" size="16" /> Descargar
        </x-ui.button>
    </x-slot:actions>
</x-ui.page-header>

@if ($previewError)
    <div class="document-preview-error">
        <span><x-ui.icon entity="documento" size="20" /></span>
        <div>
            <strong>Vista previa no disponible</strong>
            <p>{{ $previewError }}</p>
        </div>
    </div>
@elseif ($previewType === 'pdf')
    <section class="document-preview-panel">
        <div class="document-preview-panel__heading">
            <span><x-ui.icon name="file-pdf" size="19" /></span>
            <div><small>Documento PDF</small><strong>{{ $documento->nombre_original }}</strong></div>
        </div>
        <iframe
            class="document-preview-pdf"
            src="{{ route('biblioteca.documentos.preview', array_merge($previewRouteParameters, ['contenido' => 1])) }}"
            title="Vista previa de {{ $documento->nombre }}"
        ></iframe>
    </section>
@elseif ($previewType === 'imagen')
    <section class="document-preview-panel">
        <div class="document-preview-panel__heading">
            <span><x-ui.icon name="image" size="19" /></span>
            <div><small>Imagen</small><strong>{{ $documento->nombre_original }}</strong></div>
        </div>
        <div class="document-preview-image">
            <img
                src="{{ route('biblioteca.documentos.preview', array_merge($previewRouteParameters, ['contenido' => 1])) }}"
                alt="Vista previa de {{ $documento->nombre }}"
            />
        </div>
    </section>
@elseif ($xlsxPreview)
    <section class="document-preview-panel document-preview-panel--spreadsheet">
        <div class="document-preview-panel__heading">
            <span><x-ui.icon name="file-spreadsheet" size="19" /></span>
            <div>
                <small>Libro XLSX · {{ count($xlsxPreview['sheets']) }} {{ count($xlsxPreview['sheets']) === 1 ? 'hoja' : 'hojas' }}</small>
                <strong>{{ $documento->nombre_original }}</strong>
            </div>
        </div>

        <nav class="document-preview-sheets" aria-label="Hojas del libro">
            @foreach ($xlsxPreview['sheets'] as $index => $sheetName)
                <a
                    class="{{ $xlsxPreview['selected_sheet'] === $index ? 'is-active' : '' }}"
                    href="{{ route('biblioteca.documentos.preview', array_merge($previewRouteParameters, ['hoja' => $index])) }}"
                >{{ $sheetName }}</a>
            @endforeach
        </nav>

        <div class="document-preview-sheet-meta">
            <span>Hoja seleccionada</span>
            <strong>{{ $xlsxPreview['selected_name'] }}</strong>
            <small>{{ $xlsxPreview['total_rows'] }} filas · {{ $xlsxPreview['total_columns'] }} columnas</small>
        </div>

        @if ($xlsxPreview['limited'])
            <div class="document-preview-limit">
                Vista previa limitada. Descarga el archivo para consultar el contenido completo.
            </div>
        @endif

        <div class="document-preview-table-scroll">
            <table class="document-preview-table">
                <thead>
                    <tr>
                        <th aria-label="Número de fila"></th>
                        @for ($column = 1; $column <= $xlsxPreview['visible_columns']; $column++)
                            <th>{{ $columnName($column) }}</th>
                        @endfor
                    </tr>
                </thead>
                <tbody>
                    @forelse ($xlsxPreview['rows'] as $row)
                        <tr>
                            <th>{{ $row['number'] }}</th>
                            @for ($column = 1; $column <= $xlsxPreview['visible_columns']; $column++)
                                <td>{{ $row['cells'][$column] ?? '' }}</td>
                            @endfor
                        </tr>
                    @empty
                        <tr><td colspan="{{ $xlsxPreview['visible_columns'] + 1 }}">La hoja no contiene datos visibles.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endif
@endsection
