<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\EventoHistorial;
use App\Models\Factura;
use App\Models\OrdenCompra;
use App\Models\Pago;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnularPagoTest extends TestCase
{
    use RefreshDatabase;

    public function test_registered_payment_can_be_cancelled(): void
    {
        [$user, , $pago] = $this->crearPagoAsignado();

        $response = $this->actingAs($user)->patch(
            route('pagos.anular', $pago),
            [
                'motivo' => 'Pago informado dos veces.',
                'confirmacion' => '1',
            ],
        );

        $evento = EventoHistorial::query()
            ->where('registrable_type', Pago::class)
            ->where('registrable_id', $pago->id)
            ->where('evento', 'pago_anulado')
            ->sole();

        $response->assertRedirect(route('pagos.show', $pago));
        $this->assertSame('anulado', $pago->refresh()->estado);
        $this->assertSame($user->id, $evento->user_id);
        $this->assertSame('registrado', $evento->cambios['estado_anterior']);
        $this->assertSame('anulado', $evento->cambios['estado_nuevo']);
        $this->assertSame('Pago informado dos veces.', $evento->cambios['motivo']);
        $this->actingAs($user)
            ->get(route('pagos.show', $pago))
            ->assertSeeText('Motivo de anulación')
            ->assertSeeText('Pago informado dos veces.')
            ->assertDontSeeText('Confirmo que deseo anular este pago');
    }

    public function test_cancelled_payment_cannot_be_cancelled_again(): void
    {
        [$user, , $pago] = $this->crearPagoAsignado();
        $pago->update(['estado' => 'anulado']);

        $response = $this->actingAs($user)->patch(
            route('pagos.anular', $pago),
            [
                'motivo' => 'Segundo intento de anulación.',
                'confirmacion' => '1',
            ],
        );

        $response->assertSessionHasErrors([
            'pago' => 'El pago ya se encuentra anulado.',
        ]);
        $this->assertSame('anulado', $pago->refresh()->estado);
        $this->assertSame(0, EventoHistorial::query()
            ->where('evento', 'pago_anulado')
            ->count());
    }

    public function test_cancelled_payment_stops_counting_toward_invoice_total_paid(): void
    {
        [$user, $factura, $pago] = $this->crearPagoAsignado();
        $this->assertSame(60000.0, $factura->totalPagado());
        $this->assertSame(40000.0, $factura->saldoPago());
        $this->assertSame('pago_parcial', $factura->estadoPago());

        $this->actingAs($user)->patch(
            route('pagos.anular', $pago),
            [
                'motivo' => 'El banco rechazó el abono.',
                'confirmacion' => '1',
            ],
        );

        $factura->refresh();
        $this->assertSame(0.0, $factura->totalPagado());
        $this->assertSame(100000.0, $factura->saldoPago());
        $this->assertSame('pendiente', $factura->estadoPago());
    }

    public function test_payment_assignment_remains_after_cancellation(): void
    {
        [$user, $factura, $pago] = $this->crearPagoAsignado();

        $this->actingAs($user)->patch(
            route('pagos.anular', $pago),
            [
                'motivo' => 'Pago duplicado.',
                'confirmacion' => '1',
            ],
        );

        $this->assertDatabaseHas('pago_factura', [
            'pago_id' => $pago->id,
            'factura_id' => $factura->id,
            'monto_asignado' => 60000,
        ]);
        $this->assertSame(60000.0, (float) $pago->facturas()->sole()->pivot->monto_asignado);
    }

    /**
     * @return array{User, Factura, Pago}
     */
    private function crearPagoAsignado(): array
    {
        $user = User::factory()->create(['activo' => true]);
        $cliente = Cliente::create([
            'razon_social' => 'Cliente Anulación Pago',
            'identificador_tributario' => '55.555.555-5',
            'estado' => 'activo',
        ]);
        $cotizacion = Cotizacion::create([
            'cliente_id' => $cliente->id,
            'creado_por' => $user->id,
            'codigo' => 'COT-ANULAR-PAGO',
            'estado' => 'aceptada',
        ]);
        $ordenCompra = OrdenCompra::create([
            'cotizacion_id' => $cotizacion->id,
            'cliente_id' => $cliente->id,
            'creado_por' => $user->id,
            'numero' => 'OC-ANULAR-PAGO',
            'fecha' => '2026-10-15',
            'monto' => 100000,
            'estado' => 'registrada',
        ]);
        $factura = Factura::create([
            'orden_compra_id' => $ordenCompra->id,
            'cliente_id' => $cliente->id,
            'creado_por' => $user->id,
            'folio' => 'F-ANULAR-PAGO',
            'fecha_emision' => '2026-10-15',
            'monto_neto' => 100000,
            'iva_porcentaje' => 0,
            'iva' => 0,
            'total' => 100000,
            'moneda' => 'CLP',
            'estado' => 'emitida',
            'condicion_pago' => 'contado',
        ]);
        $pago = Pago::create([
            'cliente_id' => $cliente->id,
            'codigo' => 'PAG-ANULAR',
            'fecha_pago' => '2026-10-15',
            'monto_total' => 60000,
            'moneda' => 'CLP',
            'medio_pago' => 'transferencia',
            'estado' => 'registrado',
            'usuario_creador_id' => $user->id,
        ]);
        $pago->facturas()->attach($factura->id, ['monto_asignado' => 60000]);

        return [$user, $factura, $pago];
    }
}
