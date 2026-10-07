<?php

namespace Tests\Feature;

use App\Domain\Cotizaciones\CrearCotizacion;
use App\Enums\UnidadPrecio;
use App\Models\CatalogoServicio;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\NivelVariablePrecio;
use App\Models\Servicio;
use App\Models\TipoServicio;
use App\Models\User;
use App\Models\VariablePrecio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CotizacionPrecioNormalizadoTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_summary_uses_operational_and_catalog_descriptions(): void
    {
        $contexto = $this->crearContexto();

        $response = $this->actingAs($contexto['user'])
            ->get(route('cotizaciones.create', [
                'cliente' => $contexto['cliente'],
                'servicio' => $contexto['servicio'],
            ]));

        $response->assertOk();
        $this->assertSame(
            'Descripción operativa',
            $response->viewData('serviciosOperativosPorCliente')[$contexto['cliente']->id][$contexto['catalogo']->id]['descripcion'],
        );

        $contexto['servicio']->update(['descripcion' => null]);

        $response = $this->actingAs($contexto['user'])
            ->get(route('cotizaciones.create', [
                'cliente' => $contexto['cliente'],
                'servicio' => $contexto['servicio'],
            ]));

        $response->assertOk();
        $this->assertNull(
            $response->viewData('serviciosOperativosPorCliente')[$contexto['cliente']->id][$contexto['catalogo']->id]['descripcion'],
        );
        $this->assertSame(
            'Descripción del catálogo',
            $response->viewData('configuracionPrecios')[$contexto['catalogo']->id]['descripcion'],
        );

        $contexto['catalogo']->update(['descripcion' => null]);

        $response = $this->actingAs($contexto['user'])
            ->get(route('cotizaciones.create', [
                'cliente' => $contexto['cliente'],
                'servicio' => $contexto['servicio'],
            ]));

        $response->assertOk();
        $this->assertNull(
            $response->viewData('serviciosOperativosPorCliente')[$contexto['cliente']->id][$contexto['catalogo']->id]['descripcion'],
        );
        $this->assertNull(
            $response->viewData('configuracionPrecios')[$contexto['catalogo']->id]['descripcion'],
        );
    }

    public function test_normalized_price_is_recalculated_and_browser_values_are_ignored(): void
    {
        $contexto = $this->crearContexto();
        $datos = $this->datosNormalizados($contexto);
        $datos['partidas'][0]['monto_sugerido'] = 1;
        $datos['partidas'][0]['unidad'] = UnidadPrecio::HoraProfesional->value;

        $response = $this->actingAs($contexto['user'])->post(
            route('cotizaciones.store'),
            $datos,
        );

        $cotizacion = Cotizacion::with('revisionActual.partidas')->firstOrFail();
        $partida = $cotizacion->revisionActual->partidas->sole();

        $response->assertRedirect(route('cotizaciones.show', $cotizacion));
        $this->assertSame('108000.00', $partida->monto_sugerido);
        $this->assertSame('108000.00', $partida->precio_unitario);
        $this->assertSame(UnidadPrecio::Servicio->value, $partida->unidad);
    }

    public function test_level_from_another_variable_is_rejected(): void
    {
        $contexto = $this->crearContexto();
        $datos = $this->datosNormalizados($contexto);
        $datos['partidas'][0]['criterios'][$contexto['variables'][0]->id] =
            $contexto['niveles'][1]->id;

        $this->actingAs($contexto['user'])
            ->post(route('cotizaciones.store'), $datos)
            ->assertSessionHasErrors(
                'partidas.0.criterios.'.$contexto['variables'][0]->id,
            );

        $this->assertDatabaseCount('cotizaciones', 0);
    }

    public function test_incomplete_normalized_criteria_are_rejected(): void
    {
        $contexto = $this->crearContexto();
        $datos = $this->datosNormalizados($contexto);
        unset($datos['partidas'][0]['criterios'][$contexto['variables'][1]->id]);

        $this->actingAs($contexto['user'])
            ->post(route('cotizaciones.store'), $datos)
            ->assertSessionHasErrors('partidas.0.criterios');
    }

    public function test_variable_not_assigned_to_service_type_is_rejected(): void
    {
        $contexto = $this->crearContexto();
        $variableAjena = VariablePrecio::create([
            'codigo' => 'variable-ajena',
            'nombre' => 'Variable ajena',
            'activo' => true,
        ]);
        $nivelAjeno = NivelVariablePrecio::create([
            'variable_precio_id' => $variableAjena->id,
            'codigo' => 'nivel-ajeno',
            'nombre' => 'Nivel ajeno',
            'factor' => 1,
        ]);
        $datos = $this->datosNormalizados($contexto);
        unset($datos['partidas'][0]['criterios'][$contexto['variables'][1]->id]);
        $datos['partidas'][0]['criterios'][$variableAjena->id] = $nivelAjeno->id;

        $this->actingAs($contexto['user'])
            ->post(route('cotizaciones.store'), $datos)
            ->assertSessionHasErrors('partidas.0.criterios');
    }

    public function test_service_without_base_price_cannot_use_normalized_method(): void
    {
        $contexto = $this->crearContexto();
        $contexto['catalogo']->update(['precio_base' => null]);

        $this->actingAs($contexto['user'])
            ->post(
                route('cotizaciones.store'),
                $this->datosNormalizados($contexto),
            )
            ->assertSessionHasErrors('partidas.0.metodo_precio');

        $this->actingAs($contexto['user'])
            ->get(route('cotizaciones.create', [
                'cliente' => $contexto['cliente'],
                'servicio' => $contexto['servicio'],
            ]))
            ->assertOk()
            ->assertSeeText('Sin referencia configurada');
    }

    public function test_manual_method_requires_justification_and_accepts_valid_data(): void
    {
        $contexto = $this->crearContexto();
        $datos = $this->datosManuales($contexto);
        $datos['partidas'][0]['justificacion_ajuste'] = '';

        $this->actingAs($contexto['user'])
            ->post(route('cotizaciones.store'), $datos)
            ->assertSessionHasErrors('partidas.0.justificacion_ajuste');

        $datos['partidas'][0]['justificacion_ajuste'] =
            'Estimación basada en horas profesionales.';

        $this->actingAs($contexto['user'])
            ->post(route('cotizaciones.store'), $datos)
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('partidas_cotizacion', [
            'metodo_precio' => 'hora_hombre',
            'monto_sugerido' => 90000,
            'precio_unitario' => 95000,
            'unidad' => UnidadPrecio::HoraProfesional->value,
        ]);
    }

    public function test_manual_method_rejects_normalized_criteria_and_unknown_unit(): void
    {
        $contexto = $this->crearContexto();
        $datos = $this->datosManuales($contexto);
        $datos['partidas'][0]['criterios'] = [
            $contexto['variables'][0]->id => $contexto['niveles'][0]->id,
        ];
        $datos['partidas'][0]['unidad'] = 'turno inventado';

        $this->actingAs($contexto['user'])
            ->post(route('cotizaciones.store'), $datos)
            ->assertSessionHasErrors([
                'partidas.0.criterios',
                'partidas.0.unidad',
            ]);
    }

    public function test_normalized_final_price_adjustment_requires_justification(): void
    {
        $contexto = $this->crearContexto();
        $datos = $this->datosNormalizados($contexto);
        $datos['partidas'][0]['precio_unitario'] = 120000;

        $this->actingAs($contexto['user'])
            ->post(route('cotizaciones.store'), $datos)
            ->assertSessionHasErrors('partidas.0.justificacion_ajuste');

        $datos['partidas'][0]['justificacion_ajuste'] =
            'Ajuste por plazo extraordinario solicitado por el cliente.';

        $this->actingAs($contexto['user'])
            ->post(route('cotizaciones.store'), $datos)
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('partidas_cotizacion', [
            'monto_sugerido' => 108000,
            'precio_unitario' => 120000,
        ]);
    }

    public function test_normalized_snapshots_and_criteria_are_persisted(): void
    {
        $contexto = $this->crearContexto();

        $this->actingAs($contexto['user'])->post(
            route('cotizaciones.store'),
            $this->datosNormalizados($contexto),
        );

        $partida = Cotizacion::firstOrFail()
            ->revisionActual
            ->partidas()
            ->with('valoresVariables')
            ->firstOrFail();

        $this->assertSame($contexto['catalogo']->id, $partida->catalogo_servicio_id);
        $this->assertSame('Servicio normalizado', $partida->catalogo_nombre_snapshot);
        $this->assertSame('Descripción del catálogo', $partida->catalogo_descripcion_snapshot);
        $this->assertSame('100000.00', $partida->precio_base_snapshot);
        $this->assertSame('CLP', $partida->moneda_precio_snapshot);
        $this->assertSame(UnidadPrecio::Servicio->value, $partida->unidad_precio_snapshot);
        $this->assertSame('1.080000', $partida->factor_total_snapshot);
        $this->assertSame('97200.00', $partida->rango_minimo_snapshot);
        $this->assertSame('118800.00', $partida->rango_maximo_snapshot);
        $this->assertCount(2, $partida->valoresVariables);
        $this->assertEqualsCanonicalizing(
            ['1.2000', '0.9000'],
            $partida->valoresVariables->pluck('factor_aplicado')->all(),
        );
        $this->assertSame(
            ['1.0000', '1.0000'],
            $partida->valoresVariables->pluck('peso_aplicado')->all(),
        );
    }

    public function test_snapshot_remains_unchanged_when_reference_is_edited_later(): void
    {
        $contexto = $this->crearContexto();

        $this->actingAs($contexto['user'])->post(
            route('cotizaciones.store'),
            $this->datosNormalizados($contexto),
        );
        $partida = Cotizacion::firstOrFail()->revisionActual->partidas()->firstOrFail();

        $contexto['catalogo']->update([
            'precio_base' => 500000,
            'descripcion' => 'Descripción posterior',
            'unidad_precio' => UnidadPrecio::Jornada->value,
        ]);
        $contexto['niveles'][0]->update(['factor' => 2]);

        $partida->refresh();
        $this->assertSame('100000.00', $partida->precio_base_snapshot);
        $this->assertSame('Descripción del catálogo', $partida->catalogo_descripcion_snapshot);
        $this->assertSame(UnidadPrecio::Servicio->value, $partida->unidad_precio_snapshot);
        $this->assertSame('1.080000', $partida->factor_total_snapshot);
    }

    public function test_two_lines_keep_independent_normalized_criteria(): void
    {
        $contexto = $this->crearContexto();
        $datos = $this->datosNormalizados($contexto);
        $segunda = $datos['partidas'][0];
        $segunda['descripcion'] = 'Segunda partida';
        $segunda['criterios'] = [
            $contexto['variables'][0]->id => $contexto['nivelesAlternativos'][0]->id,
            $contexto['variables'][1]->id => $contexto['nivelesAlternativos'][1]->id,
        ];
        $segunda['precio_unitario'] = 100000;
        $datos['partidas'][] = $segunda;

        $this->actingAs($contexto['user'])
            ->post(route('cotizaciones.store'), $datos)
            ->assertSessionHasNoErrors();

        $partidas = Cotizacion::firstOrFail()
            ->revisionActual
            ->partidas()
            ->with('valoresVariables')
            ->get();

        $this->assertCount(2, $partidas);
        $this->assertSame('108000.00', $partidas[0]->monto_sugerido);
        $this->assertSame('100000.00', $partidas[1]->monto_sugerido);
        $this->assertCount(2, $partidas[0]->valoresVariables);
        $this->assertCount(2, $partidas[1]->valoresVariables);
        $this->assertNotEqualsCanonicalizing(
            $partidas[0]->valoresVariables->pluck('nivel_variable_precio_id')->all(),
            $partidas[1]->valoresVariables->pluck('nivel_variable_precio_id')->all(),
        );
    }

    public function test_domain_action_rechecks_normalized_level_ownership(): void
    {
        $contexto = $this->crearContexto();
        $datos = $this->datosNormalizados($contexto);
        $datos['partidas'][0]['criterios'][$contexto['variables'][0]->id] =
            $contexto['niveles'][1]->id;

        $this->expectException(ValidationException::class);

        app(CrearCotizacion::class)->ejecutar($datos, $contexto['user']->id);
    }

    public function test_historical_line_without_price_snapshots_still_opens(): void
    {
        $contexto = $this->crearContexto();
        $cotizacion = Cotizacion::create([
            'cliente_id' => $contexto['cliente']->id,
            'creado_por' => $contexto['user']->id,
            'codigo' => 'COT-HIST-PRECIO',
            'estado' => 'borrador',
        ]);
        $revision = $cotizacion->revisiones()->create([
            'creado_por' => $contexto['user']->id,
            'revision' => 1,
            'titulo' => 'Cotización histórica',
            'moneda' => 'CLP',
            'estado' => 'borrador',
        ]);
        $revision->partidas()->create([
            'descripcion' => 'Partida histórica',
            'cantidad' => 1,
            'unidad' => 'servicio',
            'precio_unitario' => 50000,
            'monto_neto' => 50000,
            'metodo_precio' => 'normalizado',
            'monto_final' => 50000,
            'orden' => 1,
        ]);
        $cotizacion->update(['revision_actual_id' => $revision->id]);

        $this->actingAs($contexto['user'])
            ->get(route('cotizaciones.show', $cotizacion))
            ->assertOk()
            ->assertSeeText('Partida histórica')
            ->assertSeeText('Sin registro');
    }

    private function crearContexto(): array
    {
        $user = User::factory()->create(['activo' => true]);
        $cliente = Cliente::create([
            'razon_social' => 'Cliente de prueba',
            'identificador_tributario' => '76.123.456-7',
            'estado' => 'activo',
        ]);
        $tipo = TipoServicio::create([
            'codigo' => 'tipo-prueba',
            'nombre' => 'Tipo de prueba',
            'activo' => true,
        ]);
        $catalogo = CatalogoServicio::create([
            'tipo_servicio_id' => $tipo->id,
            'codigo' => 'catalogo-prueba',
            'nombre' => 'Servicio normalizado',
            'descripcion' => 'Descripción del catálogo',
            'precio_base' => 100000,
            'moneda_precio' => 'CLP',
            'unidad_precio' => UnidadPrecio::Servicio->value,
            'activo' => true,
        ]);
        $servicio = Servicio::create([
            'cliente_id' => $cliente->id,
            'tipo_servicio_id' => $tipo->id,
            'catalogo_servicio_id' => $catalogo->id,
            'codigo' => 'SER-PRUEBA',
            'nombre' => 'Servicio operativo',
            'descripcion' => 'Descripción operativa',
            'estado' => 'en_curso',
        ]);

        $variableUno = VariablePrecio::create([
            'codigo' => 'complejidad',
            'nombre' => 'Complejidad',
            'descripcion' => 'Complejidad técnica del trabajo.',
            'activo' => true,
        ]);
        $nivelUno = NivelVariablePrecio::create([
            'variable_precio_id' => $variableUno->id,
            'codigo' => 'alta',
            'nombre' => 'Alta',
            'criterio' => 'Trabajo de alta complejidad.',
            'factor' => 1.2,
            'orden' => 1,
        ]);
        $nivelUnoAlternativo = NivelVariablePrecio::create([
            'variable_precio_id' => $variableUno->id,
            'codigo' => 'media',
            'nombre' => 'Media',
            'factor' => 1,
            'orden' => 2,
        ]);
        $variableDos = VariablePrecio::create([
            'codigo' => 'informacion',
            'nombre' => 'Información',
            'descripcion' => 'Calidad de los antecedentes disponibles.',
            'activo' => true,
        ]);
        $nivelDos = NivelVariablePrecio::create([
            'variable_precio_id' => $variableDos->id,
            'codigo' => 'completa',
            'nombre' => 'Completa',
            'factor' => 0.9,
            'orden' => 1,
        ]);
        $nivelDosAlternativo = NivelVariablePrecio::create([
            'variable_precio_id' => $variableDos->id,
            'codigo' => 'parcial',
            'nombre' => 'Parcial',
            'factor' => 1,
            'orden' => 2,
        ]);
        $tipo->variablesPrecio()->attach([
            $variableUno->id => ['peso' => 1, 'requerida' => true],
            $variableDos->id => ['peso' => 1, 'requerida' => true],
        ]);

        return [
            'user' => $user,
            'cliente' => $cliente,
            'tipo' => $tipo,
            'catalogo' => $catalogo,
            'servicio' => $servicio,
            'variables' => [$variableUno, $variableDos],
            'niveles' => [$nivelUno, $nivelDos],
            'nivelesAlternativos' => [$nivelUnoAlternativo, $nivelDosAlternativo],
        ];
    }

    private function datosNormalizados(array $contexto): array
    {
        return [
            'cliente_id' => $contexto['cliente']->id,
            'servicio_id' => $contexto['servicio']->id,
            'titulo' => 'Cotización normalizada',
            'moneda' => 'CLP',
            'iva_porcentaje' => 19,
            'partidas' => [[
                'descripcion' => 'Partida normalizada editable',
                'clase' => 'servicio',
                'cantidad' => 1,
                'unidad' => UnidadPrecio::HoraProfesional->value,
                'precio_unitario' => 108000,
                'metodo_precio' => 'normalizado',
                'monto_sugerido' => 999999,
                'justificacion_ajuste' => null,
                'criterios' => [
                    $contexto['variables'][0]->id => $contexto['niveles'][0]->id,
                    $contexto['variables'][1]->id => $contexto['niveles'][1]->id,
                ],
            ]],
        ];
    }

    private function datosManuales(array $contexto): array
    {
        return [
            'cliente_id' => $contexto['cliente']->id,
            'servicio_id' => $contexto['servicio']->id,
            'titulo' => 'Cotización manual',
            'moneda' => 'CLP',
            'iva_porcentaje' => 19,
            'partidas' => [[
                'descripcion' => 'Partida manual',
                'clase' => 'servicio',
                'cantidad' => 1,
                'unidad' => UnidadPrecio::HoraProfesional->value,
                'precio_unitario' => 95000,
                'metodo_precio' => 'hora_hombre',
                'monto_sugerido' => 90000,
                'justificacion_ajuste' => 'Estimación basada en horas profesionales.',
            ]],
        ];
    }
}
