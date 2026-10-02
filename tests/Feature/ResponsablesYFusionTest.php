<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\AreaResponsable;
use App\Models\Expediente;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ResponsablesYFusionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->admin = User::factory()->create()->assignRole('superadmin');
    }

    private function asignar(Area $area, User $user, string $tipo = 'titular', string $desde = '2026-01-01', ?string $hasta = null): TestResponse
    {
        return $this->actingAs($this->admin)->post('/responsables', [
            'area_id' => $area->id, 'user_id' => $user->id, 'tipo' => $tipo, 'vigente_desde' => $desde, 'vigente_hasta' => $hasta,
        ]);
    }

    public function test_asignar_un_titular_le_da_visibilidad_de_inmediato(): void
    {
        $area = Area::factory()->create();
        $coordinador = User::factory()->create()->assignRole('coordinador');
        $expediente = Expediente::factory()->create(['area_principal_id' => $area->id]);
        $this->assertFalse(Expediente::visiblesPara($coordinador)->whereKey($expediente->id)->exists());

        $this->asignar($area, $coordinador)->assertRedirect('/responsables');

        $this->assertTrue(Expediente::visiblesPara($coordinador)->whereKey($expediente->id)->exists());
        $this->assertDatabaseHas('auditoria', ['accion' => 'responsable.asignado']);
    }

    public function test_un_solo_titular_a_la_vez_y_suplentes_sin_limite(): void
    {
        $area = Area::factory()->create();
        [$ana, $luis, $rosa] = User::factory()->count(3)->create();

        $this->asignar($area, $ana, desde: '2026-01-01', hasta: '2026-06-30')->assertSessionHasNoErrors();
        $this->asignar($area, $luis, desde: '2026-06-01')->assertSessionHasErrors('vigente_desde');
        $this->asignar($area, $luis, desde: '2026-07-01')->assertSessionHasNoErrors();
        $this->asignar($area, $rosa, 'suplente', '2026-01-01')->assertSessionHasNoErrors();
        $this->asignar($area, $ana, 'suplente', '2026-03-01')->assertSessionHasNoErrors();
    }

    public function test_un_usuario_inactivo_no_puede_ser_responsable(): void
    {
        $this->asignar(Area::factory()->create(), User::factory()->create(['activo' => false]))->assertSessionHasErrors('user_id');
    }

    public function test_fusionar_reasigna_expedientes_y_dependientes_y_desactiva_el_origen(): void
    {
        $origen = Area::factory()->create();
        $destino = Area::factory()->create();
        $dependiente = Area::factory()->create(['parent_id' => $origen->id]);
        $expedientes = Expediente::factory()->count(2)->create(['area_principal_id' => $origen->id]);
        $coordinador = User::factory()->create()->assignRole('coordinador');
        AreaResponsable::create(['area_id' => $destino->id, 'user_id' => $coordinador->id, 'tipo' => 'titular', 'vigente_desde' => '2026-01-01']);

        $this->actingAs($this->admin)->post("/areas/{$origen->id}/fusionar", ['destino_id' => $destino->id])
            ->assertInertiaFlash('toast.mensaje', "«{$origen->nombre}» se fusionó en «{$destino->nombre}»: 2 expedientes reasignados.");

        $this->assertFalse($origen->fresh()->activa);
        $this->assertSame($destino->id, $dependiente->fresh()->parent_id);
        $this->assertSame(2, Expediente::visiblesPara($coordinador)->whereKey($expedientes->pluck('id'))->count());
        $fusion = DB::table('auditoria')->where('accion', 'area.fusionada')->first();
        $this->assertSame($expedientes->pluck('id')->all(), json_decode($fusion->valor_nuevo, true)['expedientes']);
    }

    public function test_no_se_fusiona_en_si_misma_ni_en_una_dependiente_ni_en_una_inactiva(): void
    {
        $escuela = Area::factory()->create();
        $hija = Area::factory()->create(['parent_id' => $escuela->id]);
        $nieta = Area::factory()->create(['parent_id' => $hija->id]);
        $inactiva = Area::factory()->create(['activa' => false]);

        foreach ([$escuela, $nieta, $inactiva] as $destino) {
            $this->actingAs($this->admin)->post("/areas/{$escuela->id}/fusionar", ['destino_id' => $destino->id])
                ->assertInertiaFlash('toast.tipo', 'error');
        }
        $this->assertTrue($escuela->fresh()->activa);
    }

    public function test_sin_permiso_de_configuracion_no_se_asignan_responsables_ni_se_fusiona(): void
    {
        $director = User::factory()->create()->assignRole('director');
        [$a, $b] = Area::factory()->count(2)->create();

        $this->actingAs($director)->get('/responsables')->assertForbidden();
        $this->actingAs($director)->post("/areas/{$a->id}/fusionar", ['destino_id' => $b->id])->assertForbidden();
    }
}
