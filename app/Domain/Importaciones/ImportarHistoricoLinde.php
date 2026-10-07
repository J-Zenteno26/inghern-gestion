<?php

namespace App\Domain\Importaciones;

use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\EventoHistorial;
use App\Models\Servicio;
use App\Models\TipoServicio;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ImportarHistoricoLinde
{
    public function resumen(): array
    {
        $datos = $this->datos();

        return [
            'clientes' => 1,
            'contactos' => 4,
            'plantas' => 6,
            'cotizaciones' => count($datos),
            'partidas' => collect($datos)->sum(
                fn ($item) => count($item['partidas']),
            ),
            'por_validar' => collect($datos)
                ->where('estado', 'por_validar')
                ->pluck('codigo')
                ->values()
                ->all(),
            'anuladas' => collect($datos)
                ->where('estado', 'anulada')
                ->pluck('codigo')
                ->values()
                ->all(),
        ];
    }

    public function ejecutar(User $usuario): array
    {
        return DB::transaction(function () use ($usuario) {
            $cliente = Cliente::updateOrCreate(
                ['identificador_tributario' => '90.100.000-K'],
                [
                    'razon_social' => 'Linde Gas Chile S.A.',
                    'nombre_fantasia' => 'Linde Gas Chile',
                    'direccion' => 'Av. Isidora Goyenechea 3621, oficina 301',
                    'comuna' => 'Las Condes',
                    'ciudad' => 'Santiago',
                    'region' => 'Región Metropolitana',
                    'estado' => 'activo',
                ],
            );

            $contactos = collect([
                'Rodrigo Fernández',
                'Sebastián Espinoza',
                'Álvaro Villarroel',
                'Sergio Parra',
            ])->mapWithKeys(function (string $nombre) use ($cliente) {
                $contacto = $cliente
                    ->contactos()
                    ->firstOrCreate(
                        ['nombre' => $nombre],
                        ['estado' => 'activo'],
                    );

                return [$nombre => $contacto];
            });

            $plantas = collect([
                'concepcion' => [
                    'nombre' => 'Planta Concepción',
                    'codigo' => 'LIN-CON',
                    'ciudad' => 'Concepción',
                    'region' => 'Región del Biobío',
                ],
                'antofagasta' => [
                    'nombre' => 'Planta Antofagasta',
                    'codigo' => 'LIN-ANT',
                    'ciudad' => 'Antofagasta',
                    'region' => 'Región de Antofagasta',
                ],
                'nacimiento' => [
                    'nombre' => 'Planta ASU Santa Fe / Nacimiento',
                    'codigo' => 'LIN-NAC',
                    'ciudad' => 'Nacimiento',
                    'region' => 'Región del Biobío',
                ],
                'puerto_montt' => [
                    'nombre' => 'Planta Puerto Montt',
                    'codigo' => 'LIN-PTM',
                    'ciudad' => 'Puerto Montt',
                    'region' => 'Región de Los Lagos',
                ],
                'valdivia' => [
                    'nombre' => 'Planta Valdivia',
                    'codigo' => 'LIN-VAL',
                    'ciudad' => 'Valdivia',
                    'region' => 'Región de Los Ríos',
                ],
                'punta_arenas' => [
                    'nombre' => 'Planta Punta Arenas',
                    'codigo' => 'LIN-PTA',
                    'ciudad' => 'Punta Arenas',
                    'region' => 'Región de Magallanes',
                ],
            ])->mapWithKeys(function (array $datos, string $clave) use (
                $cliente,
            ) {
                $planta = $cliente
                    ->plantas()
                    ->firstOrCreate(
                        ['codigo' => $datos['codigo']],
                        $datos + ['estado' => 'activa'],
                    );

                return [$clave => $planta];
            });

            $creadas = 0;
            $omitidas = 0;

            foreach ($this->datos() as $dato) {
                $existente = Cotizacion::withTrashed()
                    ->where('codigo', $dato['codigo'])
                    ->first();
                if ($existente) {
                    $omitidas++;

                    continue;
                }

                $tipo = TipoServicio::where(
                    'codigo',
                    $dato['tipo'],
                )->firstOrFail();
                $contacto = $contactos->get($dato['contacto']);
                $estadoServicio =
                    $dato['estado'] === 'anulada'
                        ? 'cancelado'
                        : ($dato['estado'] === 'aceptada'
                            ? 'finalizado'
                            : 'prospecto');

                $servicio = Servicio::create([
                    'cliente_id' => $cliente->id,
                    'tipo_servicio_id' => $tipo->id,
                    'creado_por' => $usuario->id,
                    'codigo' => str_replace('COT-', 'SER-', $dato['codigo']),
                    'nombre' => $dato['titulo'],
                    'descripcion' => 'Registro histórico importado desde documentación comercial LINDE.',
                    'estado' => $estadoServicio,
                ]);
                $servicio
                    ->plantas()
                    ->sync(
                        collect($dato['plantas'])
                            ->map(fn ($clave) => $plantas->get($clave)?->id)
                            ->filter()
                            ->all(),
                    );

                $cotizacion = Cotizacion::create([
                    'cliente_id' => $cliente->id,
                    'creado_por' => $usuario->id,
                    'codigo' => $dato['codigo'],
                    'estado' => $dato['estado'],
                ]);

                $subtotal = collect($dato['partidas'])->sum('monto');
                $iva = round($subtotal * 0.19, 2);
                $revision = $cotizacion->revisiones()->create([
                    'contacto_id' => $contacto?->id,
                    'creado_por' => $usuario->id,
                    'revision' => 1,
                    'titulo' => $dato['titulo'],
                    'fecha_emision' => $dato['fecha'],
                    'moneda' => 'CLP',
                    'iva_porcentaje' => 19,
                    'subtotal' => $subtotal,
                    'iva' => $iva,
                    'total' => $subtotal + $iva,
                    'estado' => $dato['estado'],
                    'cliente_snapshot' => [
                        'razon_social' => $cliente->razon_social,
                        'nombre_fantasia' => $cliente->nombre_fantasia,
                        'identificador_tributario' => $cliente->identificador_tributario,
                    ],
                    'contacto_snapshot' => $contacto
                        ? ['nombre' => $contacto->nombre]
                        : null,
                ]);

                $revisionServicio = $revision->servicios()->create([
                    'servicio_id' => $servicio->id,
                    'titulo' => $dato['titulo'],
                    'descripcion' => "Contenido histórico transcrito desde {$dato['codigo']}.",
                    'orden' => 1,
                ]);

                foreach ($dato['partidas'] as $orden => $partida) {
                    $revision->partidas()->create([
                        'revision_servicio_id' => $revisionServicio->id,
                        'clase' => $partida['clase'] ?? 'servicio',
                        'descripcion' => $partida['descripcion'],
                        'cantidad' => 1,
                        'unidad' => 'servicio',
                        'precio_unitario' => $partida['monto'],
                        'monto_neto' => $partida['monto'],
                        'metodo_precio' => 'referencia',
                        'monto_sugerido' => null,
                        'monto_final' => $partida['monto'],
                        'justificacion_ajuste' => 'Valor histórico transcrito; no constituye tarifa vigente.',
                        'orden' => $orden + 1,
                    ]);
                }

                if ($dato['nota']) {
                    $revision->bloques()->create([
                        'tipo' => 'nota_importacion',
                        'titulo' => 'Validación pendiente',
                        'contenido' => $dato['nota'],
                        'orden' => 1,
                        'visible' => true,
                    ]);
                }

                $cotizacion->update(['revision_actual_id' => $revision->id]);
                EventoHistorial::create([
                    'registrable_type' => Cotizacion::class,
                    'registrable_id' => $cotizacion->id,
                    'user_id' => $usuario->id,
                    'evento' => 'importacion_historica',
                    'descripcion' => 'Cotización importada desde el archivo histórico LINDE.',
                    'cambios' => [
                        'fuente' => $dato['codigo'],
                        'requiere_validacion' => $dato['estado'] === 'por_validar',
                    ],
                ]);

                $creadas++;
            }

            return ['creadas' => $creadas, 'omitidas' => $omitidas];
        });
    }

    private function datos(): array
    {
        return [
            [
                'codigo' => 'COT-LINDE-0004',
                'fecha' => '2026-04-27',
                'contacto' => 'Rodrigo Fernández',
                'tipo' => 'PID',
                'plantas' => ['concepcion'],
                'estado' => 'aceptada',
                'titulo' => 'Revisión y regularización de P&ID según condición actual de planta',
                'nota' => null,
                'partidas' => [
                    [
                        'descripcion' => 'Revisión y actualización de P&ID en terreno',
                        'monto' => 380000,
                    ],
                ],
            ],
            [
                'codigo' => 'COT-LINDE-0005',
                'fecha' => '2026-05-15',
                'contacto' => 'Rodrigo Fernández',
                'tipo' => 'ACT-CRIT',
                'plantas' => ['concepcion'],
                'estado' => 'aceptada',
                'titulo' => 'Levantamiento técnico, documentación crítica e identificación operacional',
                'nota' => null,
                'partidas' => [
                    [
                        'descripcion' => 'Registro en planta de PCO, bloqueos, válvulas de seguridad e instrumentación crítica',
                        'monto' => 280000,
                    ],
                    [
                        'descripcion' => 'Revisión de certificados y antecedentes de instrumentación crítica',
                        'monto' => 160000,
                    ],
                    [
                        'descripcion' => 'Incorporación en plano de PCO, bloqueos, TAGs y elementos levantados',
                        'monto' => 180000,
                    ],
                    [
                        'descripcion' => 'Preparación, plastificado e instalación de placas PCO y TAGs',
                        'monto' => 210000,
                    ],
                    [
                        'descripcion' => 'Consolidación de matrices y brechas documentales',
                        'monto' => 60000,
                    ],
                ],
            ],
            [
                'codigo' => 'COT-LINDE-0007',
                'fecha' => '2026-05-04',
                'contacto' => 'Sebastián Espinoza',
                'tipo' => 'PID',
                'plantas' => ['antofagasta'],
                'estado' => 'aceptada',
                'titulo' => 'Regularización documental, actualización de P&ID e identificación de elementos críticos',
                'nota' => null,
                'partidas' => [
                    [
                        'descripcion' => 'Levantamiento técnico en terreno de sistemas y elementos críticos',
                        'monto' => 950000,
                    ],
                    [
                        'descripcion' => 'Regularización, actualización y normalización de documentación P&ID',
                        'monto' => 1000000,
                    ],
                    [
                        'descripcion' => 'Trazabilidad de certificados de instrumentación y válvulas de seguridad',
                        'monto' => 300000,
                    ],
                    [
                        'descripcion' => 'Bloques atributados e información técnica en AutoCAD',
                        'monto' => 350000,
                    ],
                    [
                        'descripcion' => 'Desarrollo de matrices PCO y CSS',
                        'monto' => 800000,
                    ],
                    [
                        'descripcion' => 'Preparación e instalación de placas PCO, CSS y TAGs',
                        'monto' => 300000,
                    ],
                    [
                        'descripcion' => 'Traslado, estadía, alimentación y movilización local',
                        'monto' => 1000000,
                        'clase' => 'traslado',
                    ],
                ],
            ],
            [
                'codigo' => 'COT-LINDE-0008',
                'fecha' => '2026-06-22',
                'contacto' => 'Sebastián Espinoza',
                'tipo' => 'MOD-3D',
                'plantas' => [],
                'estado' => 'por_validar',
                'titulo' => 'Modelado 3D y documentación técnica de protección para válvulas criogénicas',
                'nota' => 'El título y los entregables describen modelado 3D, pero la partida económica transcrita describe un levantamiento general. Confirmar alcance y planta antes de reutilizar.',
                'partidas' => [
                    [
                        'descripcion' => 'Partida histórica de levantamiento técnico indicada en el documento',
                        'monto' => 150000,
                    ],
                ],
            ],
            [
                'codigo' => 'COT-LINDE-0009',
                'fecha' => '2026-06-30',
                'contacto' => 'Rodrigo Fernández',
                'tipo' => 'PID',
                'plantas' => ['concepcion'],
                'estado' => 'aceptada',
                'titulo' => 'Levantamiento y actualización de P&ID de máquina de hielo seco',
                'nota' => null,
                'partidas' => [
                    [
                        'descripcion' => 'Levantamiento e incorporación de máquina de hielo seco al P&ID maestro',
                        'monto' => 200000,
                    ],
                ],
            ],
            [
                'codigo' => 'COT-LINDE-0010',
                'fecha' => '2026-07-24',
                'contacto' => 'Álvaro Villarroel',
                'tipo' => 'LAYOUT',
                'plantas' => ['concepcion', 'puerto_montt', 'punta_arenas'],
                'estado' => 'anulada',
                'titulo' => 'Layouts de emergencia para plantas industriales',
                'nota' => 'La carpeta fuente identifica esta cotización como anulada. No debe tratarse como antecedente contratado sin validación.',
                'partidas' => [
                    [
                        'descripcion' => 'Layout de emergencia Planta Concepción',
                        'monto' => 350000,
                    ],
                    [
                        'descripcion' => 'Layout de emergencia Planta Puerto Montt',
                        'monto' => 475000,
                    ],
                    [
                        'descripcion' => 'Layout de emergencia Planta Punta Arenas',
                        'monto' => 475000,
                    ],
                ],
            ],
            [
                'codigo' => 'COT-LINDE-0011',
                'fecha' => '2026-07-29',
                'contacto' => 'Sergio Parra',
                'tipo' => 'PID',
                'plantas' => ['nacimiento'],
                'estado' => 'aceptada',
                'titulo' => 'Actualización documental de P&ID - ASU Santa Fe / Nacimiento',
                'nota' => null,
                'partidas' => [
                    [
                        'descripcion' => 'Actualización de P&ID sistema Back up tanque LOX D3802',
                        'monto' => 390000,
                    ],
                    [
                        'descripcion' => 'Actualización del P&ID general de planta',
                        'monto' => 400000,
                    ],
                    [
                        'descripcion' => 'Identificación gráfica de PCO y revisión documental',
                        'monto' => 260000,
                    ],
                    [
                        'descripcion' => 'Hasta tres visitas técnicas a terreno',
                        'monto' => 210000,
                        'clase' => 'traslado',
                    ],
                ],
            ],
            [
                'codigo' => 'COT-LINDE-0012',
                'fecha' => '2026-07-29',
                'contacto' => 'Sergio Parra',
                'tipo' => 'PID',
                'plantas' => ['nacimiento'],
                'estado' => 'aceptada',
                'titulo' => 'Vectorización y normalización CAD de P&ID - ASU Santa Fe / Nacimiento',
                'nota' => null,
                'partidas' => [
                    [
                        'descripcion' => 'Vectorización y normalización CAD P&ID SF-80.01.01',
                        'monto' => 420000,
                    ],
                    [
                        'descripcion' => 'Vectorización y normalización CAD P&ID LOX600-CSSN-02',
                        'monto' => 440000,
                    ],
                    [
                        'descripcion' => 'Vectorización y normalización CAD P&ID SF-73.10.0',
                        'monto' => 400000,
                    ],
                ],
            ],
            [
                'codigo' => 'COT-LINDE-0013',
                'fecha' => '2026-08-04',
                'contacto' => 'Álvaro Villarroel',
                'tipo' => 'LAYOUT',
                'plantas' => ['concepcion', 'puerto_montt'],
                'estado' => 'aceptada',
                'titulo' => 'Layouts de emergencia Zona Sur - Concepción y Puerto Montt',
                'nota' => null,
                'partidas' => [
                    [
                        'descripcion' => 'Layout de emergencia Planta Concepción',
                        'monto' => 350000,
                    ],
                    [
                        'descripcion' => 'Layout de emergencia Planta Puerto Montt',
                        'monto' => 450000,
                    ],
                ],
            ],
            [
                'codigo' => 'COT-LINDE-0014',
                'fecha' => '2026-08-04',
                'contacto' => 'Álvaro Villarroel',
                'tipo' => 'LAYOUT',
                'plantas' => ['punta_arenas', 'valdivia'],
                'estado' => 'aceptada',
                'titulo' => 'Layouts de emergencia Zona Sur - Punta Arenas y Valdivia',
                'nota' => null,
                'partidas' => [
                    [
                        'descripcion' => 'Layout de emergencia Planta Punta Arenas',
                        'monto' => 450000,
                    ],
                    [
                        'descripcion' => 'Layout de emergencia Planta Valdivia',
                        'monto' => 400000,
                    ],
                ],
            ],
            [
                'codigo' => 'COT-LINDE-0015',
                'fecha' => '2026-08-11',
                'contacto' => 'Rodrigo Fernández',
                'tipo' => 'PID',
                'plantas' => ['concepcion'],
                'estado' => 'aceptada',
                'titulo' => 'Actualización de P&ID y regularización de información crítica',
                'nota' => null,
                'partidas' => [
                    [
                        'descripcion' => 'Levantamiento focalizado y actualización de P&ID',
                        'monto' => 490000,
                    ],
                    [
                        'descripcion' => 'Detalle técnico de caja de presión',
                        'monto' => 310000,
                    ],
                    [
                        'descripcion' => 'Revisión y actualización de Matriz PCO',
                        'monto' => 250000,
                    ],
                    [
                        'descripcion' => 'Instalación y verificación de TAG en terreno',
                        'monto' => 180000,
                    ],
                ],
            ],
            [
                'codigo' => 'ING-COT-2026-016',
                'fecha' => '2026-09-09',
                'contacto' => 'Rodrigo Fernández',
                'tipo' => 'SEG-PROC',
                'plantas' => ['concepcion'],
                'estado' => 'por_validar',
                'titulo' => 'Regularización, desarrollo y gestión PSI - Concepción',
                'nota' => 'Existen formatos breve y técnico-económico. Se carga como referencia la propuesta Rev. 1; confirmar cuál fue emitida o aceptada.',
                'partidas' => [
                    [
                        'descripcion' => 'Revisión, desarrollo y gestión documental PSI',
                        'monto' => 512000,
                    ],
                ],
            ],
            [
                'codigo' => 'ING-COT-2026-017',
                'fecha' => '2026-09-09',
                'contacto' => 'Rodrigo Fernández',
                'tipo' => 'SEG-PROC',
                'plantas' => ['valdivia'],
                'estado' => 'por_validar',
                'titulo' => 'Levantamiento, diagnóstico, desarrollo y gestión PSI - Valdivia',
                'nota' => 'Existen formatos breve y técnico-económico. Se carga como referencia la propuesta Rev. 1; confirmar cuál fue emitida o aceptada.',
                'partidas' => [
                    [
                        'descripcion' => 'Levantamiento, diagnóstico, desarrollo y gestión PSI',
                        'monto' => 376000,
                    ],
                    [
                        'descripcion' => 'Levantamiento focalizado de activos y certificados',
                        'monto' => 128000,
                    ],
                    [
                        'descripcion' => 'Traslado, alojamiento, alimentación y movilización local',
                        'monto' => 250000,
                        'clase' => 'traslado',
                    ],
                ],
            ],
            [
                'codigo' => 'ING-COT-2026-018',
                'fecha' => '2026-09-09',
                'contacto' => 'Rodrigo Fernández',
                'tipo' => 'SEG-PROC',
                'plantas' => ['puerto_montt'],
                'estado' => 'por_validar',
                'titulo' => 'Levantamiento, diagnóstico, desarrollo y gestión PSI - Puerto Montt',
                'nota' => 'La propuesta Rev. 1 totaliza neto $1.990.600; el documento breve contiene gastos y total distintos. Confirmar variante aceptada.',
                'partidas' => [
                    [
                        'descripcion' => 'Levantamiento, diagnóstico, desarrollo y gestión PSI',
                        'monto' => 643000,
                    ],
                    [
                        'descripcion' => 'Levantamiento de activos y certificados',
                        'monto' => 352000,
                    ],
                    [
                        'descripcion' => 'Vectorización y normalización de documentación P&ID',
                        'monto' => 505600,
                    ],
                    [
                        'descripcion' => 'Traslado, permanencia, alimentación y movilización local',
                        'monto' => 490000,
                        'clase' => 'traslado',
                    ],
                ],
            ],
            [
                'codigo' => 'ING-COT-2026-019',
                'fecha' => '2026-09-09',
                'contacto' => 'Rodrigo Fernández',
                'tipo' => 'SEG-PROC',
                'plantas' => ['punta_arenas'],
                'estado' => 'por_validar',
                'titulo' => 'Levantamiento, diagnóstico, desarrollo y gestión PSI - Punta Arenas',
                'nota' => 'La propuesta Rev. 1 totaliza neto $1.774.000; el documento breve contiene gastos y total distintos. Confirmar variante aceptada.',
                'partidas' => [
                    [
                        'descripcion' => 'Levantamiento, diagnóstico, desarrollo y gestión PSI',
                        'monto' => 576000,
                    ],
                    [
                        'descripcion' => 'Levantamiento de activos, certificados e información técnica',
                        'monto' => 224000,
                    ],
                    [
                        'descripcion' => 'Vectorización y actualización de documentación P&ID',
                        'monto' => 384000,
                    ],
                    [
                        'descripcion' => 'Transporte aéreo, alojamiento, alimentación y movilización local',
                        'monto' => 590000,
                        'clase' => 'traslado',
                    ],
                ],
            ],
        ];
    }
}
