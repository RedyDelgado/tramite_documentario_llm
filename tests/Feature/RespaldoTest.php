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

    /** La carpeta de respaldo más reciente (al lado vive el almacén de originales, `archivos/`). */
    private function ultimo(): string
    {
        return collect(File::directories($this->directorio))->filter(fn ($d) => preg_match('/\d{4}-\d{2}-\d{2}_\d{6}$/', $d))->sort()->last();
    }

    private function cifrar(): void
    {
        config(['tramite.respaldo.clave' => base64_encode(random_bytes(32))]);
    }

    public function test_restaurar_un_respaldo_devuelve_los_mismos_datos_y_archivos(): void
    {
        $area = Area::factory()->create(['nombre' => 'Mesa de partes']);
        Storage::disk('originales')->put('ab/abcdef.pdf', 'contenido original');
        File::ensureDirectoryExists($this->modelos.'/v20261001000000');
        File::put($this->modelos.'/v20261001000000/meta.json', '{"ejemplos": 40}');

        $this->artisan('respaldo:crear')->assertSuccessful();
        $carpeta = $this->ultimo();
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
        $carpeta = $this->ultimo();

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

    public function test_un_respaldo_cifrado_no_se_lee_sin_la_clave_y_se_restaura_con_ella(): void
    {
        $this->cifrar();
        Area::factory()->create(['nombre' => 'Mesa de partes']);
        Storage::disk('originales')->put('adjuntos/ab/oficio.pdf', 'OFICIO MÚLTIPLE N° 045-2026 confidencial');
        // Más de dos trozos de cifrado (1 MB cada uno): el archivo vuelve completo.
        $grande = random_bytes(2_500_000);
        Storage::disk('originales')->put('adjuntos/cd/escaneo.pdf', $grande);

        $this->artisan('respaldo:crear')->assertSuccessful();
        $carpeta = $this->ultimo();

        $this->assertFileDoesNotExist($carpeta.'/base.dump');
        $this->assertStringNotContainsString('PGDMP', File::get($carpeta.'/base.dump.cifrado'));
        $this->assertStringNotContainsString('Mesa de partes', File::get($carpeta.'/base.dump.cifrado'));
        $oficio = File::get($this->directorio.'/archivos/'.substr($sha = hash('sha256', 'OFICIO MÚLTIPLE N° 045-2026 confidencial'), 0, 2)."/{$sha}.cifrado");
        $this->assertStringNotContainsString('confidencial', $oficio);

        DB::table('areas')->update(['nombre' => 'Actual']);
        Storage::disk('originales')->delete(['adjuntos/ab/oficio.pdf', 'adjuntos/cd/escaneo.pdf']);
        $this->artisan('respaldo:restaurar', ['carpeta' => $carpeta, '--force' => true])->assertSuccessful();
        $this->assertSame($grande, Storage::disk('originales')->get('adjuntos/cd/escaneo.pdf'));

        $this->assertSame(['Mesa de partes'], DB::table('areas')->pluck('nombre')->all());
        $this->assertSame('OFICIO MÚLTIPLE N° 045-2026 confidencial', Storage::disk('originales')->get('adjuntos/ab/oficio.pdf'));
    }

    public function test_la_clave_equivocada_o_un_original_alterado_se_rechazan_sin_tocar_la_base(): void
    {
        $this->cifrar();
        Area::factory()->create(['nombre' => 'Mesa de partes']);
        Storage::disk('originales')->put('adjuntos/ab/oficio.pdf', 'contenido original');
        $this->artisan('respaldo:crear')->assertSuccessful();
        $carpeta = $this->ultimo();
        DB::table('areas')->update(['nombre' => 'Actual']);
        $claveBuena = config('tramite.respaldo.clave');

        $this->cifrar();
        $this->artisan('respaldo:restaurar', ['carpeta' => $carpeta, '--force' => true])
            ->expectsOutputToContain('la clave no es la del respaldo')->assertFailed();

        // El almacén no está en SHA256SUMS de la carpeta: lo protege el cifrado autenticado.
        config(['tramite.respaldo.clave' => $claveBuena]);
        $almacenado = File::allFiles($this->directorio.'/archivos')[0]->getPathname();
        $bytes = File::get($almacenado);
        File::put($almacenado, substr($bytes, 0, -1).chr(ord($bytes[-1]) ^ 1));
        $this->artisan('respaldo:restaurar', ['carpeta' => $carpeta, '--force' => true])
            ->expectsOutputToContain('no se pudo descifrar')->assertFailed();

        $this->assertSame(['Actual'], DB::table('areas')->pluck('nombre')->all());
    }

    public function test_los_originales_se_copian_una_vez_y_la_retencion_borra_lo_que_nadie_usa(): void
    {
        $this->travelTo('2026-09-01 02:30:00');
        Storage::disk('originales')->put('adjuntos/aa/viejo.pdf', 'solo en el primer respaldo');
        Storage::disk('originales')->put('adjuntos/bb/comun.pdf', 'en todos');
        $this->artisan('respaldo:crear')->assertSuccessful();

        $this->travelTo('2026-09-02 02:30:00');
        $this->artisan('respaldo:crear')->assertSuccessful();
        // El segundo respaldo no copia de nuevo lo que no cambió.
        $this->assertCount(2, File::allFiles($this->directorio.'/archivos'));

        $this->travelTo('2026-10-15 02:30:00');
        Storage::disk('originales')->delete('adjuntos/aa/viejo.pdf');
        Storage::disk('originales')->put('adjuntos/cc/nuevo.pdf', 'nuevo');
        $this->artisan('respaldo:crear')->assertSuccessful();

        // Vencieron los dos primeros: «viejo» ya no lo usa nadie; «común» y «nuevo», sí.
        $this->assertSame(['2026-10-15_023000', 'archivos'], collect(File::directories($this->directorio))->map(fn ($d) => basename($d))->sort()->values()->all());
        $this->assertEqualsCanonicalizing(
            [hash('sha256', 'en todos'), hash('sha256', 'nuevo')],
            collect(File::allFiles($this->directorio.'/archivos'))->map(fn ($f) => $f->getFilename())->all(),
        );
    }
}
