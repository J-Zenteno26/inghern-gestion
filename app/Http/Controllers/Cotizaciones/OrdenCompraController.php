<?php

namespace App\Http\Controllers\Cotizaciones;

use App\Domain\Cotizaciones\RegistrarOrdenCompra;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrdenCompraRequest;
use App\Models\Cotizacion;
use Illuminate\Http\RedirectResponse;

class OrdenCompraController extends Controller
{
    public function store(
        StoreOrdenCompraRequest $request,
        Cotizacion $cotizacion,
        RegistrarOrdenCompra $registrar,
    ): RedirectResponse {
        $registrar->ejecutar(
            $cotizacion,
            $request->validated(),
            $request->user()->id,
        );

        return to_route('cotizaciones.show', $cotizacion)->with(
            'exito',
            'Orden de compra registrada correctamente.',
        );
    }
}
