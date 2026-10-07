<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'razon_social' => ['required', 'string', 'max:180'],
            'nombre_fantasia' => ['nullable', 'string', 'max:180'],
            'identificador_tributario' => [
                'required',
                'string',
                'max:30',
                'unique:clientes,identificador_tributario',
            ],
            'email_facturacion' => ['nullable', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:40'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'comuna' => ['nullable', 'string', 'max:100'],
            'ciudad' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'contacto_nombre' => ['nullable', 'string', 'max:150'],
            'contacto_email' => ['nullable', 'email', 'max:255'],
            'contacto_telefono' => ['nullable', 'string', 'max:40'],
            'planta_nombre' => ['nullable', 'string', 'max:150'],
        ];
    }
}
