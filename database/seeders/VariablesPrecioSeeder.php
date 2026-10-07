<?php

namespace Database\Seeders;

use App\Models\TipoServicio;
use App\Models\VariablePrecio;
use Illuminate\Database\Seeder;

class VariablesPrecioSeeder extends Seeder
{
    public function run(): void
    {
        $variables = [
            [
                'codigo' => 'tamano_planta',
                'nombre' => 'Tamaño de planta',
                'descripcion' => 'Extensión física y cantidad aproximada de áreas involucradas.',
                'niveles' => [
                    ['pequena', 'Pequeña', 0.85],
                    ['mediana', 'Mediana', 1],
                    ['grande', 'Grande', 1.25],
                    ['extensa', 'Extensa', 1.55],
                ],
            ],
            [
                'codigo' => 'complejidad_tecnica',
                'nombre' => 'Complejidad técnica',
                'descripcion' => 'Diversidad de disciplinas, sistemas e interdependencias.',
                'niveles' => [
                    ['baja', 'Baja', 0.85],
                    ['media', 'Media', 1],
                    ['alta', 'Alta', 1.3],
                    ['critica', 'Crítica', 1.6],
                ],
            ],
            [
                'codigo' => 'calidad_informacion',
                'nombre' => 'Calidad de información',
                'descripcion' => 'Disponibilidad y confiabilidad de los antecedentes de entrada.',
                'niveles' => [
                    ['completa', 'Completa', 0.9],
                    ['parcial', 'Parcial', 1],
                    ['dispersa', 'Dispersa', 1.25],
                    ['inexistente', 'Inexistente', 1.5],
                ],
            ],
            [
                'codigo' => 'condicion_operacional',
                'nombre' => 'Condición operacional',
                'descripcion' => 'Restricciones de acceso y continuidad productiva durante la ejecución.',
                'niveles' => [
                    ['detenida', 'Planta detenida', 0.9],
                    ['parcial', 'Operación parcial', 1],
                    ['operacion', 'En operación', 1.2],
                    ['restringida', 'Acceso restringido', 1.35],
                ],
            ],
        ];
        $variablesPrecio = collect();

        foreach ($variables as $data) {
            $niveles = $data['niveles'];
            unset($data['niveles']);
            $variable = VariablePrecio::updateOrCreate(
                ['codigo' => $data['codigo']],
                $data + ['activo' => true],
            );
            $variablesPrecio->push($variable);

            foreach ($niveles as $orden => [$codigo, $nombre, $factor]) {
                $nivel = $variable->niveles()->firstOrNew([
                    'codigo' => $codigo,
                ]);
                $nivel->nombre = $nombre;
                $nivel->orden = $orden + 1;

                if (! $nivel->exists) {
                    $nivel->factor = $factor;
                }

                $nivel->save();
            }
        }

        $relaciones = $variablesPrecio
            ->mapWithKeys(
                fn (VariablePrecio $variable) => [
                    $variable->id => ['peso' => 1, 'requerida' => true],
                ],
            )
            ->all();

        TipoServicio::query()
            ->where('activo', true)
            ->each(
                fn (TipoServicio $tipo) => $tipo
                    ->variablesPrecio()
                    ->syncWithoutDetaching($relaciones),
            );
    }
}
