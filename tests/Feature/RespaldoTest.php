<?php

namespace Tests\Feature;

use App\Models\Area;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\ExecutableFinder;
use Tests\TestCase;

/** pg_dump y pg_restore usan su propia conexión: los datos deben estar confirmados, no en una transacción de test. */
class RespaldoTest extends TestCase
{
    use DatabaseMigrations;

    private string $directorio;

    private string $modelos;

    protected function setUp(): void
    {
        parent::setUp();

        if (! (new ExecutableFinder)->find('pg_restore')) {
            $this->markTestSkipped('Requiere pg_dump y pg_restore (corren en el contenedor app).');
        }

        Storage::fake('originales');
        $this->directorio = sys_get_temp_dir().'/respaldos-test-'.uniqid();
        $this->modelos = sys_get_temp_dir().'/modelos-test-'.uniqid();
        config(['tramite.respaldo.directorio' => $this->directorio, 'tramite.respaldo.modelos' => $this->modelos]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directorio);
        File::deleteDirectory($this->modelos);
        parent::tearDown();
    }

    public function test_restaurar_un_respaldo_devuelve_los_mismos_datos_y_archivos(): void
    {
        $area = Area::factory()->create(['nombre' => 'Mesa de partes']);
        Storage::disk('originales')->put('ab/abcdef.pdf', 'contenido original');
        File::ensureDirectoryExists($this->modelos.'/v20261001000000');
        File::put($this->modelos.'/v20261001000000/meta.json', '{"ejemplos": 40}');

        $this->artisan('respaldo:crear')->assertSuccessful();
        $carpeta = File::directories($this->directorio)[0];
        $this->assertFileExists($carpeta.'/SHA256SUMS');

        // Lo que pasa después del respaldo: cambios, archivos perdidos y datos nuevos.
        $area->update(['nombre' => 'Cambiado después']);
        Area::factory()->create(['nombre' => 'Área posterior']);
        Storage::disk('originales')->delete('ab/abcdef.pdf');
        File::deleteDirectory($this->modelos);

        $this->artisan('respaldo:restaurar', ['carpeta' => $carpeta, '--force' => true])->assertSuccessful();

        $this->assertSame(['Mesa de partes'], DB::table('areas')->pluck('nombre')->all());
        $this->assertSame('contenido original', Storage::disk('originales')->get('ab/abcdef.pdf'));
        $this->assertSame('{"ejemplos": 40}', File::get($this->modelos.'/v20261001000000/meta.json'));
        $this->artisan('auditoria:verificar')->assertSuccessful();
        $this->assertSame(1, DB::table('auditoria')->where('accion', 'respaldo.restaurado')->count());
    }

    public function test_un_respaldo_danado_se_rechaza_sin_tocar_la_base(): void
    {
        Area::factory()->create(['nombre' => 'Mesa de partes']);
        $this->artisan('respaldo:crear')->assertSuccessful();
        $carpeta = File::directories($this->directorio)[0];

        DB::table('areas')->update(['nombre' => 'Actual']);
        File::append($carpeta.'/base.dump', 'x');

        $this->artisan('respaldo:restaurar', ['carpeta' => $carpeta, '--force' => true])
            ->expectsOutputToContain('base.dump no coincide')
            ->assertFailed();
        $this->assertSame(['Actual'], DB::table('areas')->pluck('nombre')->all());
    }

    public function test_borra_los_respaldos_vencidos_y_nada_mas(): void
    {
        $this->travelTo('2026-10-02 02:30:00');
        File::ensureDirectoryExists($this->directorio.'/2026-08-01_023000');
        File::ensureDirectoryExists($this->directorio.'/2026-09-25_023000');
        File::ensureDirectoryExists($this->directorio.'/copia-manual');

        $this->artisan('respaldo:crear')->assertSuccessful();

        $this->assertSame(
            ['2026-09-25_023000', '2026-10-02_023000', 'copia-manual'],
            collect(File::directories($this->directorio))->map(fn ($d) => basename($d))->sort()->values()->all(),
        );
    }
}
