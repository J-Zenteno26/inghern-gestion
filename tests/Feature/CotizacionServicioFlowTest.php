<?php

namespace Tests\Feature;

use App\Domain\Cotizaciones\CrearCotizacion;
use App\Models\CatalogoServicio;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\Servicio;
use App\Models\TipoServicio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CotizacionServicioFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_from_service_requires_the_service_to_belong_to_the_client(): void
    {
        $user = User::factory()->create(['activo' => true]);
        $cliente = $this->crearCliente('Cliente Uno', '11.111.111-1');
        $otroCliente = $this->crearCliente('Cliente Dos', '22.222.222-2');
        $servicio = $this->crearServicio($otroCliente, 'Servicio ajeno');

        $this->actingAs($user)
            ->get(
                route('cotizaciones.create', [
                    'cliente' => $cliente,
                    'servicio' => $servicio,
                ]),
            )
            ->assertRedirect()
            ->assertSessionHasErrors('servicio');
    }

    public function test_create_from_service_preselects_and_protects_client_and_service(): void
    {
        $user = User::factory()->create(['activo' => true]);
        $cliente = $this->crearCliente('Cliente Uno', '11.111.111-1');
        $tipo = TipoServicio::create([
            'codigo' => 'levantamiento',
            'nombre' => 'Levantamiento',
            'activo' => true,
        ]);
        $catalogo = CatalogoServicio::create([
            'tipo_servicio_id' => $tipo->id,
            'codigo' => 'levantamiento-operativo',
            'nombre' => 'Levantamiento normalizado',
            'activo' => true,
        ]);
        $servicio = $this->crearServicio(
            $cliente,
            'Levantamiento operativo',
            'en_curso',
        );
        $servicio->update([
            'tipo_servicio_id' => $tipo->id,
            'catalogo_servicio_id' => $catalogo->id,
        ]);

        $response = $this->actingAs($user)
            ->get(
                route('cotizaciones.create', [
                    'cliente' => $cliente,
                    'servicio' => $servicio,
                ]),
            );

        $response
            ->assertOk()
            ->assertSee('name="cliente_id"', false)
            ->assertSee('value="'.$cliente->id.'"', false)
            ->assertSee('name="servicios[0][servicio_id]"', false)
            ->assertSee('value="'.$servicio->id.'"', false)
            ->assertSee('data-operational-service', false);

        $this->assertSame(
            'Levantamiento operativo',
            $response->viewData('serviciosOperativosPorCliente')[$cliente->id][$catalogo->id]['nombre'],
        );
    }

    public function test_new_quote_offers_the_full_active_catalog_grouped_by_type(): void
    {
        $user = User::factory()->create(['activo' => true]);
        $tipoUno = TipoServicio::create([
            'codigo' => 'tipo-uno',
            'nombre' => 'Tipo Uno',
            'activo' => true,
        ]);
        $tipoDos = TipoServicio::create([
            'codigo' => 'tipo-dos',
            'nombre' => 'Tipo Dos',
            'activo' => true,
        ]);
        $catalogoUno = CatalogoServicio::create([
            'tipo_servicio_id' => $tipoUno->id,
            'codigo' => 'catalogo-uno',
            'nombre' => 'Servicio catálogo uno',
            'activo' => true,
        ]);
        $catalogoDos = CatalogoServicio::create([
            'tipo_servicio_id' => $tipoDos->id,
            'codigo' => 'catalogo-dos',
            'nombre' => 'Servicio catálogo dos',
            'activo' => true,
        ]);
        CatalogoServicio::create([
            'tipo_servicio_id' => $tipoUno->id,
            'codigo' => 'catalogo-inactivo',
            'nombre' => 'Servicio catálogo inactivo',
            'activo' => false,
        ]);

        $response = $this->actingAs($user)->get(route('cotizaciones.create'));

        $response
            ->assertOk()
            ->assertSeeText('Tipo Uno')
            ->assertSeeText('Tipo Dos')
            ->assertSeeText('Servicio catálogo uno')
            ->assertSeeText('Servicio catálogo dos')
            ->assertSee('data-catalog-checkbox', false)
            ->assertSee('value="'.$catalogoUno->id.'"', false)
            ->assertSee('value="'.$catalogoDos->id.'"', false)
            ->assertSee('data-type-id="'.$tipoUno->id.'"', false)
            ->assertSee('data-type-id="'.$tipoDos->id.'"', false)
            ->assertSeeText('Costos asociados a la propuesta')
            ->assertSee('data-add-general-cost="hospedaje"', false)
            ->assertSee('data-preview-services', false)
            ->assertDontSee('data-add-preset-line="hospedaje"', false)
            ->assertDontSeeText('Servicio catálogo inactivo');

        $this->assertNotSame($catalogoUno->tipo_servicio_id, $catalogoDos->tipo_servicio_id);
    }

    public function test_store_persists_multiple_catalog_services_and_links_only_the_existing_service(): void
    {
        $user = User::factory()->create(['activo' => true]);
        $cliente = $this->crearCliente('Cliente Uno', '11.111.111-1');
        $tipo = TipoServicio::create([
            'codigo' => 'tipo-multiple',
            'nombre' => 'Tipo múltiple',
            'activo' => true,
        ]);
        $catalogoExistente = CatalogoServicio::create([
            'tipo_servicio_id' => $tipo->id,
            'codigo' => 'catalogo-existente',
            'nombre' => 'Servicio existente',
            'activo' => true,
        ]);
        $catalogoNuevo = CatalogoServicio::create([
            'tipo_servicio_id' => $tipo->id,
            'codigo' => 'catalogo-nuevo',
            'nombre' => 'Servicio nuevo',
            'activo' => true,
        ]);
        $servicioExistente = $this->crearServicio($cliente, 'Servicio operativo');
        $servicioExistente->update([
            'tipo_servicio_id' => $tipo->id,
            'catalogo_servicio_id' => $catalogoExistente->id,
        ]);

        $response = $this->actingAs($user)->post(route('cotizaciones.store'), [
            'cliente_id' => $cliente->id,
            'titulo' => 'Cotización multiservicio',
            'moneda' => 'CLP',
            'iva_porcentaje' => 19,
            'servicios' => [
                $this->datosServicioCatalogo($catalogoExistente, $servicioExistente, 1000),
                $this->datosServicioCatalogo($catalogoNuevo, null, 2000),
            ],
        ]);

        $cotizacion = Cotizacion::with('revisionActual.servicios')->firstOrFail();
        $serviciosCotizados = $cotizacion->revisionActual->servicios->sortBy('orden')->values();

        $response->assertRedirect(route('cotizaciones.show', $cotizacion));
        $this->assertCount(2, $serviciosCotizados);
        $this->assertSame($servicioExistente->id, $serviciosCotizados[0]->servicio_id);
        $this->assertSame($catalogoExistente->id, $serviciosCotizados[0]->catalogo_servicio_id);
        $this->assertNull($serviciosCotizados[1]->servicio_id);
        $this->assertSame($catalogoNuevo->id, $serviciosCotizados[1]->catalogo_servicio_id);
    }

    public function test_store_persists_global_costs_without_changing_each_service_subtotal(): void
    {
        $user = User::factory()->create(['activo' => true]);
        $cliente = $this->crearCliente('Cliente Global', '33.333.333-3');
        $tipo = TipoServicio::create([
            'codigo' => 'tipo-global',
            'nombre' => 'Tipo global',
            'activo' => true,
        ]);
        $catalogoUno = CatalogoServicio::create([
            'tipo_servicio_id' => $tipo->id,
            'codigo' => 'global-uno',
            'nombre' => 'Servicio global uno',
            'activo' => true,
        ]);
        $catalogoDos = CatalogoServicio::create([
            'tipo_servicio_id' => $tipo->id,
            'codigo' => 'global-dos',
            'nombre' => 'Servicio global dos',
            'activo' => true,
        ]);

        $response = $this->actingAs($user)->post(route('cotizaciones.store'), [
            'cliente_id' => $cliente->id,
            'titulo' => 'Cotización con costos globales',
            'moneda' => 'CLP',
            'iva_porcentaje' => 19,
            'servicios' => [
                $this->datosServicioCatalogo($catalogoUno, null, 1000),
                $this->datosServicioCatalogo($catalogoDos, null, 2000),
            ],
            'costos_generales' => [
                $this->datosCostoGeneral('Hospedaje', 'traslado', 500),
                $this->datosCostoGeneral('Alimentación', 'costo_adicional', 250),
            ],
        ]);

        $cotizacion = Cotizacion::with([
            'revisionActual.partidas',
            'revisionActual.servicios.partidas',
        ])->firstOrFail();
        $revision = $cotizacion->revisionActual;
        $servicios = $revision->servicios->sortBy('orden')->values();
        $costosGenerales = $revision->partidas->whereNull('revision_servicio_id')->values();

        $response->assertRedirect(route('cotizaciones.show', $cotizacion));
        $this->assertCount(2, $servicios);
        $this->assertCount(2, $costosGenerales);
        $this->assertSame(1000.0, (float) $servicios[0]->partidas->sum('monto_neto'));
        $this->assertSame(2000.0, (float) $servicios[1]->partidas->sum('monto_neto'));
        $this->assertSame(750.0, (float) $costosGenerales->sum('monto_neto'));
        $this->assertSame(3750.0, (float) $revision->subtotal);
        $this->assertSame(712.5, (float) $revision->iva);
        $this->assertSame(4462.5, (float) $revision->total);
        $this->assertTrue($costosGenerales->every(
            fn ($partida) => $partida->revision_servicio_id === null,
        ));
        $this->assertEqualsCanonicalizing(
            ['traslado', 'costo_adicional'],
            $costosGenerales->pluck('clase')->all(),
        );
    }

    public function test_store_rejects_duplicate_catalog_services(): void
    {
        $user = User::factory()->create(['activo' => true]);
        $cliente = $this->crearCliente('Cliente Uno', '11.111.111-1');
        $tipo = TipoServicio::create([
            'codigo' => 'tipo-unico',
            'nombre' => 'Tipo único',
            'activo' => true,
        ]);
        $catalogo = CatalogoServicio::create([
            'tipo_servicio_id' => $tipo->id,
            'codigo' => 'catalogo-unico',
            'nombre' => 'Servicio sin duplicados',
            'activo' => true,
        ]);

        $this->actingAs($user)
            ->post(route('cotizaciones.store'), [
                'cliente_id' => $cliente->id,
                'titulo' => 'Cotización duplicada',
                'moneda' => 'CLP',
                'iva_porcentaje' => 19,
                'servicios' => [
                    $this->datosServicioCatalogo($catalogo, null, 1000),
                    $this->datosServicioCatalogo($catalogo, null, 2000),
                ],
            ])
            ->assertSessionHasErrors('servicios.1.catalogo_servicio_id');

        $this->assertDatabaseCount('cotizaciones', 0);
    }

    public function test_store_links_service_and_preserves_its_current_snapshot(): void
    {
        $user = User::factory()->create(['activo' => true]);
        $cliente = $this->crearCliente('Cliente Uno', '11.111.111-1');
        $servicio = $this->crearServicio(
            $cliente,
            'Nombre operativo actual',
            'prospecto',
            'Descripción operativa actual',
        );

        $response = $this->actingAs($user)->post(
            route('cotizaciones.store'),
            $this->datosCotizacion($cliente, $servicio),
        );

        $cotizacion = Cotizacion::with('revisionActual.servicios')->firstOrFail();
        $revisionServicio = $cotizacion->revisionActual->servicios->sole();

        $response->assertRedirect(route('cotizaciones.show', $cotizacion));
        $this->assertSame($servicio->id, $revisionServicio->servicio_id);
        $this->assertSame('Nombre operativo actual', $revisionServicio->titulo);
        $this->assertSame(
            'Descripción operativa actual',
            $revisionServicio->descripcion,
        );
    }

    public function test_store_rejects_a_service_from_another_client(): void
    {
        $user = User::factory()->create(['activo' => true]);
        $cliente = $this->crearCliente('Cliente Uno', '11.111.111-1');
        $otroCliente = $this->crearCliente('Cliente Dos', '22.222.222-2');
        $servicio = $this->crearServicio($otroCliente, 'Servicio ajeno');

        $this->actingAs($user)
            ->post(
                route('cotizaciones.store'),
                $this->datosCotizacion($cliente, $servicio),
            )
            ->assertSessionHasErrors('servicio_id');

        $this->assertDatabaseCount('cotizaciones', 0);
    }

    public function test_domain_action_rechecks_service_ownership_inside_transaction(): void
    {
        $user = User::factory()->create(['activo' => true]);
        $cliente = $this->crearCliente('Cliente Uno', '11.111.111-1');
        $otroCliente = $this->crearCliente('Cliente Dos', '22.222.222-2');
        $servicio = $this->crearServicio($otroCliente, 'Servicio ajeno');

        $this->expectException(ValidationException::class);

        app(CrearCotizacion::class)->ejecutar(
            $this->datosCotizacion($cliente, $servicio),
            $user->id,
        );
    }

    public function test_historical_quote_without_service_id_still_opens(): void
    {
        $user = User::factory()->create(['activo' => true]);
        $cliente = $this->crearCliente('Cliente Uno', '11.111.111-1');
        $cotizacion = $this->crearCotizacion($cliente, $user, 'COT-HIST-001');
        $revision = $cotizacion->revisiones()->create([
            'creado_por' => $user->id,
            'revision' => 1,
            'titulo' => 'Propuesta histórica',
            'moneda' => 'CLP',
            'estado' => 'borrador',
        ]);
        $revision->servicios()->create([
            'servicio_id' => null,
            'titulo' => 'Snapshot histórico',
            'descripcion' => 'Sin relación operativa',
            'orden' => 1,
        ]);
        $cotizacion->update(['revision_actual_id' => $revision->id]);

        $this->actingAs($user)
            ->get(route('cotizaciones.show', $cotizacion))
            ->assertOk()
            ->assertSeeText('Pendiente de creación')
            ->assertSeeText('Se creará al aceptar la cotización');
    }

    public function test_service_detail_lists_each_quote_once_using_current_revision(): void
    {
        $user = User::factory()->create(['activo' => true]);
        $cliente = $this->crearCliente('Cliente Uno', '11.111.111-1');
        $servicio = $this->crearServicio($cliente, 'Servicio operativo');
        $cotizacion = $this->crearCotizacion($cliente, $user, 'COT-2026-0099');

        foreach ([1 => 'Propuesta anterior', 2 => 'Propuesta vigente'] as $numero => $titulo) {
            $revision = $cotizacion->revisiones()->create([
                'creado_por' => $user->id,
                'revision' => $numero,
                'titulo' => $titulo,
                'moneda' => 'CLP',
                'total' => $numero * 1000,
                'estado' => 'borrador',
            ]);
            $revision->servicios()->create([
                'servicio_id' => $servicio->id,
                'titulo' => $servicio->nombre,
                'descripcion' => $servicio->descripcion,
                'orden' => 1,
            ]);
            $cotizacion->update(['revision_actual_id' => $revision->id]);
        }

        $response = $this->actingAs($user)->get(
            route('servicios.show', $servicio),
        );

        $response
            ->assertOk()
            ->assertSeeText('Propuesta vigente')
            ->assertDontSeeText('Propuesta anterior');
        $this->assertSame(1, substr_count($response->getContent(), 'COT-2026-0099'));
    }

    private function crearCliente(string $nombre, string $rut): Cliente
    {
        return Cliente::create([
            'razon_social' => $nombre,
            'identificador_tributario' => $rut,
            'estado' => 'activo',
        ]);
    }

    private function crearServicio(
        Cliente $cliente,
        string $nombre,
        string $estado = 'prospecto',
        ?string $descripcion = null,
    ): Servicio {
        return Servicio::create([
            'cliente_id' => $cliente->id,
            'codigo' => 'SER-'.$cliente->id.'-'.str()->random(6),
            'nombre' => $nombre,
            'descripcion' => $descripcion,
            'estado' => $estado,
        ]);
    }

    private function crearCotizacion(
        Cliente $cliente,
        User $user,
        string $codigo,
    ): Cotizacion {
        return Cotizacion::create([
            'cliente_id' => $cliente->id,
            'creado_por' => $user->id,
            'codigo' => $codigo,
            'estado' => 'borrador',
        ]);
    }

    private function datosCotizacion(
        Cliente $cliente,
        Servicio $servicio,
    ): array {
        return [
            'cliente_id' => $cliente->id,
            'servicio_id' => $servicio->id,
            'titulo' => 'Título comercial independiente',
            'moneda' => 'CLP',
            'iva_porcentaje' => 19,
            'partidas' => [
                [
                    'descripcion' => 'Partida principal',
                    'clase' => 'servicio',
                    'cantidad' => 1,
                    'unidad' => 'servicio',
                    'precio_unitario' => 1000,
                    'metodo_precio' => 'a_criterio',
                    'monto_sugerido' => 1000,
                    'justificacion_ajuste' => 'Estimación manual inicial.',
                ],
            ],
        ];
    }

    private function datosServicioCatalogo(
        CatalogoServicio $catalogo,
        ?Servicio $servicio,
        float $precio,
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
                'precio_unitario' => $precio,
                'metodo_precio' => 'a_criterio',
                'monto_sugerido' => $precio,
                'justificacion_ajuste' => 'Estimación manual inicial.',
            ]],
        ];
    }

    private function datosCostoGeneral(string $descripcion, string $clase, float $precio): array
    {
        return [
            'descripcion' => $descripcion,
            'clase' => $clase,
            'cantidad' => 1,
            'unidad' => 'servicio',
            'precio_unitario' => $precio,
            'metodo_precio' => 'a_criterio',
            'monto_sugerido' => $precio,
            'justificacion_ajuste' => 'Costo global definido para la propuesta.',
        ];
    }
}
