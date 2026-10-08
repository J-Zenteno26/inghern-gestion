<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDocumentoRequest;
use App\Http\Requests\StoreDocumentoVinculoRequest;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\Documento;
use App\Models\DocumentoVinculo;
use App\Models\Factura;
use App\Models\OrdenCompra;
use App\Models\Planta;
use App\Models\RevisionServicio;
use App\Models\Servicio;
use App\Support\XlsxPreviewReader;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class DocumentoController extends Controller
{
    public function index(Request $request): View
    {
        $vista = in_array($request->query('vista'), ['carpetas', 'lista'], true)
            ? (string) $request->query('vista')
            : 'carpetas';
        $buscar = trim((string) $request->query('buscar'));
        $clienteId = $request->integer('cliente');
        $tipoDocumento = (string) $request->query('tipo', '');
        $entidad = (string) $request->query('entidad', '');
        $formato = Str::lower((string) $request->query('formato', ''));
        $plantaContexto = $request->filled('planta')
            ? Planta::query()->with('cliente')->findOrFail($request->integer('planta'))
            : null;
        $cotizacionContexto = $request->filled('cotizacion')
            ? Cotizacion::query()
                ->with(['cliente', 'planta', 'revisionActual'])
                ->findOrFail($request->integer('cotizacion'))
            : null;
        $revisionServicioContexto = null;
        $contextoGeneral = $cotizacionContexto !== null
            && $request->query('contexto') === 'general';

        if ($request->filled('revision_servicio')) {
            abort_unless($cotizacionContexto !== null && ! $contextoGeneral, 404);

            $revisionServicioContexto = RevisionServicio::query()
                ->whereKey($request->integer('revision_servicio'))
                ->whereHas(
                    'revision',
                    fn ($revisiones) => $revisiones->where(
                        'cotizacion_id',
                        $cotizacionContexto->getKey(),
                    ),
                )
                ->firstOrFail();
        }

        if ($cotizacionContexto !== null) {
            $clienteId = (int) $cotizacionContexto->cliente_id;
        }
        if ($plantaContexto !== null) {
            abort_if(
                $cotizacionContexto !== null
                    && (int) $cotizacionContexto->planta_id !== (int) $plantaContexto->getKey(),
                404,
            );
            $clienteId = (int) $plantaContexto->cliente_id;
        }

        if (! array_key_exists($tipoDocumento, Documento::TIPOS)) {
            $tipoDocumento = '';
        }
        if (! array_key_exists($entidad, DocumentoVinculo::TIPOS)) {
            $entidad = '';
        }
        if (! in_array($formato, config('filesystems.document_uploads.allowed_extensions'), true)) {
            $formato = '';
        }

        $documentos = Documento::query()
            ->with(['cliente', 'usuarioCreador', 'vinculos.vinculable'])
            ->when(
                $buscar,
                fn ($query) => $query->whereRaw(
                    'LOWER(nombre) LIKE ?',
                    ['%'.Str::lower($buscar).'%'],
                ),
            )
            ->when($clienteId, fn ($query) => $query->where('cliente_id', $clienteId))
            ->when($tipoDocumento, fn ($query) => $query->where('tipo_documento', $tipoDocumento))
            ->when($formato, fn ($query) => $query->where('extension', $formato))
            ->when(
                $plantaContexto !== null
                    && $cotizacionContexto === null
                    && $vista === 'lista',
                fn ($query) => $query->dePlanta($plantaContexto),
            )
            ->when(
                $plantaContexto !== null
                    && $cotizacionContexto === null
                    && $vista === 'carpetas',
                fn ($query) => $query->whereHas(
                    'vinculos',
                    fn ($vinculos) => $vinculos
                        ->where('vinculable_type', $plantaContexto->getMorphClass())
                        ->where('vinculable_id', $plantaContexto->getKey()),
                ),
            )
            ->when(
                $cotizacionContexto !== null
                    && $revisionServicioContexto === null
                    && ! $contextoGeneral
                    && $vista === 'lista',
                fn ($query) => $query->deCotizacion($cotizacionContexto),
            )
            ->when(
                $revisionServicioContexto,
                fn ($query) => $query->whereHas(
                    'vinculos',
                    fn ($vinculos) => $vinculos
                        ->where('vinculable_type', $revisionServicioContexto->getMorphClass())
                        ->where('vinculable_id', $revisionServicioContexto->getKey()),
                ),
            )
            ->when(
                $cotizacionContexto !== null
                    && $revisionServicioContexto === null
                    && ($contextoGeneral || $vista === 'carpetas'),
                fn ($query) => $query->generalesDeCotizacion($cotizacionContexto),
            )
            ->when(
                $entidad,
                fn ($query) => $query->whereHas(
                    'vinculos',
                    fn ($vinculos) => $vinculos->where(
                        'vinculable_type',
                        DocumentoVinculo::TIPOS[$entidad],
                    ),
                ),
            )
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $plantasBiblioteca = collect();
        if ($plantaContexto === null && $cotizacionContexto === null) {
            $plantasBiblioteca = Planta::query()
                ->with('cliente')
                ->withCount(['cotizaciones', 'servicios'])
                ->when($clienteId, fn ($query) => $query->where('cliente_id', $clienteId))
                ->orderBy('nombre')
                ->get()
                ->each(function (Planta $planta): void {
                    $planta->setAttribute(
                        'documentos_count',
                        Documento::query()->dePlanta($planta)->count(),
                    );
                });
        }

        $cotizacionesPlanta = collect();
        if ($plantaContexto !== null && $cotizacionContexto === null) {
            $cotizacionesPlanta = Cotizacion::query()
                ->with('revisionActual.servicios')
                ->where('planta_id', $plantaContexto->getKey())
                ->latest()
                ->get()
                ->each(function (Cotizacion $cotizacion): void {
                    $cotizacion->setAttribute(
                        'documentos_count',
                        Documento::query()->deCotizacion($cotizacion)->count(),
                    );
                });
        }

        $serviciosCotizacion = $cotizacionContexto?->revision_actual_id
            ? RevisionServicio::query()
                ->with('revision')
                ->withCount('documentos')
                ->where('cotizacion_revision_id', $cotizacionContexto->revision_actual_id)
                ->orderBy('orden')
                ->get()
            : collect();

        return view('documentos.index', [
            'vista' => $vista,
            'documentos' => $documentos,
            'clientes' => Cliente::query()->orderBy('razon_social')->get(),
            'plantasFiltro' => Planta::query()
                ->with('cliente')
                ->orderBy('nombre')
                ->get(),
            'plantasBiblioteca' => $plantasBiblioteca,
            'cotizacionesPlanta' => $cotizacionesPlanta,
            'opcionesVinculo' => $this->opcionesVinculo(),
            'buscar' => $buscar,
            'clienteId' => $clienteId,
            'tipoDocumento' => $tipoDocumento,
            'entidad' => $entidad,
            'formato' => $formato,
            'plantaContexto' => $plantaContexto,
            'cotizacionContexto' => $cotizacionContexto,
            'revisionServicioContexto' => $revisionServicioContexto,
            'contextoGeneral' => $contextoGeneral,
            'serviciosCotizacion' => $serviciosCotizacion,
        ]);
    }

    public function storeFromLibrary(StoreDocumentoRequest $request): RedirectResponse
    {
        $cliente = Cliente::findOrFail($request->integer('cliente_id'));
        $vinculableAdicional = $this->resolverVinculable($request);
        $vinculablesAdicionales = [];

        if ($request->filled('cotizacion_id')) {
            $cotizacion = Cotizacion::findOrFail($request->integer('cotizacion_id'));
            $vinculablesAdicionales[] = $cotizacion;

            if ($request->filled('revision_servicio_id')) {
                $vinculablesAdicionales[] = RevisionServicio::findOrFail(
                    $request->integer('revision_servicio_id'),
                );
            }
        } elseif ($vinculableAdicional instanceof RevisionServicio) {
            $vinculablesAdicionales[] = $vinculableAdicional->revision()
                ->firstOrFail()
                ->cotizacion()
                ->firstOrFail();
            $vinculablesAdicionales[] = $vinculableAdicional;
        } elseif ($vinculableAdicional !== null) {
            $vinculablesAdicionales[] = $vinculableAdicional;
        }

        return $this->almacenar(
            $request,
            $cliente,
            $cliente,
            $vinculablesAdicionales,
        );
    }

    public function downloadFromLibrary(
        Cliente $cliente,
        Documento $documento,
    ): StreamedResponse {
        $this->asegurarOrganizacion($cliente, $documento);

        return Storage::disk('documentos')->download(
            $documento->ruta_storage,
            $documento->nombre_original,
        );
    }

    public function previewFromLibrary(
        Request $request,
        Cliente $cliente,
        Documento $documento,
        XlsxPreviewReader $xlsxPreviewReader,
    ): View|BinaryFileResponse {
        $this->asegurarOrganizacion($cliente, $documento);

        $extension = Str::lower($documento->extension);
        abort_unless(in_array($extension, ['pdf', 'jpg', 'jpeg', 'png', 'xlsx'], true), 404);

        $disk = Storage::disk('documentos');
        $archivoExiste = $disk->exists($documento->ruta_storage);
        $rutaFisica = $archivoExiste ? $disk->path($documento->ruta_storage) : null;
        $archivoExiste = $rutaFisica !== null && is_readable($rutaFisica);

        if ($request->boolean('contenido')) {
            abort_unless($archivoExiste && in_array($extension, ['pdf', 'jpg', 'jpeg', 'png'], true), 404);

            $mimeType = match ($extension) {
                'pdf' => 'application/pdf',
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
            };
            $response = response()->file($rutaFisica, [
                'Content-Type' => $mimeType,
                'X-Content-Type-Options' => 'nosniff',
            ]);
            $response->setContentDisposition(
                'inline',
                $documento->nombre_original,
                (Str::slug(pathinfo($documento->nombre_original, PATHINFO_FILENAME)) ?: 'documento').'.'.$extension,
            );

            return $response;
        }

        $xlsxPreview = null;
        $previewError = $archivoExiste
            ? null
            : 'No fue posible generar la vista previa de este archivo. Puedes descargar el original.';

        if ($extension === 'xlsx' && $archivoExiste) {
            try {
                $xlsxPreview = $xlsxPreviewReader->read(
                    $rutaFisica,
                    max(0, $request->integer('hoja')),
                );
            } catch (Throwable) {
                $previewError = 'No fue posible generar la vista previa de este archivo. Puedes descargar el original.';
            }
        }

        return view('documentos.preview', [
            'cliente' => $cliente,
            'documento' => $documento,
            'previewType' => match ($extension) {
                'pdf' => 'pdf',
                'jpg', 'jpeg', 'png' => 'imagen',
                'xlsx' => 'xlsx',
            },
            'previewError' => $previewError,
            'xlsxPreview' => $xlsxPreview,
            'contextoBiblioteca' => $request->only([
                'planta',
                'cotizacion',
                'revision_servicio',
                'contexto',
                'vista',
                'buscar',
                'tipo',
                'entidad',
                'formato',
                'page',
            ]),
        ]);
    }

    public function links(Cliente $cliente, Documento $documento): View
    {
        $this->asegurarOrganizacion($cliente, $documento);
        $documento->load(['cliente', 'vinculos.vinculable']);

        return view('documentos.links', [
            'cliente' => $cliente,
            'documento' => $documento,
            'opcionesVinculo' => $this->opcionesVinculo($cliente->getKey(), true),
        ]);
    }

    public function storeLink(
        StoreDocumentoVinculoRequest $request,
        Cliente $cliente,
        Documento $documento,
    ): RedirectResponse {
        $vinculable = $this->resolverVinculable($request);

        DocumentoVinculo::firstOrCreate([
            'documento_id' => $documento->getKey(),
            'vinculable_type' => $vinculable->getMorphClass(),
            'vinculable_id' => $vinculable->getKey(),
        ]);

        return back()->with('exito', 'Vínculo añadido correctamente.');
    }

    public function destroyLink(
        Cliente $cliente,
        Documento $documento,
        DocumentoVinculo $documentoVinculo,
    ): RedirectResponse {
        $this->asegurarOrganizacion($cliente, $documento);
        abort_unless(
            (int) $documentoVinculo->documento_id === (int) $documento->getKey(),
            404,
        );

        $vinculable = $documentoVinculo->vinculable;
        abort_unless(
            $vinculable !== null
                && $this->clienteId($vinculable) === (int) $cliente->getKey(),
            404,
        );

        $documentoVinculo->delete();

        return back()->with('exito', 'Vínculo eliminado correctamente.');
    }

    public function storeForCliente(
        StoreDocumentoRequest $request,
        Cliente $cliente,
    ): RedirectResponse {
        return $this->almacenar($request, $cliente, $cliente);
    }

    public function storeForCotizacion(
        StoreDocumentoRequest $request,
        Cotizacion $cotizacion,
    ): RedirectResponse {
        return $this->almacenar(
            $request,
            $cotizacion,
            $cotizacion->cliente()->firstOrFail(),
        );
    }

    public function downloadForCliente(
        Cliente $cliente,
        Documento $documento,
    ): StreamedResponse {
        $this->asegurarVinculo($cliente, $documento);

        return Storage::disk('documentos')->download(
            $documento->ruta_storage,
            $documento->nombre_original,
        );
    }

    public function downloadForCotizacion(
        Cotizacion $cotizacion,
        Documento $documento,
    ): StreamedResponse {
        $this->asegurarVinculo($cotizacion, $documento);

        return Storage::disk('documentos')->download(
            $documento->ruta_storage,
            $documento->nombre_original,
        );
    }

    public function destroyForCliente(
        Cliente $cliente,
        Documento $documento,
    ): RedirectResponse {
        return $this->desvincular($cliente, $documento);
    }

    public function destroyForCotizacion(
        Cotizacion $cotizacion,
        Documento $documento,
    ): RedirectResponse {
        return $this->desvincular($cotizacion, $documento);
    }

    private function almacenar(
        StoreDocumentoRequest $request,
        Cliente|Cotizacion $vinculable,
        Cliente $cliente,
        array $vinculablesAdicionales = [],
    ): RedirectResponse {
        /** @var UploadedFile $archivo */
        $archivo = $request->file('archivo');
        $extension = Str::lower($archivo->getClientOriginalExtension());
        $nombreFisico = Str::uuid()->toString().'.'.$extension;
        $directorio = 'clientes/'.$cliente->getKey();
        $rutaStorage = $archivo->storeAs(
            $directorio,
            $nombreFisico,
            'documentos',
        );

        if ($rutaStorage === false) {
            throw new RuntimeException('No fue posible almacenar el documento.');
        }

        try {
            DB::transaction(function () use (
                $request,
                $archivo,
                $extension,
                $rutaStorage,
                $vinculable,
                $vinculablesAdicionales,
                $cliente,
            ): void {
                $nombreOriginal = basename(str_replace(
                    '\\',
                    '/',
                    $archivo->getClientOriginalName(),
                ));
                $nombre = Str::limit(
                    pathinfo($nombreOriginal, PATHINFO_FILENAME),
                    180,
                    '',
                );
                $nombreSolicitado = $request->string('nombre')->trim()->toString();
                $documento = Documento::create([
                    'cliente_id' => $cliente->getKey(),
                    'nombre' => $nombreSolicitado !== ''
                        ? $nombreSolicitado
                        : ($nombre !== '' ? $nombre : 'Documento'),
                    'nombre_original' => Str::limit($nombreOriginal, 255, ''),
                    'descripcion' => $request->validated('descripcion'),
                    'tipo_documento' => $request->string('tipo_documento')->toString(),
                    'mime_type' => $archivo->getMimeType() ?: 'application/octet-stream',
                    'extension' => $extension,
                    'tamano' => $archivo->getSize(),
                    'ruta_storage' => $rutaStorage,
                    'usuario_creador_id' => $request->user()->getKey(),
                ]);

                foreach ([$vinculable, ...$vinculablesAdicionales] as $entidad) {
                    DocumentoVinculo::firstOrCreate([
                        'documento_id' => $documento->getKey(),
                        'vinculable_type' => $entidad->getMorphClass(),
                        'vinculable_id' => $entidad->getKey(),
                    ]);
                }
            });
        } catch (Throwable $throwable) {
            Storage::disk('documentos')->delete($rutaStorage);

            throw $throwable;
        }

        return back()->with('exito', 'Documento cargado correctamente.');
    }

    private function desvincular(
        Cliente|Cotizacion $vinculable,
        Documento $documento,
    ): RedirectResponse {
        $this->asegurarVinculo($vinculable, $documento);
        $vinculable->documentos()->detach($documento->getKey());

        return back()->with('exito', 'Documento desvinculado correctamente.');
    }

    private function asegurarVinculo(
        Cliente|Cotizacion $vinculable,
        Documento $documento,
    ): void {
        $clienteId = $vinculable instanceof Cliente
            ? $vinculable->getKey()
            : $vinculable->cliente_id;

        abort_unless((int) $documento->cliente_id === (int) $clienteId, 404);
        abort_unless(
            $documento->vinculos()
                ->where('vinculable_type', $vinculable->getMorphClass())
                ->where('vinculable_id', $vinculable->getKey())
                ->exists(),
            404,
        );
    }

    private function asegurarOrganizacion(
        Cliente $cliente,
        Documento $documento,
    ): void {
        abort_unless(
            (int) $documento->cliente_id === (int) $cliente->getKey(),
            404,
        );
    }

    private function resolverVinculable(Request $request): ?Model
    {
        if (! $request->filled('vinculable_type')) {
            return null;
        }

        $clase = DocumentoVinculo::TIPOS[$request->string('vinculable_type')->toString()];

        return $clase::findOrFail($request->integer('vinculable_id'));
    }

    private function clienteId(Model $vinculable): int
    {
        if ($vinculable instanceof Cliente) {
            return (int) $vinculable->getKey();
        }

        if ($vinculable instanceof RevisionServicio) {
            return (int) $vinculable->revision()
                ->firstOrFail()
                ->cotizacion()
                ->value('cliente_id');
        }

        return (int) $vinculable->cliente_id;
    }

    /**
     * @return array<int, array{tipo: string, cliente_id: int, id: int, etiqueta: string}>
     */
    private function opcionesVinculo(?int $clienteId = null, bool $incluirCliente = false): array
    {
        $opciones = collect();

        if ($incluirCliente && $clienteId !== null) {
            $cliente = Cliente::findOrFail($clienteId);
            $opciones->push([
                'tipo' => 'cliente',
                'cliente_id' => $cliente->getKey(),
                'id' => $cliente->getKey(),
                'etiqueta' => $cliente->nombre_display,
            ]);
        }

        Cotizacion::query()
            ->with(['revisionActual', 'planta'])
            ->when($clienteId, fn ($query) => $query->where('cliente_id', $clienteId))
            ->orderBy('codigo')
            ->get()
            ->each(function (Cotizacion $cotizacion) use ($opciones): void {
                $planta = $cotizacion->planta?->nombre
                    ? 'Planta '.$cotizacion->planta->nombre
                    : 'Planta por asignar';

                $opciones->push([
                    'tipo' => 'cotizacion',
                    'cliente_id' => (int) $cotizacion->cliente_id,
                    'id' => (int) $cotizacion->getKey(),
                    'etiqueta' => implode(' · ', [
                        $cotizacion->codigo,
                        $cotizacion->revisionActual?->titulo ?: 'Sin título',
                        $planta,
                    ]),
                ]);
            });

        Servicio::query()
            ->with('plantas')
            ->when($clienteId, fn ($query) => $query->where('cliente_id', $clienteId))
            ->orderBy('codigo')
            ->get()
            ->each(function (Servicio $servicio) use ($opciones): void {
                $plantas = $servicio->plantas
                    ->pluck('nombre')
                    ->map(fn (string $nombre) => 'Planta '.$nombre)
                    ->implode(', ');

                $opciones->push([
                    'tipo' => 'servicio',
                    'cliente_id' => (int) $servicio->cliente_id,
                    'id' => (int) $servicio->getKey(),
                    'etiqueta' => collect([
                        $servicio->codigo,
                        $servicio->nombre,
                        $plantas ?: null,
                    ])->filter()->implode(' · '),
                ]);
            });

        Factura::query()
            ->with(['cliente', 'ordenCompra.cotizacion.planta'])
            ->when($clienteId, fn ($query) => $query->where('cliente_id', $clienteId))
            ->orderBy('folio')
            ->get()
            ->each(function (Factura $factura) use ($opciones): void {
                $nombrePlanta = $factura->ordenCompra?->cotizacion?->planta?->nombre;

                $opciones->push([
                    'tipo' => 'factura',
                    'cliente_id' => (int) $factura->cliente_id,
                    'id' => (int) $factura->getKey(),
                    'etiqueta' => implode(' · ', [
                        $factura->folio,
                        $factura->cliente->nombre_display,
                        $nombrePlanta ? 'Planta '.$nombrePlanta : 'Planta por asignar',
                    ]),
                ]);
            });

        OrdenCompra::query()
            ->with(['cliente', 'cotizacion.planta'])
            ->when($clienteId, fn ($query) => $query->where('cliente_id', $clienteId))
            ->orderBy('numero')
            ->get()
            ->each(function (OrdenCompra $ordenCompra) use ($opciones): void {
                $nombrePlanta = $ordenCompra->cotizacion?->planta?->nombre;

                $opciones->push([
                    'tipo' => 'orden_compra',
                    'cliente_id' => (int) $ordenCompra->cliente_id,
                    'id' => (int) $ordenCompra->getKey(),
                    'etiqueta' => collect([
                        $ordenCompra->numero,
                        $ordenCompra->cliente->nombre_display,
                        $nombrePlanta ? 'Planta '.$nombrePlanta : null,
                    ])->filter()->implode(' · '),
                ]);
            });

        Planta::query()
            ->with('cliente')
            ->when($clienteId, fn ($query) => $query->where('cliente_id', $clienteId))
            ->orderBy('nombre')
            ->get()
            ->each(fn (Planta $planta) => $opciones->push([
                'tipo' => 'planta',
                'cliente_id' => (int) $planta->cliente_id,
                'id' => (int) $planta->getKey(),
                'etiqueta' => $planta->nombre.' · '.$planta->cliente->nombre_display,
            ]));

        RevisionServicio::query()
            ->with('revision.cotizacion.planta')
            ->when(
                $clienteId,
                fn ($query) => $query->whereHas(
                    'revision.cotizacion',
                    fn ($cotizaciones) => $cotizaciones->where('cliente_id', $clienteId),
                ),
            )
            ->orderBy('titulo')
            ->get()
            ->each(function (RevisionServicio $revisionServicio) use ($opciones): void {
                $cotizacion = $revisionServicio->revision->cotizacion;
                $opciones->push([
                    'tipo' => 'revision_servicio',
                    'cliente_id' => (int) $cotizacion->cliente_id,
                    'id' => (int) $revisionServicio->getKey(),
                    'etiqueta' => collect([
                        $cotizacion->codigo,
                        $revisionServicio->titulo,
                        $cotizacion->planta?->nombre ? 'Planta '.$cotizacion->planta->nombre : null,
                    ])->filter()->implode(' · '),
                ]);
            });

        return $opciones->all();
    }
}
