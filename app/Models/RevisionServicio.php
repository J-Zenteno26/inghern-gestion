<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RevisionServicio extends Model
{
    protected $table = 'revision_servicios';

    protected $fillable = [
        'cotizacion_revision_id',
        'servicio_id',
        'catalogo_servicio_id',
        'titulo',
        'descripcion',
        'orden',
    ];

    public function revision(): BelongsTo
    {
        return $this->belongsTo(
            CotizacionRevision::class,
            'cotizacion_revision_id',
        );
    }

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class);
    }

    public function catalogoServicio(): BelongsTo
    {
        return $this->belongsTo(CatalogoServicio::class);
    }

    public function partidas(): HasMany
    {
        return $this->hasMany(PartidaCotizacion::class);
    }
}
