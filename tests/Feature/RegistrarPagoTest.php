<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\Factura;
use App\Models\OrdenCompra;
use App\Models\Pago;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RegistrarPagoTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('montosPermitidos')]
    public function test_allows_payments_that_do_not_exceed_current_balance(
        float $monto,
        float $saldoEsperado,
        string $estadoEsperado,
    ): void {
        [$user, $ordenCompra] = $this->crearContexto();
        $factura = $this->crearFactura($ordenCompra, $user, 'F-PAGO-PERMITIDO', 100000);

        $response = $this->registrarPago($user, $monto, [
            ['factura_id' => $factura->id, 'monto_asignado' => $monto],
        ]);

        $pago = Pago::query()->sole();
        $factura->refresh();
        $response->assertRedirect(route('pagos.show', $pago));
        $this->assertSame($factura->cliente_id, $pago->cliente_id);
        $this->assertSame($factura->moneda, $pago->moneda);
        $this->assertSame('registrado', $pago->estado);
        $this->assertSame($monto, (float) $pago->monto_total);
        $this->assertSame($monto, (float) $pago->facturas()->sole()->pivot->monto_asignado);
        $this->assertSame($saldoEsperado, $factura->saldoPago());
        $this->assertSame($estadoEsperado, $factura->estadoPago());
    }

    /**
     * @return array<string, array{float, float, string}>
     */
    public static function montosPermitidos(): array
    {
        return [
            'pago exacto' => [100000.0, 0.0, 'pagada'],
            'pago parcial' => [40000.0, 60000.0, 'pago_parcial'],
        ];
    }

    public function test_rejects_payment_above_invoice_balance_without_creating_partial_records(): void
    {
        [$user, $ordenCompra] = $this->crearContexto();
        $factura = $this->crearFactura($ordenCompra, $user, 'F-SOBREPAGO', 100000);

        $response = $this->registrarPago($user, 100001, [
            ['factura_id' => $factura->id, 'monto_asignado' => 100001],
        ]);

        $response->assertSessionHasErrors([
            'asignaciones.0.monto_asignado' => 'El monto asignado a la factura F-SOBREPAGO supera su saldo pendiente de CLP $100.000.',
        ]);
        $this->assertDatabaseCount('pagos', 0);
        $this->assertDatabaseCount('pago_factura', 0);
        $this->assertDatabaseCount('eventos_historial', 0);
    }

    public function test_recalculates_locked_balance_before_registering_payment(): void
    {
        [$user, $ordenCompra] = $this->crearContexto();
        $factura = $this->crearFactura($ordenCompra, $user, 'F-SALDO-ACTUAL', 100000);
        $this->asignarPagoExistente($factura, $user, 60000, 'PAG-EXISTENTE');

        $response = $this->registrarPago($user, 50000, [
            ['factura_id' => $factura->id, 'monto_asignado' => 50000],
        ]);

        $response->assertSessionHasErrors([
            'asignaciones.0.monto_asignado' => 'El monto asignado a la factura F-SALDO-ACTUAL supera su saldo pendiente de CLP $40.000.',
        ]);
        $this->assertDatabaseCount('pagos', 1);
        $this->assertDatabaseCount('pago_factura', 1);
    }

    public function test_cancelled_and_paid_invoices_are_not_selectable_for_new_payments(): void
    {
        [$user, $ordenCompra] = $this->crearContexto();
        $anulada = $this->crearFactura($ordenCompra, $user, 'F-ANULADA', 100000, 'CLP', 'anulada');
        $pagada = $this->crearFactura($ordenCompra, $user, 'F-PAGADA', 100000);
        $pendiente = $this->crearFactura($ordenCompra, $user, 'F-PENDIENTE', 100000);
        $this->asignarPagoExistente($pagada, $user, 100000, 'PAG-COMPLETO');

        $response = $this->actingAs($user)->get(route('pagos.create'));

        $response
            ->assertSeeText($pendiente->folio)
            ->assertDontSeeText($anulada->folio)
            ->assertDontSeeText($pagada->folio);
    }

    public function test_rejects_payment_when_allocations_do_not_match_total(): void
    {
        [$user, $ordenCompra] = $this->crearContexto();
        $factura = $this->crearFactura($ordenCompra, $user, 'F-DISTRIBUCION', 100000);

        $response = $this->registrarPago($user, 50000, [
            ['factura_id' => $factura->id, 'monto_asignado' => 40000],
        ]);

        $response->assertSessionHasErrors([
            'asignaciones' => 'La suma de los montos asignados debe coincidir con el monto total del pago.',
        ]);
        $this->assertDatabaseCount('pagos', 0);
        $this->assertDatabaseCount('pago_factura', 0);
    }

    public function test_rejects_allocations_between_invoices_with_different_currencies(): void
    {
        [$user, $ordenCompra] = $this->crearContexto();
        $facturaClp = $this->crearFactura($ordenCompra, $user, 'F-CLP', 100000);
        $facturaUsd = $this->crearFactura($ordenCompra, $user, 'F-USD', 100000, 'USD');

        $response = $this->registrarPago($user, 100000, [
            ['factura_id' => $facturaClp->id, 'monto_asignado' => 50000],
            ['factura_id' => $facturaUsd->id, 'monto_asignado' => 50000],
        ]);

        $response->assertSessionHasErrors([
            'asignaciones' => 'Las facturas seleccionadas deben utilizar la misma moneda.',
        ]);
        $this->assertDatabaseCount('pagos', 0);
        $this->assertDatabaseCount('pago_factura', 0);
    }

    public function test_allows_allocations_between_invoices_from_same_client_and_currency(): void
    {
        [$user, $ordenCompra] = $this->crearContexto();
        $primeraFactura = $this->crearFactura($ordenCompra, $user, 'F-MISMO-CLIENTE-1', 100000);
        $segundaFactura = $this->crearFactura($ordenCompra, $user, 'F-MISMO-CLIENTE-2', 100000);

        $response = $this->registrarPago($user, 100000, [
            ['factura_id' => $primeraFactura->id, 'monto_asignado' => 50000],
            ['factura_id' => $segundaFactura->id, 'monto_asignado' => 50000],
        ]);

        $pago = Pago::query()->sole();
        $response->assertRedirect(route('pagos.show', $pago));
        $this->assertSame($ordenCompra->cliente_id, $pago->cliente_id);
        $this->assertSame('CLP', $pago->moneda);
        $this->assertSame('registrado', $pago->estado);
        $this->assertCount(2, $pago->facturas);
    }

    public function test_rejects_allocations_between_invoices_from_different_clients(): void
    {
        [$user, $primeraOrdenCompra] = $this->crearContexto();
        [, $segundaOrdenCompra] = $this->crearContexto('2');
        $primeraFactura = $this->crearFactura($primeraOrdenCompra, $user, 'F-CLIENTE-1', 100000);
        $segundaFactura = $this->crearFactura($segundaOrdenCompra, $user, 'F-CLIENTE-2', 100000);

        $response = $this->registrarPago($user, 100000, [
            ['factura_id' => $primeraFactura->id, 'monto_asignado' => 50000],
            ['factura_id' => $segundaFactura->id, 'monto_asignado' => 50000],
        ]);

        $response->assertSessionHasErrors([
            'asignaciones' => 'Las facturas seleccionadas deben pertenecer a la misma organización.',
        ]);
        $this->assertDatabaseCount('pagos', 0);
        $this->assertDatabaseCount('pago_factura', 0);
    }

    public function test_waiting_date_metric_and_payment_actions_only_include_collectible_invoices(): void
    {
        [$user, $ordenCompra] = $this->crearContexto();
        $pendiente = $this->crearFactura($ordenCompra, $user, 'F-COBRABLE', 100000);
        $parcial = $this->crearFactura($ordenCompra, $user, 'F-PARCIAL', 100000);
        $pagada = $this->crearFactura($ordenCompra, $user, 'F-SIN-SALDO', 100000);
        $anulada = $this->crearFactura($ordenCompra, $user, 'F-NULA', 100000, 'CLP', 'anulada');
        $this->asignarPagoExistente($parcial, $user, 40000, 'PAG-PARCIAL');
        $this->asignarPagoExistente($pagada, $user, 100000, 'PAG-TOTAL');

        $response = $this->actingAs($user)->get(route('pagos.index'));

        $response
            ->assertViewHas('metricas', fn (array $metricas): bool => $metricas['esperando_fecha'] === 2)
            ->assertSee(route('pagos.create', ['factura' => $pendiente->id]), false)
            ->assertSee(route('pagos.create', ['factura' => $parcial->id]), false)
            ->assertDontSee(route('pagos.create', ['factura' => $pagada->id]), false)
            ->assertDontSee(route('pagos.create', ['factura' => $anulada->id]), false);
    }

    /**
     * @return array{User, OrdenCompra}
     */
    private function crearContexto(string $sufijo = ''): array
    {
        $user = User::factory()->create(['activo' => true]);
        $cliente = Cliente::create([
            'razon_social' => 'Cliente Pagos'.$sufijo,
            'identificador_tributario' => $sufijo === '' ? '77.777.777-7' : '77.777.772-2',
            'estado' => 'activo',
        ]);
        $cotizacion = Cotizacion::create([
            'cliente_id' => $cliente->id,
            'creado_por' => $user->id,
            'codigo' => 'COT-PAG-'.str()->random(6),
            'estado' => 'aceptada',
        ]);
        $ordenCompra = $cotizacion->ordenCompra()->create([
            'cliente_id' => $cliente->id,
            'creado_por' => $user->id,
            'numero' => 'OC-PAGOS'.$sufijo,
            'fecha' => '2026-10-09',
            'monto' => 1000000,
            'estado' => 'registrada',
        ]);

        return [$user, $ordenCompra];
    }

    private function crearFactura(
        OrdenCompra $ordenCompra,
        User $user,
        string $folio,
        float $total,
        string $moneda = 'CLP',
        string $estado = 'emitida',
    ): Factura {
        return $ordenCompra->facturas()->create([
            'cliente_id' => $ordenCompra->cliente_id,
            'creado_por' => $user->id,
            'folio' => $folio,
            'fecha_emision' => '2026-10-09',
            'monto_neto' => $total,
            'iva_porcentaje' => 0,
            'iva' => 0,
            'total' => $total,
            'moneda' => $moneda,
            'estado' => $estado,
            'condicion_pago' => 'contado',
        ]);
    }

    private function asignarPagoExistente(
        Factura $factura,
        User $user,
        float $monto,
        string $codigo,
    ): Pago {
        $pago = Pago::create([
            'cliente_id' => $factura->cliente_id,
            'codigo' => $codigo,
            'fecha_pago' => '2026-10-09',
            'monto_total' => $monto,
            'moneda' => $factura->moneda,
            'medio_pago' => 'transferencia',
            'estado' => 'registrado',
            'usuario_creador_id' => $user->id,
        ]);
        $pago->facturas()->attach($factura->id, ['monto_asignado' => $monto]);

        return $pago;
    }

    /**
     * @param  array<int, array{factura_id: int, monto_asignado: int|float}>  $asignaciones
     */
    private function registrarPago(User $user, float $montoTotal, array $asignaciones): TestResponse
    {
        return $this->actingAs($user)->post(route('pagos.store'), [
            'fecha_pago' => '2026-10-09',
            'monto_total' => $montoTotal,
            'medio_pago' => 'transferencia',
            'referencia' => 'REF-PAGO',
            'observacion' => null,
            'asignaciones' => $asignaciones,
        ]);
    }
}
