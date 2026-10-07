<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperacionFactoring extends Model
{
    protected $table = 'operaciones_factoring';

    protected $fillable = [
        'factura_id',
        'codigo',
        'entidad_factoring',
        'fecha_solicitud',
        'monto_solicitado',
        'porcentaje_anticipo',
        'monto_adelantado',
        'costo_factoring',
        'fecha_abono',
        'fecha_liquidacion_estimada',
        'fecha_liquidacion_real',
        'estado',
        'observacion',
        'usuario_creador_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha_solicitud' => 'date',
            'monto_solicitado' => 'decimal:2',
            'porcentaje_anticipo' => 'decimal:2',
            'monto_adelantado' => 'decimal:2',
            'costo_factoring' => 'decimal:2',
            'fecha_abono' => 'date',
            'fecha_liquidacion_estimada' => 'date',
            'fecha_liquidacion_real' => 'date',
        ];
    }

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class);
    }

    public function usuarioCreador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_creador_id');
    }
}
