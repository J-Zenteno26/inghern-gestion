<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\EventoHistorial;
use App\Models\Factura;
use App\Models\OrdenCompra;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class FacturaTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_order_can_register_an_invoice_with_calculated_tax_and_traceability(): void
    {
        [$user, $cotizacion, $ordenCompra] = $this->crearContexto();

        $response = $this->actingAs($user)->post(
            route('facturas.store'),
            [
                'orden_compra_id' => $ordenCompra->id,
                'folio' => 'F-1001',
                'fecha_emision' => '2026-10-06',
                'monto_neto' => 100000,
                'observacion' => 'Primera facturación parcial.',
                'condicion_pago' => 'credito',
                'dias_pago' => 45,
            ],
        );

        $factura = Factura::query()->sole();
        $evento = EventoHistorial::query()
            ->where('registrable_type', Factura::class)
            ->where('registrable_id', $factura->id)
            ->where('evento', 'factura_registrada')
            ->sole();

        $response->assertRedirect(route('facturas.show', $factura));
        $this->assertSame($ordenCompra->id, $factura->orden_compra_id);
        $this->assertSame($ordenCompra->cliente_id, $factura->cliente_id);
        $this->assertSame($user->id, $factura->creado_por);
        $this->assertSame('F-1001', $factura->folio);
        $this->assertSame('2026-10-06', $factura->fecha_emision->toDateString());
        $this->assertSame(100000.0, (float) $factura->monto_neto);
        $this->assertSame(19.0, (float) $factura->iva_porcentaje);
        $this->assertSame(19000.0, (float) $factura->iva);
        $this->assertSame(119000.0, (float) $factura->total);
        $this->assertSame('CLP', $factura->moneda);
        $this->assertSame('emitida', $factura->estado);
        $this->assertSame('Primera facturación parcial.', $factura->observacion);
        $this->assertSame('credito', $factura->condicion_pago);
        $this->assertSame(45, $factura->dias_pago);
        $this->assertSame($factura->id, $evento->cambios['factura_id']);
        $this->assertSame($ordenCompra->id, $evento->cambios['orden_compra_id']);
        $this->assertSame($cotizacion->id, $evento->cambios['cotizacion_id']);
        $this->assertSame($ordenCompra->cliente_id, $evento->cambios['cliente_id']);
        $this->assertSame($user->id, $evento->cambios['usuario_id']);
        $this->actingAs($user)
            ->get(route('facturas.show', $factura))
            ->assertOk()
            ->assertSeeText('F-1001')
            ->assertSeeText('CLP $119.000')
            ->assertSeeText('Crédito · 45 días')
            ->assertSeeText('Estado documental')
            ->assertSeeText('Estado de pago')
            ->assertSeeText('Total pagado')
            ->assertSeeText('CLP $0')
            ->assertSeeText('pendiente');
    }

    public function test_purchase_order_supports_multiple_partial_invoices_and_shows_total_balance(): void
    {
        [$user, $cotizacion, $ordenCompra] = $this->crearContexto();

        $this->registrarFactura($user, $ordenCompra, 'F-2001', 250000);
        $this->registrarFactura($user, $ordenCompra, 'F-2002', 350000);

        $this->assertDatabaseCount('facturas', 2);
        $this->actingAs($user)
            ->get(route('cotizaciones.show', $cotizacion))
            ->assertOk()
            ->assertSeeText('F-2002')
            ->assertSeeText('CLP $714.000')
            ->assertSeeText('CLP $286.000')
            ->assertSeeText('facturacion parcial')
            ->assertSeeText('Gestionar facturas');
        $this->actingAs($user)
            ->get(route('facturas.index', ['oc' => $ordenCompra->id]))
            ->assertOk()
            ->assertSeeText('F-2001')
            ->assertSeeText('F-2002')
            ->assertSeeText('Pagado')
            ->assertSeeText('Saldo pendiente')
            ->assertSeeText('Estado de pago')
            ->assertSeeText('pendiente')
            ->assertSeeText('Vista filtrada por OC');
    }

    public function test_purchase_order_uses_valid_invoice_totals_for_its_billing_state(): void
    {
        [$user, $cotizacion, $ordenCompra] = $this->crearContexto();
        $ordenCompra->update([
            'numero' => 'COT-LINDE-0015',
            'monto' => 1463700,
        ]);
        $this->registrarFactura($user, $ordenCompra, 'F-LINDE-VALIDA', 984000);
        $this->registrarFactura($user, $ordenCompra, 'F-LINDE-ANULADA', 246000);
        Factura::query()
            ->where('folio', 'F-LINDE-ANULADA')
            ->update(['estado' => 'anulada']);

        $ordenCompra->refresh()->load('facturas');

        $this->assertSame(1170960.0, $ordenCompra->totalFacturado());
        $this->assertSame(292740.0, $ordenCompra->saldoFacturacion());
        $this->assertSame(80.0, $ordenCompra->porcentajeFacturado());
        $this->assertSame('facturacion_parcial', $ordenCompra->estadoFacturacion());
        $this->actingAs($user)
            ->get(route('cotizaciones.show', $cotizacion))
            ->assertSeeText('CLP $1.463.700')
            ->assertSeeText('CLP $1.170.960')
            ->assertSeeText('CLP $292.740')
            ->assertSeeText('80%')
            ->assertSeeText('facturacion parcial');
    }

    public function test_purchase_order_reports_complete_and_overbilled_states_from_invoice_totals(): void
    {
        [$user, , $ordenCompra] = $this->crearContexto();
        $ordenCompra->update(['monto' => 1463700]);
        $this->registrarFactura($user, $ordenCompra, 'F-COMPLETA', 1230000);

        $ordenCompra->refresh()->load('facturas');

        $this->assertSame(1463700.0, $ordenCompra->totalFacturado());
        $this->assertSame(0.0, $ordenCompra->saldoFacturacion());
        $this->assertSame(100.0, $ordenCompra->porcentajeFacturado());
        $this->assertSame('facturacion_completa', $ordenCompra->estadoFacturacion());

        $this->registrarFactura($user, $ordenCompra, 'F-EXCEDENTE', 100000);
        $ordenCompra->refresh()->load('facturas');

        $this->assertSame('sobrefacturada', $ordenCompra->estadoFacturacion());
        $this->assertSame(-119000.0, $ordenCompra->saldoFacturacion());
    }

    public function test_invoice_total_over_purchase_order_amount_is_allowed_and_shows_warning(): void
    {
        [$user, $cotizacion, $ordenCompra] = $this->crearContexto();

        $this->registrarFactura($user, $ordenCompra, 'F-3001', 1100000);

        $this->assertDatabaseCount('facturas', 1);
        $this->actingAs($user)
            ->get(route('cotizaciones.show', $cotizacion))
            ->assertOk()
            ->assertSeeText('El total facturado sobrepasa el monto de la OC en CLP $309.000.')
            ->assertSeeText('CLP $-309.000');
    }

    public function test_invoice_folio_cannot_be_repeated_for_the_same_organization(): void
    {
        [$user, , $ordenCompra] = $this->crearContexto();

        $this->registrarFactura($user, $ordenCompra, 'F-DUPLICADA', 100000);
        $response = $this->registrarFactura($user, $ordenCompra, 'F-DUPLICADA', 200000);

        $response->assertSessionHasErrors('folio');
        $this->assertDatabaseCount('facturas', 1);
        $this->assertSame(1, EventoHistorial::query()
            ->where('evento', 'factura_registrada')
            ->count());
    }

    public function test_invoice_cannot_be_registered_on_an_unregistered_purchase_order(): void
    {
        [$user, , $ordenCompra] = $this->crearContexto();
        $ordenCompra->update(['estado' => 'pendiente']);

        $response = $this->registrarFactura($user, $ordenCompra, 'F-PENDIENTE', 100000);

        $response->assertSessionHasErrors([
            'factura' => 'Solo una orden de compra registrada puede recibir facturas.',
        ]);
        $this->assertDatabaseCount('facturas', 0);
        $this->assertDatabaseCount('eventos_historial', 0);
    }

    public function test_invoice_can_register_client_reported_payment_date_with_traceability(): void
    {
        [$user, , $ordenCompra] = $this->crearContexto();
        $this->registrarFactura($user, $ordenCompra, 'F-FECHA-1', 100000);
        $factura = Factura::query()->sole();

        $response = $this->actingAs($user)->patch(
            route('facturas.fecha-pago-informada.update', $factura),
            ['fecha_pago_informada_cliente' => '2026-11-20'],
        );

        $factura->refresh();
        $evento = EventoHistorial::query()
            ->where('registrable_type', Factura::class)
            ->where('registrable_id', $factura->id)
            ->where('evento', 'fecha_pago_cliente_registrada')
            ->sole();

        $response->assertRedirect(route('facturas.show', $factura));
        $this->assertSame('2026-11-20', $factura->fecha_pago_informada_cliente->toDateString());
        $this->assertSame($factura->id, $evento->cambios['factura_id']);
        $this->assertSame($user->id, $evento->cambios['usuario_id']);
        $this->assertNull($evento->cambios['fecha_anterior']);
        $this->assertSame('2026-11-20', $evento->cambios['fecha_pago_informada_cliente']);
        $this->assertNotNull($evento->created_at);
        $this->actingAs($user)
            ->get(route('facturas.show', $factura))
            ->assertOk()
            ->assertSeeText('Fecha de pago informada')
            ->assertSeeText('pendiente')
            ->assertDontSeeText('con fecha de pago')
            ->assertSeeText('Editar fecha');
    }

    public function test_invoice_can_edit_client_reported_payment_date(): void
    {
        [$user, , $ordenCompra] = $this->crearContexto();
        $this->registrarFactura($user, $ordenCompra, 'F-FECHA-2', 100000);
        $factura = Factura::query()->sole();
        $factura->update(['fecha_pago_informada_cliente' => '2026-11-20']);

        $response = $this->actingAs($user)->patch(
            route('facturas.fecha-pago-informada.update', $factura),
            ['fecha_pago_informada_cliente' => '2026-12-05'],
        );

        $factura->refresh();
        $evento = EventoHistorial::query()
            ->where('registrable_type', Factura::class)
            ->where('registrable_id', $factura->id)
            ->where('evento', 'fecha_pago_cliente_registrada')
            ->sole();

        $response->assertRedirect(route('facturas.show', $factura));
        $this->assertSame('2026-12-05', $factura->fecha_pago_informada_cliente->toDateString());
        $this->assertSame('2026-11-20', $evento->cambios['fecha_anterior']);
        $this->assertSame('2026-12-05', $evento->cambios['fecha_pago_informada_cliente']);
    }

    /**
     * @return array{User, Cotizacion, OrdenCompra}
     */
    private function crearContexto(): array
    {
        $user = User::factory()->create(['activo' => true]);
        $cliente = Cliente::create([
            'razon_social' => 'Cliente Facturación',
            'identificador_tributario' => '66.666.666-6',
            'estado' => 'activo',
        ]);
        $cotizacion = Cotizacion::create([
            'cliente_id' => $cliente->id,
            'creado_por' => $user->id,
            'codigo' => 'COT-FAC-'.str()->random(6),
            'estado' => 'aceptada',
        ]);
        $revision = $cotizacion->revisiones()->create([
            'creado_por' => $user->id,
            'revision' => 1,
            'titulo' => 'Cotización facturable',
            'moneda' => 'CLP',
            'iva_porcentaje' => 19,
            'subtotal' => 1000000,
            'iva' => 190000,
            'total' => 1190000,
            'estado' => 'aceptada',
        ]);
        $cotizacion->update(['revision_actual_id' => $revision->id]);
        $ordenCompra = $cotizacion->ordenCompra()->create([
            'cliente_id' => $cliente->id,
            'creado_por' => $user->id,
            'numero' => 'OC-FACTURABLE',
            'fecha' => '2026-10-06',
            'monto' => 1000000,
            'estado' => 'registrada',
        ]);

        return [$user, $cotizacion, $ordenCompra];
    }

    private function registrarFactura(
        User $user,
        OrdenCompra $ordenCompra,
        string $folio,
        float $montoNeto,
    ): TestResponse {
        return $this->actingAs($user)->post(
            route('facturas.store'),
            [
                'orden_compra_id' => $ordenCompra->id,
                'folio' => $folio,
                'fecha_emision' => '2026-10-06',
                'monto_neto' => $montoNeto,
                'observacion' => null,
                'condicion_pago' => 'contado',
            ],
        );
    }
}
