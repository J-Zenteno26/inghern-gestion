<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CotizacionPlazo extends Model
{
    protected $table = 'cotizacion_plazos';

    protected $fillable = [
        'cotizacion_revision_id',
        'hito',
        'duracion',
        'unidad',
        'condicion_inicio',
        'orden',
    ];

    public function revision(): BelongsTo
    {
        return $this->belongsTo(
            CotizacionRevision::class,
            'cotizacion_revision_id',
        );
    }
}
