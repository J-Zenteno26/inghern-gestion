<?php

namespace App\Http\Requests;

use App\Models\Cliente;
use App\Models\DocumentoVinculo;
use App\Models\RevisionServicio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDocumentoVinculoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $cliente = $this->route('cliente');
        $documento = $this->route('documento');

        abort_unless(
            $cliente instanceof Cliente
                && $documento !== null
                && (int) $documento->cliente_id === (int) $cliente->getKey(),
            404,
        );

        return true;
    }

    public function rules(): array
    {
        return [
            'vinculable_type' => [
                'required',
                Rule::in(array_keys(DocumentoVinculo::TIPOS)),
            ],
            'vinculable_id' => ['required', 'integer'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $clase = DocumentoVinculo::TIPOS[$this->string('vinculable_type')->toString()];
            $vinculable = $clase::find($this->integer('vinculable_id'));
            $cliente = $this->route('cliente');
            $clienteId = match (true) {
                $vinculable instanceof Cliente => $vinculable->getKey(),
                $vinculable instanceof RevisionServicio => $vinculable->revision()
                    ->firstOrFail()
                    ->cotizacion()
                    ->value('cliente_id'),
                default => $vinculable?->cliente_id,
            };

            if ($vinculable === null || (int) $clienteId !== (int) $cliente->getKey()) {
                $validator->errors()->add(
                    'vinculable_id',
                    'La entidad seleccionada no pertenece a esta organización.',
                );
            }
        }];
    }
}
