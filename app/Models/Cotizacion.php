<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cotizacion extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'cotizaciones';

    protected $fillable = [
        'cliente_id',
        'planta_id',
        'creado_por',
        'codigo',
        'estado',
        'revision_actual_id',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function planta(): BelongsTo
    {
        return $this->belongsTo(Planta::class);
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function revisiones(): HasMany
    {
        return $this->hasMany(CotizacionRevision::class);
    }

    public function revisionActual(): BelongsTo
    {
        return $this->belongsTo(
            CotizacionRevision::class,
            'revision_actual_id',
        );
    }

    public function ordenCompra(): HasOne
    {
        return $this->hasOne(OrdenCompra::class);
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
