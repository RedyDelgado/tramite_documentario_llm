<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Expediente;
use App\Models\PlazoArea;
use App\Models\TipoTramite;
use App\Models\User;
use App\Services\ExpedienteService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConfiguracionPlazosTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->admin = User::factory()->create()->assignRole('superadmin');
    }

    private function asignar(TipoTramite $tipo, ?Area $area = null): Expediente
    {
        // Viernes 2 de octubre de 2026.
        $expediente = Expediente::factory()->create(['fecha_ingreso' => '2026-10-02 09:00:00', 'area_principal_id' => $area?->id]);

        return app(ExpedienteService::class)->asignarTipo($expediente, $tipo);
    }

    public function test_cambiar_un_plazo_surte_efecto_sin_alterar_expedientes_ya_ingresados(): void
    {
        $this->actingAs($this->admin)->post('/tipos-tramite', [
            'nombre' => 'Requerimiento de información', 'plazo_dias' => 3, 'tipo_dias' => 'habiles', 'aprueba_cierre' => null, 'activo' => true,
        ])->assertRedirect('/tipos-tramite');
        $tipo = TipoTramite::firstOrFail();
        $antiguo = $this->asignar($tipo);

        $this->actingAs($this->admin)->put("/tipos-tramite/{$tipo->id}", [
            'nombre' => 'Requerimiento de información', 'plazo_dias' => 10, 'tipo_dias' => 'habiles', 'aprueba_cierre' => null, 'activo' => true,
        ])->assertRedirect('/tipos-tramite');
        $nuevo = $this->asignar($tipo->fresh());

        $this->assertSame([3, '2026-10-07'], [$antiguo->fresh()->plazo_dias_aplicado, $antiguo->fresh()->fecha_limite->toDateString()]);
        $this->assertSame([10, '2026-10-16'], [$nuevo->plazo_dias_aplicado, $nuevo->fecha_limite->toDateString()]);

        $cambio = DB::table('auditoria')->where('accion', 'tipo_tramite.actualizado')->first();
        $this->assertSame(['plazo_dias' => 3], json_decode($cambio->valor_anterior, true));
        $this->assertSame(['plazo_dias' => 10], json_decode($cambio->valor_nuevo, true));
    }

    public function test_un_area_creada_desde_el_panel_sirve_de_inmediato_para_su_plazo_propio(): void
    {
        $tipo = TipoTramite::create(['nombre' => 'Invitación', 'plazo_dias' => 3]);
        $this->actingAs($this->admin)->post('/areas', [
            'nombre' => 'Filial Quillabamba', 'descripcion' => null, 'palabras_clave' => [], 'parent_id' => null, 'orden' => 0, 'activa' => true,
        ]);
        $filial = Area::where('nombre', 'Filial Quillabamba')->firstOrFail();

        $this->actingAs($this->admin)->post('/plazos', ['tipo_tramite_id' => $tipo->id, 'area_id' => $filial->id, 'plazo_dias' => 1])
            ->assertRedirect('/plazos');

        $this->assertSame('2026-10-05', $this->asignar($tipo, $filial)->fecha_limite->toDateString());
        $this->assertSame('2026-10-07', $this->asignar($tipo)->fecha_limite->toDateString());
    }

    public function test_quitar_un_plazo_por_area_es_logico_auditado_y_permite_volver_a_crearlo(): void
    {
        $tipo = TipoTramite::create(['nombre' => 'Invitación', 'plazo_dias' => 3]);
        $area = Area::factory()->create();
        $plazo = PlazoArea::create(['tipo_tramite_id' => $tipo->id, 'area_id' => $area->id, 'plazo_dias' => 1]);
        $datos = ['tipo_tramite_id' => $tipo->id, 'area_id' => $area->id, 'plazo_dias' => 2];

        $this->actingAs($this->admin)->post('/plazos', $datos)->assertSessionHasErrors('area_id');
        $this->actingAs($this->admin)->delete("/plazos/{$plazo->id}")->assertRedirect('/plazos');

        $this->assertSoftDeleted($plazo);
        $this->assertDatabaseHas('auditoria', ['accion' => 'plazo_area.quitado', 'entidad_id' => (string) $plazo->id]);
        $this->actingAs($this->admin)->post('/plazos', $datos)->assertSessionHasNoErrors();
    }

    public function test_no_se_repite_un_feriado_con_el_mismo_alcance(): void
    {
        $area = Area::factory()->create();
        $feriado = ['fecha' => '2026-12-08', 'descripcion' => 'Inmaculada Concepción', 'area_id' => null];

        $this->actingAs($this->admin)->post('/feriados', $feriado)->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post('/feriados', $feriado)->assertSessionHasErrors('fecha');
        $this->actingAs($this->admin)->post('/feriados', [...$feriado, 'area_id' => $area->id])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('auditoria', ['accion' => 'feriado.creado']);
    }

    public function test_solo_quien_gestiona_la_configuracion_edita_plazos(): void
    {
        $director = User::factory()->create()->assignRole('director');
        $delegado = User::factory()->create()->assignRole('administrativo');
        $delegado->givePermissionTo('configuracion.gestionar');

        foreach (['/tipos-tramite', '/plazos', '/feriados'] as $ruta) {
            $this->actingAs($director)->get($ruta)->assertForbidden();
            $this->actingAs($delegado)->get($ruta)->assertOk();
        }
        $this->actingAs($director)->post('/tipos-tramite', ['nombre' => 'X', 'tipo_dias' => 'habiles', 'activo' => true])->assertForbidden();
    }
}
