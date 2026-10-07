<?php

namespace App\Http\Controllers\Servicios;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateReferenciaPrecioRequest;
use App\Models\CatalogoServicio;
use App\Models\TipoServicio;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ReferenciaPrecioController extends Controller
{
    public function index(): View
    {
        $tipos = TipoServicio::query()
            ->with('catalogoServicios')
            ->orderBy('nombre')
            ->get();

        return view('servicios.referencias-precio.index', compact('tipos'));
    }

    public function update(
        UpdateReferenciaPrecioRequest $request,
        CatalogoServicio $catalogoServicio,
    ): RedirectResponse {
        $catalogoServicio->update($request->validated());

        return redirect(
            route('servicios.referencias-precio.index').
                '#catalogo-'.$catalogoServicio->id,
        )->with('exito', 'Referencia de precio actualizada.');
    }
}
