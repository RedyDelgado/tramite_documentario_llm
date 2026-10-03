<?php

namespace App\Services;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

/**
 * La aplicación no se conecta como dueña de las tablas (9, 11): lee y escribe, pero en la auditoría solo
 * inserta y lee, y no puede desactivar su trigger. Migrar y respaldar lo hace el rol dueño (`pgsql_dueno`).
 */
class RolBaseDatosService
{
    /** Un solo rol (sin DB_DUENO_*): la aplicación es la dueña y no hay nada que restringir. */
    public function separado(): bool
    {
        return config('database.connections.pgsql.username') !== config('database.connections.pgsql_dueno.username');
    }

    /** Crea o actualiza el rol de la aplicación con su clave del .env y le da los permisos; idempotente. */
    public function aplicar(): void
    {
        if (! $this->separado()) {
            return;
        }

        $dueno = DB::connection('pgsql_dueno');
        $rol = $dueno->getQueryGrammar()->wrap(config('database.connections.pgsql.username'));
        $base = $dueno->getQueryGrammar()->wrap($dueno->getDatabaseName());
        $clave = $dueno->getPdo()->quote((string) config('database.connections.pgsql.password'));
        $existe = $dueno->selectOne('SELECT 1 FROM pg_roles WHERE rolname = ?', [config('database.connections.pgsql.username')]);

        $this->ejecutar($dueno, [
            ($existe ? "ALTER ROLE {$rol}" : "CREATE ROLE {$rol}")." LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE PASSWORD {$clave}",
            "GRANT CONNECT ON DATABASE {$base} TO {$rol}",
            "GRANT USAGE ON SCHEMA public TO {$rol}",
            "GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO {$rol}",
            "GRANT USAGE, SELECT, UPDATE ON ALL SEQUENCES IN SCHEMA public TO {$rol}",
            // La auditoría solo crece: el trigger lo impide y, además, el rol no tiene con qué intentarlo.
            "REVOKE UPDATE, DELETE, TRUNCATE ON auditoria FROM {$rol}",
        ]);
    }

    /** @param  list<string>  $sentencias */
    private function ejecutar(Connection $conexion, array $sentencias): void
    {
        $conexion->transaction(fn () => array_map(fn (string $s) => $conexion->statement($s), $sentencias));
    }
}
