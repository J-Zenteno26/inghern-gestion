<?php

namespace App\Http\Controllers\Cotizaciones;

use App\Domain\Cotizaciones\AceptarCotizacion;
use App\Domain\Cotizaciones\CrearCotizacion;
use App\Enums\UnidadPrecio;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCotizacionRequest;
use App\Models\CatalogoServicio;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\CotizacionRevision;
use App\Models\Servicio;
use App\Models\TipoServicio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CotizacionController extends Controller
{
    public function index(Request $request): View
    {
        $buscar = trim((string) $request->query('buscar'));
        $clienteId = $request->integer('cliente');
        $estado = (string) $request->query('estado', '');
        $periodo = (string) $request->query('periodo', '');
        $orden = in_array($request->query('orden'), ['propuesta', 'cliente'], true)
            ? (string) $request->query('orden')
            : '';
        $direccion = $request->query('direccion') === 'desc' ? 'desc' : 'asc';

        $estados = [
            'borrador' => 'Borrador',
            'enviada' => 'Enviada',
            'en_revision' => 'En revisión',
            'aceptada' => 'Aceptada',
            'rechazada' => 'Rechazada',
            'por_validar' => 'Por validar',
            'anulada' => 'Anulada',
        ];
        if (! array_key_exists($estado, $estados)) {
            $estado = '';
        }

        $rangoPeriodo = match ($periodo) {
            'este_mes' => [now()->startOfMonth(), now()->endOfMonth()],
            'mes_anterior' => [
                now()->subMonthNoOverflow()->startOfMonth(),
                now()->subMonthNoOverflow()->endOfMonth(),
            ],
            'este_ano' => [now()->startOfYear(), now()->endOfYear()],
            default => null,
        };
        if ($rangoPeriodo === null) {
            $periodo = '';
        }

        $inicioMes = now()->startOfMonth();
        $finMes = now()->endOfMonth();
        $inicioMesAnterior = now()->subMonthNoOverflow()->startOfMonth();
        $finMesAnterior = now()->subMonthNoOverflow()->endOfMonth();
        $esDelPeriodo = fn ($inicio, $fin) => fn ($q) => $q
            ->whereBetween('fecha_emision', [$inicio, $fin])
            ->orWhere(function ($sinFecha) use ($inicio, $fin) {
                $sinFecha
                    ->whereNull('fecha_emision')
                    ->whereHas(
                        'cotizacion',
                        fn ($cotizacion) => $cotizacion->whereBetween(
                            'cotizaciones.created_at',
                            [$inicio, $fin],
                        ),
                    );
            });
        $esDelMesActual = $esDelPeriodo($inicioMes, $finMes);
        $esDelMesAnterior = $esDelPeriodo(
            $inicioMesAnterior,
            $finMesAnterior,
        );

        $metricas = [
            'total' => Cotizacion::count(),
            'mes' => CotizacionRevision::query()
                ->whereIn('id', Cotizacion::select('revision_actual_id'))
                ->where($esDelMesActual)
                ->count(),
            'por_validar' => Cotizacion::where('estado', 'por_validar')->count(),
            'monto_mes' => CotizacionRevision::query()
                ->whereIn('id', Cotizacion::select('revision_actual_id'))
                ->where($esDelMesActual)
                ->whereHas(
                    'cotizacion',
                    fn ($cotizacion) => $cotizacion->whereNotIn('estado', [
                        'borrador',
                        'anulada',
                    ]),
                )
                ->sum('total'),
            'monto_mes_anterior' => CotizacionRevision::query()
                ->whereIn('id', Cotizacion::select('revision_actual_id'))
                ->where($esDelMesAnterior)
                ->whereHas(
                    'cotizacion',
                    fn ($cotizacion) => $cotizacion->whereNotIn('estado', [
                        'borrador',
                        'anulada',
                    ]),
                )
                ->sum('total'),
        ];

        $cotizaciones = Cotizacion::with(['cliente', 'revisionActual.contacto'])
            ->when(
                $buscar,
                fn ($q) => $q->where(
                    fn ($s) => $s
                        ->where('codigo', 'ilike', "%{$buscar}%")
                        ->orWhereHas(
                            'cliente',
                            fn ($c) => $c->where(
                                'razon_social',
                                'ilike',
                                "%{$buscar}%",
                            ),
                        )
                        ->orWhereHas(
                            'revisionActual',
                            fn ($r) => $r
                                ->where('titulo', 'ilike', "%{$buscar}%")
                                ->orWhereHas(
                                    'contacto',
                                    fn ($contacto) => $contacto
                                        ->where('nombre', 'ilike', "%{$buscar}%")
                                        ->orWhere('email', 'ilike', "%{$buscar}%")
                                        ->orWhere('telefono', 'ilike', "%{$buscar}%"),
                                ),
                        ),
                ),
            )
            ->when($clienteId, fn ($q) => $q->where('cliente_id', $clienteId))
            ->when($estado, fn ($q) => $q->where('estado', $estado))
            ->when(
                $rangoPeriodo,
                fn ($q) => $q->where(function ($porPeriodo) use ($rangoPeriodo) {
                    $porPeriodo
                        ->whereHas(
                            'revisionActual',
                            fn ($revision) => $revision->whereBetween(
                                'fecha_emision',
                                $rangoPeriodo,
                            ),
                        )
                        ->orWhere(function ($sinFecha) use ($rangoPeriodo) {
                            $sinFecha
                                ->whereHas(
                                    'revisionActual',
                                    fn ($revision) => $revision->whereNull(
                                        'fecha_emision',
                                    ),
                                )
                                ->whereBetween(
                                    'cotizaciones.created_at',
                                    $rangoPeriodo,
                                );
                        });
                }),
            )
            ->when(
                $orden === 'propuesta',
                fn ($q) => $q->orderBy(
                    CotizacionRevision::select('titulo')
                        ->whereColumn(
                            'cotizacion_revisiones.id',
                            'cotizaciones.revision_actual_id',
                        )
                        ->limit(1),
                    $direccion,
                ),
            )
            ->when(
                $orden === 'cliente',
                fn ($q) => $q->orderBy(
                    Cliente::selectRaw(
                        'COALESCE(nombre_fantasia, razon_social)',
                    )
                        ->whereColumn('clientes.id', 'cotizaciones.cliente_id')
                        ->limit(1),
                    $direccion,
                ),
            )
            ->when(! $orden, fn ($q) => $q->latest())
            ->paginate(15)
            ->withQueryString();

        return view(
            'cotizaciones.index',
            compact(
                'cotizaciones',
                'buscar',
                'clienteId',
                'estado',
                'estados',
                'periodo',
                'orden',
                'direccion',
                'metricas',
            ) + [
                'clientesFiltro' => Cliente::orderBy('razon_social')->get(),
            ],
        );
    }

    public function create(Request $request): View
    {
        $seleccion = $request->validate([
            'cliente' => ['nullable', 'required_with:servicio', 'integer', 'exists:clientes,id'],
            'servicio' => [
                'nullable',
                'integer',
                Rule::exists('servicios', 'id')->where(
                    fn ($query) => $query
                        ->where('cliente_id', $request->input('cliente'))
                        ->whereNull('deleted_at'),
                ),
            ],
        ]);

        $servicioSeleccionado = isset($seleccion['servicio'])
            ? Servicio::with(['tipo', 'catalogoServicio', 'plantas'])->findOrFail($seleccion['servicio'])
            : null;

        $catalogoServicios = CatalogoServicio::query()
            ->with(['tipo.variablesPrecio.niveles'])
            ->where('activo', true)
            ->orderBy('tipo_servicio_id')
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();

        $tiposDisponibles = TipoServicio::query()
            ->where('activo', true)
            ->whereHas('catalogoServicios', fn ($query) => $query->where('activo', true))
            ->orderBy('nombre')
            ->get();

        $serviciosOperativos = Servicio::query()
            ->with(['tipo', 'catalogoServicio'])
            ->whereIn('estado', ['prospecto', 'planificado', 'en_curso', 'en_pausa'])
            ->whereNotNull('catalogo_servicio_id')
            ->orderBy('id')
            ->get();

        $configuracionPrecios = $catalogoServicios->mapWithKeys(function (CatalogoServicio $catalogo) {
            $unidad = $catalogo->unidad_precio
                ? UnidadPrecio::tryFrom($catalogo->unidad_precio)
                : null;
            $referenciaDisponible = $catalogo->precio_base !== null;
            $normalizacionDisponible = $referenciaDisponible && $unidad;
            $variables = $catalogo->tipo?->variablesPrecio
                ->filter(fn ($variable) => $variable->activo && (bool) $variable->pivot->requerida)
                ->values() ?? collect();

            return [
                $catalogo->id => [
                    'catalogo_id' => $catalogo->id,
                    'tipo_id' => $catalogo->tipo_servicio_id,
                    'tipo_nombre' => $catalogo->tipo?->nombre,
                    'codigo' => $catalogo->codigo,
                    'nombre' => $catalogo->nombre,
                    'descripcion' => $catalogo->descripcion,
                    'referencia_disponible' => (bool) $referenciaDisponible,
                    'normalizacion_disponible' => (bool) $normalizacionDisponible,
                    'precio_base' => $referenciaDisponible ? (float) $catalogo->precio_base : null,
                    'moneda' => $referenciaDisponible ? $catalogo->moneda_precio : null,
                    'unidad' => $unidad?->value,
                    'unidad_etiqueta' => $unidad?->etiqueta(),
                    'variables' => $variables->map(fn ($variable) => [
                        'id' => $variable->id,
                        'nombre' => $variable->nombre,
                        'descripcion' => $variable->descripcion,
                        'peso' => (float) $variable->pivot->peso,
                        'niveles' => $variable->niveles->map(fn ($nivel) => [
                            'id' => $nivel->id,
                            'nombre' => $nivel->nombre,
                            'criterio' => $nivel->criterio,
                            'factor' => (float) $nivel->factor,
                        ])->values(),
                    ])->values(),
                ],
            ];
        });

        $serviciosOperativosPorCliente = $serviciosOperativos
            ->groupBy('cliente_id')
            ->map(fn ($servicios) => $servicios
                ->keyBy('catalogo_servicio_id')
                ->map(fn (Servicio $servicio) => [
                    'id' => $servicio->id,
                    'codigo' => $servicio->codigo,
                    'nombre' => $servicio->nombre,
                    'descripcion' => $servicio->descripcion,
                    'estado' => $servicio->estado,
                ]));

        return view('cotizaciones.create', [
            'clientes' => Cliente::with('contactos')->orderBy('razon_social')->get(),
            'catalogoServicios' => $catalogoServicios,
            'tiposDisponibles' => $tiposDisponibles,
            'clienteSeleccionado' => $seleccion['cliente'] ?? null,
            'servicioSeleccionado' => $servicioSeleccionado,
            'configuracionPrecios' => $configuracionPrecios,
            'serviciosOperativosPorCliente' => $serviciosOperativosPorCliente,
        ]);
    }

    public function store(
        StoreCotizacionRequest $request,
        CrearCotizacion $crear,
    ): RedirectResponse {
        $cotizacion = $crear->ejecutar(
            $request->validated(),
            $request->user()->id,
        );

        return to_route('cotizaciones.show', $cotizacion)->with(
            'exito',
            'Cotización creada como borrador.',
        );
    }

    public function show(Cotizacion $cotizacion): View
    {
        $cotizacion->load([
            'cliente',
            'ordenCompra.creador',
            'ordenCompra.facturas',
            'revisiones',
            'revisionActual.contacto',
            'revisionActual.bloques',
            'revisionActual.partidas.valoresVariables',
            'revisionActual.servicios.catalogoServicio.tipo',
            'revisionActual.servicios.servicio.tipo',
            'revisionActual.servicios.servicio.catalogoServicio',
            'revisionActual.servicios.servicio.plantas',
        ]);

        $facturasOrdenCompra = $cotizacion->ordenCompra?->facturas;
        $netoFacturado = (float) ($facturasOrdenCompra?->sum('monto_neto') ?? 0);
        $saldoNetoPorFacturar = $cotizacion->ordenCompra
            ? (float) $cotizacion->ordenCompra->monto - $netoFacturado
            : 0;
        $avanceFacturacion = $cotizacion->ordenCompra && (float) $cotizacion->ordenCompra->monto > 0
            ? ($netoFacturado / (float) $cotizacion->ordenCompra->monto) * 100
            : 0;

        return view('cotizaciones.show', [
            'cotizacion' => $cotizacion,
            'revisionActual' => $cotizacion->revisionActual,
            'netoFacturado' => $netoFacturado,
            'saldoNetoPorFacturar' => $saldoNetoPorFacturar,
            'cantidadFacturas' => $facturasOrdenCompra?->count() ?? 0,
            'ultimaFactura' => $facturasOrdenCompra?->first(),
            'avanceFacturacion' => $avanceFacturacion,
        ]);
    }

    public function aceptar(
        Request $request,
        Cotizacion $cotizacion,
        AceptarCotizacion $aceptar,
    ): RedirectResponse {
        $aceptar->ejecutar($cotizacion, $request->user()->id);

        return to_route('cotizaciones.show', $cotizacion)->with(
            'exito',
            'Cotización aceptada y servicios operativos vinculados correctamente.',
        );
    }
}
