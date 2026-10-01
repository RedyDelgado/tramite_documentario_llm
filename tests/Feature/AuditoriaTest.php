<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Auditoria;
use App\Models\User;
use App\Services\AuditoriaService;
use Database\Seeders\RolesSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditoriaTest extends TestCase
{
    use RefreshDatabase;

    public function test_cada_registro_encadena_el_hash_del_anterior(): void
    {
        $servicio = app(AuditoriaService::class);
        $servicio->registrar('prueba.uno', 'prueba', 1, despues: ['b' => 2, 'a' => ['y' => 1, 'x' => 'ñandú']]);
        $servicio->registrar('prueba.dos', 'prueba', 2);

        [$primero, $segundo] = Auditoria::orderBy('id')->get();

        $this->assertNull($primero->hash_anterior);
        $this->assertSame($primero->hash_registro, $segundo->hash_anterior);
        $this->artisan('auditoria:verificar')->assertSuccessful();
    }

    public function test_la_base_rechaza_modificar_o_borrar(): void
    {
        app(AuditoriaService::class)->registrar('prueba', 'prueba', 1);

        $this->assertLanzaError(fn () => DB::table('auditoria')->update(['accion' => 'otra']));
        $this->assertLanzaError(fn () => DB::table('auditoria')->delete());
        $this->assertLanzaError(fn () => DB::statement('TRUNCATE auditoria'));
    }

    public function test_verificar_detecta_una_alteracion_manual(): void
    {
        $servicio = app(AuditoriaService::class);
        $servicio->registrar('prueba.uno', 'prueba', 1, despues: ['monto' => 10]);
        $servicio->registrar('prueba.dos', 'prueba', 2);

        // Un dueño de la base puede desactivar el trigger; la cadena de hashes igual lo delata.
        DB::statement('ALTER TABLE auditoria DISABLE TRIGGER auditoria_sin_cambios');
        DB::table('auditoria')->where('accion', 'prueba.uno')->update(['valor_nuevo' => json_encode(['monto' => 99])]);
        DB::statement('ALTER TABLE auditoria ENABLE TRIGGER auditoria_sin_cambios');

        $this->artisan('auditoria:verificar')->assertFailed();
    }

    public function test_los_cambios_de_un_area_quedan_con_valor_anterior_y_usuario(): void
    {
        $this->seed(RolesSeeder::class);
        $admin = User::factory()->create()->assignRole('superadmin');
        $area = Area::factory()->create(['nombre' => 'Mesa de partes', 'orden' => 1]);

        $this->actingAs($admin)->put("/areas/{$area->id}", [
            'nombre' => 'Mesa de partes central', 'descripcion' => $area->descripcion, 'palabras_clave' => $area->palabras_clave,
            'parent_id' => null, 'orden' => 1, 'activa' => true,
        ]);

        $evento = Auditoria::de($area)->sole();
        $this->assertSame('area.actualizada', $evento->accion);
        $this->assertSame($admin->id, $evento->usuario_id);
        $this->assertSame(['nombre' => 'Mesa de partes'], $evento->valor_anterior);
        $this->assertSame(['nombre' => 'Mesa de partes central'], $evento->valor_nuevo);
        $this->assertNotNull($evento->ip);
    }

    private function assertLanzaError(callable $operacion): void
    {
        try {
            DB::transaction($operacion);
            $this->fail('La base permitió alterar la auditoría.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('solo inserción', $e->getMessage());
        }
    }
}
