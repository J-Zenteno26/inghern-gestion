<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Factura extends Model
{
    protected $fillable = [
        'orden_compra_id',
        'cliente_id',
        'creado_por',
        'folio',
        'fecha_emision',
        'fecha_pago_informada_cliente',
        'monto_neto',
        'iva_porcentaje',
        'iva',
        'total',
        'moneda',
        'estado',
        'observacion',
        'condicion_pago',
        'dias_pago',
    ];

    protected function casts(): array
    {
        return [
            'fecha_emision' => 'date',
            'fecha_pago_informada_cliente' => 'date',
            'monto_neto' => 'decimal:2',
            'iva_porcentaje' => 'decimal:2',
            'iva' => 'decimal:2',
            'total' => 'decimal:2',
            'dias_pago' => 'integer',
        ];
    }

    public function ordenCompra(): BelongsTo
    {
        return $this->belongsTo(OrdenCompra::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function pagos(): BelongsToMany
    {
        return $this->belongsToMany(Pago::class, 'pago_factura')
            ->withPivot('monto_asignado')
            ->withTimestamps();
    }

    public function operacionesFactoring(): HasMany
    {
        return $this->hasMany(OperacionFactoring::class);
    }

    public function totalPagado(): float
    {
        if (array_key_exists('total_pagado', $this->attributes)) {
            return (float) $this->attributes['total_pagado'];
        }

        return (float) $this->pagos()->sum('pago_factura.monto_asignado');
    }

    public function saldoPago(): float
    {
        return (float) $this->total - $this->totalPagado();
    }

    public function estadoPago(): string
    {
        if ($this->estado === 'anulada') {
            return 'anulada';
        }

        $totalPagado = $this->totalPagado();
        $total = (float) $this->total;

        return match (true) {
            $totalPagado <= 0 => 'pendiente',
            $totalPagado < $total - 0.005 => 'pago_parcial',
            abs($totalPagado - $total) <= 0.005 => 'pagada',
            default => 'sobrepagada',
        };
    }
}
