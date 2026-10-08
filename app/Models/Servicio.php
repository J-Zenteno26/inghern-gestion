<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Servicio extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'cliente_id',
        'tipo_servicio_id',
        'catalogo_servicio_id',
        'servicio_otro',
        'creado_por',
        'codigo',
        'nombre',
        'descripcion',
        'estado',
        'fecha_inicio_estimada',
        'fecha_termino_estimada',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio_estimada' => 'date',
            'fecha_termino_estimada' => 'date',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TipoServicio::class, 'tipo_servicio_id');
    }

    public function catalogoServicio(): BelongsTo
    {
        return $this->belongsTo(
            CatalogoServicio::class,
            'catalogo_servicio_id',
        );
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function plantas(): BelongsToMany
    {
        return $this->belongsToMany(Planta::class, 'servicio_planta');
    }

    public function inclusionesCotizacion(): HasMany
    {
        return $this->hasMany(RevisionServicio::class);
    }

    public function documentos(): MorphToMany
    {
        return $this->morphToMany(Documento::class, 'vinculable', 'documento_vinculos')
            ->withTimestamps();
    }
}
