<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\EventoHistorial;
use App\Models\OrdenCompra;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrdenCompraTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepted_quote_can_register_a_purchase_order_with_an_independent_amount(): void
    {
        $user = User::factory()->create(['activo' => true]);
        $cliente = $this->crearCliente();
        $cotizacion = $this->crearCotizacion($cliente, $user, 'aceptada', 1190000);

        $response = $this->actingAs($user)->post(
            route('cotizaciones.orden-compra.store', $cotizacion),
            [
                'numero' => 'OC-2026-0042',
                'fecha' => '2026-10-06',
                'monto' => 875500,
                'observacion' => 'Monto real informado por el cliente.',
            ],
        );

        $ordenCompra = OrdenCompra::query()->sole();
        $evento = EventoHistorial::query()
            ->where('registrable_type', OrdenCompra::class)
            ->where('registrable_id', $ordenCompra->id)
            ->where('evento', 'oc_registrada')
            ->sole();

        $response->assertRedirect(route('cotizaciones.show', $cotizacion));
        $this->assertSame($cotizacion->id, $ordenCompra->cotizacion_id);
        $this->assertSame($cliente->id, $ordenCompra->cliente_id);
        $this->assertSame($user->id, $ordenCompra->creado_por);
        $this->assertSame('OC-2026-0042', $ordenCompra->numero);
        $this->assertSame('2026-10-06', $ordenCompra->fecha->toDateString());
        $this->assertSame(875500.0, (float) $ordenCompra->monto);
        $this->assertSame('registrada', $ordenCompra->estado);
        $this->assertSame('Monto real informado por el cliente.', $ordenCompra->observacion);
        $this->assertSame($cotizacion->id, $evento->cambios['cotizacion_id']);
        $this->assertSame($ordenCompra->id, $evento->cambios['orden_compra_id']);
        $this->assertSame($cliente->id, $evento->cambios['cliente_id']);
        $this->assertSame($user->id, $evento->cambios['usuario_id']);
        $this->actingAs($user)
            ->get(route('cotizaciones.show', $cotizacion))
            ->assertSeeText('OC-2026-0042')
            ->assertSeeText('CLP $875.500')
            ->assertSeeText('Monto real informado por el cliente.');
    }

    public function test_quote_that_is_not_accepted_cannot_register_a_purchase_order(): void
    {
        $user = User::factory()->create(['activo' => true]);
        $cliente = $this->crearCliente();
        $cotizacion = $this->crearCotizacion($cliente, $user, 'borrador');

        $response = $this->actingAs($user)->post(
            route('cotizaciones.orden-compra.store', $cotizacion),
            $this->datosOrdenCompra('OC-RECHAZADA'),
        );

        $response->assertSessionHasErrors([
            'cotizacion' => 'Solo una cotización aceptada puede registrar una orden de compra.',
        ]);
        $this->assertDatabaseCount('ordenes_compra', 0);
        $this->assertDatabaseCount('eventos_historial', 0);
    }

    public function test_quote_cannot_register_a_second_purchase_order(): void
    {
        $user = User::factory()->create(['activo' => true]);
        $cliente = $this->crearCliente();
        $cotizacion = $this->crearCotizacion($cliente, $user, 'aceptada');

        $this->actingAs($user)->post(
            route('cotizaciones.orden-compra.store', $cotizacion),
            $this->datosOrdenCompra('OC-PRIMERA'),
        );
        $response = $this->actingAs($user)->post(
            route('cotizaciones.orden-compra.store', $cotizacion),
            $this->datosOrdenCompra('OC-SEGUNDA'),
        );

        $response->assertSessionHasErrors([
            'cotizacion' => 'Esta cotización ya tiene una orden de compra registrada.',
        ]);
        $this->assertDatabaseCount('ordenes_compra', 1);
        $this->assertSame('OC-PRIMERA', OrdenCompra::query()->sole()->numero);
        $this->assertSame(1, EventoHistorial::query()
            ->where('evento', 'oc_registrada')
            ->count());
    }

    private function crearCliente(): Cliente
    {
        return Cliente::create([
            'razon_social' => 'Cliente OC',
            'identificador_tributario' => '55.555.555-5',
            'estado' => 'activo',
        ]);
    }

    private function crearCotizacion(
        Cliente $cliente,
        User $user,
        string $estado,
        float $total = 1000000,
    ): Cotizacion {
        $cotizacion = Cotizacion::create([
            'cliente_id' => $cliente->id,
            'creado_por' => $user->id,
            'codigo' => 'COT-OC-'.str()->random(6),
            'estado' => $estado,
        ]);
        $revision = $cotizacion->revisiones()->create([
            'creado_por' => $user->id,
            'revision' => 1,
            'titulo' => 'Cotización para OC',
            'moneda' => 'CLP',
            'total' => $total,
            'estado' => $estado,
        ]);
        $cotizacion->update(['revision_actual_id' => $revision->id]);

        return $cotizacion;
    }

    private function datosOrdenCompra(string $numero): array
    {
        return [
            'numero' => $numero,
            'fecha' => '2026-10-06',
            'monto' => 750000,
            'observacion' => null,
        ];
    }
}
