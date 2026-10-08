<?php

namespace Tests\Feature;

use App\Models\CatalogoServicio;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\Documento;
use App\Models\Planta;
use App\Models\Servicio;
use App\Models\TipoServicio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlantaContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_cotizacion_belongs_to_planta(): void
    {
        [$user, $cliente, $planta] = $this->contexto('Principal');
        $cotizacion = $this->cotizacion($user, $cliente, $planta, 'COT-PLANTA-001');

        $this->assertTrue($cotizacion->planta->is($planta));
        $this->assertTrue($planta->cotizaciones()->sole()->is($cotizacion));
    }

    public function test_new_quote_rejects_a_plant_from_another_organization(): void
    {
        [$user, $cliente] = $this->contexto('Principal');
        [, , $plantaAjena] = $this->contexto('Ajena');
        $catalogo = $this->catalogo();

        $this->actingAs($user)
            ->post(route('cotizaciones.store'), $this->datosCotizacion($cliente, $plantaAjena, $catalogo))
            ->assertSessionHasErrors('planta_id');

        $this->assertDatabaseCount('cotizaciones', 0);
    }

    public function test_assigning_a_plant_from_another_organization_is_rejected(): void
    {
        [$user, $cliente, $planta] = $this->contexto('Principal');
        [, , $plantaAjena] = $this->contexto('Ajena');
        $cotizacion = $this->cotizacion($user, $cliente, $planta, 'COT-PLANTA-002');

        $this->actingAs($user)
            ->patch(route('cotizaciones.planta.update', $cotizacion), ['planta_id' => $plantaAjena->id])
            ->assertSessionHasErrors('planta_id');

        $this->assertSame($planta->id, $cotizacion->fresh()->planta_id);
    }

    public function test_quotes_can_be_filtered_by_plant(): void
    {
        [$user, $cliente, $planta] = $this->contexto('Visible');
        $otraPlanta = $cliente->plantas()->create(['nombre' => 'Oculta', 'estado' => 'activa']);
        $this->cotizacion($user, $cliente, $planta, 'COT-VISIBLE');
        $this->cotizacion($user, $cliente, $otraPlanta, 'COT-OCULTA');

        $this->actingAs($user)
            ->get(route('cotizaciones.index', ['planta' => $planta->id]))
            ->assertOk()
            ->assertSeeText('COT-VISIBLE')
            ->assertDontSeeText('COT-OCULTA');
    }

    public function test_services_can_be_filtered_by_plant(): void
    {
        [$user, $cliente, $planta] = $this->contexto('Visible');
        $otraPlanta = $cliente->plantas()->create(['nombre' => 'Oculta', 'estado' => 'activa']);
        $visible = $this->servicio($cliente, 'Servicio visible');
        $oculto = $this->servicio($cliente, 'Servicio oculto');
        $visible->plantas()->attach($planta);
        $oculto->plantas()->attach($otraPlanta);

        $this->actingAs($user)
            ->get(route('servicios.index', ['planta' => $planta->id]))
            ->assertOk()
            ->assertSeeText('Servicio visible')
            ->assertDontSeeText('Servicio oculto');
    }

    public function test_library_filters_all_plant_contexts_without_duplicates(): void
    {
        [$user, $cliente, $planta] = $this->contexto('Documental');
        $cotizacion = $this->cotizacion($user, $cliente, $planta, 'COT-DOC-PLANTA');
        $revision = $cotizacion->revisiones()->create([
            'creado_por' => $user->id,
            'revision' => 1,
            'titulo' => 'Revisión documental',
            'moneda' => 'CLP',
            'estado' => 'borrador',
        ]);
        $revisionServicio = $revision->servicios()->create([
            'titulo' => 'Servicio cotizado',
            'orden' => 1,
        ]);
        $servicio = $this->servicio($cliente, 'Servicio documental');
        $servicio->plantas()->attach($planta);

        $documento = $this->documento($user, $cliente, 'Documento visible una vez');
        $planta->documentos()->attach($documento);
        $cotizacion->documentos()->attach($documento);
        $revisionServicio->documentos()->attach($documento);
        $servicio->documentos()->attach($documento);

        $response = $this->actingAs($user)->get(route('biblioteca.index', ['planta' => $planta->id]));

        $response->assertOk()->assertSeeText('Planta · '.$planta->nombre);
        $this->assertSame(1, substr_count($response->getContent(), 'Documento visible una vez'));
    }

    public function test_plant_show_contains_only_its_context(): void
    {
        [$user, $cliente, $planta] = $this->contexto('Visible');
        $otraPlanta = $cliente->plantas()->create(['nombre' => 'Planta ajena al contexto', 'estado' => 'activa']);
        $visible = $this->servicio($cliente, 'Servicio del contexto');
        $oculto = $this->servicio($cliente, 'Servicio fuera del contexto');
        $visible->plantas()->attach($planta);
        $oculto->plantas()->attach($otraPlanta);
        $this->cotizacion($user, $cliente, $planta, 'COT-CONTEXTO');
        $this->cotizacion($user, $cliente, $otraPlanta, 'COT-FUERA');

        $this->actingAs($user)
            ->get(route('plantas.show', $planta))
            ->assertOk()
            ->assertSeeText('Servicio del contexto')
            ->assertSeeText('COT-CONTEXTO')
            ->assertDontSeeText('Servicio fuera del contexto')
            ->assertDontSeeText('COT-FUERA');
    }

    /**
     * @return array{User, Cliente, Planta}
     */
    private function contexto(string $sufijo): array
    {
        $user = User::factory()->create(['activo' => true]);
        $cliente = Cliente::create([
            'razon_social' => 'Organización '.$sufijo,
            'identificador_tributario' => str()->random(10),
            'estado' => 'activo',
        ]);
        $planta = $cliente->plantas()->create([
            'nombre' => 'Planta '.$sufijo,
            'estado' => 'activa',
        ]);

        return [$user, $cliente, $planta];
    }

    private function cotizacion(
        User $user,
        Cliente $cliente,
        Planta $planta,
        string $codigo,
    ): Cotizacion {
        return Cotizacion::create([
            'cliente_id' => $cliente->id,
            'planta_id' => $planta->id,
            'creado_por' => $user->id,
            'codigo' => $codigo,
            'estado' => 'borrador',
        ]);
    }

    private function servicio(Cliente $cliente, string $nombre): Servicio
    {
        return Servicio::create([
            'cliente_id' => $cliente->id,
            'codigo' => 'SER-'.str()->upper(str()->random(8)),
            'nombre' => $nombre,
            'estado' => 'en_curso',
        ]);
    }

    private function catalogo(): CatalogoServicio
    {
        $tipo = TipoServicio::create([
            'codigo' => 'tipo-'.str()->random(6),
            'nombre' => 'Tipo de prueba',
            'activo' => true,
        ]);

        return CatalogoServicio::create([
            'tipo_servicio_id' => $tipo->id,
            'codigo' => 'catalogo-'.str()->random(6),
            'nombre' => 'Servicio cotizable',
            'activo' => true,
        ]);
    }

    private function datosCotizacion(
        Cliente $cliente,
        Planta $planta,
        CatalogoServicio $catalogo,
    ): array {
        return [
            'cliente_id' => $cliente->id,
            'planta_id' => $planta->id,
            'titulo' => 'Cotización de prueba',
            'moneda' => 'CLP',
            'iva_porcentaje' => 19,
            'servicios' => [[
                'tipo_servicio_id' => $catalogo->tipo_servicio_id,
                'catalogo_servicio_id' => $catalogo->id,
                'partidas' => [[
                    'descripcion' => 'Partida principal',
                    'clase' => 'servicio',
                    'cantidad' => 1,
                    'unidad' => 'servicio',
                    'precio_unitario' => 1000,
                    'metodo_precio' => 'a_criterio',
                    'monto_sugerido' => 1000,
                    'justificacion_ajuste' => 'Estimación de prueba.',
                ]],
            ]],
        ];
    }

    private function documento(User $user, Cliente $cliente, string $nombre): Documento
    {
        return Documento::create([
            'cliente_id' => $cliente->id,
            'nombre' => $nombre,
            'nombre_original' => 'documento.pdf',
            'tipo_documento' => 'informe',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'tamano' => 100,
            'ruta_storage' => 'clientes/'.$cliente->id.'/documento.pdf',
            'usuario_creador_id' => $user->id,
        ]);
    }
}
