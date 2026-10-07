<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CotizacionBloque extends Model
{
    protected $table = 'cotizacion_bloques';

    protected $fillable = [
        'cotizacion_revision_id',
        'tipo',
        'titulo',
        'contenido',
        'orden',
        'visible',
    ];

    protected function casts(): array
    {
        return ['visible' => 'boolean'];
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(
            CotizacionRevision::class,
            'cotizacion_revision_id',
        );
    }
}
