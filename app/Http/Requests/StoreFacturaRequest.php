<?php

namespace App\Http\Requests;

use App\Models\OrdenCompra;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFacturaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $ordenCompra = OrdenCompra::find($this->integer('orden_compra_id'));

        return [
            'orden_compra_id' => [
                'required',
                'integer',
                Rule::exists('ordenes_compra', 'id')->where(
                    fn (Builder $query) => $query->where('estado', 'registrada'),
                ),
            ],
            'folio' => [
                'required',
                'string',
                'max:100',
                Rule::unique('facturas', 'folio')->where(
                    fn (Builder $query) => $query->where(
                        'cliente_id',
                        $ordenCompra instanceof OrdenCompra ? $ordenCompra->cliente_id : null,
                    ),
                ),
            ],
            'fecha_emision' => ['required', 'date'],
            'monto_neto' => ['required', 'numeric', 'gt:0', 'max:9999999999999.99'],
            'observacion' => ['nullable', 'string', 'max:5000'],
            'condicion_pago' => ['required', Rule::in(['credito', 'contado'])],
            'dias_pago' => [
                'exclude_unless:condicion_pago,credito',
                'required',
                'integer',
                'min:1',
                'max:3650',
            ],
        ];
    }
}
