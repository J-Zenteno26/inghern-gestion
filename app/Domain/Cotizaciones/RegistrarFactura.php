<?php

namespace App\Domain\Cotizaciones;

use App\Models\EventoHistorial;
use App\Models\Factura;
use App\Models\OrdenCompra;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegistrarFactura
{
    /**
     * @param  array{folio: string, fecha_emision: string, monto_neto: int|float|string, observacion?: ?string, condicion_pago: string, dias_pago?: int|string}  $datos
     */
    public function ejecutar(
        OrdenCompra $ordenCompra,
        array $datos,
        int $usuarioId,
    ): Factura {
        return DB::transaction(function () use ($ordenCompra, $datos, $usuarioId) {
            $ordenCompra = OrdenCompra::query()
                ->with('cotizacion.revisionActual')
                ->whereKey($ordenCompra->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($ordenCompra->estado !== 'registrada') {
                throw ValidationException::withMessages([
                    'factura' => 'Solo una orden de compra registrada puede recibir facturas.',
                ]);
            }

            $revision = $ordenCompra->cotizacion->revisionActual;

            if (! $revision) {
                throw ValidationException::withMessages([
                    'factura' => 'La orden de compra no tiene una revisión de cotización vigente.',
                ]);
            }

            if (Factura::query()
                ->where('cliente_id', $ordenCompra->cliente_id)
                ->where('folio', $datos['folio'])
                ->exists()) {
                throw ValidationException::withMessages([
                    'folio' => 'Este folio ya fue registrado para la organización.',
                ]);
            }

            $montoNeto = round((float) $datos['monto_neto'], 2);
            $ivaPorcentaje = (float) $revision->iva_porcentaje;
            $iva = round($montoNeto * ($ivaPorcentaje / 100), 2);

            $factura = $ordenCompra->facturas()->create([
                'cliente_id' => $ordenCompra->cliente_id,
                'creado_por' => $usuarioId,
                'folio' => $datos['folio'],
                'fecha_emision' => $datos['fecha_emision'],
                'monto_neto' => $montoNeto,
                'iva_porcentaje' => $ivaPorcentaje,
                'iva' => $iva,
                'total' => round($montoNeto + $iva, 2),
                'moneda' => $revision->moneda,
                'estado' => 'emitida',
                'observacion' => $datos['observacion'] ?? null,
                'condicion_pago' => $datos['condicion_pago'],
                'dias_pago' => $datos['condicion_pago'] === 'credito'
                    ? (int) $datos['dias_pago']
                    : null,
            ]);

            EventoHistorial::create([
                'registrable_type' => Factura::class,
                'registrable_id' => $factura->id,
                'user_id' => $usuarioId,
                'evento' => 'factura_registrada',
                'descripcion' => "Factura {$factura->folio} registrada.",
                'cambios' => [
                    'factura_id' => $factura->id,
                    'orden_compra_id' => $ordenCompra->id,
                    'cotizacion_id' => $ordenCompra->cotizacion_id,
                    'cliente_id' => $ordenCompra->cliente_id,
                    'usuario_id' => $usuarioId,
                    'condicion_pago' => $factura->condicion_pago,
                    'dias_pago' => $factura->dias_pago,
                ],
            ]);

            return $factura;
        });
    }
}
