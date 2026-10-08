<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\Documento;
use App\Models\Planta;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlantaController extends Controller
{
    public function index(Request $request): View
    {
        $buscar = trim((string) $request->query('buscar'));
        $clienteId = $request->integer('cliente');

        $plantas = Planta::query()
            ->with('cliente')
            ->withCount([
                'servicios as servicios_activos_count' => fn ($query) => $query->whereIn(
                    'servicios.estado',
                    ['prospecto', 'planificado', 'en_curso', 'en_pausa', 'activo'],
                ),
                'cotizaciones',
            ])
            ->when($clienteId, fn ($query) => $query->where('cliente_id', $clienteId))
            ->when($buscar, fn ($query) => $query->where(function ($filtro) use ($buscar): void {
                $termino = '%'.mb_strtolower($buscar).'%';
                $filtro
                    ->whereRaw('LOWER(nombre) LIKE ?', [$termino])
                    ->orWhereRaw('LOWER(COALESCE(direccion, \'\')) LIKE ?', [$termino])
                    ->orWhereRaw('LOWER(COALESCE(comuna, \'\')) LIKE ?', [$termino])
                    ->orWhereRaw('LOWER(COALESCE(ciudad, \'\')) LIKE ?', [$termino])
                    ->orWhereRaw('LOWER(COALESCE(region, \'\')) LIKE ?', [$termino]);
            }))
            ->orderBy('nombre')
            ->paginate(12)
            ->withQueryString();

        $plantas->getCollection()->each(function (Planta $planta): void {
            $planta->setAttribute(
                'documentos_relacionados_count',
                Documento::query()->dePlanta($planta)->count(),
            );
        });

        return view('plantas.index', [
            'plantas' => $plantas,
            'clientes' => Cliente::query()->orderBy('razon_social')->get(),
            'buscar' => $buscar,
            'clienteId' => $clienteId,
        ]);
    }

    public function show(Planta $planta): View
    {
        $planta->load('cliente');

        $servicios = $planta->servicios()
            ->with(['cliente', 'tipo', 'catalogoServicio'])
            ->orderByRaw("CASE WHEN servicios.estado IN ('en_curso', 'activo', 'planificado') THEN 0 ELSE 1 END")
            ->latest('servicios.updated_at')
            ->limit(5)
            ->get();
        $cotizaciones = $planta->cotizaciones()
            ->with('revisionActual')
            ->latest()
            ->limit(5)
            ->get();
        $documentos = Documento::query()
            ->dePlanta($planta)
            ->latest()
            ->limit(3)
            ->get();

        return view('plantas.show', [
            'planta' => $planta,
            'servicios' => $servicios,
            'cotizaciones' => $cotizaciones,
            'documentos' => $documentos,
            'serviciosActivos' => $planta->servicios()
                ->whereIn('servicios.estado', ['prospecto', 'planificado', 'en_curso', 'en_pausa', 'activo'])
                ->count(),
            'totalCotizaciones' => $planta->cotizaciones()->count(),
            'totalDocumentos' => Documento::query()->dePlanta($planta)->count(),
            'montoAceptado' => Cotizacion::query()
                ->whereBelongsTo($planta)
                ->where('estado', 'aceptada')
                ->whereHas('revisionActual')
                ->with('revisionActual:id,total')
                ->get()
                ->sum(fn (Cotizacion $cotizacion) => (float) $cotizacion->revisionActual?->total),
        ]);
    }
}
