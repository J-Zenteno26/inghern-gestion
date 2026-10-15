<?php

namespace App\Console\Commands;

use App\Domain\Cotizaciones\RegistrarPago;
use App\Models\Cotizacion;
use App\Models\Documento;
use App\Models\EventoHistorial;
use App\Models\Factura;
use App\Models\Pago;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

#[Signature('inghern:importar-pagos-linde
    {--user= : ID del usuario responsable de la importación}
    {--commit : Confirma la creación de los pagos importables}')]
#[Description('Analiza o importa pagos históricos de Linde respaldados por evidencia revisada')]
class ImportarPagosLinde extends Command
{
    /**
     * Evidencias aprobadas manualmente después de revisar el contenido del documento.
     * Una entrada solo puede añadirse si el documento acredita factura, monto, fecha y medio de pago.
     *
     * @var array<string, array{
     *     documento_id: int,
     *     fecha_pago: string,
     *     monto: int|float,
     *     moneda: string,
     *     medio_pago: string,
     *     referencia?: ?string,
     *     observacion?: ?string
     * }>
     */
    private const EVIDENCIAS_REVISADAS = [];

    private const MEDIOS_PAGO = [
        'transferencia',
        'deposito',
        'cheque',
        'efectivo',
        'otro',
    ];

    public function handle(RegistrarPago $registrarPago): int
    {
        $commit = (bool) $this->option('commit');
        $usuario = $commit ? $this->resolveUser() : null;

        if ($commit && $usuario === null) {
            $this->error('Con --commit debes indicar --user= con el ID de un usuario existente.');

            return self::FAILURE;
        }

        $this->info($commit ? 'MODO COMMIT' : 'MODO SIMULACIÓN · sin escrituras');
        $this->line('Solo se aceptan evidencias revisadas que acrediten factura, monto, fecha y medio de pago.');
        $this->newLine();

        $cotizaciones = Cotizacion::query()
            ->where('codigo', 'like', 'COT-LINDE-%')
            ->with([
                'documentos',
                'ordenCompra.documentos',
                'ordenCompra.facturas.documentos',
            ])
            ->orderBy('codigo')
            ->get();

        $filas = [];
        $resumen = [
            'importables' => 0,
            'existentes' => 0,
            'revisar' => 0,
            'sin_evidencia' => 0,
            'errores' => 0,
        ];

        foreach ($cotizaciones as $cotizacion) {
            $ordenCompra = $cotizacion->ordenCompra;

            if ($ordenCompra === null || $ordenCompra->facturas->isEmpty()) {
                $filas[] = [
                    $cotizacion->codigo,
                    $ordenCompra?->numero ?? '—',
                    '—',
                    '—',
                    $this->documentosTexto($cotizacion->documentos),
                    $ordenCompra === null
                        ? 'No existe OC histórica registrada.'
                        : 'No existen facturas históricas registradas.',
                    'revisar',
                ];
                $resumen['revisar']++;

                continue;
            }

            foreach ($ordenCompra->facturas as $factura) {
                $documentos = $this->documentosDeFactura($cotizacion, $factura);
                $claveCatalogo = $cotizacion->codigo.'|'.trim((string) $factura->folio);
                $evidencia = self::EVIDENCIAS_REVISADAS[$claveCatalogo] ?? null;

                if ($evidencia === null) {
                    $estado = $documentos->isEmpty() ? 'sin evidencia' : 'revisar';
                    $faltante = $documentos->isEmpty()
                        ? 'Monto pagado real, fecha real y medio de pago respaldado.'
                        : 'Los documentos no acreditan monto, fecha y medio de pago reales.';

                    $filas[] = $this->filaFactura(
                        $cotizacion->codigo,
                        $ordenCompra->numero,
                        $factura,
                        $this->documentosTexto($documentos),
                        $faltante,
                        $estado,
                    );
                    $resumen[$documentos->isEmpty() ? 'sin_evidencia' : 'revisar']++;

                    continue;
                }

                $validacion = $this->validarEvidencia($factura, $documentos, $evidencia);

                if ($validacion !== null) {
                    $filas[] = $this->filaFactura(
                        $cotizacion->codigo,
                        $ordenCompra->numero,
                        $factura,
                        $this->documentosTexto($documentos),
                        $validacion,
                        'revisar',
                    );
                    $resumen['revisar']++;

                    continue;
                }

                $claveImportacion = $this->claveImportacion($factura, $evidencia);

                if ($this->yaImportado($claveImportacion)) {
                    $filas[] = $this->filaFactura(
                        $cotizacion->codigo,
                        $ordenCompra->numero,
                        $factura,
                        $this->documentosTexto($documentos),
                        'Ninguno.',
                        'ya existente',
                    );
                    $resumen['existentes']++;

                    continue;
                }

                if (! $commit) {
                    $filas[] = $this->filaFactura(
                        $cotizacion->codigo,
                        $ordenCompra->numero,
                        $factura,
                        $this->documentosTexto($documentos),
                        'Ninguno.',
                        'importable',
                    );
                    $resumen['importables']++;

                    continue;
                }

                try {
                    $creado = $this->importar(
                        $registrarPago,
                        $factura,
                        $evidencia,
                        $claveImportacion,
                        $usuario,
                    );
                    $estado = $creado ? 'importado' : 'ya existente';
                    $resumen[$creado ? 'importables' : 'existentes']++;
                    $filas[] = $this->filaFactura(
                        $cotizacion->codigo,
                        $ordenCompra->numero,
                        $factura,
                        $this->documentosTexto($documentos),
                        'Ninguno.',
                        $estado,
                    );
                } catch (Throwable $throwable) {
                    $filas[] = $this->filaFactura(
                        $cotizacion->codigo,
                        $ordenCompra->numero,
                        $factura,
                        $this->documentosTexto($documentos),
                        $throwable->getMessage(),
                        'error',
                    );
                    $resumen['errores']++;
                }
            }
        }

        if ($filas === []) {
            $this->warn('No existen cotizaciones Linde con facturas históricas disponibles para analizar.');
        } else {
            $this->table(
                ['Cotización', 'OC', 'Factura', 'Total', 'Evidencia encontrada', 'Falta / observación', 'Estado'],
                $filas,
            );
        }

        $this->newLine();
        $this->table(
            ['Resultado', 'Cantidad'],
            [
                ['Importables', $resumen['importables']],
                ['Ya existentes', $resumen['existentes']],
                ['Requieren revisión', $resumen['revisar']],
                ['Sin evidencia', $resumen['sin_evidencia']],
                ['Errores', $resumen['errores']],
            ],
        );

        if (! $commit) {
            $this->info('Simulación terminada. No se creó ni modificó ningún pago.');
            $this->line('Usa --commit únicamente después de revisar y aprobar el reporte.');
        }

        return $resumen['errores'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function resolveUser(): ?User
    {
        $userId = $this->option('user');

        if ($userId === null || $userId === '' || ! ctype_digit((string) $userId)) {
            return null;
        }

        return User::query()->find((int) $userId);
    }

    /**
     * @return Collection<int, Documento>
     */
    private function documentosDeFactura(Cotizacion $cotizacion, Factura $factura): Collection
    {
        return collect()
            ->concat($cotizacion->documentos)
            ->concat($cotizacion->ordenCompra?->documentos ?? collect())
            ->concat($factura->documentos)
            ->unique('id')
            ->values();
    }

    /**
     * @param  Collection<int, Documento>  $documentos
     * @param  array{documento_id: int, fecha_pago: string, monto: int|float, moneda: string, medio_pago: string, referencia?: ?string, observacion?: ?string}  $evidencia
     */
    private function validarEvidencia(Factura $factura, Collection $documentos, array $evidencia): ?string
    {
        if ($factura->estado === 'anulada') {
            return 'La factura está anulada.';
        }

        if (! $documentos->contains('id', $evidencia['documento_id'])) {
            return 'El documento aprobado no está vinculado a la factura, OC o cotización.';
        }

        if (! $this->fechaValida($evidencia['fecha_pago'])) {
            return 'La fecha de pago acreditada no es válida.';
        }

        $monto = round((float) $evidencia['monto'], 2);

        if ($monto <= 0) {
            return 'El monto pagado acreditado debe ser mayor que cero.';
        }

        if ($evidencia['moneda'] !== $factura->moneda) {
            return 'La moneda acreditada no coincide con la factura.';
        }

        if (! in_array($evidencia['medio_pago'], self::MEDIOS_PAGO, true)) {
            return 'El medio de pago no está respaldado o no es válido para el sistema.';
        }

        if ($monto > $factura->saldoPago() + 0.009) {
            return 'El monto acreditado excede el saldo disponible de la factura.';
        }

        return null;
    }

    /**
     * @param  array{documento_id: int, fecha_pago: string, monto: int|float, moneda: string, medio_pago: string, referencia?: ?string, observacion?: ?string}  $evidencia
     */
    private function importar(
        RegistrarPago $registrarPago,
        Factura $factura,
        array $evidencia,
        string $claveImportacion,
        User $usuario,
    ): bool {
        return DB::transaction(function () use (
            $registrarPago,
            $factura,
            $evidencia,
            $claveImportacion,
            $usuario,
        ): bool {
            Factura::query()->whereKey($factura->id)->lockForUpdate()->firstOrFail();

            if ($this->yaImportado($claveImportacion)) {
                return false;
            }

            $pago = $registrarPago->ejecutar([
                'fecha_pago' => $evidencia['fecha_pago'],
                'monto_total' => $evidencia['monto'],
                'medio_pago' => $evidencia['medio_pago'],
                'referencia' => $evidencia['referencia'] ?? null,
                'observacion' => $evidencia['observacion'] ?? null,
                'asignaciones' => [[
                    'factura_id' => $factura->id,
                    'monto_asignado' => $evidencia['monto'],
                ]],
            ], $usuario->id);

            EventoHistorial::create([
                'registrable_type' => Pago::class,
                'registrable_id' => $pago->id,
                'user_id' => $usuario->id,
                'evento' => 'pago_historico_linde_importado',
                'descripcion' => "Pago histórico Linde importado desde documento #{$evidencia['documento_id']}.",
                'cambios' => [
                    'clave_importacion' => $claveImportacion,
                    'documento_id' => $evidencia['documento_id'],
                    'factura_id' => $factura->id,
                    'monto' => round((float) $evidencia['monto'], 2),
                    'fecha_pago' => $evidencia['fecha_pago'],
                ],
            ]);

            return true;
        });
    }

    private function yaImportado(string $claveImportacion): bool
    {
        return EventoHistorial::query()
            ->where('evento', 'pago_historico_linde_importado')
            ->get(['cambios'])
            ->contains(
                fn (EventoHistorial $evento): bool => data_get($evento->cambios, 'clave_importacion') === $claveImportacion,
            );
    }

    /**
     * @param  array{documento_id: int, fecha_pago: string, monto: int|float, moneda: string, medio_pago: string, referencia?: ?string, observacion?: ?string}  $evidencia
     */
    private function claveImportacion(Factura $factura, array $evidencia): string
    {
        return hash('sha256', implode('|', [
            'linde',
            $evidencia['documento_id'],
            $factura->id,
            $evidencia['fecha_pago'],
            number_format((float) $evidencia['monto'], 2, '.', ''),
        ]));
    }

    private function fechaValida(string $fecha): bool
    {
        $valor = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);

        return $valor !== false && $valor->format('Y-m-d') === $fecha;
    }

    /**
     * @param  Collection<int, Documento>  $documentos
     */
    private function documentosTexto(Collection $documentos): string
    {
        if ($documentos->isEmpty()) {
            return 'Ninguna';
        }

        return Str::limit(
            $documentos
                ->map(fn (Documento $documento): string => "#{$documento->id} {$documento->nombre_original}")
                ->implode(', '),
            120,
        );
    }

    /**
     * @return array<int, string>
     */
    private function filaFactura(
        string $cotizacion,
        string $ordenCompra,
        Factura $factura,
        string $evidencia,
        string $faltante,
        string $estado,
    ): array {
        return [
            $cotizacion,
            $ordenCompra,
            $factura->folio,
            $factura->moneda.' $'.number_format((float) $factura->total, 0, ',', '.'),
            $evidencia,
            $faltante,
            $estado,
        ];
    }
}
