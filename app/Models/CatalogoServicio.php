<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogoServicio extends Model
{
    use HasFactory;

    protected $table = 'catalogo_servicios';

    protected $fillable = [
        'tipo_servicio_id',
        'codigo',
        'nombre',
        'descripcion',
        'precio_base',
        'moneda_precio',
        'unidad_precio',
        'orden',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'orden' => 'integer',
            'precio_base' => 'decimal:2',
        ];
    }

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TipoServicio::class, 'tipo_servicio_id');
    }

    public function servicios(): HasMany
    {
        return $this->hasMany(Servicio::class, 'catalogo_servicio_id');
    }
}
