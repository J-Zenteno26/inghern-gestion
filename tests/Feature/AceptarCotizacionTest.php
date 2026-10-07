<?php

namespace Tests\Feature;

use App\Domain\Cotizaciones\CrearCotizacion;
use App\Models\CatalogoServicio;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\EventoHistorial;
use App\Models\Servicio;
use App\Models\TipoServicio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AceptarCotizacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_acceptance_links_an_existing_service_without_changing_its_operational_data(): void
    {
        $user = User::factory()->create(['activo' => true]);
        $cliente = $this->crearCliente();
        $catalogo = $this->crearCatalogo('existente');
        $servicio = $this->crearServicio($cliente, $catalogo, $user, 'SER-EXISTENTE');
        $cotizacion = $this->crearCotizacion($cliente, $user, [
            $this->datosServicio($catalogo, $servicio),
        ]);

        $response = $this->actingAs($user)->patch(
            route('cotizaciones.aceptar', $cotizacion),
        );

        $response->assertRedirect(route('cotizaciones.show', $cotizacion));
        $this->assertSame(1, Servicio::query()->count());
        $this->assertSame('SER-EXISTENTE', $servicio->fresh()->codigo);
        $this->assertSame('Servicio operativo vigente', $servicio->fresh()->nombre);
        $this->assertSame('Datos operativos conservados', $servicio->fresh()->descripcion);
        $this->assertSame(
            $servicio->id,
            $cotizacion->fresh()->revisionActual->servicios->sole()->servicio_id,
        );
    }

    public function test_acceptance_creates_a_prospect_service_when_no_operational_service_exists(): void
    {
        $user = User::factory()->create(['activo' => true]);
        $cliente = $this->crearCliente();
        $catalogo = $this->crearCatalogo('nuevo');
        $cotizacion = $this->crearCotizacion($cliente, $user, [
            $this->datosServicio($catalogo),
        ]);

        $this->actingAs($user)->patch(
            route('cotizaciones.aceptar', $cotizacion),
        )->assertRedirect(route('cotizaciones.show', $cotizacion));

        $servicio = Servicio::query()->sole();
        $servicioCotizado = $cotizacion->fresh()->revisionActual->servicios->sole();
        $evento = EventoHistorial::query()
            ->where('registrable_type', Servicio::class)
            ->where('registrable_id', $servicio->id)
            ->where('evento', 'creado_desde_cotizacion')
            ->sole();

        $this->assertSame($cliente->id, $servicio->cliente_id);
        $this->assertSame($catalogo->tipo_servicio_id, $servicio->tipo_servicio_id);
        $this->assertSame($catalogo->id, $servicio->catalogo_servicio_id);
        $this->assertSame($user->id, $servicio->creado_por);
        $this->assertSame('prospecto', $servicio->estado);
        $this->assertSame($servicio->id, $servicioCotizado->servicio_id);
        $this->assertSame($cotizacion->id, $evento->cambios['cotizacion_id']);
        $this->assertSame($servicioCotizado->id, $evento->cambios['revision_servicio_id']);
    }

    public function test_acceptance_resolves_existing_and_new_services_in_the_same_quote(): void
    {
        $user = User::factory()->create(['activo' => true]);
        $cliente = $this->crearCliente();
        $catalogoExistente = $this->crearCatalogo('mixto-existente');
        $catalogoNuevo = $this->crearCatalogo('mixto-nuevo');
        $servicioExistente = $this->crearServicio(
            $cliente,
            $catalogoExistente,
            $user,
            'SER-MIXTO',
        );
        $cotizacion = $this->crearCotizacion($cliente, $user, [
            $this->datosServicio($catalogoExistente, $servicioExistente),
            $this->datosServicio($catalogoNuevo),
        ]);

        $this->actingAs($user)->patch(
            route('cotizaciones.aceptar', $cotizacion),
        )->assertRedirect(route('cotizaciones.show', $cotizacion));

        $cotizacion->refresh()->load('revisionActual.servicios');
        $serviciosCotizados = $cotizacion->revisionActual->servicios
            ->keyBy('catalogo_servicio_id');
        $servicioNuevo = Servicio::query()
            ->where('catalogo_servicio_id', $catalogoNuevo->id)
            ->sole();

        $this->assertSame('aceptada', $cotizacion->estado);
        $this->assertSame('aceptada', $cotizacion->revisionActual->estado);
        $this->assertSame(2, Servicio::query()->count());
        $this->assertSame(
            $servicioExistente->id,
            $serviciosCotizados[$catalogoExistente->id]->servicio_id,
        );
        $this->assertSame(
            $servicioNuevo->id,
            $serviciosCotizados[$catalogoNuevo->id]->servicio_id,
        );
    }

    public function test_acceptance_detects_a_matching_service_and_remains_idempotent(): void
    {
        $user = User::factory()->create(['activo' => true]);
        $cliente = $this->crearCliente();
        $catalogo = $this->crearCatalogo('sin-duplicar');
        $cotizacion = $this->crearCotizacion($cliente, $user, [
            $this->datosServicio($catalogo),
        ]);
        $servicio = $this->crearServicio($cliente, $catalogo, $user, 'SER-SIN-DUPLICAR');

        $this->actingAs($user)->patch(route('cotizaciones.aceptar', $cotizacion));
        $this->actingAs($user)->patch(route('cotizaciones.aceptar', $cotizacion));

        $this->assertSame(1, Servicio::query()
            ->where('cliente_id', $cliente->id)
            ->where('catalogo_servicio_id', $catalogo->id)
            ->count());
        $this->assertSame(
            $servicio->id,
            $cotizacion->fresh()->revisionActual->servicios->sole()->servicio_id,
        );
        $this->assertSame(1, EventoHistorial::query()
            ->where('registrable_type', Cotizacion::class)
            ->where('registrable_id', $cotizacion->id)
            ->where('evento', 'cotizacion_aceptada')
            ->count());
    }

    private function crearCliente(): Cliente
    {
        return Cliente::create([
            'razon_social' => 'Cliente aceptación',
            'identificador_tributario' => '44.444.444-4',
            'estado' => 'activo',
        ]);
    }

    private function crearCatalogo(string $sufijo): CatalogoServicio
    {
        $tipo = TipoServicio::create([
            'codigo' => "tipo-{$sufijo}",
            'nombre' => "Tipo {$sufijo}",
            'activo' => true,
        ]);

        return CatalogoServicio::create([
            'tipo_servicio_id' => $tipo->id,
            'codigo' => "catalogo-{$sufijo}",
            'nombre' => "Servicio {$sufijo}",
            'descripcion' => "Descripción {$sufijo}",
            'activo' => true,
        ]);
    }

    private function crearServicio(
        Cliente $cliente,
        CatalogoServicio $catalogo,
        User $user,
        string $codigo,
    ): Servicio {
        return Servicio::create([
            'cliente_id' => $cliente->id,
            'tipo_servicio_id' => $catalogo->tipo_servicio_id,
            'catalogo_servicio_id' => $catalogo->id,
            'creado_por' => $user->id,
            'codigo' => $codigo,
            'nombre' => 'Servicio operativo vigente',
            'descripcion' => 'Datos operativos conservados',
            'estado' => 'planificado',
        ]);
    }

    private function crearCotizacion(
        Cliente $cliente,
        User $user,
        array $servicios,
    ): Cotizacion {
        return app(CrearCotizacion::class)->ejecutar([
            'cliente_id' => $cliente->id,
            'titulo' => 'Cotización para aceptación',
            'moneda' => 'CLP',
            'iva_porcentaje' => 19,
            'servicios' => $servicios,
        ], $user->id);
    }

    private function datosServicio(
        CatalogoServicio $catalogo,
        ?Servicio $servicio = null,
    ): array {
        return [
            'tipo_servicio_id' => $catalogo->tipo_servicio_id,
            'catalogo_servicio_id' => $catalogo->id,
            'servicio_id' => $servicio?->id,
            'partidas' => [[
                'descripcion' => $catalogo->nombre,
                'clase' => 'servicio',
                'cantidad' => 1,
                'unidad' => 'servicio',
                'precio_unitario' => 1000,
                'metodo_precio' => 'a_criterio',
                'monto_sugerido' => 1000,
                'justificacion_ajuste' => 'Estimación manual para aceptación.',
            ]],
        ];
    }
}
