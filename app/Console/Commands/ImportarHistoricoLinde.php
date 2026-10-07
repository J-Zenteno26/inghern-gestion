<?php

namespace App\Console\Commands;

use App\Domain\Importaciones\ImportarHistoricoLinde as Importador;
use App\Models\User;
use Illuminate\Console\Command;

class ImportarHistoricoLinde extends Command
{
    protected $signature = 'inghern:importar-linde {--usuario= : Correo del usuario responsable} {--confirmar : Ejecuta la escritura en la base de datos}';

    protected $description = 'Previsualiza o importa el histórico comercial seguro de LINDE';

    public function handle(Importador $importador): int
    {
        $resumen = $importador->resumen();
        $this->table(
            ['Entidad', 'Cantidad'],
            [
                ['Clientes', $resumen['clientes']],
                ['Contactos', $resumen['contactos']],
                ['Plantas', $resumen['plantas']],
                ['Cotizaciones', $resumen['cotizaciones']],
                ['Partidas', $resumen['partidas']],
            ],
        );
        $this->warn('Por validar: '.implode(', ', $resumen['por_validar']));
        $this->warn('Anulada: '.implode(', ', $resumen['anuladas']));

        if (! $this->option('confirmar')) {
            $this->info(
                'Previsualización terminada. No se escribió ningún registro.',
            );
            $this->line(
                'Ejecuta nuevamente con --confirmar cuando estés conforme.',
            );

            return self::SUCCESS;
        }

        $usuario = $this->option('usuario')
            ? User::where('email', $this->option('usuario'))->first()
            : User::where('activo', true)->orderBy('id')->first();

        if (! $usuario) {
            $this->error(
                'No existe un usuario activo para atribuir la importación.',
            );

            return self::FAILURE;
        }

        $resultado = $importador->ejecutar($usuario);
        $this->info(
            sprintf(
                'Importación terminada: %d cotizaciones creadas, %d existentes omitidas.',
                $resultado['creadas'],
                $resultado['omitidas'],
            ),
        );

        return self::SUCCESS;
    }
}
