<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Pago extends Model
{
    protected $fillable = [
        'codigo',
        'fecha_pago',
        'monto_total',
        'medio_pago',
        'referencia',
        'observacion',
        'usuario_creador_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha_pago' => 'date',
            'monto_total' => 'decimal:2',
        ];
    }

    public function facturas(): BelongsToMany
    {
        return $this->belongsToMany(Factura::class, 'pago_factura')
            ->withPivot('monto_asignado')
            ->withTimestamps();
    }

    public function usuarioCreador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_creador_id');
    }
}
