<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\CotizacionRevision;
use App\Models\Documento;
use App\Models\DocumentoVinculo;
use App\Models\RevisionServicio;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DocumentoTest extends TestCase
{
    use RefreshDatabase;

    public function test_documento_relations_expose_its_owner_creator_and_linked_entities(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::create([
            'razon_social' => 'Cliente Documental',
            'identificador_tributario' => '11.111.111-1',
            'estado' => 'activo',
        ]);
        $cotizacion = Cotizacion::create([
            'cliente_id' => $cliente->id,
            'creado_por' => $user->id,
            'codigo' => 'COT-DOC-001',
            'estado' => 'borrador',
        ]);
        $documento = Documento::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Propuesta técnica',
            'nombre_original' => 'propuesta-tecnica.pdf',
            'descripcion' => 'Documento asociado a cliente y cotización.',
            'tipo_documento' => 'propuesta',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'tamano' => 2048,
            'ruta_storage' => 'clientes/1/propuesta-tecnica.pdf',
            'usuario_creador_id' => $user->id,
        ]);

        $cliente->documentos()->attach($documento);
        $cotizacion->documentos()->attach($documento);

        $this->assertTrue($documento->cliente->is($cliente));
        $this->assertTrue($documento->usuarioCreador->is($user));
        $this->assertTrue($cliente->documentos()->sole()->is($documento));
        $this->assertTrue($cotizacion->documentos()->sole()->is($documento));
        $this->assertCount(2, $documento->vinculos);
        $this->assertTrue($documento->vinculos
            ->firstWhere('vinculable_type', Cliente::class)
            ->vinculable
            ->is($cliente));
        $this->assertTrue($documento->vinculos
            ->firstWhere('vinculable_type', Cotizacion::class)
            ->documento
            ->is($documento));
    }

    public function test_same_document_cannot_be_linked_twice_to_the_same_entity(): void
    {
        $user = User::factory()->create();
        $cliente = Cliente::create([
            'razon_social' => 'Cliente Vínculo Único',
            'identificador_tributario' => '22.222.222-2',
            'estado' => 'activo',
        ]);
        $documento = Documento::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Contrato',
            'nombre_original' => 'contrato.pdf',
            'tipo_documento' => 'contrato',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'tamano' => 4096,
            'ruta_storage' => 'clientes/1/contrato.pdf',
            'usuario_creador_id' => $user->id,
        ]);
        DocumentoVinculo::create([
            'documento_id' => $documento->id,
            'vinculable_type' => Cliente::class,
            'vinculable_id' => $cliente->id,
        ]);

        $this->expectException(QueryException::class);

        DocumentoVinculo::create([
            'documento_id' => $documento->id,
            'vinculable_type' => Cliente::class,
            'vinculable_id' => $cliente->id,
        ]);
    }

    #[DataProvider('documentTargets')]
    public function test_valid_document_is_uploaded_with_private_metadata_and_link(
        string $target,
    ): void {
        Storage::fake('documentos');
        [$user, $cliente, $cotizacion] = $this->createDocumentContext();
        $vinculable = $target === 'cliente' ? $cliente : $cotizacion;
        $route = $target === 'cliente'
            ? 'clientes.documentos.store'
            : 'cotizaciones.documentos.store';
        $contenidoPdf = "%PDF-1.4\n%%EOF";
        $archivo = UploadedFile::fake()->createWithContent(
            'informe-tecnico.pdf',
            $contenidoPdf,
        );

        $response = $this->actingAs($user)
            ->from('/origen-documentos')
            ->post(route($route, $vinculable), [
                'archivo' => $archivo,
                'tipo_documento' => 'informe',
                'descripcion' => 'Informe técnico de prueba.',
            ]);

        $documento = Documento::query()->sole();
        $response->assertRedirect('/origen-documentos');
        $this->assertSame($cliente->id, $documento->cliente_id);
        $this->assertSame('informe-tecnico', $documento->nombre);
        $this->assertSame('informe-tecnico.pdf', $documento->nombre_original);
        $this->assertSame('Informe técnico de prueba.', $documento->descripcion);
        $this->assertSame('informe', $documento->tipo_documento);
        $this->assertSame('application/pdf', $documento->mime_type);
        $this->assertSame('pdf', $documento->extension);
        $this->assertSame(strlen($contenidoPdf), $documento->tamano);
        $this->assertSame($user->id, $documento->usuario_creador_id);
        $this->assertMatchesRegularExpression(
            '/^clientes\/'.$cliente->id.'\/[0-9a-f-]{36}\.pdf$/',
            $documento->ruta_storage,
        );
        $this->assertStringNotContainsString(
            $documento->nombre_original,
            $documento->ruta_storage,
        );
        Storage::disk('documentos')->assertExists($documento->ruta_storage);
        $this->assertDatabaseHas('documento_vinculos', [
            'documento_id' => $documento->id,
            'vinculable_type' => $vinculable->getMorphClass(),
            'vinculable_id' => $vinculable->getKey(),
        ]);
    }

    public function test_upload_rejects_a_disallowed_file_format(): void
    {
        Storage::fake('documentos');
        [$user, $cliente] = $this->createDocumentContext();
        $archivo = UploadedFile::fake()->createWithContent(
            'programa.exe',
            'MZ contenido ejecutable',
        );

        $response = $this->actingAs($user)->post(
            route('clientes.documentos.store', $cliente),
            [
                'archivo' => $archivo,
                'tipo_documento' => 'otro',
            ],
        );

        $response->assertSessionHasErrors('archivo');
        $this->assertDatabaseCount('documentos', 0);
        $this->assertSame([], Storage::disk('documentos')->allFiles());
    }

    public function test_linked_document_can_be_downloaded_through_laravel(): void
    {
        Storage::fake('documentos');
        [$user, $cliente] = $this->createDocumentContext();
        $documento = $this->createStoredDocument($user, $cliente);
        $cliente->documentos()->attach($documento);

        $response = $this->actingAs($user)->get(route(
            'clientes.documentos.download',
            [$cliente, $documento],
        ));

        $response->assertDownload('contrato.pdf');
    }

    public function test_document_from_another_organization_cannot_be_downloaded(): void
    {
        Storage::fake('documentos');
        [$user, $cliente] = $this->createDocumentContext();
        $otroCliente = Cliente::create([
            'razon_social' => 'Otra Organización',
            'identificador_tributario' => '44.444.444-4',
            'estado' => 'activo',
        ]);
        $documento = $this->createStoredDocument($user, $cliente);
        $cliente->documentos()->attach($documento);

        $response = $this->actingAs($user)->get(route(
            'clientes.documentos.download',
            [$otroCliente, $documento],
        ));

        $response->assertNotFound();
    }

    public function test_document_is_unlinked_without_deleting_its_record_or_file(): void
    {
        Storage::fake('documentos');
        [$user, $cliente] = $this->createDocumentContext();
        $documento = $this->createStoredDocument($user, $cliente);
        $cliente->documentos()->attach($documento);

        $response = $this->actingAs($user)
            ->from('/origen-documentos')
            ->delete(route(
                'clientes.documentos.destroy',
                [$cliente, $documento],
            ));

        $response->assertRedirect('/origen-documentos');
        $this->assertModelExists($documento);
        $this->assertDatabaseMissing('documento_vinculos', [
            'documento_id' => $documento->id,
            'vinculable_type' => Cliente::class,
            'vinculable_id' => $cliente->id,
        ]);
        Storage::disk('documentos')->assertExists($documento->ruta_storage);
    }

    public function test_library_lists_documents_with_their_organizations(): void
    {
        [$user, $cliente] = $this->createDocumentContext();
        $documento = $this->createStoredDocument($user, $cliente);

        $response = $this->actingAs($user)->get(route('biblioteca.index'));

        $response->assertOk()
            ->assertSeeText($documento->nombre)
            ->assertSeeText($cliente->nombre_display);
    }

    public function test_library_combines_organization_and_document_type_filters(): void
    {
        [$user, $cliente] = $this->createDocumentContext();
        $documentoVisible = $this->createStoredDocument($user, $cliente);
        $documentoVisible->update(['tipo_documento' => 'informe']);
        $otroCliente = Cliente::create([
            'razon_social' => 'Organización Excluida',
            'identificador_tributario' => '55.555.555-5',
            'estado' => 'activo',
        ]);
        Documento::create([
            'cliente_id' => $otroCliente->id,
            'nombre' => 'Plano excluido',
            'nombre_original' => 'plano.pdf',
            'tipo_documento' => 'plano',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'tamano' => 8,
            'ruta_storage' => 'clientes/'.$otroCliente->id.'/plano.pdf',
            'usuario_creador_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('biblioteca.index', [
            'cliente' => $cliente->id,
            'tipo' => 'informe',
        ]));

        $response->assertOk()
            ->assertSeeText($documentoVisible->nombre)
            ->assertDontSeeText('Plano excluido');
    }

    public function test_document_can_be_uploaded_from_library_with_a_valid_link(): void
    {
        Storage::fake('documentos');
        [$user, $cliente, $cotizacion] = $this->createDocumentContext();
        $archivo = UploadedFile::fake()->createWithContent(
            'informe.pdf',
            "%PDF-1.4\n%%EOF",
        );

        $response = $this->actingAs($user)
            ->from(route('biblioteca.index'))
            ->post(route('biblioteca.documentos.store'), [
                'archivo' => $archivo,
                'nombre' => 'Informe de terreno',
                'tipo_documento' => 'informe',
                'cliente_id' => $cliente->id,
                'vinculable_type' => 'cotizacion',
                'vinculable_id' => $cotizacion->id,
            ]);

        $documento = Documento::query()->sole();
        $response->assertRedirect(route('biblioteca.index'));
        $this->assertSame('Informe de terreno', $documento->nombre);
        $this->assertDatabaseHas('documento_vinculos', [
            'documento_id' => $documento->id,
            'vinculable_type' => Cotizacion::class,
            'vinculable_id' => $cotizacion->id,
        ]);
        Storage::disk('documentos')->assertExists($documento->ruta_storage);
    }

    public function test_library_adds_a_link_within_the_same_organization(): void
    {
        [$user, $cliente, $cotizacion] = $this->createDocumentContext();
        $documento = $this->createStoredDocument($user, $cliente);

        $response = $this->actingAs($user)->post(route(
            'biblioteca.documentos.links.store',
            [$cliente, $documento],
        ), [
            'vinculable_type' => 'cotizacion',
            'vinculable_id' => $cotizacion->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('documento_vinculos', [
            'documento_id' => $documento->id,
            'vinculable_type' => Cotizacion::class,
            'vinculable_id' => $cotizacion->id,
        ]);
    }

    public function test_library_rejects_a_link_from_another_organization(): void
    {
        [$user, $cliente] = $this->createDocumentContext();
        $documento = $this->createStoredDocument($user, $cliente);
        $otroCliente = Cliente::create([
            'razon_social' => 'Organización Ajena',
            'identificador_tributario' => '77.777.777-7',
            'estado' => 'activo',
        ]);
        $cotizacionAjena = Cotizacion::create([
            'cliente_id' => $otroCliente->id,
            'creado_por' => $user->id,
            'codigo' => 'COT-AJENA',
            'estado' => 'borrador',
        ]);

        $response = $this->actingAs($user)->post(route(
            'biblioteca.documentos.links.store',
            [$cliente, $documento],
        ), [
            'vinculable_type' => 'cotizacion',
            'vinculable_id' => $cotizacionAjena->id,
        ]);

        $response->assertSessionHasErrors('vinculable_id');
        $this->assertDatabaseMissing('documento_vinculos', [
            'documento_id' => $documento->id,
            'vinculable_type' => Cotizacion::class,
            'vinculable_id' => $cotizacionAjena->id,
        ]);
    }

    public function test_library_removes_only_the_selected_link(): void
    {
        Storage::fake('documentos');
        [$user, $cliente, $cotizacion] = $this->createDocumentContext();
        $documento = $this->createStoredDocument($user, $cliente);
        $vinculo = DocumentoVinculo::create([
            'documento_id' => $documento->id,
            'vinculable_type' => Cotizacion::class,
            'vinculable_id' => $cotizacion->id,
        ]);

        $response = $this->actingAs($user)->delete(route(
            'biblioteca.documentos.links.destroy',
            [$cliente, $documento, $vinculo],
        ));

        $response->assertRedirect();
        $this->assertModelExists($documento);
        $this->assertModelMissing($vinculo);
        Storage::disk('documentos')->assertExists($documento->ruta_storage);
    }

    public function test_revision_service_can_have_documents(): void
    {
        [$user, $cliente, $cotizacion] = $this->createDocumentContext();
        $revisionServicio = $this->createRevisionService($cotizacion, $user);
        $documento = $this->createStoredDocument($user, $cliente);

        $revisionServicio->documentos()->attach($documento);

        $this->assertTrue($revisionServicio->documentos()->sole()->is($documento));
        $this->assertTrue($documento->vinculos()->sole()->vinculable->is($revisionServicio));
    }

    public function test_general_quote_documents_and_service_document_counts_are_derived_from_links(): void
    {
        [$user, $cliente, $cotizacion] = $this->createDocumentContext();
        $revisionServicio = $this->createRevisionService($cotizacion, $user);
        $documentoGeneral = $this->createStoredDocument($user, $cliente);
        $documentoServicio = Documento::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Plano del servicio',
            'nombre_original' => 'plano-servicio.pdf',
            'tipo_documento' => 'plano',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'tamano' => 8,
            'ruta_storage' => 'clientes/'.$cliente->id.'/plano-servicio.pdf',
            'usuario_creador_id' => $user->id,
        ]);

        $cotizacion->documentos()->attach([$documentoGeneral->id, $documentoServicio->id]);
        $revisionServicio->documentos()->attach($documentoServicio);

        $this->assertSame(1, $revisionServicio->documentos()->count());
        $this->assertTrue(
            Documento::query()->generalesDeCotizacion($cotizacion)->sole()->is($documentoGeneral),
        );
    }

    public function test_document_is_uploaded_for_quote_service_with_all_context_links(): void
    {
        Storage::fake('documentos');
        [$user, $cliente, $cotizacion] = $this->createDocumentContext();
        $revisionServicio = $this->createRevisionService($cotizacion, $user);

        $response = $this->actingAs($user)->post(
            route('biblioteca.documentos.store'),
            [
                'archivo' => UploadedFile::fake()->createWithContent(
                    'informe-servicio.pdf',
                    "%PDF-1.4\n%%EOF",
                ),
                'nombre' => 'Informe del servicio cotizado',
                'tipo_documento' => 'informe',
                'cliente_id' => $cliente->id,
                'cotizacion_id' => $cotizacion->id,
                'revision_servicio_id' => $revisionServicio->id,
            ],
        );

        $documento = Documento::query()->sole();
        $response->assertRedirect();
        $this->assertDatabaseHas('documento_vinculos', [
            'documento_id' => $documento->id,
            'vinculable_type' => Cliente::class,
            'vinculable_id' => $cliente->id,
        ]);
        $this->assertDatabaseHas('documento_vinculos', [
            'documento_id' => $documento->id,
            'vinculable_type' => Cotizacion::class,
            'vinculable_id' => $cotizacion->id,
        ]);
        $this->assertDatabaseHas('documento_vinculos', [
            'documento_id' => $documento->id,
            'vinculable_type' => RevisionServicio::class,
            'vinculable_id' => $revisionServicio->id,
        ]);
    }

    public function test_library_rejects_a_quote_service_from_another_quote_and_organization(): void
    {
        Storage::fake('documentos');
        [$user, $cliente, $cotizacion] = $this->createDocumentContext();
        $otroCliente = Cliente::create([
            'razon_social' => 'Organización con servicio ajeno',
            'identificador_tributario' => '88.888.888-8',
            'estado' => 'activo',
        ]);
        $otraCotizacion = Cotizacion::create([
            'cliente_id' => $otroCliente->id,
            'creado_por' => $user->id,
            'codigo' => 'COT-SERVICIO-AJENO',
            'estado' => 'borrador',
        ]);
        $revisionServicioAjeno = $this->createRevisionService($otraCotizacion, $user);

        $response = $this->actingAs($user)->post(
            route('biblioteca.documentos.store'),
            [
                'archivo' => UploadedFile::fake()->createWithContent(
                    'informe.pdf',
                    "%PDF-1.4\n%%EOF",
                ),
                'nombre' => 'Documento inválido',
                'tipo_documento' => 'informe',
                'cliente_id' => $cliente->id,
                'cotizacion_id' => $cotizacion->id,
                'revision_servicio_id' => $revisionServicioAjeno->id,
            ],
        );

        $response->assertSessionHasErrors('revision_servicio_id');
        $this->assertDatabaseCount('documentos', 0);
        $this->assertSame([], Storage::disk('documentos')->allFiles());
    }

    public function test_library_filters_documents_by_quote(): void
    {
        [$user, $cliente, $cotizacion] = $this->createDocumentContext();
        $revisionServicio = $this->createRevisionService($cotizacion, $user);
        $documentoGeneral = $this->createStoredDocument($user, $cliente);
        $documentoServicio = Documento::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Documento del servicio visible',
            'nombre_original' => 'servicio.pdf',
            'tipo_documento' => 'informe',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'tamano' => 8,
            'ruta_storage' => 'clientes/'.$cliente->id.'/servicio.pdf',
            'usuario_creador_id' => $user->id,
        ]);
        $cotizacion->documentos()->attach($documentoGeneral);
        $revisionServicio->documentos()->attach($documentoServicio);

        $response = $this->actingAs($user)->get(route('biblioteca.index', [
            'cotizacion' => $cotizacion->id,
        ]));

        $response->assertOk()
            ->assertSeeText($documentoGeneral->nombre)
            ->assertSeeText($documentoServicio->nombre)
            ->assertSeeText('Cotización · '.$cotizacion->codigo);
    }

    public function test_library_filters_documents_by_quote_service(): void
    {
        [$user, $cliente, $cotizacion] = $this->createDocumentContext();
        $revisionServicio = $this->createRevisionService($cotizacion, $user);
        $documentoGeneral = $this->createStoredDocument($user, $cliente);
        $documentoServicio = Documento::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Informe exclusivo del servicio',
            'nombre_original' => 'exclusivo.pdf',
            'tipo_documento' => 'informe',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'tamano' => 8,
            'ruta_storage' => 'clientes/'.$cliente->id.'/exclusivo.pdf',
            'usuario_creador_id' => $user->id,
        ]);
        $cotizacion->documentos()->attach($documentoGeneral);
        $revisionServicio->documentos()->attach($documentoServicio);

        $response = $this->actingAs($user)->get(route('biblioteca.index', [
            'cotizacion' => $cotizacion->id,
            'revision_servicio' => $revisionServicio->id,
        ]));

        $response->assertOk()
            ->assertSeeText($documentoServicio->nombre)
            ->assertDontSeeText($documentoGeneral->nombre)
            ->assertSeeText('Servicio · '.$revisionServicio->titulo);
    }

    public static function documentTargets(): array
    {
        return [
            'cliente' => ['cliente'],
            'cotizacion' => ['cotizacion'],
        ];
    }

    /**
     * @return array{User, Cliente, Cotizacion}
     */
    private function createDocumentContext(): array
    {
        $user = User::factory()->create(['activo' => true]);
        $cliente = Cliente::create([
            'razon_social' => 'Cliente Contexto Documental',
            'identificador_tributario' => '33.333.333-3',
            'estado' => 'activo',
        ]);
        $cotizacion = Cotizacion::create([
            'cliente_id' => $cliente->id,
            'creado_por' => $user->id,
            'codigo' => 'COT-DOC-CONTEXTO',
            'estado' => 'borrador',
        ]);

        return [$user, $cliente, $cotizacion];
    }

    private function createStoredDocument(
        User $user,
        Cliente $cliente,
    ): Documento {
        $rutaStorage = 'clientes/'.$cliente->id.'/archivo-privado.pdf';
        Storage::disk('documentos')->put($rutaStorage, '%PDF-1.4');

        return Documento::create([
            'cliente_id' => $cliente->id,
            'nombre' => 'Contrato',
            'nombre_original' => 'contrato.pdf',
            'tipo_documento' => 'contrato',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'tamano' => 8,
            'ruta_storage' => $rutaStorage,
            'usuario_creador_id' => $user->id,
        ]);
    }

    private function createRevisionService(
        Cotizacion $cotizacion,
        User $user,
        string $titulo = 'Inspección técnica cotizada',
    ): RevisionServicio {
        $revision = CotizacionRevision::create([
            'cotizacion_id' => $cotizacion->id,
            'creado_por' => $user->id,
            'revision' => 1,
            'titulo' => 'Revisión documental',
            'fecha_emision' => now()->toDateString(),
            'moneda' => 'CLP',
            'iva_porcentaje' => 19,
            'subtotal' => 0,
            'iva' => 0,
            'total' => 0,
            'estado' => 'borrador',
            'cliente_snapshot' => [],
            'contacto_snapshot' => [],
        ]);
        $cotizacion->update(['revision_actual_id' => $revision->id]);

        return RevisionServicio::create([
            'cotizacion_revision_id' => $revision->id,
            'titulo' => $titulo,
            'descripcion' => 'Servicio usado para probar vínculos documentales.',
            'orden' => 1,
        ]);
    }
}
