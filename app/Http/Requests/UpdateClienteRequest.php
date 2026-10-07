<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateClienteRequest extends StoreClienteRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['identificador_tributario'] = [
            'required',
            'string',
            'max:30',
            Rule::unique('clientes')->ignore($this->route('cliente')),
        ];

        return $rules;
    }
}
