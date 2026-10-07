<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CotizacionRevision extends Model
{
    use HasFactory;

    protected $table = 'cotizacion_revisiones';

    protected $fillable = [
        'cotizacion_id',
        'contacto_id',
        'creado_por',
        'revision',
        'titulo',
        'fecha_emision',
        'moneda',
        'iva_porcentaje',
        'subtotal',
        'iva',
        'total',
        'estado',
        'cliente_snapshot',
        'contacto_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'fecha_emision' => 'date',
            'cliente_snapshot' => 'array',
            'contacto_snapshot' => 'array',
            'iva_porcentaje' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'iva' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class);
    }

    public function contacto(): BelongsTo
    {
        return $this->belongsTo(Contacto::class);
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function servicios(): HasMany
    {
        return $this->hasMany(RevisionServicio::class)->orderBy('orden');
    }

    public function partidas(): HasMany
    {
        return $this->hasMany(PartidaCotizacion::class)->orderBy('orden');
    }

    public function bloques(): HasMany
    {
        return $this->hasMany(CotizacionBloque::class)->orderBy('orden');
    }

    public function plazos(): HasMany
    {
        return $this->hasMany(CotizacionPlazo::class)->orderBy('orden');
    }
}
