<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoServicio extends Model
{
    use HasFactory;

    protected $table = 'tipo_servicios';

    protected $fillable = [
        'codigo',
        'nombre',
        'familia',
        'descripcion',
        'precio_base',
        'moneda_precio',
        'unidad_precio',
        'activo',
    ];

    protected function casts(): array
    {
        return ['activo' => 'boolean', 'precio_base' => 'decimal:2'];
    }

    public function servicios(): HasMany
    {
        return $this->hasMany(Servicio::class);
    }

    public function catalogoServicios(): HasMany
    {
        return $this->hasMany(CatalogoServicio::class)
            ->orderBy('orden')
            ->orderBy('nombre');
    }

    public function variablesPrecio(): BelongsToMany
    {
        return $this->belongsToMany(
            VariablePrecio::class,
            'tipo_servicio_variable',
        )->withPivot(['peso', 'requerida']);
    }
}
