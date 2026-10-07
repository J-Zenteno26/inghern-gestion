<?php

namespace App\Http\Controllers\Cotizaciones;

use App\Domain\Cotizaciones\RegistrarPago;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePagoRequest;
use App\Models\Factura;
use App\Models\Pago;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PagoController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const MEDIOS_PAGO = [
        'transferencia' => 'Transferencia bancaria',
        'deposito' => 'Depósito',
        'cheque' => 'Cheque',
        'efectivo' => 'Efectivo',
        'otro' => 'Otro',
    ];

    public function index(): View
    {
        $resumenFacturas = Factura::query()
            ->with('cliente')
            ->withSum('pagos as total_pagado', 'pago_factura.monto_asignado')
            ->get([
                'id',
                'cliente_id',
                'folio',
                'fecha_pago_informada_cliente',
                'total',
                'moneda',
                'estado',
            ]);
        $facturas = Factura::query()
            ->with('cliente')
            ->withSum('pagos as total_pagado', 'pago_factura.monto_asignado')
            ->latest('fecha_emision')
            ->latest('id')
            ->paginate(15);
        $inicioMes = now()->startOfMonth();
        $finMes = now()->endOfMonth();
        $facturasOperativas = $resumenFacturas
            ->reject(fn (Factura $factura) => $factura->estado === 'anulada');
        $seguimiento = [
            'esperando_fecha' => $facturasOperativas
                ->whereNull('fecha_pago_informada_cliente')
                ->count(),
            'con_fecha' => $facturasOperativas
                ->filter(fn (Factura $factura) => $factura->fecha_pago_informada_cliente !== null
                    && $factura->totalPagado() <= 0
                    && $factura->saldoPago() > 0)
                ->count(),
            'pago_parcial' => $facturasOperativas
                ->filter(fn (Factura $factura) => $factura->estadoPago() === 'pago_parcial')
                ->count(),
            'pagadas' => $facturasOperativas
                ->filter(fn (Factura $factura) => $factura->totalPagado() >= (float) $factura->total)
                ->count(),
        ];
        $proximosPagos = $facturasOperativas
            ->filter(fn (Factura $factura) => $factura->fecha_pago_informada_cliente !== null
                && $factura->fecha_pago_informada_cliente->greaterThanOrEqualTo(today())
                && $factura->saldoPago() > 0)
            ->sortBy('fecha_pago_informada_cliente')
            ->take(5)
            ->values();

        $inicioGrafico = now()->startOfMonth()->subMonths(11);
        $totalesPorMes = Pago::query()
            ->where('fecha_pago', '>=', $inicioGrafico)
            ->get(['fecha_pago', 'monto_total'])
            ->groupBy(fn (Pago $pago) => $pago->fecha_pago->format('Y-m'))
            ->map(fn ($pagos) => (float) $pagos->sum('monto_total'));
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

        return view('pagos.index', [
            'facturas' => $facturas,
            'metricas' => [
                'cobrado_mes' => (float) Pago::query()
                    ->whereBetween('fecha_pago', [$inicioMes, $finMes])
                    ->sum('monto_total'),
                'pendiente_cobro' => $resumenFacturas
                    ->reject(fn (Factura $factura) => $factura->estado === 'anulada')
                    ->sum(fn (Factura $factura) => max($factura->saldoPago(), 0)),
                'pagos_parciales' => $facturasOperativas
                    ->filter(fn (Factura $factura) => $factura->estadoPago() === 'pago_parcial')
                    ->count(),
                'esperando_fecha' => $seguimiento['esperando_fecha'],
            ],
            'grafico' => $grafico,
            'seguimiento' => $seguimiento,
            'proximosPagos' => $proximosPagos,
        ]);
    }

    public function create(Request $request): View
    {
        $facturas = Factura::query()
            ->where('estado', '!=', 'anulada')
            ->with('cliente')
            ->withSum('pagos as total_pagado', 'pago_factura.monto_asignado')
            ->latest('fecha_emision')
            ->latest('id')
            ->get();

        return view('pagos.create', [
            'facturas' => $facturas,
            'facturaSeleccionada' => $facturas->firstWhere('id', $request->integer('factura')),
            'mediosPago' => self::MEDIOS_PAGO,
        ]);
    }

    public function store(
        StorePagoRequest $request,
        RegistrarPago $registrarPago,
    ): RedirectResponse {
        $pago = $registrarPago->ejecutar(
            $request->validated(),
            $request->user()->id,
        );

        return to_route('pagos.show', $pago)->with(
            'exito',
            'Pago registrado correctamente.',
        );
    }

    public function show(Pago $pago): View
    {
        $pago->load(['usuarioCreador', 'facturas.cliente']);

        return view('pagos.show', [
            'pago' => $pago,
            'medioPago' => self::MEDIOS_PAGO[$pago->medio_pago] ?? $pago->medio_pago,
        ]);
    }
}
