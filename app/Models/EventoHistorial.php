<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EventoHistorial extends Model
{
    protected $table = 'eventos_historial';

    protected $fillable = [
        'registrable_type',
        'registrable_id',
        'user_id',
        'evento',
        'descripcion',
        'cambios',
    ];

    protected function casts(): array
    {
        return ['cambios' => 'array'];
    }

    public function registrable(): MorphTo
    {
        return $this->morphTo();
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
