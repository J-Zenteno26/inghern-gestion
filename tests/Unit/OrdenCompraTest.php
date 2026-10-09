<?php

namespace Tests\Unit;

use App\Models\Factura;
use App\Models\OrdenCompra;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class OrdenCompraTest extends TestCase
{
    public function test_uses_valid_invoice_totals_for_partial_billing(): void
    {
        $ordenCompra = $this->ordenCompraConFacturas(1463700, [
            ['total' => 1170960, 'estado' => 'emitida'],
            ['total' => 292740, 'estado' => 'anulada'],
        ]);

        $this->assertSame(1170960.0, $ordenCompra->totalFacturado());
        $this->assertSame(292740.0, $ordenCompra->saldoFacturacion());
        $this->assertSame(80.0, $ordenCompra->porcentajeFacturado());
        $this->assertSame('facturacion_parcial', $ordenCompra->estadoFacturacion());
    }

    public function test_reports_no_billing_when_there_are_no_valid_invoices(): void
    {
        $ordenCompra = $this->ordenCompraConFacturas(1000000, [
            ['total' => 1000000, 'estado' => 'anulada'],
        ]);

        $this->assertSame(0.0, $ordenCompra->totalFacturado());
        $this->assertSame(1000000.0, $ordenCompra->saldoFacturacion());
        $this->assertSame(0.0, $ordenCompra->porcentajeFacturado());
        $this->assertSame('sin_facturar', $ordenCompra->estadoFacturacion());
    }

    public function test_reports_complete_billing_when_valid_totals_match_purchase_order(): void
    {
        $ordenCompra = $this->ordenCompraConFacturas(1463700, [
            ['total' => 1463700, 'estado' => 'emitida'],
        ]);

        $this->assertSame(0.0, $ordenCompra->saldoFacturacion());
        $this->assertSame(100.0, $ordenCompra->porcentajeFacturado());
        $this->assertSame('facturacion_completa', $ordenCompra->estadoFacturacion());
    }

    public function test_reports_overbilling_when_valid_totals_exceed_purchase_order(): void
    {
        $ordenCompra = $this->ordenCompraConFacturas(1000000, [
            ['total' => 1100000, 'estado' => 'emitida'],
        ]);

        $this->assertSame(-100000.0, $ordenCompra->saldoFacturacion());
        $this->assertSame(110.0, $ordenCompra->porcentajeFacturado());
        $this->assertSame('sobrefacturada', $ordenCompra->estadoFacturacion());
    }

    /**
     * @param  array<int, array{total: int|float, estado: string}>  $facturas
     */
    private function ordenCompraConFacturas(int|float $monto, array $facturas): OrdenCompra
    {
        $ordenCompra = new OrdenCompra(['monto' => $monto]);
        $ordenCompra->setRelation(
            'facturas',
            new Collection(array_map(
                fn (array $factura): Factura => new Factura($factura),
                $facturas,
            )),
        );

        return $ordenCompra;
    }
}
