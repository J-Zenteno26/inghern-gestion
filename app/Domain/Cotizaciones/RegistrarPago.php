<?php

namespace App\Domain\Cotizaciones;

use App\Domain\Shared\GeneraCodigo;
use App\Models\EventoHistorial;
use App\Models\Factura;
use App\Models\Pago;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegistrarPago
{
    public function __construct(private readonly GeneraCodigo $codigos) {}

    /**
     * @param  array{
     *     fecha_pago: string,
     *     monto_total: int|float|string,
     *     medio_pago: string,
     *     referencia?: ?string,
     *     observacion?: ?string,
     *     asignaciones: array<int, array{factura_id: int|string, monto_asignado: int|float|string}>
     * }  $datos
     */
    public function ejecutar(array $datos, int $usuarioId): Pago
    {
        return DB::transaction(function () use ($datos, $usuarioId) {
            $facturaIds = collect($datos['asignaciones'])
                ->pluck('factura_id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $facturas = Factura::query()
                ->whereKey($facturaIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($facturas->count() !== count($facturaIds)
                || $facturas->contains(fn (Factura $factura) => $factura->estado === 'anulada')) {
                throw ValidationException::withMessages([
                    'asignaciones' => 'Una de las facturas seleccionadas ya no está disponible.',
                ]);
            }

            $pago = Pago::create([
                'codigo' => $this->codigos->siguiente(Pago::class, 'PAG'),
                'fecha_pago' => $datos['fecha_pago'],
                'monto_total' => round((float) $datos['monto_total'], 2),
                'medio_pago' => $datos['medio_pago'],
                'referencia' => $datos['referencia'] ?? null,
                'observacion' => $datos['observacion'] ?? null,
                'usuario_creador_id' => $usuarioId,
            ]);

            foreach ($datos['asignaciones'] as $asignacion) {
                $factura = $facturas->get((int) $asignacion['factura_id']);
                $montoAsignado = round((float) $asignacion['monto_asignado'], 2);

                $pago->facturas()->attach($factura->id, [
                    'monto_asignado' => $montoAsignado,
                ]);

                EventoHistorial::create([
                    'registrable_type' => Factura::class,
                    'registrable_id' => $factura->id,
                    'user_id' => $usuarioId,
                    'evento' => 'pago_asignado_factura',
                    'descripcion' => "Pago {$pago->codigo} asignado a la factura {$factura->folio}.",
                    'cambios' => [
                        'pago_id' => $pago->id,
                        'factura_id' => $factura->id,
                        'usuario_id' => $usuarioId,
                        'monto_asignado' => $montoAsignado,
                    ],
                ]);
            }

            EventoHistorial::create([
                'registrable_type' => Pago::class,
                'registrable_id' => $pago->id,
                'user_id' => $usuarioId,
                'evento' => 'pago_registrado',
                'descripcion' => "Pago {$pago->codigo} registrado.",
                'cambios' => [
                    'pago_id' => $pago->id,
                    'usuario_id' => $usuarioId,
                    'monto_total' => (float) $pago->monto_total,
                    'medio_pago' => $pago->medio_pago,
                    'factura_ids' => $facturaIds,
                ],
            ]);

            return $pago;
        });
    }
}
