<?php

namespace App\Http\Requests;

use App\Models\Planta;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateCotizacionPlantaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'planta_id' => ['required', 'integer', 'exists:plantas,id'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $cotizacion = $this->route('cotizacion');
            $pertenece = Planta::query()
                ->whereKey($this->integer('planta_id'))
                ->where('cliente_id', $cotizacion->cliente_id)
                ->exists();

            if (! $pertenece) {
                $validator->errors()->add(
                    'planta_id',
                    'La planta seleccionada no pertenece a la organización de la cotización.',
                );
            }
        }];
    }
}
