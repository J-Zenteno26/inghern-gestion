<?php

namespace App\Domain\Shared;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class GeneraCodigo
{
    /** @param class-string<Model> $modelo */
    public function siguiente(string $modelo, string $prefijo): string
    {
        $base = Str::upper($prefijo).'-'.now()->format('Y').'-';
        $consulta = $modelo::query();

        if (in_array(SoftDeletes::class, class_uses_recursive($modelo), true)) {
            $consulta->withTrashed();
        }

        $ultimo = $consulta
            ->where('codigo', 'like', $base.'%')
            ->orderByDesc('codigo')
            ->lockForUpdate()
            ->value('codigo');
        $numero = $ultimo ? ((int) Str::afterLast($ultimo, '-')) + 1 : 1;

        return $base.str_pad((string) $numero, 4, '0', STR_PAD_LEFT);
    }
}
