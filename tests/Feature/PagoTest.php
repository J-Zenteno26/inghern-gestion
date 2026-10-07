<?php

namespace Tests\Feature;

use App\Models\Factura;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PagoTest extends TestCase
{
    #[DataProvider('estadosDePago')]
    public function test_deriva_el_estado_de_pago_desde_el_total_asignado(
        float $total,
        float $totalPagado,
        string $estadoFactura,
        string $estadoEsperado,
    ): void {
        $factura = new Factura;
        $factura->forceFill([
            'total' => $total,
            'estado' => $estadoFactura,
            'total_pagado' => $totalPagado,
        ]);

        $this->assertSame($totalPagado, $factura->totalPagado());
        $this->assertSame($total - $totalPagado, $factura->saldoPago());
        $this->assertSame($estadoEsperado, $factura->estadoPago());
    }

    /**
     * @return array<string, array{float, float, string, string}>
     */
    public static function estadosDePago(): array
    {
        return [
            'pendiente' => [100_000.0, 0.0, 'emitida', 'pendiente'],
            'pago parcial' => [100_000.0, 35_000.0, 'emitida', 'pago_parcial'],
            'pagada' => [100_000.0, 100_000.0, 'emitida', 'pagada'],
            'sobrepagada' => [100_000.0, 110_000.0, 'emitida', 'sobrepagada'],
            'anulada prevalece' => [100_000.0, 100_000.0, 'anulada', 'anulada'],
        ];
    }
}
