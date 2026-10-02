<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\DocumentoSaliente;
use App\Models\TipoDocumento;
use App\Models\User;
use App\Services\SalienteService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Aprobaciones simultáneas en procesos separados (7.3.4): números sin saltos ni duplicados por tipo, área y año. */
class SalienteNumeracionConcurrenteTest extends TestCase
{
    use DatabaseMigrations;

    public function test_aprobaciones_simultaneas_reciben_numeros_consecutivos_sin_saltos_ni_repetidos(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('Requiere la extensión pcntl (corre en el contenedor app y en CI).');
        }
        $this->seed(RolesSeeder::class);
        Storage::fake('originales');
        $director = User::factory()->create()->assignRole('director');
        $autor = User::factory()->create();
        $area = Area::factory()->create(['siglas' => 'DGA']);
        $oficio = TipoDocumento::create(['nombre' => 'Oficio']);
        $procesos = 4;
        $porProceso = 2;
        $ids = collect(range(1, $procesos * $porProceso))->map(function () use ($oficio, $area, $autor) {
            $s = DocumentoSaliente::create([
                'tipo_documento_id' => $oficio->id, 'area_id' => $area->id, 'asunto' => 'Circular', 'cuerpo' => 'Texto',
                'destinatarios' => [['email' => 'a@b.pe', 'nombre' => null]], 'creado_por' => $autor->id,
            ]);
            $s->forceFill(['estado' => 'en_revision'])->save();

            return $s->id;
        })->chunk($porProceso);

        // Cada hijo abre su propia conexión; compartir el socket del padre corrompería ambas.
        DB::disconnect();
        $hijos = [];
        foreach ($ids as $lote) {
            $pid = pcntl_fork();
            if ($pid === 0) {
                foreach ($lote as $id) {
                    app(SalienteService::class)->aprobar(DocumentoSaliente::findOrFail($id), $director);
                }
                posix_kill(getmypid(), SIGKILL);
            }
            $hijos[] = $pid;
        }
        foreach ($hijos as $pid) {
            pcntl_waitpid($pid, $estado);
        }

        $secuencias = DocumentoSaliente::orderBy('secuencia')->pluck('secuencia')->all();
        $this->assertSame(range(1, $procesos * $porProceso), $secuencias);
        $this->assertSame('OFICIO N.º 008-2026-DGA', DocumentoSaliente::where('secuencia', 8)->value('numero'));
    }
}
