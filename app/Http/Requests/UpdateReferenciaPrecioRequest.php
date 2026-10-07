<?php

namespace App\Http\Requests;

use App\Enums\UnidadPrecio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReferenciaPrecioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $unidadAnterior = $this->route('catalogoServicio')?->unidad_precio;
        $unidadAnteriorInvalida = filled($unidadAnterior) &&
            UnidadPrecio::tryFrom($unidadAnterior) === null;

        return [
            'descripcion' => ['nullable', 'string', 'max:5000'],
            'precio_base' => [
                'nullable',
                'numeric',
                'min:0',
                'max:9999999999999.99',
            ],
            'moneda_precio' => ['required', 'in:CLP,UF,USD'],
            'unidad_precio' => [
                'nullable',
                Rule::requiredIf(
                    fn (): bool => $this->filled('precio_base') ||
                        $unidadAnteriorInvalida,
                ),
                Rule::enum(UnidadPrecio::class),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'precio_base.min' => 'El precio base no puede ser negativo.',
            'moneda_precio.in' => 'La moneda debe ser CLP, UF o USD.',
            'unidad_precio.required' => 'Selecciona una unidad válida para el precio.',
            'unidad_precio.enum' => 'La unidad seleccionada no pertenece al catálogo permitido.',
        ];
    }
}
