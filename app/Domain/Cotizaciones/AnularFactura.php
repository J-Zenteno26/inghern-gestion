<?php

namespace App\Domain\Cotizaciones;

use App\Models\EventoHistorial;
use App\Models\Factura;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AnularFactura
{
    public function ejecutar(Factura $factura, string $motivo, int $usuarioId): Factura
    {
        return DB::transaction(function () use ($factura, $motivo, $usuarioId) {
            $factura = Factura::query()
                ->whereKey($factura->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($factura->estado === 'anulada') {
                throw ValidationException::withMessages([
                    'factura' => 'La factura ya se encuentra anulada.',
                ]);
            }

            if ($factura->pagos()->exists()) {
                throw ValidationException::withMessages([
                    'factura' => 'La factura tiene pagos asignados. Primero debes resolver o reasignar esos pagos.',
                ]);
            }

            $estadoAnterior = $factura->estado;

            $factura->update(['estado' => 'anulada']);

            EventoHistorial::create([
                'registrable_type' => Factura::class,
                'registrable_id' => $factura->id,
                'user_id' => $usuarioId,
                'evento' => 'factura_anulada',
                'descripcion' => "Factura {$factura->folio} anulada.",
                'cambios' => [
                    'factura_id' => $factura->id,
                    'orden_compra_id' => $factura->orden_compra_id,
                    'cliente_id' => $factura->cliente_id,
                    'usuario_id' => $usuarioId,
                    'estado_anterior' => $estadoAnterior,
                    'estado_nuevo' => 'anulada',
                    'motivo' => trim($motivo),
                ],
            ]);

            return $factura;
        });
    }
}
