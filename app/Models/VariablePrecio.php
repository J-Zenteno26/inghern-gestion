<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VariablePrecio extends Model
{
    protected $table = 'variables_precio';

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'aplica_a',
        'activo',
    ];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function niveles(): HasMany
    {
        return $this->hasMany(NivelVariablePrecio::class)->orderBy('orden');
    }

    public function tiposServicio(): BelongsToMany
    {
        return $this->belongsToMany(
            TipoServicio::class,
            'tipo_servicio_variable',
        )->withPivot(['peso', 'requerida']);
    }
}
