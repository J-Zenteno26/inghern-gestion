<?php

namespace App\Http\Controllers\Clientes;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClienteRequest;
use App\Http\Requests\UpdateClienteRequest;
use App\Models\Cliente;
use App\Models\Documento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ClienteController extends Controller
{
    public function index(Request $request): View
    {
        $buscar = trim((string) $request->query('buscar'));
        $clientes = Cliente::query()
            ->withCount([
                'contactos',
                'plantas',
                'cotizaciones',
                'documentosPropios',
                'servicios as servicios_activos_count' => fn ($query) => $query->whereIn(
                    'estado',
                    ['prospecto', 'planificado', 'en_curso', 'en_pausa', 'activo'],
                ),
            ])
            ->when(
                $buscar,
                fn ($q) => $q->where(
                    fn ($sub) => $sub
                        ->where('razon_social', 'ilike', "%{$buscar}%")
                        ->orWhere('nombre_fantasia', 'ilike', "%{$buscar}%")
                        ->orWhere(
                            'identificador_tributario',
                            'ilike',
                            "%{$buscar}%",
                        ),
                ),
            )
            ->orderBy('razon_social')
            ->paginate(15)
            ->withQueryString();

        return view('clientes.index', compact('clientes', 'buscar'));
    }

    public function create(): View
    {
        return view('clientes.create');
    }

    public function store(StoreClienteRequest $request): RedirectResponse
    {
        $cliente = DB::transaction(function () use ($request) {
            $data = $request->validated();
            $cliente = Cliente::create(
                Arr::except($data, [
                    'contacto_nombre',
                    'contacto_email',
                    'contacto_telefono',
                    'planta_nombre',
                ]),
            );
            if (! empty($data['contacto_nombre'])) {
                $cliente
                    ->contactos()
                    ->create([
                        'nombre' => $data['contacto_nombre'],
                        'email' => $data['contacto_email'] ?? null,
                        'telefono' => $data['contacto_telefono'] ?? null,
                        'es_principal' => true,
                    ]);
            }
            if (! empty($data['planta_nombre'])) {
                $cliente
                    ->plantas()
                    ->create(['nombre' => $data['planta_nombre']]);
            }

            return $cliente;
        });

        return to_route('clientes.show', $cliente)->with(
            'exito',
            'Cliente creado correctamente.',
        );
    }

    public function show(Cliente $cliente): View
    {
        $cliente->load([
            'contactos',
            'plantas.servicios',
            'plantas.cotizaciones',
            'servicios.tipo',
            'servicios.plantas',
            'cotizaciones.revisionActual',
        ]);

        $cliente->plantas->each(function ($planta): void {
            $planta->setAttribute(
                'documentos_relacionados_count',
                Documento::query()->dePlanta($planta)->count(),
            );
        });

        return view('clientes.show', compact('cliente'));
    }

    public function edit(Cliente $cliente): View
    {
        return view('clientes.edit', compact('cliente'));
    }

    public function update(
        UpdateClienteRequest $request,
        Cliente $cliente,
    ): RedirectResponse {
        $cliente->update(
            Arr::except($request->validated(), [
                'contacto_nombre',
                'contacto_email',
                'contacto_telefono',
                'planta_nombre',
            ]),
        );

        return to_route('clientes.show', $cliente)->with(
            'exito',
            'Datos del cliente actualizados.',
        );
    }
}
