<?php

namespace App\Http\Requests;

use App\Domain\Cotizaciones\CalculadorPrecioNormalizado;
use App\Enums\UnidadPrecio;
use App\Models\CatalogoServicio;
use App\Models\Planta;
use App\Models\Servicio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCotizacionRequest extends FormRequest
{
    private bool $legacyPayload = false;

    protected function prepareForValidation(): void
    {
        if (! $this->has('servicios') && $this->has('partidas')) {
            $this->legacyPayload = true;
            $servicio = $this->input('servicio_id')
                ? Servicio::query()->find($this->input('servicio_id'))
                : null;

            $this->merge([
                'servicios' => [[
                    'tipo_servicio_id' => $servicio?->tipo_servicio_id,
                    'catalogo_servicio_id' => $servicio?->catalogo_servicio_id,
                    'servicio_id' => $this->input('servicio_id'),
                    'partidas' => $this->input('partidas', []),
                ]],
            ]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cliente_id' => ['required', 'exists:clientes,id'],
            'planta_id' => ['required', 'integer', 'exists:plantas,id'],
            'contacto_id' => ['nullable', 'exists:contactos,id'],
            'titulo' => ['required', 'string', 'max:200'],
            'moneda' => ['required', 'in:CLP,USD,UF'],
            'iva_porcentaje' => ['required', 'numeric', 'min:0', 'max:100'],
            'servicios' => ['required', 'array', 'min:1'],
            'servicios.*.tipo_servicio_id' => ['nullable', 'integer', 'exists:tipo_servicios,id'],
            'servicios.*.catalogo_servicio_id' => [
                'nullable',
                'integer',
                'distinct',
                Rule::exists('catalogo_servicios', 'id')->where(fn ($query) => $query->where('activo', true)),
            ],
            'servicios.*.servicio_id' => ['nullable', 'integer', 'exists:servicios,id'],
            'servicios.*.partidas' => ['required', 'array', 'min:1'],
            'servicios.*.partidas.*.descripcion' => ['required', 'string', 'max:1000'],
            'servicios.*.partidas.*.clase' => ['required', 'in:servicio,traslado,costo_adicional'],
            'servicios.*.partidas.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'servicios.*.partidas.*.unidad' => ['nullable', Rule::enum(UnidadPrecio::class)],
            'servicios.*.partidas.*.precio_unitario' => ['required', 'numeric', 'min:0'],
            'servicios.*.partidas.*.metodo_precio' => [
                'required',
                'in:a_criterio,referencia,hora_hombre,costo_margen,normalizado',
            ],
            'servicios.*.partidas.*.monto_sugerido' => ['nullable', 'numeric', 'min:0'],
            'servicios.*.partidas.*.justificacion_ajuste' => ['nullable', 'string', 'max:1000'],
            'servicios.*.partidas.*.criterios' => ['nullable', 'array'],
            'servicios.*.partidas.*.criterios.*' => [
                'nullable',
                'integer',
                'exists:niveles_variable_precio,id',
            ],
            'costos_generales' => ['nullable', 'array'],
            'costos_generales.*.descripcion' => ['required', 'string', 'max:1000'],
            'costos_generales.*.clase' => ['required', 'in:traslado,costo_adicional'],
            'costos_generales.*.cantidad' => ['required', 'numeric', 'in:1'],
            'costos_generales.*.unidad' => ['required', Rule::in([UnidadPrecio::Servicio->value])],
            'costos_generales.*.precio_unitario' => ['required', 'numeric', 'min:0'],
            'costos_generales.*.metodo_precio' => ['required', 'in:a_criterio'],
            'costos_generales.*.monto_sugerido' => ['required', 'numeric', 'min:0'],
            'costos_generales.*.justificacion_ajuste' => ['required', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $clienteId = (int) $this->input('cliente_id');

            $plantaValida = Planta::query()
                ->whereKey($this->integer('planta_id'))
                ->where('cliente_id', $clienteId)
                ->exists();

            if (! $plantaValida) {
                $validator->errors()->add(
                    'planta_id',
                    'La planta seleccionada no pertenece a la organización indicada.',
                );
            }

            foreach ($this->input('servicios', []) as $servicioIndice => $datosServicio) {
                $rutaServicio = "servicios.{$servicioIndice}";
                $servicioOperativo = null;
                $catalogo = null;

                if (! empty($datosServicio['servicio_id'])) {
                    $servicioOperativo = Servicio::query()
                        ->whereKey($datosServicio['servicio_id'])
                        ->where('cliente_id', $clienteId)
                        ->whereNull('deleted_at')
                        ->first();

                    if (! $servicioOperativo) {
                        $validator->errors()->add(
                            "{$rutaServicio}.servicio_id",
                            'El servicio operativo seleccionado no pertenece al cliente indicado.',
                        );

                        continue;
                    }
                }

                if (! empty($datosServicio['catalogo_servicio_id'])) {
                    $catalogo = CatalogoServicio::with('tipo.variablesPrecio.niveles')
                        ->whereKey($datosServicio['catalogo_servicio_id'])
                        ->where('activo', true)
                        ->first();
                }

                if (! $catalogo && ! $servicioOperativo) {
                    $validator->errors()->add(
                        "{$rutaServicio}.catalogo_servicio_id",
                        'Selecciona un servicio del catálogo.',
                    );

                    continue;
                }

                if ($servicioOperativo && $catalogo && $servicioOperativo->catalogo_servicio_id &&
                    $servicioOperativo->catalogo_servicio_id !== $catalogo->id) {
                    $validator->errors()->add(
                        "{$rutaServicio}.servicio_id",
                        'El servicio operativo no corresponde al servicio seleccionado del catálogo.',
                    );

                    continue;
                }

                $catalogo ??= $servicioOperativo?->catalogoServicio()
                    ->with('tipo.variablesPrecio.niveles')
                    ->first();

                foreach ($datosServicio['partidas'] ?? [] as $partidaIndice => $partida) {
                    $ruta = "{$rutaServicio}.partidas.{$partidaIndice}";
                    $metodo = $partida['metodo_precio'] ?? null;

                    if ($metodo === 'normalizado') {
                        $this->validarPartidaNormalizada($validator, $catalogo, $partida, $ruta);

                        continue;
                    }

                    if ($metodo) {
                        if (! array_key_exists('monto_sugerido', $partida) ||
                            $partida['monto_sugerido'] === null || $partida['monto_sugerido'] === '') {
                            $validator->errors()->add(
                                "{$ruta}.monto_sugerido",
                                'Ingresa el valor sugerido para el método manual.',
                            );
                        }

                        if (collect($partida['criterios'] ?? [])->filter(fn ($nivel) => filled($nivel))->isNotEmpty()) {
                            $validator->errors()->add(
                                "{$ruta}.criterios",
                                'Los métodos manuales no aceptan criterios normalizados.',
                            );
                        }

                        if (blank($partida['justificacion_ajuste'] ?? null)) {
                            $validator->errors()->add(
                                "{$ruta}.justificacion_ajuste",
                                'Explica la fuente o construcción del precio manual.',
                            );
                        }

                        if (blank($partida['unidad'] ?? null)) {
                            $validator->errors()->add(
                                "{$ruta}.unidad",
                                'Selecciona una unidad para la partida.',
                            );
                        }
                    }
                }
            }

            if ($this->legacyPayload) {
                foreach ($validator->errors()->messages() as $campo => $mensajes) {
                    $alias = null;
                    if ($campo === 'servicios.0.servicio_id') {
                        $alias = 'servicio_id';
                    } elseif (str_starts_with($campo, 'servicios.0.partidas.')) {
                        $alias = substr($campo, strlen('servicios.0.'));
                    }

                    if ($alias) {
                        foreach ($mensajes as $mensaje) {
                            $validator->errors()->add($alias, $mensaje);
                        }
                    }
                }
            }
        });
    }

    private function validarPartidaNormalizada(
        Validator $validator,
        ?CatalogoServicio $catalogo,
        array $partida,
        string $ruta,
    ): void {
        if (! $catalogo || $catalogo->precio_base === null ||
            ! UnidadPrecio::tryFrom((string) $catalogo->unidad_precio)) {
            $validator->errors()->add(
                "{$ruta}.metodo_precio",
                'Este servicio no tiene una referencia de precio configurada.',
            );

            return;
        }

        $variables = $catalogo->tipo?->variablesPrecio
            ->filter(fn ($variable) => $variable->activo && (bool) $variable->pivot->requerida)
            ->values() ?? collect();
        $criterios = collect($partida['criterios'] ?? [])->filter(fn ($nivel) => filled($nivel));
        $esperadas = $variables->pluck('id')->map(fn ($id) => (string) $id)->sort()->values();
        $recibidas = $criterios->keys()->map(fn ($id) => (string) $id)->sort()->values();

        if ($esperadas->all() !== $recibidas->all()) {
            $validator->errors()->add(
                "{$ruta}.criterios",
                'Selecciona exactamente un nivel para cada criterio requerido.',
            );

            return;
        }

        $selecciones = collect();
        foreach ($variables as $variable) {
            $nivelId = $criterios->get((string) $variable->id, $criterios->get($variable->id));
            $nivel = $variable->niveles->firstWhere('id', (int) $nivelId);

            if (! $nivel) {
                $validator->errors()->add(
                    "{$ruta}.criterios.{$variable->id}",
                    "El nivel seleccionado no pertenece a {$variable->nombre}.",
                );

                return;
            }

            $selecciones->push([
                'factor' => $nivel->factor,
                'peso' => $variable->pivot->peso,
            ]);
        }

        $calculo = app(CalculadorPrecioNormalizado::class)->calcular(
            (float) $catalogo->precio_base,
            $selecciones,
        );

        if ($this->aCentavos($partida['precio_unitario'] ?? 0) !==
            $this->aCentavos($calculo['monto_sugerido']) &&
            blank($partida['justificacion_ajuste'] ?? null)) {
            $validator->errors()->add(
                "{$ruta}.justificacion_ajuste",
                'Justifica por qué el precio final difiere del valor sugerido.',
            );
        }
    }

    private function aCentavos(mixed $monto): int
    {
        return (int) round((float) $monto * 100);
    }
}
