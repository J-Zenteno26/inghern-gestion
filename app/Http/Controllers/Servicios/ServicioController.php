<?php

namespace App\Http\Controllers\Servicios;

use App\Domain\Shared\GeneraCodigo;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreServicioRequest;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\Planta;
use App\Models\Servicio;
use App\Models\TipoServicio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ServicioController extends Controller
{
    public function index(Request $request): View
    {
        $buscar = trim((string) $request->query('buscar'));
        $estado = $request->query('estado');
        $clienteId = $request->integer('cliente');
        $plantaId = $request->integer('planta');
        $servicios = Servicio::with([
            'cliente',
            'tipo',
            'catalogoServicio',
            'plantas',
        ])
            ->when(
                $buscar,
                fn ($q) => $q->where(
                    fn ($s) => $s
                        ->where('codigo', 'ilike', "%{$buscar}%")
                        ->orWhere('nombre', 'ilike', "%{$buscar}%")
                        ->orWhereHas(
                            'cliente',
                            fn ($c) => $c->where(
                                'razon_social',
                                'ilike',
                                "%{$buscar}%",
                            ),
                        ),
                ),
            )
            ->when($estado, fn ($q) => $q->where('estado', $estado))
            ->when($clienteId, fn ($q) => $q->where('cliente_id', $clienteId))
            ->when(
                $plantaId,
                fn ($q) => $q->whereHas('plantas', fn ($plantas) => $plantas->whereKey($plantaId)),
            )
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view(
            'servicios.index',
            compact('servicios', 'buscar', 'estado', 'clienteId', 'plantaId') + [
                'clientesFiltro' => Cliente::query()->orderBy('razon_social')->get(),
                'plantasFiltro' => Planta::query()
                    ->orderBy('nombre')
                    ->get(),
            ],
        );
    }

    public function create(Request $request): View
    {
        return view('servicios.create', [
            'clientes' => Cliente::with('plantas')
                ->orderBy('razon_social')
                ->get(),
            'tipos' => TipoServicio::with([
                'catalogoServicios' => fn ($q) => $q->where('activo', true),
            ])
                ->where('activo', true)
                ->orderBy('nombre')
                ->get(),
            'clienteSeleccionado' => $request->integer('cliente'),
        ]);
    }

    public function store(
        StoreServicioRequest $request,
        GeneraCodigo $codigos,
    ): RedirectResponse {
        $servicio = DB::transaction(function () use ($request, $codigos) {
            $data = $request->validated();
            $plantas = Arr::pull($data, 'plantas', []);
            $seleccion = Arr::pull($data, 'servicio_catalogo_seleccion');
            $data['catalogo_servicio_id'] =
                $seleccion === 'otro' ? null : (int) $seleccion;

            if ($seleccion !== 'otro') {
                $data['servicio_otro'] = null;
            }

            $servicio = Servicio::create(
                $data + [
                    'codigo' => $codigos->siguiente(Servicio::class, 'SER'),
                    'creado_por' => $request->user()->id,
                ],
            );
            $servicio->plantas()->sync($plantas);

            return $servicio;
        });

        return to_route('servicios.show', $servicio)->with(
            'exito',
            'Servicio creado y listo para seguimiento.',
        );
    }

    public function show(Servicio $servicio): View
    {
        $servicio->load([
            'cliente',
            'tipo',
            'catalogoServicio',
            'plantas',
        ]);
        $cotizacionesVinculadas = Cotizacion::query()
            ->with('revisionActual')
            ->whereHas(
                'revisiones.servicios',
                fn ($query) => $query->where('servicio_id', $servicio->id),
            )
            ->latest()
            ->get();

        return view(
            'servicios.show',
            compact('servicio', 'cotizacionesVinculadas'),
        );
    }
}
