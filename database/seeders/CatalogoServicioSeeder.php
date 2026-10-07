<?php

namespace Database\Seeders;

use App\Models\CatalogoServicio;
use App\Models\TipoServicio;
use Illuminate\Database\Seeder;

class CatalogoServicioSeeder extends Seeder
{
    public function run(): void
    {
        $catalogo = [
            'PID' => [
                ['PID-ACT', 'Actualización de P&ID'],
                ['PID-REG', 'Revisión y regularización de P&ID'],
                ['PID-VEC', 'Vectorización y normalización CAD'],
                ['PID-ASB', 'Elaboración de P&ID As-Built'],
                ['PID-INC', 'Incorporación de equipos o sistemas a P&ID'],
                ['PID-BLO', 'Desarrollo de bloques atributados'],
            ],
            'ACT-CRIT' => [
                ['ACT-LEV', 'Levantamiento y clasificación de activos críticos'],
                ['ACT-INS', 'Registro de instrumentación crítica'],
                ['ACT-CER', 'Gestión de certificados y calibraciones'],
                ['ACT-TAG', 'Identificación y normalización de TAGs'],
                ['ACT-MAT', 'Desarrollo de matrices de activos'],
                ['ACT-PLA', 'Instalación de placas y TAGs'],
            ],
            'LEV-TER' => [
                ['LEV-GEN', 'Levantamiento general de instalaciones'],
                ['LEV-FOC', 'Levantamiento focalizado de equipos o sistemas'],
                ['LEV-VAL', 'Validación documental contra condición real'],
                ['LEV-INS', 'Inspección y registro técnico'],
                ['LEV-TAG', 'Verificación de TAGs y elementos instalados'],
            ],
            'SEG-PROC' => [
                ['SEG-DIA', 'Diagnóstico PSI'],
                ['SEG-REG', 'Desarrollo y regularización documental PSI'],
                ['SEG-BRE', 'Gestión y trazabilidad de brechas PSI'],
                ['SEG-PCO', 'Desarrollo de matrices PCO y CSS'],
                ['SEG-PFD', 'Desarrollo de PFD y matrices C&E'],
                ['SEG-ALI', 'Revisión de dispositivos de alivio y condiciones críticas'],
            ],
            'LAYOUT' => [
                ['LAY-GEN', 'Layout industrial general'],
                ['LAY-EME', 'Layout de emergencia'],
                ['LAY-SEG', 'Layout de seguridad'],
                ['LAY-EVA', 'Plano de rutas de evacuación'],
                ['LAY-ACT', 'Plano de ubicación de equipos y activos'],
                ['LAY-ACTU', 'Actualización o vectorización de layout existente'],
            ],
            'MOD-3D' => [
                ['M3D-REC', 'Reconstrucción digital de componentes'],
                ['M3D-COM', 'Modelado 3D de componentes'],
                ['M3D-INS', 'Modelado 3D de instalaciones'],
                ['M3D-FAB', 'Planos técnicos de fabricación'],
                ['M3D-ENS', 'Vistas de ensamble y explosión'],
                ['M3D-CAD', 'Documentación CAD editable'],
                ['M3D-DIS', 'Diseño de soportes, protecciones o modificaciones menores'],
            ],
        ];

        foreach ($catalogo as $codigoTipo => $servicios) {
            $tipo = TipoServicio::where('codigo', $codigoTipo)->firstOrFail();

            foreach ($servicios as $indice => [$codigo, $nombre]) {
                CatalogoServicio::updateOrCreate(
                    ['codigo' => $codigo],
                    [
                        'tipo_servicio_id' => $tipo->id,
                        'nombre' => $nombre,
                        'orden' => $indice + 1,
                        'activo' => true,
                    ],
                );
            }
        }
    }
}
