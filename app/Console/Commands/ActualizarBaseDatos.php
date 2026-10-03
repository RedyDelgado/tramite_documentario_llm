<?php

namespace App\Console\Commands;

use App\Services\RolBaseDatosService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('db:actualizar {--seed : Además, cargar roles, reglas de correo no trámite y el superadmin}')]
#[Description('Migra con el rol dueño y deja al rol de la aplicación con sus permisos (la auditoría solo crece)')]
class ActualizarBaseDatos extends Command
{
    public function handle(RolBaseDatosService $roles): int
    {
        $migrar = $this->call('migrate', ['--database' => 'pgsql_dueno', '--force' => true, '--seed' => $this->option('seed')]);
        if ($migrar !== self::SUCCESS) {
            return $migrar;
        }

        // Después de migrar: las tablas nuevas también quedan con permisos.
        $roles->aplicar();
        $this->info($roles->separado()
            ? 'Permisos aplicados al rol «'.config('database.connections.pgsql.username').'»: la auditoría solo admite insertar y leer.'
            : 'Un solo rol (sin DB_DUENO_*): la aplicación es dueña de las tablas y la auditoría la protegen el trigger y la cadena de hashes.');

        return self::SUCCESS;
    }
}
