<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnularPagoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'max:1000'],
            'confirmacion' => ['required', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo.required' => 'Debes indicar el motivo de la anulación.',
            'confirmacion.required' => 'Debes confirmar explícitamente la anulación del pago.',
            'confirmacion.accepted' => 'Debes confirmar explícitamente la anulación del pago.',
        ];
    }

    public function attributes(): array
    {
        return [
            'motivo' => 'motivo de anulación',
            'confirmacion' => 'confirmación de anulación',
        ];
    }
}
