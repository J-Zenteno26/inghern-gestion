<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

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

    public function documentos(): MorphToMany
    {
        return $this->morphToMany(
            Documento::class,
            'vinculable',
            'documento_vinculos',
        )->withTimestamps();
    }
}
