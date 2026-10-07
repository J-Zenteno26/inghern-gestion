<?php

namespace Database\Seeders;

use App\Models\TipoServicio;
use Illuminate\Database\Seeder;

class TipoServicioSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = [
            [
                'codigo' => 'PID',
                'nombre' => 'P&ID y documentación de procesos',
                'familia' => 'Ingeniería de procesos',
                'descripcion' => 'Levantamiento, revisión, actualización y normalización de documentación P&ID.',
            ],
            [
                'codigo' => 'ACT-CRIT',
                'nombre' => 'Activos críticos',
                'familia' => 'Integridad operacional',
                'descripcion' => 'Identificación, clasificación y documentación de activos críticos.',
            ],
            [
                'codigo' => 'LEV-TER',
                'nombre' => 'Levantamiento en terreno',
                'familia' => 'Ingeniería de terreno',
                'descripcion' => 'Captura y validación de información técnica directamente en planta.',
            ],
            [
                'codigo' => 'SEG-PROC',
                'nombre' => 'Seguridad de procesos',
                'familia' => 'Integridad operacional',
                'descripcion' => 'Apoyo técnico para gestión de riesgos y seguridad de procesos.',
            ],
            [
                'codigo' => 'LAYOUT',
                'nombre' => 'Layouts industriales',
                'familia' => 'Diseño industrial',
                'descripcion' => 'Desarrollo y actualización de disposiciones de equipos e instalaciones.',
            ],
            [
                'codigo' => 'MOD-3D',
                'nombre' => 'Modelado 3D',
                'familia' => 'Diseño industrial',
                'descripcion' => 'Modelado tridimensional de instalaciones y soluciones industriales.',
            ],
        ];
        foreach ($tipos as $tipo) {
            TipoServicio::updateOrCreate(
                ['codigo' => $tipo['codigo']],
                $tipo + ['activo' => true],
            );
        }
    }
}
