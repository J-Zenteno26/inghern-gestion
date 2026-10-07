<?php

namespace App\Http\Requests;

use App\Models\Factura;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePagoRequest extends FormRequest
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
            'fecha_pago' => ['required', 'date_format:Y-m-d'],
            'monto_total' => ['required', 'numeric', 'gt:0', 'max:9999999999999.99'],
            'medio_pago' => [
                'required',
                Rule::in(['transferencia', 'deposito', 'cheque', 'efectivo', 'otro']),
            ],
            'referencia' => ['nullable', 'string', 'max:120'],
            'observacion' => ['nullable', 'string', 'max:5000'],
            'asignaciones' => ['required', 'array', 'list', 'min:1', 'max:2'],
            'asignaciones.*' => ['required', 'array:factura_id,monto_asignado'],
            'asignaciones.*.factura_id' => [
                'required',
                'integer',
                'distinct:strict',
                Rule::exists('facturas', 'id')->where(
                    fn (Builder $query) => $query->where('estado', '!=', 'anulada'),
                ),
            ],
            'asignaciones.*.monto_asignado' => [
                'required',
                'numeric',
                'gt:0',
                'max:9999999999999.99',
            ],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $asignaciones = $this->input('asignaciones', []);
                $montoTotal = round((float) $this->input('monto_total'), 2);
                $montoDistribuido = round(
                    (float) collect($asignaciones)->sum('monto_asignado'),
                    2,
                );

                if (abs($montoTotal - $montoDistribuido) > 0.009) {
                    $validator->errors()->add(
                        'asignaciones',
                        'La suma de los montos asignados debe coincidir con el monto total del pago.',
                    );
                }

                $monedas = Factura::query()
                    ->whereKey(collect($asignaciones)->pluck('factura_id'))
                    ->pluck('moneda')
                    ->unique();

                if ($monedas->count() > 1) {
                    $validator->errors()->add(
                        'asignaciones',
                        'Las facturas seleccionadas deben utilizar la misma moneda.',
                    );
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'asignaciones.max' => 'Un pago puede distribuirse entre un máximo de 2 facturas.',
            'asignaciones.*.factura_id.distinct' => 'No puedes seleccionar la misma factura más de una vez.',
        ];
    }

    public function attributes(): array
    {
        return [
            'fecha_pago' => 'fecha de pago',
            'monto_total' => 'monto total',
            'medio_pago' => 'medio de pago',
            'asignaciones' => 'asignaciones',
            'asignaciones.*.factura_id' => 'factura',
            'asignaciones.*.monto_asignado' => 'monto asignado',
        ];
    }
}
