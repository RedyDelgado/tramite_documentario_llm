<?php

namespace Tests\Feature;

use App\Services\RolBaseDatosService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\ExecutableFinder;
use Tests\TestCase;

/** Los permisos se dan con el rol dueño y se prueban con otra conexión: no caben en una transacción de test. */
class RolBaseDatosTest extends TestCase
{
    use DatabaseMigrations;

    private const ROL = 'tramite_app_test';

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.pgsql' => [
            ...config('database.connections.pgsql_dueno'),
            'username' => self::ROL,
            'password' => 'clave-de-prueba',
        ]]);
        DB::purge('pgsql');
    }

    protected function tearDown(): void
    {
        DB::purge('pgsql');
        if (DB::connection('pgsql_dueno')->selectOne('SELECT 1 FROM pg_roles WHERE rolname = ?', [self::ROL])) {
            DB::connection('pgsql_dueno')->statement('DROP OWNED BY '.self::ROL);
            DB::connection('pgsql_dueno')->statement('DROP ROLE '.self::ROL);
        }
        parent::tearDown();
    }

    /** El mensaje de PostgreSQL al rol de la aplicación, o null si la sentencia se permite. */
    private function comoAplicacion(string $sentencia): ?string
    {
        try {
            DB::connection('pgsql')->statement($sentencia);

            return null;
        } catch (QueryException $e) {
            return $e->getMessage();
        }
    }

    public function test_la_aplicacion_trabaja_pero_no_puede_alterar_la_auditoria(): void
    {
        app(RolBaseDatosService::class)->aplicar();

        // Lo de todos los días: leer y escribir en las tablas de negocio, insertar en la auditoría.
        $this->assertNull($this->comoAplicacion('UPDATE expedientes SET asunto = asunto WHERE false'));
        $this->assertNull($this->comoAplicacion('DELETE FROM envios WHERE false'));
        $this->assertNull($this->comoAplicacion('INSERT INTO auditoria SELECT * FROM auditoria WHERE false'));
        $this->assertNull($this->comoAplicacion('SELECT count(*) FROM auditoria'));

        // Lo que nunca: cambiar o borrar la auditoría, ni desactivar su trigger o crear tablas.
        $this->assertStringContainsString('permission denied', (string) $this->comoAplicacion('UPDATE auditoria SET accion = accion WHERE false'));
        $this->assertStringContainsString('permission denied', (string) $this->comoAplicacion('DELETE FROM auditoria WHERE false'));
        $this->assertStringContainsString('permission denied', (string) $this->comoAplicacion('TRUNCATE auditoria'));
        $this->assertStringContainsString('must be owner', (string) $this->comoAplicacion('ALTER TABLE auditoria DISABLE TRIGGER ALL'));
        $this->assertStringContainsString('permission denied', (string) $this->comoAplicacion('CREATE TABLE intrusa (id int)'));
    }

    public function test_aplicar_es_idempotente_y_actualiza_la_clave(): void
    {
        app(RolBaseDatosService::class)->aplicar();
        config(['database.connections.pgsql.password' => 'clave-nueva']);
        app(RolBaseDatosService::class)->aplicar();
        DB::purge('pgsql');

        $this->assertNull($this->comoAplicacion('SELECT 1'));
    }

    public function test_restaurar_un_respaldo_devuelve_los_permisos_al_rol_de_la_aplicacion(): void
    {
        if (! (new ExecutableFinder)->find('pg_restore')) {
            $this->markTestSkipped('Requiere pg_dump y pg_restore (corren en el contenedor app).');
        }
        Storage::fake('originales');
        $directorio = sys_get_temp_dir().'/respaldos-rol-'.uniqid();
        config(['tramite.respaldo.directorio' => $directorio, 'tramite.respaldo.modelos' => $directorio.'-modelos']);
        app(RolBaseDatosService::class)->aplicar();

        try {
            $this->artisan('respaldo:crear')->assertSuccessful();
            // La restauración recrea las tablas sin los permisos del respaldo y los vuelve a dar.
            $this->artisan('respaldo:restaurar', ['carpeta' => File::directories($directorio)[0], '--force' => true])->assertSuccessful();
        } finally {
            File::deleteDirectory($directorio);
        }

        DB::purge('pgsql');
        $this->assertNull($this->comoAplicacion('SELECT count(*) FROM expedientes'));
        $this->assertStringContainsString('permission denied', (string) $this->comoAplicacion('DELETE FROM auditoria WHERE false'));
    }

    public function test_con_un_solo_rol_no_hace_nada(): void
    {
        config(['database.connections.pgsql.username' => config('database.connections.pgsql_dueno.username')]);

        $this->assertFalse(app(RolBaseDatosService::class)->separado());
        app(RolBaseDatosService::class)->aplicar();
        $this->assertNull(DB::connection('pgsql_dueno')->selectOne('SELECT 1 FROM pg_roles WHERE rolname = ?', [self::ROL]));
    }
}
