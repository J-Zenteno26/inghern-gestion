<?php

namespace App\Domain\Cotizaciones;

use App\Domain\Shared\GeneraCodigo;
use App\Enums\UnidadPrecio;
use App\Models\CatalogoServicio;
use App\Models\Cliente;
use App\Models\Contacto;
use App\Models\Cotizacion;
use App\Models\Servicio;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CrearCotizacion
{
    public function __construct(
        private readonly GeneraCodigo $codigos,
        private readonly CalculadorPrecioNormalizado $calculador,
    ) {}

    public function ejecutar(array $datos, int $usuarioId): Cotizacion
    {
        if (! isset($datos['servicios']) && isset($datos['partidas'])) {
            $servicio = ! empty($datos['servicio_id'])
                ? Servicio::query()->find($datos['servicio_id'])
                : null;
            $datos['servicios'] = [[
                'tipo_servicio_id' => $servicio?->tipo_servicio_id,
                'catalogo_servicio_id' => $servicio?->catalogo_servicio_id,
                'servicio_id' => $datos['servicio_id'] ?? null,
                'partidas' => $datos['partidas'],
            ]];
        }

        return DB::transaction(function () use ($datos, $usuarioId) {
            $cliente = Cliente::query()->lockForUpdate()->findOrFail($datos['cliente_id']);
            $contacto = isset($datos['contacto_id'])
                ? Contacto::whereBelongsTo($cliente)->find($datos['contacto_id'])
                : null;

            $cotizacion = Cotizacion::create([
                'cliente_id' => $cliente->id,
                'creado_por' => $usuarioId,
                'codigo' => $this->codigos->siguiente(Cotizacion::class, 'COT'),
                'estado' => 'borrador',
            ]);

            $revision = $cotizacion->revisiones()->create([
                'contacto_id' => $contacto?->id,
                'creado_por' => $usuarioId,
                'revision' => 1,
                'titulo' => $datos['titulo'],
                'moneda' => $datos['moneda'] ?? 'CLP',
                'iva_porcentaje' => $datos['iva_porcentaje'] ?? 19,
                'estado' => 'borrador',
                'cliente_snapshot' => Arr::only($cliente->toArray(), [
                    'razon_social',
                    'nombre_fantasia',
                    'identificador_tributario',
                    'direccion',
                    'comuna',
                    'ciudad',
                    'region',
                ]),
                'contacto_snapshot' => $contacto
                    ? Arr::only($contacto->toArray(), ['nombre', 'cargo', 'email', 'telefono'])
                    : null,
            ]);

            $subtotal = 0;
            $ordenPartida = 1;

            foreach (array_values($datos['servicios']) as $servicioIndice => $datosServicio) {
                [$catalogo, $servicioOperativo] = $this->resolverServicioCotizado(
                    $cliente->id,
                    $datosServicio,
                    $servicioIndice,
                );

                $revisionServicio = $revision->servicios()->create([
                    'servicio_id' => $servicioOperativo?->id,
                    'catalogo_servicio_id' => $catalogo?->id,
                    'titulo' => $servicioOperativo?->nombre ?? $catalogo?->nombre ?? 'Servicio',
                    'descripcion' => $servicioOperativo?->descripcion
                        ?: $catalogo?->descripcion,
                    'orden' => $servicioIndice + 1,
                ]);

                foreach (array_values($datosServicio['partidas']) as $partidaIndice => $datosPartida) {
                    $ruta = "servicios.{$servicioIndice}.partidas.{$partidaIndice}";
                    $metodo = $datosPartida['metodo_precio'] ?? 'a_criterio';

                    if (! in_array($metodo, [
                        'a_criterio',
                        'referencia',
                        'hora_hombre',
                        'costo_margen',
                        'normalizado',
                    ], true)) {
                        throw ValidationException::withMessages([
                            "{$ruta}.metodo_precio" => 'El método de precio seleccionado no es válido.',
                        ]);
                    }

                    if (! isset($datosPartida['precio_unitario']) ||
                        ! is_numeric($datosPartida['precio_unitario']) ||
                        (float) $datosPartida['precio_unitario'] < 0) {
                        throw ValidationException::withMessages([
                            "{$ruta}.precio_unitario" => 'El precio final debe ser numérico y mayor o igual a cero.',
                        ]);
                    }

                    $normalizacion = $metodo === 'normalizado'
                        ? $this->resolverNormalizacion($catalogo, $datosPartida, $ruta)
                        : null;

                    if ($metodo !== 'normalizado') {
                        $this->validarPartidaManual($datosPartida, $ruta);
                    }

                    $cantidad = (float) $datosPartida['cantidad'];
                    $precio = (float) $datosPartida['precio_unitario'];
                    $monto = round($cantidad * $precio, 2);
                    $subtotal += $monto;
                    $montoSugerido = $normalizacion
                        ? $normalizacion['calculo']['monto_sugerido']
                        : (float) $datosPartida['monto_sugerido'];
                    $unidad = $normalizacion
                        ? $normalizacion['catalogo']->unidad_precio
                        : $datosPartida['unidad'];

                    if ($normalizacion &&
                        $this->aCentavos($precio) !== $this->aCentavos($montoSugerido) &&
                        blank($datosPartida['justificacion_ajuste'] ?? null)) {
                        throw ValidationException::withMessages([
                            "{$ruta}.justificacion_ajuste" => 'Justifica por qué el precio final difiere del valor sugerido.',
                        ]);
                    }

                    $partida = $revision->partidas()->create([
                        'revision_servicio_id' => $revisionServicio->id,
                        'catalogo_servicio_id' => $normalizacion['catalogo']->id ?? $catalogo?->id,
                        'clase' => $datosPartida['clase'] ?? 'servicio',
                        'descripcion' => $datosPartida['descripcion'],
                        'cantidad' => $cantidad,
                        'unidad' => $unidad,
                        'precio_unitario' => $precio,
                        'monto_neto' => $monto,
                        'metodo_precio' => $metodo,
                        'monto_sugerido' => $montoSugerido,
                        'monto_final' => $precio,
                        'justificacion_ajuste' => $datosPartida['justificacion_ajuste'] ?? null,
                        'catalogo_nombre_snapshot' => $catalogo?->nombre,
                        'catalogo_descripcion_snapshot' => $catalogo?->descripcion,
                        'precio_base_snapshot' => $normalizacion['calculo']['precio_base'] ?? $catalogo?->precio_base,
                        'moneda_precio_snapshot' => $catalogo?->moneda_precio,
                        'unidad_precio_snapshot' => $catalogo?->unidad_precio,
                        'factor_total_snapshot' => $normalizacion['calculo']['factor_compuesto'] ?? null,
                        'rango_minimo_snapshot' => $normalizacion['calculo']['rango_minimo'] ?? null,
                        'rango_maximo_snapshot' => $normalizacion['calculo']['rango_maximo'] ?? null,
                        'orden' => $ordenPartida++,
                    ]);

                    foreach ($normalizacion['selecciones'] ?? [] as $seleccion) {
                        $partida->valoresVariables()->create([
                            'cotizacion_revision_id' => $revision->id,
                            'revision_servicio_id' => $revisionServicio->id,
                            'variable_precio_id' => $seleccion['variable']->id,
                            'nivel_variable_precio_id' => $seleccion['nivel']->id,
                            'variable_codigo' => $seleccion['variable']->codigo,
                            'nivel_nombre' => $seleccion['nivel']->nombre,
                            'factor_aplicado' => $seleccion['nivel']->factor,
                            'peso_aplicado' => $seleccion['variable']->pivot->peso,
                            'fuente' => 'sistema',
                        ]);
                    }
                }
            }

            foreach (array_values($datos['costos_generales'] ?? []) as $costoIndice => $datosCosto) {
                $ruta = "costos_generales.{$costoIndice}";
                $this->validarPartidaManual($datosCosto, $ruta);

                $cantidad = (float) $datosCosto['cantidad'];
                $precio = (float) $datosCosto['precio_unitario'];
                $monto = round($cantidad * $precio, 2);
                $subtotal += $monto;

                $revision->partidas()->create([
                    'revision_servicio_id' => null,
                    'catalogo_servicio_id' => null,
                    'clase' => $datosCosto['clase'],
                    'descripcion' => $datosCosto['descripcion'],
                    'cantidad' => $cantidad,
                    'unidad' => $datosCosto['unidad'],
                    'precio_unitario' => $precio,
                    'monto_neto' => $monto,
                    'metodo_precio' => $datosCosto['metodo_precio'],
                    'monto_sugerido' => (float) $datosCosto['monto_sugerido'],
                    'monto_final' => $precio,
                    'justificacion_ajuste' => $datosCosto['justificacion_ajuste'],
                    'orden' => $ordenPartida++,
                ]);
            }

            $iva = round($subtotal * ((float) $revision->iva_porcentaje / 100), 2);
            $revision->update([
                'subtotal' => $subtotal,
                'iva' => $iva,
                'total' => $subtotal + $iva,
            ]);
            $cotizacion->update(['revision_actual_id' => $revision->id]);

            return $cotizacion->load(
                'revisionActual.servicios.catalogoServicio',
                'revisionActual.partidas.valoresVariables',
                'cliente',
            );
        });
    }

    private function resolverServicioCotizado(
        int $clienteId,
        array $datosServicio,
        int $indice,
    ): array {
        $catalogo = null;
        if (! empty($datosServicio['catalogo_servicio_id'])) {
            $catalogo = CatalogoServicio::query()
                ->with('tipo.variablesPrecio.niveles')
                ->whereKey($datosServicio['catalogo_servicio_id'])
                ->where('activo', true)
                ->lockForUpdate()
                ->first();
        }

        $servicioOperativo = null;
        if (! empty($datosServicio['servicio_id'])) {
            $servicioOperativo = Servicio::query()
                ->whereKey($datosServicio['servicio_id'])
                ->where('cliente_id', $clienteId)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            if (! $servicioOperativo) {
                throw ValidationException::withMessages([
                    "servicios.{$indice}.servicio_id" => 'El servicio operativo seleccionado no pertenece al cliente indicado.',
                ]);
            }
        }

        if (! $catalogo && ! $servicioOperativo) {
            throw ValidationException::withMessages([
                "servicios.{$indice}.catalogo_servicio_id" => 'Selecciona un servicio del catálogo.',
            ]);
        }

        if ($servicioOperativo && $catalogo && $servicioOperativo->catalogo_servicio_id &&
            $servicioOperativo->catalogo_servicio_id !== $catalogo->id) {
            throw ValidationException::withMessages([
                "servicios.{$indice}.servicio_id" => 'El servicio operativo no corresponde al servicio del catálogo.',
            ]);
        }

        $catalogo ??= $servicioOperativo?->catalogoServicio()
            ->with('tipo.variablesPrecio.niveles')
            ->lockForUpdate()
            ->first();

        if (! $servicioOperativo && $catalogo) {
            $servicioOperativo = Servicio::query()
                ->where('cliente_id', $clienteId)
                ->where('catalogo_servicio_id', $catalogo->id)
                ->whereIn('estado', ['prospecto', 'planificado', 'en_curso', 'en_pausa'])
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();
        }

        return [$catalogo, $servicioOperativo];
    }

    private function resolverNormalizacion(
        ?CatalogoServicio $catalogo,
        array $partida,
        string $ruta,
    ): array {
        if (! $catalogo || $catalogo->precio_base === null ||
            ! UnidadPrecio::tryFrom((string) $catalogo->unidad_precio)) {
            throw ValidationException::withMessages([
                "{$ruta}.metodo_precio" => 'Este servicio no tiene una referencia de precio configurada.',
            ]);
        }

        $catalogo->loadMissing('tipo.variablesPrecio.niveles');
        $variables = $catalogo->tipo?->variablesPrecio
            ->filter(fn ($variable) => $variable->activo && (bool) $variable->pivot->requerida)
            ->values() ?? collect();
        $criterios = collect($partida['criterios'] ?? [])->filter(fn ($nivel) => filled($nivel));
        $esperadas = $variables->pluck('id')->map(fn ($id) => (string) $id)->sort()->values();
        $recibidas = $criterios->keys()->map(fn ($id) => (string) $id)->sort()->values();

        if ($esperadas->all() !== $recibidas->all()) {
            throw ValidationException::withMessages([
                "{$ruta}.criterios" => 'Selecciona exactamente un nivel para cada criterio requerido.',
            ]);
        }

        $selecciones = collect();
        foreach ($variables as $variable) {
            $nivelId = $criterios->get((string) $variable->id, $criterios->get($variable->id));
            $nivel = $variable->niveles->firstWhere('id', (int) $nivelId);

            if (! $nivel) {
                throw ValidationException::withMessages([
                    "{$ruta}.criterios.{$variable->id}" => "El nivel seleccionado no pertenece a {$variable->nombre}.",
                ]);
            }

            $selecciones->push([
                'variable' => $variable,
                'nivel' => $nivel,
                'factor' => $nivel->factor,
                'peso' => $variable->pivot->peso,
            ]);
        }

        return [
            'catalogo' => $catalogo,
            'selecciones' => $selecciones,
            'calculo' => $this->calculador->calcular(
                (float) $catalogo->precio_base,
                $selecciones,
            ),
        ];
    }

    private function validarPartidaManual(array $partida, string $ruta): void
    {
        if (! array_key_exists('monto_sugerido', $partida) ||
            $partida['monto_sugerido'] === null || $partida['monto_sugerido'] === '') {
            throw ValidationException::withMessages([
                "{$ruta}.monto_sugerido" => 'Ingresa el valor sugerido para el método manual.',
            ]);
        }

        if (blank($partida['justificacion_ajuste'] ?? null)) {
            throw ValidationException::withMessages([
                "{$ruta}.justificacion_ajuste" => 'Explica la fuente o construcción del precio manual.',
            ]);
        }

        if (blank($partida['unidad'] ?? null) ||
            ! UnidadPrecio::tryFrom((string) $partida['unidad'])) {
            throw ValidationException::withMessages([
                "{$ruta}.unidad" => 'Selecciona una unidad válida para la partida.',
            ]);
        }

        if (collect($partida['criterios'] ?? [])->filter(fn ($nivel) => filled($nivel))->isNotEmpty()) {
            throw ValidationException::withMessages([
                "{$ruta}.criterios" => 'Los métodos manuales no aceptan criterios normalizados.',
            ]);
        }
    }

    private function aCentavos(mixed $monto): int
    {
        return (int) round((float) $monto * 100);
    }
}
