<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class OrdenCompra extends Model
{
    protected $table = 'ordenes_compra';

    protected $fillable = [
        'cotizacion_id',
        'cliente_id',
        'creado_por',
        'numero',
        'fecha',
        'monto',
        'estado',
        'observacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'monto' => 'decimal:2',
        ];
    }

    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function facturas(): HasMany
    {
        return $this->hasMany(Factura::class)
            ->latest('fecha_emision')
            ->latest('id');
    }

    public function documentos(): MorphToMany
    {
        return $this->morphToMany(Documento::class, 'vinculable', 'documento_vinculos')
            ->withTimestamps();
    }

    public function totalFacturado(): float
    {
        if (array_key_exists('total_facturado', $this->attributes)) {
            return (float) $this->attributes['total_facturado'];
        }

        if ($this->relationLoaded('facturas')) {
            return (float) $this->facturas
                ->where('estado', '!=', 'anulada')
                ->sum('total');
        }

        return (float) $this->facturas()
            ->where('estado', '!=', 'anulada')
            ->sum('total');
    }

    public function saldoFacturacion(): float
    {
        return (float) $this->monto - $this->totalFacturado();
    }

    public function porcentajeFacturado(): float
    {
        $monto = (float) $this->monto;

        if ($monto <= 0) {
            return 0;
        }

        return round(($this->totalFacturado() / $monto) * 100, 2);
    }

    public function estadoFacturacion(): string
    {
        $totalFacturado = $this->totalFacturado();
        $monto = (float) $this->monto;

        return match (true) {
            $totalFacturado <= 0 => 'sin_facturar',
            $totalFacturado < $monto - 0.005 => 'facturacion_parcial',
            abs($totalFacturado - $monto) <= 0.005 => 'facturacion_completa',
            default => 'sobrefacturada',
        };
    }
}
