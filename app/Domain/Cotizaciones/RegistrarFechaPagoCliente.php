<?php

namespace App\Domain\Cotizaciones;

use App\Models\EventoHistorial;
use App\Models\Factura;
use Illuminate\Support\Facades\DB;

class RegistrarFechaPagoCliente
{
    public function ejecutar(
        Factura $factura,
        string $fechaPagoInformada,
        int $usuarioId,
    ): Factura {
        return DB::transaction(function () use ($factura, $fechaPagoInformada, $usuarioId) {
            $factura = Factura::query()
                ->whereKey($factura->id)
                ->lockForUpdate()
                ->firstOrFail();
            $fechaAnterior = $factura->fecha_pago_informada_cliente?->toDateString();

            if ($fechaAnterior === $fechaPagoInformada) {
                return $factura;
            }

            $factura->update([
                'fecha_pago_informada_cliente' => $fechaPagoInformada,
            ]);

            EventoHistorial::create([
                'registrable_type' => Factura::class,
                'registrable_id' => $factura->id,
                'user_id' => $usuarioId,
                'evento' => 'fecha_pago_cliente_registrada',
                'descripcion' => $fechaAnterior
                    ? "Fecha de pago informada de la factura {$factura->folio} modificada."
                    : "Fecha de pago informada de la factura {$factura->folio} registrada.",
                'cambios' => [
                    'factura_id' => $factura->id,
                    'usuario_id' => $usuarioId,
                    'fecha_anterior' => $fechaAnterior,
                    'fecha_pago_informada_cliente' => $fechaPagoInformada,
                ],
            ]);

            return $factura;
        });
    }
}
