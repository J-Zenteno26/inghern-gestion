<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartidaCotizacion extends Model
{
    use HasFactory;

    protected $table = 'partidas_cotizacion';

    protected $fillable = [
        'cotizacion_revision_id',
        'revision_servicio_id',
        'catalogo_servicio_id',
        'clase',
        'descripcion',
        'cantidad',
        'unidad',
        'precio_unitario',
        'monto_neto',
        'metodo_precio',
        'monto_sugerido',
        'monto_final',
        'justificacion_ajuste',
        'catalogo_nombre_snapshot',
        'catalogo_descripcion_snapshot',
        'precio_base_snapshot',
        'moneda_precio_snapshot',
        'unidad_precio_snapshot',
        'factor_total_snapshot',
        'rango_minimo_snapshot',
        'rango_maximo_snapshot',
        'orden',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:3',
            'precio_unitario' => 'decimal:2',
            'monto_neto' => 'decimal:2',
            'monto_sugerido' => 'decimal:2',
            'monto_final' => 'decimal:2',
            'precio_base_snapshot' => 'decimal:2',
            'factor_total_snapshot' => 'decimal:6',
            'rango_minimo_snapshot' => 'decimal:2',
            'rango_maximo_snapshot' => 'decimal:2',
        ];
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(
            CotizacionRevision::class,
            'cotizacion_revision_id',
        );
    }

    public function servicioIncluido(): BelongsTo
    {
        return $this->belongsTo(
            RevisionServicio::class,
            'revision_servicio_id',
        );
    }

    public function catalogoServicio(): BelongsTo
    {
        return $this->belongsTo(CatalogoServicio::class);
    }

    public function valoresVariables(): HasMany
    {
        return $this->hasMany(
            RevisionValorVariable::class,
            'partida_cotizacion_id',
        );
    }
}
