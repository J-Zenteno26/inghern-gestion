<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DocumentoVinculo extends Model
{
    public const TIPOS = [
        'cliente' => Cliente::class,
        'cotizacion' => Cotizacion::class,
        'revision_servicio' => RevisionServicio::class,
        'orden_compra' => OrdenCompra::class,
        'factura' => Factura::class,
        'servicio' => Servicio::class,
        'planta' => Planta::class,
    ];

    public const ETIQUETAS = [
        'cliente' => 'Organización',
        'cotizacion' => 'Cotización',
        'revision_servicio' => 'Servicio cotizado',
        'orden_compra' => 'Orden de compra',
        'factura' => 'Factura',
        'servicio' => 'Servicio',
        'planta' => 'Planta',
    ];

    protected $fillable = [
        'documento_id',
        'vinculable_type',
        'vinculable_id',
    ];

    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class);
    }

    public function vinculable(): MorphTo
    {
        return $this->morphTo();
    }
}
