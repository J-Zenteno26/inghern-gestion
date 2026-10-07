<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RevisionValorVariable extends Model
{
    protected $table = 'revision_valores_variable';

    protected $fillable = [
        'cotizacion_revision_id',
        'revision_servicio_id',
        'partida_cotizacion_id',
        'variable_precio_id',
        'nivel_variable_precio_id',
        'variable_codigo',
        'nivel_nombre',
        'factor_aplicado',
        'peso_aplicado',
        'fuente',
        'justificacion',
    ];

    protected function casts(): array
    {
        return [
            'factor_aplicado' => 'decimal:4',
            'peso_aplicado' => 'decimal:4',
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

    public function variable(): BelongsTo
    {
        return $this->belongsTo(VariablePrecio::class, 'variable_precio_id');
    }

    public function partida(): BelongsTo
    {
        return $this->belongsTo(
            PartidaCotizacion::class,
            'partida_cotizacion_id',
        );
    }

    public function nivel(): BelongsTo
    {
        return $this->belongsTo(
            NivelVariablePrecio::class,
            'nivel_variable_precio_id',
        );
    }
}
