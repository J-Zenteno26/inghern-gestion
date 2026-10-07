<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdminUser extends Command
{
    protected $signature = 'inghern:create-admin {--name=} {--email=}';

    protected $description = 'Crea o actualiza el usuario administrador inicial';

    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Nombre');
        $email = $this->option('email') ?: $this->ask('Correo electrónico');
        $password = $this->secret('Contraseña (mínimo 12 caracteres)');
        if (mb_strlen((string) $password) < 12) {
            $this->error('La contraseña debe tener al menos 12 caracteres.');

            return self::FAILURE;
        }
        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'rol' => 'administrador',
                'activo' => true,
            ],
        );
        $this->info('Administrador disponible para iniciar sesión.');

        return self::SUCCESS;
    }
}
