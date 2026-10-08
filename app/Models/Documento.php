<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Documento extends Model
{
    public const TIPOS = [
        'plano' => 'Plano',
        'especificacion_tecnica' => 'Especificación técnica',
        'fotografia' => 'Fotografía',
        'cotizacion_respaldo_comercial' => 'Cotización / respaldo comercial',
        'orden_compra' => 'Orden de compra',
        'factura_respaldo' => 'Factura / respaldo',
        'informe' => 'Informe',
        'certificado' => 'Certificado',
        'documento_cliente' => 'Documento cliente',
        'otro' => 'Otro',
    ];

    public static function iconoParaTipo(string $tipo): string
    {
        return match ($tipo) {
            'plano' => 'drafting-compass',
            'fotografia' => 'image',
            'especificacion_tecnica', 'informe', 'certificado' => 'file-text',
            'cotizacion_respaldo_comercial', 'orden_compra', 'factura_respaldo' => 'receipt',
            'documento_cliente' => 'file-type',
            default => 'file',
        };
    }

    public function icono(): string
    {
        return match (strtolower($this->extension)) {
            'pdf' => 'file-pdf',
            'doc', 'docx' => 'file-type',
            'xls', 'xlsx' => 'file-spreadsheet',
            'jpg', 'jpeg', 'png' => 'image',
            'dwg' => 'drafting-compass',
            default => self::iconoParaTipo($this->tipo_documento),
        };
    }

    protected $fillable = [
        'cliente_id',
        'nombre',
        'nombre_original',
        'descripcion',
        'tipo_documento',
        'mime_type',
        'extension',
        'tamano',
        'ruta_storage',
        'usuario_creador_id',
    ];

    protected function casts(): array
    {
        return [
            'tamano' => 'integer',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function usuarioCreador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_creador_id');
    }

    public function vinculos(): HasMany
    {
        return $this->hasMany(DocumentoVinculo::class);
    }

    public function scopeDeCotizacion(Builder $query, Cotizacion $cotizacion): Builder
    {
        $revisionServicioIds = $this->revisionServicioIds($cotizacion);

        return $query->where(function (Builder $contexto) use ($cotizacion, $revisionServicioIds): void {
            $contexto
                ->whereHas('vinculos', function (Builder $vinculos) use ($cotizacion): void {
                    $vinculos
                        ->where('vinculable_type', $cotizacion->getMorphClass())
                        ->where('vinculable_id', $cotizacion->getKey());
                })
                ->orWhereHas('vinculos', function (Builder $vinculos) use ($revisionServicioIds): void {
                    $vinculos
                        ->where('vinculable_type', (new RevisionServicio)->getMorphClass())
                        ->whereIn('vinculable_id', $revisionServicioIds);
                });
        });
    }

    public function scopeGeneralesDeCotizacion(Builder $query, Cotizacion $cotizacion): Builder
    {
        $revisionServicioIds = $this->revisionServicioIds($cotizacion);

        return $query
            ->whereHas('vinculos', function (Builder $vinculos) use ($cotizacion): void {
                $vinculos
                    ->where('vinculable_type', $cotizacion->getMorphClass())
                    ->where('vinculable_id', $cotizacion->getKey());
            })
            ->whereDoesntHave('vinculos', function (Builder $vinculos) use ($revisionServicioIds): void {
                $vinculos
                    ->where('vinculable_type', (new RevisionServicio)->getMorphClass())
                    ->whereIn('vinculable_id', $revisionServicioIds);
            });
    }

    public function scopeDePlanta(Builder $query, Planta $planta): Builder
    {
        return $query->where(function (Builder $contexto) use ($planta): void {
            $contexto
                ->whereHas('vinculos', function (Builder $vinculos) use ($planta): void {
                    $vinculos
                        ->where('vinculable_type', $planta->getMorphClass())
                        ->where('vinculable_id', $planta->getKey());
                })
                ->orWhereHas('vinculos', function (Builder $vinculos) use ($planta): void {
                    $vinculos
                        ->where('vinculable_type', (new Cotizacion)->getMorphClass())
                        ->whereIn(
                            'vinculable_id',
                            Cotizacion::query()
                                ->select('cotizaciones.id')
                                ->where('planta_id', $planta->getKey()),
                        );
                })
                ->orWhereHas('vinculos', function (Builder $vinculos) use ($planta): void {
                    $vinculos
                        ->where('vinculable_type', (new RevisionServicio)->getMorphClass())
                        ->whereIn(
                            'vinculable_id',
                            RevisionServicio::query()
                                ->select('revision_servicios.id')
                                ->whereHas(
                                    'revision.cotizacion',
                                    fn (Builder $cotizaciones) => $cotizaciones->where('planta_id', $planta->getKey()),
                                ),
                        );
                })
                ->orWhereHas('vinculos', function (Builder $vinculos) use ($planta): void {
                    $vinculos
                        ->where('vinculable_type', (new Servicio)->getMorphClass())
                        ->whereIn(
                            'vinculable_id',
                            Servicio::query()
                                ->select('servicios.id')
                                ->whereHas('plantas', fn (Builder $plantas) => $plantas->whereKey($planta->getKey())),
                        );
                });
        });
    }

    /**
     * @return array<int, int>
     */
    private function revisionServicioIds(Cotizacion $cotizacion): array
    {
        return RevisionServicio::query()
            ->whereHas(
                'revision',
                fn (Builder $revisiones) => $revisiones->where('cotizacion_id', $cotizacion->getKey()),
            )
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }
}
