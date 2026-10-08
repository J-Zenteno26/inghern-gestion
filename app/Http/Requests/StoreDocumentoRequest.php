<?php

namespace App\Http\Requests;

use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\Documento;
use App\Models\DocumentoVinculo;
use App\Models\RevisionServicio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;

class StoreDocumentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $extensionesPermitidas = config(
            'filesystems.document_uploads.allowed_extensions',
        );

        return [
            'archivo' => [
                'required',
                File::types($extensionesPermitidas)->max(
                    config('filesystems.document_uploads.max_size_kilobytes'),
                ),
                'extensions:'.implode(',', $extensionesPermitidas),
            ],
            'nombre' => [
                Rule::requiredIf($this->routeIs('biblioteca.documentos.store')),
                'nullable',
                'string',
                'max:180',
            ],
            'tipo_documento' => [
                'required',
                'string',
                'max:60',
                Rule::in(array_keys(Documento::TIPOS)),
            ],
            'descripcion' => ['nullable', 'string', 'max:10000'],
            'cliente_id' => [
                Rule::requiredIf($this->routeIs('biblioteca.documentos.store')),
                'nullable',
                'integer',
                'exists:clientes,id',
            ],
            'vinculable_type' => [
                'nullable',
                'required_with:vinculable_id',
                Rule::in(array_keys(DocumentoVinculo::TIPOS)),
            ],
            'vinculable_id' => ['nullable', 'required_with:vinculable_type', 'integer'],
            'cotizacion_id' => [
                'nullable',
                'integer',
                'required_with:revision_servicio_id',
                'exists:cotizaciones,id',
            ],
            'revision_servicio_id' => [
                'nullable',
                'integer',
                'exists:revision_servicios,id',
            ],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $clienteId = $this->route('cliente')?->getKey() ?? $this->integer('cliente_id');

            if ($this->filled('cotizacion_id')) {
                $cotizacion = Cotizacion::find($this->integer('cotizacion_id'));

                if ($cotizacion === null || (int) $cotizacion->cliente_id !== (int) $clienteId) {
                    $validator->errors()->add(
                        'cotizacion_id',
                        'La cotización seleccionada no pertenece a la organización indicada.',
                    );

                    return;
                }

                if ($this->filled('revision_servicio_id')) {
                    $revisionServicio = RevisionServicio::query()
                        ->whereKey($this->integer('revision_servicio_id'))
                        ->whereHas(
                            'revision',
                            fn ($revisiones) => $revisiones->where('cotizacion_id', $cotizacion->getKey()),
                        )
                        ->first();

                    if ($revisionServicio === null) {
                        $validator->errors()->add(
                            'revision_servicio_id',
                            'El servicio cotizado no pertenece a la cotización indicada.',
                        );
                    }
                }
            }

            if (! $this->filled('vinculable_type') || ! $this->filled('vinculable_id')) {
                return;
            }

            $clase = DocumentoVinculo::TIPOS[$this->string('vinculable_type')->toString()] ?? null;
            $vinculable = $clase !== null
                ? $clase::find($this->integer('vinculable_id'))
                : null;
            if ($vinculable === null || $this->clienteId($vinculable) !== $clienteId) {
                $validator->errors()->add(
                    'vinculable_id',
                    'La entidad seleccionada no pertenece a la organización indicada.',
                );
            }
        }];
    }

    public function attributes(): array
    {
        return [
            'archivo' => 'archivo',
            'nombre' => 'nombre del documento',
            'tipo_documento' => 'tipo de documento',
            'descripcion' => 'descripción',
            'cliente_id' => 'organización',
            'vinculable_type' => 'tipo de vínculo',
            'vinculable_id' => 'entidad vinculada',
            'cotizacion_id' => 'cotización',
            'revision_servicio_id' => 'servicio cotizado',
        ];
    }

    private function clienteId(object $vinculable): int
    {
        if ($vinculable instanceof Cliente) {
            return (int) $vinculable->getKey();
        }

        if ($vinculable instanceof RevisionServicio) {
            return (int) $vinculable->revision()
                ->firstOrFail()
                ->cotizacion()
                ->value('cliente_id');
        }

        return (int) $vinculable->cliente_id;
    }
}
