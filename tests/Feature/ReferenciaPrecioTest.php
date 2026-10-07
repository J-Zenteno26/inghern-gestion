<?php

namespace Tests\Feature;

use App\Enums\UnidadPrecio;
use App\Models\CatalogoServicio;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\TipoServicio;
use App\Models\User;
use App\Models\VariablePrecio;
use Database\Seeders\VariablesPrecioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReferenciaPrecioTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_can_access_grouped_price_references(): void
    {
        $user = User::factory()->create(['activo' => true]);
        [$tipo, $catalogo] = $this->crearCatalogo();
        $configurado = CatalogoServicio::create([
            'tipo_servicio_id' => $tipo->id,
            'codigo' => 'PID-CONFIGURADO',
            'nombre' => 'Servicio con referencia',
            'descripcion' => 'Servicio con precio base cero.',
            'precio_base' => 0,
            'moneda_precio' => 'CLP',
            'unidad_precio' => 'servicio',
            'orden' => 2,
            'activo' => true,
        ]);

        $this->actingAs($user)
            ->get(route('servicios.referencias-precio.index'))
            ->assertOk()
            ->assertSeeText('Referencias de precio')
            ->assertSeeText($tipo->nombre)
            ->assertSeeText($catalogo->nombre)
            ->assertSeeText($catalogo->descripcion)
            ->assertSeeText($configurado->nombre)
            ->assertSeeText('1 de 2 configurados')
            ->assertSee('data-catalog-id="'.$catalogo->id.'"', false)
            ->assertSee('data-configured="false"', false)
            ->assertSee('data-catalog-id="'.$configurado->id.'"', false)
            ->assertSee('data-configured="true"', false)
            ->assertSeeText(
                'Sin precio base: no podrá usar cálculo normalizado automático.',
            )
            ->assertSee(
                route('servicios.referencias-precio.update', $catalogo),
                false,
            )
            ->assertDontSee('name="nombre"', false)
            ->assertDontSee('name="tipo_servicio_id"', false);
    }

    public function test_validation_error_reopens_affected_type_and_catalog_editor(): void
    {
        $user = User::factory()->create(['activo' => true]);
        [, $catalogo] = $this->crearCatalogo();
        $index = route('servicios.referencias-precio.index');

        $this->actingAs($user)
            ->from($index)
            ->patch(
                route('servicios.referencias-precio.update', $catalogo),
                [
                    '_catalogo_servicio_id' => $catalogo->id,
                    'descripcion' => 'Valor que debe conservarse.',
                    'precio_base' => -10,
                    'moneda_precio' => 'CLP',
                    'unidad_precio' => 'servicio',
                ],
            )
            ->assertRedirect($index)
            ->assertSessionHasErrors('precio_base')
            ->assertSessionHasInput(
                '_catalogo_servicio_id',
                (string) $catalogo->id,
            );

        $this->get($index)
            ->assertOk()
            ->assertSee(
                'data-error-catalog="'.$catalogo->id.'"',
                false,
            )
            ->assertSee('class="price-reference-item is-editing"', false)
            ->assertSee('Valor que debe conservarse.')
            ->assertSee('aria-expanded="true"', false);
    }

    public function test_unpriced_service_remains_editable_and_groups_start_closed(): void
    {
        $user = User::factory()->create(['activo' => true]);
        [, $catalogo] = $this->crearCatalogo();

        $this->actingAs($user)
            ->get(route('servicios.referencias-precio.index'))
            ->assertOk()
            ->assertSee('data-price-group-toggle', false)
            ->assertSee('aria-expanded="false"', false)
            ->assertSee('data-price-edit', false)
            ->assertSee('data-price-editor', false)
            ->assertSee('name="precio_base"', false)
            ->assertSee(
                route('servicios.referencias-precio.update', $catalogo),
                false,
            );
    }

    public function test_price_reference_routes_keep_their_existing_contract(): void
    {
        $routes = app('router')->getRoutes();
        $index = $routes->getByName('servicios.referencias-precio.index');
        $update = $routes->getByName('servicios.referencias-precio.update');

        $this->assertSame('servicios/referencias-precio', $index->uri());
        $this->assertSame(['GET', 'HEAD'], $index->methods());
        $this->assertSame(
            'servicios/referencias-precio/{catalogoServicio}',
            $update->uri(),
        );
        $this->assertSame(['PATCH'], $update->methods());
    }

    public function test_valid_update_persists_description_and_price_reference(): void
    {
        $user = User::factory()->create(['activo' => true]);
        [, $catalogo] = $this->crearCatalogo();

        $this->actingAs($user)
            ->patch(
                route('servicios.referencias-precio.update', $catalogo),
                [
                    '_catalogo_servicio_id' => $catalogo->id,
                    'descripcion' => 'Referencia técnica revisada.',
                    'precio_base' => '1250000.50',
                    'moneda_precio' => 'UF',
                    'unidad_precio' => 'entregable',
                ],
            )
            ->assertRedirect(
                route('servicios.referencias-precio.index').
                    '#catalogo-'.$catalogo->id,
            )
            ->assertSessionHas('exito');

        $catalogo->refresh();

        $this->assertSame('Referencia técnica revisada.', $catalogo->descripcion);
        $this->assertSame('1250000.50', $catalogo->precio_base);
        $this->assertSame('UF', $catalogo->moneda_precio);
        $this->assertSame('entregable', $catalogo->unidad_precio);
    }

    public function test_every_catalog_price_unit_is_accepted(): void
    {
        $user = User::factory()->create(['activo' => true]);
        [, $catalogo] = $this->crearCatalogo();

        foreach (UnidadPrecio::cases() as $unidad) {
            $this->actingAs($user)
                ->patch(
                    route('servicios.referencias-precio.update', $catalogo),
                    [
                        '_catalogo_servicio_id' => $catalogo->id,
                        'descripcion' => $catalogo->descripcion,
                        'precio_base' => 100,
                        'moneda_precio' => 'CLP',
                        'unidad_precio' => $unidad->value,
                    ],
                )
                ->assertSessionDoesntHaveErrors();

            $this->assertSame(
                $unidad->value,
                $catalogo->fresh()->unidad_precio,
            );
        }
    }

    public function test_arbitrary_price_unit_is_rejected(): void
    {
        $user = User::factory()->create(['activo' => true]);
        [, $catalogo] = $this->crearCatalogo();

        $this->actingAs($user)
            ->patch(
                route('servicios.referencias-precio.update', $catalogo),
                [
                    '_catalogo_servicio_id' => $catalogo->id,
                    'descripcion' => $catalogo->descripcion,
                    'precio_base' => 100,
                    'moneda_precio' => 'CLP',
                    'unidad_precio' => 'turno inventado',
                ],
            )
            ->assertSessionHasErrors('unidad_precio');

        $this->assertNull($catalogo->fresh()->unidad_precio);
    }

    public function test_price_unit_is_required_when_base_price_is_present(): void
    {
        $user = User::factory()->create(['activo' => true]);
        [, $catalogo] = $this->crearCatalogo();

        $this->actingAs($user)
            ->patch(
                route('servicios.referencias-precio.update', $catalogo),
                [
                    '_catalogo_servicio_id' => $catalogo->id,
                    'descripcion' => $catalogo->descripcion,
                    'precio_base' => 100,
                    'moneda_precio' => 'CLP',
                    'unidad_precio' => null,
                ],
            )
            ->assertSessionHasErrors('unidad_precio');

        $this->assertNull($catalogo->fresh()->precio_base);
    }

    public function test_price_unit_may_be_null_without_base_price(): void
    {
        $user = User::factory()->create(['activo' => true]);
        [, $catalogo] = $this->crearCatalogo();

        $this->actingAs($user)
            ->patch(
                route('servicios.referencias-precio.update', $catalogo),
                [
                    '_catalogo_servicio_id' => $catalogo->id,
                    'descripcion' => 'Referencia sin precio.',
                    'precio_base' => null,
                    'moneda_precio' => 'CLP',
                    'unidad_precio' => null,
                ],
            )
            ->assertSessionDoesntHaveErrors();

        $catalogo->refresh();
        $this->assertNull($catalogo->precio_base);
        $this->assertNull($catalogo->unidad_precio);
    }

    public function test_existing_service_unit_is_selected_in_the_catalog(): void
    {
        $user = User::factory()->create(['activo' => true]);
        [, $catalogo] = $this->crearCatalogo();
        $catalogo->update([
            'precio_base' => 100,
            'unidad_precio' => UnidadPrecio::Servicio->value,
        ]);

        $response = $this->actingAs($user)->get(
            route('servicios.referencias-precio.index'),
        );

        $response->assertOk();
        $this->assertMatchesRegularExpression(
            '/<option\s+value="servicio"\s+selected\s*>/s',
            $response->getContent(),
        );
    }

    public function test_historical_unit_is_shown_but_requires_valid_replacement_on_save(): void
    {
        $user = User::factory()->create(['activo' => true]);
        [, $catalogo] = $this->crearCatalogo();
        $catalogo->update(['unidad_precio' => 'turno histórico']);

        $this->actingAs($user)
            ->get(route('servicios.referencias-precio.index'))
            ->assertOk()
            ->assertSeeText('Valor anterior: turno histórico');

        $this->actingAs($user)
            ->patch(
                route('servicios.referencias-precio.update', $catalogo),
                [
                    '_catalogo_servicio_id' => $catalogo->id,
                    'descripcion' => $catalogo->descripcion,
                    'precio_base' => null,
                    'moneda_precio' => 'CLP',
                    'unidad_precio' => null,
                ],
            )
            ->assertSessionHasErrors('unidad_precio');

        $this->assertSame(
            'turno histórico',
            $catalogo->fresh()->unidad_precio,
        );
    }

    public function test_negative_base_price_is_rejected(): void
    {
        $user = User::factory()->create(['activo' => true]);
        [, $catalogo] = $this->crearCatalogo();

        $this->actingAs($user)
            ->from(route('servicios.referencias-precio.index'))
            ->patch(
                route('servicios.referencias-precio.update', $catalogo),
                [
                    '_catalogo_servicio_id' => $catalogo->id,
                    'descripcion' => $catalogo->descripcion,
                    'precio_base' => -1,
                    'moneda_precio' => 'CLP',
                    'unidad_precio' => null,
                ],
            )
            ->assertRedirect(route('servicios.referencias-precio.index'))
            ->assertSessionHasErrors('precio_base');

        $this->assertNull($catalogo->fresh()->precio_base);
    }

    public function test_unsupported_currency_is_rejected(): void
    {
        $user = User::factory()->create(['activo' => true]);
        [, $catalogo] = $this->crearCatalogo();

        $this->actingAs($user)
            ->from(route('servicios.referencias-precio.index'))
            ->patch(
                route('servicios.referencias-precio.update', $catalogo),
                [
                    '_catalogo_servicio_id' => $catalogo->id,
                    'descripcion' => $catalogo->descripcion,
                    'precio_base' => 100,
                    'moneda_precio' => 'EUR',
                    'unidad_precio' => 'servicio',
                ],
            )
            ->assertRedirect(route('servicios.referencias-precio.index'))
            ->assertSessionHasErrors('moneda_precio');

        $this->assertSame('CLP', $catalogo->fresh()->moneda_precio);
    }

    public function test_price_variable_seeder_is_idempotent_and_preserves_factors(): void
    {
        $tipoActivo = TipoServicio::create([
            'codigo' => 'ACTIVO',
            'nombre' => 'Tipo activo',
            'activo' => true,
        ]);
        $tipoInactivo = TipoServicio::create([
            'codigo' => 'INACTIVO',
            'nombre' => 'Tipo inactivo',
            'activo' => false,
        ]);
        $variable = VariablePrecio::create([
            'codigo' => 'tamano_planta',
            'nombre' => 'Tamaño personalizado',
            'activo' => true,
        ]);
        $nivel = $variable->niveles()->create([
            'codigo' => 'pequena',
            'nombre' => 'Pequeña',
            'factor' => 2.3456,
            'orden' => 1,
        ]);

        $seeder = app(VariablesPrecioSeeder::class);
        $seeder->run();
        $seeder->run();

        $codigosEsperados = [
            'tamano_planta',
            'complejidad_tecnica',
            'calidad_informacion',
            'condicion_operacional',
        ];
        $relaciones = DB::table('tipo_servicio_variable')
            ->where('tipo_servicio_id', $tipoActivo->id)
            ->get();

        $this->assertCount(4, $relaciones);
        $this->assertEqualsCanonicalizing(
            $codigosEsperados,
            VariablePrecio::whereHas(
                'tiposServicio',
                fn ($query) => $query->whereKey($tipoActivo->id),
            )
                ->pluck('codigo')
                ->all(),
        );
        $this->assertTrue(
            $relaciones->every(
                fn ($relacion) => (float) $relacion->peso === 1.0 &&
                    (bool) $relacion->requerida,
            ),
        );
        $this->assertDatabaseMissing('tipo_servicio_variable', [
            'tipo_servicio_id' => $tipoInactivo->id,
        ]);
        $this->assertSame('2.3456', $nivel->fresh()->factor);
    }

    public function test_price_reference_management_does_not_modify_historical_quotes(): void
    {
        $user = User::factory()->create(['activo' => true]);
        [, $catalogo] = $this->crearCatalogo();
        $cliente = Cliente::create([
            'razon_social' => 'Cliente histórico',
            'identificador_tributario' => '76.000.000-1',
            'estado' => 'activo',
        ]);
        $cotizacion = Cotizacion::create([
            'cliente_id' => $cliente->id,
            'creado_por' => $user->id,
            'codigo' => 'COT-HIST-REFERENCIA',
            'estado' => 'aceptada',
        ]);
        $revision = $cotizacion->revisiones()->create([
            'creado_por' => $user->id,
            'revision' => 1,
            'titulo' => 'Cotización histórica intacta',
            'moneda' => 'CLP',
            'total' => 500000,
            'estado' => 'aceptada',
        ]);
        $cotizacion->update(['revision_actual_id' => $revision->id]);
        $cotizacionAntes = $cotizacion->fresh()->getAttributes();
        $revisionAntes = $revision->fresh()->getAttributes();

        $this->actingAs($user)->patch(
            route('servicios.referencias-precio.update', $catalogo),
            [
                '_catalogo_servicio_id' => $catalogo->id,
                'descripcion' => 'Nueva descripción de catálogo',
                'precio_base' => 250000,
                'moneda_precio' => 'CLP',
                'unidad_precio' => 'servicio',
            ],
        );
        app(VariablesPrecioSeeder::class)->run();

        $this->assertSame($cotizacionAntes, $cotizacion->fresh()->getAttributes());
        $this->assertSame($revisionAntes, $revision->fresh()->getAttributes());
        $this->assertDatabaseCount('cotizaciones', 1);
        $this->assertDatabaseCount('cotizacion_revisiones', 1);
    }

    /** @return array{TipoServicio, CatalogoServicio} */
    private function crearCatalogo(): array
    {
        $tipo = TipoServicio::create([
            'codigo' => 'PID-TEST',
            'nombre' => 'P&ID de prueba',
            'familia' => 'Ingeniería',
            'activo' => true,
        ]);
        $catalogo = CatalogoServicio::create([
            'tipo_servicio_id' => $tipo->id,
            'codigo' => 'PID-REF-TEST',
            'nombre' => 'Actualización documental',
            'descripcion' => 'Descripción vigente del servicio.',
            'orden' => 1,
            'activo' => true,
        ]);

        return [$tipo, $catalogo];
    }
}
