<?php

namespace Tests\Feature;

use App\Services\SecuenciaService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Concurrencia real: procesos separados con su propia conexión (no cabe en una transacción de test). */
class SecuenciaConcurrenteTest extends TestCase
{
    use DatabaseMigrations;

    public function test_altas_simultaneas_reciben_numeros_consecutivos_sin_saltos_ni_repetidos(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('Requiere la extensión pcntl (corre en el contenedor app y en CI).');
        }

        Schema::create('numeros_prueba', fn (Blueprint $t) => $t->unsignedInteger('n'));
        $procesos = 8;
        $porProceso = 10;

        // Cada hijo abre su propia conexión; compartir el socket del padre corrompería ambas.
        DB::disconnect();
        $hijos = [];
        for ($i = 0; $i < $procesos; $i++) {
            $pid = pcntl_fork();
            if ($pid === 0) {
                $servicio = app(SecuenciaService::class);
                for ($j = 0; $j < $porProceso; $j++) {
                    DB::transaction(fn () => DB::table('numeros_prueba')->insert(['n' => $servicio->siguiente('registro', 2030)]));
                }
                // Termina sin ejecutar los manejadores de cierre de PHPUnit del proceso padre.
                posix_kill(getmypid(), SIGKILL);
            }
            $hijos[] = $pid;
        }
        foreach ($hijos as $pid) {
            pcntl_waitpid($pid, $estado);
        }

        $numeros = DB::table('numeros_prueba')->orderBy('n')->pluck('n')->all();
        $this->assertSame(range(1, $procesos * $porProceso), $numeros);

        Schema::drop('numeros_prueba');
    }
}
