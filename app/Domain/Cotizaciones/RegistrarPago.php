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
            $montoTotal = round((float) $datos['monto_total'], 2);
            $montoDistribuido = round(
                (float) collect($datos['asignaciones'])->sum('monto_asignado'),
                2,
            );

            if (abs($montoTotal - $montoDistribuido) > 0.009) {
                throw ValidationException::withMessages([
                    'asignaciones' => 'La suma de los montos asignados debe coincidir con el monto total del pago.',
                ]);
            }

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

            if ($facturas->pluck('moneda')->unique()->count() > 1) {
                throw ValidationException::withMessages([
                    'asignaciones' => 'Las facturas seleccionadas deben utilizar la misma moneda.',
                ]);
            }

            if ($facturas->pluck('cliente_id')->unique()->count() > 1) {
                throw ValidationException::withMessages([
                    'asignaciones' => 'Las facturas seleccionadas deben pertenecer a la misma organización.',
                ]);
            }

            $clienteId = (int) $facturas->first()->cliente_id;
            $moneda = $facturas->first()->moneda;

            $asignacionesValidadas = [];

            foreach ($datos['asignaciones'] as $indice => $asignacion) {
                $factura = $facturas->get((int) $asignacion['factura_id']);
                $montoAsignado = round((float) $asignacion['monto_asignado'], 2);
                $saldoActual = $factura->saldoPago();

                if ($saldoActual <= 0.009) {
                    throw ValidationException::withMessages([
                        "asignaciones.{$indice}.monto_asignado" => "La factura {$factura->folio} ya no tiene saldo pendiente disponible.",
                    ]);
                }

                if ($montoAsignado > $saldoActual + 0.009) {
                    $saldoFormateado = number_format($saldoActual, 0, ',', '.');

                    throw ValidationException::withMessages([
                        "asignaciones.{$indice}.monto_asignado" => "El monto asignado a la factura {$factura->folio} supera su saldo pendiente de {$factura->moneda} \${$saldoFormateado}.",
                    ]);
                }

                $asignacionesValidadas[] = [
                    'factura' => $factura,
                    'monto_asignado' => $montoAsignado,
                ];
            }

            $pago = Pago::create([
                'cliente_id' => $clienteId,
                'codigo' => $this->codigos->siguiente(Pago::class, 'PAG'),
                'fecha_pago' => $datos['fecha_pago'],
                'monto_total' => $montoTotal,
                'moneda' => $moneda,
                'medio_pago' => $datos['medio_pago'],
                'referencia' => $datos['referencia'] ?? null,
                'observacion' => $datos['observacion'] ?? null,
                'estado' => 'registrado',
                'usuario_creador_id' => $usuarioId,
            ]);

            foreach ($asignacionesValidadas as $asignacion) {
                $factura = $asignacion['factura'];
                $montoAsignado = $asignacion['monto_asignado'];

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
