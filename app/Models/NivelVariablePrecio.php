<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NivelVariablePrecio extends Model
{
    protected $table = 'niveles_variable_precio';

    protected $fillable = [
        'variable_precio_id',
        'codigo',
        'nombre',
        'criterio',
        'factor',
        'orden',
    ];

    protected function casts(): array
    {
        return ['factor' => 'decimal:4'];
    }

    public function variable(): BelongsTo
    {
        return $this->belongsTo(VariablePrecio::class, 'variable_precio_id');
    }
}
