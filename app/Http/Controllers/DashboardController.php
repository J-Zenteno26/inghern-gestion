<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\Servicio;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('dashboard', [
            'metricas' => [
                [
                    'etiqueta' => 'Servicios activos',
                    'valor' => Servicio::whereIn('estado', [
                        'planificado',
                        'en_curso',
                    ])->count(),
                    'detalle' => 'Operación en seguimiento',
                    'tono' => 'azul',
                ],
                [
                    'etiqueta' => 'Clientes',
                    'valor' => Cliente::where('estado', 'activo')->count(),
                    'detalle' => 'Relaciones vigentes',
                    'tono' => 'petroleo',
                ],
                [
                    'etiqueta' => 'Cotizaciones abiertas',
                    'valor' => Cotizacion::whereIn('estado', [
                        'borrador',
                        'enviada',
                        'en_revision',
                    ])->count(),
                    'detalle' => 'Pendientes de resolución',
                    'tono' => 'naranjo',
                ],
            ],
            'servicios' => Servicio::with(['cliente', 'tipo'])
                ->latest()
                ->limit(5)
                ->get(),
            'cotizaciones' => Cotizacion::with(['cliente', 'revisionActual'])
                ->latest()
                ->limit(5)
                ->get(),
        ]);
    }
}
