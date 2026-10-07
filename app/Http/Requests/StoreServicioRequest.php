<?php

namespace App\Http\Requests;

use App\Models\CatalogoServicio;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StoreServicioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cliente_id' => ['required', 'exists:clientes,id'],
            'tipo_servicio_id' => ['required', 'exists:tipo_servicios,id'],
            'servicio_catalogo_seleccion' => [
                'required',
                function (
                    string $attribute,
                    mixed $value,
                    Closure $fail,
                ): void {
                    if ($value === 'otro') {
                        return;
                    }

                    if (! ctype_digit((string) $value)) {
                        $fail('Selecciona un servicio válido.');

                        return;
                    }

                    $existe = CatalogoServicio::query()
                        ->whereKey((int) $value)
                        ->where(
                            'tipo_servicio_id',
                            $this->integer('tipo_servicio_id'),
                        )
                        ->where('activo', true)
                        ->exists();

                    if (! $existe) {
                        $fail(
                            'El servicio seleccionado no corresponde al tipo indicado.',
                        );
                    }
                },
            ],
            'servicio_otro' => [
                'nullable',
                'required_if:servicio_catalogo_seleccion,otro',
                'string',
                'max:180',
            ],
            'nombre' => ['required', 'string', 'max:180'],
            'descripcion' => ['nullable', 'string', 'max:5000'],
            'estado' => [
                'required',
                'in:prospecto,planificado,en_curso,en_pausa,finalizado,cancelado',
            ],
            'fecha_inicio_estimada' => ['nullable', 'date'],
            'fecha_termino_estimada' => [
                'nullable',
                'date',
                'after_or_equal:fecha_inicio_estimada',
            ],
            'plantas' => ['array'],
            'plantas.*' => ['integer', 'exists:plantas,id'],
        ];
    }
}
