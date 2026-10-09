<?php

namespace App\Http\Controllers\Cotizaciones;

use App\Domain\Cotizaciones\RegistrarFactura;
use App\Domain\Cotizaciones\RegistrarFechaPagoCliente;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFacturaRequest;
use App\Models\Cliente;
use App\Models\Factura;
use App\Models\OrdenCompra;
use App\Models\Planta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FacturaController extends Controller
{
    public function index(Request $request): View
    {
        $buscar = trim((string) $request->query('buscar'));
        $clienteId = $request->integer('cliente');
        $plantaId = $request->integer('planta');
        $estado = (string) $request->query('estado', '');
        $periodo = (string) $request->query('periodo', '');
        $ordenCompraId = $request->integer('oc');
        $estados = [
            'emitida' => 'Emitida',
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

        $ordenCompraFiltro = $ordenCompraId
            ? OrdenCompra::with('cliente')->find($ordenCompraId)
            : null;
        $ordenCompraId = $ordenCompraFiltro?->id ?? 0;

        $resumenOrdenes = OrdenCompra::query()
            ->where('estado', 'registrada')
            ->withSum(
                ['facturas as total_facturado' => fn ($query) => $query->where('estado', '!=', 'anulada')],
                'total',
            )
            ->get(['id', 'monto']);

        $inicioMes = now()->startOfMonth();
        $finMes = now()->endOfMonth();
        $metricas = [
            'facturado_mes' => (float) Factura::query()
                ->where('estado', '!=', 'anulada')
                ->whereBetween('fecha_emision', [$inicioMes, $finMes])
                ->sum('monto_neto'),
            'saldo_por_facturar' => $resumenOrdenes->sum(
                fn (OrdenCompra $ordenCompra) => max($ordenCompra->saldoFacturacion(), 0),
            ),
            'emitidas_mes' => Factura::query()
                ->where('estado', '!=', 'anulada')
                ->whereBetween('fecha_emision', [$inicioMes, $finMes])
                ->count(),
            'oc_parciales' => $resumenOrdenes
                ->filter(fn (OrdenCompra $ordenCompra) => $ordenCompra->estadoFacturacion() === 'facturacion_parcial')
                ->count(),
        ];

        $inicioGrafico = now()->startOfMonth()->subMonths(11);
        $totalesPorMes = Factura::query()
            ->where('estado', '!=', 'anulada')
            ->where('fecha_emision', '>=', $inicioGrafico)
            ->get(['fecha_emision', 'monto_neto'])
            ->groupBy(fn (Factura $factura) => $factura->fecha_emision->format('Y-m'))
            ->map(fn ($facturas) => (float) $facturas->sum('monto_neto'));
        $grafico = collect(range(0, 11))->map(function (int $mes) use ($inicioGrafico, $totalesPorMes) {
            $fecha = $inicioGrafico->copy()->addMonths($mes);

            return [
                'clave' => $fecha->format('Y-m'),
                'etiqueta' => $fecha->format('m/Y'),
                'monto' => (float) $totalesPorMes->get($fecha->format('Y-m'), 0),
            ];
        });
        $maximoGrafico = max((float) $grafico->max('monto'), 1);
        $grafico = $grafico->map(fn (array $mes) => [
            ...$mes,
            'porcentaje' => $mes['monto'] > 0
                ? max(($mes['monto'] / $maximoGrafico) * 100, 4)
                : 0,
        ]);

        $facturas = Factura::query()
            ->with([
                'cliente',
                'ordenCompra' => fn ($query) => $query
                    ->with('cotizacion.planta')
                    ->withSum(
                        ['facturas as total_facturado' => fn ($query) => $query->where('estado', '!=', 'anulada')],
                        'total',
                    ),
            ])
            ->withSum('pagos as total_pagado', 'pago_factura.monto_asignado')
            ->when(
                $buscar,
                fn ($query) => $query->where(
                    fn ($filtro) => $filtro
                        ->where('folio', 'ilike', "%{$buscar}%")
                        ->orWhereHas(
                            'ordenCompra',
                            fn ($ordenCompra) => $ordenCompra->where('numero', 'ilike', "%{$buscar}%"),
                        )
                        ->orWhereHas(
                            'cliente',
                            fn ($cliente) => $cliente
                                ->where('razon_social', 'ilike', "%{$buscar}%")
                                ->orWhere('nombre_fantasia', 'ilike', "%{$buscar}%"),
                        )
                        ->orWhereHas(
                            'ordenCompra.cotizacion.planta',
                            fn ($planta) => $planta->where('nombre', 'ilike', "%{$buscar}%"),
                        ),
                ),
            )
            ->when($clienteId, fn ($query) => $query->where('cliente_id', $clienteId))
            ->when(
                $plantaId,
                fn ($query) => $query->whereHas(
                    'ordenCompra.cotizacion',
                    fn ($cotizacion) => $cotizacion->where('planta_id', $plantaId),
                ),
            )
            ->when($estado, fn ($query) => $query->where('estado', $estado))
            ->when(
                $rangoPeriodo,
                fn ($query) => $query->whereBetween('fecha_emision', $rangoPeriodo),
            )
            ->when(
                $ordenCompraId,
                fn ($query) => $query->where('orden_compra_id', $ordenCompraId),
            )
            ->latest('fecha_emision')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('facturas.index', [
            'facturas' => $facturas,
            'clientesFiltro' => Cliente::query()->orderBy('razon_social')->get(),
            'plantasFiltro' => Planta::query()->orderBy('nombre')->get(),
            'estados' => $estados,
            'metricas' => $metricas,
            'grafico' => $grafico,
            'buscar' => $buscar,
            'clienteId' => $clienteId,
            'plantaId' => $plantaId,
            'estado' => $estado,
            'periodo' => $periodo,
            'ordenCompraFiltro' => $ordenCompraFiltro,
        ]);
    }

    public function create(Request $request): View
    {
        $ordenesCompra = OrdenCompra::query()
            ->where('estado', 'registrada')
            ->whereHas('cotizacion.revisionActual')
            ->with(['cliente', 'cotizacion.revisionActual'])
            ->withSum(
                ['facturas as total_facturado' => fn ($query) => $query->where('estado', '!=', 'anulada')],
                'total',
            )
            ->latest('fecha')
            ->get();
        $ordenCompraSeleccionada = $ordenesCompra->firstWhere(
            'id',
            $request->integer('oc'),
        );

        return view('facturas.create', [
            'ordenesCompra' => $ordenesCompra,
            'ordenCompraSeleccionada' => $ordenCompraSeleccionada,
        ]);
    }

    public function store(
        StoreFacturaRequest $request,
        RegistrarFactura $registrar,
    ): RedirectResponse {
        $ordenCompra = OrdenCompra::findOrFail(
            $request->integer('orden_compra_id'),
        );
        $factura = $registrar->ejecutar(
            $ordenCompra,
            $request->validated(),
            $request->user()->id,
        );

        return to_route('facturas.show', $factura)->with(
            'exito',
            'Factura registrada correctamente.',
        );
    }

    public function show(Factura $factura): View
    {
        $factura->load(['cliente', 'ordenCompra.cotizacion']);
        $factura->loadSum('pagos as total_pagado', 'pago_factura.monto_asignado');

        return view('facturas.show', ['factura' => $factura]);
    }

    public function actualizarFechaPago(
        Request $request,
        Factura $factura,
        RegistrarFechaPagoCliente $registrarFechaPago,
    ): RedirectResponse {
        $datos = $request->validate(
            ['fecha_pago_informada_cliente' => ['required', 'date_format:Y-m-d']],
            [],
            ['fecha_pago_informada_cliente' => 'fecha de pago informada por cliente'],
        );

        $registrarFechaPago->ejecutar(
            $factura,
            $datos['fecha_pago_informada_cliente'],
            $request->user()->id,
        );

        return to_route('facturas.show', $factura)->with(
            'exito',
            'Fecha de pago informada guardada correctamente.',
        );
    }
}
