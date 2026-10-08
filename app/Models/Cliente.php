<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cliente extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'razon_social',
        'nombre_fantasia',
        'identificador_tributario',
        'email_facturacion',
        'telefono',
        'direccion',
        'comuna',
        'ciudad',
        'region',
        'estado',
    ];

    public function contactos(): HasMany
    {
        return $this->hasMany(Contacto::class);
    }

    public function plantas(): HasMany
    {
        return $this->hasMany(Planta::class);
    }

    public function servicios(): HasMany
    {
        return $this->hasMany(Servicio::class);
    }

    public function cotizaciones(): HasMany
    {
        return $this->hasMany(Cotizacion::class);
    }

    public function documentos(): MorphToMany
    {
        return $this->morphToMany(
            Documento::class,
            'vinculable',
            'documento_vinculos',
        )->withTimestamps();
    }

    public function documentosPropios(): HasMany
    {
        return $this->hasMany(Documento::class);
    }

    public function getNombreDisplayAttribute(): string
    {
        return $this->nombre_fantasia ?: $this->razon_social;
    }
}
