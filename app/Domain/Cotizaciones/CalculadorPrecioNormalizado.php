<?php

namespace App\Domain\Cotizaciones;

use Illuminate\Support\Collection;

class CalculadorPrecioNormalizado
{
    public function calcular(float $precioBase, Collection $selecciones): array
    {
        $factor = $selecciones->reduce(function (float $acumulado, $seleccion) {
            $factorNivel = (float) data_get($seleccion, 'factor');
            $peso = (float) data_get($seleccion, 'peso', 1);

            return $acumulado * (1 + ($factorNivel - 1) * $peso);
        }, 1.0);

        $sugerido = round($precioBase * $factor, 2);

        return [
            'precio_base' => $precioBase,
            'factor_compuesto' => round($factor, 6),
            'monto_sugerido' => $sugerido,
            'rango_minimo' => round($sugerido * 0.9, 2),
            'rango_maximo' => round($sugerido * 1.1, 2),
        ];
    }
}
