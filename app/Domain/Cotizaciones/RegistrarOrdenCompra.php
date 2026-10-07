<?php

namespace App\Domain\Cotizaciones;

use App\Models\Cotizacion;
use App\Models\EventoHistorial;
use App\Models\OrdenCompra;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegistrarOrdenCompra
{
    public function ejecutar(
        Cotizacion $cotizacion,
        array $datos,
        int $usuarioId,
    ): OrdenCompra {
        return DB::transaction(function () use ($cotizacion, $datos, $usuarioId) {
            $cotizacion = Cotizacion::query()
                ->whereKey($cotizacion->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($cotizacion->estado !== 'aceptada') {
                throw ValidationException::withMessages([
                    'cotizacion' => 'Solo una cotización aceptada puede registrar una orden de compra.',
                ]);
            }

            if ($cotizacion->ordenCompra()->lockForUpdate()->exists()) {
                throw ValidationException::withMessages([
                    'cotizacion' => 'Esta cotización ya tiene una orden de compra registrada.',
                ]);
            }

            $ordenCompra = $cotizacion->ordenCompra()->create([
                'cliente_id' => $cotizacion->cliente_id,
                'creado_por' => $usuarioId,
                'numero' => $datos['numero'],
                'fecha' => $datos['fecha'],
                'monto' => $datos['monto'],
                'estado' => 'registrada',
                'observacion' => $datos['observacion'] ?? null,
            ]);

            EventoHistorial::create([
                'registrable_type' => OrdenCompra::class,
                'registrable_id' => $ordenCompra->id,
                'user_id' => $usuarioId,
                'evento' => 'oc_registrada',
                'descripcion' => "Orden de compra {$ordenCompra->numero} registrada.",
                'cambios' => [
                    'cotizacion_id' => $cotizacion->id,
                    'orden_compra_id' => $ordenCompra->id,
                    'cliente_id' => $cotizacion->cliente_id,
                    'usuario_id' => $usuarioId,
                ],
            ]);

            return $ordenCompra;
        });
    }
}
