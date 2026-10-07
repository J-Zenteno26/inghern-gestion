<?php

namespace App\Domain\Cotizaciones;

use App\Domain\Shared\GeneraCodigo;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\CotizacionRevision;
use App\Models\EventoHistorial;
use App\Models\RevisionServicio;
use App\Models\Servicio;
use Illuminate\Support\Facades\DB;

class AceptarCotizacion
{
    private const ESTADOS_OPERATIVOS = [
        'prospecto',
        'planificado',
        'en_curso',
        'en_pausa',
    ];

    public function __construct(private readonly GeneraCodigo $codigos) {}

    public function ejecutar(Cotizacion $cotizacion, int $usuarioId): Cotizacion
    {
        return DB::transaction(function () use ($cotizacion, $usuarioId) {
            $cotizacion = Cotizacion::query()
                ->whereKey($cotizacion->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($cotizacion->estado === 'aceptada') {
                return $this->cargarResultado($cotizacion);
            }

            Cliente::query()
                ->whereKey($cotizacion->cliente_id)
                ->lockForUpdate()
                ->firstOrFail();

            $revision = CotizacionRevision::query()
                ->whereKey($cotizacion->revision_actual_id)
                ->where('cotizacion_id', $cotizacion->id)
                ->lockForUpdate()
                ->firstOrFail();

            $serviciosCotizados = RevisionServicio::query()
                ->with('catalogoServicio')
                ->where('cotizacion_revision_id', $revision->id)
                ->orderBy('orden')
                ->lockForUpdate()
                ->get();

            $resueltos = [];

            foreach ($serviciosCotizados as $servicioCotizado) {
                $servicio = $this->buscarServicioExistente(
                    $servicioCotizado,
                    $cotizacion->cliente_id,
                );
                $resultado = 'vinculado';

                if (! $servicio) {
                    $catalogo = $servicioCotizado->catalogoServicio;
                    $servicio = Servicio::create([
                        'cliente_id' => $cotizacion->cliente_id,
                        'tipo_servicio_id' => $catalogo?->tipo_servicio_id,
                        'catalogo_servicio_id' => $catalogo?->id,
                        'creado_por' => $usuarioId,
                        'codigo' => $this->codigos->siguiente(Servicio::class, 'SER'),
                        'nombre' => $servicioCotizado->titulo,
                        'descripcion' => $servicioCotizado->descripcion,
                        'estado' => 'prospecto',
                    ]);
                    $resultado = 'creado';
                }

                if ($servicioCotizado->servicio_id !== $servicio->id) {
                    $servicioCotizado->update(['servicio_id' => $servicio->id]);
                }

                EventoHistorial::create([
                    'registrable_type' => Servicio::class,
                    'registrable_id' => $servicio->id,
                    'user_id' => $usuarioId,
                    'evento' => $resultado === 'creado'
                        ? 'creado_desde_cotizacion'
                        : 'vinculado_a_cotizacion',
                    'descripcion' => $resultado === 'creado'
                        ? "Servicio creado desde la cotización {$cotizacion->codigo}."
                        : "Servicio vinculado a la cotización {$cotizacion->codigo}.",
                    'cambios' => [
                        'cotizacion_id' => $cotizacion->id,
                        'cotizacion_codigo' => $cotizacion->codigo,
                        'cotizacion_revision_id' => $revision->id,
                        'revision_servicio_id' => $servicioCotizado->id,
                        'resultado' => $resultado,
                    ],
                ]);

                $resueltos[] = [
                    'revision_servicio_id' => $servicioCotizado->id,
                    'servicio_id' => $servicio->id,
                    'resultado' => $resultado,
                ];
            }

            $revision->update(['estado' => 'aceptada']);
            $cotizacion->update(['estado' => 'aceptada']);

            EventoHistorial::create([
                'registrable_type' => Cotizacion::class,
                'registrable_id' => $cotizacion->id,
                'user_id' => $usuarioId,
                'evento' => 'cotizacion_aceptada',
                'descripcion' => 'Cotización aceptada y servicios operativos resueltos.',
                'cambios' => ['servicios' => $resueltos],
            ]);

            return $this->cargarResultado($cotizacion);
        });
    }

    private function buscarServicioExistente(
        RevisionServicio $servicioCotizado,
        int $clienteId,
    ): ?Servicio {
        if ($servicioCotizado->servicio_id) {
            $servicioVinculado = Servicio::query()
                ->whereKey($servicioCotizado->servicio_id)
                ->where('cliente_id', $clienteId)
                ->lockForUpdate()
                ->first();

            if ($servicioVinculado) {
                return $servicioVinculado;
            }
        }

        if (! $servicioCotizado->catalogo_servicio_id) {
            return null;
        }

        return Servicio::query()
            ->where('cliente_id', $clienteId)
            ->where('catalogo_servicio_id', $servicioCotizado->catalogo_servicio_id)
            ->whereIn('estado', self::ESTADOS_OPERATIVOS)
            ->orderBy('id')
            ->lockForUpdate()
            ->first();
    }

    private function cargarResultado(Cotizacion $cotizacion): Cotizacion
    {
        return $cotizacion->load([
            'revisionActual.servicios.servicio',
            'revisionActual.servicios.catalogoServicio',
        ]);
    }
}
