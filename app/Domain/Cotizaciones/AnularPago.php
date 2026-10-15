<?php

namespace App\Domain\Cotizaciones;

use App\Models\EventoHistorial;
use App\Models\Pago;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AnularPago
{
    public function ejecutar(Pago $pago, string $motivo, int $usuarioId): Pago
    {
        return DB::transaction(function () use ($pago, $motivo, $usuarioId) {
            $pago = Pago::query()
                ->whereKey($pago->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($pago->estado !== 'registrado') {
                throw ValidationException::withMessages([
                    'pago' => 'El pago ya se encuentra anulado.',
                ]);
            }

            $estadoAnterior = $pago->estado;

            $pago->update(['estado' => 'anulado']);

            EventoHistorial::create([
                'registrable_type' => Pago::class,
                'registrable_id' => $pago->id,
                'user_id' => $usuarioId,
                'evento' => 'pago_anulado',
                'descripcion' => "Pago {$pago->codigo} anulado.",
                'cambios' => [
                    'pago_id' => $pago->id,
                    'cliente_id' => $pago->cliente_id,
                    'usuario_id' => $usuarioId,
                    'estado_anterior' => $estadoAnterior,
                    'estado_nuevo' => 'anulado',
                    'motivo' => trim($motivo),
                ],
            ]);

            return $pago;
        });
    }
}
